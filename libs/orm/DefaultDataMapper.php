<?php

namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen\ORM\CQLQuery;
use Stayfilm\stayzen\ORM\ModelFactory;
use Stayfilm\stayzen\Utilities;
use phpcassa\Schema\DataType\DateType;
use phpcassa\Schema\DataType\Int32Type;
use phpcassa\Schema\DataType\LongType;
use phpcassa\Schema\DataType\IntegerType;
use phpcassa\Schema\DataType\BooleanType;
use Stayfilm\stayzen\Application;

/**
 * Description of DefaultDataMapper
 *
 * @author julien
 */
class DefaultDataMapper
{

	/**
	 *
	 * @var \Stayfilm\stayzen\ORM\SchemaManager
	 */
	static $schemaManager;

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\SchemaManager $schemaManager
	 */
	public function __construct($schemaManager)
	{
		self::$schemaManager = $schemaManager;
	}

	/**
	 *
	 * @param Model $model
	 * @throws \Exception
	 */
	public function delete($model)
	{
		debug('DefaultDataMapper::delete()');

		$query = new CQLQuery($model->getModelName(), null, $model->getKeyspaceName());

		$pk = self::$schemaManager->getPrimaryKey($model->getModelName(), $model->getKeyspaceName());

		if ($pk['type'] === 'composite')
		{
			foreach ($pk['cols'] as $col)
			{
				$field = $col['name'];
				$value = $model->{$col['name']};

				$query->where($field, $value, $col['type'], '=');
			}
		}
		else if ($pk["type"] === 'uuid' || $pk["type"] === 'bigint' || $pk["type"] === 'int' || $pk["type"] === 'text')
		{
			$val = $model->{$pk['name']};

			if ($pk["type"] === 'uuid' && ! Utilities::isValidUUID4($val) )
			{
				throw new \Exception("Invalid UUID $val");
			}

			$field = $pk['name'];

			$query->where($field, $model->$pk['name'], $pk["type"], '=');
		}
		else
		{
			throw new \Exception("wrong type");
		}

		$query->delete();

		return;
	}

	function create($model)
	{
		$query = new CQLQuery($model->getModelName(), null, $model->getKeyspaceName());

		if ( ! $model->isNew())
		{
			throw new \Exception("Can not create a model with status NEW");
		}

		$pk = self::$schemaManager->getPrimaryKey($model->getModelName(), $model->getKeyspaceName());

		$ttlKey = self::$schemaManager->getOption($model->getModelName(), $model->getKeyspaceName(), 'ttl');

		if ($ttlKey)
		{
			$query->ttl(Application::$config->$ttlKey);
		}

		if ($pk["type"] === 'uuid')
		{
			if ($model->{$pk['name']})
			{
				$val = $model->{$pk['name']};

				if ( ! Utilities::isValidUUID4($val) )
				{
					throw new \Exception("Invalid UUID $val");
				}
			}
			else
			{
				// if the pk is already set, don' generate an id
				// create and set the UUID
				$uuid = (string) \phpcassa\UUID::uuid4();
				$model->{$pk['name']} = $uuid;
			}
		}
		else if ($pk["type"] === 'bigint')
		{
			if (! $model->{$pk['name']})
			{ // same than above
				$model->{$pk['name']} = round(microtime(true) * 100);
				//$model->{$pk['name']} = time();
			}
		}
		else if ($pk["type"] === 'text')
		{
			if ( ! $model->{$pk['name']})
			{
				throw new \Exception("Key is type TEXT. Should be set in model before creating");
			} // else do nothing
		}
		else if ($pk['type'] === 'composite')
		{
			// do nothing
			// the composite keys are supposed to be already set.
		}
		else
		{
			throw new \Exception("wrong type");
		}

		// created field
		if (self::$schemaManager->hasField($model->getModelName(), 'created', $model->getKeyspaceName()))
		{
			$model->created = time();
		}

		if (self::$schemaManager->hasField($model->getModelName(), 'updated', $model->getKeyspaceName()))
		{
			$model->updated = time();
		}

		$newattrs = $this->convert($model, false /* insert */);

		// TODO : check what's in the retrun
		$query->save($newattrs);

		$model->setAsSync();

		return $model;
	}

