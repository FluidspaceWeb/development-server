<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

require $_SERVER['DOCUMENT_ROOT'] . '/../../vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/../php/inc/loadEnvironmentVariables.php';

function generate(): void
{
	$hashids = new \Hashids\Hashids($_ENV['APP_SALT']);
	$module_oid = new \MongoDB\BSON\ObjectId();
	echo '<pre>'.$hashids->encodeHex((string)$module_oid).'</pre>';
}
?>

<p>Module ID <?php generate(); ?></p>

<a href="/" style="display: block;margin-top: 32px">⬅️ Back</a>
