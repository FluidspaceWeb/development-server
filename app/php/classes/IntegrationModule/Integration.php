<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\IntegrationModule;

require_once $_SERVER['DOCUMENT_ROOT'].'/../php/config/mdb_config.inc.php';
require_once __DIR__.'/AuthHandler.php';
require_once __DIR__ . '/../environment.class.php';

use core\classes\environment;
use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;

class Integration extends AuthHandler
{
	private ObjectId $user_id;
	private string $integration_id;
	private ObjectId $integration_oid;
	//private string $workspace_id;
	//private ObjectId $workspace_oid;
	private Client $mdb;
	
	private static int $ACCOUNT_AUTHS_LIMIT = 2;
	
	public function __construct(string $integration_hashed_id)
	{
		/** @noinspection SpellCheckingInspection */
		$this->user_id = new ObjectId('64bd4e59ecebd5028d1be4c5');	// dummy user id for development purpose only
		$this->integration_id = $integration_hashed_id;
		$this->integration_oid = new ObjectId(environment::unhashId($integration_hashed_id));
		$this->mdb = getConnection();
		
		parent::__construct($this->user_id, $this->integration_oid);
	}
	
	public static function defaultResponse(int $status_code, string $message = '', array $extra = []): array
	{
		$default = [
			'request_status' => $status_code,
			'message' => $message
		];
		return array_merge($default, $extra);
	}
	
	private function getExistingAccountsCount(string $access_type): int
	{
		$authAccounts = $this->getIntegrationAuths($access_type);
		return count($authAccounts);
	}
	
	public function getAuthProviderConfig(string $provider_name): array
	{
		$provider_name = substr(trim($provider_name), 0, 50);
		$provider_name = preg_replace('/[^a-zA-Z0-9\-_]/', '', $provider_name);
		
		$this->setProviderName($provider_name);
		$provider_config = $this->fetchAuthProviderConfig();
		if(isset($provider_config['request_status']) && $provider_config['request_status'] !== 1) {
			return $provider_config;
		}
		return $this->formatProviderConfigForFrontend($provider_config);
	}

	/**
	 * @param string $provider_name
	 * @param string $access_type
	 * @param array|null $request_payload contains provider_name, access_type, auth_code
	 * @return array
	 */
	public function addAccount(string $provider_name, string $access_type, array $request_payload = null): array
	{
		// get integration auths{} from mdb->module_library->integration_configs[]
		// extract provider info using provider_name
		// use authType to invoke appropriate handler
		// save handler response as "credentials" and other info in user's config -> integration id.
		
		if($this->getExistingAccountsCount($access_type) >= self::$ACCOUNT_AUTHS_LIMIT) {
			return self::defaultResponse(-2, 'Reached maximum account limit ('.self::$ACCOUNT_AUTHS_LIMIT.').');
		}
		
		$this->setAuthAccessType($access_type);
		$this->setProviderName($provider_name);
		
		return $this->authoriseAndSave($request_payload);
	}
	
	public function deleteAccount(string &$account_id, string &$access_type): array
	{
		$integration_hashId = $this->integration_id;
		$updateQuery = [
			'$pull' => [
				'integration_auths.'.$integration_hashId => ['_id' => $account_id]
			]
		];
		
		if($access_type === AuthHandler::$ACCESS_TYPE_PRIVATE) {
			$user_configs = $this->mdb->environment->user_configs;
			$update = $user_configs->updateOne(['_id' => $this->user_id], $updateQuery);
		}
		// elseif($access_type === AuthHandler::$ACCESS_TYPE_SHARED) {} // TODO
		else {
			return self::defaultResponse(0, 'Unknown access_type');
		}
		
		if($update->isAcknowledged() && $update->getModifiedCount() === 1) {
			unset($_SESSION['integration_auths'][$integration_hashId][$account_id]); // FIXME: only for private access_type
			// TODO: Revoke token for supported auth type. Note: revoke urls have varying query parameters
			return self::defaultResponse(1, 'Removed account '.$account_id);
		}
		
		return self::defaultResponse(0, 'Account removal failed');
	}
	
	public function getAccounts(string $access_type): array
	{
		$auths = $this->getIntegrationAuths($access_type);
		$accountsResponse = [];
		
		if(!empty($auths)) {
			foreach($auths as &$auth) {
				// format response to frontend using appropriate handler based on auth_type
				$accountsResponse[] = $this->formatSavedAuthForFrontend($auth);
			}
		}
		return [
			'request_status' => 1,
			'accounts' => $accountsResponse
		];
	}

	/**
	 * fetch integration and account auth config for secret values.
	 * check the auth_type (in authHandler).
	 * get requestCredentialHeaders from appropriate auth handler.
	 * set credential headers of HTTPRequest class and execute()
	 *
	 * @param string &$account_id
	 * @param string &$access_type
	 * @throws Exception
	 * @return array
	 */
	private function loadFreshRequestCredentialsInSession(string &$account_id, string &$access_type): array
	{
		$this->setAuthAccessType($access_type);
		if($this->fetchFreshRequestCredentials($account_id)) {
			return $_SESSION['integration_auths'][$this->integration_id][$account_id];
		}
		else {
			throw new Exception('Cannot get fresh authorisation credentials, please login again.', 0);
		}
	}
	
	/* FEATURE DISABLED
	public function makeRequest(string $account_id, string $access_type, array $request): array
	{}
	*/
	
	/*
	 * it provides the frontend with requestCredentials which it uses to make direct requests to external API.
	 */
	public function getSessionRequestCredentials(string $account_id, string $access_type): array
	{
		try {
			$integration_auths = $_SESSION['integration_auths'] ?? null;
			$allowedHosts = null;
			
			if($integration_auths === null
				|| !isset($integration_auths[$this->integration_id])
				|| !isset($integration_auths[$this->integration_id][$account_id])
				|| $integration_auths[$this->integration_id][$account_id]['credentials']['exp'] <= time()  // expired session credentials
			) {
				// integration request credentials do not exist in session or have expired
				$requestCredential = $this->loadFreshRequestCredentialsInSession($account_id, $access_type);
				$credentials = $requestCredential['credentials'];
				$allowedHosts = $requestCredential['allowed_hosts'];
			}
			else {
				// valid credentials exist in session
				$credentials = $integration_auths[$this->integration_id][$account_id]['credentials'];
				// extract array of allowed host strings
				$allowedHosts = $integration_auths[$this->integration_id][$account_id]['allowed_hosts'];
			}

			return [
				'request_status' => 1,
				'credentials' => $credentials,
				'allowed_hosts' => $allowedHosts
			];
		}
		catch(Exception $err) {
			return self::defaultResponse(0, $err->getMessage());
		}
	}
}
