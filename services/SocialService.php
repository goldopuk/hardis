<?php


namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\UserModel;
use \Stayfilm\stayzen\ORM\MovieModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\services as serv;
use \Stayfilm\stayzen\Bcrypt;
use \Stayfilm\stayzen\ORM\CQLQuery;
use \Stayfilm\stayzen\ORM\UserFriendsModel;
use \Stayfilm\stayzen\Application;
use \Stayfilm\stayzen as zen;
use Symfony\Component\Validator\Constraints as constraints;
use Symfony\Component\Validator\Validation;
use WindowsAzure\Common\ServicesBuilder;

/**
 * Description of SocialService
 *
 * @author julien
 */
class SocialService extends Service
{

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\SocialService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param  \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	public function getFriendshipRequests($user, $limit = NULL, $ts = FALSE)
	{
		$userServ = serv\UserService::getInstance();

		$fields   = array();
		$fields[] = 'iduser';

		$values   = array();
		$values[] = $user->iduser;

		if ($ts)
		{
			$fields[] = '>friendshiprequestcreated';
			$values[] = $ts;
		}

		$list =  DataMapperManager::findAllBy('dbsite.friendshiprequestcore', $fields, $values, array(), $limit);

		$friends = $userServ->getFriends($user, NULL, array('idfriend'));

		$newList = array();

		$valid = true;

		// TODO: Isto é apenas um fix temporário. Temos de encontrar onde está conseguindo fazer pedido de amizade se já é amigo.
		if ($list)
		{
			foreach ($list as $l)
			{
				foreach ($friends as $f)
				{
					if ($f->idfriend === $l->idrequester)
					{
						$valid = false;
						break;
					}
				}

				if ($valid)
				{
					$newList[] = $l;
				}

				$valid = true;
			}
		}

		return $newList;
	}

	function countFriendshipRequests($user)
	{
		$requests = $this->getFriendshipRequests($user);
		return count($requests);
	}

