<?php


use Stayfilm\stayzen\Utilities;

class UtilitiesTest extends PHPUnit_Framework_TestCase {


	function testGetSnConf()
	{
		$conf = Utilities::getSnConf('facebook', 'site');
		$this->assertTrue(array_key_exists('scope', $conf));

		$conf = Utilities::getSnConf('facebook', 'webservices');
		$this->assertTrue(array_key_exists('scope', $conf));

	}
}