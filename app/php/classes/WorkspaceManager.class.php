<?php
// Copyright (c) 2023 Rishik Tiwari
// 
// This Source Code Form is subject to the terms of the Mozilla Public
// License, v. 2.0. If a copy of the MPL was not distributed with this
// file, You can obtain one at http://mozilla.org/MPL/2.0/.

declare(strict_types=1);

namespace core\classes\Workspace;

require_once $_SERVER['DOCUMENT_ROOT'] . '/../php/inc/loadEnvironmentVariables.php';
//require_once 'environment.class.php';
//require_once 'rolesManager.class.php';
//require_once 'usersManager.class.php';
//require_once __DIR__ . '/microservice/CDN.class.php';

//use core\classes\environment;
//use core\classes\rolesManager;
//use core\classes\usersManager;
//use classes\microservice\CDN;
use Hashids\Hashids;
use MongoDB\BSON\ObjectId;

class WorkspaceManager
{
	private Hashids $hashids;
	private $mdb;
	private ObjectId $user_id;
	//private int $ws_count;
	//private array $ws_info;

	public function __construct(string $user_id)
	{
		$this->hashids = new Hashids($_ENV['APP_SALT']);
		$conc_mdb = connectDb();

		if($conc_mdb !== false) {
			$this->mdb = $conc_mdb;
			$this->user_id = new ObjectId($user_id);
		}
		else {
			die('Connection to MDB Failed!');
		}
	}

	public static function isValidMdbId(string $mdbId): bool //pass only OID string
	{
		if(is_string($mdbId)) {
			if(strlen($mdbId) == 24 && ctype_xdigit($mdbId)) {
				return true;
			}
		}
		return false;
	}
}
