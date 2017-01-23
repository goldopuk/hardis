<?php
namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\services as serv;
use Facebook;
use phpcassa\UUID;

class SocialNetworkService extends Service
{

	static protected $_instance = NULL;

	/**
	 * DO NOT DELETE - For INTELLISENSE
	 *
	 * @return \Stayfilm\stayzen\services\SocialNetworkService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function insert($user, $networks)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		if ( ! $networks)
		{
			throw new \Exception('Missing networks parameter.');
		}


		$oauthServ = serv\OAuthService::getInstance();

		$jobService = serv\JobService::getInstance();

		foreach ($networks as $network)
		{
			switch ($network)
			{
				case 'facebook':
					$userToken = $oauthServ->getToken($user, $network);

					$job = new orm\JobModel();
					$job->iduser  = $user->iduser;
					$job->jobtype = serv\JobService::JOBTYPE_SOCIALNETWORK;
					$job->source  = $network;

					$jobServ = serv\JobService::getInstance();
					$jobServ->create($job);

					try
					{
						$this->facebookInsert($user, $userToken);
					}
					catch (\Exception $e)
					{
						$job->status = ORM\JobModel::FAILURE;

						$data = $job->data;

						if ( ! is_array($data))
						{
							$data = array();
						}

						$data['failure'] = $e->getMessage();

						$job->data = $data;

						$jobService->update($job);
					}

					break;
				default:
					throw new \Exception('Invalid social network.');

					break;
			}
		}
	}

	private function facebookInsert(orm\UserModel $user, orm\UserTokenModel $userToken)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		if ( ! $userToken)
		{
			throw new \Exception('Missing userToken parameter.');
		}

		$midiaServ = serv\MidiaService::getInstance();

		$appId = $userToken->appid;

		$conf = zen\Utilities::getSnConf('facebook', '', $appId);

		$appSecret = $conf['secret'];

		$userAccessToken = $userToken->accesstoken;
		//$userSecret      = $userToken->secret;

		Facebook\FacebookSession::setDefaultApplication($appId, $appSecret);

		$session = new Facebook\FacebookSession($userAccessToken);

		$request1     = new Facebook\FacebookRequest($session, 'GET', '/me/albums');
		$response1    = $request1->execute();
		$graphObject1 = $response1->getGraphObject();
		$albums       = $graphObject1->asArray();

		foreach ($albums['data'] as $album)
		{
			$newAlbum = $this->createAlbumFromFacebook($user, $album);

			// Get album's photos
			$request2     = new Facebook\FacebookRequest($session, 'GET', "/{$album->id}/photos");
			$response2    = $request2->execute();
			$graphObject2 = $response2->getGraphObject();
			$photos       = $graphObject2->asArray();

			$lastPage = false;

			do {
				if ( ! isset($photos['data']))
				{
					break;
				}

				foreach ($photos['data'] as $photo)
				{

					$comments = array();

					// Get photo's comments
					if (isset($photo->comments->data))
					{
						foreach ($photo->comments->data as $comment)
						{
							$comments[] = $comment->message;
						}
					}

					$likesCount = 0;

					// Get photo's likes
					if (isset($photo->likes->data))
					{
						$likesCount += count($photo->likes->data);
					}

					$location = null;

					// Get photo's place
					if (isset($photo->place) && isset($photo->place->id))
					{
						$location[] = str_replace("\n", "\\n", str_replace("\"", '', str_replace("\r", "\\n", $photo->place->name)));

						if (isset($photo->place->location->city))
						{
							$location['city'] = $photo->place->location->city;
						}

						if (isset($photo->place->location->state))
						{
							$location[] = $photo->place->location->state;
						}

						if (isset($photo->place->location->country))
						{
							$location[] = $photo->place->location->country;
						}
					}

					$peopleTaged = null;

					// Get taged people
					if (isset($photo->tags) && isset($photo->tags->data))
					{
						foreach ($photo->tags->data as $personTagged)
						{
							$p = array();

							if (isset($personTagged->x))
							{
								$personTagged->x = str_replace("\":", '', $personTagged->x);
								$personTagged->y = str_replace("\":", '', $personTagged->y);

								$p['x'] = $personTagged->x;
								$p['y'] = $personTagged->y;
							}

							$peopleTaged[] = json_encode($p);
						}
					}

					$media = new orm\MidiaModel();
					$media->iduser          = $user->iduser;
					$media->idmidia         = UUID::uuid4()->string;
					$media->comments        = json_encode($comments);
					$media->extension       = 'jpg';
					$media->height          = $photo->height;
					$media->idalbum         = $newAlbum->idalbum;
					$media->idmidianet      = $photo->id;
					$media->idsocialnetwork = 1;
					$media->likecount       = $likesCount;
					$media->location        = json_encode($location);
					$media->name            = isset($photo->name) ? str_replace("'", '', $photo->name) : '';
					$media->origin          = 'socialnetwork';
					$media->peopletagged    = $peopleTaged;
					$media->source          = $photo->source;
					$media->thumbnail       = $photo->picture;
					$media->width           = $photo->width;

					$midiaServ->create($media);
				}

				if (isset($photos['paging']->next))
				{
					$request3     = new Facebook\FacebookRequest($session, 'GET',
							"/{$album->id}/photos?pretty=0&limit=25&after={$photos['paging']->cursors->after}");
					$response3    = $request3->execute();
					$graphObject3 = $response3->getGraphObject();
					$photos       = $graphObject3->asArray();
				}
				else
				{
					$request3 = null;
					$lastPage = true;
				}
			} while( ! $lastPage);
		}
	}

	private function createAlbumFromFacebook($user, $albumInfo)
	{
		$albumServ = serv\AlbumService::getInstance();

		$album = new orm\AlbumModel();

		$album->iduser          = $user->iduser;
		$album->description     = isset($albumInfo->description) ? $albumInfo->description : '';
		$album->idalbumnet      = $albumInfo->id;
		$album->idsocialnetwork = 1;
		$album->name            = $albumInfo->name;

		return $albumServ->create($album, $user);
	}


	//public function update($user, $medias) { }
}