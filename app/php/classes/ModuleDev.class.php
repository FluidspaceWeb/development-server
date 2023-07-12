<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\api;
require_once $_SERVER['DOCUMENT_ROOT']. '/../php/inc/loadEnvironmentVariables.php';
require_once __DIR__.'/microservice/CDN.class.php';

use classes\microservice\CDN;
//use core\classes\environment;
use core\classes\ModuleLibrary;
use core\classes\Workspace\WorkspaceManager;
use Hashids\Hashids;
use MongoDB\BSON\ObjectId;

class ModuleDev
{
    protected Hashids $hashids;
    protected Hashids $environmentHashids;
    protected string $hashed_module_id;
    protected string $module_fullName;
    protected ObjectId $module_oid;
    protected string $dataset_id;

    private \MongoDB\Database $mdb;

	private array $VALID_LOGIC_OP = ['$or', '$nor', '$and'];
	private array $SUPPORTED_OP = ['$or', '$nor', '$and', '$eq', '$ne', '$lt', '$lte', '$gt', '$gte', '$in', '$nin'];
	private int $MAX_RECURSION_DEPTH = 10;

    public function __construct(string $module_fullName, string $hashed_module_id, string $dataset_id)
    {
        $this->mdb = getConnection()->selectDatabase($_ENV['MDB_MODDATA_DB_NAME']);
        $this->hashids = new Hashids($_ENV['MODULE_SALT']); //module data has different salt from environment
		
        $this->environmentHashids = new Hashids($_ENV['APP_SALT']); //separate salt for module identifiers

        $module_oid = new ObjectId($this->environmentHashids->decodeHex($hashed_module_id));

        $this->module_fullName = $module_fullName;
        $this->hashed_module_id = $hashed_module_id;
        $this->module_oid = $module_oid;
        $this->dataset_id = $dataset_id;
    }

    private function getProjectionFields(&$fields_array): array
    {
        /*
         *  Generates associative array of field name as key and 1 as value for only that field to be returned
         */
        $projection_fields = [];
        $numOfFields = count($fields_array);
        for($i = 0; $i < $numOfFields; $i++) {
            if(gettype($fields_array[$i]) === 'string' && trim($fields_array[$i]) !== '') {
                $projection_fields[$fields_array[$i]] = 1;
            }
        }

        // DEFAULTS
        $projection_fields['_id'] = 1;
        $projection_fields['_created_on'] = 1;
        $projection_fields['_last_modified'] = 1;

        return $projection_fields;
    }

    protected function getQueryOptions(array $filterOptions): array
    {
        $DEFAULTS = [
            'page' => 1,
            'limit' => 15,
            'sort_field' => '_created_on',
            'sort_order' => -1
        ];

        $mdb_options = [];

        //for pagination; based on page number and limit per page
        if(isset($filterOptions['page']) && (gettype($filterOptions['page']) === 'integer') && ($filterOptions['page'] > 0)) {
            $pageNum = $filterOptions['page'] - 1;

            $limit = $DEFAULTS['limit'];
            if(isset($filterOptions['limit']) && (gettype($filterOptions['limit']) === 'integer') && ($filterOptions['limit'] > 0 && $filterOptions['limit'] <= 50))
            {
                $limit = $filterOptions['limit'];
            }
            $skipCount = $pageNum * $limit;

            $mdb_options['limit'] = $limit;
            $mdb_options['skip'] = $skipCount;
        }
        else {
            $mdb_options['limit'] = $DEFAULTS['limit'];
        }

        //for sorting
        if(isset($filterOptions['search_text']) && gettype($filterOptions['search_text']) === 'string' && trim($filterOptions['search_text']) !== '')
        {   //if filtering by search_text then user's sort options are overridden
            $mdb_options['sort'] = [
                'score' => [
                    '$meta' => 'textScore'
                ]
            ];
        }
        elseif(isset($filterOptions['sort_field']) && isset($filterOptions['sort_order'])) //sort by specified field name and order
        {
            $fieldName = substr(trim($filterOptions['sort_field']), 0, 30);
            $sortOrder = $DEFAULTS['sort_order'];

            if($filterOptions['sort_order'] === -1 || $filterOptions['sort_order'] === 1)
            {
                $sortOrder = $filterOptions['sort_order'];
            }

            $mdb_options['sort'] = [
                $fieldName => $sortOrder
            ];
        }
        else { //invalid sort options provided, use defaults
            $mdb_options['sort'] = [
                $DEFAULTS['sort_field'] => $DEFAULTS['sort_order']
            ];
        }

        //for field projections
        if(isset($filterOptions['get_fields']) && gettype($filterOptions['get_fields']) === 'array' && !empty($filterOptions['get_fields'])) {
            $mdb_options['projection'] = $this->getProjectionFields($filterOptions['get_fields']);
        }

        return $mdb_options;
    }

