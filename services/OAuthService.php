<?php

namespace Stayfilm\stayzen\services;

use DPZ\Flickr;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen\ORM as orm;

class OAuthService extends Service
{
	const SN_FACEBOOK  = 'facebook';
	const SN_INSTAGRAM = 'instagram';
	const SN_FLICKR    = 'flickr';
	const SN_PICASA    = 'picasa';
	const SN_VIMEO     = 'vimeo';
	const SN_GPLUS     = 'gplus';
	const SN_TWITTER   = 'twitter';

	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\OAuthService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 * @param string $token
	 * @return string
	 */
	function getFacebookUserInfoByToken($token, $type = NULL, $fbAppId = NULL)
	{
		$conf = zen\Utilities::getSnConf('facebook', $type, $fbAppId);
//pre($conf);
		$provider = \OAuth2::provider('facebook', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $token));

		return $provider->get_user_info($token);
	}

	/**
	 *
	 * @param UserModel $user
	 * @param string $sn
	 * @param string $uid
	 * @return type
	 */
	function createUserBySnUID($user, $sn, $uid)
	{
		$model = new orm\UserBySnUIDModel();

		$model->iduser = $user->iduser;
		$model->snuid  = "$sn-$uid";

		return orm\DataMapperManager::create($model);
	}

	/**
	 * @param $token
	 * @return string
	 * @throws \Exception
	 */
	function getInstagramUserInfoByToken($token)
	{
		$conf = zen\Utilities::getSnConf('instagram');
		$provider = \OAuth2::provider('instagram', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $token));

		$infos = $provider->get_user_info($token);

		if ( ! isset($infos['uid']))
		{
			throw new \Exception("wromg userinfo from instagram - missing uid");
		}

		return $infos;
	}

	/**
	 * @param $token
	 * @param string $type
	 * @param null $appId
	 * @return \Google_Person
	 */
	function getGPlusUserInfoByToken($token, $type = 'site', $appId = NULL)
	{
		$conf = $conf = zen\Utilities::getSnConf('gplus', $type, $appId);

		$client = new \Google_Client();
		$client->setClientId($conf['id']);
		$client->setClientSecret($conf['secret']);

		$gplus = new \Google_PlusService($client);

		$client->setAccessToken($token);

		$userData = $gplus->people->get('me');

		return $userData;
	}

	/**
	 * @param $token
	 * @param $secret
	 * @param string $type
	 * @return mixed
	 */
	function getVimeoUserInfoByToken($token, $secret, $type = 'site')
	{
		$conf = zen\Utilities::getSnConf('vimeo', $type);

		$provider = \OAuth::provider('vimeo');

		$consumer = \OAuth::consumer(array(
			'key'    => $conf['id'],
			'secret' => $conf['secret'],
			'scope'  => $conf['scope']
		));

		$options = array('access_token' => $token, 'secret' => $secret);
		$token = \OAuth_Token::forge('Access', $options);

		return $provider->get_user_info($consumer, $token);
	}

	/**
	 * @param $token
	 * @param $secret
	 * @param string $type
	 * @return mixed
	 */
	function getTwitterUserInfoByToken($token, $secret, $type = 'site')
	{
		$conf = zen\Utilities::getSnConf('twitter', $type);

		$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $token, $secret);

		return $twitter->get('account/verify_credentials');
	}

	/**
	 * @param $sn
	 * @param $shortToken
	 * @param string $type
	 * @param null $appId
	 * @return \stdClass
	 * @throws \Exception
	 */
	function getLongLivedToken($sn, $shortToken, $type = 'site', $appId = NULL)
	{
		if ($sn !== 'facebook')
		{
			throw new \Exception("getLongLivedToken not implemented for $sn");
		}

		$conf = zen\Utilities::getSnConf($sn, $type, $appId);

		$provider = \OAuth2::provider('facebook', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $shortToken));

		$longToken = $provider->get_long_lived_token($token);

		$o = new \stdClass();

		foreach ($longToken as $key => $value)
		{
			$o->$key = $value;
		}

		return $o;
	}

	function getLongLivedTokenByFrob($sn, $frob, $type = 'site', $appId = NULL)
	{
		$conf = zen\Utilities::getSnConf($sn, $type, $appId);

		if ($sn === 'facebook' || $sn === 'instagram')
		{
			$conf = zen\Utilities::getSnConf($sn, 'webservices', $appId);

			$provider = \OAuth2::provider($sn, $conf);

			$token = $provider->access($frob);
		}
		else if ($sn === 'flickr')
		{
			$f = new \phpFlickr($conf['id'], $conf['secret']);

			$token = $f->auth_getToken($frob);

			if ( ! $token)
			{
				throw new \Exception("flickr:getToken() failed - frob: $frob");
			}

			if ( ! isset($token['token']) || ! isset($token['user']) || ! isset($token['user']['nsid']))
			{
				throw new \Exception("response from flickr error");
			}
		}
		else
		{
			throw new \Exception("getLongLivedTokenByFrob not implemented for $sn");
		}

		return $token;
	}

	/**
	 *
	 * @param type $user
	 * @param type $sn
	 * @return type
	 * @throws \Exception
	 */
	function getUserInfo($user, $sn)
	{
		$userToken = $this->getToken($user, $sn);

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($sn, NULL, $userToken->appid);

		if ($sn === 'facebook')
		{
			$provider = \OAuth2::provider($sn, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->get_user_info($token);
		}

		if ($sn === self::SN_INSTAGRAM)
		{
			$provider = \OAuth2::provider($sn, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->get_user_info($token);
		}

		if ($sn === "vimeo")
		{
			$provider = \OAuth::provider('vimeo');

			$consumer = \OAuth::consumer(array(
				'key'    => $conf['id'],
				'secret' => $conf['secret'],
				'scope'  => $conf['scope']
			));

			$options = array('access_token' => $userToken->accesstoken, 'secret' => $userToken->secret);
			$token = \OAuth_Token::forge('Access', $options);

			return $provider->get_user_info($consumer, $token);
		}

		if ($sn === self::SN_FLICKR)
		{
			$flickr = new \phpFlickr($conf['id'], $conf['secret']);
			$flickr->token = $userToken->accesstoken;

			return $flickr->people_getInfo($userToken->uid);
		}

		if ($sn === self::SN_GPLUS)
		{
			$client = new \Google_Client();
			$client->setClientId($conf['id']);
			$client->setClientSecret($conf['secret']);
			$service = new \Google_PlusService($client);

			$client->setAccessToken($userToken->accesstoken);

			return $service->people->get('me');
		}

		if ($sn === self::SN_TWITTER)
		{
			$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $userToken->accesstoken, $userToken->secret);

			return $twitter->get('account/verify_credentials');
		}
	}

	/**
	 * @param $user
	 * @return mixed
	 * @throws \Exception
	 */
	function getPostInfo($user)
	{
		$userToken = $this->getToken($user, 'facebook');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf('facebook', NULL, $userToken->appid);

		$provider = \OAuth2::provider('facebook', $conf);

		if ($userToken->expire < time())
		{
			throw new \Exception("No token");
		}

		$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

		return $provider->getPostInfo($token);
	}

	/**
	 * @param $user
	 * @param $movie
	 * @param $link
	 * @param array $uidsTagged
	 * @param string $adText
	 * @return mixed
	 * @throws \Exception
	 */
	function shareMovieToFacebook($user, $movie, $link, $uidsTagged = array(), $adText = '')
	{
		$movieServ = serv\MovieService::getInstance();

		$params = array();
		$params['picture'] = $movieServ->getImageUrl($movie);
		$params['link'] = $link;
		$params['name'] = $movie->title;
		$params['description'] = $movie->synopsis;

		// Facebook does not allow automatic text to be attached on user's text
		// when publishing on Facebook.
		//$params['description'] = "{$movie->synopsis} {$adText}";


		if (count($uidsTagged) > 0)
		{
			// place = Stayfilm page on Facebook
			$params['tags'] = implode(',', $uidsTagged);
			$params['place'] = zen\Application::$config->id_stayfilm_facebook_page;
		}

		if (STAYZEN_ENV === 'prod')
		{
			$params['privacy'] = "{'value':'EVERYONE'}";
		}
		else
		{
			$params['privacy'] = "{'value':'SELF'}";
		}

		$userToken = $this->getToken($user, 'facebook');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf('facebook', NULL, $userToken->appid);

		$provider = \OAuth2::provider('facebook', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

		// Set BI Data for facebook movie sharing
		info('PUBLISHING TO FACEBOOK');
		$biData = $movie->bidata ? $movie->bidata : array();
		$biData['shared_on_facebook'] = 1;
		$movie->bidata = $biData;
		$movieServ->update($movie);
		info($movie);

		return $provider->feed($token, $params);
	}

	/**
	 * @param $user
	 * @param $text
	 * @return mixed
	 * @throws \Exception
	 */
	function notifyRegisterToFacebook($user, $text)
	{
		$params = array();
		$params['message'] = $text;
		$params['link']    = 'http://www.stayfilm.com';

		if (STAYZEN_ENV === 'prod')
		{
			$params['privacy'] = "{'value':'EVERYONE'}";
		}
		else
		{
			$params['privacy'] = "{'value':'SELF'}";
		}

		$userToken = $this->getToken($user, 'facebook');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf('facebook', NULL, $userToken->appid);

		$provider = \OAuth2::provider('facebook', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

		return $provider->feed($token, $params);
	}

	/**
	 * @param $userToken
	 * @return bool
	 * @throws \Exception
	 */
	function isValidToken($userToken)
	{
		info('isValidToken()');

		if ( ! $this->isValidSN($userToken->socialnetwork))
		{
			throw new \Exception("Invalid social networks {$userToken->socialnetwork}");
		}

		if ( ! $userToken->uid)
		{
			return FALSE;
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		if ($userToken->socialnetwork === self::SN_FACEBOOK)
		{
			$provider = \OAuth2::provider($userToken->socialnetwork, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->is_valid_token($token);
		}

		if ($userToken->socialnetwork === self::SN_INSTAGRAM)
		{

			$provider = \OAuth2::provider($userToken->socialnetwork, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->is_valid_token($token);
		}

		if ($userToken->socialnetwork === self::SN_FLICKR)
		{
			$this->convertOldFlickrTokenIfNecessary($userToken);

			$flickr = new \phpFlickr($conf['id'], $conf['secret']);

			$snVersion = zen\Application::$config->socialnetwork_version;

			if ($snVersion === 1)
			{
				$flickr->setToken($userToken->accesstoken);

				return (boolean)$flickr->auth_checkToken();
			}
			else if ($snVersion === 2)
			{
				if ( ! isset($userToken->data['oauth_access_token']) || ! isset($userToken->data['oauth_access_token_secret']))
				{
					return FALSE;
				}

				$flickr = new Flickr($conf['id'], $conf['secret']);

				return $flickr->isValidOauthToken($userToken->data['oauth_access_token'], $userToken->data['oauth_access_token_secret']);
			}
		}

		if ($userToken->socialnetwork === self::SN_VIMEO)
		{
			$provider = \OAuth::provider('vimeo');

			$consumer = \OAuth::consumer(array(
				'key'    => $conf['id'],
				'secret' => $conf['secret'],
				'scope'  => $conf['scope']
			));

			$options = array('access_token' => $userToken->accesstoken, 'secret' => $userToken->secret);
			$token = \OAuth_Token::forge('Access', $options);

			try
			{
				return (boolean)$provider->get_user_info($consumer, $token);
			}
			catch (\Exception $e)
			{
				info($e);
				return false;
			}
		}

		if ($userToken->socialnetwork === self::SN_GPLUS)
		{
			try
			{
				$this->getGPlusUserInfoByToken($userToken->accesstoken, NULL, $userToken->appid);
				return TRUE;
			}
			catch (\Exception $e)
			{
				try
				{
					$this->refreshToken($userToken);
					return TRUE;
				}
				catch (\Exception $e)
				{
					return FALSE;
				}
			}
		}

		if ($userToken->socialnetwork === self::SN_TWITTER)
		{
//			$connection = new \TwitterOAuth($conf['id'], $conf['secret'], $userToken->accesstoken, $userToken->secret);
//
//			$result = $connection->get('account/verify_credentials');

			return true; // TODO
		}
	}

	function convertOldFlickrTokenIfNecessary($userToken)
	{
		if ($userToken->socialnetwork !== 'flickr')
		{
			throw new \Exception('token invalid, should be flickr');
		}

		if (zen\Application::$config->socialnetwork_version !== 2)
		{
			return;
		}

		if	($userToken->data && is_array($userToken->data) && isset($userToken->data['oauth_access_token_secret']))
		{
			return;
		}

		$conf = zen\Utilities::getSnConf('flickr', NULL, $userToken->appid);

		info('Converting old flickr token');

		$data = array();

		$flickr = new Flickr($conf['id'], $conf['secret']);

		$flickr->convertOldToken($userToken->accesstoken);

		$data['oauth_access_token_secret'] = $flickr->getOauthData(Flickr::OAUTH_ACCESS_TOKEN_SECRET);
		$data['oauth_access_token']        = $flickr->getOauthData(Flickr::OAUTH_ACCESS_TOKEN);

		$userToken->data = $data;

		$this->updateUserToken($userToken);
	}

	/**
	 * @param $userToken
	 * @return type
	 * @throws \Exception
	 */
	function refreshToken($userToken)
	{
		if ($userToken->socialnetwork !== 'gplus')
		{
			throw new \Exception("RefreshToken does not exist for sn {$userToken->socialnetwork}");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		$client = new \Google_Client();

		$client->setClientId($conf['id']);
		$client->setClientSecret($conf['secret']);

		$client->setAccessToken($userToken->accesstoken);

		$json = $userToken->accesstoken;

		$token = json_decode($json, TRUE);

		$client->refreshToken($token['refresh_token']);

		$newToken = $client->getAuth()->token;

		$userServ = serv\UserService::getInstance();
		$user = $userServ->get($userToken->iduser);

		$newToken = $this->addToken($user, $userToken->socialnetwork, json_encode($newToken), $userToken->uid);

		return $newToken;
	}

	/**
	 * @param $user
	 * @param $sn
	 * @throws \Exception
	 */
	function refreshTokenByUser($user, $sn)
	{
		$userToken = $this->getToken($user, $sn);

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		if ($sn === 'gplus')
		{
			$this->refreshToken($userToken);
		}
	}

	/**
	 *
	 * @param type $userToken
	 * @param type $permission
	 * @return type
	 * @throws \Exception
	 */
	function tokenHasPermission($userToken, $permission)
	{
		if ( $userToken->socialnetwork !== 'facebook')
		{
			throw new \Exception("Token should be Facebook but is {$userToken->socialnetwork}");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		$provider = \OAuth2::provider($userToken->socialnetwork, $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

		return $provider->tokenHasPermission($token, $permission);
	}

	/**
	 * @param $user
	 * @param $sn
	 * @param $token
	 * @param $uid
	 * @param null $expire
	 * @param null $secret
	 * @param string $type
	 * @param null $appId
	 * @return orm\UserTokenModel
	 * @throws \Exception
	 */
	function addToken($user, $sn, $token, $uid, $expire = null, $secret = null, $type = 'site', $appId = NULL)
	{
		if ( ! self::isValidSN($sn))
		{
			throw new \Exception("Invalid social networks $sn");
		}

		if ( ! $uid)
		{
			throw new \Exception("Missing uid for $sn. ");
		}

		$conf = zen\Utilities::getSnConf($sn, $type, $appId);

		$userToken = new orm\UserTokenModel();

		$userToken->iduser        = $user->iduser;
		$userToken->socialnetwork = $sn;
		$userToken->uid           = $uid;
		$userToken->accesstoken   = $token;
		$userToken->appid         = $conf['id'];

		if ($expire)
		{
			$userToken->expire = $expire;
		}

		if ($secret)
		{
			$userToken->secret = $secret;
		}

		orm\DataMapperManager::create($userToken);

		$this->createUserBySnUID($user, $sn, $uid);

		if ($userToken->socialnetwork === 'flickr')
		{
			$this->convertOldFlickrTokenIfNecessary($userToken);
		}

		return $userToken;
	}

	public function updateUserToken($usertoken)
	{
		orm\DataMapperManager::update($usertoken);
	}

	/**
	 *
	 * @param type $user
	 * @param type $sn
	 * @return type
	 * @throws \Exception
	 */
	function getToken($user, $sn)
	{
		if ( ! self::isValidSN($sn))
		{
			throw new \Exception("Invalid social networks $sn");
		}

		return orm\DataMapperManager::findByKey('dbsite.usertoken', array($user->iduser, $sn));
	}

	/**
	 *
	 * @param type $user
	 * @param type $sn
	 * @return type
	 * @throws \Exception
	 */
	function getAllTokens($user)
	{
		if ( ! $user)
		{
			throw new \Exception("Invalid user.");
		}

		return orm\DataMapperManager::findAllBy('dbsite.usertoken', array('iduser'), array($user->iduser));
	}

	/**
	 * @param $user
	 * @param $sn
	 * @param int $limit
	 * @param int $offset
	 * @return bool
	 * @throws \Exception
	 */
	function getFriends($user, $sn, $limit = 10, $offset = 0)
	{
		$userToken = $this->getToken($user, $sn);

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		if ($sn === 'facebook')
		{
			$provider = \OAuth2::provider($sn, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->get_user_friends($token, $limit, $offset);
		}

		if ($sn === self::SN_FLICKR)
		{
			$flickr = new \phpFlickr();
			$flickr->load($conf['id'], $conf['secret']);
			$flickr->token = $userToken->accesstoken;

			return $flickr->contacts_getList();
		}

		if ($sn === self::SN_GPLUS)
		{
			throw new \Exception('Invalid network to this method. Use OAuthService::getGPlusFriends()');
		}

		if ($sn === self::SN_TWITTER)
		{
			throw new \Exception('Invalid network to this method. Use OAuthService::getTwitterFriends()');
		}
	}

	function getFacebookFriends($user, $limit = 100, $offset = 0)
	{
		$userToken = $this->getToken($user, 'facebook');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		$provider = \OAuth2::provider('facebook', $conf);

		$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

		return $provider->get_user_friends($token, $limit, $offset);
	}

	/**
	 * @param $user
	 * @param null $tokenNextPage
	 * @return \Google_PeopleFeed
	 */
	function getGPlusFriends($user, $tokenNextPage = NULL)
	{
		$userToken = $this->getToken($user, 'gplus');

		if ( ! $this->isValidToken($userToken))
		{
			$userToken = $this->refreshToken($userToken);
		}

		$client = new \Google_Client();
		$service = new \Google_PlusService($client);

		$client->setAccessToken($userToken->accesstoken);

		$params = array('maxResults' => 10, 'orderBy' => 'best');

		if ($tokenNextPage)
		{
			$params['pageToken'] = $tokenNextPage;
		}

		return $service->people->listPeople('me', 'visible', $params);
	}

	/**
	 *
	 * @param type $user
	 * @param type $sn
	 * @return type
	 * @throws \Exception
	 */
	function getFollowers($user, $sn)
	{
		$userToken = $this->getToken($user, $sn);

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		if ($sn === self::SN_INSTAGRAM)
		{
			$provider = \OAuth2::provider($sn, $conf);

			$token = \OAuth2_Token::factory('access', array('access_token' => $userToken->accesstoken));

			return $provider->get_user_followers($token);
		}

		throw new \Exception("getFollowers not implemented $sn");
	}

	/**
	 * @param $user
	 * @param null $cursor
	 * @param int $count
	 * @return mixed
	 * @throws \Exception
	 */
	function getTwitterFriends($user, $cursor = NULL, $count = 20)
	{
		$userToken = $this->getToken($user, 'twitter');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $userToken->accesstoken, $userToken->secret);

		$friendList = new \stdClass();
		$friendList->users = array();

		if (isset($cursor))
		{
			$friendList = $twitter->get('friends/list', array('cursor' => $cursor, 'count' => $count));
		}
		else
		{
			$friendList = $twitter->get('friends/list');
		}

		return $friendList->users;
	}

	/**
	 * @param $user
	 * @param null $cursor
	 * @param int $count
	 * @return \stdClass
	 * @throws \Exception
	 */
	function getTwitterFollowers($user, $cursor = NULL, $count = 20)
	{
		$userToken = $this->getToken($user, 'twitter');

		if ( ! $userToken)
		{
			throw new \Exception("No token");
		}

		$conf = zen\Utilities::getSnConf($userToken->socialnetwork, NULL, $userToken->appid);

		$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $userToken->accesstoken, $userToken->secret);

		$followers = new \stdClass();
		$followers->users = array();

		if (isset($cursor))
		{
			$followers = $twitter->get('followers/list', array('cursor' => $cursor, 'count' => $count));
		}
		else
		{
			$followers = $twitter->get('followers/list');
		}

		return $followers;
	}

	/**
	 *
	 * @param UserModel $user
	 * @return array
	 */
	function getUserNetworks($user)
	{
		$tokens = orm\DataMapperManager::findAllBy('dbsite.usertoken', 'iduser', $user->iduser);

		$list = array();

		foreach ($tokens as $token)
		{
			$list[] = $token->socialnetwork;
		}

		return $list;
	}

	/**
	 * @param $user
	 * @return array
	 */
	function getUserTokens($user)
	{
		return orm\DataMapperManager::findAllBy('dbsite.usertoken', 'iduser', $user->iduser);
	}

	/**
	 * @param $user
	 */
	function removeInvalidTokens($user)
	{
		$tokens = $this->getUserTokens($user);

		foreach ($tokens as $userToken)
		{
			if ( ! $this->isValidToken($userToken))
			{
				$this->removeToken($userToken);
			}
		}
	}

	/**
	 * @param $userToken
	 */
	function removeToken($userToken)
	{
		return orm\DataMapperManager::delete($userToken);
	}

	/**
	 *
	 * @param type $sn
	 * @return type
	 */
	static function isValidSN($sn)
	{
		return in_array($sn, self::getSocialNetworks());
	}

	/**
	 *
	 * @return type
	 */
	static function getSocialNetworks()
	{
//
//               0 |     Stayfilm
//               1 |     Facebook
//               2 |      Twitter
//               4 |       Flickr
//              10 | teste backup
//               5 |        Vimeo
//               6 |       Picasa
//               3 |    Instagram
		return array(1 => self::SN_FACEBOOK, 3 => self::SN_INSTAGRAM, 4 => self::SN_FLICKR,
			5 => self::SN_VIMEO, 7 => self::SN_GPLUS, 2 => self::SN_TWITTER);
	}

	public function createTokenFromShortLivedToken($user, $shortLivedToken, $appId, $type = 'site', $uid = NULL)
	{
		if ( ! $user)
		{
			throw new \Exception('Missing user.');
		}

		if ( ! $shortLivedToken)
		{
			throw new \Exception('Missing shortLivedToken.');
		}

		if ( ! $appId)
		{
			throw new \Exception('Missing appId.');
		}

		$uid = $uid ? $uid : $user->idfacebook;

		if ( ! $uid)
		{
			throw new \Exception('Missing uid.');
		}

		$longLivedToken = $this->getLongLivedToken('facebook', $shortLivedToken, $type, $appId);

		$this->addToken($user, serv\OAuthService::SN_FACEBOOK, $longLivedToken->access_token, $uid, $longLivedToken->expires_in, NULL, $type, $appId);
	}

}
