<?php

namespace Stayfilm\stayzen\ORM;
use Stayfilm\stayzen\Application;

/**
 * Build cql requests and execute them with a CassaClient
 *
 * @author julien
 */
class CQLQuery
{

	/**
	 *
	 * @var \Stayfilm\stayzen\CassaClient
	 */
	public static $connections = array();

	/**
	 * the default conneciton
	 *
	 * @var type
	 */
	public static $conn;

	/**
	 *
	 * @var string
	 */
	public $table;

	/**
	 *
	 * @var array
	 */
	public $where = array();

	/**
	 *
	 * @var fields to select (for SELECT only)
	 */
	protected $fields = array();

	/**
	 *
	 * @var int
	 */
	protected $offset;

	/**
	 *
	 * @var int
	 */
	protected $limit;

	/**
	 *
	 * @var string
	 */
	protected $order;

	/**
	 *
	 * @var string
	 */
	protected $direction;

	/**
	 *
	 * @var int
	 */
	protected $ttl;

	/**
	 *
	 * @param string $table
	 * @param array $fields
	 * @param string $db connection string name
	 */
	public function __construct($table, $fields = null, $keyspace = 'dbsite')
	{
		$this->table = $table;
		$this->keyspace = $keyspace;
		$this->fields = $fields ? $fields : array('*');

		self::setCurConnection('cassandra');
	}

	/**
	 * @param string $key An key for the connection
	 * @param \Stayfilm\stayzen\CassaClient $db
	 */
	public static function addConnection($key, $db)
	{
		self::$connections[$key] = $db;
	}

