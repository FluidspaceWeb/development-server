<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
namespace controller\API\moduleDev;

require_once __DIR__.'/../config/mdb_config.inc.php';
//require_once __DIR__.'/../classes/userAuthenticationManager.class.php';

require_once __DIR__.'/../classes/WorkspaceManager.class.php';
require_once __DIR__ . '/../classes/ModuleDev.class.php';   // DEVELOPMENT VERSION
require_once __DIR__.'/../classes/ModuleLibrary.class.php';

//use core\classes\environment;
//use core\classes\ModuleLibrary;
use core\classes\Workspace\WorkspaceManager;
use Hashids\Hashids;
//use MongoDB\BSON\ObjectId;
//use MongoDB\Model\BSONDocument;
//use userAuthenticationManager;
use core\classes\api;

@session_start();

function getPostPayload(): array
{
    return json_decode(file_get_contents("php://input"), true) ?? [];
}

function hasValidCredentials(): bool
{
    /*
     * SKIPS USER VALIDATION AND ALLOWS DIRECT ACCESS TO TEST DB
     * TODO: Implement dev key validation
     *
    if (userAuthenticationManager::isUserLoggedIn()) {
        if (WorkspaceManager::verifyToken($_SERVER['HTTP_X_WSID'], $_SERVER['HTTP_X_ACSRF_TOKEN'])) { //verify anti-csrf*/
            if(isset($_SERVER['HTTP_X_DATASET_ID']) && isset($_SERVER['HTTP_X_MODULE_ID']) && isset($_SERVER['HTTP_X_MODULE_FN']) && isset($_SERVER['HTTP_X_ALLOWED_PERM'])
                && (!empty($_SERVER['HTTP_X_DATASET_ID']) && !empty($_SERVER['HTTP_X_MODULE_ID']) && !empty($_SERVER['HTTP_X_MODULE_FN']) && isset($_SERVER['HTTP_X_ALLOWED_PERM']))
            ) {

                $env_salt = $_ENV['APP_SALT'];
                $hashids = new Hashids($env_salt);
                $dataset_id = $_SERVER['HTTP_X_DATASET_ID'];
                $module_id = $hashids->decodeHex($_SERVER['HTTP_X_MODULE_ID']);
                if (strlen($dataset_id) === 20 && WorkspaceManager::isValidMdbId($module_id)) {
                    return true;
                }
                else {
                    http_response_code(400);
                    echo json_encode(['request_status' => 400, 'message' => 'Invalid ID(s) provided!']);
                    return false;
                }

            }
            http_response_code(400);
            echo json_encode(['request_status' => 400, 'message' => 'Incomplete Request']);
            return false;
        /*}
        http_response_code(400);
        echo json_encode(['request_status' => 0, 'message' => 'Invalid Token']);
        return false;
    }
    else {
        http_response_code(401);
        echo json_encode(['request_status' => -1, 'message' => 'User not logged in']);
    }
    return false;*/
}

function isActionAllowed(string $action_type, int $allowed_permission): bool
{
    $ACTION_PERM = [
        'fetch' => 1,
        //'execute' => 4, //this action is not used anywhere yet
        'update' => 4,
        'delete' => 9,
        'insert' => 4
    ];

    if(!isset($ACTION_PERM[$action_type])) //if invalid action type provided
    {
        return false;
    }

    if ($ACTION_PERM[$action_type] <= $allowed_permission) { // check if requested action perm is less or equal to allowed perm
        return true;
    }
    return false;
}

/*
function getSessionWorkspaceInfo(): BSONDocument
{
    $WORKSPACE_INFO = unserialize($_SESSION['active_workspaces'][$_SERVER['HTTP_X_WSID']]);
    return $WORKSPACE_INFO;
}
*/

function validateAndGetWsInfo(string $action_type)
{
    if(hasValidCredentials())
    {
        /*
        $WORKSPACE_INFO = getSessionWorkspaceInfo();
        $WS_PAGES = $WORKSPACE_INFO->active_roleInfo->pages;


        if(!isset($WORKSPACE_INFO->ws_subscriptionId) || $WORKSPACE_INFO->ws_subscriptionId === null) {
            http_response_code(500);
            echo json_encode(['request_status' => 500, 'message' => 'Subscription Identifier does not exist!']);
        }
        else {
            */
            $allowedPerm = (int) $_SERVER['HTTP_X_ALLOWED_PERM'];
            if(isActionAllowed($action_type, $allowedPerm)) {
                return [];
            }
            else {
                http_response_code(403);
                echo json_encode(['request_status' => 403, 'message' => 'Insufficient Permissions']);
            }
        //}
    }
    //else response is handled by hasValidCredentials()
    return false;
}

