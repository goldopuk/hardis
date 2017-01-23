<?php

namespace Stayfilm\stayzen\zend\logger\Writer;

use Traversable;
use Stayfilm\stayzen as zen;
use Zend\Db\Adapter\Adapter;
use Zend\Log\Exception;
use Zend\Log\Formatter;
use Zend\Log\Formatter\Db as DbFormatter;
use Stayfilm\stayzen\ORM\CassaClient;
use Zend\Log\Writer\AbstractWriter;

class InMemoryWriter extends AbstractWriter
{

	protected $modelName;

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

		zen\Application::$currentLog[] = $message;
	}

}
