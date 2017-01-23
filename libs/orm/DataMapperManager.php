<?php

namespace Stayfilm\stayzen\ORM;
use Stayfilm\stayzen\Utilities;

/**
 * Description of DataMapperManager
 *
 * @author julien
 */
class DataMapperManager
{

	/**
	 *
	 * @var array
	 */
	static protected $cols = array();

	/**
	 *
	 * @var \Stayfilm\stayzen\ORM\SchemaManager
	 */
	static public $schemaManager;

	/*
	 * @var \Stayfilm\stayzen\ORM\IdentityMap
	 */
	static public $identityMap;

	/*
	* @var boolean
	*/
	static public $isCacheActive = true;

	/**
	 *
	 * @var string
	 */
	protected $table;

	/**
	 *
	 * @param string $keyspace
	 * @param \cassandra\CassandraClient $conn
	 */
	static function init($schemaManager, $identityMap = null)
	{
		self::$schemaManager = $schemaManager;
		self::$identityMap = $identityMap ? $identityMap : new IdentityMap() ;
	}

	/**
	 *
	 */
	static function disableCache()
	{
		self::$isCacheActive = false;
	}

	/**
	 *
	 */
	static function enableCache()
	{
		self::$isCacheActive = true;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\Model $model
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	static function create($model)
	{
		$dm = self::getDataMapper($model->getModelName());
		return $dm->create($model);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\Model $model
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	static function update($model)
	{
		$dm = self::getDataMapper($model->getModelName());
		return $dm->update($model);
	}

	/**
	 *
	 * @param string $modelName
	 * @param mixed $id
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	static function findByKey($modelName, $id, $selectFields = array(), $addToCache = true)
	{
		if (self::$isCacheActive && self::$identityMap->has($modelName, $id)) {
			return self::$identityMap->get($modelName, $id);
		}

		$dm = self::getDataMapper($modelName);

		$model = $dm->findByKey($modelName, $id, $selectFields);

		if ($addToCache)
		{
			self::$identityMap->add($modelName, $id, $model);
		}

		return $model;
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param mixed $value
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	static function findBy($modelName, $fields, $values, $selectFields = array())
	{
		$dm = self::getDataMapper($modelName);
		return $dm->findBy($modelName, $fields, $values, $selectFields);
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param string $value
	 * @param array $selectFields
	 * @param int $limit
	 * @return array
	 */
	static function findAllBy($modelName, $field, $value, $selectFields = array(), $limit = 20, $order = NULL)
	{
		$dm = self::getDataMapper($modelName);
		return $dm->findAllBy($modelName, $field, $value, $selectFields, $limit, $order);
	}

	/**
	 *
	 * @param string $modelName
	 * @param int $limit
	 * @param int $offset
	 * @param array $fields
	 * @param boolean $exclude
	 * @return array
	 */
	static function findAll($modelName, $selectFields = array(), $limit = 20)
	{
		$dm = self::getDataMapper($modelName);
		return $dm->findAll($modelName, $selectFields, $limit);
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param array $values
	 * @return array
	 */
	static function findAllIn($modelName, $field, $values, $selectFields = array(), $limit = NULL)
	{
		$dm = self::getDataMapper($modelName);
		return $dm->findAllIn($modelName, $field, $values, $selectFields, $limit);
	}

	/**
	 *
	 * @param type $model
	 */
	static function delete($model)
	{
		if ( ! $model)
		{
			throw new \Exception('Model empty');
		}

		$primaryKeyName = self::$schemaManager->getPrimaryKey($model->getModelName(), $model->getKeyspaceName(), false);

		if (count($primaryKeyName) === 1)
		{
			self::$identityMap->remove($model->getFullModelName(), $model->$primaryKeyName[0]);
		}

		$dm = self::getDataMapper($model->getModelName());
		return $dm->delete($model);
	}


	static function deleteByKey($keyspace, $table, $key, $value)
	{
		$con = CQLQuery::getConnection($keyspace);

		$ksName = Utilities::getRealKeyspaceName($keyspace);

		$cql = "DELETE FROM {$ksName}.$table WHERE $key = $value";

		$con->query($cql);

		return true;
	}


	/**
	 *
	 * @param type $model
	 */
	static function deleteModels($models)
	{

		foreach ($models as $model)
		{
			$dm = self::getDataMapper($model->getModelName());
			$dm->delete($model);

		}
		return count($models);
	}

	/**
	 *
	 * @param type $model
	 * @param type $model
	 * @param type $model
	 */
	static function countBy($modelName, $field, $value)
	{
		$dm = self::getDataMapper($modelName);
		return $dm->countBy($modelName, $field, $value);
	}

	/**
	 *
	 * @param string $modelName
	 * @return \Stayfilm\stayzen\ORM\DefaultDataMapper
	 */
	protected static function getDataMapper($modelName)
	{
		$classname = '\Stayfilm\stayzen\datamapper\\' . ucfirst($modelName) . "DataMapper";

		if (class_exists($classname))
		{
			$dm = new $classname(self::$schemaManager);
		}
		else
		{
			$dm = new DefaultDataMapper(self::$schemaManager);
		}

		return $dm;
	}

	/**
	 *
	 * @param string $modelName
	 */
	static function deleteAll($table, $keyspace = 'dbsite')
	{
		$primaries = self::$schemaManager->getPrimaryKey($table, $keyspace, false);
		$models = self::findAll("$keyspace.$table", $primaries, 200);
		$count = 0;

		while (count($models) > 0)
		{
			$count = 0;

			foreach ($models as $model)
			{
				self::delete($model);

				$count++;
			}

			$models = self::findAll("$keyspace.$table", $primaries, 200);
		}

		return $count;
	}

	/**
	 *
	 * @param string $keyspace
	 * @param array $tables
	 */
	static function truncateTables($keyspace, $tables)
	{
		foreach ($tables as $table)
		{
			self::deleteAll($table, $keyspace);
		}
	}


	/**
	 *
	 * @param type $modelName
	 * @param type $fields
	 * @param type $where
	 * @param type $limit
	 * @return type
	 */
	function findAllByWhere($modelName, $fields = array(), $where = null, $limit = null)
	{
		$dm = self::getDataMapper($modelName);
		return $dm->findAllByWhere($modelName, $fields, $where, $limit);
	}

}
