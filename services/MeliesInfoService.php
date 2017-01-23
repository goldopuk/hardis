<?php
namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\MeliesInfoModel;

class MeliesInfoService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.meliesinfo';

	protected $ec2Client;

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MeliesInfoService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}
}