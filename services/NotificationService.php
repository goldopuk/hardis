<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\services as serv;
use \Stayfilm\stayzen as zen;

class NotificationService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbsite.notification';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\NotificationService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 * @param $user
	 * @param $type
	 * @param $params
	 * @return orm\Model|orm\NotificationModel
	 */
	function create($user, $type, $params)
	{
		$userServ = UserService::getInstance();

		$notif = new orm\NotificationModel();

		$notif->iduser = $user->iduser;
		$notif->created = time();
		$notif->notiftype = $type;
		$notif->data = $params;
		$notif->status = orm\NotificationModel::STATUS_UNREAD;

		$notif = DataMapperManager::create($notif);

		$notifCore = new orm\NotificationCoreModel();

		$notifCore->iduser = $user->iduser;
		$notifCore->notificationcreated = $notif->created;
		$notifCore->notiftype = $type;
		$notifCore->data = $params;
		$notifCore->idnotification = $notif->idnotification;
		$notifCore->status = orm\NotificationModel::STATUS_UNREAD;

		DataMapperManager::create($notifCore);

		$user->notifications = $user->notifications ? $user->notifications + 1 : 1;

		$userServ->update($user);

		return $notif;
	}

	/**
	 *
	 * @param type $iduser
	 * @param type $created
	 */
	function delete($notification)
	{
		$key = array();
		$key[] = $notification->iduser;
		$key[] = $notification->created;

		$notifCore = DataMapperManager::findByKey('dbsite.notificationcore', $key);
		DataMapperManager::delete($notifCore);
		DataMapperManager::delete($notification);
	}

	/**
	 *
	 * @param type $user
	 * @param type $onlyUnread
	 * @param type $offset
	 * @param type $limit
	 * @return type
	 */
	function getUserNotifications($user, $offset = NULL, $desc = TRUE, $limit = 10)
	{
		$fields = array();
		$fields[] = 'iduser';

		$values = array();
		$values[] = $user->iduser;

		if ($offset)
		{
			if ($desc)
			{
				$fields[] = '<notificationcreated';
			}
			else
			{
				$fields[] = '>notificationcreated';
			}

			$values[] = $offset;
		}

		$result = array();
		$result['notifications'] = DataMapperManager::findAllBy('dbsite.notificationcore', $fields, $values, NULL, $limit, 'notificationcreated desc');
		$result['total']         = DataMapperManager::countBy('dbsite.notificationcore', 'iduser', $user->iduser);

		$result['newOffset'] = NULL;

		if (count($result['notifications']) > 0)
		{
			$result['newOffset'] = $result['notifications'][count($result['notifications']) - 1]->notificationcreated;
		}

		return $result;
	}

	/**
	 *
	 * @param type $user
	 * @return type
	 */
	public function getUnreadUserNotifications($user)
	{
		$fields = array();
		$fields[] = 'iduser';
		$fields[] = 'status';

		$values = array();
		$values[] = $user->iduser;
		$values[] = orm\NotificationModel::STATUS_UNREAD;

		$limit = NULL;
		return DataMapperManager::findAllBy('dbsite.notification', $fields, $values, NULL, $limit);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	function countUserNotifications($user, $onlyUnread = false)
	{
		if ($onlyUnread)
		{
			return $user->notifications;
		}
		else
		{
			return DataMapperManager::countBy('dbsite.notificationcore', 'iduser', $user->iduser);
		}
	}

	/**
	 *
	 * @param string $eventName
	 * @param mixed $params
	 */
	function handleEvent($eventName, $params)
	{
		info(__METHOD__);

		$movieServ = serv\MovieService::getInstance();
		$emailManager = zen\EmailManager::getInstance();
		$userServ  = serv\UserService::getInstance();
		$pushServ = serv\PushNotificationService::getInstance();

		parent::handleEvent($eventName, $params);

		if ($eventName === 'friendship-request')
		{
			$requester = $params['requester'];
			$user = $params['user'];

			if (zen\Application::$config->notif_friendshiprequest_active)
			{
				$this->create($user, 'friendship-request', array('iduser' => $requester->iduser));
			}

			$pushServ->friendshipRequest($user, $requester);

			$email = $emailManager->getEmailInstance('friendship-request');

			if ($email)
			{
				$email->configure($user, $requester);
				$emailManager->send($email);
			}
		}

		if ($eventName === 'friendship-accepted')
		{
			$requester = $params['requester'];
			$user      = $params['user'];

			$this->create($requester, 'friendship-accepted', array('iduser' => $user->iduser));

			$pushServ->friendshipAccepted($requester, $user);

			$email = $emailManager->getEmailInstance('friendship-accepted');

			if ($email)
			{
				$email->configure($requester, $user);
				$emailManager->send($email);
			}
		}

		if ($eventName === 'MovieService:addLike')
		{
			$movie = $params['movie'];
			$liker = $params['liker'];
			$liked = $params['liked'];

			$movieOwner = $movie->getUser(false);

			if ( ! $movieOwner)
			{
				warn("movie owner {$movie->id_movie} does not exist");
				return;
			}

			if ($movieOwner->iduser === $liker->iduser)
			{
				// a user liked hi own movie. Do not send notifcation.
				return;
			}

			if ( ! $liked)
			{
				$this->create($movieOwner, 'movie-like', array('idliker' => $liker->iduser, 'idmovie' => $movie->idmovie));

				$pushServ->movieLike($movieOwner, $liker, $movie);

				$email = $emailManager->getEmailInstance('like');

				if ($email)
				{
					$email->configure($liker, $movie, $movieOwner);
					$emailManager->send($email);
				}
			}
		}

		if ($eventName === 'MovieService:addComment')
		{
			$comment = $params['comment'];

			$movie = $params['movie'];

			$commentator = DataMapperManager::findByKey('dbsite.user', $comment->iduser);

			$movieOwner = $movie->getUser();

			if ($commentator->iduser !== $movieOwner->iduser)
			{
				$this->create($movieOwner, 'movie-comment', array('idcommentator' => $comment->iduser, 'idmovie' => $movie->idmovie,
						'idcomment' => $comment->idcomment));

				$pushServ->movieComment($movieOwner, $commentator, $movie, $comment);

				$email = $emailManager->getEmailInstance('movie-comment');

				if ($email)
				{
					$email->configure($movieOwner, $movie, $commentator, $movieOwner);
					$emailManager->send($email);
				}
			}

			$users = $movieServ->getCommentators($movie);

			// create notification for every users that added a comment to this movie
			foreach ($users as $user)
			{
				if ($user->iduser != $commentator->iduser && $user->iduser != $movieOwner->iduser)
				{
					$this->create($user, 'movie-comment', array('idcommentator' => $comment->iduser, 'idmovie' => $movie->idmovie,
						'idcomment' => $comment->idcomment));

					$pushServ->movieComment($user, $commentator, $movie, $comment);

					$email = $emailManager->getEmailInstance('movie-comment');

					if ($email)
					{
						$email->configure($user, $movie, $commentator, $movieOwner);
						$emailManager->send($email);
					}
				}
			}
		}

		if ($eventName === 'movie-shared')
		{
			$sharer  = $params['user'];
			$movie = $params['movie'];
			$movieOwner    = $userServ->get($movie->iduser);

			$this->create($movieOwner, 'movie-shared', array('idsharer' => $sharer->iduser, 'idmovie' => $movie->idmovie));

			$pushServ->movieShared($movieOwner, $sharer, $movie);
		}

		if ($eventName === 'user-quoted')
		{
			$users     = $params['users'];
			$userCited = $params['userCited'];
			$movie     = $params['movie'];

			$emailManager = zen\EmailManager::getInstance();

			$email = $emailManager->getEmailInstance('user-quoted');
			foreach ($users as $user)
			{
				$email->configure($user, $movie);
				$emailManager->send($email);

				$this->create($user, 'user-quoted', array('usercited' => $userCited->iduser, 'idmovie' => $movie->idmovie));
			}
		}

		if ($eventName === 'movie-created')
		{
			$movie = $params['movie'];
			$user = $params['user'];

			$campaignServ = serv\CampaignService::getInstance();

			$campaign = $campaignServ->getCampaignById($movie->idcampaign);

			if ($campaign->slug === 'fbmessenger')
			{
				// do nothing by now...
			}
			else
			{
				$this->create($user, 'movie-created', array('idmovie' => $movie->idmovie));

				$pushServ->movieCreated($user, $movie);

				$email = $emailManager->getEmailInstance('movie-created');

				if ($email)
				{
					$email->configure($user, $movie);
					$emailManager->send($email);
				}
			}
		}

		if ($eventName === 'movie-created-automatically')
		{
			$movie = $params['movie'];
			$user = $params['user'];

			//$this->create($user, 'movie-created', array('idmovie' => $movie->idmovie));

			// Send a message to Stayfilm
			$email = new zen\email\AutomaticallyCreatedMovie();
			$email->configure($user, $movie);
			$emailManager->send($email);
		}

		return;
	}

	/**
	 *
	 * @param type $notif
	 * @return type
	 */
	function setAsRead($notif, $user = NULL)
	{
		if ($notif->status === orm\NotificationModel::STATUS_READ)
		{
			return $notif;
		}

		$userServ = UserService::getInstance();
		$notif->status = orm\NotificationModel::STATUS_READ;

		$key = array();
		$key[] = $notif->iduser;
		$key[] = $notif->created;

		$notifCore = DataMapperManager::findByKey('dbsite.notificationcore', $key);

		$notifCore->status = orm\NotificationModel::STATUS_READ;

		if ( ! $user)
		{
			DataMapperManager::disableCache();
			$user = $userServ->get($notif->iduser);
			DataMapperManager::enableCache();
		}

		if ($user)
		{
			$user->notifications = $user->notifications ? $user->notifications - 1 : 0;
			$userServ->update($user);
		}

		DataMapperManager::update($notifCore);

		return DataMapperManager::update($notif);
	}

	/**
	 * Pass the notification status to read for all notifications unread from user.
	 * @param type $user
	 */
	function setAllAsRead($user)
	{
		$notifs = $this->getUnreadUserNotifications($user);

		foreach ($notifs as $notif)
		{
			$this->setAsRead($notif, $user);
		}

		return;
	}
}
