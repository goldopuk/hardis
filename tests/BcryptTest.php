<?php

use Stayfilm\stayzen\Bcrypt;

class BcryptTest extends PHPUnit_Framework_TestCase
{
    public function testConfig()
    {
    	$this->assertTrue(Bcrypt::validate('123456', '$2a$08$NDM4MDUxOTM4NTE2YzczNuuqFA/n76KOdaNsgaH6DTIkWrhWQDvY.'));

    	
    }
	
	
}