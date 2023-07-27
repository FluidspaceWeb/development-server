<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\IntegrationModule;

require_once __DIR__.'/../environment.class.php';
require_once __DIR__.'/Auths/OAuth2.php';
require_once __DIR__.'/HTTPRequest.php';

use core\classes\environment;
use core\classes\IntegrationModule\Auths\OAuth2;

use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

class AuthHandler
{
	private ObjectId $user_id;
	private ObjectId $integration_oid;
	private Client $mdb;
	private string $provider_name;
	private string $access_type;
	private string $selected_auth_type;
	private array $authProviderConfig;
	private string $addedAccountId;
	
	public static string $ACCESS_TYPE_PRIVATE = 'private';
	public static string $ACCESS_TYPE_SHARED = 'shared';
	
	/*
	 * TODO: create class for containing array userIntegrationAuth, iConfig, iProviderConfig.
	 * whenever any of these values are fetched, construct them to appropriate class
	 * use these classes return types and parameter type declaration.
	 * It will help in property and type-hinting of the data within these objects.
	 * Thereby, removing dependence on checking if array field exists or not, and worrying about fieldNames.
	 * Example: $account_auth = new accountAuth(array $userAccountAuth);
	 * Usage: $profile = $account_auth->getProfile();
	*/
	
	public function __construct(ObjectId $user_id, ObjectId $integration_id)
	{
		$this->user_id = $user_id;
		$this->integration_oid = $integration_id;
		$this->mdb = getConnection();
	}
	
	public static function isUrlAllowedHost(string $url, array &$allowedHosts): bool
	{
		// assuming raw url has been passed from frontend, if not then false will be returned in subsequent checks.
		$url = substr(trim($url), 0, 2000);

		if(filter_var($url, FILTER_VALIDATE_URL) === false
			|| ($url = filter_var(trim($url), FILTER_SANITIZE_URL)) === false
			|| ($urlComponents = parse_url($url)) === false
		) {
			return false;
		}
		
		/* FIXME: DISABLED FOR DEVELOPMENT PURPOSE
		if(empty($urlComponents['scheme'])|| $urlComponents['scheme'] !== 'https' || empty($urlComponents['host'])) {
			// to ensure only https is allowed and host has been extracted
			return false;
		}
		*/
		if(empty($urlComponents['scheme']) || empty($urlComponents['host'])) {
			// to ensure only https is allowed and host has been extracted
			return false;
		}

		$hostName = $urlComponents['scheme'].'://'.$urlComponents['host'];
		return in_array($hostName, $allowedHosts, true);
	}

	private function getIntegrationConfig(): array
	{
		$modLib = $this->mdb->module_library;
		$config = $modLib->integration_configs->findOne(
			['_id' => $this->integration_oid],
			['projection' => ['_id' => 0]]
		);
		return json_decode(json_encode($config), true);
	}
	
	private function areFieldsSetForAuthAndSave(): bool
	{
		if(!empty($this->provider_name) && !empty($this->access_type)) {
			return true;
		}
		return false;
	}
	
	private function getAuthCredentials(array $request_payload): array
	{
		if(empty($this->authProviderConfig)) {
			// if auth provider not set before calling this method.
			return Integration::defaultResponse(0, 'Auth provider not set.');
		}
		
		// execute appropriate auth handler
		$this->selected_auth_type = $this->authProviderConfig['authType'];
		try {
			if($this->selected_auth_type === 'OAuth2') {
				$auth = new OAuth2($this->authProviderConfig);
				if(empty($request_payload['auth_code'])) {
					return Integration::defaultResponse(0, 'Undefined auth code');
				}
				return $auth->getAllCredentials($request_payload['auth_code']);
			}
		}
		catch( Exception $e) {
			return Integration::defaultResponse($e->getCode(), $e->getMessage());
		}

		return Integration::defaultResponse(0, 'Unsupported authorisation type.');
	}

	protected function getIntegrationAuths(string &$access_type): array
	{
		$integration_id = environment::hashOid($this->integration_oid);
		$options = [
			'projection' => [
				'_id' => 0,
				'integration_auths.'.$integration_id => 1
			]
		];
		if($access_type === AuthHandler::$ACCESS_TYPE_PRIVATE) {
			$users = $this->mdb->environment->user_configs;
			$accounts = (array)$users->findOne(['_id' => $this->user_id], $options);
		}
		/*elseif($access_type === AuthHandler::$ACCESS_TYPE_SHARED) {
			$workspaces = $this->mdb->environment->workspaces;
			$accounts = (array)$workspaces->findOne(['_id' => $this->workspace_oid], $options);
		}*/

		if(!empty($accounts['integration_auths']) && isset($accounts['integration_auths'][$integration_id])) {
			// convert to associative array and return
			return json_decode(json_encode($accounts['integration_auths'][$integration_id]), true);
		}
		return [];
	}
	
