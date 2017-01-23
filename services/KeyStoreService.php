<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM as orm;

class KeyStoreService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.keystore';
	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\KeyStoreService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function set($type, $idobject, $key, $value)
	{
		if ( ! $type)
		{
			throw new \Exception('Missing type parameter.');
		}

		if ( ! $idobject)
		{
			throw new \Exception('Missing idobject parameter.');
		}

		if ( ! $key)
		{
			throw new \Exception('Missing key parameter.');
		}

		$keyStore = new orm\KeyStoreModel();

		$keyStore->type     = $type;
		$keyStore->idobject = $idobject;
		$keyStore->key      = $key;
		$keyStore->value    = $value;

		DataMapperManager::create($keyStore);
	}

	public function get($type, $idobject = NULL, $key = NULL)
	{
		if ( ! $type)
		{
			throw new \Exception('Missing type parameter.');
		}

		$field = array();
		$field[] = 'type';

		$value = array();
		$value[] = $type;

		if ($idobject)
		{
			$field[] = 'idobject';
			$value[] = $idobject;

			if ($key)
			{
				$field[] = 'key';
				$value[] = $key;
			}
		}

		$config = DataMapperManager::findAllBy($this->table, $field, $value, array('key', 'value'));

		$configArr = array();

		if ($config && ! is_array($config))
		{
			$config = array($config);
		}

		if ($config)
		{
			foreach ($config as $item)
			{
				$configArr[$item->key] = $item->value;
			}
		}

		return $configArr;
	}

	public function remove($type, $idobject, $key)
	{
		if ( ! $type)
		{
			throw new \Exception('Missing type parameter.');
		}

		if ( ! $idobject)
		{
			throw new \Exception('Missing idobject parameter.');
		}

		if ( ! $key)
		{
			throw new \Exception('Missing key parameter.');
		}

		$keyStoreArr = $this->get($type, $idobject, $key);

		if ( ! $keyStoreArr)
		{
			throw new \Exception("Register register with key: #type: {$type}, #idobject: {$idobject}, #key: {$key}");
		}

		$keyStore = new orm\KeyStoreModel();
		$keyStore->type     = $type;
		$keyStore->idobject = $idobject;
		$keyStore->key      = $key;

		DataMapperManager::delete($keyStore);
	}
}