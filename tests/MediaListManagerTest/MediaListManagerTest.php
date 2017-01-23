<?php

use Stayfilm\stayzen\Application;
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services AS serv;
use \Stayfilm\stayzen as zen;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class MediaListManagerTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass()
	{
	}

	function testCountMedias()
	{
		$this->markTestSkipped();

		$userServ  = serv\UserService::getInstance();
		$agentServ = serv\AgentService::getInstance();

		$me = $userServ->getUserByUsername('criatividadeehagora');
		$networks = array();
		$networks[] = serv\OAuthService::SN_FACEBOOK;
		$theme = 1;
		$subtheme = 44;
		$genre = 2;
		$albums = array();
		$hints = array();
		$hints[] = 'eu';
		$hints[] = 'qualquer';
		$hints[] = 'Juca';

		$json = $agentServ->selector($me, $networks, $theme, $subtheme, $genre, is_array($albums) ? $albums : array(), $hints);

		$mediaList = $json['json'];

		$genreTemplateServ = serv\GenreTemplateService::getInstance();

		$genreTemplate = $genreTemplateServ->get(2, 1);

		$medias = zen\MediaListManager::getRankedMedias($mediaList, $genreTemplate);

		$this->assertEquals(72, count($medias));

	}
}