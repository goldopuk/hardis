<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\PartyInviteModel;
use Stayfilm\stayzen as zen;
use \Stayfilm\stayzen\Application;

class PartyService extends TableService
{
	const CONFIRMED  = 1;
	const EMAILSENT  = 2;

	static protected $_instance = null;

	protected $table = 'dbsite.partyinvite';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\PartyService
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
		$invite = DataMapperManager::findBy('partyinvite', 'email', $email);

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
		return (boolean) DataMapperManager::findBy('partyinvite', 'email', $email);
	}

	/**
	 *
	 * @param type $email
	 * @return type
	 */
	public function create($user, $idpartyinvite, $firstname, $lastname, $company, $occupation, $phone, $email, $confirmed,
			$cantakeguest, $phoneconfirmed, $confirmationcode, $phonetrycount, $guestfirstname, $guestlastname, $guestemail)
	{
		$partyModel = new PartyInviteModel();
		$partyModel->iduser = $user->iduser;
		$partyModel->idpartyinvite = $idpartyinvite;
		$partyModel->firstname = $firstname;
		$partyModel->lastname = $lastname;
		$partyModel->company = $company;
		$partyModel->occupation = $occupation;
		$partyModel->phone = $phone;
		$partyModel->email = $email;
		$partyModel->confirmed = ($phoneconfirmed ? true : $confirmed);
		$partyModel->phoneconfirmed = $phoneconfirmed;
		$partyModel->cantakeguest = $cantakeguest;
		$partyModel->confirmationcode = $confirmationcode;
		$partyModel->phonetrycount = $phonetrycount;
		$partyModel->guestfirstname = $guestfirstname;
		$partyModel->guestlastname = $guestlastname;
		$partyModel->guestemail = $guestemail;

		return DataMapperManager::create($partyModel);
	}

	public function getInvites($fieldOrder = '', $order = '')
	{
		$client = Application::getSolrClient();

		$query = $client->createSelect();

		$query->setStart(0)->setRows(99999);

		if ( $fieldOrder )
		{
			$query->addSort($fieldOrder, ($order == 'desc' ? $query::SORT_DESC : $query::SORT_ASC));
		}
		else
		{
			$query->addSort('firstname', $query::SORT_ASC);
		}

		list($result) = $client->execute($query, 'dbsite.partyinvite', true);

		return $result;
		//return DataMapperManager::findAllBy($modelName, $field, $value, $selectFields, $limit);
	}

	public function getPartyInviteByEmail($email)
	{
		$invite =  DataMapperManager::findBy('partyinvite', 'email', $email);

		return $invite;
	}

	public function confirmInvite($idpartyinvite = null, $guestfirstname = null, $guestlastname = null, $guestemail = null)
	{
		if ( ! $idpartyinvite)
		{
			throw new \Exception('Party invite code missing.');
		}

		$partyServ = zen\services\PartyService::getInstance();
		$invite = $partyServ->get($idpartyinvite);
		$invite->confirmed = self::CONFIRMED;
		$invite->guestfirstname = $guestfirstname;
		$invite->guestlastname = $guestlastname;
		$invite->guestemail = $guestemail;
		$invite->activated = time();

		DataMapperManager::update($invite);

		return $invite;
	}


	/**
	 *
	 * @param $idpartyinvite
	 * @return string
	 */
	public function removePartyInvite($idpartyinvite = null)
	{
		if ( ! $idpartyinvite)
		{
			throw new \Exception('Party invite code missing.');
		}

		$partyServ = zen\services\PartyService::getInstance();
		$invite = $partyServ->get($idpartyinvite);

		DataMapperManager::delete($invite);

		return true;
	}

	public function send($email)
	{
		$invite = $email->partyinvite;

		if ( ! $invite)
		{
			throw new \Exception("Missing invite");
		}

		$emailM = zen\EmailManager::getInstance();
		$emailM->send($email);

		$invite->confirmed = self::EMAILSENT;

		DataMapperManager::update($invite);

		return $invite;
	}
}