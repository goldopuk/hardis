<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\services as serv;

class VoucherService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.voucher';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\VoucherService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}


	/**
	 *
	 * @param type $idvoucher
	 * @param type $user
	 * @return boolean
	 * @throws \Exception
	 */
	public function isValidVoucher($idvoucher, $user, $campaign)
	{
		if ( ! $idvoucher)
		{
			throw new \Exception('Missing idvoucher parameter.');
		}

		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		if ( ! isValidUUID($idvoucher))
		{
			return FALSE;
		}

		$voucher = DataMapperManager::findBy('dbsite.voucher', array('idvoucher'), array($idvoucher));

		if ( ! $voucher)
		{
			return FALSE;
		}

		if ($voucher->iduser !== NULL && $voucher->iduser !== $user->iduser)
		{
			return FALSE;
		}

		if ($voucher->idcampaign !== $campaign->idcampaign)
		{
			return FALSE;
		}

		return TRUE;
	}

	/**
	 *
	 * @param type $voucher
	 * @param type $user
	 * @param type $campaign
	 * @throws \Exception
	 */
	public function setUser($voucher, $user, $campaign)
	{
		if ( ! $voucher)
		{
			throw new \Exception('Missing voucher parameter.');
		}
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}
		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		$voucher->iduser = $user->iduser;
		DataMapperManager::update($voucher);

		$user2CampaignModel = new orm\User2CampaignModel();
		$user2CampaignModel->idcampaign = $voucher->idcampaign;
		$user2CampaignModel->idcustomer = $campaign->idcustomer;
		$user2CampaignModel->iduser     = $user->iduser;
		DataMapperManager::create($user2CampaignModel);

		$userServ = serv\UserService::getInstance();
		$userServ->addConfigItem($user, "{$campaign->slug}_voucher", $voucher->idvoucher);
	}

	public function create($campaign)
	{
		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		$voucher = new orm\VoucherModel();

		$voucher->idvoucher  = (string) \phpcassa\UUID::uuid4();
		$voucher->idcampaign = $campaign->idcampaign;

		orm\DataMapperManager::create($voucher);

		return $voucher;
	}

	/**
	* Check if the campaign associated with the voucher is active.
	*/
	public function isCampaignActive($idcustomer = NULL, $idcampaign = NULL)
	{
		if ( ! $idcampaign || ! $idcustomer)
		{
			return false;
		}

		$campaign = DataMapperManager::findBy('dbsite.campaign', array('idcustomer, idcampaign'), array($idcustomer, $idcampaign));

		if ($campaign->isactive)
		{
			return true;
		}

		return false;
	}
}