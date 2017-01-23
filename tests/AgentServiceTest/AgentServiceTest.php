<?php
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\services\AgentService;
use Stayfilm\stayzen\services\UserService;
use Stayfilm\stayzen\services\JobService;
use Stayfilm\stayzen\ORM\UserModel;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\ORM\MovieModel;
use Guzzle\Http\Client;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class AgentServiceTest extends PHPUnit_Framework_TestCase
{

	static function setUpBeforeClass()
	{
		orm\DataMapperManager::truncateTables('dbsite', array('job', 'user', 'movie', 'timeline', 'gallery', 'meliesinfo'));
	}

	public function testSelector()
	{
		$this->markTestSkipped('Component test');
		$userId = "66da8600-b440-11e2-9e96-0800200c9a66";
		$theme = 0;
		$networks = array('facebook');
		$title = 'Just a film title';
		$hints = array('party', 'dog');
		$albums = array();
		$genre = 1;
		$subtheme = "1234";

		$agentServ = AgentService::getInstance();

		$result = $agentServ->selector($userId, $networks, $theme, $subtheme, $genre, $albums, $hints,  $title);

		$this->assertTrue(array_key_exists('job_key', $result));
		$this->assertTrue(array_key_exists('json', $result));

	}

	public function testConnection()
	{
		$this->markTestSkipped('Component test');
		$agentServ = AgentService::getInstance();
		$result = $agentServ->ping('selector');
		$this->assertTrue($result);

		$agentServ = AgentService::getInstance();
		$result = $agentServ->ping('socialnetwork');
		$this->assertTrue($result);
	}


	public function testSocialNetwork()
	{
		$this->markTestSkipped('Component test');
		//$this->markTestSkipped();
		$agentServ = AgentService::getInstance();

		$userId = '12345678-1234-1234-1234-000000000206';
		$snUserId = "123";
		$snToken = "qwert";
		$network = "facebook";
		$returnUrl = null;

		$job = $agentServ->_socialNetwork($network, $userId, $snToken, $snUserId, $returnUrl);

		$this->assertEquals('socialnetwork', $job->jobtype);
	}

	public function testTimelineCallback()
	{
		$this->markTestSkipped('Skipping timeline test callback.');
		$user = new \Stayfilm\stayzen\ORM\UserModel();
		$user->username = "toto";
		$user->password = "123";

		$userServ = Stayfilm\stayzen\services\UserService::getInstance();

		$user = $userServ->createUser($user);

		$job = new Stayfilm\stayzen\ORM\JobModel();
		$job->jobtype = 'timeline';
		$job->iduser = $user->iduser;
		$job->data = array('permission' => \Stayfilm\stayzen\ORM\MovieModel::PUBLIC_, 'title' => 'Starwars');

		$jobServ = Stayfilm\stayzen\services\JobService::getInstance();
		$job = $jobServ->create($job);

		$json = array();
		$json['code'] = 0;
		$json['message'] = 'un message';
		$json['duration'] = '100';
		$json['idjob'] = $job->idjob;
		$json['genre'] = 2;
		$json['theme'] = 2;
		$json['subtheme'] = 2;
		$json['title'] = 'Untitled';
		$json['guid'] = phpcassa\UUID::uuid4()->string;
		$json['recipe'] = 'recipe';
		$json['permission'] = orm\MovieModel::PUBLIC_;
		$json['return_url'] = NULL;
		$json['video_url'] = 'jfhskaj';
		$json['idtemplate'] = 3;

		$agentServ = AgentService::getInstance();

		$movie = $agentServ->timelineCallback($json);

		$this->assertEquals($user->iduser, $movie->iduser);
		$this->assertEquals('Untitled', $movie->title);
		$this->assertEquals(\Stayfilm\stayzen\ORM\MovieModel::PUBLIC_, $movie->permission);
	}

	public function testTimeline()
	{
		$this->markTestSkipped('Skipping timeline test callback.');
		$userServ = UserService::getInstance();

		$user = new UserModel();
		$user->username = 'bob';
		$user->password = '123456';
		$user = $userServ->createUser($user);

		$data = array();
		$data['code'] = 0;
		$data['message'] = 'this is the message';
		$data['idtheme']    = "e13eb630-aec5-11e2-9e96-0800200c9a66";
		$data['idsubtheme'] = "fcb1fe90-aec5-11e2-9e96-0800200c9a66";
		$data['idgender']   = "00e03ef0-aec6-11e2-9e96-0800200c9a66";
		$data['title'] = "La grande vadrouille";
		$data['return_url'] = "La grande vadrouille";
		$data['guid'] = phpcassa\UUID::uuid4()->string;
		$data['recipe'] = 'the recipe';
		$data['duration'] = 180;
		$data['json'] = null;

		$permission = MovieModel::FRIEND;

		$agentServ = AgentService::getInstance();

		$job = $agentServ->timeline($data, $user, $data['idtheme'], $data['idsubtheme'], $permission, NULL);

		$jobServ = JobService::getInstance();

		$job = $jobServ->get($job->idjob);

		$this->assertEquals($permission, $job->data['sentdata']['genre']);
	}

	public function testSocialNetworkCallback()
	{
		$userServ = Stayfilm\stayzen\services\UserService::getInstance();
		$jobServ = Stayfilm\stayzen\services\JobService::getInstance();
		$agentServ = AgentService::getInstance();

		// CREATE USER
		$user = new \Stayfilm\stayzen\ORM\UserModel();
		$user->username = "toto123";
		$user->password = "123";
		$user = $userServ->createUser($user);

		// CREATE JOB
		$job = new Stayfilm\stayzen\ORM\JobModel();
		$job->jobtype = 'socialnetwork';
		$job->iduser = $user->iduser;
		$job = $jobServ->create($job);

		// CREATE A ARRAY THAT SHOULD BE SEND BY THE SOCIALNETWORK
		$json = array();
		$json['code'] = 0;
		$json['message'] = 'un message';
		$json['idjob'] = $job->idjob;

		$agentServ->socialNetworkCallback($json);

		// CHECK IF THE JOB HAS BEEN UPDATED
		$job = $jobServ->get($job->idjob);

		$this->assertEquals(orm\JobModel::SUCCESS, $job->status);
	}

	public function testUpdateMeliesInfo()
	{
		$agentServ = AgentService::getInstance();

		$agentServ->updateMeliesInfo('Copola', 10, 7, '');

		$melies = $agentServ->getMeliesInfo('Copola');

		$this->assertEquals(7, $melies->process);

		$agentServ->updateMeliesInfo('Copola', 10, 8, '');

		orm\DataMapperManager::disableCache();

		$melies = $agentServ->getMeliesInfo('Copola');

		orm\DataMapperManager::enableCache();

		$this->assertEquals(8, $melies->process);
	}

	public function testGetOrderedMeliesUrls()
	{
		$agentServ = serv\AgentService::getInstance();

		$agentServ->updateMeliesInfo('Spielberg', 20, 10, '');
		$agentServ->updateMeliesInfo('Jeunet', 30, 10, '');
		$agentServ->updateMeliesInfo('Copola', 10, 10, '');

		$urls = $agentServ->getOrderedMeliesUrls();

		$this->assertEquals('https://Copola:7777/new', implode('', $urls[0]));
	}

}
