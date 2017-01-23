<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM\JobModel;

class JobService extends TableService
{
	/**
	 * @const string
	 */
	const JOBTYPE_TIMELINE      = 'timeline';

	/**
	 * @const string
	 */
	const JOBTYPE_SOCIALNETWORK = 'socialnetwork';

	/**
	 * @const string
	 */
	const JOBTYPE_IMAGEANALYZER = 'imageanalyzer';

	/**
	 *
	 * @var Stayfilm\stayzen\services\JobService
	 */
	static protected $_instance = null;

	/**
	 *
	 * @var string
	 */
	protected $table = 'dbsite.job';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\JobService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\orm\JobModel $model
	 * @return JobModel
	 */
	public function create($model)
	{
		$model->status = JobModel::PENDING;
		DataMapperManager::create($model);

		$this->fire('job-updated', $model);

		return $model;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param string $type
	 * @param int $timeout
	 * @param string $source
	 * @return boolean
	 */
	function hasPendingJobs($user, $type, $timeout = null, $source = null)
	{
		return (boolean)$this->getPendingJobs($user, $type, $timeout, $source);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param string $type
	 * @param int $timeout
	 * @param string $source
	 * @return boolean
	 */
	public function getPendingJobs($user, $type, $timeout = NULL, $source = NULL)
	{
		if ( ! $user)
		{
			throw new \Exception("user invalid");
		}

		if ($timeout === NULL)
		{
			switch ($type)
			{
				case self::JOBTYPE_SOCIALNETWORK:
					$timeout = zen\Application::$config->socialnetwork_job_timeout;
					break;
				case self::JOBTYPE_TIMELINE:
					$timeout = zen\Application::$config->movie_production_timeout;
					break;
				case self::JOBTYPE_IMAGEANALYZER:
					$timeout = zen\Application::$config->image_analyzer_timeout;
					break;
				default:
					throw new \Exception("Job type $type is invalid");
			}
		}

		if ( ! is_int($timeout))
		{
			throw new \Exception("timeout $timeout should be an integer");
		}

		$fields = array('iduser', 'jobtype');
		$values = array($user->iduser, $type);
		$selectFields = array('idjob', 'created', 'jobcreated');

		$jobs = DataMapperManager::findAllBy('dbsite.jobpending', $fields, $values, $selectFields, NULL);

		$pendingJobs = array();
		$jobServ = zen\services\JobService::getInstance();

		foreach ($jobs as $pendingJob)
		{
			$fields = array();
			$fields[] = 'idjob';
			$fields[] = 'iduser';
			$fields[] = 'jobtype';
			$fields[] = 'progress';
			$fields[] = 'source';
			$fields[] = 'status';
			$fields[] = 'updated';

			$userJob = $jobServ->get($pendingJob->idjob, $fields);

			if ($userJob)
			{
				if ($userJob->status !== JobModel::PENDING)
				{
					error("Jobs without PENDING status should not exist. idjob: {$userJob->idjob}");
					continue;
				}

				if (time() - $pendingJob->jobcreated < $timeout)
				{
					if ($source) // Social Network job
					{
						if ($userJob->source === $source)
						{
							$pendingJobs[] = $userJob;
						}
					}
					else
					{
						$pendingJobs[] = $userJob;
					}
				}
			}
		}

		return $pendingJobs;
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\UserModel $user
	 * @param string $status
	 * @return array
	 */
	public function getUserJobs($user, $status)
	{
		$fields = array('iduser', 'status');
		$values = array($user->iduser, $status);

		$excludeFields = array('-', 'data');

		return DataMapperManager::findAllBy('dbsite.job', $fields, $values, null, 0, $excludeFields);
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\UserModel $user
	 * @param string $status
	 * @return array
	 */
	public function update($job)
	{
		parent::update($job);
		$this->fire('job-updated', $job);

		return $job;
	}
}
