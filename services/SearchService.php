<?php

namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\Application;
use Stayfilm\stayzen\SolrClient;
use Stayfilm\stayzen\ORM as orm;

/**
 *
 */
class SearchService extends Service
{

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\SearchService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param type $str
	 * @return Solarium\QueryType\Select\Result\Result
	 */
	function searchUsers($str, $limit = 20, $offset = 0)
	{
		$users = array();

		// busca por e-mail
		if (strpos($str, '@') !== FALSE) {

			$userServ = UserService::getInstance();

			$user = $userServ->getUserByEmail($str);

			$list = $user ? array($user) : array();

			return array($list, count($list));

		}
		// busca por tudo
		else
		{
			$userServ = UserService::getInstance();

			$client = Application::getSolrClient('usersearch');

			$query = $client->createSelect();

			//$str = "firstname:*$str* OR lastname:*$str* OR username:*$str*";

			info($str);

			$query->setStart($offset)->setRows($limit);

			$query->setQuery($str);

			list($list, $count) = $client->execute($query, TRUE);

			foreach ($list as $userSearch)
			{
				$user = $userServ->get($userSearch->iduser);

				if ( ! $user)
				{
					$count--;
					continue;
				}

				$users[] = $user;
			}

			return array($users, $count);
		}
	}

	/**
	 *
	 * @param type $str
	 * @return Solarium\QueryType\Select\Result\Result
	 */
	function searchMovies($str, $user = null, $limit = 10, $offset = 0)
	{
		$movieServ = MovieService::getInstance();

		$client = Application::getSolrClient('moviesearch');

		$query = $client->createSelect();

		$query->setStart($offset)->setRows($limit);

		//$str = "firstname:*$str* OR lastname:*$str* OR username:*$str*";

		$userServ = UserService::getInstance();

		if ($user)
		{
			$friends = $userServ->getFriends($user, null);

			$friendList = '';

			foreach ($friends as $friend)
			{
				$friendList .= $friend->idfriend . ' OR ';
			}

			$friendList .= " {$user->iduser}";

			$q = $str;
			info($q);

			$query->setQuery("$q");

			$fq = "(permission:" . orm\MovieModel::PUBLIC_ . " OR (permission:" . orm\MovieModel::FRIEND .
				" AND iduser:({$friendList})) OR (permission:" . orm\MovieModel::PRIVATE_ . " AND iduser:{$user->iduser})) AND status:(1 OR 2)";

			$query->createFilterQuery("fq")->setQuery($fq);
		}
		else
		{
			$query->setQuery($str);

			$fq = "(permission:" . orm\MovieModel::PUBLIC_ . ") AND status:(1 OR 2)";

			$query->createFilterQuery("fq")->setQuery($fq);
		}

		info($str);

		list($items, $movieCount) = $client->execute($query, true);

		$movies = array();

		foreach ($items as $movieSearch)
		{
			$movie = $movieServ->get($movieSearch->idmovie);

			if ( ! $movie)
			{
				continue;
			}

			$movies[] = $movie;
		}

		$movieLiked = array();

		if ($user)
		{
			foreach($movies as $movie)
			{
				$movieLiked[$movie->idmovie] = $movieServ->isLiked($movie, $user);
			}
		}

		return array($movies, $movieCount, $movieLiked);
	}

	/**
	 *
	 * @param string $str
	 * @param array $userFriends list de UserFriendModel
	 * @return array list of UserModel
	 * @throws \Exception
	 */
	function searchFriends($str, $userFriends)
	{
		$userServ = UserService::getInstance();

		$client = Application::getSolrClient('usersearch');

		$query = $client->createSelect();

		if ( ! $userFriends)
		{
			return array();
		}
		else if ( ! is_array($userFriends))
		{
			throw new \Exception('filter must be an array');
		}

		$command = 'iduser:(';

		foreach ($userFriends as $f)
		{
			$command .= ((string)($f->idfriend)) . ' ';
		}

		$command .= ") ";

		$command .= " AND *$str*";

		$query->setQuery("$command");

		$list = $client->execute($query, FALSE);

		$userFriends = array();

		foreach ($list as $userSearch)
		{
			$user = $userServ->get($userSearch->iduser);

			if ( ! $user)
			{
				continue;
			}

			$userFriends[] = $user;
		}

		return $userFriends;
	}

}