    private function isValidFilterOperator(string $operator): bool
    {
        $supported_operators = ['eq', 'ne', 'lt', 'lte', 'gt', 'gte'];
        return in_array($operator, $supported_operators, true);
    }

	/*
	 * 	THIS METHOD RECURSIVELY SCANS DEEPLY NESTED ARRAYS TO VALIDATE UNGAINST SUPPORT OPERATIONS FOR THE QUERY
	 * 	IT RETURNS VOID, AND TAKES THE REFERENCE OF QUERY ARRAY, DIRECTLY OPERATING ON IT.
	 *
	 * 	Working:
	 *		The $key can be either integer for list-type array or string for associative array
	 * 		The $val can be any allowed data type including array
	 *
	 *		If the current depth is less than max allowed depth for validation then it proceeds.
	 * 		|	If $val is array then each element is iterated as $n_key => $n_val, this pair dives deep with recursion
	 * 		|	|	If $n_key is string type meaning its in associative array then check if mongo operator or custom field name.
	 * 		|	|	|	Mongo operators (starting with $) are validated against SUPPORTED_OP, if valid then continues with recursion else skips and
	 * 		|	|	|	...throws error for unsupported query.
	 * 		|	|	|
	 * 		|	|	|	Custom field names are added to $latched_key, now subsequent recursions search for its value which must be of any supported type.
	 * 		|	|
	 * 		|	Elseif $val is any other supported datatype then
	 * 		|	|	If fields are internal fields then the values are converted to appropriate object class.
	 * 		|	|	elseif internal secret fields are set to empty string.
	 * 		|	|	else kept untouched.
	 *		|
	 * 		|	Else throws error for unsupported data type
	 * 		|
	 * 		Else, max validation depth has been exceeded and query is not applied.
	 * */
	private function recursiveExpressionValidation(&$key, &$val, $current_depth = 0, $latched_key = NULL)
	{
		if($current_depth < $this->MAX_RECURSION_DEPTH) { // when current depth is less than max allowed depth for scanning and validating
			// pretty print
			//echo "\n";
			//for($i = 0; $i < $current_depth; $i++) {echo "\t";}
			//echo "--- $key at depth $current_depth\n";

			$valType = gettype($val);
			if($valType === 'array') //If true then dive deep with recursion
			{
				foreach($val as $n_key => &$n_val) { //for each field of the array $val
					//pretty print
					//for($i = 0; $i < $current_depth+1; $i++) {echo "\t";}
					//echo "> $n_key\n";

					$shoudProceed = true;	//proceed only if supported operator or custom field name
					if(is_string($n_key)) {
						if($n_key[0] === '$' && !in_array($n_key, $this->SUPPORTED_OP, true)) {
							$shoudProceed = false;
						}
						elseif($n_key[0] !== '$') {
							$latched_key = $n_key;
						}
					}

					if($shoudProceed) {
						$this->recursiveExpressionValidation($n_key, $n_val, $current_depth+1, $latched_key);
					}
					else {
						//echo ">>>> THROWING ERROR $key for $n_key\n\n";
						throw new \Exception('Unsupported Operation Encountered! Query not applied.');
					}
				}
			}
			//	reached value of the $latched_key got from earlier recursions.
			elseif($valType === 'string' || $valType ===  'integer' || $valType === 'double' || $valType === 'boolean' || $valType === 'array')
			{
				$key_isString = is_string($latched_key);

				//echo "FOUND at depth $current_depth:\n";
				if($key_isString && ($latched_key === '_created_on' || $latched_key === '_last_modified')) {
					$val = new \MongoDB\BSON\UTCDateTime($val);
					//echo "Converted to MongoDateTime\n";
				}
				elseif($key_isString && ($latched_key === '_id' || $latched_key === '_created_by')) {
					if($latched_key === '_id') {
						$val = $this->hashids->decodeHex($val);
					}
					else { //use environment hashids for _created_by
						$val = $this->environmentHashids->decodeHex($val);
					}
					$val = new ObjectId($val);
					//echo "Converted to ObjectId\n";
				}
				elseif($key_isString && ($latched_key === '_dataset' || $latched_key === '_subscription')) {
					$val = '';
				}
				//echo "$latched_key => $val\n\n";
			}
			else {
				throw new \Exception('Unsupported Data-Type Encountered! Query not applied.');
			}
		}
		else {
			//echo "\nDEPTH MAXED\n";
			$val = []; //query is cleared and no user provided query is applied
		}
	}

