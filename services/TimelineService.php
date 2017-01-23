<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\ORM\ThemeModel; // TODO

class TimelineService extends Service
{

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\TimelineService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param string $eventName
	 * @param mixed $params
	 */
	function handleEvent($eventName, $params)
	{
		info(__METHOD__);
		info("Event Name : $eventName");

		parent::handleEvent($eventName, $params);

		if ($eventName === 'SocialService:createFriendship')
		{
			$this->newRelationShip($params);
		}
	}

	/**
	 *
	 * @param array $params
	 * @throws \Exception
	 */
	protected function newRelationShip($params)
	{
		info(__METHOD__);

		$user1 = $params['user1'];
		$user2 = $params['user2'];

		$user1 = DataMapperManager::findByKey('dbsite.user', $user1->iduser);

		if ( ! $user1)
		{
			throw new \Exception("User {$user1->iduser} does not exist");
		}

		$user2 = DataMapperManager::findByKey('dbsite.user', $user2->iduser);

		if ( ! $user2)
		{
			throw new \Exception("User {$user2->iduser} does not exist");
		}

		$related = (string) UUID::uuid4();
		$this->add($user1, 'friendship', array('friend' => $user2), $related);
		$this->add($user2, 'friendship', array('friend' => $user1), $related);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * * @param \Stayfilm\stayzen\ORM\Model $object
	 * @return null
	 * @throws \Exception
	 */
	public function add($user, $objectType, $params, $related = null)
	{
		if ( ! self::isObjectTypeValid($objectType))
		{
			throw new \Exception("Invalid objecttype $objectType");
		}

		$tl = new orm\TimelineModel();
		$tl->iduser     = $user->iduser;
		$tl->created    = time();
		$tl->objecttype = $objectType;

		$tlreference = new orm\TimelineReferenceModel();

		$tlreference->iduser          = $tl->iduser;
		$tlreference->objecttype      = $tl->objecttype;

		if ($objectType === 'movie')
		{
			$movie = $params['movie'];

			$tl->objectid   = $movie->idmovie;
			$tl->metadata   = array();

			$tlreference->timelinecreated = $tl->created;
			$tlreference->objectid        = $tl->objectid;
		}

		if ($objectType === 'movie-share')
		{
			$movie   = $params['movie'];
			$comment = $params['comment'];

			$tl->objectid   = $movie->idmovie;
			$tl->metadata   = array('comment' => $comment);

			$tlreference->timelinecreated = $tl->created;
			$tlreference->objectid        = $tl->objectid;
		}

		if ($objectType === 'friendship')
		{
			$friend = $params['friend'];

			$tl->objectid   = $friend->iduser;
			$tl->metadata   = array();
			$tl->related    = $related;

			$tlreference->timelinecreated = $tl->created;
			$tlreference->objectid        = $tl->objectid;
		}

		DataMapperManager::create($tl);
		DataMapperManager::create($tlreference);

		return $tl;
	}

	/**
	 *
	 * @param type $user
	 * @param type $movie
	 * @return boolean
	 */
	function has($user, $movie)
	{
		$tlcore = DataMapperManager::findBy('dbsite.timelinereference', array('iduser', 'objectid', 'objecttype'),
					array($user->iduser, $movie->idmovie, 'movie'));

		return (boolean) $tlcore;
	}

	/**
	 *
	 * @param string $objectType
	 * @return string
	 */
	static function isObjectTypeValid($objectType)
	{
		$types =  array(orm\TimelineModel::TYPE_MOVIE, orm\TimelineModel::TYPE_MOVIESHARE,
			orm\TimelineModel::TYPE_COMMENT, orm\TimelineModel::TYPE_FRIENDSHIP);
		return in_array($objectType, $types);
	}
}