<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\InviteModel;
use Stayfilm\stayzen as zen;
use \Stayfilm\stayzen\Application;

class InviteService extends TableService
{

	const PENDING_FOR_APPROVAL   = 0;
	const ACCEPTED  = 1;
	const SENT  = 2;

	static protected $_instance = null;

	protected $table = 'dbsite.invite';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\InviteService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param string $email
	 * @return boolean
	 */
	public function isNew($email)
	{
		$invite = DataMapperManager::findBy('invite', 'emailto', $email);

		if ($invite)
		{
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param type $email
	 * @return type
	 */
	public function emailExists($email)
	{
		return (boolean) DataMapperManager::findBy('invite', 'emailto', $email);
	}

	/**
	 *
	 * @param type $email
	 * @return type
	 */
	public function create($email, $accepted = false, $premium = false)
	{
		$invModel = new InviteModel();
		$invModel->emailto   = $email;
		$invModel->activated = $accepted ? self::ACCEPTED : self::PENDING_FOR_APPROVAL;
		$invModel->ispremium = $premium;

		return DataMapperManager::create($invModel);
	}

	public function getInvites($isPremium = '0', $fieldOrder = '', $order = '')
	{
		$client = Application::getSolrClient('invite');

		$query = $client->createSelect();

		$str = "ispremium:" . $isPremium;

		$query->setQuery($str);
		$query->setStart(0)->setRows(99999);

		if ( $fieldOrder )
		{
			$query->addSort($fieldOrder, ($order == 'desc' ? $query::SORT_DESC : $query::SORT_ASC));
		}
		else
		{
			$query->addSort('emailto', $query::SORT_ASC);
		}

		list($result) = $client->execute($query, TRUE);

		return $result;
	}

	public function activeInvite($invite)
	{
		if ($invite->activated > self::ACCEPTED)
		{
			throw new \Exception("Invite already used");
		}

		$invite->activated = self::ACCEPTED;

		DataMapperManager::update($invite);

		return $invite;
	}

	/**
	 *
	 * @param $idinvite
	 * @return boolean
	 */
	public function consumeInvite($invite, $receiver)
	{

		$fields = array();
		$fields[0] = 'idinvite';
		$fields[1] = 'activated';

		$values = array();
		$values[0] = $invite->idinvite;
		$values[1] = 0;

		$invite->iduserreceiver = $receiver->iduser;
		$invite->activated      = time();

		DataMapperManager::update($invite);
	}

	/**
	 *
	 * @param $idinvite
	 * @return boolean
	 */
	public function isValidInvite($invite)
	{
		return $invite->activated === self::ACCEPTED || $invite->activated === self::SENT
				|| $invite->activated === self::PENDING_FOR_APPROVAL; //pending is disabled.
	}

	public function send($email)
	{
		$invite = $email->invite;

		if ( ! $invite)
		{
			throw new \Exception("Missing invite");
		}

		if ($invite->activated <= self::SENT)
		{
			$emailM = zen\EmailManager::getInstance();
			$emailM->send($email);
		}
		else
		{
			throw new \Exception("Invite not accepted or already used");
		}

		$invite->activated = self::SENT;

		DataMapperManager::update($invite);

		return $invite;
	}
}