	private function sanitizeCustomQuery(&$customQueryArray): void
	{
		$validatedQuery = [];
		try {
			//for each key in query associative array
			foreach($customQueryArray as $key => &$val) { // Iterates for all fields at ROOT of the query

				//check if logical expression is valid and its value is of array type (as per MDB specifications for the logical operator)
				// recursion is used to validate nested fields and operators
				if(in_array($key, $this->VALID_LOGIC_OP, true) && gettype($val) === 'array' && !empty($val)) {
					//echo "================= $key =================\n";
					$this->recursiveExpressionValidation($key, $val, 0);
				}
			}
			//print_r($customQueryArray);
		}
		catch(\Exception $err) {
			$customQueryArray = []; //clears the query, no user provided query applied
			//echo 'The query has errors. '.$err->getMessage()."\n\n";
		}
	}

    private function generateBulkFilters(&$filters_array): array
    {
        $SUPPORTED_VALUE_TYPES = ['string', 'integer', 'double', 'boolean', 'NULL'];
        $numOfFilters = count($filters_array);
        $filters = [];

        for($i = 0; $i < $numOfFilters; $i++) {
            $filter = $filters_array[$i];

            if(isset($filter['value'])) // VALIDATE FILTER VALUE FIELD EXISTS OR NOT AND WHETHER ITS DATA TYPE IS SUPPORTED
            {                           // IF NOT THEN SKIP
                $value_type = gettype($filter['value']);
                if(in_array($value_type, $SUPPORTED_VALUE_TYPES, true)) // IF VALUE DATA TYPE IS SUPPORTED, ELSE SKIP
                {
                    if(isset($filter['field']) && gettype($filter['field']) === 'string' && $filter['field'] !== '')    // VERIFY FIELD TYPE, IF UNSUPPORTED THEN SKIP
                    {
                        //  USE OPERATOR IF SUPPORTED, ELSE DEFAULT TO EQ OPERATION.
                        if(isset($filter['operator']) && gettype($filter['operator']) === 'string' && $this->isValidFilterOperator($filter['operator'])) {
                            $op = '$'.$filter['operator'];
                            $filters[$filter['field']] = [
                                $op => $filter['value']
                            ];
                        }
                        else { // DEFAULTS TO EQ
                            $filters[$filter['field']] = $filter['value'];
                        }
                    }
                }
            }
        }

        return $filters;
    }

