<?php
namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\Application;

class CacheService extends Service
{
	static protected $_instance = null;
	protected $_cache = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\CacheService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function get($cacheName)
	{
		if ( ! $this->isActive())
		{
			return;
		}

		if ($this->_cache->hasItem($cacheName))
		{
			return unserialize($this->_cache->getItem($cacheName));
		}
		else
		{
			return;
		}
	}

	function isActive()
	{
		return Application::$config->cache_active;
	}

	public function set($cacheName, $value)
	{
		if ( ! $this->isActive())
		{
			return;
		}

		$this->_cache->setItem($cacheName, serialize($value));
	}

	function __construct()
	{
		if ($this->isActive())
		{
			$this->_cache = \Zend\Cache\StorageFactory::factory(array(
				'adapter' => array(
					'name' => 'filesystem',
					'ttl' => 0,
					'options' => array('cache_dir' => Application::$config->cache_dir),
				),
				'plugins' => array(
					// Don't throw exceptions on cache errors
	//				'exception_handler' => array(
	//					'throw_exceptions' => false
	//				),
				)
			));
		}
	}
}