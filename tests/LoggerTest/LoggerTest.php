<?php

use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\services as serv;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class LoggerTest extends PHPUnit_Framework_TestCase {

	static function setUpBeforeClass()
	{
		zen\orm\DataMapperManager::truncateTables('dbsite',  array('user', 'log'));
	}

	function testSimpleFormatter()
	{
		$format = '%timestamp% %priorityName% %priority% %message%';

		$formatter = new zen\Zend\Logger\Formatter\SimpleFormatter($format);

		$event = array(
			'timestamp'    => 1,
			'priority'     => 1,
			'priorityName' => 'INFO',
			'message'      => 'hello',
			'extra'        => array()
		);

		$str = $formatter->format($event);

		$this->assertEquals('unknown - 1 INFO 1 hello', $str);
	}

	function testCassaWriter()
	{
		$db = zen\ORM\CQLQuery::getConnection('dbsite');
		$logger = new Logger();
		$writer = new zen\Zend\Logger\Writer\CassaWriter($db, 'log');

		$formatter = new \Zend\Log\Formatter\Simple("%message%");
		$writer->setFormatter($formatter);

		$logger->addWriter($writer);

		$logger->info('test');

		$logger->info(array('key1' => 'data'));

		$logServ = serv\LogService::getInstance();

	}

	function testInMemoryWriter()
	{
		$logger = new Logger();
		$writer = new zen\Zend\Logger\Writer\InMemoryWriter();

		$formatter = new \Zend\Log\Formatter\Simple("%message%");
		$writer->setFormatter($formatter);

		$logger->addWriter($writer);

		$logger->info('test');

		$logger->info(array('key1' => 'data'));
	}

}
