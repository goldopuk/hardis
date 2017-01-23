<?php
namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\CassaClient;
use \Stayfilm\stayzen as zen;
use Stayfilm\stayzen\Publisher;
use \Zend\Log\Logger;

/**
 * Description of Service
 *
 * @author julien
 */
class Service extends Publisher
{

	static $requester = NULL;
	static protected $permissionEnabled = FALSE;

	static public function getInstance()
	{
		$classname = get_called_class();

		if ( ! $classname::$_instance)
		{
			if (self::useMockup())
			{
				$classname = $classname . 'Mockup';
				$classname::$_instance = new $classname();
			}
			else
			{
				$instance = new $classname();

				if (zen\Application::$config->profiler === true)
				{
					$classname::$_instance =  new zen\ServiceProfilerProxy($instance);
				}
				else
				{
					$classname::$_instance = $instance;
				}
			}
		}

		return $classname::$_instance;
	}

	static public function filterFields($model)
	{
		$securityM = zen\SecurityManager::getInstance();

		$fields = $securityM->getAllowedFields($model, self::$requester);

		$attrs = $model->getAttrs();

		foreach(array_keys($attrs) as $key)
		{
			if ( ! in_array($key, $fields))
			{
				$model->unsetField($key);
			}
		}

		return $model;
	}

	static function setRequester($requester)
	{
		self::$requester = $requester;
	}

	static public function enablePermission()
	{
		self::$permissionEnabled = TRUE;
	}

	static public function disablePermission()
	{
		self::$permissionEnabled = FALSE;
	}

	/**
	 *
	 * service name
	 *
	 * @var string
	 */
	protected $name;

	/**
	  *
	  * @var Stayfilm\stayzen\DB
	  */
	static protected $conn;

	/**
	 *
	 * @var boolean
	 */
	static protected $useGlobalMockup;

	/**
	 *
	 * @var boolean
	 */
	static protected $useLocalMockup;

	/**
	 *
	 * @var Zend\logger
	 */
	static protected $logger;


	function __construct()
	{
		$reflector = new \ReflectionClass($this);
		$this->name = $reflector->getShortName();
	}

	/**
	 *
	 * @param Stayfilm\stayzen\CassaClient
	 */
	static function setConnection($conn)
	{
		self::$conn = $conn;
	}

	/**
	 *
	 * @param Zend\Log\Logger $logger
	 */
	static public function setLogger(\Zend\Log\Logger $logger)
	{
		self::$logger = $logger;
	}

	/**
	 *
	 * @return Stayfilm\stayzen\CassaClient
	 */
	static public function getConnection()
	{
		return self::$conn;
	}

	/**
	 *
	 * @param boolean $b
	 */
	static public function useMockup($b = null)
	{
		if ($b === null) {
			return self::$useGlobalMockup;
		}

		self::$useGlobalMockup = (boolean) $b;
	}

	static public function useLocalMockup($b = null)
	{
		if ($b === null)
		{
			return self::$useLocalMockup;
		}

		self::$useLocalMockup = (boolean) $b;
	}

	/**
	 *
	 * @param string $eventName
	 * @return string
	 */
	public function getEventName($eventName)
	{
		return $this->name . ":" . $eventName;
	}

	/**
	 *
	 * @return string
	 */
	public function getServiceName()
	{
		return $this->name;
	}
}
