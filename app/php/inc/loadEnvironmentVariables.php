<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\includes\loadEnvironmentVariables;
require_once $_SERVER['DOCUMENT_ROOT'].'/../../vendor/autoload.php';
use Exception;
use Dotenv\Dotenv;

try {
	$dotenv = Dotenv::createImmutable($_SERVER['DOCUMENT_ROOT'] . '/../../');
	$dotenv->load();
}
catch (Exception $e)
{
	http_response_code(500);
	die(json_encode(
		[
			'request_status' => 0,
			'err_message' => 'Internal Error occurred while loading connection credentials!',
			'err_code' => 'ENV-VAR-ERR',
		]
	));
}
