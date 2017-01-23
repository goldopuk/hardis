<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\ORM\DataMapperManager;

class SiteRouteService extends TableService
{
	protected static $_instance = null;

	protected $table = 'dbsite.siteroute';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\SiteRouteService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function getAllRoutes()
	{
		$fields = array();
		$fields[] = 'key';
		$fields[] = 'value';

		$routes = DataMapperManager::findAll('dbsite.siteroute', $fields, 100);

		return $routes;
	}
}