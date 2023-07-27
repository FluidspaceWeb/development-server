<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/../../vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/../php/inc/loadEnvironmentVariables.php';
require $_SERVER['DOCUMENT_ROOT'] . '/../php/config/mdb_config.inc.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
	if(empty($_POST['integration_id']) || empty($_POST['auth_provider_name']) || empty($_POST['auth_config'])) {
		exit('Bad request');
	}

	$auth_provider_name = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['auth_provider_name'])); // keep only alphanumeric and _
	$auth_config_string = preg_replace('/(\s\t\n\r)+/', '', $_POST['auth_config']); // remove spaces, tabs, newline
	
	$hashids = new Hashids\Hashids($_ENV['APP_SALT']);
	$client = getConnection();
	$col = $client->selectDatabase('module_library')->selectCollection('integration_configs');
	
	try {
		$integration_oid = new \MongoDB\BSON\ObjectId($hashids->decodeHex(trim($_POST['integration_id'])));
		$auth_config = json_decode($auth_config_string, true, 5);
		
		$required_fields = [
			'authType' => 'string',
			'allowedHosts' => 'array',
			'secret' => 'array',
			'nonSecret' => 'array',
			'tokenExchangeURL' => 'string',
			'authGrantURL' => 'string'
		];
		
		$fieldsValidated = true;
		foreach($required_fields as $key => $val) {
			if(isset($auth_config[$key])) {
				if(gettype($auth_config[$key]) !== $val) {
					echo "Incorrect field: <b>$key</b> type, requires <b>$val</b>";
					$fieldsValidated = false;
					break;
				}
			}
			else {
				echo "required field <b>$key</b> is missing";
				$fieldsValidated = false;
				break;
			}
		}
		if($fieldsValidated === false) {
			echo '<a href="/" style="display: block; margin-top: 32px">⬅️ Back</a>';
			exit();
		}
		
		$updateStatus = $col->updateOne(
			['_id' => $integration_oid],
			[
				'$set' => [
					'auths.'.$auth_provider_name => $auth_config
				]
			],
			['upsert' => true]
		);
		
		if($updateStatus->isAcknowledged() && ($updateStatus->getModifiedCount() === 1 || $updateStatus->getUpsertedCount() === 1)) {
			echo 'Config Inserted! ✅';
		}
		else {
			echo 'Could not insert config! ❌';
		}
	}
	catch (Exception $err) {
		echo 'An error occurred ⛔️';
		print_r($err);
	}
}
else {
	exit('Invalid request 🛑️');
}

echo '<br><pre style="white-space: pre-wrap; border: solid 1px #cecece">'.$_POST['auth_config'].'</pre><br>';
echo '<a href="/" style="display: block; margin-top: 32px">⬅️ Back</a>';
