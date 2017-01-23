<?php

namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;

class EmailService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.email';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\GenreService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param type $idemail
	 * @return type
	 */
	public function get($idemail)
	{
		$email = DataMapperManager::findByKey('dbsite.email', $idemail);

		return $email;
	}

	public function delete($email)
	{
		DataMapperManager::delete($email);
	}

	public function create($email)
	{
		return DataMapperManager::create($email);
	}
}