<?
use Stayfilm\stayzen as zen;
use Guzzle\Http\Client;

/**
 * Instagram OAuth2 Provider
 *
 * @package    CodeIgniter/OAuth2
 * @category   Provider
 * @author     Phil Sturgeon
 * @copyright  (c) 2012 HappyNinjas Ltd
 * @license    http://philsturgeon.co.uk/code/dbad-license
 */

class OAuth2_Provider_Instagram extends OAuth2_Provider
{
	/**
	 * @var  string  scope separator, most use "," but some like Google are spaces
	 */
	public $scope_seperator = '+';

	/**
	 * @var  string  the method to use when requesting tokens
	 */
	public $method = 'POST';

	public function url_authorize()
	{
		return 'https://api.instagram.com/oauth/authorize';
	}

	public function url_access_token()
	{
		return 'https://api.instagram.com/oauth/access_token';
	}

	public function authorize($options = array())
	{
		debug(__METHOD__);

		$state = md5(uniqid(rand(), TRUE));
		get_instance()->session->set_userdata('state', $state);

		$params = array(
			'client_id' 		=> $this->client_id,
			'redirect_uri' 		=> isset($options['redirect_uri']) ? $options['redirect_uri'] : $this->redirect_uri,
			'state' 			=> $state,
			'scope'				=> is_array($this->scope) ? implode($this->scope_seperator, $this->scope) : $this->scope,
			'response_type' 	=> 'code',
			'state'				=> isset($options['state']) ? $options['state'] : null,
			'approval_prompt'   => 'force' // - google force-recheck
		);

		$url = $this->url_authorize().'?'.http_build_query($params);

		debug($url);

		return $url;
	}

	public function get_user_info(OAuth2_Token_Access $token)
	{
		$t = explode('.',$token->access_token);
		$url = 'https://api.instagram.com/v1/users/' . $t[0] . '?'.http_build_query(array(
			'access_token' => $token->access_token,
		));

		debug($url);

		$client = new Client($url);
		$request = $client->get();

		$response = NULL;

		try
		{
			$response = $request->send();
		}
		catch (\Exception $ex)
		{
			$instagramErrorInfo = zen\Utilities::getGuzzleFormattedExceptionMessage($ex);
			$instagramErrorInfo['message'] = 'Error while trying to fetch user info from Instagram. URL: ' . $url;

			$instagramException = new zen\Exception\InstagramOauthException(json_encode($instagramErrorInfo), 1, $ex);
			throw $instagramException;
		}

		$user = $response->json();

		if ( ! $user)
		{
			$fbException = new zen\Exception\InstagramOauthException("Instagram did not returned a valid user. URL: " . $url, 1);
			throw $fbException;
		}

		return array(
			'uid' => $user['data']['id'],
			'nickname' => $user['data']['username'],
			'name' => $user['data']['full_name'],
			'image' => $user['data']['profile_picture'],
			'urls' => array(
			'website' => $user['data']['website'],
			),
			'access_token' => $token->access_token
		);
	}

	public function get_user_followers(OAuth2_Token_Access $token)
	{
		$t = explode('.',$token->access_token);
		$url = "https://api.instagram.com/v1/users/{$t[0]}/followed-by?".http_build_query(array(
			'access_token' => $token->access_token,
		));

		debug($url);

		return json_decode(file_get_contents($url));
	}

	function is_valid_token(OAuth2_Token_Access $token)
	{
		try {

			$info = $this->get_user_info($token);
			return true;
		}
		catch (Exception $e)
		{
			return false;
		}
	}
}
