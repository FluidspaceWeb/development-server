<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
namespace core\Interfaces\Integration;

interface AuthInterface {
	public function __construct(array $auth_provider_config);

	public static function formatSavedCredentials(array $credentials): array;

	public static function formatProviderConfig(array &$provider_config): array;
	
	public function getAllCredentials(string $code): array;
	
	public function getFreshRequestCredentials(array $user_account_auth): array;

	public static function getRequestCredentialHeaders(array $session_auth): array;
}