    protected function getQueryFilters(array $filterOptions): array
    {
        $DEFAULTS = [
            'filter_operator' => 'eq',
            'filter_value' => ''
        ];

        $mdb_filters = [];

        //check if period range is set, if yes then create mongodb UTC datetime object and use the range as filter condition
        if(isset($filterOptions['period_start']) && gettype($filterOptions['period_start']) === 'integer') {
            $current_utc_millis = (new \DateTime('now'))->getTimestamp() * 1000;
            $start_mdbObj = new \MongoDB\BSON\UTCDateTime($filterOptions['period_start']);
            $end_mdbObj = new \MongoDB\BSON\UTCDateTime($current_utc_millis); //current date as default ending period

            //set passed period_end seconds if it exists and is not after current utc_seconds
            if(isset($filterOptions['period_end']) && gettype($filterOptions['period_start']) === 'integer' && $filterOptions['period_end'] <= $current_utc_millis) {
                $end_mdbObj = new \MongoDB\BSON\UTCDateTime($filterOptions['period_end']);
            }

            $mdb_filters['$and'] = [
                [
                    '_created_on' => ['$gte' => $start_mdbObj]
                ],
                [
                    '_created_on' => ['$lte' => $end_mdbObj]
                ],
            ];
        }

		//filter by requested value and its comparison
		//  FOR CUSTOM VALIDATED MDB QUERY
		if(isset($filterOptions['query']) && (gettype($filterOptions['query']) === 'array') && !empty($filterOptions['query'])) {
			$this->sanitizeCustomQuery($filterOptions['query']);
			$mdb_filters = array_merge($mdb_filters, $filterOptions['query']);
		}
		//  FOR MULTIPLE FILTERS IN AND-TYPE OPERATION
        elseif(isset($filterOptions['filters']) && (gettype($filterOptions['filters']) === 'array') && !empty($filterOptions['filters'])) {
            $bulkFilters = $this->generateBulkFilters($filterOptions['filters']);
            $mdb_filters = array_merge($mdb_filters, $bulkFilters);
        }
		//  FOR SINGLE AND-TYPE OPERATION
        elseif(isset($filterOptions['filter_field']) && (gettype($filterOptions['filter_field']) === 'string') && !empty(trim($filterOptions['filter_field'])))
        {
            $filter_field = substr(trim($filterOptions['filter_field']), 0, 30);
            $filter_value = $filterOptions['filter_value'] ?? $DEFAULTS['filter_value'];
            $filter_value_type = gettype($filter_value);

            //check if filter field is hashed id, if yes then un-hash and convert to oid
            if(($filter_field === '_id' || $filter_field === '_created_by') && $filter_value_type === 'string' && $filter_value !== '') {
                try { //to prevent error on invalid hashed id
					if($filter_field === '_id') {
						$filter_value = new ObjectId($this->hashids->decodeHex($filter_value));
					}
					else { // use environment hashids for _created_by field
						$filter_value = new ObjectId($this->environmentHashids->decodeHex($filter_value));
					}
                    $filter_value_type = 'ObjectId';
                }
                catch (\Exception $err) { //will cause string type $eq operation in later step.
                    $filter_value_type = 'string';
                }
            }

            //validate filter operator
            if(isset($filterOptions['filter_operator'])
                && (gettype($filterOptions['filter_operator']) === 'string')
                && $this->isValidFilterOperator($filterOptions['filter_operator'])
                && $filter_value_type !== 'ObjectId'
            )
            {
                $filter_op = '$'.$filterOptions['filter_operator']; //prepends operator keyword with $ to make it mdb operator
                if($filter_value_type === 'integer' || $filter_value_type === 'double' || $filter_value_type === 'boolean') {
                    $mdb_filters[$filter_field] = [$filter_op => $filter_value];
                }
                else { // string type
					$mdb_filters[$filter_field] = $filter_value; //eq operation
				}
            }
            else { //invalid or unsupported filter operator or search on ID
                //only if supported value type, else ignore
                if($filter_value_type === 'string' || $filter_value_type === 'boolean' || $filter_value_type === 'integer' || $filter_value_type === 'double' || $filter_value_type === 'ObjectId')
                {
                    $mdb_filters[$filter_field] = $filter_value; //eq operation
                }
            }
        }

        //if text search requested
        if(isset($filterOptions['search_text']) && gettype($filterOptions['search_text']) === 'string' && trim($filterOptions['search_text']) !== '') {
            $cleanedText = substr(trim($filterOptions['search_text']), 0, 40);
            $mdb_filters['$text'] = [
                '$search' => $cleanedText
            ];
        }

        return $mdb_filters;
    }

    protected function getUTCMillis(\MongoDB\BSON\UTCDateTime $mongo_dateTime): int
    {
        $dt = $mongo_dateTime->toDateTime();
        $millis = (int) $dt->format('Uv');
        return $millis;
    }

