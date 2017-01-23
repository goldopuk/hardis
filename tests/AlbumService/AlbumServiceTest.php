<?php

use Stayfilm\stayzen\services\UserService;
use Stayfilm\stayzen\services\MovieService;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\ORM\UserModel;
use Stayfilm\stayzen\ORM\Model;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\ORM\MovieModel;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\Application;


// use Stayfilm\stayzen\utilities;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class AlbumServiceTest extends PHPUnit_Framework_TestCase
{

	public static function setUpBeforeClass()
	{
		DataMapperManager::truncateTables('dbsite',  array('user'));
		DataMapperManager::truncateTables('dbstay',  array('album', 'useralbum'));
	}

	public function testAlbum()
	{
		$albumServ = serv\AlbumService::getInstance();
		$userServ  = serv\UserService::getInstance();

		$user = new UserModel();
		$user->username = "toto";
		$user->password = "123";
		$user = $userServ->createUser($user);

		// teste CREATE
		$album = new orm\AlbumModel();
		$album->name            = 'Album Teste';
		$album->idsocialnetwork = 6;
		$album->hints           = 'tag1,tag2,tag3';
		$album->theme           = 1;

		$album = $albumServ->create($album, $user);

		$this->assertNotEmpty($album);

		// teste UPDATE
		$key = array();
		$key[0] = $user->iduser;
		$key[1] = $album->idsocialnetwork;

		$useralbum = DataMapperManager::findByKey('dbstay.useralbum', $key);

		$this->assertNotEmpty($useralbum);

		$album->name = 'Outro';

		// necessito de midias caso queira realizar o teste completo
		$albumServ->update($album, NULL);

		$this->assertEquals('Outro', $album->name);

		// teste DELETE
		$albumServ->delete($album, $user);

		$key = array();
		$key[0] = $album->iduser;
		$key[1] = $album->idalbum;

		$albumDeleted = DataMapperManager::findByKey('dbstay.album', $key);

		$this->assertEmpty($albumDeleted);

		$key = array();
		$key[0] = $album->iduser;
		$key[1] = $album->idalbum;

		$albumDeleted = DataMapperManager::findByKey('dbstay.album', $key);

		$this->assertEmpty($albumDeleted);

		$fields = array();
		$fields[0] = 'idalbum';

		$values = array();
		$values[0] = $album->idalbum;

		$mediasDeleted = DataMapperManager::findAllBy('dbstay.media2album', $fields, $values);

		$this->assertEmpty($mediasDeleted);
	}

	function testGetMedia()
	{
		$this->markTestSkipped('skip as we need to upload file in azure to execute that test');

		$userServ = serv\UserService::getInstance();
		$albumServ = serv\AlbumService::getInstance();
		$midiaServ = serv\MidiaService::getInstance();

		$user = new orm\UserModel();
		$user->username = "toto";
		$user->password = 123;

		$album = new orm\AlbumModel();
		$album->idsubtheme = 3;
		$album->name = 'testAlbum';
		$user = $userServ->createUser($user);

		$album = new orm\AlbumModel();
		$album->idsocialnetwork = \Stayfilm\stayzen\Utilities::getSnId('sf_album_manager');
		$album = $albumServ->create($album, $user);

		$pathImage = STAYZEN_ROOT . '/tests/assets/chat.jpg';
		$media1 = $midiaServ->createFromUpload($pathImage, $album, $user);
		$media2 = $midiaServ->createFromUpload($pathImage, $album, $user);
		$media3 = $midiaServ->createFromUpload($pathImage, $album, $user);

		list($medias) = $albumServ->getMedias($album);

		$this->assertEquals(3, count($medias));

		$albumServ->update($album, array($media1->idmidia, $media2->idmidia));

		list($medias) = $albumServ->getMedias($album);

		$this->assertEquals(2, count($medias));

		$cover1 = $albumServ->getAlbumCover($album);

		$this->assertNotNull($cover1);

		// Since idcoverphoto is not used anymore, we will skip the rest
		// of this test. Check #255 bug for details.
//		$album->idcoverphoto = $media3->idmidia;
//		$albumServ->update($album);
//
//		$cover2 = $albumServ->getAlbumCover($album);
//
//		$this->assertNotEquals($cover1->idmidia, $cover2->idmidia);
	}
}