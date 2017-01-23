<?php

use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\services as serv;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints as c;


class MiscTest extends PHPUnit_Framework_TestCase
{
    public function testValidate()
    {
		
		$str = 'juliencolob@gmail.com';
		
		$validator = Validation::createValidator();
		
		$violations = $validator->validateValue($str, new Email());
		
		$this->assertEquals(0, $violations->count());
		
		$str = 'toto';
		
		$violations = $validator->validateValue($str, new Email());
		
		$this->assertEquals(1, $violations->count());
		
		
		$constraints = new c\Regex(array('pattern' => '/^[a-z]+$/')) ;
		
		$str = 'juliencolomb';
		$violations = $validator->validateValue($str, $constraints);
		$this->assertEquals(0, $violations->count());
		
		$str = '123';
		$violations = $validator->validateValue($str, $constraints);
		$this->assertEquals(1, $violations->count());
		
    }
	
	
	public function testServiceClass() 
	{
		$userServ = serv\UserService::getInstance();
		
		$this->assertEquals('UserService', $userServ->getServiceName());
		
		$this->assertEquals('UserService:myevent', $userServ->getEventName('myevent'));
	}
	
	
}