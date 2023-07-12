<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);
namespace core\classes;

require_once $_SERVER['DOCUMENT_ROOT']. '/../php/inc/loadEnvironmentVariables.php';

use Hashids\Hashids;
use MongoDB\BSON\ObjectId;

class ModuleLibrary
{
    private \MongoDB\Database $mdb;
    private Hashids $hashids;

    public function __construct()
    {
        $this->mdb = getConnection()->module_library;
        $this->hashids = new Hashids($_ENV['APP_SALT']);
    }

    private function getModuleByOid(ObjectId $module_oid, bool $essentialFieldsOnly = false): array
    {
        $options = [];
        if($essentialFieldsOnly) {
            $options = [
                'projection' => [
                    'developer_id' => 1,
                    'category_id' => 1,
                    'mod_name' => 1,
                    'namespace' => 1,
                    'mod_perm_type' => 1
                ]
            ];
        }

        $modules = $this->mdb->modules->findOne(['_id' => $module_oid], $options);

        return (array)$modules;
    }

    public function getModules(array $filters = []): array //TODO: implement module filters for search and sorting
    {
        $modules = $this->mdb->modules->find()->toArray();
        $numOfModules = count($modules);

        for($i = 0; $i < $numOfModules; $i++) {
            $modules[$i]['_id'] = $this->hashids->encodeHex($modules[$i]['_id']);
        }
        return $modules;
    }

    public function getModuleParameters(string $hashed_module_id): array
    {
        $mod_id_length = strlen(trim($hashed_module_id));
        if ($mod_id_length < 20 || $mod_id_length > 50) //invalid hashed id
            return [];

        //decode hashed id and create oid.
        $module_oid = $this->hashids->decodeHex($hashed_module_id);
        $module_oid = new ObjectId($module_oid);

        $modParams = $this->mdb->modules->findOne(
            [
                '_id' => $module_oid
            ],
            [
                'projection' => [
                    '_id' => 0,
                    'mod_name' => 1,
                    'namespace' => 1,
                    'required_fields' => 1
                ]
            ]
        )->getArrayCopy();
        $modParams['required_fields'] = (array) $modParams['required_fields'];

        return $modParams;
    }

    public function getModLibConnection(): \MongoDB\Database
    {
        return $this->mdb;
    }

    public function getModuleFullName(ObjectId $module_oid): string
    {
        $moduleInfo = $this->getModuleByOid($module_oid, true);

        if(isset($moduleInfo['namespace']) && isset($moduleInfo['mod_name'])) {
            return ($moduleInfo['namespace'].'_'.$moduleInfo['mod_name']);
        }
        return '';
    }
}