	function update($model)
	{
		$query = new CQLQuery($model->getModelName(), null, $model->getKeyspaceName());

		if ($model->isNew())
		{
			throw new \Exception('Can not update a model with status NEW');
		}

		if ( ! $model->isDirty())
		{
			return $model;
		}

		$ttlKey = self::$schemaManager->getOption($model->getModelName(), $model->getKeyspaceName(), 'ttl');

		if ($ttlKey)
		{
			$query->ttl(Application::$config->$ttlKey);
		}

		$pk = self::$schemaManager->getPrimaryKey($model->getModelName(), $model->getKeyspaceName());

		if ($pk["type"] === 'uuid' || $pk["type"] === 'bigint' || $pk["type"] === 'text')
		{
			$val = $model->{$pk['name']};

			if ($pk["type"] === 'uuid' && ! Utilities::isValidUUID4($val) )
			{
				throw new \Exception("Invalid UUID $val");
			}

			$query->where($pk['name'], $val, $pk['type'], '=');
		}
		else if ($pk['type'] === 'composite')
		{

			$values = array();
			$values[] = ''; // the first element will hold the sql

			foreach ($pk['cols'] as $col)
			{
				$query->where($col['name'], $model->{$col['name']}, $col['type'], '=');
			}
		}
		else
		{
			throw new \Exception("wrong type");
		}

		if (self::$schemaManager->hasField($model->getModelName(), 'updated', $model->getKeyspaceName()))
		{
			$model->updated = time();
		}

		$newattrs = $this->convert($model, true);

		unset($newattrs['created']);

		// TODO : check what's in the $res
		$query->save($newattrs);

		$model->setAsSync();

		return $model;
	}

	protected function convert($model)
	{
		$newattrs = array();

		if ($model->isNew())
		{
			$attrs = $model->getAttrs();
		}
		elseif ($model->isDirty())
		{
			$attrs = $model->getModifiedAttrs();
		}
		else
		{
			throw new \Exception("Model ");
		}

		$table = $model->getModelName();

		$pk = self::$schemaManager->getPrimaryKey($table, $model->getKeyspaceName(), false);

		foreach ($attrs as $key => $val)
		{
			if ($model->isDirty() && in_array($key, $pk))
			{
				continue;
			}

			$el = array();

			$el['name'] = $key;

			$type = self::$schemaManager->getColumnType($model->getModelName(), $key, $model->getKeyspaceName());

			if ($type === 'serialized')
			{
				$el['value'] = serialize($val);
			}
			elseif ($type === 'json')
			{
				$el['value'] = json_encode($val);
			}
			elseif (is_array($val) || is_object($val))
			{
				$el['value'] = serialize($val);
			}
			elseif (is_bool($val))
			{
				$el['value'] = ($val ? '1' : '0');
			}
			else
			{
				$el['value'] = $val;
			}

			$el['type'] = self::$schemaManager->getColumnType($table, $key, $model->getKeyspaceName());

			$newattrs[$key] = $el;
		}

		return $newattrs;
	}

	function findByKey($modelName, $value, $excludes = array())
	{
		list($db, $table) = $this->parseModelName($modelName);

		$fields = $this->getFields($db, $table, $excludes);

		$query = new CQLQuery($table, $fields, $db);

		$pk = self::$schemaManager->getPrimaryKey($table, $db);

		if ($pk["type"] === 'uuid' || $pk["type"] === 'bigint' || $pk["type"] === 'int' || $pk["type"] === 'text')
		{
			if ($pk["type"] === 'uuid' && ! Utilities::isValidUUID4($value) )
			{
				throw new \Exception("Invalid UUID $value");
			}

			$query->where($pk['name'], $value, $pk["type"], '=');
		}
		else if ($pk['type'] === 'composite')
		{
			if (is_array($value))
			{
				$i = 0;

				foreach ($pk['cols'] as $col)
				{
					if (isset($value[$i]))
					{
						if ( $value[$i] === NULL)
						{
							throw new \Exception('Missing value in composite key.');
						}

						$query->where($col['name'], $value[$i], $col['type'], '=');
					}

					$i++;
				}
			}
			else
			{
				$query->where($pk['cols'][0]['name'], $value, $pk['cols'][0]['type'], '=');
			}
		}
		else
		{
			throw new \Exception("wrong type {$pk["type"]}");
		}

		$rows = $query->select();

		if (count($rows) === 0)
		{
			return null;
		}

		// create a model
		$model = ModelFactory::build($modelName);

		$this->loadModelFromCqlRow($model, $rows[0]);

		$model->setAsSync();

		return $model;
	}

