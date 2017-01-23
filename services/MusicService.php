<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\MusicModel; // TODO

class MusicService extends Service
{
	
	static protected $_instance = null;
	
	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MusicService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\MusicModel $music
	 * @return Stayfilm\stayzen\ORM\MusicModel
	 * @throws \Exception
	 */
	public function createMusic($music)
	{
		$music = DataMapperManager::create($music);

		return $music;
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\MusicModel $music
	 * @return Stayfilm\stayzen\ORM\MusicModel
	 * @throws \Exception
	 */
	public function updateMusic($music)
	{
		$music = DataMapperManager::update($music);

		return $music;
	}
}
