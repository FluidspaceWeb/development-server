<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\api;
use MongoDB\BSON\ObjectId;

require_once 'ModuleDev.class.php';

class ModulePropsDev extends ModuleDev {
    private \MongoDB\Database $DB_MODULES_PROPS; // HAS THE DEVELOPMENT DB

    function __construct(string $module_fullName, string $hashed_module_id, string $dataset_id)
    {
        parent::__construct($module_fullName, $hashed_module_id, $dataset_id);
        $this->DB_MODULES_PROPS = getConnection()->selectDatabase($_ENV['MDB_MODPROPS_DB_NAME']); // USE DEVELOPMENT DATABASE
    }

    private function formatPropsForResponse(&$props): void
    {
        $numOfProps = count($props);
        for($i = 0; $i < $numOfProps; $i++) {
            if(isset($props[$i]['_id'])) {  // hash the id
                $props[$i]['_id'] = $this->hashids->encodeHex($props[$i]['_id']);
            }
            if(isset($props[$i]['_created_on'])) {  // convert MongoDB Date to Unix ms
                $props[$i]['_created_on'] = $this->getUTCMillis($props[$i]['_created_on']);
            }
            if(isset($props[$i]['_subscription'])) {
                unset($props[$i]['_subscription']);
            }
            if(isset($props[$i]['_dataset'])) {
                unset($props[$i]['_dataset']);
            }
        }
    }

    public function getProps(array $filterOptions = []): array
    {
        $mdb = $this->DB_MODULES_PROPS;
        $mod_fn = $this->module_fullName;

        //keep only supported options
        $valid_filterOptions = [];
        $supported_options = ['filter_field', 'field_operator', 'filter_value', 'get_fields'];
        foreach($supported_options as $sopt) { //walks through each supported field and adds to valid_filterOptions[] if supported field exists
            if(isset($filterOptions[$sopt])) {
                $valid_filterOptions[$sopt] = $filterOptions[$sopt];
            }
        }

        //get query related filters
        $filters = $this->getQueryFilters($valid_filterOptions);
//        $filters['_subscription'] = $this->ws_subscription_oid; // NOT REQUIRED FOR DEVELOPMENT PURPOSE
        $filters['_dataset'] = $this->dataset_id; //mandatory filter

        //get query options
        $options = $this->getQueryOptions($valid_filterOptions);
        $options['limit'] = 20; // equals to props per module per dataset limit
        unset($options['sort']); // not applicable for props

        $props = $mdb->$mod_fn->find($filters, $options)->toArray();

        //internal field operations
        $this->formatPropsForResponse($props);

        return $props;
    }

    public function insertProps(array $props): array
    {
        /*
         * Gets count of props for module in the dataset
         * Insert props if numOfPropsToInsert + numOfExistingProps <= 20
         * Else prompt, props limit reached
         * */

        $mod_fn = $this->module_fullName;
        $filters = [
//            '_subscription' => $this->ws_subscription_oid, // NOT REQUIRED FOR DEVELOPMENT PURPOSE
            '_dataset' => $this->dataset_id //mandatory filter
        ];
        //  GET COUNT OF EXISTING PROPS
        $numOfExistingProps = ($this->DB_MODULES_PROPS)->$mod_fn->countDocuments($filters);
        $numOfPropsToInsert = count($props);
        if(($numOfExistingProps + $numOfPropsToInsert) > 20) {
            return [
                'insert_status' => 3,
                'error_message' => 'No props inserted. Number of props exceeds maximum props limit of 20',
                'num_of_existing_props' => $numOfExistingProps
            ];
        }
        //  WITHIN LIMIT, INSERT PROPS
        $utcDateTime = new \MongoDB\BSON\UTCDateTime();
        foreach ($props as &$prop) { //add mandatory fields to the prop and also overwrite if conflict with passed fields.
            $prop['_id'] = new ObjectId();
            $prop['_created_on'] = $utcDateTime;
            $prop['_dataset'] = $this->dataset_id;
//            $prop['_subscription'] = $this->ws_subscription_oid; // NOT REQUIRED FOR DEVELOPMENT PURPOSE
        }

        $inserted_count = 0;
        if($numOfPropsToInsert === 1)
        {
            $insertProp = ($this->DB_MODULES_PROPS)->$mod_fn->insertOne($props[0]);
            $inserted_count = $insertProp->getInsertedCount();
        }
        else
        {
            $insertProps = ($this->DB_MODULES_PROPS)->$mod_fn->insertMany($props);
            $inserted_count = $insertProps->getInsertedCount();
        }

        //internal field operations
        $this->formatPropsForResponse($props);

        return [
            'insert_status' => 1,
            'props_count' => $numOfPropsToInsert,
            'inserted_count' => $inserted_count,
            'inserted_props' => $props
        ];
    }

    public function updateProp(string $prop_hashed_id, array $updates): array
    {
        $mod_fn = $this->module_fullName;
        $prop_oid = null;
        try {
            $prop_oid = $this->hashids->decodeHex($prop_hashed_id);
            $prop_oid = new ObjectId($prop_oid);
        }
        catch (\Exception $err) {
            return [
                'update_status' => 0,
                'error_message' => 'No Prop updated. Invalid Prop ID.',
            ];
        }

        $updatedProp = ($this->DB_MODULES_PROPS)->$mod_fn->findOneAndUpdate(
            [
                '_id' => $prop_oid
            ],
            [
                '$set' => $updates
            ],
            [
                'projection' => [
                    '_dataset' => 0,
                    '_subscription' => 0
                ],
                'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
            ]
        );
        if($updatedProp !== null) {
            $updatedProp = [$updatedProp];
            $this->formatPropsForResponse($updatedProp);
            $updatedProp = $updatedProp[0];
        }


        return [
            'update_status' => ($updatedProp !== null)? 1 : 3, //responds update_status: 3 when prop to update not found
            'updated_prop' => $updatedProp ?? []
        ];
    }

    public function deleteProp(array $prop_hashed_ids): array
    {
        $prop_oids = [];
        foreach ($prop_hashed_ids as &$prop_id) {
            try {
                $pid = $this->hashids->decodeHex($prop_id);
                $prop_oids[] = new ObjectId($pid);
            }
            catch (\Exception $err) {
                return [
                    'delete_status' => 0,
                    'error_message' => 'No prop deleted. Invalid Module Prop ID(s).',
                ];
            }
        }

        $mod_fn = $this->module_fullName;
        $deleteProps = ($this->DB_MODULES_PROPS)->$mod_fn->deleteMany(
            [
                '_id' => [
                    '$in' => $prop_oids
                ]
            ]
        );
        $deletedCount = $deleteProps->getDeletedCount();

        return [
            'delete_status' => 1,
            'deleted_count' => $deletedCount,
            'deleted_ids' => ($deletedCount === 0)? [] : $prop_hashed_ids
        ];
    }
}