/*function getWsid(): ObjectId
{
    $hashed_wsid = $_SERVER['HTTP_X_WSID'];
    return WorkspaceManager::getWorkspaceOid($hashed_wsid);
}*/

function getModuleData(bool $getCompanionData = false): void
{
    $WS_INFO = validateAndGetWsInfo('fetch');
    if($WS_INFO !== false)
    {
        try {
            $Module = new api\ModuleDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);

            $data = null;
            if (!$getCompanionData) {
                $data = $Module->fetch(getPostPayload());
            }
            else {
                $payload = getPostPayload();
                if(isset($payload['companion_id']) && gettype($payload['companion_id']) === 'string' && strlen($payload['companion_id']) >= 20) {
                    $filtersAndOptions = $payload['filtersAndOptions'] ?? [];
                    $data = $Module->getCompanionData($payload['companion_id'], $filtersAndOptions);
                }
                else {
                    http_response_code(400);
                    $data = [
                        'request_status' => 0,
                        'message' => 'Invalid or incomplete request parameters'
                    ];
                }

            }
            echo json_encode($data);
        }
        catch (\Exception $e)
        {
            http_response_code(500);
            die(json_encode([
                'request_status' => 0,
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]));
        }
    }
}

function insertDocuments(): void
{
    $WS_INFO = validateAndGetWsInfo('insert');
    if($WS_INFO !== false)
    {
        /*$ws_subscriptionOid = $WS_INFO->ws_subscriptionId;
        $moduleLibrary = new ModuleLibrary();
        $moduleParams = $moduleLibrary->getModuleParameters($_SERVER['HTTP_X_MODULE_ID']); //to verify for required fields */
        $documentsToInsert = getPostPayload()['documents'] ?? [];
        $numOfDocuments = count($documentsToInsert);

        if ($numOfDocuments === 0) { //inserted nothing
            exit(
                json_encode([
                    'insert_status' => 2,
                    'error_message' => 'No documents to insert',
                    'inserted_documents' => []
                ])
            );
        }

        //Check if all the required fields exist.
        $required_fields_exist = true;
        /*foreach ($documentsToInsert as $document)
        {
            foreach ($moduleParams['required_fields'] as $field_name => $data_type)
            {
                if(!isset($document[$field_name])) {
                    $required_fields_exist = false;
                    break 2; //breaks out of both loops
                }
            }
        }*/

        if($required_fields_exist)
        {
            try {
                $Module = new api\ModuleDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
                $data = $Module->insert($documentsToInsert);
                echo json_encode($data);
            }
            catch (\Exception $e)
            {
                http_response_code(500);
                die(json_encode([
                    'message' => $e->getMessage(),
                    'line' => $e->getLine()
                ]));
            }
        }
        else {
            echo json_encode([
                'insert_status' => 0,
                'error_message' => 'Missing required fields',
                'inserted_documents' => []
            ]);
        }
    }
}

function deleteDocuments(): void
{
    $WS_INFO = validateAndGetWsInfo('delete');
    if ($WS_INFO !== false) {
        //$ws_subscriptionOid = $WS_INFO->ws_subscriptionId;
        $documentsToDelete = getPostPayload()['document_ids'] ?? [];
        $documentsToDelete = gettype($documentsToDelete) === 'array' ? $documentsToDelete : [];

        if (empty($documentsToDelete)) { // nothing to delete
            exit(
                json_encode([
                    'delete_status' => 2,
                    'error_message' => 'No documents to delete',
                    'deleted_ids' => []
                ])
            );
        }

        try {
            $Module = new api\ModuleDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
            $data = $Module->delete($documentsToDelete);
            echo json_encode($data);
        }
        catch (\Exception $e)
        {
            http_response_code(500);
            die(json_encode([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]));
        }
    }
}

function updateDocument(): void
{
    $WS_INFO = validateAndGetWsInfo('update');
    if ($WS_INFO !== false) {
        //$ws_subscriptionOid = $WS_INFO->ws_subscriptionId;
        $postPayload = getPostPayload();

        if (!isset($postPayload['_id']) || !isset($postPayload['updates'])) {
            http_response_code(400);
            exit(
                json_encode([
                    'update_status' => 2,
                    'error_message' => 'Incomplete update information'
                ])
            );
        }

        try {
            $Module = new api\ModuleDev($_SERVER['HTTP_X_MODULE_FN'], $_SERVER['HTTP_X_MODULE_ID'], $_SERVER['HTTP_X_DATASET_ID']);
            $data = $Module->update($postPayload);
            echo json_encode($data);
        }
        catch (\Exception $e)
        {
            http_response_code(500);
            die(json_encode([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]));
        }
    }
}
