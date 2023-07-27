<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\IntegrationModule;
require_once __DIR__.'/Integration.php';

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

class HTTPRequest
{
	private string $url;
	private string $method;
	private array $headers;
	private string $content_type;
	private array $body;
	private string $serialised_body;

	private array $REQUIRED_PARAMETERS = ['url', 'method', 'headers', 'content_type', 'body'];
	private array $ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
	private array $SUPPORTED_CONTENT_TYPES = [
		'custom' => '',
		'application/json' => 'json',
		'multipart/form-data' => 'multipart',
		'application/x-www-form-urlencoded' => 'form_params'
	];

	/**
	 * @param array $request_parameters
	 * @throws Exception
	 */
	public function __construct(array $request_parameters)
	{
		foreach($this->REQUIRED_PARAMETERS as &$fieldName) {
			if(!isset($request_parameters[$fieldName])) {
				throw new Exception('Missing '.$fieldName.' field', 0);
			}
		}
		
		if(!in_array($request_parameters['method'], $this->ALLOWED_METHODS, true)) {
			throw new Exception('Unsupported request method', 0);
		}
		if(!in_array($request_parameters['content_type'], array_keys($this->SUPPORTED_CONTENT_TYPES), true)) {
			throw new Exception('Unsupported Content-Type', 0);
		}
		
		// POST method validations
		if($request_parameters['method'] === 'POST') {
			if($request_parameters['content_type'] === 'custom') {
				throw new Exception('Content-Type not supported for POST method', 0);
			}
			if(!is_array($request_parameters['body'])) {
				throw new Exception('Body must be key-value pairs for POST method', 0);
			}
		}
		
		// URL Sanitization and set to object variable
		if(($this->url = filter_var($request_parameters['url'], FILTER_SANITIZE_URL)) === false) {
			throw new Exception('Malformed URL', 0);
		}
		
		$this->method = $request_parameters['method'];
		$this->headers = $request_parameters['headers'];
		$this->content_type = $request_parameters['content_type'];
		
		// body value setting based on type of received body
		$this->serialised_body = gettype($request_parameters['body']) === 'string'? $request_parameters['body'] : '';
		$this->body = is_array($request_parameters['body'])? $request_parameters['body'] : [];
	}
	
	public function setCredentialHeaders(array $credentialHeaders): void
	{
		$this->headers = array_merge($this->headers, $credentialHeaders);
	}
	
	public function execute()
	{
		$config = [
			'connect_timeout' => 10,	// seconds
			'timeout' => 10,			// seconds
		];
		
		try {
			$client = new Client($config);
			$response = null;
			
			if($this->method === 'POST') {
				$body = [];
				$contentTypePropertyName = $this->SUPPORTED_CONTENT_TYPES[$this->content_type];
				$body[$contentTypePropertyName] = $this->body;
				$response = $client->post($this->url, $body);
			}
			elseif ($this->method === 'GET') {
				unset($this->headers['method']); // remove duplicate method header key
				$request = new Request($this->method, $this->url, $this->headers);
				$response = $client->send($request);
			}
			else {	// PUT, PATCH, DELETE
				if($this->content_type === 'application/json') {
					unset($this->headers['method']); // remove duplicate method header key
					$this->headers['Content-Type'] = 'application/json';
					$request = new Request($this->method, $this->url, $this->headers, json_encode($this->body));
				}
				else {
					// for put, patch, delete and non-json type use serialised string and headers from frontend.
					$request = new Request($this->method, $this->url, $this->headers, $this->serialised_body);
				}
				$response = $client->send($request);
			}
			
			$response_body = $response->getBody();
			
			return [
				'request_status' => 1,
				'response' => [
					'status' => $response->getStatusCode(),
					'headers' => $response->getHeaders(),
					'body' => $response_body->getContents(),
					'body_size' => $response_body->getSize()
				]
			];
		}
		catch (ClientException $exp) { // handle 4xx errors
			$response = $exp->getResponse();
			return [
				'request_status' => 2,
				'response' => [
					'status' => $response->getStatusCode(),
					'headers' => $response->getHeaders(),
					'body' => $response->getBody()->getContents(),
				]
			];
		}
		catch(RequestException $exp) { // handle 5xx, tooManyRedirects errors.
			return Integration::defaultResponse(0, 'Request failed');
		}
		catch(ConnectException $exp) {	// handle timeout, no internet errors
			return Integration::defaultResponse(0, 'Network error, request timeout!');
		}
		catch(GuzzleException $exp) { // all remaining errors
			return Integration::defaultResponse(0, 'An unexpected error occurred!');
		}
	}
}
