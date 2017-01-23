<?php

use Stayfilm\stayzen\SolrClient;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\orm as orm;
use Stayfilm\stayzen\services as serv;

// use Stayfilm\stayzen\utilities;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class SolrClientTest extends PHPUnit_Framework_TestCase
{

	static function setUpBeforeClass()
	{
		orm\DataMapperManager::truncateTables('dbsite', array('user', 'usersearch'));
	}

	public function testSolrClient()
	{
		$this->markTestSkipped();
		
		$config = Application::$config->solr;

		$userServ = serv\UserService::getInstance();

		$user = new orm\UserModel();
		$user->username = 'john2';
		$user->password = 123456;
		$userServ->createUser($user);

		// create a client instance
		$client = new SolrClient('usersearch');

		$query = $client->createSelect();

		$query->setQuery('username:john')
				->setOmitHeader(false);

		$client->execute($query);

		// query all
		$select = $client->createSelect();
		$select->setQuery('*:*');
		$client->execute($select);

	}

}
