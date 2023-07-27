<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\IntegrationModule;

use Exception;

class Utils
{
	/**
	 * @param string $token
	 * @return array
	 * @throws Exception
	 */
	protected static function encryptToken(string $token): array
	{
		try {
			$key = base64_decode($_ENV['INTEGRATION_TOKEN_CRYPTO_KEY']);
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$encryptedToken = sodium_crypto_secretbox($token, $nonce, $key);
			return [
				'token' => base64_encode($encryptedToken),
				'nonce' => base64_encode($nonce)
			];
		}
		catch(Exception $e) {
			error_log($e->getFile().' -- '.$e->getMessage(), 0);
			throw new Exception('token encryption error', 0);
		}
	}

	/**
	 * @param string $token
	 * @param string $nonce
	 * @return string
	 * @throws Exception
	 */
	protected static function decryptToken(string $token, string $nonce): string
	{
		try {
			return sodium_crypto_secretbox_open(
				base64_decode($token),
				base64_decode($nonce),
				base64_decode($_ENV['INTEGRATION_TOKEN_CRYPTO_KEY'])
			);
		}
		catch(Exception $e) {
			error_log($e->getFile().' -- '.$e->getMessage(), 0);
			throw new Exception('token decryption error', 0);
		}
	}
}
