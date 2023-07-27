<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\IntegrationModule\Auths;

require_once __DIR__.'/AuthInterface.php';
require_once __DIR__.'/../Utils.php';
require_once __DIR__.'/../Integration.php';
require_once __DIR__.'/../AuthHandler.php';

use core\Interfaces\Integration\AuthInterface;
use core\classes\IntegrationModule\Utils;
use core\classes\IntegrationModule\AuthHandler;
use core\classes\IntegrationModule\Integration;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class OAuth2 extends Utils implements AuthInterface
{
	private array $providerConfig;
	private array $accountAuth;
	
	public function __construct(array $auth_provider_config)
	{
		$this->providerConfig = $auth_provider_config;
	}
	
	public static function formatSavedCredentials(array $credentials): array
	{
		// This is used to format user's save credentials
		return [
			'profile' => $credentials['profile'] ?? null,
			'scope' => $credentials['scope'] ?? null,
			'sub' => $credentials['sub'] ?? null,
			'issuer' => $credentials['issuer'] ?? null
		];
	}
	
	public static function formatProviderConfig(array &$provider_config): array
	{
		// Used to format provider config for frontend response, does not return secrets.
		return [
			'auth_type' => $provider_config['authType'],
			'allowed_hosts' => $provider_config['allowedHosts'],
			'non_secret' => $provider_config['nonSecret'],
			'auth_grant_url' => $provider_config['authGrantURL'],
			'token_exchange_url' => $provider_config['tokenExchangeURL']
		];
	}
	
	private function httpRequestResponse(int $request_status, ResponseInterface $guzzleResponse, string $message = ''): array
	{
		$body = $guzzleResponse->getBody()->getContents();
		$respContentType = $guzzleResponse->getHeader('Content-Type');
		if(!empty($respContentType) && str_starts_with($respContentType[0], 'application/json')) {
			$body = json_decode($body, true, 50);
		}
		
		return [
			'request_status' => $request_status,
			'message' => ($message === '')? $guzzleResponse->getReasonPhrase() : $message,
			'response' => [
				'status' => $guzzleResponse->getStatusCode(),
				'body' => $body
			]
		];
	}
	
	private function executeHTTPRequest(string $method, string &$url, array &$formDataPayload): array
	{
		try {
			$client = new Client();
			$response = $client->request($method, $url, ['form_params' => $formDataPayload]);
			return $this->httpRequestResponse(1, $response);
		}
		catch(ClientException $err) {
			return $this->httpRequestResponse(0, $err->getResponse());
		}
		catch(RequestException $err) {
			return $this->httpRequestResponse(0, $err->getResponse(), 'Authorisation Server Error');
		}
		catch(GuzzleException $err) {
			return Integration::defaultResponse(0, 'An internal error occurred while exchanging OAuth2 token.');
		}
		catch(Exception $err) {
			return Integration::defaultResponse(0, 'An unexpected error occurred.');
		}
	}
	
	private function oauth2_exchangeToken(string $auth_code): array
	{
		// exchange auth_code for access_token & refresh_token
		$PAYLOAD = [
			'client_id' => $this->providerConfig['nonSecret']['client_id'],
			'scope' => $this->providerConfig['nonSecret']['scope'],
			'code' => $auth_code,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $_ENV['INTEGRATION_OAUTH2_REDIRECT_URL'],
			'client_secret' => $this->providerConfig['secret']['client_secret'],
		];
		
		$TOKEN_EXCHANGE_URL = $this->providerConfig['tokenExchangeURL'];
		
		if(!AuthHandler::isUrlAllowedHost($TOKEN_EXCHANGE_URL, $this->providerConfig['allowedHosts'])) {
			return Integration::defaultResponse(0, 'Host URL(s) not allowed.');
		}
		
		$response = $this->executeHTTPRequest('POST', $TOKEN_EXCHANGE_URL, $PAYLOAD);
		if($response['request_status'] === 1 && $response['response']['status'] === 200) {
			return Integration::defaultResponse($response['request_status'], '',
				['credentials' => $response['response']['body']]
			);
		}
		
		return $response;
	}
	
	private function getNewTokens(string $refresh_token): array
	{
		$TOKEN_EXCHANGE_URL = $this->providerConfig['tokenExchangeURL'];
		$PAYLOAD = [
			'client_id' => $this->providerConfig['nonSecret']['client_id'],
			'client_secret' => $this->providerConfig['secret']['client_secret'],
			'refresh_token' => $refresh_token,
			'grant_type' => 'refresh_token'
		];
		
		$response = $this->executeHTTPRequest('POST', $TOKEN_EXCHANGE_URL, $PAYLOAD);
		if($response['request_status'] === 1 && $response['response']['status'] === 200) {
			return Integration::defaultResponse($response['request_status'], '',
				['credentials' => $response['response']['body']]
			);
		}

		return $response;
	}
	
	private function decodeIdToken(string $id_token): array
	{
		//TODO: use JWT library for this and verify signature
		$t = explode('.', $id_token, 3);
		return json_decode(base64_decode($t[1]), true);
	}

	/**
	 * @param array $credentials
	 * @param array $id_profile_fields
	 * @return array
	 * @throws Exception
	 */
	private function createClosedCredentials(array $credentials, array &$id_profile_fields): array
	{
		// encrypt refresh token and set nonce field
		if(isset($credentials['refresh_token']) === true && $credentials['refresh_token'] !== null) {
			$encryptedRT = self::encryptToken($credentials['refresh_token']);
			$credentials['refresh_token'] = null; // set default value, only store encrypted RT.
			if($encryptedRT['token'] !== null) { // encryption successful
				$credentials['refresh_token'] = $encryptedRT; // array containing encrypted token and nonce.
			}
		}
		else {
			$credentials['refresh_token'] = null; // field does not exist, creating and setting as null
		}
		
		$closedCredentials = [ // will be save in db > user_configs[] > integration_auths[]
			'sub' => $credentials['id_token_payload']['sub'] ?? null,
			'issuer' => $credentials['id_token_payload']['iss'] ?? null,
			'refresh_token' => $credentials['refresh_token'],
			'token_type' => $credentials['token_type'] ?? null,
			'scope' => $credentials['scope'] ?? null, // because granted scope, can be different from requested scope
			'profile' => [] // returned when fetching accounts
		];

		// add account profile information.
		// selectively adds decoded id_token_payload[] fields to profile[] for storage, generally used to generate UI.
		foreach($id_profile_fields as $fieldName) {
			if(isset($credentials['id_token_payload'][$fieldName]) && $credentials['id_token_payload'][$fieldName] !== null) {
				$closedCredentials['profile'][$fieldName] = $credentials['id_token_payload'][$fieldName];
			}
		}
		
		return $closedCredentials;
	}
	
	private function createSessionCredentials(array $credentials): array
	{
		return [
			'token_type' => $credentials['token_type'],
			'access_token' => $credentials['access_token'],
			'exp' => isset($credentials['expires_in'])? (time()+$credentials['expires_in']) : (time()+3600)
		];
	}

	/**
	 * @param string $auth_code
	 * @return array
	 * @throws Exception
	 */
	public function getAllCredentials(string $auth_code): array
	{
		$exchangeResponse = $this->oauth2_exchangeToken($auth_code);
		
		if($exchangeResponse['request_status'] === 1 && !empty($exchangeResponse['credentials'])) {
			// successfully exchange auth code for access token and refresh token
			// now assort data in sensitive_credentials and open_credentials fields.
			$credentials = $exchangeResponse['credentials'];

			// decode id_token, extract payload of id_token into id_token_payload and id_token is removed.
			$credentials['id_token_payload'] = isset($credentials['id_token'])? $this->decodeIdToken($credentials['id_token']) : [];
			unset($credentials['id_token']);
			
			// closedCredentials will be stored in database in credentials[] field to maintain authorisation across sessions
			// and devices, and extract selective profile information from id_token_payload[] to profile[].
			$id_profile_fields = ['email', 'name', 'given_name', 'family_name', 'picture'];
			$closedCredentials = $this->createClosedCredentials($credentials, $id_profile_fields);
			
			// sessionCredentials are temporary and stored in the session
			$sessionCredentials = $this->createSessionCredentials($credentials);
			
			// remove sensitive credential fields from $credentials
			$sensitive_fields = ['access_token', 'refresh_token', 'token_type'];
			foreach($sensitive_fields as &$fieldName) {
				unset($credentials[$fieldName]);
			}
			
			return [
				'request_status' => 1,
				'closed_credentials' => $closedCredentials,
				'session_credentials' => $sessionCredentials,
				'open_credentials' => $credentials // all response fields except sensitive_fields[]
			];
		}
		
		return $exchangeResponse;
	}
	
	/**
	 * @param array $user_account_auth
	 * @return array
	 * @throws Exception
	 * 
	 * 1. get allowedHosts client_id, client_secret, tokenExchangeURL from integration's provider config
	 * 2. decode existing refresh_token passes in params
	 * 3. get fresh access token from tokenExchangeURL
	 * 4. segregate auth fields, session_credentials are updated in session by AuthHandler
	 * if refresh token or scope was responded and has changed then update in user_config (in AuthHandler)
	 */
	public function getFreshRequestCredentials(array $user_account_auth): array
	{
		$this->accountAuth = $user_account_auth;
		if(empty($this->accountAuth['credentials']['refresh_token'])) {
			return Integration::defaultResponse(0, 'Refresh token does not exist, please login again.');
		}
		
		$currentRefreshToken = $user_account_auth['credentials']['refresh_token'];
		$currentRefreshToken = Utils::decryptToken($currentRefreshToken['token'], $currentRefreshToken['nonce']);
		$newTokens = $this->getNewTokens($currentRefreshToken);
		
		if($newTokens['request_status'] === 1) {
			$newCredentials = &$newTokens['credentials'];
			$response = [
				'request_status' => 1,
				'session_credentials' => [	// to update in session
					'token_type' => $newCredentials['token_type'],
					'access_token' => $newCredentials['access_token'],
					'exp' => (time() + $newCredentials['expires_in'])
				],
				'closed_credentials' => [], // to be updated in db -> user_configs -> integration_auths
				'open_credentials' => [] // to be returned
			];
			
			if(isset($newCredentials['refresh_token']) && $newCredentials['refresh_token'] !== $currentRefreshToken) {
				// refresh token has been rotated, update refresh_token in db. RT not to be added in session.
				$response['closed_credentials']['refresh_token'] =  Utils::encryptToken($newCredentials['refresh_token']);
			}
			if(isset($newCredentials['scope']) && $newCredentials['scope'] !== $user_account_auth['credentials']['scope']) {
				// scope has been changed, update in db and return to frontend.
				$response['closed_credentials']['scope'] =  $newCredentials['scope'];
				$response['open_credentials']['scope'] =  $newCredentials['scope'];
			}
			
			return $response;
		}
		
		return $newTokens;
	}
	
	public static function getRequestCredentialHeaders(array $session_auth): array
	{
		return [
			'Authorization' => $session_auth['credentials']['token_type'].' '.$session_auth['credentials']['access_token']
		];
	}
}
