<?php

use Stayfilm\stayzen\Application;
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services AS serv;
use \Stayfilm\stayzen as zen;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class MidiaServiceTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass()
	{
		orm\DataMapperManager::truncateTables('dbsite', array('user', 'usersearch'));
		orm\DataMapperManager::truncateTables('dbstay', array('album', 'midia'));
	}

//	function testUpload()
//	{
//		$userServ = serv\UserService::getInstance();
//		$albumServ = serv\AlbumService::getInstance();
//		$midiaServ = serv\MidiaService::getInstance();
//
//		$user = new orm\UserModel();
//		$user->username = "toto";
//		$user->password = 123;
//		$user = $userServ->createUser($user);
//
//		$album = new orm\AlbumModel();
//		$album->idsocialnetwork = SF_SOCIALNETWORK_STAYFILM_ID;
//		$album->idsubtheme = 3;
//		$album->name = 'testAlbum';
//		$album = $albumServ->create($album, $user);
//
//		$pathImage = STAYZEN_ROOT . '/tests/assets/chat.jpg';
//
//		$media = $midiaServ->createFromUpload($pathImage, $album, $user);
//
//		$midiaServ->delete($user, $media);
//	}

	function testBuildMediaUrl()
	{
		$midiaServ = serv\MidiaService::getInstance();
		$blobId = 'test_uuid';
		$mediaUrl = $midiaServ->buildMediaUrl('jpg', $blobId);
		$containerConfig = zen\Application::$config->azure_album_container;
		$this->assertEquals($mediaUrl, $containerConfig->url . '/' . $blobId . '.jpg');
	}

//	function testUploadImage()
//	{
//
//		$userServ = serv\UserService::getInstance();
//		$albumServ = serv\AlbumService::getInstance();
//		$midiaServ = serv\MidiaService::getInstance();
//
//		$user = new orm\UserModel();
//		$user->username = "titi";
//		$user->password = 123;
//		$user = $userServ->createUser($user);
//
//		$album = new orm\AlbumModel();
//		$album->idsocialnetwork = SF_SOCIALNETWORK_STAYFILM_ID;
//		$album->idsubtheme = 3;
//		$album->name = 'testAlbum';
//		$album = $albumServ->create($album, $user);
//
//		$pathImage = STAYZEN_ROOT . '/tests/assets/2000x1500.jpg';
//
//		$media = $midiaServ->createFromUpload($pathImage, $album, $user);
//	}

	function testUploadVideo()
	{

		$userServ = serv\UserService::getInstance();
		$albumServ = serv\AlbumService::getInstance();
		$midiaServ = serv\MidiaService::getInstance();

		$user = new orm\UserModel();
		$user->username = "titi";
		$user->password = 123;
		$user = $userServ->createUser($user);

		$album = new orm\AlbumModel();
		$album->idsocialnetwork = SF_SOCIALNETWORK_STAYFILM_ID;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$album = $albumServ->create($album, $user);

		$pathImage = STAYZEN_ROOT . '/tests/assets/video.mp4';

		//comment this line to avoide test in azure
		//$media = $midiaServ->createFromUpload($pathImage, $album, $user);
	}

	function testAlbum()
	{
		$userServ = serv\UserService::getInstance();
		$albumServ = serv\AlbumService::getInstance();
		$midiaServ = serv\MidiaService::getInstance();

		$user = new orm\UserModel();
		$user->username = "tete";
		$user->password = 123;
		$user = $userServ->createUser($user);

		$album = new orm\AlbumModel();
		$album->idsocialnetwork = $midiaServ->getSNid('sf_upload');
		$album->idtheme = 3;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$albumServ->create($album, $user);

		$album = new orm\AlbumModel();
		$album->idsocialnetwork = $midiaServ->getSNid('facebook');
		$album->idtheme = 3;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$albumServ->create($album, $user);

		$album = new orm\AlbumModel();
		$album->idsocialnetwork = $midiaServ->getSNid('facebook');
		$album->idtheme = 3;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$albumServ->create($album, $user);

		$albums = $midiaServ->getAlbums($user);
		$this->assertEquals(3, count($albums));

		$albums = $midiaServ->getAlbums($user, 'facebook');

		$this->assertEquals(2, count($albums));

		$albums = $midiaServ->getAlbums($user, 'sf_upload');

		$this->assertEquals(1, count($albums));
	}

	function testMoveToStayfilm()
	{
		$userServ = serv\UserService::getInstance();
		$albumServ = serv\AlbumService::getInstance();
		$midiaServ = serv\MidiaService::getInstance();

		$user = new orm\UserModel();
		$user->username = "tata";
		$user->password = 123;
		$user = $userServ->createUser($user);

		$album = new orm\AlbumModel();
		$album->idtheme = 3;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$album->idsocialnetwork = $midiaServ->getSNid('sf_upload');
		$albumServ->create($album, $user);

		$albums = $midiaServ->getAlbums($user, 'sf_upload');
		$this->assertEquals(1, count($albums));

		$pathImage = STAYZEN_ROOT . '/tests/assets/chat.jpg';
		return;
		$media = $midiaServ->createFromUpload($pathImage, $album, $user);

		$midiaServ->moveSnUploadedPhoto($user);

		$albums = $midiaServ->getAlbums($user, 'sf_upload');
		$this->assertEquals(0, count($albums));

		$albums = $midiaServ->getAlbums($user, 'sf_album_manager');
		$this->assertEquals(1, count($albums));
	}

	function testRemoveAllMedias()
	{
		$userServ = serv\UserService::getInstance();
		$albumServ = serv\AlbumService::getInstance();
		$midiaServ = serv\MidiaService::getInstance();

		$user = new orm\UserModel();
		$user->username = "tata";
		$user->password = 123;
		$user = $userServ->createUser($user);

		$album = new orm\AlbumModel();
		$album->idtheme = 3;
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$album->idsocialnetwork = $midiaServ->getSNid('sf_upload');
		$albumServ->create($album, $user);


		$pathImage = STAYZEN_ROOT . '/tests/assets/chat.jpg';
		return;
//		$media = $midiaServ->createFromUpload($pathImage, $album, $user);
//		$media = $midiaServ->createFromUpload($pathImage, $album, $user);
//		$media = $midiaServ->createFromUpload($pathImage, $album, $user);
//
//		$medias = $albumServ->getMedias($album);
//
//		$this->assertEquals(3, count($medias));
//
//		$midiaServ->removeAllMedia($user);
	}

}