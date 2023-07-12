<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/../../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT']. '/../php/inc/loadEnvironmentVariables.php';

function getConnection(): MongoDB\Client
{
    try {
        $URL = 'mongodb://'.$_ENV['MDB_HOST'];
        $CREDENTIAL = [
            'username' => $_ENV['MDB_USERNAME'],
            'password' => $_ENV['MDB_PASSWORD']
        ];
		
        if($CREDENTIAL['username'] === '' && $CREDENTIAL['password'] === '') {
			$mdb_client = new MongoDB\Client($URL);
		}
        else {
			$mdb_client = new MongoDB\Client($URL, $CREDENTIAL);
		}
        return $mdb_client;
    }
    catch (Exception $e) {
        http_response_code(503);
        die(json_encode(
            [
                'request_status' => 0,
                'err_message' => $e->getMessage(),
                'err_code' => 503,
            ]
        ));
    }
}