	/**
	 *
	 * @param  \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	public function getMyFriendshipRequests($user)
	{
		$fields   = array();
		$fields[] = 'iduser';

		$values   = array();
		$values[] = $user->iduser;

		$list =  DataMapperManager::findAllBy('dbsite.friendshiprequest', $fields, $values, array(), null);

		$newlist = array();

		foreach ($list as $req)
		{
			if ($req->status === orm\FriendshipRequestModel::PENDING)
			{
				$newlist[] = $req->idrequester;
			}
		}

		$fields   = array();
		$fields[] = 'idrequester';

		$values   = array();
		$values[] = $user->iduser;

		$list =  DataMapperManager::findAllBy('dbsite.friendshiprequest', $fields, $values, array(), null);

		foreach ($list as $req)
		{
			if ($req->status === orm\FriendshipRequestModel::PENDING)
			{
				$newlist[] = $req->iduser;
			}
		}

		return $newlist;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $requester
	 * @param \Stayfilm\stayzen\ORM\UserModel $friend
	 * @return \Stayfilm\stayzen\ORM\FriendshipRequest
	 */
	public function getFriendshipRequest($user, $requester)
	{
		return DataMapperManager::findByKey('dbsite.friendshiprequest', array($user->iduser, $requester->iduser));
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param \Stayfilm\stayzen\ORM\UserModel $requester
	 * @return string
	 */
	public function getFriendshipStatus($user, $otherUser)
	{
		if ( ! $user || ! $user->iduser)
		{
			return 'MISSING_USER';
		}

		if (! $otherUser || ! $otherUser->iduser)
		{
			return 'MISSING_USER';
		}

		if ($user->iduser === $otherUser->iduser)
		{
			return 'SAME';
		}

		if ($this->areFriends($user, $otherUser))
		{
			return 'FRIENDS';
		}

		$req = $this->getFriendshipRequest($otherUser, $user);

		if ($req && $req->status === ORM\FriendshipRequestModel::PENDING)
		{
			return 'REQUEST_RECEIVED';
		}

		$req = $this->getFriendshipRequest($user, $otherUser);

		if ($req && $req->status === ORM\FriendshipRequestModel::PENDING)
		{
			return 'REQUEST_SENT';
		}

		if ($req && $req->status === ORM\FriendshipRequestModel::REJECTED)
		{
			return 'REJECTED';
		}

		return 'NO_RELATIONSHIP';
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param \Stayfilm\stayzen\ORM\UserModel $requester
	 * @return boolean
	 * @throws \Exception
	 */
	public function acceptFriendship($user, $requester)
	{
		$socialServ = serv\SocialService::getInstance();

		$req = $this->getFriendshipRequest($user, $requester);

		if ( ! $req)
		{
			return FALSE;
		}

		$key = array();
		$key[0] = $user->iduser;
		$key[1] = $req->created;

		if ( ! $req)
		{
			throw new \Exception("FriendRequest does not exist");
		}

		if ($req->status != ORM\FriendshipRequestModel::PENDING)
		{
			throw new \Exception("Friend request should have the status PENDING");
		}

		$this->createFriendship($user, $requester);

		$req->status = orm\FriendshipRequestModel::ACCEPTED;

		DataMapperManager::update($req);

		// look for other friendship request pending between those 2 users
		$req = $socialServ->getFriendshipRequest($requester, $user);

		if ($req)
		{
			$req->status = orm\FriendshipRequestModel::ACCEPTED;
			DataMapperManager::update($req);
		}

		$reqCore = DataMapperManager::findByKey('dbsite.friendshiprequestcore', $key);
		DataMapperManager::delete($reqCore);


		if ( ! $user->friends)
		{
			$user->friends = 0;
		}

		$user->friends += 1;
		DataMapperManager::update($user);

		if ( ! $requester->friends)
		{
			$requester->friends = 0;
		}

		$requester->friends += 1;
		DataMapperManager::update($requester);


		$this->fire('friendship-accepted', array('user' => $user, 'requester' => $requester));

		return TRUE;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $requester
	 * @param \Stayfilm\stayzen\ORM\UserModel $friend
	 * @throws \Exception
	 */
	public function rejectFriendship($user, $requester)
	{
		$socialServ = serv\SocialService::getInstance();

		$req = $socialServ->getFriendshipRequest($user, $requester);

		$key = array();
		$key[0] = $user->iduser;
		$key[1] = $req->created;

		if ( ! $req)
		{
			throw new \Exception("FriendRequest does not exist");
		}

		if ($req->status !== ORM\FriendshipRequestModel::PENDING)
		{
			throw new \Exception("Friend request should have the status PENDING");
		}

		$req->status = orm\FriendshipRequestModel::REJECTED;

		DataMapperManager::update($req);

		$reqCore = DataMapperManager::findByKey('dbsite.friendshiprequestcore', $key);
		DataMapperManager::delete($reqCore);

		$this->fire('friendship-rejected', array('user' => $user, 'requester' => $requester));

		return;
	}

		/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param \Stayfilm\stayzen\ORM\UserModel $requester
	 * @return \Stayfilm\stayzen\ORM\FriendshipRequestModel
	 */
	public function createFriendshipRequest($user, $requester)
	{
		// check status
		$status = $this->getFriendshipStatus($user, $requester);

		if ( ! in_array($status, array('NO_RELATIONSHIP', 'REJECTED')))
		{
			throw new \Exception("Friendship status $status invalid to create a new friendship request");
		}

		$req = new ORM\FriendshipRequestModel();

		$req->iduser = $user->iduser;
		$req->idrequester = $requester->iduser;
		$req->status = orm\FriendshipRequestModel::PENDING;

		$req = DataMapperManager::create($req);

		$reqCore = new ORM\FriendshipRequestCoreModel();
		$reqCore->iduser = $req->iduser;
		$reqCore->idrequester = $req->idrequester;
		$reqCore->friendshiprequestcreated = $req->created;

		DataMapperManager::create($reqCore);

		$this->fire('friendship-request', array('user' => $user, "requester" => $requester ));

		return $req;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user1
	 * @param \Stayfilm\stayzen\ORM\UserModel $user2
	 */
	public function createFriendship($user1, $user2)
	{
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

		$uf = new orm\UserFriendsModel();
		$uf->iduser     = $user1->iduser;
		$uf->idfriend   = $user2->iduser;
		$uf->friendname = $user2->username;
		DataMapperManager::create($uf);

		$uf = new orm\UserFriendsModel();
		$uf->iduser = $user2->iduser;
		$uf->idfriend = $user1->iduser;
		$uf->friendname = $user1->username;
		DataMapperManager::create($uf);

		$this->fire($this->getEventName('createFriendship'), array('user1' => $user1, 'user2' => $user2));
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user1
	 * @param \Stayfilm\stayzen\ORM\UserModel $user2
	 * @return null
	 */
	public function removeFriendship($user1, $user2)
	{
		$query = new CQLQuery('userfriends');

		// Fepas, alterado a forma como é preenchido os campos que serão usados no where.
		$query->where('iduser', $user1->iduser, 'uuid', '=');
		$query->where('idfriend', $user2->iduser, 'uuid', '=');

		$res = $query->delete();

		$query = new CQLQuery('userfriends');

		$query->where('iduser', $user2->iduser, 'uuid', '=');
		$query->where('idfriend', $user1->iduser, 'uuid', '=');

		$res = $query->delete();

		if($user1->friends)
		{
			$user1->friends -= 1;
			DataMapperManager::update($user1);
		}

		if($user2->friends)
		{
			$user2->friends -= 1;
			DataMapperManager::update($user2);
		}

		return;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	public function getFriends($user, $limit = NULL, $offset = NULL, $solr = FALSE, $total = FALSE, $colunms = NULL)
	{
		if ($solr)
		{
			$client = Application::getSolrClient('user2user');

			$query = $client->createSelect();
			$query->setStart($offset)->setRows($limit);

			$str = 'iduser1:' . $user->iduser . ' OR iduser2:' . $user->iduser;

			$query->setQuery($str);

			list($user2user, $count) = $client->execute($query, true);

			if ( ! $user2user)
			{
				$user2user = array();
			}

			$friends = array();

			if (self::$permissionEnabled)
			{
				$securityM = zen\SecurityManager::getInstance();

				$fields = $securityM->getAllowedFieldsByStatus('FRIENDS');
			}

			foreach ($user2user as $u2u)
			{
				$friend = DataMapperManager::findByKey('dbsite.user', $u2u->iduser1 != $user->iduser ? $u2u->iduser1 : $u2u->iduser2);

				if ($friend)
				{
					$friends[] = $friend;
				}
			}

			if ($total === true)
			{
				return array($friends, $count);
			}
			else
			{
				return $friends;
			}
		}
		else
		{
			$total = DataMapperManager::countBy('userfriends', 'iduser', $user->iduser);

			$fields = array();
			$fields[] = 'iduser';

			$values = array();
			$values[] = $user->iduser;

			if ($offset)
			{
				$fields[] = '>idfriend';
				$values[] = $offset;
			}

			$userFriends = DataMapperManager::findAllBy('userfriends', $fields, $values, array('idfriend'), $limit);

			if ( ! $userFriends)
			{
				return array(array(), 0, $total);
			}

			$ids = array();

			foreach ($userFriends as $uf)
			{
				$ids[] = $uf->idfriend;
			}

			if (self::$permissionEnabled)
			{
				$securityM = zen\SecurityManager::getInstance();

				$colunms = $securityM->getAllowedFieldsByStatus('FRIENDS');
			}
			else
			{
				$colunms = $colunms === '*' ? NULL : array('iduser', 'username', 'photo', 'firstname', 'lastname');
			}

			// find amigos
			$users = DataMapperManager::findAllIn('user', 'iduser', $ids, $colunms, $limit);

			if (count($userFriends) > 0)
			{
				$last = end($userFriends);
				if ($last)
				{
					$newOffset = $last->idfriend;
				}
				else
				{
					$newOffset = 0;
				}
			}

			return array($users, $newOffset, $total);
		}
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	public function getFriendCount($user)
	{
		$userFriends = DataMapperManager::findAllBy('userfriends', 'iduser', $user->iduser);

		return count($userFriends);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	public function getFeed($user, $limit = 10, $ts = 0, $newStories = FALSE, $storiesType = array())
	{
		$movieServ     = serv\MovieService::getInstance();
		$securityManag = zen\SecurityManager::getInstance();

		// get the list of users that we will search for timeline registers to show on our "main page" (feed page)
		// these users are our friends and us
		list($friends) = $this->getFriends($user);
		$friends[]     = $user;

		$ids = array();

		foreach ($friends as $friend)
		{
			$ids[] = $friend->iduser;
		}

		$followings = $this->getFollowings($user);

		if ($followings)
		{
			foreach ($followings as $following)
			{
				$ids[] = $following->idfollowing;
			}
		}

		$nextOffset     = 0;
		$previousOffset = 0;
		$limit          = (int)$limit;
		$newSearch      = FALSE;
		$stories        = array();
		$related        = array();

		$maxLoop = 10;
		$loopCount = 0;

		do {

			$loopCount++;

			$fields   = array();
			$fields[] = '@iduser';

			$values   = array();
			$values[] = array_unique($ids);

			if ($ts)
			{
				$fields[] = $newStories ? '>created' : '<created';
				$values[] = $ts;
			}

			$result = DataMapperManager::findAllBy('dbsite.timeline', $fields, $values, array(), $limit, "created DESC");

			foreach($result as $r)
			{
				// if the actual object isn't an onject that is in the list of types that the user want, so, continue, and refresh the timestamp (offset)
				if (count($storiesType) > 0 && ! in_array($r->objecttype, $storiesType))
				{
					if ($newStories)
					{
						$ts = $r->created > $previousOffset ? $r->created : $previousOffset;
					}
					else
					{
						$ts         = $r->created < $nextOffset || $nextOffset === 0 ? $r->created : $nextOffset;
						$nextOffset = $r->created < $nextOffset || $nextOffset === 0 ? $r->created : $nextOffset;
					}

					if ($r->related)
					{
						$related[] = $r->related;
					}

					continue;
				}

				if ($r->related)
				{
					if (in_array($r->related, $related))
					{
						continue;
					}
					if ($r->related)
					{
						$related[] = $r->related;
					}
				}

				switch ($r->objecttype)
				{
					case 'friendship':
						$stories[$r->iduser][] = $r;
						break;
					case 'movie':
					case 'movie-share':
						try
						{
							$movie = $movieServ->get($r->objectid);
							$securityManag->checkPermission($user, $movie);
							$stories[] = $r;
						}
						catch (\Exception $ex)
						{
							$previousOffset = $r->created > $previousOffset ? $r->created : $previousOffset;
							$nextOffset     = $r->created < $nextOffset || $nextOffset === 0 ? $r->created : $nextOffset;

							continue;
						}
						break;
					default:
						$stories[] = $r;
						break;
				}

				$previousOffset = $r->created > $previousOffset ? $r->created : $previousOffset;
				$nextOffset     = $r->created < $nextOffset || $nextOffset === 0 ? $r->created : $nextOffset;

				if (count($stories) === $limit)
				{
					break;
				}
			}

			$break = FALSE;

			// when it's a new story and the count we got from DB is lower than the limit we want, so
			// then we know that we got the table's finish
			if ($newStories)
			{
				if (count($result) < $limit)
				{
					$break = TRUE;
				}
				else
				{
					$ts = $previousOffset;
				}
			}

			// this first condition can will fail to the last register.
			// i would have to implement the "limit + 1" be sure that there's no more registers
			// @todo implement "limit + 1" to be sure that there's no more registers
			if (count($result) > 0 && count($stories) < $limit)
			{
				$newSearch = TRUE;

				$ts = ($newStories ? $previousOffset : $nextOffset);
			}
			else
			{
				$newSearch = FALSE;
			}
		} while ($newSearch && count($result) !== 0 && ! $break && $loopCount < $maxLoop);

		$nextOffset = count($stories) < $limit ? 0 : $nextOffset;

		return array($stories, count($stories), $nextOffset, $previousOffset);
	}

	public function getStories($user, $ts = NULL, $limit = 6)
	{
		$fields   = array();
		$fields[] = 'iduser';

		$values   = array();
		$values = $user->iduser;

		if ($ts)
		{
			$fields[] = '<created';
			$values[] = $ts;
		}

		$models = DataMapperManager::findAllBy('dbsite.timeline', $fields, $values, array(), $limit, "created DESC");

		$stories = $this->formatStories($models, $user);

		return $stories;
	}

	private function formatStories($models, $user = NULL)
	{
		$movieServ     = serv\MovieService::getInstance();
		$securityManag = zen\SecurityManager::getInstance();

		$stories = array();
		$related = array();

		foreach($models as $model)
		{
			if (($model->related && in_array($model->related, $related)))
			{
				continue;
			}

			$related[] = $model->related;

			switch ($model->objecttype)
			{
				case 'friendship':
					$stories[$model->iduser][] = $model;
					break;
				case 'movie':
				case 'movie-share':
					try
					{
						$movie = $movieServ->get($model->objectid);

						$securityManag->checkPermission($user, $movie);
						$stories[] = $model;
					}
					catch (\Exception $ex)
					{
						continue;
					}
					break;
				default:
					$stories[] = $model;
					break;
			}
		}

		return $stories;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param \Stayfilm\stayzen\ORM\UserModel $friend
	 * @return boolean
	 */
	public function areFriends($user, $friend)
	{
		$uf = DataMapperManager::findByKey('dbsite.userfriends', array($user->iduser, $friend->iduser));
		return (boolean)$uf;
	}

	/**
	 *
	 * @param $iduser int
	 * @return int
	 */
	function getCountPendingInvitations($iduser)
	{
		$userServ = serv\UserService::getInstance();

		// $client = Application::getSolrClient();

		// $str = "iduser: " . $iduser . ' AND status: ' . self::PENDING;

		// $query = $client->createSelect();
		// $query->setQuery($str);

		$requests = DataMapperManager::findAllBy('dbsite.friendshiprequest', 'iduser', $iduser, array(), NULL);

		$count = 0;

		foreach($requests as $request)
		{
			$fields = array();
			$fields[] = 'iduser';

			$iduserRequester = $userServ->getUserByKey($request->idrequester, $fields);

			if ($iduserRequester)
			{
				if($request->status == ORM\FriendshipRequestModel::PENDING)
				{
					$count++;
				}
			}
		}

		return $count;
		//return $client->count($query, 'dbsite.friendshipquerests');
	}
	/**
	* Work in progress
	*/
	public function getUserGraph($user)
	{
		$models = DataMapperManager::findAllBy('dbsite.usersearch', 'iduser', $user->iduser);

		$graph = array();
		$graph[0] = array('iduser' => $user->iduser, 'name' => $user->getPrettyName());
		$graph[1] = array();
		$graph[2] = array();

		foreach ($models as $model)
		{
			if ($model->distance === 1)
			{
				$graph[1][] = array('iduser' => $model->idfriend, 'name' => $model->name);
			}

			if ($model->distance === 2)
			{
				$graph[2][] = array('iduser' => $model->idfriend, 'name' => $model->name);
			}
		}

		return $graph;
	}

	/**
	 *
	 * @param type $user
	 * @param type $movie
	 * @param type $comment
	 * @throws \Exception
	 */
	public function shareMovie($user, $movie, $comment)
	{
		info(__METHOD__);

		$timelineServ = serv\TimelineService::getInstance();
		$movieServ    = serv\MovieService::getInstance();

		// check if the user already share a movie.
		if ($movieServ->isMovieSharedByUser($user, $movie))
		{
			throw new \Exception("User has already shared that movie");
		}

		if ($movie->status !== orm\MovieModel::STATUS_ACTIVE)
		{
			throw new \Exception("Movie {$movie->idmovie} is not active and cannot be shared");
		}

		if ($movie->permission !== orm\MovieModel::PUBLIC_)
		{
			throw new \Exception("Movie {$movie->idmovie} is not public and cannot be shared");
		}

		$timelineServ->add($user, orm\TimelineModel::TYPE_MOVIESHARE, array('movie' => $movie, 'comment' => $comment));
		$movieServ->addShare($movie, $user);
	}

	/**
	 *
	 * @param type $user
	 * @param type $movie
	 * @return boolean
	 */
	function hasSharedMovie()
	{
		return false;
	}

	public function isValidAccount($user)
	{
		$userServ = serv\UserService::getInstance();

		$validator = Validation::createValidator();

		$emailConst  = new constraints\Email();
		$lengthConst = new constraints\Length(array('min' => 3, "max" => 12));

		$violations = $validator->validateValue($user->email, $emailConst);

		if ($violations->count() > 0)
		{
			throw new \Exception(__('Por favor, preencha o e-mail corretamente.'));
		}

		$violations = $validator->validateValue($user->password, $lengthConst);

		if ( ! $user->password)
		{
			throw new \Exception(__('A senha precisa ter entre 5 e 12 caracteres.'));
		}

		if ($userServ->emailExists($user->email))
		{
			throw new \Exception(__('Este e-mail já está em uso. Por favor, escolha outro e-mail válido.'));
		}

		if ( ! isValidDate($user->birthday, 'pt'))
		{
			throw new \Exception(__('Por favor, informe sua verdadeira data de aniversário.'));
		}

		if ( ! zen\Utilities::isValidGender($user->gender))
		{
			throw new \Exception(__('Você precisa informar o gênero.'));
		}
	}

	public function setUserWithFacebookData($user, $shortLivedToken, $type = 'site', $appId = NULL)
	{
		$oauthServ = serv\OAuthService::getInstance();

		$fbData = $oauthServ->getFacebookUserInfoByToken($shortLivedToken, $type, $appId);

		info($fbData);

		$location         = $fbData['location'] ? explode(',', $fbData['location']) : array();
		$country          = array_pop($location);
		$city             = array_pop($location);
		$user->idfacebook = $fbData['uid'];

		if ($country)
		{
			$user->country = trim($country);
		}

		if ($city)
		{
			$user->city = trim($city);
		}

		$user->languages = $fbData['language'];

		// Gets the avatar image from Facebook and put it on blob
		try
		{
			$url = $fbData['image'];

			$filename = (string) \phpcassa\UUID::uuid4();

			$fullImagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

			$imageUrlContent = file_get_contents($url);

			if ( ! $imageUrlContent)
			{
				throw new \Exception("Couldn't get content from " . $url);
			}

			file_put_contents($fullImagePath, $imageUrlContent);

			$midiaServ = serv\MidiaService::getInstance();

			$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($midiaServ->getAzureConnectionString());

			$configBlob = zen\Application::$config->azure_album_container;

			$fileHandle = fopen($fullImagePath, "r");

			$blobRestProxy->createBlockBlob($configBlob->name, $filename, $fileHandle);

			$user->photo = $configBlob->url . '/' . $filename;

			@unlink($fullImagePath);
		}
		catch(\Exception $ex)
		{
			// Do nothing, just continue and leave the $user->photo empty
		}
	}

	public function addFollowing($following, $follower)
	{
		$userFollower = new orm\UserFollowerModel();

		$userFollower->iduser     = $following->iduser;
		$userFollower->idfollower = $follower->iduser;

		DataMapperManager::create($userFollower);

		$userFollowing = new orm\UserFollowingModel();

		$userFollowing->iduser      = $follower->iduser;
		$userFollowing->idfollowing = $following->iduser;

		DataMapperManager::create($userFollowing);
	}

	public function removeFollowing($following, $follower)
	{
		$userFollower = new orm\UserFollowerModel();

		$userFollower->iduser     = $following->iduser;
		$userFollower->idfollower = $follower->iduser;

		DataMapperManager::delete($userFollower);

		$userFollowing = new ORM\UserFollowingModel();

		$userFollowing->iduser      = $follower->iduser;
		$userFollowing->idfollowing = $following->iduser;

		DataMapperManager::delete($userFollowing);
	}

	public function getFollowers($following)
	{
		$field = array();
		$field[] = 'iduser';

		$value = array();
		$value[] = $following->iduser;

		return DataMapperManager::findAllBy('dbsite.userfollower', $field, $value);
	}

	public function getFollowings($follower)
	{
		$field = array();
		$field[] = 'iduser';

		$value = array();
		$value[] = $follower->iduser;

		return DataMapperManager::findAllBy('dbsite.userfollowing', $field, $value);
	}

	/**
	 *
	 * @param type $follower
	 * @param type $following
	 * @return type
	 */
	public function isFollowed($follower, $following)
	{
		$id = array();
		$id[] = $follower->iduser;
		$id[] = $following->iduser;

		$result = DataMapperManager::findByKey('dbsite.userfollowing', $id);

		return ($result ? TRUE : FALSE);
	}
}
