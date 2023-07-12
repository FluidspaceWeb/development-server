<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

require $_SERVER['DOCUMENT_ROOT'] . '/../../vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/../php/inc/loadEnvironmentVariables.php';
require $_SERVER['DOCUMENT_ROOT'] . '/../php/config/mdb_config.inc.php';

if($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
	if(empty($_POST['namespace']) || empty($_POST['modname']) || empty($_POST['dbname'])) {
		exit('Bad request');
	}
	
	$db_name = ($_POST['dbname'] === 'db_data')? $_ENV['MDB_MODDATA_DB_NAME'] : $_ENV['MDB_MODPROPS_DB_NAME'];
	$col_name = preg_replace('/[^a-z0-9_]/', '',strtolower(trim($_POST['namespace']).'_'.trim($_POST['modname'])));
	
	$client = getConnection();
	@$createCol = $client->selectDatabase($db_name)->createCollection($col_name);
	echo '<h3>Created: '.$createCol->ok.'</h3>';
}
else {
	exit('Invalid request');
}
?>

<a href="/" style="display: block;margin-top: 32px">⬅️ Back</a>
