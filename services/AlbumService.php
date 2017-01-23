<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\ThemeModel; // TODO
use \Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\exception as ex;

class AlbumService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbstay.album';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\AlbumService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\AlbumModel $album
	 * @return \Stayfilm\stayzen\ORM\AlbumModel
	 * @throws \Exception
	 */
	public function create($album, $user)
	{
		if ( ! $user || ! $user->iduser)
		{
			throw new \Exception("Missing User");
		}

		if ( ! $album)
		{
			throw new \Exception("Missing Album");
		}

		if ( $album->idsocialnetwork === NULL)
		{
			throw new \Exception("Missing idsoicalnetwork");
		}

		if ( ! $album->idalbum)
		{
			$album->idalbum    = UUID::uuid4()->string;
		}

		$album->iduser     = $user->iduser;
		$album->idalbumnet = $album->idalbum;

		$album = DataMapperManager::create($album);

		$useralbum = new orm\UserAlbumModel();
		$useralbum->iduser          = $user->iduser;
		$useralbum->idsocialnetwork = $album->idsocialnetwork;
		$useralbum->idalbum         = $album->idalbum;
		$useralbum->name            = $album->name;

		DataMapperManager::create($useralbum);

		return $album;
	}

	/**
	 *
	 * @param type $album
	 * @param array $medias
	 * @throws \Exception
	 */
	public function update($album, $idMidias = NULL)
	{
		$missingMidias = array();

		if ( ! $album)
		{
			throw new \Exception("Missing Album");
		}

		$key = array();
		$key[0] = $album->iduser;
		$key[1] = $album->idalbum;
		DataMapperManager::disableCache();
		$originalAlbum = DataMapperManager::findByKey('dbstay.album', $key);

		DataMapperManager::enableCache();

		if ( ! $originalAlbum)
		{
			throw new \Exception("Album #$album->idalbum does not exist");
		}

		DataMapperManager::update($album);

		$keys = array();
		$keys[] = $album->iduser;
		$keys[] = $originalAlbum->idsocialnetwork; // original idsocialnetwork in case it is modified in $album
		$keys[] = $album->idalbum;

		$userAlbum = DataMapperManager::findByKey('dbstay.useralbum', $keys);

		if ($userAlbum)
		{
			$userAlbum->idsocialnetwork = $album->idsocialnetwork;
			$userAlbum->name = $album->name;

			DataMapperManager::update($userAlbum);
		}
		else
		{
			try
			{
				throw new ex\AlbumManagerUserAlbumMissing("Album #$album->idalbum does not exist in table useralbum");
			}
			catch (ex\AlbumManagerUserAlbumMissing $ex)
			{ }
		}

		if ($idMidias && is_array($idMidias) && count($idMidias) > 0)
		{
			$fields = array();
			$fields[0] = 'idalbum';

			$values = array();
			$values[0] = $album->idalbum;

			$midiasOld = DataMapperManager::findAllBy('dbstay.media2album', $fields, $values, array(), null);

			foreach ($midiasOld as $midiaOld)
			{
				DataMapperManager::delete($midiaOld);
			}

			foreach ($idMidias as $idMidia)
			{
				$key = array();
				$key[0] = $album->iduser;
				$key[1] = $idMidia;

				$midiaOrig = DataMapperManager::findByKey('dbstay.midia', $key);

				if ( ! $midiaOrig)
				{
					$missingMidias[] = $idMidia;
					continue;
					//throw new \Exception("Midia #$idMidia does not exist");
				}

				$midia = new orm\Media2AlbumModel();
				$midia->idalbum = $album->idalbum;
				$midia->idmidia = $idMidia;
				$midia->iduser  = $album->iduser;
				$midia->source  = $midiaOrig->source;
				$midia->thumbnail  = $midiaOrig->thumbnail;

				DataMapperManager::create($midia);
			}
		}

		return $missingMidias;
	}

	/**
	 *
	 * @param type $idalbum
	 * @return array
	 * @throws \Exception
	 */
	public function getMedias($album, $limit = NULL, $offset = NULL)
	{
		$midiaServ = MidiaService::getInstance();
		$userServ = UserService::getInstance();

		if ( ! $album)
		{
			throw new \Exception('Missing album.');
		}

		$user = $userServ->get($album->iduser);

		if ( ! $user)
		{
			throw new \Exception("user {$album->iduser} does not exist");
		}

		$fields = array();
		$fields[] = 'idalbum';

		$values = array();
		$values[] = $album->idalbum;

		if ($offset)
		{
			$fields[] = '>idmidia';
			$values[] = $offset;
		}

		$list = DataMapperManager::findAllBy('dbstay.media2album', $fields, $values, array(), $limit ? $limit + 1 : $limit);

		$newOffset = 0;

		if ($list && $limit && count($list) === $limit + 1)
		{
			array_pop($list);

			$newOffset = end($list)->idmidia;

			reset($list);
		}

		$medias = array();

		foreach ($list as $media2album)
		{
			$media = $midiaServ->get($user, $media2album->idmidia);

			if ( ! $media)
			{
				continue;
			}

			$medias[] = $media;
		}

		$result = array();
		$result[] = $medias;
		$result[] = $newOffset;

		return $result;
	}

	/**
	 *
	 * @param type $album
	 * @return MidiaModel
	 */
	public function getAlbumCover($album)
	{
		list($medias) = $this->getMedias($album, 5);

		foreach ($medias as $media)
		{
			if ($media->isImage()) {
				return $media;
			}
		}

		return NULL;
	}

	public function getAlbumCoverUrl($album)
	{
		list($medias) = $this->getMedias($album, 5);

		foreach ($medias as $media)
		{
			if ($media->isImage()) {
				return $media->thumbnail ?  $media->thumbnail : $media->source;
			}
		}

		return NULL;
	}

	/**
	 *
	 * @param type $user
	 * @param type $idalbum
	 * @return type
	 * @throws \Exception
	 */
	function getAlbum($user, $idalbum)
	{
		if ( ! $user->iduser)
		{
			throw new \Exception("User does not have an id");
		}

		$keys = array();
		$keys[0] = $user->iduser;
		$keys[1] = $idalbum;

		$album = DataMapperManager::findByKey('dbstay.album', $keys);

		return $album;
	}

	/**
	 *
	 * @param string $socialNetwork
	 * @param UserModel $user
	 * @return array
	 */
	function getAlbumsBySnAndUser($user, $socialNetwork, $limit = NULL, $offset = NULL)
	{
		$idsocialnetwork = zen\Utilities::getSnId($socialNetwork);

		if ($idsocialnetwork === NULL)
		{
			throw new \Exception("no id socialnetwork for socialnetwork $socialNetwork");
		}

		$fields = array();
		$fields[] = 'iduser';
		$fields[] = 'idsocialnetwork';

		$values = array();
		$values[] = $user->iduser;
		$values[] = $idsocialnetwork;

		if ($offset)
		{
			$fields[] = '>idalbum';
			$values[] = $offset;
		}

		$userAlbums = DataMapperManager::findAllBy('dbstay.useralbum', $fields, $values, array(), $limit);

		$albums = array();

		foreach ($userAlbums as $userAlbum)
		{
			$album = $this->getAlbum($user, $userAlbum->idalbum);

			if ( ! $album)
			{
				warn("missing album #{$userAlbum->idalbum} in table $album");
				continue;
			}

			$albums[] = $album;
		}

		$result = array();
		$result[] = $albums;

		if ($limit)
		{
			$result[] = count($albums) < $limit ? 0 : $albums[count($albums) - 1]->idalbum;
		}

		return $result;
	}

	/**
	 *
	 * @param type $iduser
	 * @param type $idalbum
	 * @return type
	 */
	function findByKey($iduser, $idalbum)
	{
		$key = array();
		$key[0] = $iduser;
		$key[1] = $idalbum;

		$album = DataMapperManager::findByKey('dbstay.album', $key);

		return $album;
	}

	/**
	 *
	 * @param type $album
	 * @param type $user
	 * @return type
	 * @throws \Exception
	 */
	public function delete($album, $user)
	{
		if ( ! $user)
		{
			throw new \Exception("Missing User");
		}

		if ( ! $album)
		{
			throw new \Exception("Missing Album");
		}

		$fields = array();
		$fields[] = 'idalbum';

		$values = array();
		$values[] = $album->idalbum;

		$media2albums = DataMapperManager::findAllBy('dbstay.media2album', $fields, $values, array(), null);

		foreach ($media2albums as $media)
		{
			DataMapperManager::delete($media);
		}

		$key = array();
		$key[] = $user->iduser;
		$key[] = $album->idsocialnetwork;
		$key[] = $album->idalbum;

		$useralbum = DataMapperManager::findByKey('dbstay.useralbum', $key);

		DataMapperManager::delete($useralbum);

		DataMapperManager::delete($album);

		return;
	}

	/**
	 *
	 * @param type $user
	 * @param type $limit
	 * @param type $offset
	 * @return type
	 * @throws \Exception
	 */
	public function getAlbums($user, $limit, $offset = NULL)
	{
		if ( ! $user)
		{
			throw new \Exception("Missing User");
		}

		if ( ! $limit)
		{
			throw new \Exception("Missing Limit");
		}

		$fields = array();
		$fields[0] = 'iduser';
		$fields[1] = 'idsocialnetwork';

		$values = array();
		$values[0] = $user->iduser;
		$values[1] = 6; // Stayfilm Albums

		if ($offset)
		{
			$fields[2] = '>idalbum';
			$values[2] = $offset;
		}

		$useralbums = DataMapperManager::findAllBy('dbstay.useralbum', $fields, $values, array(), $limit);

		return $useralbums;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\AlbumModel $album
	 * @return int
	 */
	function countAlbumsByUser($user)
	{
		return DataMapperManager::countBy('dbstay.useralbum', 'iduser', $user->iduser);
	}

	function countMedias($album)
	{
		if ($album->photocount || $album->videocount)
		{
			return ($album->photocount ? $album->photocount : 0) + ($album->videocount ? $album->videocount : 0);
		}
		else
		{
			return DataMapperManager::countBy('dbstay.media2album', 'idalbum', $album->idalbum);
		}
	}
}
