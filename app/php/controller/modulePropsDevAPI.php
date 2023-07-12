<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
namespace controller\API\modulePropsDev;

require_once __DIR__.'/../classes/ModulePropsDev.class.php';
require_once 'moduleDevAPI.php';
//require_once __DIR__.'/../classes/environment.class.php';

use classes\microservice\CDN;
use controller\API\moduleDev as ModuleAPI;  //  DEVELOPMENT VERSION
use core\classes\api\ModulePropsDev;    //  DEVELOPMENT VERSION
//use core\classes\environment;
//use MongoDB\BSON\ObjectId;

function getModuleProps(): void
{
    $payload = ModuleAPI\getPostPayload();
    $WS_INFO = ModuleAPI\validateAndGetWsInfo('fetch');
    if($WS_INFO !== false) {
        $ModuleProps = new ModulePropsDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
        echo json_encode($ModuleProps->getProps($payload));
    }
}

function insertModuleProps(): void
{
    $payload = ModuleAPI\getPostPayload();
    $WS_INFO = ModuleAPI\validateAndGetWsInfo('insert');
    if($WS_INFO !== false) {
        if(isset($payload['props']) && gettype($payload['props']) === 'array') {
            $numOfProps = count($payload['props']);
            if($numOfProps > 20) {
                http_response_code(413); //payload entity too large
                echo json_encode([
                    'insert_status' => 3,
                    'error_message' => 'No props inserted. Number of props exceeds maximum props limit of 20',
                    'inserted_props' => []
                ]);
            }
            elseif ($numOfProps === 0) {
                echo json_encode([
                    'insert_status' => 2,
                    'error_message' => 'No props to insert'
                ]);
            }
            else {
                $ModuleProps = new ModulePropsDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
                echo json_encode($ModuleProps->insertProps($payload['props']));
            }
        }
        else {
            http_response_code(400);
            echo json_encode([
                'insert_status' => 0,
                'error_message' => 'Invalid request type!'
            ]);
        }
    }
}

function updateModuleProp(): void
{
    $payload = ModuleAPI\getPostPayload();
    $WS_INFO = ModuleAPI\validateAndGetWsInfo('update');
    if($WS_INFO !== false) {
        if(isset($payload['_id']) && isset($payload['updates']) && gettype($payload['_id'] === 'string') && gettype($payload['updates']) === 'array') {
           if (count($payload['updates']) > 0) {
               $ModuleProps = new ModulePropsDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
               echo json_encode($ModuleProps->updateProp($payload['_id'], $payload['updates']));
           }
           else {
               echo json_encode([
                   'update_status' => 2,
                   'error_message' => 'No prop updates!'
               ]);
           }
        }
        else {
            http_response_code(400);
            echo json_encode([
                'update_status' => 0,
                'error_message' => 'Invalid request type!'
            ]);
        }
    }
}

function deleteModuleProps(): void
{
    $payload = ModuleAPI\getPostPayload();
    $WS_INFO = ModuleAPI\validateAndGetWsInfo('delete');
    if($WS_INFO !== false) {
        if(isset($payload['prop_ids']) && gettype($payload['prop_ids']) === 'array') {
            if (count($payload['prop_ids']) > 0) {
                $ModuleProps = new ModulePropsDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
                echo json_encode($ModuleProps->deleteProp($payload['prop_ids']));
            }
            else {
                echo json_encode([
                    'delete_status' => 2,
                    'error_message' => 'No prop specified to delete!'
                ]);
            }
        }
        else {
            http_response_code(400);
            echo json_encode([
                'delete_status' => 0,
                'error_message' => 'Invalid request type!'
            ]);
        }
    }
}

function getWorkspaceUsers(): void
{
    if(ModuleAPI\hasValidCredentials()) {
    	$dummyUsers = [
    		[
    			'_id' => 'yKVVJWy3ObUq9gvVBkmN',
				'fname' => 'Eleanora',
				'lname' => 'Price',
				'email' => 'eleanora.p@domain.tld',
				'img' => 'http://localhost:1822/images/profile/yKVVJWy3ObUq9gvVBkmN.jpg'
			],
			[
				'_id' => 'AQLOo2VLL3C0D4EkE00Y',
				'fname' => 'Ram',
				'lname' => 'Shukla',
				'email' => 'ramshukla@domain.tld',
				'img' => 'http://localhost:1822/images/profile/AQLOo2VLL3C0D4EkE00Y.jpg'
			],
			[
				'_id' => 'DpLQ2dJ0xKIra3kRQ1ro',
				'fname' => 'Johnathon',
				'lname' => 'Predovic',
				'email' => 'johnathon_p@domain.tld',
				'img' => 'http://localhost:1822/images/profile/DpLQ2dJ0xKIra3kRQ1ro.jpg'
			],
			[
				'_id' => '2w77Wa3xDYhEJBxpl9LD',
				'fname' => 'Lenna',
				'lname' => 'Renner',
				'email' => 'leena.r@domain.tld',
				'img' => 'http://localhost:1822/images/profile/2w77Wa3xDYhEJBxpl9LD.jpg'
			],
			[
				'_id' => 'kkDq33qOQDfPkZEVw413',
				'fname' => 'Frankie',
				'lname' => 'Hudson',
				'email' => 'frankie_111@domain.tld',
				'img' => 'http://localhost:1822/images/profile/kkDq33qOQDfPkZEVw413.jpg'
			],
			[
				'_id' => 'QwLyobJLRyCaopJENgx3',
				'fname' => 'Rachel',
				'lname' => 'Jacobson',
				'email' => 'rachel.j@domain.tld',
				'img' => 'http://localhost:1822/images/profile/QwLyobJLRyCaopJENgx3.jpg'
			]
		];
    	
    	foreach($dummyUsers as &$user) {
    		$user['img'] = CDN::getUserProfileImagePath($user['_id']);
		}
    	
        echo json_encode($dummyUsers);
    }
}