	/**
	 *
	 * @param type $modelName
	 * @return string
	 */
	protected function parseModelName($modelName)
	{
		if (strpos($modelName, '.') !== false)
		{
			list($db, $table) = explode('.', $modelName);
		}
		else
		{
			$table = $modelName;
			$db = 'dbsite';
		}

		return array($db, $table);
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param string $value
	 * @return \Stayfilm\stayzen\ORM\Model
	 * @throws Exception
	 */
	function findBy($modelName, $fields, $values, $selectFields = array())
	{
		list($db, $table) = $this->parseModelName($modelName);

		$selectFields = $this->getFields($db, $table, $selectFields);

		$query = new CQLQuery($table, $selectFields, $db);

		if ( ! is_array($fields))
		{
			$fields = array($fields);
		}

		if ( ! is_array($values))
		{
			$values = array($values);
		}

		if (count($fields) != count($values))
		{
			throw new \Exception("fields does not equal to values");
		}

		$i = 0;

		foreach ($fields as $field)
		{
			$type = self::$schemaManager->getColumnType($table, $field, $db);

			switch($field[0]) //first letter of the string - can < OR >
			{
				case '>':
					$operator = '>';
					$field = substr($field, 1, strlen($field));
					break;
				default:
					$operator = '=';
					break;
			}

			$query->where($field, $values[$i], $type, $operator);
			$i++;
		}

		$rows = $query->select();

		if (!is_array($rows))
		{
			throw new Exception("rows should be an array");
		}

		if (count($rows) === 0)
		{
			return null;
		}

		// create a model
		$model = ModelFactory::build($modelName);

		$this->loadModelFromCqlRow($model, $rows[0]);

		$model->setAsSync();

		return $model;
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param string $value
	 * @return array
	 * @throws Exception
	 */
	function findAllBy($modelName, $fields, $values, $selectFields = array(), $limit = 20, $order = NULL)
	{
		list($db, $table) = $this->parseModelName($modelName);

		$selectFields = $this->getFields($db, $table, $selectFields);

		$query = new CQLQuery($table, $selectFields, $db);

		if ($limit)
		{
			$query->limit($limit);
		}

		if ( ! is_array($fields))
		{
			$fields = array($fields);
		}

		if ( ! is_array($values))
		{
			$values = array($values);
		}

		if (count($fields) != count($values))
		{
			throw new \Exception("fields does not equal to values");
		}

		if ($order)
		{
			$query->order($order);
		}

		$i = 0;
		foreach ($fields as $field)
		{
			switch($field[0])
			{
				case '>':
					$operator = '>';
					$field = substr($field, 1, strlen($field));
					break;
				case '<':
					$operator = '<';
					$field = substr($field, 1, strlen($field));
					break;
				case '@':
					$operator = 'IN';
					$field = substr($field, 1, strlen($field));
					break;
				default:
					$operator = '=';
					break;
			}

			if (strpos($field, "token(") === false)
			{
				$type = self::$schemaManager->getColumnType($table, $field, $db);
				$query->where($field, $values[$i], $type, $operator);
			}
			else //hack to accept some function from cassandra (token)
			{
				$query->where($field, $values[$i], 'func', $operator);
			}

			$i++;
		}

		$rows = $query->select();

		if (!is_array($rows))
		{
			throw new Exception("rows should be an array");
		}

		if (count($rows) === 0)
		{
			return array();
		}

		$list = array();

		foreach ($rows as $row)
		{
			// create a model
			$model = ModelFactory::build($modelName);
			$model = $this->loadModelFromCqlRow($model, $row);
			$model->setAsSync();
			$list[] = $model;
		}

		return $list;
	}

	function findAllByWhere($modelName, $selectFields = array(), $where = null, $limit = 20)
	{
		list($db, $table) = $this->parseModelName($modelName);

		$selectFields = $this->getFields($db, $table, $selectFields, false);

		$query = new CQLQuery($table, $selectFields, $db);

		if ($where)
		{
			$query->where($where);
		}

		if ($limit)
		{
			$query->limit($limit);
		}

		$rows = $query->select();

		if ( ! is_array($rows))
		{
			throw new Exception("rows should be an array");
		}

		if (count($rows) === 0)
		{
			return array();
		}

		$list = array();

		foreach ($rows as $row)
		{
			// create a model
			$model = ModelFactory::build($modelName);
			$model = $this->loadModelFromCqlRow($model, $row);
			$model->setAsSync();
			$list[] = $model;
		}

		return $list;
	}

	/**
	 *
	 * @param string $db
	 * @param string $table
	 * @param array $excludes
	 * @return mixed
	 */
	protected function getFields($db, $table, $fields = null)
	{
		$selectedFields = array('*');

		if ($fields)
		{
			if ( ! is_array($fields))
			{
				throw new \Exception("fields should be an array");
			}

			$selectedFields = array();

			$cols = self::$schemaManager->getColumns($table, $db, true);

			if (in_array($fields[0], array('+', '-')))
			{
				$operator = array_shift($fields);

				if ($operator === '+')
				{
					$selectedFields = array_unique(array_merge($cols, $fields));
				}
				else if ($operator === '-')
				{
					$selectedFields = array_diff($cols, $fields);
				}
			}
			else
			{
				$selectedFields = $fields;
			}

			if ( ! $selectedFields)
			{
				throw new \Exception('Selected fields empty');
			}
		}

		return $selectedFields;
	}

	/**
	 *
	 * @param string $modelName
	 * @param int $limit
	 * @param int $offset
	 * @param array $excludes
	 * @return array
	 * @throws \Exception
	 */
	function findAll($modelName, $selectFields = array(), $limit = 20)
	{
		list($db, $table) = $this->parseModelName($modelName);

		$selectFields = $this->getFields($db, $table, $selectFields);

		$query = new CQLQuery($table, $selectFields, $db);

		if ($limit && $limit > 0)
		{
			$query->limit($limit);
		}

		$rows = $query->select();

		if ( ! is_array($rows))
		{
			throw new \Exception("rows should be an array");
		}

		if (count($rows) === 0)
		{
			return array();
		}

		$list = array();

		foreach ($rows as $row)
		{
			// create a model
			$model = ModelFactory::build($modelName);
			$this->loadModelFromCqlRow($model, $row);
			$model->setAsSync();
			$list[] = $model;
		}

		return $list;
	}

	/**
	 *
	 * @param string $modelName
	 * @param string $field
	 * @param array $values
	 * @return array
	 * @throws Exception
	 */
	function findAllIn($modelName, $field, $values, $selectFields = array(), $limit = NULL)
	{
		list($db, $table) = $this->parseModelName($modelName);

		$selectFields = $this->getFields($db, $table, $selectFields);

		$query = new CQLQuery($table, $selectFields, $db);

		$type = self::$schemaManager->getColumnType($table, $field, $db);
		$query->where($field, $values, $type, 'IN');

		if ($limit)
		{
			$query->limit($limit);
		}

		$rows = $query->select();

		if (!is_array($rows))
		{
			throw new Exception("rows should be an array");
		}

		if (count($rows) === 0)
		{
			return array();
		}

		$list = array();

		foreach ($rows as $row)
		{
			// create a model
			$model = ModelFactory::build($modelName);
			$this->loadModelFromCqlRow($model, $row);
			$model->setAsSync();
			$list[] = $model;
		}

		return $list;
	}

	/**
	 * @param $modelName
	 * @param $field
	 * @param $value
	 * @return mixed
	 * @throws \Exception
	 */
	function countBy($modelName, $field, $value)
	{
		list($db, $table) = $this->parseModelName($modelName);

		$fields = array();
		$fields[0] = "COUNT(*)";

		$query = new CQLQuery($table, $fields, $db);

		if (is_array($field) && is_array($value))
		{
			if (count($field) !== count($value))
			{
				throw new \Exception('Number of fields is not equal to number of values.');
			}

			for ($c = 0; $c < count($field); $c++)
			{
				$type = self::$schemaManager->getColumnType($table, $field[$c], $db);

				$query->where($field[$c], $value[$c], $type, '=');
			}
		}
		else
		{
			$type = self::$schemaManager->getColumnType($table, $field, $db);

			$query->where($field, $value, $type, '=');
		}

		$rows = $query->select();

		return $rows[0]['count'];
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\Model $model
	 * @param \cassandra\CQLRow $cqlrow
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	function loadModelFromCqlRow($model, $cqlrow)
	{
		foreach ($cqlrow as $name => $value)
		{
			// ignore automatically created fields for solr
			if (strpos($name, '_') === 0)
			{
				continue;
			}

			if (in_array($name, array('solr_query')))
			{
				continue;
			}

			try
			{
				$type = self::$schemaManager->getColumnType($model->getModelName(), $name, $model->getKeyspaceName());
			}
			catch (\Exception $e)
			{
				continue; //ignore the field if it does not exist
			}

			if ($value)
			{
				switch ($type)
				{
					case 'serialized':
						$value = unserialize($value);
						break;
					case 'json':
						$value = json_decode($value, true);
						break;
					default:
				}
			}

			$model->{$name} = $value;
		}

		return $model;
	}

}
