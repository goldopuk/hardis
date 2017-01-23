<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen as zen;

class TwitterService extends Service
{
	const MAX_MESSAGE_CHACATER = 140;
	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\TwitterService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param type $tokenFrom
	 * @param type $securityTokenFrom
	 * @param type $idTo
	 * @param type $screenNameTo
	 * @param type $message
	 * @throws \Exception
	 */
	public function inviteSF($token, $securityToken, $idtwitter, $message)
	{
		$conf = zen\Utilities::getSnConf('twitter');

		if(strlen($message) > self::MAX_MESSAGE_CHACATER)
		{
			throw new \Exception("Message can't have more than 140 characteres.");
		}

		$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $token, $securityToken);

		return $twitter->post('direct_messages/new', array('user_id' => $idtwitter, 'text' => $message));
	}

	public function shareMovie($token, $securityToken, $movieURL, $message, $movie)
	{
		$conf = zen\Utilities::getSnConf('twitter');

		$twitter = new \TwistOAuth($conf['id'], $conf['secret'], $token, $securityToken);

		$twitterConf = $twitter->get('help/configuration');

		$hashStayfilm = ' #stayfilm';

		// +1 to the space charactere
		$message = $message ? substr($message, 0, self::MAX_MESSAGE_CHACATER - ($twitterConf->short_url_length_https + 1) - strlen($hashStayfilm))  : '';

		$message = "{$movieURL} {$message} #stayfilm";

		try
		{
			$url = "{$movie->videourl}/640x360_n.jpg";
			$imageFile = sys_get_temp_dir() . "/{$movie->idmovie}.jpg";

			$ctx = stream_context_create(array(
					'http' => array(
						'timeout' => 5
					)
				)
			);

			$binary  = @file_get_contents($url, 0, $ctx);

			if ($binary)
			{
				@file_put_contents($imageFile, $binary);
			}

			if (file_exists($imageFile) && filesize($imageFile) <= $twitterConf->photo_size_limit)
			{
				$twitter->postMultipart('statuses/update_with_media', array(
					'status' => $message,
					'@media[]' => $imageFile
				));
			}
			else
			{
				$twitter->post('statuses/update', array('status' => $message));
			}
		}
		catch (\TwistException $e)
		{
//			$error = $e->getMessage();
//			$code  = $e->getCode() ?: 500;
		}

		if (file_exists($imageFile))
		{
			unlink($imageFile);
		}
	}

	public function announceMovie($token, $securityToken, $idtwitter, $message)
	{
		$conf = zen\Utilities::getSnConf('twitter');

		if(strlen($message) > self::MAX_MESSAGE_CHACATER)
		{
			throw new \Exception("Message can't have more than 140 characteres.");
		}

		$twitter = new \TwistOAuth($conf->id, $conf->secret, $token, $securityToken);

		return $twitter->post('direct_messages/new', array('user_id' => $idtwitter, 'text' => $message));//'screen_name' => ?,
	}

}
