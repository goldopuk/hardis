<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\UserSessionModel;
use \Stayfilm\stayzen\Bcrypt;

/**
 * Service to UserSession.
 *
 * @author Fabiano Simões <fabiano@stayfilm.com>
 */
class UserSessionService extends Service
{

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\UserSessionService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param string $uuid
	 * @return Stayfilm\stayzen\ORM\UserSessionModel
	 */
	public function getUserSessionByKey($uuid)
	{
		return DataMapperManager::findByKey('usersession', $uuid);
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\UserSessionModel $usersession
	 * @return Stayfilm\stayzen\ORM\UserSessionModel
	 * @throws \Exception
	 */
	public function createUserSession($usersession)
	{
		$usersession = DataMapperManager::create($usersession);

		return $usersession;
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\UserSessionModel $usersession
	 * @param string $sessiionid
	 * @return Stayfilm\stayzen\ORM\UserSessionModel
	 * @throws \Exception
	 */
	public function updateUserSession($userSession, $sessionid)
	{
		if ($sessionid != $userSession->sessionid)
		{
			$userSession->sessionid = $sessionid;
			$userSession->lastactivity = time();
			$userSession = DataMapperManager::update($userSession);
		}

		return $userSession;
	}

	/**
	 *
	 * @return string
	 */
	public function helloWorld()
	{
		return "Stayzen is talking to you and saying... Hello World !";
	}

}