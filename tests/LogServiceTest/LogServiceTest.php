<?php

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\Application;
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services as serv;
/**
 * @extends PHPUnit_Framework_TestCase
 */
class LogServiceTest extends PHPUnit_Framework_TestCase {

	static function setUpBeforeClass()
	{
		DataMapperManager::truncateTables('dbsite',  array('codecstat'));
	}

	function testSaveCodecStat()
	{
		$logServ = serv\LogService::getInstance();
		DataMapperManager::disableCache();

		$codec = 'mp4.qv1';

		$logServ->saveCodecStat($codec, 'probably');
		$logServ->saveCodecStat($codec, 'probably');
		$logServ->saveCodecStat($codec, 'probably');

		$codecStat = $logServ->getCodecStat($codec);

		$this->assertEquals(3, $codecStat->probably);

		$logServ->saveCodecStat($codec, 'maybe');
		$logServ->saveCodecStat($codec, 'maybe');

		$codecStat = $logServ->getCodecStat($codec);

		$this->assertEquals(2, $codecStat->maybe);

		$logServ->saveCodecStat($codec, 'empty');
		$logServ->saveCodecStat($codec, 'empty');

		$codecStat = $logServ->getCodecStat($codec);

		$this->assertEquals(2, $codecStat->empty);
	}

}