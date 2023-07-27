<?php /** @noinspection PhpUnused */
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
namespace controller\API\integration;

require __DIR__ . '/../classes/IntegrationModule/Integration.php';

use core\classes\IntegrationModule\AuthHandler;
use core\classes\IntegrationModule\Integration;

function getPostPayload(): array
{
	return json_decode(file_get_contents("php://input"), true) ?? [];
}

function _hasValidCredentials(): bool
{
	if(isset($_SERVER['HTTP_X_MODULE_TYPE'])
		&& isset($_SERVER['HTTP_X_MODULE_ID'])
		&& isset($_SERVER['HTTP_X_MODULE_FN'])
		&& ($_SERVER['HTTP_X_MODULE_TYPE'] === 'integration'
			&& !empty($_SERVER['HTTP_X_MODULE_ID']) 
			&& !empty($_SERVER['HTTP_X_MODULE_FN'])
		)
	) {
		return true;
	}

	http_response_code(400);
	echo json_encode(['request_status' => 400, 'message' => 'Invalid Request']);
	return false;
}

function _validateFields(&$data, &$schema): bool
{
	foreach($schema as $fieldName => $fieldType) {
		if(!array_key_exists($fieldName, $data) || gettype($data[$fieldName]) !== $fieldType) {
			return false;
		}
	}
	return true;
}

function isValidAccessType(string &$access_type): bool
{
	return ($access_type === AuthHandler::$ACCESS_TYPE_PRIVATE || $access_type === AuthHandler::$ACCESS_TYPE_SHARED);
}

function getAuthProviderConfig() {
	if(_hasValidCredentials()) {
		$payload = getPostPayload();
		$requiredPayloadSchema = [
			'provider_name' => 'string'
		];

		if(_validateFields($payload, $requiredPayloadSchema) === false || trim($payload['provider_name']) === '') {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Invalid request, missing required fields!'
			]));
		}

		$integration = new Integration($_SERVER['HTTP_X_MODULE_ID']);
		echo json_encode($integration->getAuthProviderConfig($payload['provider_name']));
	}
}

function addAccount(): void
{
	if(_hasValidCredentials()) {
		$payload = getPostPayload();
		$requiredPayloadSchema = [
			'provider_name' => 'string',
			'access_type' => 'string'
		];

		if($payload['access_type'] === 'shared') {
			// TODO: Temporarily disabled creation of shared accounts, implementation not completed.
			// need to decide how will session of shared account be handled and handling of other methods.
			http_response_code(403);
			exit(json_encode([
				'request_status' => 403,
				'message' => 'Adding shared account forbidden!'
			]));	
		}
		
		if(_validateFields($payload, $requiredPayloadSchema) === false
			|| $payload['provider_name'] === '' || $payload['access_type'] === ''
		) {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Invalid request, missing required fields!'
			]));
		}

		if(!isValidAccessType($payload['access_type'])) {
			$payload['access_type'] = 'private'; // defaults the access_type if invalid value provided.
		}
		
		$integration = new Integration($_SERVER['HTTP_X_MODULE_ID']);
		echo json_encode($integration->addAccount($payload['provider_name'], $payload['access_type'], $payload));
	}
}

function deleteAccount(): void
{
	if(_hasValidCredentials()) {
		$payload = getPostPayload();
		$requiredPayloadSchema = [
			'account_id' => 'string',
			'access_type' => 'string'
		];
		if(!_validateFields($payload, $requiredPayloadSchema)) {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Invalid request, missing required fields!'
			]));
		}
		if(!isValidAccessType($payload['access_type'])) {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Invalid access_type!'
			]));
		}

		$integration = new Integration($_SERVER['HTTP_X_MODULE_ID']);
		echo json_encode($integration->deleteAccount($payload['account_id'], $payload['access_type']));
	}
}

function getAccounts(): void
{
	if(_hasValidCredentials()) {
		$payload = getPostPayload();
		$requiredPayloadSchema = [
			'access_type' => 'string'
		];

		if(_validateFields($payload, $requiredPayloadSchema) === false
			|| $payload['access_type'] === '' || !isValidAccessType($payload['access_type'])
		) {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Invalid Request!'
			]));
		}
		
		$integration = new Integration($_SERVER['HTTP_X_MODULE_ID']);
		echo json_encode($integration->getAccounts($payload['access_type']));
	}
}

/*
TODO: feature unavailable, add and check shared account support
function makeRequest(): void
{}
*/


/**
 * Attempts to get appropriate credentials, for OAuth2 type access_token from session or a fresh token using the refresh_token is responded.
 * refresh_token is NOT included in the final response.
 * Successful response JSON {
 * 		request_status,
 * 		credentials: {token_type, access_token},
 * 		allowed_hosts: string[<urls>, ...]
 * }
 * @return void
 */
function getRequestCredentials(): void
{
	if(_hasValidCredentials()) {
		if(!isset($_SERVER['HTTP_X_INTEGRATION_ACCOUNT_ID']) || !isset($_SERVER['HTTP_X_INTEGRATION_ACCESS_TYPE'])
			|| $_SERVER['HTTP_X_INTEGRATION_ACCOUNT_ID'] === '' || $_SERVER['HTTP_X_INTEGRATION_ACCESS_TYPE'] === ''
		) {
			http_response_code(400);
			exit(json_encode([
				'request_status' => 400,
				'message' => 'Incomplete request!'
			]));
		}
		$account_id = $_SERVER['HTTP_X_INTEGRATION_ACCOUNT_ID'];
		$access_type = $_SERVER['HTTP_X_INTEGRATION_ACCESS_TYPE'];

		$integration = new Integration($_SERVER['HTTP_X_MODULE_ID']);
		echo json_encode($integration->getSessionRequestCredentials($account_id, $access_type));
	}
}
