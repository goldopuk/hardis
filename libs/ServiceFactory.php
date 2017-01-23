<?php
namespace Stayfilm\stayzen\services;

/**
 * @package Stayfilm\services\utils
 *
 *
 */

class ServiceFactory {

	/**
	 * Builder for Service classes
	 *
	 * @param type String
	 * @return \class
	 * @throws Exception
	 */
	static public function build($type)
	{

		$class = '\Stayfilm\stayzen\services\\' . ucfirst($type) . "Service";

		try {
			$o = $class::getInstance();
		} catch (Exception $e) {
			throw new \Exception('Missing class : ' . $class);
		}

		return $o;
	}
}
