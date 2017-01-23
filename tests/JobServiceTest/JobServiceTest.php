<?php

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\Application;
use Symfony\Component\Yaml\Parser;
use Stayfilm\stayzen\ORM\SchemaManager;
use \Stayfilm\stayzen\ORM\JobModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\ORM\UserModel;
use Stayfilm\stayzen\services AS s;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class JobServiceTest extends PHPUnit_Framework_TestCase {

	static function setUpBeforeClass()
	{
		DataMapperManager::truncateTables('dbsite',  array('job', 'user', 'jobpending'));
	}

	function testJob()
	{
		$user = new UserModel();
		$user->username = "toto";
		$user->password = 123;

		$userServ = s\UserService::getInstance();

		$user = $userServ->createUser($user);

		$job = new JobModel();

		$job->jobtype = "create_movie";
		$job->iduser = $user->iduser;

		$jobServ = s\JobService::getInstance();

		$job = $jobServ->create($job);

		$jobs = $jobServ->findAll('dbsite.job');

		$this->assertEquals(1, count($jobs));

		$fields = array('iduser', 'jobtype', 'idjob');
		$values = array($user->iduser, 'create_movie', $jobs[0]->idjob);
		$pendingJob = DataMapperManager::findBy('dbsite.jobpending', $fields, $values);

		$this->assertNotNull($pendingJob);
		$this->assertEquals($job->idjob, $pendingJob->idjob);
		$this->assertEquals($job->created, $pendingJob->jobcreated);
	}

	function testRunningJob()
	{
		$jobServ = s\JobService::getInstance();
		$userServ = s\UserService::getInstance();

		$user = new UserModel();
		$user->username = "toto";
		$user->password = 123;
		$user = $userServ->createUser($user);

		$job = new JobModel();

		$job->jobtype = "socialnetwork";
		$job->iduser = $user->iduser;
		$job->source = 'facebook';
		$job->status = orm\JobModel::PENDING;

		$job = $jobServ->create($job);

		$this->assertTrue($jobServ->hasPendingJobs($user, 'socialnetwork', 3, 'facebook'));

		$job = new JobModel();

		$job->jobtype = "socialnetwork";
		$job->iduser = $user->iduser;
		$job->source = 'facebook';
		$job->status = orm\JobModel::SUCCESS;
		$job = $jobServ->create($job);

		$this->assertTrue($jobServ->hasPendingJobs($user, 'socialnetwork', 3, 'facebook'));

		$job = new JobModel();

		$job->jobtype = "timeline";
		$job->iduser = $user->iduser;
		$job->status = orm\JobModel::PENDING;
		$job = $jobServ->create($job);

		$this->assertTrue($jobServ->hasPendingJobs($user, 'timeline'));

		sleep(2);

		$this->assertFalse($jobServ->hasPendingJobs($user, 'timeline', 1));

		$this->assertFalse($jobServ->hasPendingJobs($user, 'toto', 3));

		try
		{
			$jobServ->hasPendingJobs($user, 'unknownType');
		}
		catch (Exception $e)
		{
			$this->assertTrue(true); // to improve
		}

		$job = new JobModel();
		$job->jobtype = "timeline";
		$job->iduser = $user->iduser;
		$job->status = orm\JobModel::PENDING;
		$job = $jobServ->create($job);

		$pendingJobs = $jobServ->getPendingJobs($user, 'timeline', 5);

		$this->assertEquals(2, count($pendingJobs));

	}

	public function testData()
	{
		$jobServ  = s\JobService::getInstance();
		$userServ = s\UserService::getInstance();

		$user = new UserModel();
		$user->username = "lucasdaveis";
		$user->password = 123;
		$userServ->createUser($user);

		$job = new JobModel();
		$job->iduser = $user->iduser;

		$job->addData('chave1', 'blablabla');

		$jobServ->create($job);

		$job->addData('chave2', 'blebleble');

		$jobServ->update($job);

		$this->assertTrue(key_exists('chave1', $job->data));
		$this->assertTrue(key_exists('chave2', $job->data));

		$this->assertFalse(key_exists('chave3', $job->data));
	}
}
