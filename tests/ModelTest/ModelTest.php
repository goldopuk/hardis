<?php

use Stayfilm\stayzen\ORM\UserModel;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM\DataMapperManager;
use cassandra\Compression;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class ModelTest extends PHPUnit_Framework_TestCase {

	function setUp()
	{
		DataMapperManager::truncateTables('dbsite',  array('user'));

		$this->conn = Application::getConnection('dbsite');
		$this->keyspaceName = zen\Application::$config->database->dbsite->keyspace;
	}

	public function testGetAttrs()
	{
		$user = new UserModel();

		$user->iduser = 'uuid';
		$user->name   = 'toto';

		$this->assertEquals('uuid', $user->iduser);
		$this->assertEquals('toto', $user->name);

		$this->assertEquals('dbsite', $user->getKeyspaceName());
		$this->assertEquals('user', $user->getModelName());
	}

	public function testLoadFromCqlRow()
	{
		$iduser = \phpcassa\UUID::uuid4();
		$cql1 = " INSERT INTO {$this->keyspaceName}.user (iduser, username, country, birthday, city) VALUES ($iduser, 'toto', 'France', 123456, 'Nantes')";
		$this->conn->query($cql1);

		$cql2 = "SELECT * FROM {$this->keyspaceName}.user WHERE iduser = $iduser";
		$rows = $this->conn->query($cql2);

		$m = Application::$schemaManager;

		$dm = new Stayfilm\stayzen\ORM\DefaultDataMapper($m);

		$user = new UserModel();

		$dm->loadModelFromCQLRow($user, $rows[0]);

		$this->assertEquals('toto', $user->username);
	}
}