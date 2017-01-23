<?php
use Stayfilm\stayzen as zen;
use Guzzle\Http\Client;

/**
 * Facebook OAuth2 Provider
 *
 * @package    CodeIgniter/OAuth2
 * @category   Provider
 * @author     Phil Sturgeon
 * @copyright  (c) 2012 HappyNinjas Ltd
 * @license    http://philsturgeon.co.uk/code/dbad-license
 *
 * This class was updated by Fabiano Simões - fabiano@stayfilm.com
 */

class OAuth2_Provider_Facebook extends OAuth2_Provider
{
	protected $scope = array('offline_access', 'email', 'read_stream');

	public function url_authorize()
	{
		return 'https://www.facebook.com/dialog/oauth';
	}

	/**
	* Get URL logout from facebook
	*/
	public function url_logout($urlRedirect, $accessToken)
	{
		return 'https://www.facebook.com/logout.php?next=' . $urlRedirect . '&access_token=' . $accessToken;
	}

	public function url_access_token()
	{
		return 'https://graph.facebook.com/v2.3/oauth/access_token';
	}

	public function get_long_lived_token(OAuth2_Token_Access $shortToken)
	{
		$params = array(
			'client_id' 	=> $this->client_id,
			'client_secret' => $this->client_secret,
			'grant_type' 	=> 'fb_exchange_token',
			'fb_exchange_token' => (string)$shortToken
		);

		$response = null;

		$url = $this->url_access_token();

		$url .= '?' . http_build_query($params);

		debug($url);

		$client = new Client($url);
		$request = $client->get();

		$return = NULL;

		try
		{
			$response = $request->send();

			if ( ! ($resp = json_decode($response->getBody(), TRUE)))
			{
				parse_str($response->getBody(), $return);
			}
			else
			{
				$return = $resp;
			}
		}
		catch (\Exception $ex)
		{
			$fbErrorInfo = zen\Utilities::getGuzzleFormattedExceptionMessage($ex);
			$fbErrorInfo['message'] = 'Error while trying to fetch Facebook Long Lived Token. URL: ' . $url;

			$fbException = new zen\Exception\FacebookOauthException(json_encode($fbErrorInfo), 1, $ex);
			throw $fbException;
		}

		return $return;
	}

	public function get_user_info(OAuth2_Token_Access $token)
	{
		debug(__METHOD__);

		$params = array();
		$params['access_token'] = $token->access_token;

		$url = 'https://graph.facebook.com/v2.3/me?' . http_build_query($params);

		debug('OpenGraph URL: '.$url);

		$client = new Client($url);

		$request = $client->get();

		$response = NULL;

		try
		{
			$response = $request->send();
		}
		catch (\Exception $ex)
		{
			$fbErrorInfo = zen\Utilities::getGuzzleFormattedExceptionMessage($ex);
			$fbErrorInfo['message'] = 'Error while trying to access Facebook API.';

			$fbException = new zen\Exception\FacebookOauthException(json_encode($fbErrorInfo), 1, $ex);
			throw $fbException;
		}

		$user = $response->json();

		if ( ! $user)
		{
			$fbException = new zen\Exception\FacebookOauthException("Facebook did not returned a valid user. URL: " . $url, 1);
			throw $fbException;
		}

		debug('Fetched user data from facebook');
		debug(json_encode($user));

		// Create a response from the request
		return array(
			'exists' => true,
			'uid' => $user['id'],
			'nickname' => isset($user['username']) ? $user['username'] : null,
			'name' => $user['name'],
			'first_name' => $user['first_name'],
			'last_name' => $user['last_name'],
			'birthday' => isset($user['birthday']) ? $user['birthday'] : null,
			'email' => isset($user['email']) ? $user['email'] : null,
			'location' => isset($user['hometown']['name']) ? $user['hometown']['name'] : null,
			'description' => isset($user['bio']) ? $user['bio'] : null,
			'gender' => isset($user['gender']) ? $user['gender'] : null,
			'language' => isset($user['locale']) ? $user['locale'] : null,
			'image' => 'https://graph.facebook.com/v2.3/me/picture?type=square&width=218&height=200&access_token='.$token->access_token,
			'urls' => array(
			'Facebook' => $user['link']
			),
			'access_token' => $token->access_token
		);
	}

	public function get_user_friends(OAuth2_Token_Access $token, $limit = 10, $offset = 0)
	{
		// If comes a pageUrl, so we are getting the next user friends page from Facebook Oauth

		$params = array();
		$params['access_token'] = $token->access_token;
		//$params['fields'] = 'username, name';
		$params['limit'] = $limit;
		$params['offset'] = $offset;

		$url = 'https://graph.facebook.com/v2.3/me/friends?' . http_build_query($params);

		debug("Facebook Graph to get friends: " . $url);

		$client = new Client($url);

		$request = $client->get();

		$response = NULL;

		try
		{
			$response = $request->send();
		}
		catch (\Exception $ex)
		{
			$fbErrorInfo = zen\Utilities::getGuzzleFormattedExceptionMessage($ex);
			$fbErrorInfo['message'] = 'Error while trying to access Facebook API.';

			$fbException = new zen\Exception\FacebookOauthException(json_encode($fbErrorInfo), 1, $ex);
			throw $fbException;
		}

		$userData = $response->json();

		if ( ! $userData)
		{
			$fbException = new zen\Exception\FacebookOauthException("Fail to get friends data from Facebook. URL: " . $url, 1);
			throw $fbException;
		}

		return $userData;
	}

	public function feed(OAuth2_Token_Access $token, $params)
	{
		// If comes a pageUrl, so we are getting the next user friends page from Facebook Oauth

		$default = array();
		$default['access_token'] = $token->access_token;

		$params = array_merge($params, $default);

		if (STAYZEN_ENV === 'prod')
		{
			$params['privacy'] = "{'value':'EVERYONE'}";
		}
		else
		{
			$params['privacy'] = "{'value':'SELF'}";
		}

		$url = "https://graph.facebook.com/v2.3/me/feed?" . http_build_query($params);

		debug($url);
		$client = new Client($url);

		$request = $client->post()->addPostFields($params);

		$response = $request->send();

		$json = $response->json();

		return $json['id'];
	}

	public function tokenHasPermission(OAuth2_Token_Access $token, $permission = '')
	{
		$default = array();
		$default['access_token'] = $token->access_token;

		$url = "https://graph.facebook.com/v2.3/me/permissions?" . http_build_query($default);
		debug($url);

		$client = new Client($url);

		try {
			$request = $client->get();
			$response = $request->send();
		} catch (\Exception $e) {
			return FALSE;
		}

		$json = $response->json();

		if ( ! isset($json['data']))
		{
			return FALSE;
		}

		$hasPermission = FALSE;

		if (isset($json['data']) && is_array($json['data']))
		{
			foreach ($json['data'] as $data)
			{
				// This check is applied here because different versions of
				// Facebook API contains different responses.
				if (isset($data['permission']) && isset($data['status']))
				{
					if ($data['permission'] === $permission && $data['status'] === 'granted')
					{
						$hasPermission = TRUE;
						break;
					}
				}
				else
				{
					if (isset($data[$permission]) && $data[$permission] === 1)
					{
						$hasPermission = TRUE;
						break;
					}
				}
			}
		}

		return $hasPermission;
	}

	function is_valid_token(OAuth2_Token_Access $token)
	{
		try
		{
			$info = $this->get_user_info($token);
			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
	}

}
