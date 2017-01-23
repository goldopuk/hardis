<?php
namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\Application;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use Guzzle\Http\Client;
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services as serv;
use Guzzle\Http\Exception\RequestException;

class CampaignService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.campaign';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\CampaignService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	* Check if the campaign associated with the voucher is active.
	*/
	public function isActive($idcustomer = NULL, $idcampaign = NULL)
	{
		$campaign = $this->get($idcustomer, $idcampaign);

		if ( ! $campaign)
		{
			throw new \Exception("campaign (idcustomer: $idcustomer, idcampaign: $idcampaign) does not exist");
		}

		if ($campaign->isactive)
		{
			return true;
		}

		return false;
	}

	public function activateCampaign($campaign)
	{
		$campaign->isactive = 1;
		$this->update($campaign);
	}

	public function deactivateCampaign($campaign)
	{
		$campaign->isactive = 0;
		$this->update($campaign);
	}

	public function get($idcustomer, $idcampaign)
	{
		if ( ! $idcampaign)
		{
			throw new \Exception('idcampaign missing');
		}

		if ( ! $idcustomer)
		{
			throw new \Exception('idcustomer missing');
		}

		return DataMapperManager::findBy('dbsite.campaign', array('idcustomer', 'idcampaign'), array($idcustomer, $idcampaign));
	}

	public function getActiveCampaigns()
	{
		$campaigns = DataMapperManager::findAll('dbsite.campaign');
		$filteredCampaigns = array();

		foreach ($campaigns as $camp)
		{
			if ( ! $camp->isactive)
			{
				continue;
			}

			$slugs = Application::$config->excluded_campaigns;

			$slugs = explode(',', $slugs);

			if (is_array($slugs) && in_array($camp->slug, $slugs)) {
				continue;
			}

			$filteredCampaigns[] = $camp;
		}

		return $filteredCampaigns;
	}

	public function getSlugCampaign($slug)
	{
		if ( ! $slug)
		{
			return NULL;
		}

		$key = array();
		$key[] = $slug;

		return DataMapperManager::findByKey('dbsite.slugcampaign', $key);
	}

	public function getCampaignBySlug($slug)
	{
		$slugCampaign = $this->getSlugCampaign($slug);

		if ( ! $slugCampaign)
		{
			return NULL;
		}

		return $this->get($slugCampaign->idcustomer, $slugCampaign->idcampaign);
	}

	public function getCampaignById($idcampaign)
	{
		if ( ! $idcampaign)
		{
			return $this->getCampaignBySlug('stayfilm');
		}
		else
		{
			$key = array();
			$key[] = $idcampaign;

			$campaignSlug = DataMapperManager::findByKey('dbsite.campaignslug', $key);

			return $this->get($campaignSlug->idcustomer, $campaignSlug->idcampaign);
		}
	}

	/**
	 *
	 * @param type $movie
	 * @return int
	 * @throws \Exception
	 */
	public function sendMovieToCuration($movie, $returnError = FALSE)
	{
		$wsAddress = Application::$config->ws_curation_address;

		if ( ! $wsAddress)
		{
			info('ws_curation_address for movie curation not configured in config.php');
			return;
		}

		$client = new Client($wsAddress, array('timeout' => 2, 'connect_timeout' => 2));

		$request = $client->post();

		$userServ = serv\UserService::getInstance();

		$user = $userServ->get($movie->iduser);

		if ( ! $user)
		{
			throw new \Exception("User {$movie->iduser} does not exist");
		}

		$params = array();
		$params['idmovie']       = $movie->idmovie;
		$params['idusercreated'] = $movie->iduser;
		$params['idcustomer']    = $movie->idcustomer;
		$params['title']         = $movie->title;
		$params['userfullname']  = $user->getPrettyName();
		$params['userphoto']     = $user->photo ? $user->photo : 'https://sfresources.blob.core.windows.net/site/avatar_38x35.jpg';
		$params['videourl']      = $movie->videourl;
		$params['action_type']   = orm\MovieModel::ACTION_CURATION;

		$request->addPostFields($params);

		try
		{
			$request->send();
			return true;
		}
		catch(RequestException $ex)
		{
			$response = json_decode($ex->getResponse()->getBody(), TRUE);

			$message = isset($response['message']) ? $response['message'] : 'Error on calling staysmart add webservice: no details about it.';

			$send2ApprovalFailed = new orm\Send2ApproveFailedModel();
			$send2ApprovalFailed->idcampaign = $movie->idcampaign;
			$send2ApprovalFailed->idmovie    = $movie->idmovie;
			$send2ApprovalFailed->message    = $message;
			DataMapperManager::create($send2ApprovalFailed);

			if ($returnError)
			{
				return $message;
			}
			else
			{
				return false;
			}
		}
		catch(\Exception $ex)
		{
			$response = json_decode($ex->getResponse()->getBody(), TRUE);

			$message = isset($response['message']) ? $response['message'] : 'Error 500 when calling staysmart add webservice.';

			$send2ApprovalFailed = new orm\Send2ApproveFailedModel();
			$send2ApprovalFailed->idcampaign = $movie->idcampaign;
			$send2ApprovalFailed->idmovie    = $movie->idmovie;
			$send2ApprovalFailed->message    = $ex->getMessage();
			DataMapperManager::create($send2ApprovalFailed);

			if ($returnError)
			{
				return $message;
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 *
	 * @param type $movie
	 * @return int
	 * @throws \Exception
	 */
	public function sendMovieToMonitor($movie, $returnError = FALSE)
	{
		$wsAddress = Application::$config->ws_curation_address;

		if ( ! $wsAddress)
		{
			info('ws_curation_address for movie monitoring not configured in config.php');
			return;
		}

		$client = new Client($wsAddress, array('timeout' => 2, 'connect_timeout' => 2));

		$request = $client->post();

		$userServ = serv\UserService::getInstance();

		$user = $userServ->get($movie->iduser);

		if ( ! $user)
		{
			throw new \Exception("User {$movie->iduser} does not exist");
		}

		$params = array();
		$params['idmovie']       = $movie->idmovie;
		$params['idusercreated'] = $movie->iduser;
		$params['idcustomer']    = $movie->idcustomer;
		$params['title']         = $movie->title;
		$params['userfullname']  = $user->getPrettyName();
		$params['userphoto']     = $user->photo ? $user->photo : 'https://sfresources.blob.core.windows.net/site/avatar_38x35.jpg';
		$params['videourl']      = $movie->videourl;
		$params['action_type']   = orm\MovieModel::ACTION_MONITOR;

		$request->addPostFields($params);

		try
		{
			$request->send();
			return true;
		}
		catch(RequestException $ex)
		{
			$response = json_decode($ex->getResponse()->getBody(), TRUE);

			$message = isset($response['message']) ? $response['message'] : 'Error on calling staysmart add webservice: no details about it.';

			$send2MonitorFailed = new orm\Send2MonitorFailedModel();
			$send2MonitorFailed->idcampaign = $movie->idcampaign;
			$send2MonitorFailed->idmovie    = $movie->idmovie;
			$send2MonitorFailed->message    = $message;
			DataMapperManager::create($send2MonitorFailed);

			if ($returnError)
			{
				return $ex;
			}
			else
			{
				return false;
			}
		}
		catch(\Exception $ex)
		{
			$response = json_decode($ex->getResponse()->getBody(), TRUE);

			$message = isset($response['message']) ? $response['message'] : 'Error 500 when calling staysmart add webservice.';

			$send2MonitorFailed = new orm\Send2MonitorFailedModel();
			$send2MonitorFailed->idcampaign = $movie->idcampaign;
			$send2MonitorFailed->idmovie    = $movie->idmovie;
			$send2MonitorFailed->message    = $message;
			DataMapperManager::create($send2MonitorFailed);

			if ($returnError)
			{
				return $ex;
			}
			else
			{
				return false;
			}
		}
	}

	public function getConfig($campaign, $key = NULL)
	{
		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		$keyStoreServ = serv\KeyStoreService::getInstance();

		return $keyStoreServ->get('campaign', $campaign->slug, $key);
	}

	public function setConfig($campaign, $config)
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();

		foreach ($config as $key => $value)
		{
			$keyStoreServ->set('campaign', $campaign->slug, $key, $value);
		}
	}

	public function removeConfig($campaign, $key)
	{
		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		if ( ! $key)
		{
			throw new \Exception('Missing key parameter');
		}

		$keyStoreServ = serv\KeyStoreService::getInstance();

		$campaignConfig = $this->getConfig($campaign, $key);

		if ($campaignConfig)
		{
			$keyStoreServ->remove('campaign', $campaign->idcampaign, $key);
		}
	}

	public function setUser($campaign, $user)
	{
		if (! $campaign)
		{
			throw new \Exception('Missing campaign parameter.');
		}

		if (! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		$config = array();
		$config['iduser'] = $user->iduser;

		$this->setConfig($campaign, $config);
	}

	public function getUser($campaign)
	{
		$config = $this->getConfig($campaign, 'iduser');

		if ( ! $config['iduser'])
		{
			return NULL;
		}

		$userServ = serv\UserService::getInstance();

		$user = $userServ->get($config['iduser']);

		if ( ! $user)
		{
			return NULL;
		}

		return $user;
	}

	public function getPublicCampaign()
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();

		$galleryConfig = $keyStoreServ->get('gallery', 'config', 'public_campaigns');

		$campaigns = array();

		if (isset($galleryConfig['public_campaigns']))
		{
			$slugs = explode(',', $galleryConfig['public_campaigns']);

			if ($slugs)
			{
				foreach ($slugs as $slug)
				{
					$campaigns[] = $this->getCampaignBySlug($slug);
				}
			}
			else
			{
				$campaigns[] = $this->getCampaignBySlug('stayfilm');
			}
		}
		else
		{
			$campaigns[] = $this->getCampaignBySlug('stayfilm');
		}

		return $campaigns;
	}

}
