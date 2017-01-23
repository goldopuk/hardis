<?php

namespace Stayfilm\stayzen\ORM;

/**
 * Description of SchemaManager
 *
 * @author julien
 */
class SchemaManager
{

	/**
	 *
	 * @var \Zend\Config\Config
	 */
	protected $schema;

	/**
	 *
	 * @param array $schema
	 */
	public function __construct($schema)
	{
		$this->schema = new \Zend\Config\Config($schema);
	}

	/**
	 *
	 * @return array
	 */
	public function getDatabases()
	{
		$dbs = array();

		$keys = array_keys($this->schema->toArray());

		foreach ($keys as $key)
		{
			$dbs[] = $key;
		}

		return $dbs;
	}

	/**
	 *
	 * @param string $table
	 * @return array
	 */
	function getPrimaryKey($table, $db = 'dbsite', $withType = true)
	{

		if ( ! isset($this->schema->$db->$table->primary))
		{
			throw new \Exception(__METHOD__ . " : table $db.$table - primary does not exist");
		}

		$primary = $this->schema->$db->$table->primary;

		$arr = array();

		if (is_object($primary))
		{

			if ($withType)
			{
				$arr['type'] = 'composite';
				foreach ($primary->toArray() as $field)
				{
					$a = array();
					$a['name'] = $field;
					$a['type'] = $this->getColumnType($table, $field, $db);
					$arr['cols'][] = $a;
				}
			}
			else
			{
				foreach ($primary->toArray() as $field)
				{
					$arr[] = $field;
				}
			}
		}
		else
		{
			if ($withType)
			{
				$arr['type'] = $this->getColumnType($table, $primary, $db);
				$arr['name'] = $primary;
			}
			else
			{
				$arr[] = $primary;
			}
		}

		return $arr;
	}

	/**
	 *
	 * @param string $table
	 * @param string $field
	 * @return type
	 * @throws \Exception
	 */
	function getColumnType($table, $field, $db = 'dbsite')
	{
		if (!isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : table $db.$table does not exist in schema");
		}
		if (!isset($this->schema->$db->$table->columns->$field))
		{
			throw new \Exception(__METHOD__ . " : column $db.$table.$field does not exist in schema");
		}

		return $this->schema->$db->$table->columns->$field->type;
	}

	/**
	 *
	 * @param string $table
	 * @param string $field
	 * @param string $field
	 * @return boolean
	 * @throws \Exception
	 */
	function hasField($table, $field, $db = 'dbsite')
	{
		if (!isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : table $db.$table does not exist in schema");
		}

		return isset($this->schema->$db->$table->columns->$field);
	}

	/**
	 *
	 */
	function createTables($db = 'dbsite', $tablesToCreate = null)
	{
		$tables = $this->getTables($db);

		foreach ($tables as $table)
		{
			if ($tablesToCreate && !in_array($table, $tablesToCreate))
			{
				continue;
			}

			$query = new CQLQuery($table, null, $db);

			$fields = $this->getColumns($table, $db);
			$primaries = $this->getPrimaries($table, $db);

			$query->createTable($fields, $primaries, $db);

			$indexes = $this->getIndexes($table, $db);

			foreach ($indexes as $field)
			{
				$query = new CQLQuery($table, null, $db);
				$query->createIndex($field, $db);
			}
		}

		return;
	}

	/**
	 *
	 * @param string $table
	 * @return array
	 * @throws \Exception
	 */
	function getIndexes($table, $db = 'dbsite')
	{

		if (!isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : table $table does not exist in schema");
		}

		if (!isset($this->schema->$db->$table->indexes))
		{
			return array();
		}

		return $this->schema->$db->$table->indexes->toArray();
	}

	/**
	 *
	 * @param boolean $exception
	 * @throws \Exception
	 */
	function dropTables($db = 'dbsite', $tablesToDrop = array(), $exception = false)
	{
		$tables = $this->getTables($db);

		foreach ($tables as $table)
		{
			if ( $tablesToDrop && ! in_array($table, $tablesToDrop))
			{
				continue;
			}

			$query = new CQLQuery($table, null, $db);

			try
			{
				$query->dropTable($db);
			} catch (\Exception $e)
			{
				if ($exception)
				{
					throw $e;
				}
			}
		}

		return;
	}

	/**
	 *
	 * @param boolean $exception
	 * @throws \Exception
	 */
	function truncateTables($db = 'dbsite', $tablesToTruncate = array(), $exception = false)
	{
		$tables = $this->getTables($db);

		foreach ($tables as $table)
		{
			if ( $tablesToTruncate && ! in_array($table, $tablesToTruncate))
			{
				continue;
			}

			$query = new CQLQuery($table, null, $db);

			try
			{
				$query->truncateTable($db);
			}
			catch (\Exception $e)
			{
				if ($exception)
				{
					throw $e;
				}
			}
		}

		return;
	}

	/**
	 *
	 * @return array
	 */
	function getTables($db = 'dbsite')
	{
		$tables = array();

		foreach ($this->schema->$db as $a)
		{
			$tables[] = $a->tablename;
		}

		return $tables;
	}

	function getModelNames()
	{
		$list = array();

		$tables = $this->getTables('dbsite');

		foreach ($tables as $table)
		{
			$list[] = "dbsite.$table";
		}

		$tables = $this->getTables('dbstay');

		foreach ($tables as $table)
		{
			$list[] = "dbstay.$table";
		}

		return $list;
	}

	/**
	 *
	 * @param string $table
	 * @return array
	 * @throws \Exception
	 */
	function getColumns($table, $db = 'dbsite', $onlyName = false)
	{
		if (!isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : Table $db.$table does not exist in schema");
		}

		$cols = array();

		foreach ($this->schema->$db->$table->columns->toArray() as $colname => $meta)
		{
			if ($onlyName)
			{
				$cols[] = $colname;
			}
			else
			{
				$col = array();
				$col['name'] = $colname;
				$col['type'] = $meta["type"];
				$cols[] = $col;
			}
		}

		return $cols;
	}

	/**
	 *
	 * @param string $table
	 * @return array
	 * @throws \Exception
	 */
	function getPrimaries($table, $db = 'dbsite')
	{
		if (!isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : Table $table does not exist in schema");
		}

		if (!isset($this->schema->$db->$table->primary))
		{
			throw new \Exception(__METHOD__ . " : primary key for Table $table does not exist in schema");
		}

		return is_string($this->schema->$db->$table->primary) ? array($this->schema->$db->$table->primary) :
				$this->schema->$db->$table->primary->toArray();
	}

	/**
	 *
	 * @param type $table
	 * @param type $db
	 * @param type $option
	 * @return null
	 * @throws \Exception
	 */
	public function getOption($table, $db, $option)
	{
		if ( ! isset($this->schema->$db->$table))
		{
			throw new \Exception(__METHOD__ . " : Table $table does not exist in schema");
		}

		if ( ! isset($this->schema->$db->$table->options))
		{
			return NULL;
		}

		if ( ! isset($this->schema->$db->$table->options->$option))
		{
			return NULL;
		}

		$option = $this->schema->$db->$table->options->$option;

		if (strpos($option, 'config.') !== FALSE)
		{
			list(, $value) = explode('.', $option);

			return $value;
		}
		else
		{
			return $option;
		}
	}

}

