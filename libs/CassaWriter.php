<?php

namespace Stayfilm\stayzen\zend\logger\writer;

use Traversable;
use Zend\Db\Adapter\Adapter;
use Zend\Log\Exception;
use Zend\Log\Formatter;
use Zend\Log\Formatter\Db as DbFormatter;
use Stayfilm\stayzen\ORM\CassaClient;
use Zend\Log\Writer\AbstractWriter;

class CassaWriter extends AbstractWriter
{

	protected $modelName;

	/**
	 * Constructor
	 *
	 * We used the Adapter instead of Zend\Db for a performance reason.
	 *
	 * @param Adapter|array|Traversable $db
	 * @param string $tableName
	 * @param array $columnMap
	 * @param string $separator
	 * @throws Exception\InvalidArgumentException
	 */
	public function __construct($db, $modelName)
	{
		$modelName = (string) $modelName;

		if ('' === $modelName) {
			throw new Exception\InvalidArgumentException('You must specify a table name');
		}

		$this->db = $db;
		$this->modelName = $modelName;
	}

	/**
	 * Write a message to the log.
	 *
	 * @param array $event event data
	 * @return void
	 * @throws Exception\RuntimeException
	 */
	protected function doWrite(array $event)
	{
		$message = $this->formatter->format($event);

		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}

		$message = str_replace("\n", " ", $message);
		$message = str_replace("\r", " ", $message);

		$message = preg_replace('/[\s\n\r]+/', ' ', $message);

		$message = str_replace("'", "''", $message);

		if ( ! class_exists('UserSession'))
		{
			return;
		}

		$iduser = \UserSession::getIdUser();

		if ( ! $iduser)
		{
			return;
		}

		$microTs = getMicrotimestamp();

		$ksName = zen\Utilities::getRealKeyspaceName('dbsite');

		$cql = "INSERT INTO {$ksName}.log (iduser, message, microcreated, priority) VALUES($iduser, '$message', $microTs, {$event['priority']})";

		file_put_contents(STAYZEN_ROOT . '/log/cassawriter', $cql . PHP_EOL, FILE_APPEND);

		$this->db->execute_cql_query_orig($cql);
	}

}
