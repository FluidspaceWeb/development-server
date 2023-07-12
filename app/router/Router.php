<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core;

class Router
{
    /*
     * $routes[] have [url => params, ...]
     * params can be path, method, handler (func or func name), args, namespace
     */

    private array $routes = [];
    private bool $requireToken = false;
    public const METHOD_POST = 'POST';
    public const METHOD_GET = 'GET';

	public function add(string $action, array $params): void
    {
        $action = trim($action);
        $this->routes[$action] = $params;
    }

    public function requireWsToken(): void
    {
        $this->requireToken = true;
    }

    private function hasWsToken(): bool
    {
        if (isset($_SERVER['HTTP_X_WSID']) && isset($_SERVER['HTTP_X_ACSRF_TOKEN'])) {
            return true;
        }
        return false;
    }

    public function run(): void
    {
		// open CORS, only for development purpose
		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
		header('Access-Control-Allow-Headers: *');
		header('Access-Control-Max-Age: 600');
		
		$req_method = $_SERVER['REQUEST_METHOD'];
		if($req_method === 'OPTIONS') {
			http_response_code(200);
			exit();
		}
		
        if ($this->requireToken && !$this->hasWsToken()) {
            http_response_code(400);
            die($this->makeErrorResponse(400, 'Workspace Token Required', 'RTR-3'));
        }

        if (empty($_GET['action'])) {
            die($this->makeErrorResponse(0, 'Undefined Action', 'RTR-0'));
        }
        $action = $_GET['action'];
		
		// Validate requested route and action
        if (!isset($this->routes[$action])) {
            echo $this->makeErrorResponse(0, 'Route Undefined', 'RTR-1');
        }
        else {
            $route = $this->routes[$action];
            if ($route['method'] !== $req_method) {
                http_response_code(405);
                echo $this->makeErrorResponse(0, 'Incorrect Method', 'RTR-2');
            }
            else {
            	//FIXME: bad, change to autoloading.
                require __DIR__ . '/../' . $route['path']; //in main directory

                //run the handler if set else just require the file
                if (isset($route['handler'])) { //TODO: Update namespace standard.
                    if (gettype($route['handler']) === 'string') { //if handler is function name
                        if (isset($route['namespace'])) { //use specified namespace
                            $route['handler'] = $route['namespace'] . "\\" . $route['handler']; //callback: namespace\handler
                        }
                        else { //default namespace if handler is string and namespace is undefined
                            $route['handler'] = "controller\\$action\\" . $route['handler']; //callback: controller\action\handler
                        }
                    }
                    $args = $route['args'] ?? [];
                    call_user_func_array($route['handler'], $args);
                }
            }
        }
    }

    private function makeErrorResponse(int $errStatus, string $message, string $errCode): string
    {
        header('Content-Type: application/json');
        $errResp = [
            'request_status' => $errStatus,
            'error_code' => $errCode,
            'message' => $message
        ];
        return json_encode($errResp);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
