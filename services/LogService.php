<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen as zen;


class LogService extends TableService
{
	static private $_instance = null;

	const TABLE = 'dbstay.logs';

	/**
	 *
	 * @return Stayfilm\stayzen\services\LogService
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

	/**
	 *
	 * @param string $type
	 * @param mixed $data
	 * @param UserModel $user
	 * @return LogModel
	 */
	function create($type, $data, $user = null)
	{
		return false;
		//throw new \Exception("Do not use that function");
	}

	/**
	 *
	 * @param type $user
	 * @return type
	 */
	function getUserLogs($user, $limit = 100)
	{
		return DataMapperManager::findAllBy('dbsite.log', 'iduser', $user->iduser, null, $limit);
	}

	/**
	 *
	 * @param string $codec
	 * @param string $result
	 * @throws \Exception
	 */
	function saveCodecStat($codec, $result)
	{
		if ( ! in_array($result, array('probably', 'maybe', 'empty')))
		{
			throw new \Exception("Value $result is invalid");
		}

		$model = DataMapperManager::findByKey('dbsite.codecstat', $codec);

		if ( ! $model)
		{
			$model = new orm\CodecStatModel();
			$model->codec = $codec;
			$model->probably = 0;
			$model->maybe = 0;
			$model->empty = 0;

			DataMapperManager::create($model);
		}

		$model->$result = $model->$result + 1;

		DataMapperManager::update($model);
	}

	/**
	 *
	 * @param string $codec
	 * @return type
	 */
	function getCodecStat($codec)
	{
		return DataMapperManager::findByKey('dbsite.codecstat', $codec);
	}
	
}
