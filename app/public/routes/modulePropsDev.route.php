<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'].'/../router/Router.php';

use core\Router;

const MODULE_API_NAMESPACE = 'controller\API\modulePropsDev';

$router = new Router();
//$router->requireWsToken(); NOT REQUIRED FOR DEVELOPMENT VERSION

$router->add('fetch',
    [
        'path' => 'php/controller/modulePropsDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'getModuleProps',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('insert',
    [
        'path' => 'php/controller/modulePropsDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'insertModuleProps',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('update',
    [
        'path' => 'php/controller/modulePropsDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'updateModuleProp',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('delete',
    [
        'path' => 'php/controller/modulePropsDevAPI.php',
        'method' => $router::METHOD_POST,
        'handler' => 'deleteModuleProps',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

$router->add('getWorkspaceUsers',
    [
        'path' => 'php/controller/modulePropsDevAPI.php',
        'method' => $router::METHOD_GET,
        'handler' => 'getWorkspaceUsers',
        'namespace' => MODULE_API_NAMESPACE
    ]
);

header('Content-Type: application/json');
$router->run();
