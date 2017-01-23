<?php

use Stayfilm\stayzen\services as serv;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class KeyStoreTest extends PHPUnit_Framework_TestCase
{

	public static function setUpBeforeClass()
	{
		//DataMapperManager::truncateTables('dbsite',  array('user'));
		//DataMapperManager::truncateTables('dbstay',  array('album', 'useralbum'));
	}

	public function testKeyStore()
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();

		$type     = 'genre';
		$idobject = 1;
		$key      = 'name';
		$value    = 'Lucas';

		$keyStoreServ->set($type, $idobject, $key, $value);

		$keyStore = $keyStoreServ->get($type, $idobject, $key);

		$this->assertEquals('Lucas', $keyStore['name']);

		$keyStoreServ->remove($type, $idobject, $key);

		$keyStore2 = $keyStoreServ->get($type, $idobject, $key);

		$this->assertTrue( ! isset($keyStore2->name));
	}
}