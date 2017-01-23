<?php
namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\PasswordResetModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen as zen;
use \Stayfilm\stayzen\exception as zexc;
use \Stayfilm\stayzen\services as serv;
use \Stayfilm\stayzen\Bcrypt;
use \Stayfilm\stayzen\ORM\CQLQuery;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints as c;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\Utilities;

class PasswordRecoverService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.passwordrecover';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\PasswordRecoverService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 * Get a valid reset password request.
	 * @param string $idtoken
	 * @return \Stayfilm\stayzen\ORM\PasswordRequestModel
	 */
	public function getRequest($idtoken)
	{
		if ( ! Utilities::isValidUUID4($idtoken))
		{
			throw new \Exception("Id Token $idtoken invalid");
		}

		$passModel = DataMapperManager::findBy('passwordrecover', 'idtoken', $idtoken);

		if ( ! $passModel)
		{
			throw new \Exception("model passwordrecover $idtoken does not exit");
		}

		$currentTime = time();

		if ($passModel->expire < $currentTime)
		{
			throw new zexc\PasswordRequestExpiredException("Token $idtoken has expired");
		}

		return $passModel;
	}

	/**
	 * Get a valid reset password request.
	 * @param string $idtoken
	 * @return \Stayfilm\stayzen\ORM\PasswordRequestModel
	 */
	public function getRequestByEmail($email)
	{
		$passModel = DataMapperManager::findBy('passwordrecover', 'email', "$email");
		return $passModel;
	}

	/**
	 * Returns true if can recover password, false otherway
	 * @param type $email
	 * @return type
	 */
	public function canRecoverPassword($email)
	{
		return (bool) DataMapperManager::findBy('passwordrecoverattempt', 'email', $email);
	}

	public function createPasswordRecoverAttempt($model)
	{
		return DataMapperManager::create($model);
	}
}
