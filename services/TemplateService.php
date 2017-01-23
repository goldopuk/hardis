<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen as zen;


class TemplateService extends TableService
{
	static private $_instance = null;
	
	const TABLE = 'dbstay.job';
	
	/**
	 *
	 * @return Stayfilm\stayzen\services\UserService
	 */
	static public function getInstance()
	{
		if (!self::$_instance)
		{
			if (self::useMockup())
			{
				$classname = __CLASS__ . 'Mockup';
				self::$_instance = new $classname(self::TABLE);
			}
			else
			{
				self::$_instance = new self(self::TABLE);
			}
		}

		return self::$_instance;
	}

}