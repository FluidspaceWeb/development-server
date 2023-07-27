<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
session_start();

require $_SERVER['DOCUMENT_ROOT'].'/../router/Router.php';

use core\Router;

const MODULE_API_NAMESPACE = 'controller\API\integration';

$router = new Router();
$router->requireWsToken();

$router->add('getAccounts',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_POST,
		'handler' => 'getAccounts',
		'namespace' => MODULE_API_NAMESPACE
	]
);

$router->add('getAuthProviderConfig',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_POST,
		'handler' => 'getAuthProviderConfig',
		'namespace' => MODULE_API_NAMESPACE
	]
);

$router->add('getRequestCredentials',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_GET,
		'handler' => 'getRequestCredentials',
		'namespace' => MODULE_API_NAMESPACE
	]
);

$router->add('addAccount',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_POST,
		'handler' => 'addAccount',
		'namespace' => MODULE_API_NAMESPACE
	]
);

$router->add('deleteAccount',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_POST,
		'handler' => 'deleteAccount',
		'namespace' => MODULE_API_NAMESPACE
	]
);

/* $router->add('makeRequest',
	[
		'path' => 'php/controller/integrationAPI.php',
		'method' => $router::METHOD_POST,
		'handler' => 'makeRequest',
		'namespace' => MODULE_API_NAMESPACE
	]
); */

header('Content-Type: application/json');
$router->run();
