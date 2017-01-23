<?php
use Stayfilm\stayzen\ORM\CQLQuery;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\ORM\CassaClient;
use phpcassa\Connection\ConnectionPool;
use phpcassa\Schema\DataType\IntegerType;
use Stayfilm\stayzen as zen;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Migration
 *
 * @author felipe.bezerra
 */
class Migration
{
	static $connections = array();

	static function init()
	{
		$dbsiteHost = Application::$config->database->dbsite->host;
		$dbstayHost = Application::$config->database->dbstay->host;

		$username = 'dba';
		$password = self::getPassword();

		if (STAYZEN_ENV !== 'prod')
		{
			$username = 'cassandra';
			$password = 'cassandra';
		}

		// connection dbsite init
		$dbsiteConf = [
			'username' => $username,
			'password' => $password,
			'hosts'    => is_string($dbsiteHost) ? array($dbsiteHost) : $dbsiteHost->toArray(),
			'keyspace' => Application::$config->database->dbsite->keyspace,
			'use_random_nodes' => FALSE
		];

		Migration::$connections['dbsite'] = CassaClient::createConnection($dbsiteConf);

		$dbstayConf = [
			'username' => $username,
			'password' => $password,
			'hosts'    => is_string($dbstayHost) ? array($dbstayHost) : $dbstayHost->toArray(),
			'keyspace' => Application::$config->database->dbstay->keyspace,
			'use_random_nodes' => FALSE
		];

		Migration::$connections['dbstay'] = CassaClient::createConnection($dbstayConf);
	}

	/**
	 *
	 * @throws Exception
	 */
	function __construct()
	{

		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->curl = "curl.exe";
		}
		else
		{
			$cmd = 'which curl';
			$curlPath = exec($cmd);

			if ( ! $curlPath)
			{
				throw new Exception('Curl is not installed');
			}

			$this->curl = $curlPath;
		}
	}

	/**
	 *
	 * @param array $arr
	 */
	function executeCql($arr)
	{
		foreach ($arr as $keyspace => $queries)
		{
			foreach ($queries as $query)
			{
				echo "$query\n";
				self::$connections[$keyspace]->query($query);
			}
		}
	}

	/**
	 *
	 * @return string
	 * @throws Exception
	 */
	static function getPassword()
	{
		$passwordFile = MIGRATION_DIR . DIRECTORY_SEPARATOR . 'CASSANDRA_PASSWORD';

		if ( ! file_exists($passwordFile))
		{
			throw new Exception('CASSANDRA_PASSWORD file does not exist');
		}

		$password = file_get_contents($passwordFile);

		if ( ! $password)
		{
			throw new Exception('Missing password');
		}

		return $password;
	}

	/**
	 *
	 * @return string
	 */
	function getSolrUrl()
	{
		$password =$this->getPassword();
		return 'http://' . Application::$config->database->admin . ":" . urlencode($password) . "@"
				. Application::$config->solr->host . ":" . Application::$config->solr->port;
	}

	/**
	 *
	 * @param string $keyspace
	 * @param string $table
	 * @param string $script
	 * @return exec
	 */
	function executeSolrSchemaCmd($keyspace, $table, $script)
	{
		$url = $this->getSolrUrl();
		$keyspaceRealName  = zen\Application::$config->database->$keyspace->keyspace;
		$cmd = "{$this->curl} $url/solr/resource/$keyspaceRealName.$table/schema.xml --data-binary @" . MIGRATION_DIR .
				DIRECTORY_SEPARATOR . "solr" . DIRECTORY_SEPARATOR . $table . DIRECTORY_SEPARATOR . $script ." -H "  . '"Content-type:text/xml; charset=utf-8"';
		info($cmd);
		return exec($cmd);
	}

	/**
	 *
	 * @param string $keyspace
	 * @param string $table
	 * @param string $action DEFAULT: "RELOAD"
	 * @return exec
	 */
	function executeSolrActionCmd($keyspace, $table, $action = "RELOAD", $reindex = false)
	{
		$url = $this->getSolrUrl();
		$keyspaceRealName  = zen\Application::$config->database->$keyspace->keyspace;

		$reindexCmd = '';
		if ( $reindex )
		{
			$reindexCmd = "&reindex=true&deleteAll=true";
		}

		$cmd =	"{$this->curl} " .  '"' . $url . "/solr/admin/cores?action=" . $action . "&name=" . $keyspaceRealName . "." . $table . $reindexCmd . '"' ;
		info($cmd);
		return exec($cmd);
	}

	/**
	 *
	 * @param string $keyspace
	 * @param string $table
	 * @param string $script
	 * @return exec
	 */
	function executeSolrConfigCmd($keyspace, $table, $script)
	{
		$url = $this->getSolrUrl();
		$keyspaceRealName  = zen\Application::$config->database->$keyspace->keyspace;
		$cmd = "{$this->curl} $url/solr/resource/$keyspaceRealName.$table/solrconfig.xml --data-binary @" . MIGRATION_DIR .
				"/solr/$table/$script -H " . '"Content-type:text/xml; charset=utf-8"';
		info($cmd);
		return exec($cmd);
	}

	static function updateVersion($version)
	{
		$ksName = zen\Utilities::getRealKeyspaceName('dbsite');

		$cql = "UPDATE {$ksName}.version SET version=$version WHERE idversion=1";
		self::$connections['cassandra']->query($cql);
	}

	static function getCurDbVersion()
	{
		$ksName = zen\Utilities::getRealKeyspaceName('dbsite');

		$cql = "SELECT  {$ksName}.version FROM version WHERE idversion=1";

		$conn = self::$connections['cassandra'];

		$rows = $conn->query($cql);
		//print_r($res);
		return $rows[0]['version'];
	}
}
