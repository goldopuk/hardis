<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\DenounceModel; // TODO
use \UserSession as UserSession;

class DenounceService extends TableService
{
	protected $denounceTypes = array('rule_policy', 'porn', 'no_media_permission', 'other');

	static protected $_instance = null;

	protected $table = 'dbsite.user';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\DenounceService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 * @return array
	 */
	public function getDenounceTypes()
	{
		return $this->denounceTypes;
	}

	/**
	 *
	 * @param type $reason
	 * @param type $denouncer
	 * @param type $movie
	 * @param type $description
	 * @return type
	 */
	public function create($reason, $denouncer, $movie, $description)
	{
		$denounce = new DenounceModel();
		$denounce->reason          = $reason;
		$denounce->iduser          = $denouncer->iduser;
		$denounce->idmovie         = $movie->idmovie;
		$denounce->description     = $description;
		$denounce->status          = DenounceModel::STATUS_INACTIVE;
		$denounce->iduserdenounced = $movie->iduser;

		$denounce = DataMapperManager::create($denounce);

		return $denounce;
	}

	public function get($iddenounce)
	{
		return DataMapperManager::findByKey('dbsite.denounce', $iddenounce);
	}

	/**
	 * Find a denounce for a given user
	 * @param Stayfilm\stayzen\ORM\UserModel $user
	 * @return array Stayfilm\stayzen\ORM\DenounceModel
	 * @throws \Exception
	 */
	public function findDenouncesByUser()
	{
		return DataMapperManager::findAll('dbsite.denounce');
	}

	public function hasDenounced($user, $movie)
	{
		$fields = array();
		$fields[] = 'iduser';
		$fields[] = 'idmovie';

		$values = array();
		$values[] = $user->iduser;
		$values[] = $movie->idmovie;

		$denounce = DataMapperManager::findBy('dbsite.denounce', $fields, $values);

		return $denounce;
	}

}
