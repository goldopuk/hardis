<?php

namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\Application;
use \Stayfilm\stayzen\ORM\JobModel;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\Utilities;
use Stayfilm\stayzen as zen;
use Guzzle\Http\Client;
use Stayfilm\stayzen\exception as zexc;
use WindowsAzure\Common\ServicesBuilder;

class MovieService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbsite.movie';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MovieService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}


	//@todo: remove this when new melies be in all hosts
	public function get($idmovie, $fields = NULL)
	{
		$movie = parent::get($idmovie, $fields);

		return $this->removeOutFromMovieURL($movie);
	}

	public function getGenreConfig($movie)
	{
		$genreServ    = serv\GenreService::getInstance();
		$campaignServ = serv\CampaignService::getInstance();

		$campaign = $campaignServ->getCampaignById($movie->idcampaign);

		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign.');
		}

		$genre = $genreServ->getGenre($movie->idgenre);

		if ( ! $genre)
		{
			throw new \Exception('Missing genre.');
		}

		$config = $genreServ->getConfig($genre, $campaign);

		return $config;
	}

	/**
	 *
	 * Should not be used directly.
	 * To create a movie, use UserService::addMovie()
	 *
	 * @param $movie
	 * @param null $recipe
	 * @return orm\Model
	 */
	public function create($movie, $recipe = null)
	{
		$movie->views    = 0;
		$movie->likes    = 0;
		$movie->shares   = 0;
		$movie->lastplay = 0;
		$movie->isstored = 0;

		if ( ! $movie->permission)
		{
			$movie->permission = orm\MovieModel::PRIVATE_;
		}

		if ( ! $movie->status)
		{
			$movie->status = orm\MovieModel::STATUS_PENDING;
		}

		if ( ! $movie->duration)
		{
			$movie->duration = 0;
		}

		if ( ! $movie->idgenre)
		{
			$movie->idgenre = 0;
		}

		$movie = DataMapperManager::create($movie);

		if ($recipe)
		{
			$recipeModel = new orm\RecipeModel();
			$recipeModel->idmovie = $movie->idmovie;
			$recipeModel->recipe = $recipe;

			DataMapperManager::create($recipeModel);
		}

		if ($movie->status === orm\MovieModel::STATUS_ACTIVE || $movie->status === orm\MovieModel::STATUS_DENOUNCE)
		{
			$userServ = serv\UserService::getInstance();
			$userServ->incrementMovie($movie);
		}

		$movieViewStatistic = new orm\MovieViewStatisticModel();
		$movieViewStatistic->idmovie = $movie->idmovie;
		// This is becasue CQL 3 does not accept insert of a register with just the key
		$movieViewStatistic->view0 = 0;
		DataMapperManager::create($movieViewStatistic);

		$this->fire($this->getEventName('update-movie'), array('movie' => $movie));

		return $movie;
	}

	/**
	 *
	 * @param type $movie
	 * @return \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @throws \Exception
	 */
	public function update($movie)
	{
		if ($movie->isNew())
		{
			throw new \Exception("Model Movie is New.");
		}

		$modifiedAttrs = $movie->getModifiedAttrs();

		if (array_key_exists('status', $modifiedAttrs))
		{
			$this->changeStatus($movie, $movie->status);
		}

		$movie = DataMapperManager::update($movie);

		$this->fire($this->getEventName('update-movie'), array('movie' => $movie));

		return $movie;
	}

	/**
	 *
	 * @param type $movie
	 * @param type $user
	 * @throws \Exception
	 */
	public function addLike($movie, $user)
	{
		$values = array();
		$values[] = $movie->idmovie;
		$values[] = $user->iduser;

		$likeHistory = DataMapperManager::findByKey('dbsite.likehistory', $values);

		if ($likeHistory && $likeHistory->status === 1)
		{
			throw new zexc\AlreadyLikedException('Movie already liked by this User.');
		}

		// This second check would be desnecessary because before i check if status is 1 but i'll mantain that here to better code understad
		if ($likeHistory && $likeHistory->status === 0)
		{
			$liked = TRUE;

			$likeHistory->status = 1;
			DataMapperManager::update($likeHistory);
		}
		else
		{
			$liked = FALSE;

			$likeHistory = new orm\LikeHistoryModel();
			$likeHistory->idmovie = $movie->idmovie;
			$likeHistory->iduser  = $user->iduser;
			$likeHistory->status  = 1;

			DataMapperManager::create($likeHistory);
		}

		$userLike = new orm\UserLikeModel();
		$userLike->iduser      = $user->iduser;
		$userLike->idmovie     = $movie->idmovie;
		$userLike->likeupdated = $likeHistory->updated;
		DataMapperManager::create($userLike);

		$movieLike = new orm\MovieLikeModel();
		$movieLike->idmovie     = $movie->idmovie;
		$movieLike->iduser      = $user->iduser;
		$movieLike->likeupdated = $likeHistory->updated;
		DataMapperManager::create($movieLike);

		$user->likes += 1;
		DataMapperManager::update($user);

		$movie->likes += 1;
		DataMapperManager::update($movie);

		$this->fire($this->getEventName('addLike'), array('movie' => $movie, 'liker' => $user, 'liked' => $liked));
	}

	/**
	 *
	 * @param type $movie
	 * @param type $user
	 * @throws \Exception
	 */
	public function removeLike($movie, $user)
	{
		$values = array();
		$values[] = $movie->idmovie;
		$values[] = $user->iduser;

		$likeHistory = DataMapperManager::findByKey('dbsite.likehistory', $values);

		if (( ! $likeHistory) || ($likeHistory && $likeHistory->status === 0))
		{
			throw new zexc\AlreadyDislikedException("Movie isn't liked by this User.");
		}

		$userLike = new orm\UserLikeModel();
		$userLike->iduser      = $user->iduser;
		$userLike->likeupdated = $likeHistory->updated;
		DataMapperManager::delete($userLike);

		$movieLike = new orm\MovieLikeModel();
		$movieLike->idmovie     = $movie->idmovie;
		$movieLike->likeupdated = $likeHistory->updated;
		DataMapperManager::delete($movieLike);

		$likeHistory->status = 0;
		DataMapperManager::update($likeHistory);

		$user->likes -= 1;
		DataMapperManager::update($user);

		$movie->likes -= 1;
		DataMapperManager::update($movie);

	}

	/**
	 *
	 * @param type $movie
	 * @return array
	 */
	public function getLikes($movie, $limit = NULL, $offset = NULL)
	{
		$fields = array();
		$fields[] = 'idmovie';

		$values = array();
		$values[] = $movie->idmovie;

		if ($offset)
		{
			$fields[] = '<likeupdated';
			$values[] = $offset;
		}

		return DataMapperManager::findAllBy('dbsite.movielike', 'idmovie', $movie->idmovie, array(), $limit, 'likeupdated desc');
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @param \Stayfilm\stayzen\ORM\UserModel $movie
	 * @return \Stayfilm\stayzen\ORM\MovieShareModel
	 */
	public function addShare($movie, $user)
	{
		$share = new orm\MovieShareModel();

		$share->idmovie = $movie->idmovie;
		$share->iduser  = $user->iduser;

		DataMapperManager::create($share);

		$movie->shares = $movie->shares + 1;

		DataMapperManager::update($movie);

		$userServ = serv\UserService::getInstance();

		$userServ->incrementShare($user);

		$this->fire('movie-shared', array('movie' => $movie, 'user' => $user));

		return $share;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @return array
	 */
	public function getShares($movie)
	{
		return DataMapperManager::findAllBy('dbsite.movieshare', 'idmovie', $movie->idmovie);
	}

	/**
	 *
	 * @param string $uuid
	 * @return Stayfilm\stayzen\ORM\UserModel
	 */
	public function getMovieByKey($uuid)
	{
		$movie = DataMapperManager::findByKey('movie', $uuid);

		//@todo: remove this when new melies be in all hosts
		return $this->removeOutFromMovieURL($movie);
	}

	/**
	 *
	 * @param string $guid
	 * @return \Stayfilm\stayzen\ORM\MovieModel
	 */
	public function getMovieByGuid($guid)
	{
		$movie = DataMapperManager::findBy('dbsite.movie', 'idmovie', $guid);

		//@todo: remove this when new melies be in all hosts
		return $this->removeOutFromMovieURL($movie);
	}

	//@todo: remove this when new melies be in all hosts
	private function removeOutFromMovieURL($movie)
	{
		if ( ! $movie) {
			return NULL;
		}

		if (Application::$config->melies_without_output)
		{
			$movie->videourl = preg_replace('/\/output/', '', $movie->videourl);
		}
		else
		{
			// kludge
			$url = $movie->videourl;

			if ( ! preg_match('/output/', $url)) {

				$url = preg_replace('/.{8}-.{4}-.{4}-.{4}-.{12}/', 'output', $url);
				$url .= '/' . $movie->idmovie;

				$movie->videourl = $url;
			}
		}

		return $movie;
	}

	public function doBestOf($idmovie, $strDate = NULL, $isTimestamp = false)
	{
		$movie = $this->getMovieByKey($idmovie);

		if ( ! $movie)
		{
			throw new \Exception('Movie does not exist');
		}

		if ($movie->permission !== orm\MovieModel::PUBLIC_)
		{
			throw new \Exception('Movie permission has to be public');
		}

		if ($movie->status !== orm\MovieModel::STATUS_ACTIVE &&
			$movie->status !== orm\MovieModel::STATUS_UNLISTED_PUBLISHED &&
			$movie->status !== orm\MovieModel::STATUS_DENOUNCE)
		{
			throw new \Exception('Movie status has to be active, unlisted or denounce');
		}

		$date = NULL;

		if ($strDate && ! $isTimestamp)
		{
			$date = new \DateTime($strDate);
		}
		elseif ($strDate)
		{
			$date = $strDate;
		}

		/// Rodar sempre, no minimo um dia anterior a semana que é para aparecer!
		$movie->bestof = $date ? ($isTimestamp ? $date : $date->getTimestamp()) : time();

		DataMapperManager::update($movie);

		$gallery = DataMapperManager::findByKey('dbsite.gallery', $movie->idmovie);

		if ( ! $gallery)
		{
			$gallery = new orm\GalleryModel();

			$gallery->idmovie = $movie->idmovie;
			$gallery->created = $movie->created;
			$gallery->bestof  = $movie->bestof;

			DataMapperManager::create($gallery);
		}
		else
		{
			$gallery->bestof = $movie->bestof;
			DataMapperManager::update($gallery);
		}
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @param integer $rank
	 * @return \Stayfilm\stayzen\ORM\TimelineModel
	 * @throws \Exception
	 */
	public function addToBestof($movie, $rank)
	{
		info(__METHOD__);

		// get from timeline
		// if exist, update to bestoff
		// else throw exception
		if ($movie->permission !== orm\MovieModel::PUBLIC_)
		{
			throw new \Exception("Movie should be public");
		}

		$gallery = DataMapperManager::findByKey('dbsite.gallery', $movie->idmovie);

		if (!$gallery)
		{
			//throw new \Exception("Movie does not exist in gallery table");
			$gallery = new orm\GalleryModel();
			$gallery->idmovie = $movie->idmovie;
			$gallery->created = $movie->created;
			$gallery->bestof = 0;
			DataMapperManager::create($gallery);
		}

		$gallery->bestof = $rank;

		DataMapperManager::update($gallery);

		return $gallery;
	}

	/**
	 *
	 * @param type $week
	 * @param type $offset
	 * @param type $limit
	 * @param type $direction
	 * @return type
	 * @throws \Exception
	 */
	function getBestof($week, $offset = NULL, $limit = 6, $direction = 'down')
	{
		debug(__METHOD__);

		if ($week && gettype($week) !== 'integer' && ! ctype_digit($week))
		{
			throw new \Exception('week paramater must be integer.');
		}

		if ($direction !== 'up' && $direction != 'down')
		{
			throw new \Exception('Direction paramater must be up or down.');
		}

		$week = (int)$week;

		$movies = array();

		$client = Application::getSolrClient('gallery');

		$query = $client->createSelect();

		if ($week)
		{
			$days = Utilities::getDaysOfTheWeek($week);
			$str = "bestof:[{$days['firstDay']} TO {$days['lastDay']}]";

			$query->addSort('bestof', $query::SORT_ASC); // Ivan requested that bestof movies should appear in the right order they are inserted.
		}
		else if ($offset)
		{
			if ($direction === 'down')
			{
				$str = "bestof:[* TO {$offset}] AND -bestof:0 AND -bestof:1";
			}
			else
			{
				$str = "bestof:[{$offset} TO *]";
			}

			$query->setRows($limit);
		}

		$query->setQuery($str);

		$galleries = $client->execute($query);

		foreach ($galleries as $gallery)
		{
			$fields   = array();
			$fields[] = 'idmovie';

			$values   = array();
			$values[] = $gallery->idmovie;

			$movie = DataMapperManager::findBy('dbsite.movie', $fields, $values);

			if ($movie)
			{
				$movies[] = $movie;
			}
		}

		$result = array();
		$result[] = $movies;
		$result[] = $galleries ? $galleries[count($galleries) - 1]->bestof - 1 : 0;
		$result[] = $galleries ? $galleries[0]->bestof + 1                     : 0;

		return $result;
	}

	function createMeliesMediaReferences($medias, $user)
	{
		if (!$medias || !$user)
		{
			return;
		}

		$mediaServ = serv\MidiaService::getInstance();

		foreach($medias as $idmidia)
		{
			if ( ! Utilities::isValidUUID4($idmidia))
			{
				continue;
			}

			$mediaDb = $mediaServ->get($user, $idmidia);

			if ($mediaDb && $mediaDb->idsocialnetwork !== SF_SOCIALNETWORK_STAYFILM_ID && $mediaDb->idsocialnetwork !== 6)
			{
				$mediaRef = orm\DataMapperManager::findByKey('dbstay.mediarefcount', $idmidia);

				if ( ! $mediaRef)
				{
					$newMediaRef = new orm\MediaRefCountModel();
					$newMediaRef->idmidia = $idmidia;
					$newMediaRef->count = 1;
					$newMediaRef->created = time();
					$newMediaRef->updated = time();

					orm\DataMapperManager::create($newMediaRef);
				}
				else
				{
					$mediaRef->idmidia = $mediaRef->idmidia;
					$mediaRef->count = $mediaRef->count + 1;
					$mediaRef->updated = time();

					orm\DataMapperManager::update($mediaRef);
				}
			}
		}
	}

	function setMediasToDelete($medias, $user)
	{
		if (!$medias || !$user)
		{
			return;
		}

		$mediaServ = serv\MidiaService::getInstance();

		foreach($medias as $media)
		{
			if ( ! Utilities::isValidUUID4($media->midia_id))
			{
				continue;
			}

			$mediaDb = $mediaServ->get($user, $media->midia_id);

			if ($mediaDb && $mediaDb->idsocialnetwork !== SF_SOCIALNETWORK_STAYFILM_ID && $mediaDb->idsocialnetwork !== 6)
			{
				$mediaRef = orm\DataMapperManager::findByKey('dbstay.mediarefcount', $media->midia_id);

				if ($mediaRef && $mediaRef->count > 1)
				{
					$mediaRef->idmidia = $mediaRef->idmidia;
					$mediaRef->count = $mediaRef->count - 1;
					$mediaRef->updated = time();

					orm\DataMapperManager::update($mediaRef);
				}
				else if ($mediaRef)
				{
					$mediaRef->idmidia = $mediaRef->idmidia;
					$mediaRef->count = 0;
					$mediaRef->updated = time();

					orm\DataMapperManager::update($mediaRef);

					// Mark media to delete
					$mediaToDelete = new orm\MediaTrashModel();
					$mediaToDelete->idmidia = $mediaRef->idmidia;
					$mediaToDelete->created = time();

					orm\DataMapperManager::create($mediaToDelete);
				}
			}
		}
	}

	/**
	 *
	 * @param MovieModel $movie
	 */
	function getCommentators($movie)
	{
		$models = DataMapperManager::findAllBy('dbsite.moviecomment', 'idmovie', $movie->idmovie);

		$users = array();

		foreach ($models as $model)
		{
			$user = DataMapperManager::findByKey('dbsite.user', $model->iduser);

			if ($user)
			{
				$users[$model->iduser] = $user; // to remove duplicated
			}
		}

		return array_values($users);
	}

	/**
	 *
	 * @param MovieModel $movie
	 * @param Integer $status
	 * @return MovieModel
	 * @throws \Exception
	 */
	protected function changeStatus($movie, $status)
	{
		info(__METHOD__);

		if ( ! in_array($status, orm\MovieModel::getStatusList()))
		{
			throw new \Exception("Status $status invalid");
		}

		$userServ = serv\UserService::getInstance();

		$movieFromDB = NULL;

		if ($movie->idmovie)
		{
			DataMapperManager::disableCache();
			$movieFromDB = $this->get($movie->idmovie);
			DataMapperManager::enableCache();
		}

		if ( ! $movieFromDB)
		{
			throw new \Exception('Trying to change status of movie that doesn\'t exist in data base.');
		}

		if ($movieFromDB->status === $movie->status)
		{
			return;
		}

		switch ($movieFromDB->status)
		{
			case orm\MovieModel::STATUS_DELETED:
				if ($movie->status === orm\MovieModel::STATUS_PENDING ||
				$movie->status === orm\MovieModel::STATUS_ONAPPROVAL ||
				$movie->status === orm\MovieModel::STATUS_UNAPPROVED)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_ACTIVE:
				if ($movie->status === orm\MovieModel::STATUS_DENOUNCE)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_DENOUNCE:
				if ($movie->status === orm\MovieModel::STATUS_ACTIVE)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_PENDING:
				if ($movie->status === orm\MovieModel::STATUS_DELETED ||
				$movie->status === orm\MovieModel::STATUS_ONAPPROVAL ||
				$movie->status === orm\MovieModel::STATUS_UNAPPROVED)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_ONAPPROVAL:
				if ($movie->status === orm\MovieModel::STATUS_PENDING ||
				$movie->status === orm\MovieModel::STATUS_UNAPPROVED ||
				$movie->status === orm\MovieModel::STATUS_DELETED)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_UNAPPROVED:
				if ($movie->status === orm\MovieModel::STATUS_DELETED ||
				$movie->status === orm\MovieModel::STATUS_PENDING ||
				$movie->status === orm\MovieModel::STATUS_ONAPPROVAL)
				{
					return;
				}

				break;
			case orm\MovieModel::STATUS_UNLISTED_PENDING:
				if ($movie->status === orm\MovieModel::STATUS_UNLISTED_PUBLISHED)
				{
					return;
				}
				break;
		}

		switch ($status)
		{
			case orm\MovieModel::STATUS_ACTIVE:
			case orm\MovieModel::STATUS_DENOUNCE:

				$userServ->incrementMovie($movie);

				break;
			case orm\MovieModel::STATUS_DELETED:
			case orm\MovieModel::STATUS_PENDING:
			case orm\MovieModel::STATUS_ONAPPROVAL:
			case orm\MovieModel::STATUS_UNAPPROVED:

				$userServ->decrementMovie($movie);

				break;
			default:
				return;
		}

		$movie->status = $status;

		return DataMapperManager::update($movie);
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @param integer $permission
	 * @return \Stayfilm\stayzen\ORM\MovieModel
	 */
	function changePermission($movie, $permission)
	{
		info(__METHOD__);

		$permission = (integer)$permission;

		if ( ! in_array($permission, orm\MovieModel::getPermissionList()))
		{
			throw new \Exception("Permission $permission invalid");
		}

		$movie->permission = $permission;

		$movie = DataMapperManager::update($movie);

		$this->fire($this->getEventName('update-movie'), array('movie' => $movie));

		return $movie;
	}

	/**
	 *
	 * @param MovieModel $movie
	 * @return string
	 */
	public function getMovieUrl($movie)
	{
		return $movie->videourl;
	}

	/**
	 *
	 * @param MovieModel $movie
	 * @param string $size
	 * @return string
	 * @throws \Exception
	 */
	public function getImageUrl($movie, $size = 'small', $hover = FALSE)
	{
		$ext = '.jpg';

		switch ($size)
		{
			case 'small':
				$filename = '266x150';
				break;
			case 'medium':
				$filename = '572x322';
				break;
			case 'large':
				$filename = '640x360';
				break;

			default:
				throw new \Exception("Image size $size not available");
		}

		if ($hover === TRUE)
		{
			$filename = $filename.'_t';
		}
		else
		{
			$filename = $filename.'_n';
		}

		return $movie->videourl . "/$filename" .$ext;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @param \Stayfilm\stayzen\ORM\UserModel $user
	 * @param string $comment
	 * @return \Stayfilm\stayzen\ORM\MovieCommentModel
	 */
	public function addComment($movie, $user, $str)
	{
		$comment = new orm\MovieCommentCoreModel();

		$comment->iduser = $user->iduser;
		$comment->idmovie = $movie->idmovie;
		$comment->comment = $str;

		$comment->status = orm\MovieCommentCoreModel::ACTIVE;

		$comment = DataMapperManager::create($comment);

		info($comment);

		$this->fire($this->getEventName('addComment'), array('comment' => $comment, 'movie' => $movie));

		return $comment;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieCommentCoreModel $comment
	 * @return \Stayfilm\stayzen\ORM\MovieCommentCoreModel
	 */
	public function removeComment($comment)
	{
		if ( ! is_object($comment))
		{
			$commentId = $comment;
			$comment = DataMapperManager::findByKey('dbsite.moviecommentcore', $commentId);
		}

		$comment->status = orm\MovieCommentCoreModel::DELETED;

		$comment = DataMapperManager::update($comment);

		$this->fire($this->getEventName('removeComment'), array('comment' => $comment));

		return $comment;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @return \Stayfilm\stayzen\ORM\MovieModel
	 */
	public function delete($movie)
	{
		$userServ = serv\UserService::getInstance();
		$user = $userServ->get($movie->iduser);

		$mediasTrash = array();
		$movieRecipe = $this->getMovieRecipe($movie);
		$recipe = json_decode($movieRecipe);

		if (isset($recipe->media_gallery))
		{
			$medias = $recipe->media_gallery;

			foreach ($medias as $media)
			{
				$mediasTrash[] = $media;
			}
		}

		// Set medias to be deleted in the future
		$this->setMediasToDelete($mediasTrash, $user);

		if ($user->idcovermovie === $movie->idmovie)
		{
			$user->idcovermovie = NULL;
			$userServ->updateUser($user);
		}

		$movie->status = orm\MovieModel::STATUS_DELETED;

		return $this->update($movie);
	}

	/**
	 *
	 * Searche in table moviecomment, NOT in moviecommmentcore
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @return array
	 */
	public function getComments($movie)
	{
		return DataMapperManager::findAllBy('dbsite.moviecomment', 'idmovie', $movie->idmovie, array(), NULL);
	}

	/**
	 *
	 * @param string $idcomment
	 * @return MovieCommentCoreModel
	 */
	public function getComment($idcomment)
	{
		return DataMapperManager::findByKey('dbsite.moviecommentcore', $idcomment);
	}

	/**
	 *
	 * @param MovieModel $movie
	 * @return int
	 */
	public function getCommentCount($movie)
	{
		return DataMapperManager::countBy('dbsite.moviecomment', 'idmovie', $movie->idmovie);
	}

	/**
	 *
	 * @param MovieModel $movie
	 * @return boolean
	 */
	public function isBestOf($movie)
	{
		return (boolean)$movie->bestof;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\MovieModel $movie
	 * @return int
	 */
	function countMoviesByUser($user)
	{
//		// TODO: melhorar essa query. Combinado com Fabiano de deixar assim apos
//		//       a mudanca da tabela dbsite.timeline que quebrou a query count(*)
//		//       que estava sendo usada.
//		$movies = DataMapperManager::findAllBy('dbsite.movie', 'iduser', $user->iduser, array('iduser', 'status'), null);
//
//		$count = 0;
//		foreach ($movies as $movie)
//		{
//			if ($movie->status == ORM\MovieModel::STATUS_ACTIVE)
//			{
//				$count++;
//			}
//			else
//			{
//				continue;
//			}
//		}

		$userServ = serv\UserService::getInstance();

		$count = $userServ->getConfigValue($user, 'movie_count');

		return $count !== NULL ? (int)$count : 0;
	}

	public function getMoviesByGenre($genre)
	{
		$fields = array();
		$fields[0] = 'idgenre';
		$fields[1] = 'permission';
		$fields[2] = 'status';

		$values[0] = $genre->idgenre;
		$values[1] = orm\MovieModel::PUBLIC_;
		$values[2] = orm\MovieModel::STATUS_ACTIVE;


		return DataMapperManager::findAllBy('dbsite.movie', $fields, $values);
	}

	public function incrementView($movie, $percent)
	{
		if ( ! $movie)
		{
			throw new zexc\MovieStatisticException('Missing movie to increment.');
		}

//		if ($movie->status != orm\MovieModel::STATUS_ACTIVE && $movie->status != orm\MovieModel::STATUS_DENOUNCE)
//		{
//			// Movie is pending; do not throw exception.
//			return NULL;
//			//throw new zexc\MovieStatisticException("Movie: #{$movie->idmovie} hasn't status ACTIVE or DENOUNCE.");
//		}

		if ($percent === '')
		{
			throw new zexc\MovieStatisticException('percent missing');
		}

		$result = NULL;

		try
		{
			$movieViewStatistic = $this->getMovieStatistic($movie->idmovie);

			if ( ! $movieViewStatistic)
			{
				$movieViewStatistic = new orm\MovieViewStatisticModel();
				$movieViewStatistic->idmovie = $movie->idmovie;
				// This is becasue CQL 3 does not accept insert of a register with just the key
				$movieViewStatistic->view0 = 0;

				$movieViewStatistic = DataMapperManager::create($movieViewStatistic);
			}

			if ($percent == 0)
			{
				$movie->views = $movie->views ? $movie->views + 1 : 1;

				DataMapperManager::update($movie);
			}

			$fieldName = "view{$percent}";
			$movieViewStatistic->$fieldName = $movieViewStatistic->$fieldName ? $movieViewStatistic->$fieldName + 1 : 1;
			$result = DataMapperManager::update($movieViewStatistic);
		}
		catch(\Exception $ex)
		{
			throw new zexc\MovieStatisticException('Error on incrementView', 1, $ex);
		}


		return $result;
	}

	public function getUsersLikedMovie($movie, $limit = NULL, $offset = NULL)
	{
		$userServ = serv\UserService::getInstance();

		$fields = array();
		$fields[] = 'idmovie';

		$values = array();
		$values[] = $movie->idmovie;

		if ($offset)
		{
			$fields[] = '<likeupdated';
			$values[] = $offset;
		}

		$movieLikes = DataMapperManager::findAllBy('dbsite.movielike', $fields, $values, array('iduser'), $limit, 'likeupdated desc');

		$users = array();
		$count = 0;

		foreach ($movieLikes as $movieLike)
		{
			$user = $userServ->getUserByKey($movieLike->iduser);

			if ( ! $user)
			{
				continue;
			}

			$users[] = $user;

			$count++;
		}

		$lastMovieLike = end($movieLikes);

		$result = array();
		$result[] = $users;
		$result[] = $lastMovieLike ? $lastMovieLike->likeupdated : NULL;

		return $result;
	}

	public function isMovieSharedByUser($user, $movie)
	{
		$key = array();
		$key[] = $movie->idmovie;
		$key[] = $user->iduser;

		$shared = (boolean) orm\DataMapperManager::findByKey('dbsite.movieshare', $key);
		return $shared;
	}

	public function isLiked($movie, $user)
	{
		if ( ! $user)
		{
			return FALSE;
		}

		$values = array();
		$values[] = $movie->idmovie;
		$values[] = $user->iduser;

		$likeHistory = DataMapperManager::findByKey('dbsite.likehistory', $values);

		if (( ! $likeHistory) || ($likeHistory->status === 0))
		{
			return FALSE;
		}
		else if ( $likeHistory->status === 1)
		{
			return TRUE;
		}
		else
		{
			throw new \Exception('We should not be here.');
		}
	}

	public function addMovieView($movie, $user)
	{
		if ( ! $movie)
		{
			throw new \Exception('Movie has to be informed');
		}

		if ( ! $user)
		{
			throw new \Exception('User has to be informed');
		}

		$movieExist = DataMapperManager::findByKey('dbsite.movie', $movie->idmovie);

		if ( ! $movieExist)
		{
			throw new \Exception('Movie does not exist.');
		}

		$userExist = DataMapperManager::findByKey('dbsite.user', $user->iduser);

		if ( ! $userExist)
		{
			throw new \Exception('User does not exist.');
		}

		$movieView = new orm\MovieViewModel();

		$movieView->idmovie = $movie->idmovie;
		$movieView->iduser  = $user->iduser;

		DataMapperManager::create($movieView);
	}

	/*
	 * $job array
	 * $params array
	 */
	public function createMovieFromJob($job, $params = array())
	{
		info(__METHOD__);

		$userServ = serv\UserService::getInstance();
		$jobServ = serv\JobService::getInstance();

		if ($job->status === orm\JobModel::SUCCESS)
		{
			throw new \Exception("Job #{$job->idjob} has already a SUCCESS status.");
		}

		// else status PENDING or FAILURE

		// We have a job.
		// If something goes wrong, we neeed to catch the exception and update the job
		try
		{
			$user = $userServ->getUserByKey($job->iduser);

			if ( ! $user)
			{
				throw new \Exception("User #{$job->iduser} does not exist");
			}

			// Video's URL
			$jobProgressURL = $job->data['job_progress_url'];
			$videolUrl = substr($jobProgressURL, 0, strripos(rtrim($jobProgressURL, "/"), "/"));

			// Create movie register
			$movie = new orm\MovieModel();
			$movie->idmovie		= $job->idjob;
			$movie->idjob		= $job->idjob;
			$movie->iduser		= $user->iduser;
			$movie->idgenre		= $job->data['sentdata']['genre'];
			$movie->idtheme		= $job->data['sentdata']['theme'];
			$movie->idsubtheme	= $job->data['sentdata']['subtheme'];
			$movie->title		= $job->data['sentdata']['title'];

			$campaignServ = serv\CampaignService::getInstance();

			$additionalData = $job->getData('additionalData');

			// Loads the genres from user campaigns, if the user has one.
			$campaign = $userServ->getUserCampaign($userServ->get($movie->iduser));

			if ( ! $campaign)
			{
				if ($additionalData && is_array($additionalData) && isset($additionalData['campaign']))
				{
					$campaign = $campaignServ->getCampaignBySlug($additionalData['campaign']);
				}
			}

			if ( ! $campaign)
			{
				$campaign = $campaignServ->getCampaignBySlug('stayfilm');
			}

			if ( ! $campaign)
			{
				throw new \Exception('missing campaign');
			}

			$movie->videourl	= $videolUrl;
			$movie->duration	= isset($params['duration']) ? $params['duration'] : null;
			$movie->idtemplate	= isset($params['idtemplate']) ? $params['idtemplate'] : null;
			$movie->bidata		= isset($params['bidata']) ? $params['bidata'] : null;
			$movie->idcampaign  = $campaign->idcampaign;
			$movie->idcustomer  = $campaign->idcustomer;

			//@todo pedir para fepas adicionar esse campo na dbsite.slugcampaign
			//$movie->idcustomer  = $campaign->idcustomer;

			info('add movie to user');

			$userServ->addMovie($user, $movie);

			if ($campaign->slug !== 'stayfilm')
			{
				$campaign2movie = new orm\Campaign2MovieModel();
				$campaign2movie->idcampaign = $campaign->idcampaign;
				$campaign2movie->idmovie = $movie->idmovie;
				DataMapperManager::create($campaign2movie);
			}

			if (isset($params['medias']))
			{
				$this->addTaggedPeopleByMedias($movie, $params['medias']);
			}

			info('update job');

			$job->status = JobModel::SUCCESS;

			$data                     = $job->data;
			$data['idmovie']          = $movie->idmovie;
			$job->data                = $data;

			$this->updateTemplateHistory($movie);

			$jobServ->update($job);
		}
		catch (\Exception $e)
		{
			info($e->getTraceAsString());

			$job->status = JobModel::FAILURE;

			$jobServ->update($job);

			throw $e;
		}

		try
		{
			if (isset($additionalData['from_stayfilm']))
			{
				$this->fire('movie-created-automatically', array('movie' => $movie, 'user' => $user));
			}
			else
			{
				$this->fire('movie-created', array('movie' => $movie, 'user' => $user));
			}
		}
		catch (\Exception $e)
		{
			zen\Utilities::logException($e, $user);
		}

		return $movie;
	}

	public function updateTemplateHistory($movie)
	{
		$userServ = serv\UserService::getInstance();
		$user = $userServ->get($movie->iduser);

		// Update movie template history for user
		$lastTemplates = $userServ->getConfigValue($user, 'last_templates');
		$lastTemplates = $lastTemplates ? $lastTemplates : array();

		if (count($lastTemplates) >= 10) // if we have more than 10 elements, throw away the first one and push the current template in the list
		{
			$lastTemplates = array_slice($lastTemplates, 1, 10);
		}

		$movieGenreTemplate = array('idgenre' => $movie->idgenre, 'idtemplate' => $movie->idtemplate);

		$lastTemplates[] = $movieGenreTemplate;

		$userServ->addConfigItem($user, 'last_templates', $lastTemplates);
	}

	public function getMostPopular2($startDate, $endDate = NULL, $offset = NULL, $limit = NULL)
	{
		if ($endDate === NULL)
		{
			$endDate = time();
		}
		else
		{
			if ($endDate < $startDate)
			{
				throw new \Exception('endData has to be bigger than startDate');
			}
		}

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		if (!$offset && !$limit)
		{
			$query->setStart(0)->setRows(6);
		}

		$str = "permission:" . orm\MovieModel::PUBLIC_ . " AND status:" . orm\MovieModel::STATUS_ACTIVE;
		$str .= " AND created:[{$startDate} TO {$endDate}]";

		info($str);

		$query->setQuery($str);
		$query->addSort('views', $query::SORT_DESC);

		$list = $client->execute($query);

		$movies = array();

		foreach ($list as $movieSearch)
		{
			$movie = $this->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		return $movies;
	}

	public function getGalleryFeed($offset = NULL, $limit = 6, $new = NULL, $campaigns = array())
	{
		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		if ( ! $offset)
		{
			$offset = time();
		}


		if ($new)
		{
			$str = "publicated:[{$offset} TO *]";
		}
		else
		{
			$str = "publicated:[* TO {$offset}]";
		}

		$status = orm\MovieModel::STATUS_ACTIVE;
		$permission = orm\MovieModel::PUBLIC_;

		$str .= " AND status:{$status} AND permission:{$permission}";

		if (count($campaigns) > 0)
		{
			$str .= ' AND idcampaign:({0})';

			$idcampaigns = array();

			foreach ($campaigns as $campaign)
			{
				$idcampaigns[] = $campaign->idcampaign;
			}

			$str = str_replace('{0}', implode(' ', $idcampaigns), $str);
		}

		info($str);

		$query->setQuery($str);
		$query->addSort('publicated', $query::SORT_DESC);
		$query->setStart(0)->setRows($limit + 1);

		$galleries = $client->execute($query);

		$movies = array();

		foreach ($galleries as $gallery)
		{
			$movieFields = array();
			$movieFields[] = 'idmovie';
			$movieFields[] = 'created';
			$movieFields[] = 'idcampaign';
			$movieFields[] = 'idgenre';
			$movieFields[] = 'iduser';
			$movieFields[] = 'status';
			$movieFields[] = 'permission';
			$movieFields[] = 'publicated';
			$movieFields[] = 'synopsis';
			$movieFields[] = 'title';
			$movieFields[] = 'bestof';
			$movieFields[] = 'likes';
			$movieFields[] = 'videourl';

			$movie = $this->get($gallery->idmovie, $movieFields);

			if ($movie)
			{
				$movies[] = $movie;
			}

			$movieFound = FALSE;

			foreach ($movies as$auxMovie)
			{
				if ($auxMovie->idmovie === $gallery->idmovie)
				{
					$movieFound = true;
					break;
				}
			}

			// If, for some reason, the movie didn't come complete from cassandra,
			// we will try to fetch it from Solr response.
			// For some reason we don't know yet, cassandra sometimes do not bring
			// all the fields required.
			if ( ! $movieFound)
			{
				if ($gallery->idmovie)
				{
					//Try again to get some missing fields from movie
					$auxMovieFields = array();
					$auxMovieFields[] = 'idmovie';
					$auxMovieFields[] = 'videourl';
					$auxMovieFields[] = 'bestof';
					$auxMovieFields[] = 'likes';
					$auxMovie = DataMapperManager::findByKey('dbsite.movie', $gallery->idmovie, $movieFields);

					// Build new movie from solr object to avoid missing fields
					$newMovie = new orm\MovieModel();
					$newMovie->idmovie = $gallery->idmovie;
					$newMovie->created = $gallery->created;
					$newMovie->idcampaign = $gallery->idcampaign;
					$newMovie->idgenre = $gallery->idgenre;
					$newMovie->iduser = $gallery->iduser;
					$newMovie->status = $gallery->status;
					$newMovie->permission = $gallery->permission;
					$newMovie->publicated = $gallery->publicated;
					$newMovie->synopsis = $gallery->synopsis;
					$newMovie->title = $gallery->title;
					$newMovie->bestof = isset($auxMovie->bestof) ? $auxMovie->bestof : 0; // not on solr
					$newMovie->likes = isset($auxMovie->likes) ? $auxMovie->likes : 0; // not on solr
					$newMovie->videourl = isset($auxMovie->videourl) ? $auxMovie->videourl : ''; // not on solr

					$movies[] = $newMovie;
				}
			}
		}

		$previousOffset = 0;
		$newOffset      = 0;

		if (count($movies) > 0)
		{
			$previousOffset = $movies[0]->publicated + 1;
		}

		if (count($galleries) === $limit + 1)
		{
			if (count($movies) === count($galleries))
			{
				$lastMovie = array_pop($movies);
				$newOffset = $lastMovie->publicated;
			}
			else if (count($movies) > 1)
			{
				$newOffset = $movies[count($movies) - 1]->publicated;
				$newOffset = $new ? $newOffset + 1 : $newOffset - 1;
			} else
			{
				// do nothing - end of list
			}
		}

		return array($movies, $newOffset, $previousOffset);
	}

	/**
	 * @param null $week A int value that represents the week ago you want to return the bestOf
	 * @param null $timeWeek
	 * @return array
	 * @throws \Exception
	 */
	function getBestof2($week = NULL, $timeWeek = NULL)
	{
		info(__METHOD__);

		if ( ! $week)
		{
			throw new \Exception("Missing week");
		}

		$movies = array();

		$client = Application::getSolrClient('gallery');

		$query = $client->createSelect();

		$days = Utilities::getDaysOfTheWeek($week, $timeWeek);
		$str = "bestof:[{$days['firstDay']} TO {$days['lastDay']}]";

		$query->setQuery($str);
		$query->setStart(0)->setRows(6);
		$query->addSort('bestof', $query::SORT_ASC);

		$galleries = $client->execute($query);

		foreach ($galleries as $gallery)
		{
			$fields   = array();
			$fields[] = 'idmovie';

			$values   = array();
			$values[] = $gallery->idmovie;

			$movie = DataMapperManager::findBy('dbsite.movie', $fields, $values);

			if ($movie)
			{
				$movies[] = $movie;
			}
		}

		return $movies;
	}

	function getByGenre($slug, $publicated = NULL, $limit = 6, $new = NULL)
	{
		$genreServ = serv\GenreService::getInstance();

		$limit = (int)$limit;

		$genre = $genreServ->getGenreBySlug($slug);

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		$str = "idgenre:{$genre->idgenre} AND permission:3 AND status:1";

		if ($publicated)
		{
			if ($new)
			{
				$str .= " AND publicated:[{$publicated} TO *]";
			}
			else
			{
				$str .= " AND publicated:[* TO {$publicated}]";
			}
		}

		info($str);

		$query->setQuery($str);

		$query->setRows($limit);

		$query->addSort('publicated', $query::SORT_DESC);

		$list = $client->execute($query);

		$movies = array();

		foreach ($list as $movieSearch)
		{
			$movie = $this->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		$data = array();
		$data[] = $movies;
		$data[] = $list ? $list[count($list) - 1]->created - 2 : 0; // removo um da data pois o MENOR do SOLR é MENOR OU IGUAL
		$data[] = count($list) === $limit ? false : true;
		$data[] = $list ? $list[0]->created + 1 : 0;

		return $data;
	}

	public function getMovieStatistic($idmovie)
	{
		return DataMapperManager::findByKey('dbsite.movieviewstatistic', $idmovie);
	}

	/**
	 *
	 * @param type $user
	 * @param type $movie
	 * @return boolean
	 */
	public function isCommentAllowed($user, $movie)
	{
		$fields = array();
		$fields[] = 'idmovie';
		$fields[] = '<commentcreated';
		$fields[] = '>commentcreated';

		$values = array();
		$values[] = $movie->idmovie;
		$values[] = time();
		$values[] = time() - Application::$config->comment_block_time;

		$comments = DataMapperManager::findAllBy('dbsite.moviecomment', $fields, $values);

		$c = 0;

		foreach ($comments as $comment)
		{
			if ($comment->iduser !== $user->iduser)
			{
				continue;
			}

			$c++;

			if ($c >= Application::$config->comments_per_cycle)
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	function getMovieRecipe($movie)
	{
		$recipeUrl = $movie->videourl . '/recipe.input';
		$json = '';

		try
		{
			$client = new Client($recipeUrl);

			$request = $client->get();
			$request->getCurlOptions()->set(CURLOPT_SSL_VERIFYPEER, FALSE);

			if (isset(zen\Application::$config->melies_curl_sslversion))
			{
				$request->getCurlOptions()->set(CURLOPT_SSLVERSION, zen\Application::$config->melies_curl_sslversion);
			}

			$response = $request->send();

			$json = $response->getBody();
		}
		catch (\Exception $ex)
		{
			try
			{
				$posAzureWord = strpos($movie->videourl, zen\Application::$config->melies_azure_cdn_url);

				if ($posAzureWord !== FALSE)
				{
					$azureContainer = zen\Application::$config->melies_recipe_azure_movie_container;
					$blobAddr = "{$movie->iduser}/{$movie->idmovie}/recipe.input";

					$connectionString = zen\Utilities::getAzureConnectionString($azureContainer);
					$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($connectionString);
					$blob = $blobRestProxy->getBlob("secure", $blobAddr);
					$json = stream_get_contents($blob->getContentStream());
				}
				else
				{
					$json = "";
				}
			}
			catch (WindowsAzure\Common\ServiceException $ex) // Bypass if we do not have any people tagged or file does not exist.
			{
				$json = "";
			}
			catch (\Exception $ex) // Bypass if we do not have any people tagged or file does not exist.
			{
				$json = "";
			}
		}

		return (string)$json;
	}

	function publish($movie, $permission, $synopsis = '', $lang = 'en', $status = orm\MovieModel::STATUS_ACTIVE)
	{
		$movieServ    = serv\MovieService::getInstance();
		$userServ     = serv\UserService::getInstance();
		$campaignServ = serv\CampaignService::getInstance();
		$genreServ    = serv\GenreService::getInstance();

		if ( ! in_array($permission, orm\MovieModel::getPermissionList()))
		{
			throw new \Exception("Permission $permission invalid");
		}

		$genreConfig = $movieServ->getGenreConfig($movie);

		if (isset($genreConfig['share_ad_text']))
		{
			if (is_array($genreConfig['share_ad_text'])) {
				$synopsis .= '&nbsp;' . $genreConfig['share_ad_text'][$lang];
			}
			else
			{
				$synopsis .= '&nbsp;' . $genreConfig['share_ad_text'];
			}
		}

		$movie->synopsis   = $synopsis;
		$movie->publicated = time();

		$user = $userServ->getUserByKey($movie->iduser);

		$campaign = NULL;

		if ($movie->idcampaign)
		{
			$campaign = $campaignServ->getCampaignById($movie->idcampaign);
		}

		if ( ! $campaign)
		{
			$campaign = $campaignServ->getCampaignBySlug('stayfilm');

			if ( ! $campaign)
			{
				throw new \Exception('Campaign stayfilm does not exist');
			}
		}

		$genre = $genreServ->get($movie->idgenre);

		if ( ! $genre)
		{
			throw new \Exception("Genre {$movie->idgenre} does not exist.");
		}

		$config = $genreServ->getConfig($genre, $campaign);

		$hasCuration = isset($config['curation']) && $config['curation'];
		$hasMonitor = isset($config['monitor']) && $config['monitor'];

		$campaignOwners = isset($config['owners_iduser']) && $config['owners_iduser'] ? explode(',', $config['owners_iduser']) : array();

		if ($hasCuration && ! in_array($movie->iduser, $campaignOwners))
		{
			$movie->status = orm\MovieModel::STATUS_ONAPPROVAL;
		}
		else
		{
			$movie->status = $status;
		}

		$movie->permission = $permission;

		$movieServ->update($movie);

		if ($hasCuration && ! in_array($movie->iduser, $campaignOwners))
		{
			$campaignServ->sendMovieToCuration($movie);

			$emailManager = zen\EmailManager::getInstance();

			$email = $emailManager->getEmailInstance('movie-sent-for-approval');

			if ($email)
			{
				$email->configure($user, $movie);
				$emailManager->send($email);
			}
		}

		if ($hasMonitor && ! in_array($movie->iduser, $campaignOwners))
		{
			$campaignServ->sendMovieToMonitor($movie);
		}

		if ($permission === orm\MovieModel::PUBLIC_)
		{
			if ( ! $user->idcovermovie)
			{
				$user->idcovermovie = $movie->idmovie;
				$userServ->update($user);
			}
		}
	}

	/**
	 *
	 * @param type $movie
	 * @param type $arrIdmedia
	 */
	public function addTaggedPeopleByMedias($movie, $arrIdmedia)
	{
		$mediaServ = serv\MidiaService::getInstance();
		$userServ  = serv\UserService::getInstance();

		$user = $userServ->get($movie->iduser);

		if ( ! $user)
		{
			error("User #{$movie->iduser} does not exist in database.");
		}

		$arrIdFacebook = array();

		foreach ($arrIdmedia as $idmidia)
		{
			if ( ! Utilities::isValidUUID4($idmidia)) { // some idmidia are not uid. Ex: tmp_fb_123456
				continue;
			}

			$media = $mediaServ->get($user, $idmidia);

			if ( ! $media)
			{
				error("Media #{$idmidia} does not exist in database.");
				continue;
			}

			$peopleTagged = $media->peopletagged;

			if ($peopleTagged)
			{
				foreach ($peopleTagged as $person)
				{
					if (isset($person['id']))
					{
						$arrIdFacebook[] = $person['id'];
					}
				}
			}
		}

		$movieData = new orm\MovieDataModel();
		$movieData->idmovie = $movie->idmovie;
		$movieData->key     = 'tagged_people';
		$movieData->value   = $arrIdFacebook;

		DataMapperManager::create($movieData);
	}

	/**
	 *
	 * @param type $movie
	 */
	public function getTaggedPeople($movie)
	{
		$key = array();
		$key[] = $movie->idmovie;
		$key[] = 'tagged_people';

		$taggedPeople = DataMapperManager::findByKey('dbsite.moviedata', $key);

		return $taggedPeople ? array_values(array_unique($taggedPeople->value)) : array();
	}

	public function addMovieData($movie, $key, $value = NULL)
	{
		if ( ! $movie)
		{
			throw new \Exception('Missing movie');
		}

		if ( ! $key)
		{
			throw new \Exception('Missing key');
		}

		$movieData = new orm\MovieDataModel();

		$movieData->idmovie = $movie->idmovie;
		$movieData->key = $key;
		$movieData->value = $value;

		DataMapperManager::create($movieData);
	}

	public function getMovieData($movie, $key)
	{
		if ( ! $movie)
		{
			throw new \Exception('Missing movie');
		}

		if ( ! $key)
		{
			throw new \Exception('Missing key');
		}

		$id = array();
		$id[] = $movie->idmovie;
		$id[] = $key;

		return DataMapperManager::findByKey('dbsite.moviedata', $id);
	}

	public function getSecureBlob($movie, $fileName = '')
	{
		if ( ! $movie || ! $fileName)
		{
			return;
		}

		$resultOfRenderUrl = $movie->videourl . "/{$fileName}";

		try
		{
			$client = new Client($resultOfRenderUrl);

			$request = $client->get();

			$response = $request->send();

			$result = $response->json();
		}
		catch (\Exception $ex)
		{
			try
			{
				$posAzureWord = strpos($movie->videourl, zen\Application::$config->melies_azure_cdn_url);

				if ($posAzureWord !== FALSE)
				{
					$azureContainer = zen\Application::$config->melies_recipe_azure_movie_container;
					$blobAddr = "{$movie->iduser}/{$movie->idmovie}/{$fileName}";

					$connectionString = zen\Utilities::getAzureConnectionString($azureContainer);
					$blobRestProxy = ServicesBuilder::getInstance()->createBlobService($connectionString);
					$blob = $blobRestProxy->getBlob("secure", $blobAddr);
					$result = stream_get_contents($blob->getContentStream());
				}
				else
				{
					$result = "Movie {$fileName} file not found.";
				}
			}
			catch (WindowsAzure\Common\ServiceException $ex)
			{
				$result = "Movie {$fileName} file not found. {$ex->getMessage()}";
			}
			catch (\Exception $ex)
			{
				$result = "Movie {$fileName} file not found. {$ex->getMessage()}";
			}
		}

		return $result;
	}

	public function getMovieResultOfRender($movie)
	{
		return $this->getSecureBlob($movie, 'result.of.render.done');
	}

	public function getMoviePostToBlob($movie)
	{
		return $this->getSecureBlob($movie, 'post.to.blob');
	}

	public function getMovieVideoLog($movie)
	{
		return $this->getSecureBlob($movie, 'video.log');
	}

	public function getMovieInput($movie)
	{
		return $this->getSecureBlob($movie, "{$movie->idmovie}.input");
	}

	public function getMovieSentMedias($movie)
	{
		if ( ! $movie)
		{
			$result = "Movie does not exist.";
			return $result;
		}

		$jobServ = serv\JobService::getInstance();

		$job = $jobServ->get($movie->idmovie);

		if ( ! $job) // Old movies
		{
			return array();
		}

		$medias = $job->data;

		return $medias['sentdata']['medias'];
	}

	public function getMovieMediaList($movie)
	{
		if ( ! $movie)
		{
			$result = "Movie does not exist.";
			return $result;
		}

		$jobServ = serv\JobService::getInstance();
		$userServ = serv\UserService::getInstance();

		$user = $userServ->get($movie->iduser);

		$job = $jobServ->get($movie->idmovie);

		if ( ! $job) // Old movies
		{
			return array();
		}

		$jobData = $job->data;

		$meliesMedias = isset($jobData['meliesusedmedias']) ? $jobData['meliesusedmedias'] : array();

		$medias = array();

		if ( ! $meliesMedias)
		{
			$resultOfRender = $this->getMovieResultOfRender($movie);

			if ( ! is_array($resultOfRender))
			{
				$resultOfRender = json_decode($resultOfRender, TRUE);
			}

			if (isset($resultOfRender['medias']))
			{
				$medias = $resultOfRender['medias'];
			}
		}
		else
		{
			$medias = $meliesMedias;
		}

		$returnMedias = $this->getFormattedMedias($medias, $user);

		return $returnMedias;
	}

	public function getFormattedMedias($listIdmedia, $user)
	{
		if ( ! $user)
		{
			throw new \Exception('No user provided.');
		}

		$returnMedias = array();

		foreach ($listIdmedia as $idmedia)
		{
			if ( ! zen\Utilities::isValidUUID4($idmedia))
			{
				continue;
			}

			$fields = array();
			$fields[] = 'idmidia';
			$fields[] = 'iduser';
			$fields[] = 'source';
			$fields[] = 'thumbnail';
			$fields[] = 'filename';
			$fields[] = 'width';
			$fields[] = 'height';
			$fields[] = 'extension';
			$fields[] = 'jsfaces';
			$fields[] = 'peopletagged';

			$keys = array();
			$keys[] = $user->iduser;
			$keys[] = $idmedia;

			$media = orm\DataMapperManager::findByKey('dbstay.midia', $keys, $fields);

			if ($media)
			{
				$returnMedias[] = $media->getAttrs();
			}
		}

		return $returnMedias;
	}

	public function getFormattedSelectorMedias($listMedias, $user = NULL)
	{
		if ( ! $user)
		{
			throw new \Exception('No user provided.');
		}

		$idMedias = array();

		foreach ($listMedias as $media)
		{
			$idMedias[] = $media['midia_id'];
		}

		return $this->getFormattedMedias($idMedias, $user);
	}
}