    public function fetch(array $filterOptions = []): array
    {
        $module_fullName = $this->module_fullName;

        $filters = $this->getQueryFilters($filterOptions);
        //$filters['_subscription'] = $this->ws_subscription_oid; //mandatory filter
        $filters['_dataset'] = $this->dataset_id; //mandatory filter

        $options = $this->getQueryOptions($filterOptions);

        //get total count of documents as per the filter
        $total_count = $this->mdb->$module_fullName->countDocuments($filters);

        //Execute query and get results from mdb
        $documents = $this->mdb->$module_fullName->find($filters, $options)->toArray();

        //convert mongo date to UTC millis and hash the oids
        foreach ($documents as &$document) {
            $document['_id'] = $this->hashids->encodeHex($document['_id']);
            $document['_created_on'] = $this->getUTCMillis($document['_created_on']);
            $document['_last_modified'] = (isset($document['_last_modified']) && $document['_last_modified'] !== null)? $this->getUTCMillis($document['_last_modified']) : null;

            //  REMOVE CRUCIAL INTERNAL FIELDS FROM RESPONSE
            if(isset($document['_dataset'])) {
                unset($document['_dataset']);
            }
            if(isset($document['_subscription'])) {
                unset($document['_subscription']);
            }

            if(isset($document['_created_by']) && $document['_created_by'] !== null) {  //can be null if user account is deleted
            	$isOid = is_a($document['_created_by'], 'MongoDB\BSON\ObjectId');
				if(!$isOid
					&& isset($filterOptions['resolve_creator'])
					&& $filterOptions['resolve_creator'] === true
					&& isset($document['_created_by']['_id'])
				) {
					// resolved user info
					$document['_created_by']['_id'] = $this->environmentHashids->encodeHex($document['_created_by']['_id']);
					$document['_created_by']['img'] = CDN::getUserProfileImagePath($document['_created_by']['_id']);
				}
				elseif ($isOid && (!isset($filterOptions['resolve_creator']) || $filterOptions['resolve_creator'] === false)) {
					// unresolved user info, but _created_by is Oid
					$document['_created_by'] = $this->environmentHashids->encodeHex($document['_created_by']);
				}
                elseif (is_a($document['_created_by'], 'MongoDB\Model\BSONDocument') && is_a($document['_created_by']['_id'], 'MongoDB\BSON\ObjectId')) {
                    // unresolved user info, but _created_by is array and contains _id as Oid. For development - user simulation.
                    $document['_created_by'] = $this->environmentHashids->encodeHex($document['_created_by']['_id']);
                }
				else {
					// unacceptable field value type
					$document['_created_by'] = null;
				}
			}
        }

        $page_num = $filterOptions['page'] ?? -1;
        return [
            'page' => $page_num,
            'count' => count($documents),
            'total_documents' => $total_count,
            'documents' => $documents
        ];
    }

    public function insert(array $documents_data = []): array
    {
        if(empty($documents_data)) {
            return [];
        }

        //adds mandatory fields to all the documents to be inserted. These fields are generally not displayed on FE.
        $utcDateTime = new \MongoDB\BSON\UTCDateTime();
        $numOfDocuments = 0;
        foreach ($documents_data as &$document) {
            $numOfDocuments++;
            $document['_id'] = new ObjectId();
            $document['_created_on'] = $utcDateTime;
            $document['_last_modified'] = $utcDateTime;
            $document['_dataset'] = $this->dataset_id;
			$document['_created_by'] = [ // in production version this is only user OID. Here its resolved for sake of dummy info.
				'_id' => new ObjectId('601b07a8f37deb54d26e1567'),
				'fname' => 'John',
				'lname' => 'Doe',
				'email' => 'alltimejohndoe@domain.tld'
			];
            // $document['_subscription'] = $this->ws_subscription_oid;
        }

        //insert document in the module collection
        $module_colName = $this->module_fullName;
		/** @noinspection PhpUnusedLocalVariableInspection */
		$inserted_count = 0;
        if(count($documents_data) === 1)
        {
            $insertDoc = $this->mdb->$module_colName->insertOne($documents_data[0]);
            $inserted_count = $insertDoc->getInsertedCount();
        }
        else
        {
            $insertDocs = $this->mdb->$module_colName->insertMany($documents_data);
            $inserted_count = $insertDocs->getInsertedCount();
        }

        //remove fields that not needs to be sent to FE
        $utcMillis = $this->getUTCMillis($utcDateTime);
        foreach ($documents_data as &$document) {
            $document['_id'] = $this->hashids->encodeHex($document['_id']);
            $document['_created_on'] = $utcMillis;
            $document['_last_modified'] = $utcMillis;
            // created by field is handled differently here from the production version of api because
			// dev version has resolved info stored in the _created_by field
            $document['_created_by'] = $this->environmentHashids->encodeHex($document['_created_by']['_id']);
            unset($document['_dataset']);
            // unset($document['_subscription']);
        }

        return [
            'insert_status' => 1,
            'documents_count' => $numOfDocuments,
            'inserted_count' => $inserted_count,
            'inserted_documents' => $documents_data
        ];
    }