	/**
	 *
	 * @param array $data
	 * @return \cassandra\CqlResult
	 */
	function save($data)
	{
		if ( ! $data)
		{
			return;
		}

		if (count($this->where) > 0)
		{
			$cql = $this->buildUpdate($data);
		}
		else
		{
			$cql = $this->buildInsert($data);
		}

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @param string $str
	 * @return string
	 */
	function quote($str)
	{
		return str_replace("'", "''", $str);
	}

	/**
	 *
	 * @param string $key
	 */
	static function setCurConnection($key)
	{
		self::$conn = self::$connections[$key];
	}

	/**
	 *
	 * @return \Stayfilm\stayzen\CassaClient
	 */
	static function getCurConnection()
	{
		return self::$conn;
	}

	/**
	 *
	 * @param string $key
	 * @return \Stayfilm\stayzen\CassaClient
	 */
	static function getConnection()
	{
		return self::$connections['cassandra'];
	}

	/**
	 *
	 * @return array
	 */
	static function getConnections()
	{
		return self::$connections;
	}

	/**
	 * @param $val
	 * @param $type
	 * @return string
	 * @throws \Exception
	 */
	function formatValue($val, $type)
	{
		if (Application::$config->cql_new_version)
		{
			switch ($type)
			{
				case 'boolean':
					$newval = ($val ? 'true' : 'false');
					break;
				case 'int':
					$newval = $val === NULL ? 'NULL' : $val;
					break;
				case 'bigint':
				case 'timestamp':
				case 'func':
				case 'uuid':
					$newval = $val === NULL ? 'NULL' : $val;
					break;
				case 'string':
				case 'json':
				case 'text':
					$newval = "'" . $this->quote($val) . "'";
					break;
				default:
					throw new \Exception("Type $type invalid");
			}
		}
		else
		{
			switch ($type)
			{
				case 'boolean':
					$newval = "'" . ($val ? 'true' : 'false') . "'";
					break;
				case 'func':
				case 'uuid':
					$newval = $this->quote($val);
					break;
				default:
					$newval = "'" . $this->quote($val) . "'";
			}
		}

		return $newval;
	}

	/**
	 * @param $field
	 * @param $value
	 * @param $type
	 * @param string $operator
	 * @return $this
	 */
	public function where($field, $value, $type, $operator = '=')
	{
		$arr = array();
		$arr['field'] = $field;
		$arr['value'] = $value;
		$arr['type'] = $type;
		$arr['operator'] = $operator;

		$this->where[] = $arr;
		return $this;
	}

	function buildWhere()
	{
		$cql = array();

		foreach ($this->where as $arr)
		{
			$field    = $arr['field'];
			$operator = $arr['operator'];
			$value    = $arr['value'];
			$type     = $arr['type'];

			if ($operator === 'IN' &&  ! is_array($value))
			{
				throw new Exception('value should be an array when operator is IN');
			}

			if (is_array($value))
			{
				$str = "(";

				foreach ($value as $v)
				{
					$str .= $this->formatValue($v, $type) . ', ';
				}

				$str = substr($str, 0, -2) . ")";

				$value = $str;
			}
			else
			{
				$value = $this->formatValue($value, $type);
			}

			$cql[] = "$field $operator $value";
		}

		return implode(' AND ', $cql);
	}

	/**
	 *
	 * @param array $data
	 * @return string
	 */
	function buildInsert($data)
	{
		$ttl = NULL;

		$fields = array();
		$values = array();

		if (Application::$config->cql_new_version)
		{
			$template = "INSERT INTO %s (%s) VALUES (%s) %s";
		}
		else
		{
			$template = "INSERT INTO %s (%s) VALUES (%s) USING CONSISTENCY ONE %s";
		}

		foreach ($data as $field)
		{
			$fields[] = $field['name'];
			$values[] = $this->formatValue($field['value'], $field['type']);
		}

		$fields = join(", ", $fields);
		$values = join(", ", $values);

		if ( ! empty($this->ttl))
		{
			if (Application::$config->cql_new_version)
			{
				$ttl = "using ttl {$this->ttl}";
			}
			else
			{
				$ttl = "and ttl {$this->ttl}";
			}
		}

		return trim(sprintf($template, $this->getFullTableName(), $fields, $values, $ttl));
	}

	function getFullTableName()
	{
		$keyspaceName = Application::$config->database->{$this->keyspace}->keyspace;
		return $keyspaceName . "." . $this->table;
	}

	/**
	 *
	 * @param array $fields
	 * @param array $primaries
	 * @return string
	 */
	function buildCreateTable($fields, $primaries)
	{
		$template = "CREATE TABLE %s (%s, PRIMARY KEY (%s))";

		$strs = array();

		foreach ($fields as $field)
		{
			$name = $field['name'];
			$type = strtoupper($field['type']);

			if ($type === 'TIMESTAMP') {
				$type = 'BIGINT';
			}

			if ($type === 'BOOLEAN') {
				$type = 'INT';
			}

			if ($type === 'SERIALIZED') {
				$type = 'TEXT';
			}

			if ($type === 'JSON') {
				$type = 'TEXT';
			}

			$strs[] = "$name $type";
		}

		$cql = sprintf($template, $this->getFullTableName(), implode(", ", $strs), implode(',', $primaries));

		return $cql;
	}

	/**
	 *
	 * @return string
	 */
	function buildDelete()
	{
		$template = "DELETE FROM %s %s";

		$where = "WHERE " . $this->buildWhere();

		$cql = sprintf($template, $this->getFullTableName(), $where);

		return $cql;
	}

	/**
	 *
	 * @param string $field
	 * @return cassandra\CQLResult
	 */
	function createIndex($field)
	{
		$cql = $this->buildCreateIndex($field);

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @param array $fields
	 * @param array $primaries
	 * @return \cassandra\CqlResult
	 */
	function createTable($fields, $primaries)
	{
		$cql = $this->buildCreateTable($fields, $primaries);

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @return \cassandra\CqlResult
	 */
	function delete()
	{
		$cql = $this->buildDelete();

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @return string
	 */
	function buildDropTable()
	{
		$template = "DROP TABLE %s";

		$cql = sprintf($template, $this->getFullTableName());

		return $cql;
	}

	/**
	 *
	 * @return string
	 */
	function buildTruncateTable()
	{
		$template = "TRUNCATE %s";

		$cql = sprintf($template, $this->getFullTableName());

		return $cql;
	}

	/**
	 *
	 * @return \cassandra\CqlResult
	 */
	function dropTable()
	{
		$cql = $this->buildDropTable();

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @return \cassandra\CqlResult
	 */
	function truncateTable()
	{
		$cql = $this->buildTruncateTable();

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @param array $data
	 * @return string
	 */
	function buildUpdate($data)
	{
		$parts = array();
		$limit = "";


		$template = "UPDATE %s SET %s %s %s";

		foreach ($data as $field)
		{
			if ($field['name'] === 'filelastaccess') // hack table midia
			{
				continue;
			}

			$parts[] = $field["name"] . " = " . $this->formatValue($field["value"], $field["type"]);
		}

		$parts = join(", ", $parts);

		$where = "WHERE " . $this->buildWhere();

		$ttl = "";
		if ( ! empty($this->ttl))
		{
			if (Application::$config->cql_new_version)
			{
				$ttl = " using ttl {$this->ttl}";
			}
			else
			{
				$ttl = " and ttl {$this->ttl}";
			}
		}

		return sprintf($template, $this->getFullTableName() . $ttl, $parts, $where, $limit);
	}

	/**
	 *
	 * @return string
	 */
	function buildSelect()
	{
		$where = $order = $limit = null;

		$template = "SELECT %s FROM %s %s %s %s ALLOW FILTERING";

		$fields = join(", ", $this->fields);

		if ( ! empty($this->where))
		{
			$where = "WHERE " . $this->buildWhere();
		}

		if ( ! empty($this->order))
		{
			$order = "ORDER BY {$this->order} {$this->direction}";
		}

		if ( ! empty($this->limit))
		{
			// no offset in cassandra
			if ($this->offset)
			{
				$limit = "LIMIT {$this->limit}, {$this->offset}";
			}
			else
			{
				$limit = "LIMIT {$this->limit}";
			}
		}

		return sprintf($template, $fields, $this->getFullTableName(), $where, $order, $limit);
	}

	/**
	 *
	 * @return array
	 */
	function select()
	{
		$cql = $this->buildSelect();

		return self::$conn->query($cql);
	}

	/**
	 *
	 * @param type $order
	 */
	public function order($order)
	{
		$this->order = $order;
	}

	public function limit($limit)
	{
		$this->limit = $limit;
	}

	public function offset($offset)
	{
		$this->offset = $offset;
	}

	/**
	 *
	 * @param type $direction
	 * @throws Exception
	 */
	public function direction($direction)
	{
		$this->direction = $direction;
	}

	/**
	 *
	 * @param type $ttl
	 */
	public function ttl($ttl)
	{
		$this->ttl = $ttl;
	}

}
