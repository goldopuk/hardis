<?php

namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen as zen;

/**
 * Class CassaClient
 * @package Stayfilm\stayzen\ORM
 */
class CassaClient
{
	protected $conn;

	/**
	 * name of the keyspace
	 *
	 * @var string
	 */
	protected $keyspace;

	function __construct(array $conf)
	{
		$this->keyspace = $conf['keyspace'];
		$this->conn = $this->createConnection($conf);
	}

	function createConnection(array $conf)
	{
		$nodes = array();

		foreach ($conf['hosts'] as $host)
		{
			$nodes["{$host}:9042"] = [
				'username'  => $conf['username'],
				'password'  => $conf['password']
			];
		}

		$conn = new \evseevnn\Cassandra\Database($nodes, $conf['keyspace'], array(), $conf['use_random_nodes']);
		$conn->connect();

		return $conn;
	}

	public function query($cql)
	{
		if (zen\Application::$config->profiler)
		{
			$profiler = zen\Profiler::getInstance();
			$profiler->mark('start');
		}

		$res = $this->conn->query($cql, [], \evseevnn\Cassandra\Enum\ConsistencyEnum::CONSISTENCY_LOCAL_ONE);

		if (zen\Application::$config->profiler)
		{
			$profiler->mark('end');
			$elapsed = $profiler->elapsedTime('start', 'end');
			$profiler->addQuery($cql, $elapsed);
		}

		if (zen\Application::$config->log_sql)
		{
			debug($cql . (isset($elapsed) ? " - $elapsed s" : ''));
		}

		return $res;
	}

	function __destruct()
	{
		$this->conn->disconnect();
	}

}
