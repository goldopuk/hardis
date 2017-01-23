<?php

use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services AS serv;
/**
 * @extends PHPUnit_Framework_TestCase
 */
class TimelineServiceTest extends PHPUnit_Framework_TestCase {

	static function setUpBeforeClass() 
	{
		orm\DataMapperManager::truncateTables('dbsite', array('timeline', 'user', 'movie'));
	}
	
	function testAdd() {
		
		$userServ = serv\UserService::getInstance();
		$movieServ = serv\MovieService::getInstance();
		$timelineServ = serv\TimelineService::getInstance();
		$socialServ = serv\SocialService::getInstance();
		
		$user = new orm\UserModel();
		$user->username = "toto";
		$user->password = "123";
		$userServ->createUser($user);
		
		$friend = new orm\UserModel();
		$friend->username = "tata";
		$friend->password = "123";
		$userServ->createUser($friend);
		
		$timelineServ->add($user, 'friendship', array('friend' => $friend), $friend->iduser);
		
		$timelines = $userServ->getTimeline($user);
		
		$this->assertEquals(1, count($timelines));
		
		$movie = new orm\MovieModel();
		$movie->title = 'movie';
		$movie->iduser = $user->iduser;
		$movie->status = orm\MovieModel::STATUS_ACTIVE;
		$movie->permission = orm\MovieModel::PUBLIC_;
		
		sleep(1);
		$movieServ->create($movie);
		
		$timelines = $userServ->getTimeline($user);
		
		$this->assertEquals(2, count($timelines));
		
		sleep(1);
		
		$socialServ->createFriendship($user, $friend);
		
		$timelines = $userServ->getTimeline($user);
		
		$this->assertEquals(3, count($timelines));
		
		$timelines = $userServ->getTimeline($friend);
		
		$this->assertEquals(1, count($timelines));
	}
}