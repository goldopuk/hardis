<?php
namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen\ORM\Model;

class UserDbstayModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.user';

}


class JobSnModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.jobsn';

}

class ErrorLogModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.errorlog';

}

class LastIntegratedModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.lastintegrated';

}


class FacebookModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.facebook';

}

class FacebookDetailsModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.facebookdetails';

}

class MidiaModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.midia';

	function isImage()
	{
		return in_array(strtolower($this->extension), array('jpeg', 'gif', 'png', 'jpg'));
	}

	/**
	 *
	 * @param string $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name)
	{
		$val = parent::__get($name);

		if ($name === 'source' || $name === 'thumbnail')
		{
			return str_replace('http://', 'https://', $val);
		}

		return $val;
	}

}

class AlbumModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.album';

}

class MidiaReferencesModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.midiareferences';

}

class Midia2AlbumModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.midia2album';

}

class UserAlbumModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.useralbum';
}

class UserMediaModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.usermedia';
}

class Media2AlbumModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.media2album';

}

class MediaRefCountModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.mediarefcount';

}

class MediaTrashModel extends Model
{
	/**
	 *
	 */
	protected $name = 'dbstay.mediatrash';

}