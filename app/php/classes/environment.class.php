<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes;
require_once $_SERVER['DOCUMENT_ROOT']. '/../php/inc/loadEnvironmentVariables.php';

use Exception;
use Hashids\Hashids;
use MongoDB\BSON\ObjectId;

class environment
{
	private Hashids $hashids;
	private ObjectId $wsid; // workspace oid
	
	/**
	 * @param mixed $wsid
	 * @throws Exception
	*/
	function __construct($wsid)
	{
		$this->hashids = new Hashids($_ENV['APP_SALT']);
		$this->wsid = gettype($wsid) === 'string' ? new ObjectId($wsid) : $wsid; //converts wsid to mdb id if string, TODO: make it consistent
	}
	
	public static function decodeHashedId($hashedId = ''): string
	{
		$hashedId = substr(trim($hashedId), 0, 30);
		if($hashedId !== '') {
			$Hashids = new Hashids($_ENV['APP_SALT']);
			$hashedId = $Hashids->decodeHex($hashedId);
		}
		return $hashedId;
	}

	public static function hashOid(ObjectId $object_id): string
	{
		$hashid = new Hashids($_ENV['APP_SALT']);
		return $hashid->encodeHex((string)$object_id);
	}

	public static function unhashId(string $hashed_id): string
	{
		$hashid = new Hashids($_ENV['APP_SALT']);
		return $hashid->decodeHex($hashed_id);
	}
}
