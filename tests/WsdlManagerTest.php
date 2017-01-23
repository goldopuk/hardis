<?php

use Symfony\Component\Yaml\Parser;
use \Stayfilm\stayzen\WsdlManager;
use Stayfilm\stayzen\Application;

class WsdlManagerTest extends PHPUnit_Framework_TestCase
{
	function setUp() 
	{
		$this->conn = Application::getConnection();
	}
	
	public function testOne() {

		$yaml = new Parser();

		$schema = $yaml->parse(file_get_contents(STAYZEN_ROOT . '/services/wsdl.yml'));
		$m = new WsdlManager($schema);
		
		$service = $m->getService("user", "getUserByUsername");
		
		$this->assertEquals('user', $service['service']);
		$this->assertEquals('getUserByUsername', $service['method']);
		
		
	}
}