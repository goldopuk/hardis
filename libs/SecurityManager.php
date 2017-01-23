<?php

namespace Stayfilm\stayzen;

use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\exception as exc;

class SecurityManager
{

	static protected $_instance = null;

	public $permissionMapping = NULL;

	/**
	 *
	 * @return \Stayfilm\stayzen\SecurityManager
	 */
	static public function getInstance()
	{

		if ( ! self::$_instance)
		{
			self::$_instance = new self();

		}

		return self::$_instance;
	}

	function __construct()
	{
		$schemaManager = Application::getSchemaManager();

		$basicInfo = $allInfo = $schemaManager->getColumns('user', 'dbsite', TRUE);

		$fieldsToRemoveOnBasicInfo = array('password', 'idfacebook', 'idflickr', 'idinstagram',
			'idvimeo', 'salt', 'status', 'created', 'email', 'lastaccess', 'role', 'updated', 'viewtour');


		$fieldsToRemoveOnAllInfo = array('password', 'role', 'created', 'lastaccess', 'update', 'viewtour');

		$basicInfo = array_diff($basicInfo, $fieldsToRemoveOnBasicInfo);
		$allInfo   = array_diff($allInfo, $fieldsToRemoveOnAllInfo);

		$this->permissionMapping = array(
			'basic_info' => $basicInfo,
			'email'      => array('email'),
			'all_info'   => $allInfo,
		);
	}

	function getPermissions($user, $requester)
	{
		if ( ! $requester)
		{
			return array('basic_info');
		}

		$reflector   = new \ReflectionClass($requester);

		if ($reflector->getShortName() === 'UserModel')
		{
			$socialServ = serv\SocialService::getInstance();

			$relation = $socialServ->getFriendshipStatus($requester, $user);

			$permissions = $this->getPermissionsByStatus($relation);
		}

		if ($requester === 'ApplicationModel')
		{
			// getListfrom cassandra
		}

		return array_unique($permissions);

		throw new \Exception('we should not get there');
	}

	function getAllowedFieldsByPermissions($permissions)
	{
		$fields = array();

		foreach ($permissions as $permission)
		{
			$fields = array_merge($fields, $this->permissionMapping[$permission]);
		}

		return $fields;
	}

	function getAllowedFields($user, $requester)
	{
		$permissions = $this->getPermissions($user, $requester);

		return $this->getAllowedFieldsByPermissions($permissions);
	}

	function getAllowedFieldsByStatus($status)
	{
		$permissions = $this->getPermissionsByStatus($status);

		return $this->getAllowedFieldsByPermissions($permissions);
	}

	function getPermissionsByStatus($status)
	{
		$permissions = array('basic_info');

		switch ($status)
		{
			case 'SAME':
				$permissions[] =  'all_info';
				break;
			case 'FRIENDS':
				$permissions[] = 'basic_info';
		}

		return $permissions;
	}

	/**
	 *
	 * @param type $user
	 * @param type $object
	 * @throws exc\ForbiddenException
	 */
	function checkPermission($user, $object, $action = NULL)
	{
		$reflector   = new \ReflectionClass($object);
		$movieServ   = serv\MovieService::getInstance();
		$socialServ  = serv\SocialService::getInstance();

		if ($user && ! $user->iduser)
		{
			$user = NULL;
		}

		if ( ! $object)
		{
			throw new exc\ForbiddenException("Forbidden - Need to pass the object");
		}

		if ($reflector->getShortName() === 'MovieCommentCoreModel')
		{
			if ( ! $user)
			{
				throw new exc\ForbiddenException("Forbidden - Need to pass the user");
			}

			$movieComment = $movieServ->getComment($object->idmoviecommentcore);

			if ($movieComment->iduser !== $user->iduser)
			{
				throw new exc\ForbiddenException("Forbidden");
			}

			return;
		}

		if ($reflector->getShortName() === 'AlbumModel')
		{
			if ( ! $user)
			{
				throw new exc\ForbiddenException("Forbidden - Need to pass the user");
			}

			if ($user->iduser !== $object->iduser)
			{
				throw new exc\ForbiddenException("Forbidden");
			}

			return;
		}

		if ($reflector->getShortName() === 'GenreTemplateModel')
		{
			if ( ! $user)
			{
				throw new exc\ForbiddenException("Forbidden - Need to pass the user");
			}

			if ( ! $object->isactive && $user->role !== 'admin')
			{
				throw new exc\ForbiddenException("Forbidden - template not active");
			}

			return;
		}

		if ($reflector->getShortName() === 'MovieModel')
		{
			$movie =& $object;

			if ($action === 'delete')
			{
				if ($user->iduser !== $movie->iduser)
				{
					throw new exc\ForbiddenException("Forbidden");
				}
			}
			if ($action === 'write')
			{
				if ($user->iduser !== $movie->iduser)
				{
					throw new exc\ForbiddenException("Forbidden");
				}
			}
			else //view
			{
				switch ($movie->status)
				{
					case ORM\MovieModel::STATUS_DELETED:
					case ORM\MovieModel::STATUS_DENOUNCE:
						throw new exc\ForbiddenException("Forbidden. Movie was deleted or denounced.");
						break;

					case ORM\MovieModel::STATUS_PENDING:
						if ((! $user) || ($user->iduser !== $movie->iduser))
						{
							throw new exc\ForbiddenException("Forbidden. Movie was not published yet.");
						}
						break;

					case ORM\MovieModel::STATUS_ONAPPROVAL:
						throw new exc\ForbiddenException("Forbidden. Movie is under approval process.");
						break;

					case ORM\MovieModel::STATUS_UNAPPROVED:
						throw new exc\ForbiddenException("Forbidden. This movie was not approved.");
						break;

					//  STATUS_ACTIVE or STATUS_DENOUNCE
					default:
						if ($movie->permission === ORM\MovieModel::PUBLIC_ || $movie->permission === ORM\MovieModel::AD)
						{
							return;
						}

						if ( ! $user)
						{
							throw new exc\ForbiddenException("Forbidden - Need to pass the user");
						}

						if ((! $user) || ($movie->permission === ORM\MovieModel::PRIVATE_ && $user->iduser !== $movie->iduser))
						{
							throw new exc\ForbiddenException("Forbidden");
						}

						if ($movie->permission === ORM\MovieModel::FRIEND)
						{
							$movieOwner = $movie->getUser(false);

							if ( ! $movieOwner)
							{
								throw new exc\ForbiddenException("Forbidden - Movie owner does not exist in db");
							}

							$relation = $socialServ->getFriendshipStatus($user, $movieOwner);

							if ($relation !== 'FRIENDS' && $relation !== 'SAME')
							{
								throw new exc\ForbiddenException("Forbidden");
							}
						}

						break;
				}
			}

			return;
		}

		if ($user->iduser !== $object->iduser)
		{
			throw new exc\ForbiddenException("Forbidden");
		}
	}

}