	protected function getAccountAuth(string $account_id): array
	{
		// gets auth user specific auth detail (for private and shared both)
		$user_integration_auth = $this->getIntegrationAuths($this->access_type);
		
		// find account auth
		$authIndex = -1;
		for($i = 0; $i < count($user_integration_auth); $i++) {
			if($user_integration_auth[$i]['_id'] === $account_id) {
				$authIndex = $i;
				break;
			}
		}
		
		if($authIndex !== -1) {
			return $user_integration_auth[$authIndex];
		}
		return [];
	}
	
	private function saveAuthorisedCredential(array &$credentials): bool
	{
		$integration_hashId = environment::hashOid($this->integration_oid);
		$update = null;
		$accountData = [
			'_id' => uniqid(),
			'auth_on' => new UTCDateTime(),
			'auth_type' => $this->selected_auth_type,
			'auth_provider_name' => $this->provider_name,
			'credentials' => $credentials
		];
		
		// save auth
		$updateQuery = [
			'$push' => [
				'integration_auths.'.$integration_hashId => $accountData
			]
		];
		if($this->access_type === self::$ACCESS_TYPE_PRIVATE) {
			$users = $this->mdb->environment->user_configs;
			$update = $users->updateOne(['_id' => $this->user_id], $updateQuery);
		}
		/*elseif($this->access_type === self::$ACCESS_TYPE_SHARED) {
			$workspaces = $this->mdb->environment->workspaces;
			$update = $workspaces->updateOne(['_id' => $this->workspace_oid], $updateQuery);
		}*/
		
		if($update !== null && $update->isAcknowledged() && $update->getModifiedCount() === 1) {
			$this->addedAccountId = $accountData['_id'];
			return true;
		}
		return false;
	}
	
	private function updateAuthorisedCredential(string $account_id, array &$credentials): bool
	{
		$integration_hashId = environment::hashOid($this->integration_oid);
		$updateQuery = [
			'$set' => []
		];
		foreach($credentials as $key => $val) { // generate query to update specific fields instead of replacing credentials[].
			$fieldIdentifier = 'integration_auths.'.$integration_hashId.'.$[auth].credentials.'.$key;
			$updateQuery['$set'][$fieldIdentifier] = $val;
		}
		$options = [
			'arrayFilters' => [
				['auth._id' => $account_id]
			]
		];

		// save auth
		$update = null;
		if($this->access_type === self::$ACCESS_TYPE_PRIVATE) {
			$users = $this->mdb->environment->user_configs;
			$update = $users->updateOne(['_id' => $this->user_id], $updateQuery, $options);
		}
		/*elseif($this->access_type === self::$ACCESS_TYPE_SHARED) {
			$workspaces = $this->mdb->environment->workspaces;
			$update = $workspaces->updateOne(['_id' => $this->workspace_oid], $updateQuery, $options);
		}*/
		
		return ($update !== null && $update->isAcknowledged() && $update->getModifiedCount() === 1);
	}
	
	private function updateSessionCredentials(string $account_id, array $new_session_credentials): void
	{
		$integration_hashedId = environment::hashOid($this->integration_oid);
		if($this->access_type === self::$ACCESS_TYPE_PRIVATE) {
			if(!isset($_SESSION['integration_auths'])) {
				$_SESSION['integration_auths'] = [];
			}

			if(!isset($_SESSION['integration_auths'][$integration_hashedId])) {
				$_SESSION['integration_auths'][$integration_hashedId] = [];
			}

			$_SESSION['integration_auths'][$integration_hashedId][$account_id] = [
				'auth_type' => $this->authProviderConfig['authType'],
				'allowed_hosts' => $this->authProviderConfig['allowedHosts'],
				'credentials' => $new_session_credentials
			];
		}
		//TODO: decide how shared accounts session handling will be done disabled for now
		//elseif($this->access_type === self::$ACCESS_TYPE_SHARED) {
		//}
	}
	
	protected function fetchAuthProviderConfig(): array
	{
		// get integration secure config from MDB.
		$iConfig = $this->getIntegrationConfig();
		if(empty($iConfig)) {
			return Integration::defaultResponse(0, 'Integration config not found.');
		}

		// check if authProvider is defined by integration config
		if(!isset($iConfig['auths'][$this->provider_name])) {
			return Integration::defaultResponse(0, 'Undefined authorisation provider.');
		}
		$this->authProviderConfig = $iConfig['auths'][$this->provider_name];
		return $this->authProviderConfig;
	}
	
