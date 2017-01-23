<?php

namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM as orm;

/**
 */
class SessionService extends Service
{

	protected $idsession = NULL;

	protected $cache = array();

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\SessionService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	function init($idsession)
	{
		$this->idsession = $idsession;

		$items = $this->getAll();

		// session disabled
		if (isset($items['disabled']))
		{
			throw new \Exception("Session $idsession is disabled");
		}

		// new session
		if ( ! $items)
		{
			$this->add('created', time());
		}

		//update some stuff
		$this->add('updated', time());
		$this->add('expire', time() + 24 * 3600);

		// cache data for the current request
		foreach ($items as $key => $value)
		{
			$this->cache[$key] = $value;
		}
	}

	function add($key, $value)
	{
		$model = new orm\SessionModel();
		$model->idsession = $this->idsession;
		$model->item = $key;
		$model->value = $value;

		orm\DataMapperManager::create($model);

		$this->cache[$key] = $value;
	}

	function remove($key)
	{
		$model = new orm\SessionModel();
		$model->idsession = $this->idsession;
		$model->item = $key;

		orm\DataMapperManager::delete($model);

		unset($this->cache[$key]);
	}

	function get($key, $cache = true)
	{
		if ($cache && isset($this->cache[$key]))
		{
			return $this->cache[$key];
		}

		$primaries = array();
		$primaries[] = $this->idsession;
		$primaries[] = $key;

		$model = orm\DataMapperManager::findBykey('dbsite.session', $primaries);

		return $model ? $model->value : NULL;
	}

	function getAll()
	{
		if ( ! $this->idsession)
		{
			throw new \Exception("missing id session");
		}

		$primaries = array();
		$primaries[] = $this->idsession;
		$list =  orm\DataMapperManager::findAllBy('dbsite.session', 'idsession', $this->idsession, array(), NULL);

		$newList = array();

		foreach ($list as $model)
		{
			$newList[$model->item] = $model->value;
		}

		return $newList;
	}

	function getIdSession()
	{
		return $this->idsession;
	}

	function destroy()
	{
		$this->add('disabled', true);
	}

}