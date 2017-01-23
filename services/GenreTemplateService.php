<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\GenreTemplateModel;
use \Stayfilm\stayzen\Application;

class GenreTemplateService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.genretemplate';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\GenreTemplateService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function get($idgenre, $idtemplate)
	{
		$key = array();
		$key[] = $idgenre;
		$key[] = $idtemplate;

		return DataMapperManager::findByKey('dbsite.genretemplate', $key);
	}
}