	protected function authoriseAndSave(array $payload = []): array
	{
		if(!$this->areFieldsSetForAuthAndSave()) {
			return Integration::defaultResponse(0, 'Required info not set.');
		}
		
		// fetch and load auth provider config from db.
		$this->fetchAuthProviderConfig();
		
		// getting user credentials of respective auth type to be stored in db.
		$credentials = $this->getAuthCredentials($payload);
		
		if($credentials['request_status'] === 1) {
			$closedCredential = $credentials['closed_credentials'];
			unset($credentials['closed_credentials']); //remove field to prevent leaking into frontend
			
			if($this->saveAuthorisedCredential($closedCredential)) {
				// authorisation and save successfully completed.
				$credentials['_id'] = $this->addedAccountId;
				$this->updateSessionCredentials($this->addedAccountId, $credentials['session_credentials']);
				unset($credentials['session_credentials']); // remove from frontend response.
				return $credentials;
			}
			else {
				return Integration::defaultResponse(2, 'Authorised but failed to save, please retry.');
			}
		}
		
		// authorisation failed or error
		return $credentials;
	}

	/**
	 * @param string $account_id
	 * @return bool
	 * @throws Exception
	 */
	protected function fetchFreshRequestCredentials(string $account_id): bool
	{
		// fetch user's integration account auth
		$accountAuth = $this->getAccountAuth($account_id);
		if(empty($accountAuth)) {
			throw new Exception('Account to use does not exist.', 0);
		}

		// fetch integration -> auth provider specific config to get secrets and urls.
		$this->setProviderName($accountAuth['auth_provider_name']);
		$pConfig = $this->fetchAuthProviderConfig();
		if(isset($pConfig['request_status']) && $pConfig['request_status'] !== 1) {
			// could not find provider config in integration's config.
			throw new Exception($pConfig['message'], $pConfig['request_status']);
		}
		
		$this->selected_auth_type = $this->authProviderConfig['authType'];
		if($this->selected_auth_type === 'OAuth2') {
			$auth = new OAuth2($pConfig);
			$freshCredentials = $auth->getFreshRequestCredentials($accountAuth);
			
			if(isset($freshCredentials['request_status']) && $freshCredentials['request_status'] === 1) {
				// closed_credentials have changed or rotated then update in db.
				if(!empty($freshCredentials['closed_credentials'])) {
					$this->updateAuthorisedCredential($account_id, $freshCredentials['closed_credentials']);
				}
				if(!empty($freshCredentials['session_credentials'])) {
					$this->updateSessionCredentials($account_id, $freshCredentials['session_credentials']);
				}
				return true;
			}
		}
		else {
			throw new Exception('Unsupported authorisation type', 0);
		}
		return false;
	}

	/**
	 * @param array &$request_credentials
	 * @param array $request
	 * @throws Exception
	 * @return array
	 */
	protected function executeRequest(array &$request_credentials, array $request): array
	{
		$httpRequest = new HTTPRequest($request);
		$this->selected_auth_type = $request_credentials['auth_type'];
		if($this->selected_auth_type === 'OAuth2') {
			$httpRequest->setCredentialHeaders(OAuth2::getRequestCredentialHeaders($request_credentials));
			return $httpRequest->execute();
		}
		return Integration::defaultResponse(0, 'Unsupported authorisation type');
	}
	
	protected function formatSavedAuthForFrontend(array $savedAuth): array
	{
		if($savedAuth['auth_type'] === 'OAuth2') {
			return [
				'_id' => $savedAuth['_id'],
				'auth_on' => $savedAuth['auth_on']['$date']['$numberLong'], // UTC ms in string
				'auth_type' => $savedAuth['auth_type'],
				'auth_provider_name' => $savedAuth['auth_provider_name'],
				'credentials' => OAuth2::formatSavedCredentials($savedAuth['credentials'])
			];
		}
		return ['err' => 'Unknown authorisation type.'];
	}
	
	protected function formatProviderConfigForFrontend(array &$provider_config): array
	{
		if($provider_config['authType'] === 'OAuth2') {
			return OAuth2::formatProviderConfig($provider_config);
		}
		return ['err' => 'Unknown authorisation type.'];
	}
	
	protected function setProviderName(string $provider_name): void
	{
		$this->provider_name = trim($provider_name);
	}
	
	protected function setAuthAccessType(string $access_type): bool
	{
		if($access_type !== self::$ACCESS_TYPE_PRIVATE && $access_type !== self::$ACCESS_TYPE_SHARED) {
			$this->access_type = self::$ACCESS_TYPE_PRIVATE;
			return false;
		}
		$this->access_type = $access_type;
		return true;
	}
}
