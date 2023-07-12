<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace classes\microservice;

use Dotenv\Dotenv;

class CDN
{
	private static function loadEnvironmentVariables()
	{
		try {
			$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT'] . '/../../');
			$dotenv->load();
		}
		catch(\Exception $e) {
			die(json_encode(
				[
					'request_status' => 0,
					'err_message' => 'Internal Error occurred, could not locate CDN base URL!',
					'err_code' => 'CDN-URL-NF',
				]
			));
		}
	}
	
	public static function getUserPictureUploadPath(): string 
	{
		return $_SERVER['DOCUMENT_ROOT'].'/images/';
	}

	public static function getUserProfileImagePath(string $hashed_uid): string
	{
		if(!isset($_ENV['CDN_BASE_URL'])) {
			CDN::loadEnvironmentVariables();
		}
		
		$fileAbsPath = self::getUserPictureUploadPath().$hashed_uid.'.jpg';
		if(file_exists($fileAbsPath)) {
			return $_ENV['CDN_BASE_URL'].'/images/'.$hashed_uid.'.jpg';
		}
		return $_ENV['CDN_BASE_URL'].'/images/user_default.jpg';
	}
	
	
	public static function copyDefaultImageForUser(string $hashed_uid): bool
	{
		$fileBasePath = self::getUserPictureUploadPath();
		$defaultImgPath = $fileBasePath.'default.jpg';
		$copyToPath = $fileBasePath.$hashed_uid.'.jpg';
		return copy($defaultImgPath, $copyToPath);
	}

	public static function getWorkspaceImagePath(string $hashed_wsid = 'DEFAULT'): string
	{
		if($hashed_wsid === 'DEFAULT') {
			return $_ENV['CDN_BASE_URL'].'/images/ws_icons/default.jpg';
		}
		
		if(!isset($_ENV['CDN_BASE_URL'])) {
			CDN::loadEnvironmentVariables();
		}
		return $_ENV['CDN_BASE_URL'].'/images/ws_icons/'.$hashed_wsid.'.jpg';
	}
}
