<?php
namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use WindowsAzure\Common\ServicesBuilder;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\MidiaModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen as zen;

class MidiaService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbstay.midia';

	protected $snMapping;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MidiaService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$this->snMapping['facebook']          = 1;
		$this->snMapping['flickr']            = 4;
		$this->snMapping['vimeo']             = 5;
		$this->snMapping['instagram']         = 3;
		$this->snMapping['gplus']             = 7;
		$this->snMapping['sf_upload']         = 0;   // stayfilm
		$this->snMapping['sf_album_manager']  = 6;   // picasa
	}

	/**
	 *
	 * @return type
	 */
	function getAzureConnectionString()
	{
		$config = zen\Application::$config->azure_album_container;

		return "DefaultEndpointsProtocol=" . $config['DefaultEndpointsProtocol'] . ";AccountName=" .
				$config['AccountName'].";AccountKey=".$config['AccountKey'];
	}

	/**
	 *
	 * @param type $uuid
	 * @param type $ext
	 * @param type $album
	 * @param type $user
	 * @return type
	 * @throws \Exception
	 */
	public function createFromTemp($uuid, $ext, $album, $user)
	{
		if ( ! $uuid)
		{
			throw new \Exception('Missing #uuid.');
		}

		if ( ! $ext)
		{
			throw new \Exception('Missing #ext.');
		}

		if ( ! $album)
		{
			throw new \Exception('Missing #album.');
		}

		if ( ! $album->idalbum)
		{
			throw new \Exception('idalbum missing in album.');
		}

		if ( ! $user)
		{
			throw new \Exception('Missing #user.');
		}

		$tempDir  = sys_get_temp_dir();
		$ds       = DIRECTORY_SEPARATOR;
		$filePath =  "{$tempDir}{$ds}{$uuid}.{$ext}";
		$blobUrl  = $this->buildMediaUrl($ext, $uuid);

		$mediaContent = FALSE;

		if ($this->blobExists("{$uuid}.{$ext}"))
		{
			$mediaContent = @file_get_contents($blobUrl);
		}

		if ( ! $mediaContent)
		{
			throw new \Exception("File {$blobUrl} does not exist.");
		}

		$result = file_put_contents($filePath, $mediaContent);

		if ( ! $result)
		{
			throw new \Exception("File {$blobUrl} does not exist.");
		}

		$isImage = $this->isImage($filePath, $ext);

		$this->checkSize($filePath, $isImage);

		if ($isImage)
		{
			$this->checkImageDimension($filePath);
		}

		$blobId = "{$uuid}.{$ext}";

		$dimension = getimagesize($filePath);

		$media = new MidiaModel();
		$media->idmidia         = $uuid;
		$media->idmidianet      = $uuid;
		$media->iduser          = $user->iduser;
		$media->idsocialnetwork = $album->idsocialnetwork; /// Stayfilm social network.
		$media->width           = $dimension[0];
		$media->height          = $dimension[1];
		$media->filename        = $blobId;
		$media->extension       = $isImage ? $ext : 'mp4';
		$media->source          = $blobUrl;
		$media->idalbum         = $album->idalbum;
		$media->albumname       = $album->name;
		$media->created         = time();
		$media->origin			= 'mobile';

		$media = $this->createMedia($media, $filePath, $isImage);

		@unlink($filePath);

		DataMapperManager::deleteByKey('dbsite', 'mediauploadtemp', 'idmidia', $media->idmidia);

		return $media;
	}

	/**
	 *
	 * @param type $filePath
	 * @param type $album
	 * @param type $user
	 * @return type
	 * @throws \Exception
	 */
	public function createFromUpload($filePath, $album, $user)
	{
		if ( ! $filePath)
		{
			throw new \Exception('Missing #filePath.');
		}

		if ( ! $album)
		{
			throw new \Exception('Missing #album.');
		}

		if ( ! $album->idalbum)
		{
			throw new \Exception('idalbum does not exists.');
		}

		if ( $album->idsocialnetwork === null )
		{
			throw new \Exception('idsocialnetwork does not exists.');
		}

		$pathInfo = pathinfo($filePath);

		if ( ! isset($pathInfo['extension']))
		{
			throw new \Exception('Filename without extension forbidden');
		}

		if ( ! $user)
		{
			throw new \Exception('Missing #user.');
		}

		$ext = $pathInfo['extension'];

		$isImage = $this->isImage($filePath, $ext);

		$this->checkSize($filePath, $isImage);

		$dimension = NULL;

		if ($isImage)
		{
			$this->prepareImage($filePath);

			$dimension = getimagesize($filePath);
		}

		$uuid   = (string)UUID::uuid4();
		$blobId = "{$uuid}.{$ext}";

		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($this->getAzureConnectionString());

		$containerConfig = zen\Application::$config->azure_album_container;
		$containerName   = $containerConfig->name;

		$fileHandle = fopen($filePath, "r");
		$blobRestProxy->createBlockBlob($containerName, $blobId, $fileHandle);
		fclose($fileHandle);

		$blobUrl = $this->buildMediaUrl($ext, $uuid);

		$media = new MidiaModel();
		$media->idmidia         = $uuid;
		$media->idmidianet      = $uuid;
		$media->iduser          = $user->iduser;
		$media->idsocialnetwork = $album->idsocialnetwork; /// Stayfilm social network.
		$media->width           = $dimension ? $dimension[0] : 0;
		$media->height          = $dimension ? $dimension[1] : 0;
		$media->filename        = $blobId;
		$media->extension       = $isImage ? $ext : 'mp4';
		$media->source          = $blobUrl;
		$media->idalbum         = $album->idalbum;
		$media->albumname       = $album->name;
		$media->created         = time();
		$media->origin			= 'site';

		return $this->createMedia($media, $filePath, $isImage);
	}

	/**
	 *
	 * @param type $media
	 * @param type $filePath
	 * @param type $isImage
	 * @return type
	 */
	private function createMedia($media, $filePath, $isImage)
	{
		$directorySeparator = '/';

		$pathInfo = pathinfo($filePath);
		$ext      = $media->extension;
		$uuid     = $media->idmidia;

		$containerConfig = zen\Application::$config->azure_album_container;
		$containerName   = $containerConfig->name;

		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($this->getAzureConnectionString());

		$thumbUrl = NULL;

		if ($isImage)
		{
			info("Resizing and cropping image to generate thumb.");

			$thumbPath = "{$pathInfo['dirname']}{$directorySeparator}{$uuid}_thumb.{$ext}";

			if (class_exists('\Imagick'))
			{
				$image = new \Imagick($filePath);
				$image->cropThumbnailImage(105, 84);
				$image->writeImage($thumbPath);
			}
			else
			{
				\ImageTransform::ImageResize($filePath, $thumbPath, 105, 84, 70, true);
			}

			$thumbHandle = fopen($thumbPath, "r");
			$thumbBlobId = "{$uuid}_thumb.{$ext}";
			$blobRestProxy->createBlockBlob($containerName, $thumbBlobId, $thumbHandle);
			fclose($thumbHandle);

			$thumbUrl = "{$containerConfig->url}{$directorySeparator}{$thumbBlobId}";

			@unlink($thumbPath);
		}

		$media->thumbnail = $thumbUrl;

		DataMapperManager::create($media);

		$mediaUpload = new orm\MediaUploadModel();
		$mediaUpload->iduser  = $media->iduser;
		$mediaUpload->idmedia = $media->idmidia;

		DataMapperManager::create($mediaUpload);

		$media2album = new orm\Media2AlbumModel();
		$media2album->idalbum   = $media->idalbum;
		$media2album->idmidia   = $media->idmidia;
		$media2album->iduser    = $media->iduser;
		$media2album->source    = $media->source;
		$media2album->thumbnail = $media->thumbnail;

		DataMapperManager::create($media2album);

		return $media;
	}

	/**
	 *
	 * @return type
	 */
	public function createTemp()
	{
		$mediaUploadTemp = new orm\MediaUploadTempModel();

		$mediaUploadTemp->idmidia = (string)UUID::uuid4();

		DataMapperManager::create($mediaUploadTemp);

		return $mediaUploadTemp->idmidia;
	}

	/**
	 *
	 * @param type $media
	 * @return type
	 */
	function getMediaThumbBlobId($media)
	{
		return $media->idmidia . '_thumb.' . $media->extension;
	}

	/**
	 *
	 * @param type $user
	 * @param type $media
	 * @throws \Exception
	 */
	function delete($user, $media)
	{
		if ( ! $media)
		{
			throw new \Exception('Midia missing');
		}

		if ( ! $user)
		{
			throw new \Exception('user missing');
		}

		$values = array();
		$values[] = $media->idalbum;
		$values[] = $media->idmidia;

		$media2album = DataMapperManager::findByKey('dbstay.media2album', $values);

		$config = zen\Application::$config->azure_album_container;
		$containerName = $config->name;

		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($this->getAzureConnectionString());

		try
		{
			$blobRestProxy->deleteBlob($containerName, $media->filename);

			if ($media->isImage())
			{
				$thumbBlobId = $this->getMediaThumbBlobId($media);
				$blobRestProxy->deleteBlob($containerName, $thumbBlobId);
			}

			DataMapperManager::delete($media);

			if ($media2album)
			{
				DataMapperManager::delete($media2album);
			}
		}
		catch(\WindowsAzure\Common\ServiceException $e)
		{
			$code         = $e->getCode();
			$errorMessage = $e->getMessage();

			throw new \Exception($code . ": " . $errorMessage);
		}
	}

	/**
	 *
	 * @param type $user
	 * @return type
	 */
	function removeAllMedia($user)
	{
		$fieldsMidia = array();
		$fieldsMidia[] = 'iduser';
		$fieldsMidia[] = 'idmidia';

		$medias = DataMapperManager::findAllBy('dbstay.midia', 'iduser', $user->iduser, $fieldsMidia, null);

		foreach ($medias as $key => $media)
		{
			DataMapperManager::delete($media);
		}

		$fieldsalbum = array();
		$fieldsalbum[] = 'iduser';
		$fieldsalbum[] = 'idalbum';

		$albums = DataMapperManager::findAllBy('dbstay.album', 'iduser', $user->iduser, $fieldsalbum, null);

		$media2AlbumCount = 0;

		foreach ($albums as $key => $album)
		{
			$media2albums  = DataMapperManager::findAllBy('dbstay.media2album', 'idalbum', $album->idalbum, array('idmidia', 'idalbum'), NULL);

			foreach ($media2albums as $key => $m)
			{
				DataMapperManager::delete($m);

				$media2AlbumCount++;
			}

			DataMapperManager::delete($album);
		}

		$fieldsuseralbum = array();
		$fieldsuseralbum[] = 'iduser';
		$fieldsuseralbum[] = 'idsocialnetwork';
		$fieldsuseralbum[] = 'idalbum';

		$useralbums = DataMapperManager::findAllBy('dbstay.useralbum', 'iduser', $user->iduser, $fieldsuseralbum, null);

		foreach ($useralbums as $key => $useralbum)
		{
			DataMapperManager::delete($useralbum);
		}

		return array('medias' => count($medias), 'media2albums' => $media2AlbumCount,
			'album' => count($albums), 'useralbum' => count($useralbums));
	}

	/**
	 *
	 * @param type $user
	 * @param type $album
	 * @return type
	 */
	function getUserMediaStat($user)
	{
		$fieldsMidia = array();
		$fieldsMidia[] = 'iduser';
		$fieldsMidia[] = 'idmidia';
		$fieldsMidia[] = 'idalbum';

		$medias = DataMapperManager::findAllBy('dbstay.midia', 'iduser', $user->iduser, $fieldsMidia, null);

		$mediaAlbums = array();

		foreach ($medias as $media)
		{
			$mediaAlbums[] = $media->idalbum;
		}

		$mediaAlbums = array_unique($mediaAlbums);

		$fieldsalbum = array();
		$fieldsalbum[] = 'iduser';
		$fieldsalbum[] = 'idalbum';
		$fieldsalbum[] = 'idsocialnetwork';
		$fieldsalbum[] = 'name';

		$albums = DataMapperManager::findAllBy('dbstay.album', 'iduser', $user->iduser, $fieldsalbum, null);

		$media2AlbumCount = 0;

		$media2albumIds = array();

		foreach ($albums as $key => $album)
		{
			$media2albums = DataMapperManager::findAllBy('dbstay.media2album', 'idalbum', $album->idalbum, array('idmidia', 'idalbum'), NULL);

			foreach ($media2albums as $key => $m)
			{
				$media2albumIds[] = $m->idmidia;
				$media2AlbumCount++;
			}
		}

		$fieldsuseralbum = array();
		$fieldsuseralbum[] = 'iduser';
		$fieldsuseralbum[] = 'idsocialnetwork';
		$fieldsuseralbum[] = 'idalbum';

		$useralbums = DataMapperManager::findAllBy('dbstay.useralbum', 'iduser', $user->iduser, $fieldsuseralbum, null);

		$albums = array_map(function ($album) {
			$arr = array();
			$arr['idalbum'] = $album->idalbum;
			$arr['name'] = $album->name;
			$arr['socialnetwork'] = zen\Utilities::getSnFromId($album->idsocialnetwork);

			return $arr;

		}, $albums);

		//find inconsistence
		$albumMissing = array();

		foreach ($albums as $album)
		{
			if ( ! in_array($album['idalbum'], $mediaAlbums))
			{
				$albumMissing[] = $album['idalbum'];
			}
		}

		$missingMedias = array();
		$mediasAlbumsSN = array();

		foreach ($medias as $media)
		{
			$mediasAlbumsSN[] = $media->idmidia . ' - ' .
				$media->idalbum . ' - ' .
				$media->idsocialnetwork . ' - ' .
				date('Y-m-d H:i:s', $media->created);
			if ( ! in_array($media->idmidia, $media2albumIds))
			{
				$missingMedias[] = $media->getAttrs();
			}
		}

		return array(
			'How many Medias' => count($medias),
			'Media - Album - SN - Date Created - Origin' => $mediasAlbumsSN,
			'Medias in midia2album' => $media2AlbumCount,
			'How many Albums' => count($albums),
			'Albums' => $albums,
			'How many Albums user has' => count($useralbums),
			'How many Albums in midia2album' => count($mediaAlbums),
			'Albums in midia2album' => $mediaAlbums,
			'Albums without medias' => $albumMissing,
			'Medias without Albums - NULL MEDIAS' => $missingMedias);
	}

	/**
	 *
	 * @param type $sn
	 * @return type
	 */
	function getSNid($sn)
	{
		return isset($this->snMapping[$sn]) ? $this->snMapping[$sn] : null;
	}

	/**
	 *
	 * @param type $needle
	 * @return null
	 */
	function getSnFromId($needle)
	{
		foreach ($this->snMapping as $key => $id)
		{
			if ($id === $needle)
			{
				return $key;
			}
		}

		return NULL;
	}

	/**
	 *
	 * @return type
	 */
	function getSnList()
	{
		return array_keys($this->snMapping);
	}

	/**
	 *
	 * @param type $user
	 * @return type
	 */
	function getAllUserMediaUpload($user)
	{
		$fields = array();
		$fields[] = 'iduser';

		$values = array();
		$values[] = $user->iduser;

		$midias = DataMapperManager::findAllBy('dbsite.mediaupload', $fields, $values);

		return $midias;
	}

	/**
	 *
	 * @param type $user
	 */
	function moveSnUploadedPhoto($user)
	{
		$midiasUploaded = $this->getAllUserMediaUpload($user);

		foreach ($midiasUploaded as $midiaUploaded)
		{
			$midia = orm\DataMapperManager::findByKey('dbstay.midia', array($midiaUploaded->iduser, $midiaUploaded->idmedia));

			if ($midia)
			{
				$midia->idsocialnetwork = $this->getSNid('sf_album_manager');

				DataMapperManager::update($midia);
			}

			DataMapperManager::delete($midiaUploaded);
		}

		$albums = $this->getAlbums($user, 'sf_upload');

		foreach ($albums as $album)
		{
			$album->idsocialnetwork = $this->getSNid('sf_album_manager');

			DataMapperManager::update($album);
		}
	}

	/**
	 *
	 * @param type $user
	 * @param type $sn
	 * @return type
	 */
	function getAlbums($user, $sn = NULL)
	{
		$idsn = $this->getSNid($sn);

		$albums = DataMapperManager::findAllBy('dbstay.album', 'iduser', $user->iduser);

		$newList = array();

		if ($idsn !== NULL)
		{
			foreach ($albums as $album)
			{

				if ($album->idsocialnetwork === $idsn)
				{
					$newList[] = $album;
				}
			}
		}
		else
		{
			$newList = $albums;
		}

		return $newList;
	}

	/**
	 *
	 * @param type $user
	 * @param type $idmidia
	 * @return type
	 */
	function get($user, $idmidia)
	{
		$values    = array();
		$values[0] = $user->iduser;
		$values[1] = $idmidia;

		return DataMapperManager::findByKey('dbstay.midia', $values);
	}

	/**
	 *
	 * @param type $ext
	 * @param type $uuid
	 * @return type
	 */
	public function buildMediaUrl($ext, $uuid = NULL)
	{
		$directorySeparator = '/';

		$uuid = $uuid ? $uuid : (string)UUID::uuid4();

		$containerConfig = zen\Application::$config->azure_album_container;

		return "{$containerConfig->url}{$directorySeparator}{$uuid}.{$ext}";
	}

	/**
	 *
	 * @param type $filePath
	 * @return type
	 */
	private function prepareImage($filePath)
	{
		$width  = 0;
		$height = 0;

		$uploadImageMaxWidth = zen\Application::$config->upload_image_max_width;
		$uploadImageMaxHeight = zen\Application::$config->upload_image_max_height;
		$uploadImageQuality  = zen\Application::$config->upload_image_quality;

		// resize original image
		if (class_exists('\Imagick'))
		{
			debug("Imagick is installed. Let's use it to resize the image");
			$image = new \Imagick($filePath);

			$width = $image->getImageWidth();
			$width =  $width > $uploadImageMaxWidth ? $uploadImageMaxWidth : $width;

			$image->thumbnailImage($width, 0);
			$image->writeImage($filePath);

			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();
		}
		else
		{
			\ImageTransform::ImageResize($filePath, $filePath, $uploadImageMaxWidth, $uploadImageMaxHeight, $uploadImageQuality, false);
		}
	}

	/**
	 *
	 * @param type $filePath
	 * @param type $ext
	 * @return boolean
	 * @throws \Exception
	 */
	private function isImage($filePath, $ext)
	{
		$mime = zen\Utilities::getMimeType($filePath);

		if (in_array($mime, zen\Application::$config->image_mimes->toArray()))
		{
			$isImage = true;
		}
		else if (in_array($mime, zen\Application::$config->video_mimes->toArray()))
		{
			$isImage = false;
		}
		else if (in_array($ext, zen\Application::$config->video_extensions->toArray()))
		{
			$isImage = false;
		}
		else if (in_array($ext, zen\Application::$config->image_extensions->toArray()) )
		{
			$isImage = true;
		}
		else
		{
			throw new \Exception(__("file (mime $mime or extension $ext) not authorized"));
		}

		return $isImage;
	}

	/**
	 *
	 * @param type $filePath
	 * @param type $isImage
	 * @throws \Exception
	 */
	private function checkSize($filePath, $isImage)
	{
		$size = filesize($filePath);

		if ($isImage)
		{
			if ($size > zen\Application::$config->image_max_size)
			{
				throw new \Exception("File Image size error $size");
			}
		}
		else
		{
			if ($size > zen\Application::$config->video_max_size)
			{
				throw new \Exception("File Video size error $size");
			}
		}
	}

	/**
	 *
	 * @param type $filePath
	 * @throws \Exception
	 */
	private function checkImageDimension($filePath)
	{
		$dimension = getimagesize($filePath);

		if ( ! isset($dimension[0]) || ! isset($dimension[1]))
		{
			throw new \Exception("Error getting the image size $filePath");
		}
	}

	/**
	 *
	 * @param string $blobId
	 * @return boolean
	 */
	public function blobExists($blobId)
	{
		$config = zen\Application::$config->azure_album_container;
		$containerName = $config->name;
		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($this->getAzureConnectionString());

		try
		{
			$blobRestProxy->getBlobProperties($containerName, $blobId);
			return TRUE;
		}
		catch(\Exception $ex)
		{
			return FALSE;
		}
	}
}