<?php

use Stayfilm\stayzen\services\UserService;
use Stayfilm\stayzen\services\MovieService;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\ORM\UserModel;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM\MovieModel;
use Stayfilm\stayzen\ORM\DataMapperManager;


// use Stayfilm\stayzen\utilities;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class UserServiceTest extends PHPUnit_Framework_TestCase
{

	public static function setUpBeforeClass()
	{
		DataMapperManager::truncateTables('dbsite',  array('user', 'userfriends', 'timeline', 'movie',
			'gallery', 'friendshiprequest', 'notification', 'userconfig', 'notificationcore'));
	}

	protected function tearDown()
    {
        serv\Service::disablePermission();
    }

	public function testCreateUser()
	{
		$userServ = UserService::getInstance();

		$user = new UserModel();
		$user->username = 'test.test_test-test123';
		$user->firstname = 'John';
		$user->lastname = 'Dalton';
		$user->password = '123456';
		$user->photo = 'http://example.com/photo.jp';
		$user->birthday = 1369867946;
		$user->gender = 1;
		$user->country = 'BR';
		$user->city = "São Paulo";
		$user->locale = "pt_BR";
		$user->languages = 'pt_BR, fr_FR';

		$this->assertTrue($user->isNew());

		$user = $userServ->createUser($user);

		$this->assertTrue($user->isSync());

		$user->firstname = "Alfred";

		$this->assertTrue($user->isDirty());

		$this->assertEquals('test.test_test-test123', $user->username);
		$this->assertEquals(1369867946, $user->birthday);

		$this->assertEquals('Alfred Dalton', $user->getPrettyName());

		return $user->iduser;
	}

	public function testCleanString()
	{
		$userServ = UserService::getInstance();
		$user = new UserModel();
		$user->username = 'test.test_test-test123';
		$user->firstname = '<br>John<br />';
		$user->lastname = 'abcdefghijklmnopdpdodoooddddddsjdqsjhfkjhkjsdqhfkjdsqhfgkljqsdghfkljsqdgfklhqsdg' .
				'fdljhqsjfhdsqlkfhlksqdhfljqsdhfljmsqdhgljsqhglmjfsqdhgljfqhgljqhgljqsfhgljqhsfgllgfhqsjlghsjlghs' .
				'fdljhqsjfhdsqlkfhlksqdhfljqsdhfljmsqdhgljsqhglmjfsqdhgljfqhgljqhgljqsfhgljqhsfgllgfhqsjlghsjlghs';
		$user->password = '123456';

		$userServ->createUser($user);

		$this->assertEquals(50, strlen($user->lastname));
		$this->assertEquals('John', $user->firstname);
	}

	/**
	 * @depends testCreateUser
	 */
	public function testGetUser($userId)
	{
		$userServ = UserService::getInstance();

		$user = $userServ->getUserByKey($userId);

		$this->assertEquals('test.test_test-test123', $user->username);
		$this->assertEquals(1369867946, $user->birthday);
	}

	public function testAuthenticate()
	{
		$userServ = UserService::getInstance();

		$user = new UserModel();
		$user->username = 'lucasdaveis';
		$user->password = '123456';

		$userServ->create($user);

		$userId = $user->iduser;

		$res = $userServ->authenticate('lucasdaveis', '123456');

		$this->assertTrue($res);

		//test wrong password
		$res = $userServ->authenticate('lucasdaveis', '1111');

		$this->assertFalse($res);

		$user = $userServ->get($userId);

		$this->assertEquals('lucasdaveis', $user->username);

		$user->password = 'qwerty';

		$userServ->update($user);

		$res = $userServ->authenticate('lucasdaveis', 'qwerty');

		$this->assertTrue($res);
	}

	function testGetMovie()
	{
		$this->markTestSkipped("Solr test are unreliable");

		$userServ = UserService::getInstance();
		$movieServ = MovieService::getInstance();

		$user = new UserModel();
		$user->username = "toto";
		$user->password = "123";
		$userServ->createUser($user);

		$data = array();
		$data[] = array("starwars");
		$data[] = array("Jack leventerur");
		$data[] = array("Indiana Jones");

		foreach ($data as $row) {
			$movie = new MovieModel();
			$movie->title = $row[0];
			$movie->status = orm\MovieModel::STATUS_ACTIVE;
			sleep(1);
			$userServ->addMovie($user, $movie);
		}

		sleep(3);
		list($movies) = $userServ->getMovies($user);

		$this->assertTrue(is_array($movies));
		$this->assertEquals(3, count($movies));
	}

	function testUsernameCreation()
	{
		$userServ = UserService::getInstance();
		$movieServ = MovieService::getInstance();

		$user = new UserModel();
		$user->username = "toto";
		$user->password = "123";
		$userServ->createUser($user);

		$user1 = new UserModel();
		$user1->username = "toto1";
		$user1->password = "123";
		$userServ->createUser($user1);

		$user2 = new UserModel();
		$user2->username = "toto2";
		$user2->password = "123";
		$userServ->createUser($user2);

		$username = $userServ->findAvailableUsername('toto');

		$this->assertEquals('toto3', $username);

		$username = $userServ->findAvailableUsername('-toto_titi+t.est!');

		$this->assertEquals('toto_tititest', $username);
	}

	function testDeleteUser()
	{
		$userServ = UserService::getInstance();
		$user = new UserModel();
		$user->username = "toto99";
		$user->password = "123";
		$user = $userServ->createUser($user);

		$uuid = $user->iduser;

		$userServ->deleteAccount($user);

		$user = $userServ->getUserByKey($uuid);

		$this->assertNull($user);
	}


	function testGetPendingMovies()
	{
		$this->markTestSkipped("Solr test are unreliable");

		$userServ = UserService::getInstance();

		// CREATE USER User
		$user = new UserModel();
		$user->username = 'user';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$movie = new MovieModel();
		$movie->title = 'Starwars';
		$userServ->addMovie($user, $movie);

		$movie = new MovieModel();
		$movie->title = 'Starwars 2';
		$userServ->addMovie($user, $movie);

		list($movies) = $userServ->getPendingMovies($user, 2);

		$this->assertEquals(2, count($movies));
	}

	function testEvents()
	{
		$userServ = serv\UserService::getInstance();
		$notifServ = serv\NotificationService::getInstance();
		$timelineServ = serv\TimelineService::getInstance();

		$user = new UserModel();
		$user->username = 'user';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$ts = time();

		sleep(1);

		$notif = $notifServ->create($user, 'testnotif', 'useless');

		sleep(1);

		$ts2 = time();

		$events = $userServ->getEvents($user, array('notification', 'story'), $ts);

		$this->assertEquals(1, count($events['notifications']));

		$events = $userServ->getEvents($user, array('notification', 'story'), $ts2);
		$this->assertFalse(isset($events['notifications']));

		sleep(1);
		$notif = $notifServ->create($user, 'testnotif', 'useless');
		sleep(1);
		$notif = $notifServ->create($user, 'testnotif', 'useless');

		sleep(1);

		$events = $userServ->getEvents($user, array('notification', 'story'), $ts);
		$this->assertEquals(3, count($events['notifications']));

		$movie = new MovieModel();
		$movie->title = "Starwars 2";
		$userServ->addMovie($user, $movie);

		$timelineServ->add($user, 'movie', array('movie' => $movie));

		$events = $userServ->getEvents($user, array('notification', 'story'), $ts);

		$this->assertEquals(1, count($events['stories']));

		$events = $userServ->getEvents($user, array('notification', 'story'), $ts2);

		$this->assertEquals(2, count($events['notifications']));
	}

	function testIncrementView()
	{
		$userServ = serv\UserService::getInstance();
		$user = new UserModel();
		$user->username = 'user';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$this->assertEquals(0, $user->views);

		$userServ->incrementView($user);

		$this->assertEquals(1, $user->views);

		$userServ->incrementView($user);

		$this->assertEquals(2, $user->views);
	}

	function testupdateLastAccess()
	{
		$userServ = serv\UserService::getInstance();
		$user = new UserModel();
		$user->username = 'userTestUpdateLastAccess';
		$user->password = '123456';
		$user = $userServ->createUser($user);
		$lastAccess = $user->lastaccess;

		sleep(1);

		$userServ->updateLastAccess($user);

		$this->assertNotEquals($lastAccess, $user->lastaccess);
	}

	function testEmailConfig()
	{
		$userServ = serv\UserService::getInstance();
		$user = new UserModel();
		$user->username = 'lucas';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$value = array();
		$value[] = 'MovieComment';

		$userServ->setEmailConfig($user, $value);

		$config = $userServ->getEmailConfig($user);

		$this->assertEquals('MovieComment', $config[0]);

		$this->assertEquals(1, count($config));

		$userConfig = new orm\UserConfigModel();
		$userConfig->iduser = $user->iduser;
		$userConfig->key    = 'Email';

		$config[] = 'Denounced';
		$userConfig->value  = json_encode($value);

		$userServ->setConfig($userConfig);

		$this->assertEquals('MovieComment', $config[0]);
		$this->assertEquals('Denounced', $config[1]);

		$this->assertEquals(2, count($config));
	}

	function testUserConfig()
	{
		$userServ = serv\UserService::getInstance();
		$user = new UserModel();
		$user->username = 'lucas';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$userServ->addConfigItem($user, 'testconfig', 15);

		$value = $userServ->getConfigValue($user, 'testconfig');

		$this->assertEquals(15, $value);

		$userServ->removeConfigItem($user, 'testconfig');

		$value = $userServ->getConfigValue($user, 'testconfig');

		$this->assertNull($value);
	}

	function testDeleteUserToken()
	{
		$userServ = serv\UserService::getInstance();
		$user = new UserModel();
		$user->username = 'lucas';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$userToken = new orm\UserTokenModel();
		$userToken->iduser = $user->iduser;
		$userToken->socialnetwork = 'facebook';
		$userToken->accesstoken = 'lalalalalalalalalala';
		$userToken->expire = 1385223689;
		$userToken->uid = 646111546;

		DataMapperManager::create($userToken);

		$values = array();
		$values[] = $user->iduser;
		$values[] = 'facebook';

		$token = DataMapperManager::findByKey('dbsite.usertoken', $values);

		$this->assertNotEmpty($token);

		$userServ->deleteUserToken($user, 'facebook');

		$values = array();
		$values[] = $user->iduser;
		$values[] = 'facebook';

		$token = DataMapperManager::findByKey('dbsite.usertoken', $values);

		$this->assertEmpty($token);
	}

	function testUserWithFacebookID()
	{
		$userServ = serv\UserService::getInstance();

		$user1 = new UserModel();
		$user1->username   = 'Julien';
		$user1->password   = '123456';
		$user1->idfacebook = '123456';

		$userServ->createUser($user1);

		$user2 = new UserModel();
		$user2->username   = 'Lucas';
		$user2->password   = '123456';
		$user2->idfacebook = '123456';

		try
		{
			$userServ->createUser($user2);
		}
		catch(\Exception $ex)
		{
			$this->assertTrue(TRUE);

			return;
		}

		$this->assertTrue(FALSE);
	}

	function testGetUserSecurity()
	{
		$userServ   = serv\UserService::getInstance();
		$socialServ = serv\SocialService::getInstance();

		$julien = new UserModel();
		$julien->username = 'Julien';
		$julien->email = 'julien@fff.com';
		$julien->password = '123456';
		$julien->city = 'Guerande';
		$userServ->createUser($julien);

		$lucas = new UserModel();
		$lucas->username = 'Lucas';
		$lucas->email = 'lucas@fff.com';
		$lucas->password = '123456';
		$userServ->createUser($lucas);

		$securityM = zen\SecurityManager::getInstance();

		$fields1 = $securityM->getAllowedFields($julien, $lucas);

		$this->assertTrue( ! array_key_exists('email', $fields1));

		DataMapperManager::disableCache();

		serv\Service::enablePermission();
		serv\Service::setRequester($lucas);

		$user1 = $userServ->get($julien->iduser);

		$exists1 = $user1->email ? TRUE : FALSE;

		$this->assertTrue( ! $exists1);

		$socialServ->createFriendship($julien, $lucas);

		$user2 = $userServ->get($julien->iduser);

		$exists2 = $user2->email ? TRUE : FALSE;

		$this->assertTrue( ! $exists2);

		$user3 = $userServ->get($lucas->iduser);

		$exists3 = $user3->email ? TRUE : FALSE;

		$this->assertTrue($exists3);

		$exists4 = $user3->password ? TRUE : FALSE;

		$this->assertTrue( ! $exists4);
	}

	public function testCounts()
	{
		$socialServ = serv\SocialService::getInstance();
		$userServ   = serv\UserService::getInstance();
		$notifServ  = serv\NotificationService::getInstance();
		$movieServ  = serv\MovieService::getInstance();

		$user1 = new orm\UserModel();
		$user1->username = 'user1';
		$user1->password = '123456';

		$userServ->create($user1);

		$user2 = new orm\UserModel();
		$user2->username = 'user2';
		$user2->password = '123456';

		$userServ->create($user2);

		$countNotificationsUser1 = $userServ->countNotifications($user1);
		$this->assertEquals(0, $countNotificationsUser1);

		$countFriendsUser1 = $userServ->countFriends($user1);
		$this->assertEquals(0, $countFriendsUser1);

		$countFriendsUser2 = $userServ->countFriends($user1);
		$this->assertEquals(0, $countFriendsUser2);

		$socialServ->createFriendship($user1, $user2);

		$countFriendsUser1 = $userServ->countFriends($user1);
		$this->assertEquals(1, $countFriendsUser1);

		$countFriendsUser2 = $userServ->countFriends($user1);
		$this->assertEquals(1, $countFriendsUser2);

		$notifServ->create($user1, 'testnotif', 'useless1');

		$countNotificationsUser1 = $userServ->countNotifications($user1);

		$this->assertEquals(1, $countNotificationsUser1);


		$countLikesUser1 = $userServ->countLikes($user1);

		$this->assertEquals(0, $countLikesUser1);

		$movie = new orm\MovieModel();
		$movie->title = "Test1";
		$movie->iduser = $user1->iduser;

		$movieServ->create($movie);

		$movieServ->addLike($movie, $user1);

		$countLikesUser1 = $userServ->countLikes($user1);

		$this->assertEquals(1, $countLikesUser1);

		$user1->friends       = 0;
		$user1->notifications = 0;
		$user1->likes         = 0;

		$userServ->update($user1);

		$user3 = $userServ->getUserByUsername('user1');

		$this->assertEquals(0, $user3->friends);
		$this->assertEquals(0, $user3->notifications);
		$this->assertEquals(0, $user3->likes);

		$userServ->updateCacheFields($user3);

		$user4 = $userServ->getUserByUsername('user1');

		$this->assertEquals(1, $user4->friends);
		$this->assertEquals(1, $user4->notifications);
		$this->assertEquals(1, $user4->likes);
	}
}
