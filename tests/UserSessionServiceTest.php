<?php

use Stayfilm\stayzen\services\UserSessionService;
use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\ORM\UserSessionModel;
use Stayfilm\stayzen\ORM\Model;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\utilities;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class UserSessionServiceTest extends PHPUnit_Framework_TestCase
{

	static public $conn;

	public static function setUpBeforeClass()
	{
		self::$conn = Service::getConnection();

		orm\DataMapperManager::truncateTables('dbsite',  array('usersession'));
	}

	public function testcreateUserSession()
	{
		//$this->session->set_userdata('iduser', 'teste');

		$userSessServ = UserSessionService::getInstance();

		$userSess = new UserSessionModel();
		$userSess->iduser = (string) \phpcassa\UUID::uuid4();
		$userSess->created = time();
		$userSess->ipaddress = "1.1.1.1"; //$this->input->server('REMOTE_ADDR');
		$userSess->useragent = "User Agent"; //$this->input->user_agent();
		$userSess->lastactivity = time();
		$userSess->sessionid = "aaa";
		
		$userSess = $userSessServ->createUserSession($userSess);

		return $userSess->idusersession;
	}
}