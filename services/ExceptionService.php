<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\ThemeModel; // TODO
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen as zen;

class ExceptionService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbsite.exception';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\ExceptionService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	function create($exception, $user = null, $log = array())
	{

		$model = new orm\ExceptionModel();
		$model->code          = $exception->getCode();
		$model->filename      = $exception->getFile();
		$model->line          = $exception->getLine();
		$model->exceptiontype = get_class($exception);
		$model->exceptionlog  = $log;
		$model->message       = $exception->getMessage();

		if ($user)
		{
			$model->iduser = $user->iduser;
		}
		
		$model->stacktrace    = $exception->getTraceAsString();

		$data = array();
		$data['browser']  = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'undefined';
		$model->data = $data;

		return parent::create($model);
	}

}
