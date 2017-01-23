<?php

namespace Stayfilm\stayzen\services;

use Gomoob\Pushwoosh\Model\Notification\IOS;
use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\services\JobService;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\ORM\JobModel;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\MovieModel;
use Stayfilm\stayzen\exception as exc;
use Guzzle\Http\Client;
use Guzzle\Plugin\Async\AsyncPlugin;
use Guzzle\Plugin\CurlAuth\CurlAuthPlugin;

class PushNotificationService extends TableService
{

	static protected $_instance = NULL;

	protected $messages = array();

	protected $table = 'dbsite.pushnotification';

	/**
	 * DO NOT DELETE - For INTELLISENSE
	 *
	 * @return \Stayfilm\stayzen\services\PushNotificationService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function setMessages($messages)
	{
		$this->messages = $messages;
	}

	public function sendToDevice($user, $pushNotif)
	{
		if ( ! zen\Application::$config->enable_push_notification)
		{
			info('Push deactivated');
			return;
		}

		$fields = array();
		$fields[] = 'iduser';

		$values = array();
		$values[] = $user->iduser;

		$userdevices = orm\DataMapperManager::findAllBy('dbsite.userdevice', $fields, $values);

		info($userdevices, '>>>> userdevices');

		if ( ! $userdevices)
		{
			return;
		}

		info('Sending Push NOtification via PUSHWOOSH !!!');

		$pushwoosh = \Gomoob\Pushwoosh\Client\Pushwoosh::create();

		info(Application::$config->app_id_push_notification, 'PushWoosh App Id');

		$pushwoosh->setApplication(Application::$config->app_id_push_notification);
		$pushwoosh->setAuth(Application::$config->auth_token_push_notification);

		$notif = \Gomoob\Pushwoosh\Model\Notification\Notification::create();
		$notif->setContent($pushNotif->message);

		$finalData = array();
		$finalData['id'] = $pushNotif->idpush;

		$notif->setData($finalData);
		$notif->setSendDate('now');

		$iosNotif = IOS::create();
		$iosNotif->setBadges("+1");
		$notif->setIOS($iosNotif);

		foreach ($userdevices as $userdevice)
		{
			info($userdevice->iddevice, 'PushWoosh Device Id');
			$notif->addDevice($userdevice->iddevice);
		}

		$request = \Gomoob\Pushwoosh\Model\Request\CreateMessageRequest::create()->addNotification($notif);

		$pushwoosh->createMessage($request);
	}

	public function movieCreated($user, $movie)
	{
		$pushNotif = new orm\PushNotificationModel();

		$data = array();
		$data['idmovie'] = $movie->idmovie;

		$pushNotif->type = 'movie-created';
		$pushNotif->data = $data;

		$pushNotif->message = $this->getMessage('movie-created', $movie->title);

		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function movieLike(orm\UserModel $user, orm\UserModel $liker, orm\MovieModel $movie)
	{
		$type = 'movie-like';

		$pushNotif = new orm\PushNotificationModel();

		$data = array();
		$data['idmovie'] = $movie->idmovie;
		$data['idliker'] = $liker->iduser;

		$pushNotif->type = $type;
		$pushNotif->data = $data;

		$pushNotif->message = $this->getMessage($type, array($liker->getPrettyName(), $movie->title));

		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function movieShared(orm\UserModel $user, orm\UserModel $sharer, orm\MovieModel $movie)
	{
		$type = 'movie-shared';

		$pushNotif = new orm\PushNotificationModel();

		$data = array();
		$data['idmovie'] = $movie->idmovie;
		$data['idsharer'] = $sharer->iduser;

		$pushNotif->type = $type;
		$pushNotif->data = $data;

		$pushNotif->message = $this->getMessage($type, array($sharer->getPrettyName(), $movie->title));

		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function movieApproved(orm\UserModel $user, orm\MovieModel $movie)
	{
		$type = 'movie-approved';

		$pushNotif = new orm\PushNotificationModel();

		$data = array();
		$data['idmovie'] = $movie->idmovie;

		$pushNotif->type = $type;
		$pushNotif->data = $data;

		$pushNotif->message = $this->getMessage($type, array($movie->title));

		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function movieComment(orm\UserModel $user, orm\UserModel $commentator, orm\MovieModel $movie, $comment)
	{
		$type = 'movie-comment';

		$pushNotif = new orm\PushNotificationModel();

		$data = array();
		$data['idmovie'] = $movie->idmovie;
		$data['idcommentator'] = $commentator->iduser;
		$data['idcomment'] = $comment->idcomment;

		$pushNotif->type = $type;
		$pushNotif->data = $data;

		$pushNotif->message = $this->getMessage($type, array($commentator->getPrettyName(), $movie->title));

		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function getMessage($type, $values)
	{
		if ( ! is_array($values))
		{
			$values = array($values);
		}

		if (isset($this->messages[$type]))
		{
			$translation =  $this->messages[$type];
		}
		else
		{
			$translation = "Push Notif $type has no message defined";
		}

		foreach ($values as $key => $value)
		{
			$translation = str_replace('{'. $key . '}', $value, $translation);
		}

		return $translation;
	}

	public function friendshipRequest($user, $requester)
	{
		$pushNotif = new orm\PushNotificationModel();

		$type = 'friendship-request';

		$data = array();
		$data['iduser'] = $requester->iduser;

		$pushNotif->type = $type;
		$pushNotif->data = $data;
		$pushNotif->message = $this->getMessage($type, $requester->getPrettyName());
		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function friendshipAccepted($user, $friend)
	{
		$pushNotif = new orm\PushNotificationModel();

		$type = 'friendship-accepted';

		$data = array();
		$data['iduser'] = $friend->iduser;

		$pushNotif->type = $type;
		$pushNotif->data = $data;
		$pushNotif->message = $this->getMessage($type, $friend->getPrettyName());
		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

	public function friendRegistered($user, $registeredUser)
	{
		$pushNotif = new orm\PushNotificationModel();

		$type = 'friend-registered';

		$data = array();
		$data['iduser'] = $registeredUser->iduser;

		$pushNotif->type = $type;
		$pushNotif->data = $data;
		$pushNotif->message = $this->getMessage($type, $registeredUser->getPrettyName());
		$pushNotif->iduser = $user->iduser;

		DataMapperManager::create($pushNotif);

		$this->sendToDevice($user, $pushNotif);
	}

}