<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.


declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/../router/Router.php';

use core\Router;

const MODULE_API_NAMESPACE = 'controller\API\moduleDev';

$router = new Router();
//$router->requireWsToken();

$router->add('fetch',
    [
        'path' => 'php/controller/moduleDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'getModuleData',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('insert',
    [
        'path' => 'php/controller/moduleDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'insertDocuments',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('delete',
    [
        'path' => 'php/controller/moduleDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'deleteDocuments',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('update',
    [
        'path' => 'php/controller/moduleDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'updateDocument',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('fetchCompanionData',
    [
        'path' => 'php/controller/moduleDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'getModuleData',
        'args' => [true], //getCompanionData: true
        'namespace' => MODULE_API_NAMESPACE
    ]
);

header('Content-Type: application/json');
$router->run();
