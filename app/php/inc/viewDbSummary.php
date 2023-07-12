<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
require $_SERVER['DOCUMENT_ROOT'] . '/../php/config/mdb_config.inc.php';

function showSummary(): void
{
	try {
		$client = getConnection();
		@$client->selectDatabase('admin')->command(['ping' => 1]);
		echo '<h3>Dev DB ('.$_ENV['MDB_HOST'].') ✅</h3><br>';
		generateSummaryHTML();
	} catch(Exception $e) {
		echo '<h3>Dev DB ❌</h3>';
		echo '<p>Could not connect to Mongo DB on <b>' . $_ENV['MDB_HOST'] . '</b></p>';
		echo '<pre>' . $e->getMessage() . '</pre>';
	}
}

function generateSummaryHTML(): void
{
	$client = getConnection();
	echo '<table>';
	echo '<thead><tr><th>Database</th><th>modules_data (cols)</th><th>modules_props (cols)</th></tr></thead>';
	echo '<tbody><tr>';
	
	$dbs_html = '<td><ul>';
	foreach($client->listDatabaseNames() as $db) {
		$dbs_html .= '<li>' . $db . '</li>';
	}
	$dbs_html .= '</ul></td>';
	echo $dbs_html;

	$dbs_html = '<td><ul>';
	foreach($client->selectDatabase('modules_data_dev')->listCollectionNames() as $col) {
		$dbs_html .= '<li>' . $col . '</li>';
	}
	$dbs_html .= '</ul></td>';
	echo $dbs_html;

	$dbs_html = '<td><ul>';
	foreach($client->selectDatabase('modules_props_dev')->listCollectionNames() as $col) {
		$dbs_html .= '<li>' . $col . '</li>';
	}
	$dbs_html .= '</ul></td>';
	echo $dbs_html;
	
	echo '</tr></tbody></table>';
}