    public function delete(array $document_ids = []): array
    {
        $validated_oids = [];
        foreach ($document_ids as &$doc_id) {
            $doc_id = $this->hashids->decodeHex($doc_id);
            if(WorkspaceManager::isValidMdbId($doc_id)) {
                $validated_oids[] = new ObjectId($doc_id);
            }
        }
        if(empty($validated_oids)) {
            return [
                'delete_status' => 2,
                'deleted_count' => 0,
                'error_message' => 'No documents to delete or invalid id provided',
                'deleted_ids' => []
            ];
        }

        $module_colName = $this->module_fullName;
        $deleteDocuments = $this->mdb->$module_colName->deleteMany(
            [
                '_id' => [
                    '$in' => $validated_oids
                ]
            ]
        );
        $deletedCount = $deleteDocuments->getDeletedCount();

        //converts oids to hashed ids using module full name as salt
        foreach ($validated_oids as &$val_id) {
            $val_id = $this->hashids->encodeHex($val_id);
        }

        //if nothing deleted then return empty array in deleted_ids field
        if($deletedCount === 0) {
            $validated_oids = [];
        }

        return [
            'delete_status' => 1,
            'deleted_count' => $deletedCount,
            'deleted_ids' => $validated_oids //this now has hashed validated ids
        ];
    }

    public function update(array $documentToUpdate): array {
        $document_oid = $this->hashids->decodeHex($documentToUpdate['_id']);
        if(WorkspaceManager::isValidMdbId($document_oid)) {
            $document_oid = new ObjectId($document_oid);
        }
        else {
            return [
                'update_status' => 3,
                'error_message' => 'Invalid document identifier'
            ];
        }

        if(empty($documentToUpdate['updates'])) { //nothing to update
            return [
                'update_status' => 1,
                'error_message' => 'Nothing to update',
                'updated_count' => 0,
                'updated_document' => []
            ];
        }

        //SET DATE OF ACTION AS LAST MODIFIED DATETIME
        $documentToUpdate['updates']['_last_modified'] = new \MongoDB\BSON\UTCDateTime();

        $module_colName = $this->module_fullName;
        $updateDocument = $this->mdb->$module_colName->findOneAndUpdate(
            [
                '_id' => $document_oid
            ],
            [
                '$set' => $documentToUpdate['updates'] //TODO: implement nested object specific updates
            ],
            [
                'projection' => [
                    '_created_on' => 0,
                    '_dataset' => 0,
                    '_created_by' => 0,
                    '_subscription' => 0
                ],
                'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
            ]
        );

        $updated_count = 0;
        if($updateDocument !== null) {
            $updateDocument['_id'] = $documentToUpdate['_id']; //replaces oid with original hashed id
            $updateDocument['_last_modified'] = (isset($updateDocument['_last_modified']) && $updateDocument['_last_modified'] !== null)? $this->getUTCMillis($updateDocument['_last_modified']) : null;
            $updated_count += 1;
        }

        return [
            'update_status' => 1,
            'updated_count' => $updated_count,
            'updated_document' => $updateDocument ?? []
        ];
    }

    /*
     * This function fetches data on behalf of another module (a.k.a. companion)
     * verifies if the companion module id is valid or not
     * Checks if the dataset in use by the workspace has the companion module or not
     * If dataset has the companion then gets companion's full name (<namespace>_<mod_name>)
     * Updates class ($this) variables to the companion's info
     * Makes request using the fetch method which now gets companion's info
     *
     * This method allows accessing one module another module's data inorder to create submodule inside itself.
     * */
    public function getCompanionData(string $hashed_companion_id, array $filtersAndOptions = []): array
    {
        $companion_oid = $this->environmentHashids->decodeHex($hashed_companion_id);
        if(!WorkspaceManager::isValidMdbId($companion_oid)) {
            return [
                'request_status' => 0,
                'message' => 'Invalid Companion ID'
            ];
        }

        $companion_oid = new ObjectId($companion_oid);
        //$dsHasModule = $this->datasetHasModule($companion_oid);
        $dsHasModule = true; //hard-coded - ONLY FOR DEVELOPMENT

        if($dsHasModule) {
            $moduleLibrary = new ModuleLibrary();
            $modFullName = $moduleLibrary->getModuleFullName($companion_oid); //get companion's full name

            //update the class variables with companion's info
            //this enables request to happen on behalf of the companion module
            if($modFullName !== '') {
                $this->hashed_module_id = $hashed_companion_id;
                $this->module_oid = $companion_oid;
                $this->module_fullName = $modFullName; //only full name is used to make the companion request

                //performs fetch using standard method and return the response
                return $this->fetch($filtersAndOptions);
            }
            else {
                return [
                    'request_status' => 404,
                    'message' => 'Could not find companion\'s database'
                ];
            }
        }

        return [
            'request_status' => 204,
            'message' => 'Companion has no data in the active dataset'
        ];
    }
}
