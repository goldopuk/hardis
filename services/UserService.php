<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\UserModel;
use \Stayfilm\stayzen\ORM\MovieModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen as zen;
use \Stayfilm\stayzen\services as serv;
use \Stayfilm\stayzen\Bcrypt;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as c;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\Utilities;
use phpcassa\UUID;

class UserService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.user';

	public $directionUp = '<';
	public $directionDown = '>';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\UserService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param string $username
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	public function getUserByUsername($username)
	{
		$user = DataMapperManager::findBy('user', 'username', $username);

		if (self::$permissionEnabled)
		{
			self::filterFields($user);
		}

		return $user;
	}

	/**
	 *
	 * @param type $iduser
	 * @return type
	 */
	public function get($iduser)
	{
		$user = DataMapperManager::findByKey('dbsite.user', $iduser);

		if (self::$permissionEnabled)
		{
			$this->filterFields($user);
		}

		return $user;
	}

	/**
	 * Search by email and fallback by username
	 *
	 * @param string $email
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	public function getUserByEmail($email)
	{
		// We MUST email filled. A query with empty e-mail will bring random users.
		if ( ! $email)
		{
			return NULL;
		}

		$user =  DataMapperManager::findBy('user', 'email', $email);

		if ( ! $user)
		{
			$user =  DataMapperManager::findBy('user', 'username', $email);
		}

		return $user;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	public function incrementView($user)
	{
		$user->views += 1;
		return DataMapperManager::update($user);
	}

	/**
	 *
	 * @param UserModel $user
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	public function incrementShare($user)
	{
		$user->shares += 1;
		return DataMapperManager::update($user);
	}

	/**
	 *
	 * @param string $idfacebook
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	public function getUserByIdFacebook($idfacebook)
	{
		return DataMapperManager::findBy('user', 'idfacebook', "$idfacebook");
	}

	/**
	 *
	 * @param string $uid
	 * @param string $sn
	 * @return array UsersBySnUIDModel
	 */
	public function getUsersBySnUID($uid, $sn)
	{
		return DataMapperManager::findAllBy('userbysnuid', 'snuid', "$sn-$uid");
	}

	/**
	 * @param string $uuid
	 * @param array $selectFields
	 * @return orm\Model
	 */
	public function getUserByKey($uuid, $selectFields = array())
	{
		return DataMapperManager::findByKey('user', $uuid, $selectFields);
	}

	/**
	 *
	 * @param string $email
	 * @param string $password
	 * @return boolean
	 */
	public function authenticate($email, $password)
	{
		$user = DataMapperManager::findBy('user', 'email', $email);

		if ( ! $user)
		{
			$user = DataMapperManager::findBy('user', 'username', $email);
		}

		if ( ! $user || ! Bcrypt::validate($password, $user->password))
		{
			return false;
		}

		return true;
	}

	/**
	 * @param $user
	 * @param $password
	 * @return bool
	 */
	public function isSamePassword($user, $password)
	{
		return Bcrypt::validate($password, $user->password);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 */
	function deleteAccount($user)
	{
		DataMapperManager::delete($user);
		$user = NULL;
		return;
	}

	/**
	 *
	 * @param string $username
	 * @return boolean
	 */
	public function isNew($username)
	{
		$user = DataMapperManager::findBy('user', 'username', "$username");

		if ($user)
		{
			return false;
		}

		return true;
	}

	/**
	 *
	 * @param string $username
	 * @return boolean
	 */
	public function usernameExists($username)
	{
		$user = DataMapperManager::findBy('user', 'username', "$username");

		if ($user)
		{
			return true;
		}

		return false;
	}

	/**
	 * @param $email
	 * @return bool
	 * @throws \Exception
	 */
	public function emailExists($email)
	{
		if ( ! $email)
		{
			throw new \Exception("missing email");
		}

		$user = DataMapperManager::findBy('user', 'email', $email);

		return (boolean)$user;
	}

	/**
	 * @param $user
	 * @return orm\UserModel
	 * @throws \Exception
	 */
	public function createUser($user)
	{
		if ( ! $user->username)
		{
			throw new \Exception("Username is not set");
		}

		if ( ! $user->password)
		{
			throw new \Exception("Password is not set");
		}

		$validator = Validation::createValidator();

		// + validates username
		$constraints = new c\Regex(array('pattern' => '/^[\.\-_a-z0-9]+$/')) ;

		$user->username = strtolower($user->username);

		$violations = $validator->validateValue($user->username, $constraints);

		if ($violations->count() > 0)
		{
			throw new \Exception("Username {$user->username} not valid");
		}
		// -

		if ($user->idfacebook)
		{
			$userFB = $this->getUserByIdFacebook($user->idfacebook);

			if ($userFB)
			{
				throw new \Exception("User with idfacebook #{$user->idfacebook} already exists.");
			}
		}

		// + validates password
		$constraints = new c\Regex(array('pattern' => '/^[^\s]+$/')) ;

		$violations = $validator->validateValue($user->password, $constraints);

		if ($violations->count() > 0)
		{
			throw new \Exception("Password invalid");
		}
		// -

		$user->password   = Bcrypt::hash($user->password);
		$user->status     = UserModel::STATUS_ACTIVE;
		$user->lastaccess = time();
		$user->views      = 0;
		$user->likes      = 0;
		$user->shares     = 0;

		$user->firstname = Utilities::cleanString($user->firstname, array('strip_tags' => NULL, 'maxlength' => array('length' => 50)));
		$user->lastname = Utilities::cleanString($user->lastname, array('strip_tags' => NULL, 'maxlength' => array('length' => 50)));

		DataMapperManager::create($user);

		$userSearch = new orm\UserSearchModel();
		$userSearch->iduser = $user->iduser;
		$userSearch->username = $user->username;
		$userSearch->firstname =  $user->firstname;
		$userSearch->lastname = $user->lastname;

		DataMapperManager::create($userSearch);

		return $user;
	}

	/**
	 * @param $user
	 * @return orm\Model|UserModel
	 */
	public function create($user)
	{
		return $this->createUser($user);
	}

	/**
	 * recursive function
	 *
	 * @param string $str
	 * @param int $postfix
	 * @return string
	 */
	function findAvailableUsername($str, $postfix = null)
	{
		debug(__METHOD__);

		if ( ! $postfix)
		{
			// clean name
			$str = preg_replace('/[^\w]/', '', $str);
		}

		$user = DataMapperManager::findBy('dbsite.user', 'username', $str.$postfix);

		if ($user)
		{
			$postfix++;

			return self::findAvailableUsername($str, $postfix);
		}

		return $str . $postfix;
	}

	public function updateUser($user)
	{
		$userServ = serv\UserService::getInstance();


		if ( ! $user->username)
		{
			throw new \Exception("Username can not be empty");
		}

//		if ( ! $user->email)
//		{
//			throw new \Exception("Email can not be empty");
//		}

		if ( ! $user->password)
		{
			throw new \Exception("Password can not be empty");
		}

		$validator = Validation::createValidator();

		$emailConst  = new c\Email();

		$violations = $validator->validateValue($user->email, $emailConst);

		if ($violations->count() > 0)
		{
			throw new \Exception("Email is not valid.");
		}

		// + validates username
		$constraints = new c\Regex(array('pattern' => '/^[\.\-_a-z0-9]+$/')) ;

		$user->username = strtolower($user->username);

		$violations = $validator->validateValue($user->username, $constraints);

		if ($violations->count() > 0)
		{
			throw new \Exception("Username {$user->username} not valid");
		}
		// -

		// fetch the original record to check value
		DataMapperManager::disableCache();
		$dbUser = DataMapperManager::findByKey('dbsite.user', $user->iduser);
		DataMapperManager::enableCache();

		if ($user->username !== $dbUser->username)
		{
			if ($userServ->usernameExists($user->username))
			{
				throw new \Exception("Username " . $user->username . " already exists.");
			}
		}

		// password has change
		if ($user->password !== $dbUser->password)
		{

			debug('New Password ' . $user->password);

			$user->password = Bcrypt::hash($user->password); /// Encrypt password.
		}

		$user = DataMapperManager::update($user);

		$userSearch = DataMapperManager::findByKey('dbsite.usersearch', $user->iduser);

		if ( ! $userSearch)
		{
			$userSearch = new orm\UserSearchModel();
		}

		$userSearch->iduser = $user->iduser;
		$userSearch->username = $user->username;
		$userSearch->firstname = $user->firstname;
		$userSearch->lastname = $user->lastname;

		if ($userSearch->isNew())
		{
			DataMapperManager::create($userSearch);
		}
		else
		{
			DataMapperManager::update($userSearch);
		}

		return $user;
	}

	/**
	 *
	 * @param orm\UserModel $user
	 * @return orm\UserModel
	 * @throws \Exception
	 */
	public function update($user)
	{
		return $this->updateUser($user);
	}

	/**
	 *
	 * @return string
	 */
	public function helloWorld()
	{
		return "Stayzen is talking to you and saying... Hello World !";
	}

	/**
	 *
	 * @param type $user
	 * @param type $limit
	 * @param int $offset
	 * @return type
	 * @throws \Exception
	 */
	public function getPendingMovies($user, $limit = 100, $offset = NULL)
	{
		debug(__METHOD__);

		if ( ! $user)
		{
			throw new \Exception("missing user");
		}

		$movieServ = serv\MovieService::getInstance();

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		$str = "iduser:" . $user->iduser . " and status:" . orm\MovieModel::STATUS_PENDING;

		$query->setQuery($str);

		$query->addSort('created', $query::SORT_DESC);

		if ( ! $offset)
		{
			$offset = 0;
		}

		$query->setStart($offset)->setRows($limit + 1);

		list($list, $total) = $client->execute($query, true);

		$nextOffset = 0;

		if (count($list) > 1 && count($list) > $limit)
		{
			array_pop($list);

			reset($list);

			$nextOffset = $offset + $limit;
		}

//

		$movies = array();

		foreach ($list as $movieSearch)
		{
			$movie = $movieServ->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		$data = array();
		$data[] = $movies;
		$data[] = $nextOffset;
		$data[] = $total;

		return $data;
	}

	/**
	 * @param $user
	 * @param null $requester
	 * @param null $limit
	 * @param null $offset
	 * @param null $slugcampaign
	 * @return array
	 */
	public function getMovies($user, $requester = null, $limit = null, $offset = null, $slugcampaign = null)
	{
		$movieServ = MovieService::getInstance();
		$campaignServ = serv\CampaignService::getInstance();
		$campaignConfig = array();
		$campaign = NULL;
		$permission = $this->getPermissionMovieSolrQuery($user, $requester);

		$permission .= ($permission == '' ? '' : ' and ' );

		if ($slugcampaign)
		{
			$campaign = $campaignServ->getCampaignBySlug($slugcampaign);
			$campaignConfig = $campaignServ->getConfig($campaign);
		}

		if (isset($campaignConfig['movie_status_published']) && $campaignConfig['movie_status_published'])
		{
			$movieStatus = orm\MovieModel::getStaticProp($campaignConfig['movie_status_published']);
		}
		else
		{
			$movieStatus = orm\MovieModel::STATUS_ACTIVE;
		}

		$str = "{$permission}status:{$movieStatus} and iduser:{$user->iduser}";

		if ($campaign)
		{
			$str = "{$str} and idcampaign:{$campaign->idcampaign}";
		}

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		$query->setQuery($str);
		$query->addSort('publicated', $query::SORT_DESC);

		if ($offset !== null)
		{
			$query->setStart($offset)->setRows($limit);
		}
		else
		{
			$query->setStart(0)->setRows($limit);
		}

		list($list, $total) = $client->execute($query, true);

		$movies = array();

		foreach($list as $movieSearch)
		{
			$movie = $movieServ->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		return array($movies, $total);
	}

	/**
	 * @param $user
	 * @param null $limit
	 * @param null $offset
	 * @param null $slugcampaign
	 * @return array
	 */
	public function getUnlistedMovies($user, $limit = null, $offset = null)
	{
		$movieServ = MovieService::getInstance();

		$movieStatus = orm\MovieModel::STATUS_UNLISTED_PUBLISHED;

		$str = "status:{$movieStatus} and iduser:{$user->iduser}";

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		$query->setQuery($str);
		$query->addSort('publicated', $query::SORT_DESC);

		if ($offset !== null)
		{
			$query->setStart($offset)->setRows($limit);
		}
		else
		{
			$query->setStart(0)->setRows($limit);
		}

		list($list, $total) = $client->execute($query, true);

		$movies = array();

		foreach($list as $movieSearch)
		{
			$movie = $movieServ->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		return array($movies, $total);
	}

	/**
	 * @param $user1
	 * @param $user2
	 * @return string
	 */
	public function getPermissionMovieSolrQuery($user1, $user2)
	{
		$servSocial = serv\SocialService::getInstance();
		$relation = '';

		if ($user2)
		{
			$relation = $servSocial->getFriendshipStatus($user1, $user2);
		}

		switch($relation)
		{
			case 'SAME':
				$permission = "";
				break;
			case 'FRIENDS':
				$permission = "-permission:" . orm\MovieModel::PRIVATE_ . ' ';
				break;
			default:
				$permission = "permission:" . orm\MovieModel::PUBLIC_ . ' ';
				break;
		}

		return $permission;
	}

	/**
	 *
	 * @param orm\UserModel $user
	 * @return array
	 */
	public function getTimeline($user)
	{
		return DataMapperManager::findAllBy('dbsite.timeline', 'iduser', $user->iduser);
	}

	/**
	 * @param $user
	 * @param $movie
	 * @param string $recipe
	 * @return MovieModel
	 * @throws \Exception
	 */
	function addMovie($user, $movie, $recipe = null)
	{
		debug(__METHOD__);

		if ( ! $movie->isNew())
		{
			throw new \Exception(__METHOD__ . " - movie should be new");
		}

		if ( ! $movie->permission)
		{
			$movie->permission = MovieModel::PUBLIC_;
		}

		$config = array();

		if ($movie->idcampaign && $movie->idgenre)
		{
			$campaignServ = serv\CampaignService::getInstance();
			$genreServ = serv\GenreService::getInstance();

			$genre = $genreServ->get($movie->idgenre);
			$campaign = $campaignServ->getCampaignById($movie->idcampaign);
			$config = $genreServ->getConfig($genre, $campaign);
		}

		if (isset($config['movie_status_pending']) && $config['movie_status_pending'])
		{
			$movie->status = MovieModel::getStaticProp($config['movie_status_pending']);
		}
		else if ( ! $movie->status)
		{
			$movie->status = MovieModel::STATUS_PENDING;
		}

		$movieServ = MovieService::getInstance();

		$movie->iduser = $user->iduser;

		$movie = $movieServ->create($movie, $recipe);

		return $movie;
	}

	/**
	 *
	 * @param  \Stayfilm\stayzen\ORM\UserModel $user
	 * @return int
	 * @throws Exception
	 */
	function countFriends($user)
	{
		return DataMapperManager::countBy('dbsite.userfriends', 'iduser', $user->iduser);
	}

	/**
	 * @param $user
	 * @param int $limit
	 * @param array $fields
	 * @return array
	 */
	function getFriends($user, $limit = 20, $fields = NULL)
	{
		$friends = DataMapperManager::findAllBy('userfriends', 'iduser', $user->iduser, $fields, $limit);
		return $friends;
	}

	/**
	 * @return orm/UserModel
	 */
	function getRandomUser()
	{
		$users = DataMapperManager::findAll('dbsite.user', array(), 300);

		shuffle($users);

		return array_shift($users);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @return array
	 */
	function getRecommendedUsers($user)
	{
		$socialServ = SocialService::getInstance();

		$friends = $this->getFriends($user, 200, array('idfriend'));
		shuffle($friends);

		$list    = array();
		$idFriends = array();
		$values = array();
		$idRequests = array();
		$idList  = array();
		$c = 0;

		foreach($friends as $friend)
		{
			$values[] = $friend->idfriend;
		}

		if ($values)
		{
			$friends = DataMapperManager::findAllIn('dbsite.user', 'iduser', $values, array('iduser'));

			$friendsOfFriends = array();

			foreach ($friends as $friend)
			{
				$friendsOfFriendsTemp = $this->getFriends($friend, 100, array('idfriend'));

				foreach($friendsOfFriendsTemp as $f)
				{
					$friendsOfFriends[$f->idfriend] = $f;
				}
			}

			shuffle($friendsOfFriends);

			foreach ($friends as $friend)
			{
				$idFriends[] = $friend->iduser;
			}

			$idRequests = $socialServ->getMyFriendshipRequests($user);

			foreach ($friendsOfFriends as $recommendedFriend)
			{
				$friend = $this->getUserByKey($recommendedFriend->idfriend);

				if ( ! $friend)
				{
					continue;
				}

				if (in_array($friend->iduser, $idFriends) || in_array($friend->iduser, $idRequests) || in_array($friend->iduser, $idList))
				{
					continue;
				}

				if ($user->iduser == $friend->iduser)
				{
					continue;
				}

				if ($socialServ->getFriendshipStatus($user, $friend) !== 'FRIENDS')
				{
					$list[]   = $friend;
					$idList[] = $friend->iduser;
					$c++;
				}

				if ($c === 10)
				{
					break;
				}
			}
		}

		// others recomendend users
		$recommendedCount = count($list);
		$userLimit = 10;

		if ($recommendedCount < $userLimit)
		{
			$fields = array();
			$fields[] = '>token(iduser)';

			$values = array();
			$values[] = 'token(' . UUID::uuid4()->string . ')';

			$otherRecommendedFriends = array();

			try
			{
				$otherRecommendedFriends = DataMapperManager::findAllBy('dbsite.user', $fields, $values, null, 20);
				shuffle($otherRecommendedFriends);
			}
			catch(\Exception $ex)
			{
			}

			foreach ($otherRecommendedFriends as $otherRecommendedFriend)
			{
				$friend = $this->getUserByKey($otherRecommendedFriend->iduser);

				if ( ! $friend)
				{
					continue;
				}

				if (in_array($friend->iduser, $idFriends) || in_array($friend->iduser, $idRequests) || in_array($friend->iduser, $idList))
				{
					continue;
				}

				if ($user->iduser == $friend->iduser)
				{
					continue;
				}

				if ($socialServ->getFriendshipStatus($user, $friend) !== 'FRIENDS')
				{
					$list[]    = $friend;
					$idList[]  = $friend->iduser;
					$c++;
				}

				if ($c === 10)
				{
					break;
				}
			}
		}

		shuffle($list);

		return $list;
	}

	function dummy()
	{
		sleep(1);
	}

	function dummy2()
	{
		sleep(1);
	}

	/**
	 * @param $user
	 * @param array $types
	 * @param int $timestamp
	 * @return array
	 * @throws \Exception
	 */
	function getEvents($user, $types = array('notification', 'friendshiprequest'), $timestamp = NULL)
	{
		$notifServ = serv\NotificationService::getInstance();
		$socialServ = serv\SocialService::getInstance();

		if ( ! $timestamp)
		{
			throw new \Exception("Timestamp missing");
		}

		$events = array();

		if (in_array('notification', $types))
		{
			$result = $notifServ->getUserNotifications($user, $timestamp, FALSE);

			if ($result['notifications'])
			{
				$events['notifications'] = $result['notifications'];
			}
		}

		if (in_array('story', $types))
		{
			list($stories) = $socialServ->getFeed($user, 10, $timestamp, true);

			if ($stories)
			{
				$events['stories'] = $stories;
			}
		}

		if (in_array('friendshiprequest', $types))
		{
			$requests = $socialServ->getFriendshipRequests($user, NULL, $timestamp);

			if ($requests)
			{
				$events['friendshiprequests'] = $requests;
			}
		}

		return $events;
	}

	/**
	 * Check if there is a password reset request
	 *
	 * @param uuid $idtoken
	 * @return bool
	 */
	function getPasswordRequest($idtoken)
	{
		$passServ = serv\PasswordRecoverService::getInstance();
		$passModel = $passServ->getRequest($idtoken);
		return $passModel;
	}

	/**
	 * @param $user
	 * @return array
	 */
	function getFriendshipRequests($user)
	{
		$fields = array();
		$fields[0] = 'iduser';
		//$fields[1] = '>friendshiprequestcreated';

		$values = array();
		$values[0] = $user->iduser;
		//$values[1] = time() - 604800;

		return DataMapperManager::findAllBy('dbsite.friendshiprequestcore', $fields, $values);
	}

	/**
	 * @param $user
	 */
	function updateLastAccess($user)
	{
		$user->lastaccess = \time();

		DataMapperManager::update($user);
	}

	/**
	 * @param $user
	 */
	function setViewTour($user)
	{
		DataMapperManager::update($user);
	}

	/**
	 * @param $user
	 * @param $requester
	 * @param $offset
	 * @param $limit
	 * @return array
	 * @throws \Exception
	 */
	public function getLikes($user, $requester, $offset, $limit, $direction = 'down')
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user.');
		}

		if ($offset === "")
		{
			throw new \Exception('Missing offset.');
		}

		if ( ! $limit)
		{
			throw new \Exception('Missing limit.');
		}

		if ($direction !== 'up' && $direction !== 'down')
		{
			throw new \Exception('Invalid direction.');
		}

		$direction = $direction === 'down' ? '<' : '>';

		$nextOffset     = $offset && $direction === $this->directionDown ? $offset : 0;
		$previousOffset = $offset && $direction === $this->directionDown ? $offset : 0;
		$limit          = (int)$limit;
		$offset         = (int)$offset;
		$likes          = array();

		$movieServ       = MovieService::getInstance();
		$securityManager = zen\SecurityManager::getInstance();

		do
		{
			$fields   = array();
			$fields[] = 'iduser';

			$values   = array();
			$values[] = $user->iduser;

			if ($offset)
			{
				$fields[] = $direction . 'likeupdated';
				$values[] = $offset;
			}

			$movieLikes = DataMapperManager::findAllBy('dbsite.userlike', $fields, $values, array(), $limit + 1, 'likeupdated DESC');

			if (count($movieLikes) > 0)
			{
				$previousOffset = $previousOffset === 0 || $movieLikes[0]->likeupdated > $previousOffset ? $movieLikes[0]->likeupdated : $previousOffset;
			}

			foreach ($movieLikes as $movieLike)
			{
				$movie = $movieServ->get($movieLike->idmovie);

				if ( ! $movie)
				{
					continue;
				}

				$nextOffset = $movieLike->likeupdated;
				$movie->addData('likeDate', $movieLike->likeupdated);

				try
				{
					$securityManager->checkPermission($requester, $movie);

					$likes[] = $movie;
				}
				catch(\Exception $ex)
				{
					continue;
				}

				if (count($likes) === $limit)
				{
					break;
				}
			}

			// consegui pegar menos mas a quantidade de filmes que peguei foi $limit +1, então significa que tem mais filmes e posso fazer
			// uma outra busca pois não chegou ao fim da tabela.
			if (count($likes) < $limit && count($movieLikes) === $limit + 1)
			{
				$finished = FALSE;

				if ($direction === $this->directionDown)
				{
					$offset = $previousOffset;
				}
				else
				{
					$offset = $nextOffset;
				}
			}
			else if (count($movieLikes) < $limit + 1)
			{
				$finished = TRUE;

				break;
			}
			else if (count($likes) === $limit)
			{
				$finished = FALSE;

				break;
			}
		} while (TRUE);

		$result = array();
		$result[] = $likes;
		$result[] = $finished ? 0 : $nextOffset;
		$result[] = $finished;
		$result[] = $previousOffset;

		return $result;
	}

	/**
	 * @param $users
	 * @param $userCited
	 * @param $movie
	 */
	public function quoted($users, $userCited, $movie)
	{
		$this->fire('user-quoted', array('users' => $users, 'userCited' => $userCited, 'movie' => $movie));
	}


	/**
	 *
	 * @param $config
	 * @throws \Exception
	 */
	public function setConfig($config)
	{
		if ( ! $config)
		{
			throw new \Exception('Missing config parameter.');
		}

		$config->value = json_encode($config->value);

		DataMapperManager::create($config);
	}

	/**
	 * @param $user
	 * @return mixed
	 */
	public function getEmailConfig($user)
	{
		$value = $this->getConfigValue($user, 'Email');

		return $value ? array_values($value) : array();
	}

	/**
	 * @param $user
	 * @param $value
	 */
	public function setEmailConfig($user, $value)
	{
		$this->addConfigItem($user, 'Email', $value);
	}

	/**
	 * @param $user
	 * @param $key
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function getConfigValue($user, $key)
	{
		if( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		if( ! $key)
		{
			throw new \Exception('Missing key parameter.');
		}

		$selectFields = array();
		$selectFields[] = 'iduser';
		$selectFields[] = 'key';
		$selectFields[] = 'value';

		$model = DataMapperManager::findByKey('dbsite.userconfig', array($user->iduser, $key), $selectFields);

		if ( ! $model || $model->value === NULL)
		{
			return NULL;
		}

		return $model->value;
	}

	/**
	 * @param $user
	 * @param $key
	 * @param $value
	 * @return orm\UserConfigModel
	 */
	public function addConfigItem($user, $key, $value)
	{
		$userConfig = new orm\UserConfigModel();
		$userConfig->iduser = $user->iduser;
		$userConfig->key    = $key;

		$userConfig->value = $value;

		DataMapperManager::create($userConfig);

		return $userConfig;
	}

	/**
	 * @param $user
	 * @param $key
	 */
	public function removeConfigItem($user, $key)
	{
		$userConfig = DataMapperManager::findByKey('dbsite.userconfig', array($user->iduser, $key));

		if ($userConfig)
		{
			DataMapperManager::delete($userConfig);
		}

		return;
	}

	/**
	 * @param $user
	 * @param $sn
	 * @throws \Exception
	 */
	public function deleteUserToken($user, $sn)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user.');
		}

		if ( ! $sn)
		{
			throw new \Exception('Missing sn.');
		}

		$values = array();
		$values[] = $user->iduser;
		$values[] = $sn;

		$usertoken = DataMapperManager::findByKey('dbsite.usertoken', $values);

		if ($usertoken)
		{
			DataMapperManager::delete($usertoken);
		}
		else
		{
			throw new \Exception('User token does not exist.');
		}
	}

	/**
	 * @param $user
	 * @param $sn
	 * @throws \Exception
	 */
	public function deleteUserMedias($user)
	{
		$fieldsAlbum = array();
		$fieldsAlbum[] = 'iduser';
		$fieldsAlbum[] = 'idalbum';

		$fieldsMedia = array();
		$fieldsMedia[] = 'idmidia';

		$albuns = orm\DataMapperManager::findAllBy('dbstay.album', 'iduser', $user->iduser, $fieldsAlbum, 100000);
		$medias = orm\DataMapperManager::findAllBy('dbstay.midia', 'iduser', $user->iduser, $fieldsMedia, 100000);

		foreach ($albuns as $album)
		{
			$fieldsMedia2Album = array();
			$fieldsMedia2Album[] = 'idalbum';

			$media2album = orm\DataMapperManager::findAllBy('dbstay.media2album', 'idalbum', $album->idalbum, $fieldsMedia2Album, null);

			if ( $media2album )
			{
				orm\DataMapperManager::deleteByKey('dbstay', 'media2album', 'idalbum', $album->idalbum);
			}

		}

		foreach ($medias as $media)
		{
			orm\DataMapperManager::deleteByKey('dbstay', 'album2media', 'idmidia', $media->idmidia);
		}

		orm\DataMapperManager::deleteByKey('dbstay', 'user', 'iduser', $user->iduser);
		orm\DataMapperManager::deleteByKey('dbstay', 'album', 'iduser', $user->iduser);
		orm\DataMapperManager::deleteByKey('dbstay', 'midia', 'iduser', $user->iduser);
		orm\DataMapperManager::deleteByKey('dbstay', 'useralbum', 'iduser', $user->iduser);
		orm\DataMapperManager::deleteByKey('dbstay', 'jobsn', 'iduser', $user->iduser);
	}
	/**
	 * @param $group
	 * @param $user
	 * @param null $offset
	 * @param null $limit
	 * @return array|type
	 */
	public function getNotification($group, $user, $offset = NULL, $limit = NULL)
	{
		$notifServ = serv\NotificationService::getInstance();
		$movieServ = serv\MovieService::getInstance();
		$userServ  = serv\UserService::getInstance();

		$allNofitications = array();

		$limit  = $limit ? $limit : 6;

		$result = $notifServ->getUserNotifications($user, $offset, TRUE, $limit);
		$notifications = $result['notifications'];
		$offset        = $result['newOffset'];

		$c = $limit;

		$newSearch = FALSE;

		$countNotifications = $notifServ->countUserNotifications($user, FALSE);

		if ($group)
		{
			do
			{
				if ($newSearch)
				{
					$result = $notifServ->getUserNotifications($user, $offset, $limit);
					$notifications = $result['notifications'];
					$offset        = $result['newOffset'];
					$c            += $limit;
				}

				foreach ($notifications as $notification)
				{
					switch($notification->notiftype)
					{
						case 'movie-like':
							$liker = $userServ->getUserByKey($notification->data['idliker']);

							if ( ! $liker)
							{
								continue;
							}

							$movie = $movieServ->get($notification->data['idmovie']);

							if ( ! $movie)
							{
								continue;
							}

							$idmovie = $notification->data['idmovie'];
							$allNofitications['movielike'][$idmovie][] = $notification;
							break;
						case 'movie-comment':
							$commentator = $userServ->getUserByKey($notification->data['idcommentator']);

							if ( ! $commentator)
							{
								continue;
							}

							$movie = $movieServ->get($notification->data['idmovie']);

							if ( ! $movie)
							{
								continue;
							}

							$idmovie = $notification->data['idmovie'];
							$allNofitications['moviecomment'][$idmovie][] = $notification;
							break;
						case 'movie-shared':
							$sharer = $userServ->getUserByKey($notification->data['idsharer']);

							if ( ! $sharer)
							{
								continue;
							}

							$movie = $movieServ->get($notification->data['idmovie']);

							if ( ! $movie)
							{
								continue;
							}

							$idmovie = $notification->data['idmovie'];
							$allNofitications['movieshare'][$idmovie][] = $notification;
							break;
						case 'friendship-accepted':
							$friend = $userServ->getUserByKey($notification->data['iduser']);

							if ( ! $friend)
							{
								continue;
							}

							$allNofitications['friendshipaccepted'] = $notification;
							break;
						case 'friendship-rejected':
						case 'testtype':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'user-quoted':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'movie-created':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'movie-approved':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'movie-reproved':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'friend-registered':
							$allNofitications['normalnotif'][] = $notification;
							break;
						case 'friendship-request':
							$allNofitications['normalnotif'][] = $notification;
							break;
						default:

							if (zen\Application::$config->show_unknown_notifs)
							{
								$allNofitications['$unknowntype'][] = $notification;
							}
							// else ignore

							break;
					}
				}

				$notifsTotal = (isset($allNofitications['unknowntype'])         ? 1                                              : 0) +
								(isset($allNofitications['movielike'])          ? count($allNofitications['movielike'])          : 0) +
								(isset($allNofitications['normalnotif'])        ? count($allNofitications['normalnotif'])        : 0) +
								(isset($allNofitications['moviecomment'])       ? count($allNofitications['moviecomment'])       : 0) +
								(isset($allNofitications['movieshare'])         ? count($allNofitications['movieshare'])         : 0) +
								(isset($allNofitications['friendshipaccepted']) ? 1                                              : 0);

				$newSearch   = TRUE;

				if (count($allNofitications) === $limit)
				{
					break;
				}

				// verify the amount of registers in notification table to don't loop eternally
				if ($c >= $countNotifications)
				{
					break;
				}

			} while ($notifsTotal < $limit);

			$result = array();
			$result[] = $allNofitications;
			$result[] = $offset;
			return $result;
		}

		$result = array();
		$result[] = $notifications;
		$result[] = $offset;
		return $result;
	}

	public function countPendingMovies($user)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user.');
		}

		$field = array();
		$field[] = 'iduser';
		$field[] = 'status';

		$value = array();
		$value[] = $user->iduser;
		$value[] = MovieModel::STATUS_PENDING;

		return DataMapperManager::countBy('dbsite.moviesearch', $field, $value);
	}

	public function getUserCampaign($user)
	{
		$campaignServ = serv\CampaignService::getInstance();
		$campaignslug = $this->getConfigValue($user, 'campaignslug');

		if ($campaignslug)
		{
			return $campaignServ->getCampaignBySlug($campaignslug);
		}

		return;
	}

	/**
	 *
	 * @param type $user
	 * @return type
	 * @throws \Exception
	 */
	public function countLikes($user)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		return DataMapperManager::countBy('dbsite.userlike', 'iduser', $user->iduser);
	}

	/**
	 *
	 * @param type $user
	 * @return int
	 * @throws \Exception
	 */
	public function countNotifications($user)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		$field = array();
		$field[] = 'iduser';

		$value = array();
		$value[] = $user->iduser;

		$notifications = DataMapperManager::findAllBy('dbsite.notificationcore', $field, $value, array('status'), NULL);

		$c = 0;

		foreach ($notifications as $notification)
		{
			if ($notification->status === 0)
			{
				$c++;
			}
		}

		return $c;
	}

	public function updateCacheFields($user)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user parameter.');
		}

		$countFriends       = $this->countFriends($user);
		$countLikes         = $this->countLikes($user);
		$countNotifications = $this->countNotifications($user);

		$user->friends       = $countFriends;
		$user->likes         = $countLikes;
		$user->notifications = $countNotifications;

		$this->updateUser($user);
	}

	public function incrementMovie($movie)
	{
		$userService = serv\UserService::getInstance();

		$user = $userService->get($movie->iduser);

		$movieCount = $userService->getConfigValue($user, 'movie_count');

		if ( ! $movieCount)
		{
			$userService->addConfigItem($user, 'movie_count', '1');
		}
		else
		{
			++$movieCount;
			$userService->addConfigItem($user, 'movie_count', "$movieCount");
		}
	}

	public function decrementMovie($movie)
	{
		$userService = serv\UserService::getInstance();

		$user = $userService->get($movie->iduser);

		$movieCount = $userService->getConfigValue($user, 'movie_count');

		if ($movieCount)
		{
			--$movieCount;
			$userService->addConfigItem($user, 'movie_count', "$movieCount");
		}
		else
		{
			$userService->addConfigItem($user, 'movie_count', '0');
		}
	}


	/**
	 * @param UserModel $user
	 * @param $iddevice
	 */
	public function addDevice(orm\UserModel $user, $iddevice)
	{
		$userdevice = new orm\UserDeviceModel();
		$userdevice->iduser   = $user->iduser;
		$userdevice->iddevice = $iddevice;

		DataMapperManager::create($userdevice);

		$deviceuser = new orm\DeviceUserModel();
		$deviceuser->iddevice = $userdevice->iddevice;
		$deviceuser->iduser   = $userdevice->iduser;

		DataMapperManager::create($deviceuser);
	}

	/**
	 *
	 * @param type $iddevice
	 * @throws \Exception
	 */
	public function removeDevice($iddevice)
	{
		if ( ! $iddevice)
		{
			throw new \Exception('Missing iddevice parameter.');
		}

		$deviceuser = DataMapperManager::findByKey('dbsite.deviceuser', array($iddevice));

		if ( ! $deviceuser)
		{
			return;
		}

		$fields = array();
		$fields[] = 'iduser';

		$values = array();
		$values[] = $deviceuser->iduser;

		$userdevices = DataMapperManager::findAllBy('dbsite.userdevice', $fields, $values);

		foreach ($userdevices as $userdevice)
		{
			DataMapperManager::delete($userdevice);
		}

		DataMapperManager::delete($deviceuser);
	}
}
