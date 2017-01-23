<?php
use Stayfilm\stayzen as zen;

class OAuth_Provider_Vimeo extends OAuth_Provider {

	public $name = 'vimeo';

	public function url_request_token()
	{
		return 'https://vimeo.com/oauth/request_token';
	}

	public function url_authorize()
	{
		return 'https://vimeo.com/oauth/authorize';
	}

	public function url_access_token()
	{
		return 'https://vimeo.com/oauth/access_token';
	}

	public function get_user_info(OAuth_Consumer $consumer, OAuth_Token $token)
	{
		// Create a new GET request with the required parameters
		$request = OAuth_Request::forge('resource', 'GET', 'https://vimeo.com/api/rest/v2', array(
			'oauth_consumer_key' => $consumer->key,
			'oauth_token' => $token->access_token,
			'nojsoncallback' => 1,
			'format' => 'json',
			'method' => 'vimeo.people.getInfo',
		));

		// Sign the request using the consumer and token
		$request->sign($this->signature, $consumer, $token);

		$response = json_decode($request->execute(), true);

		if ($response['stat'] === 'fail')
		{
			$vimeoErrorInfo = array();
			$vimeoErrorInfo['vimeo_error'] = $response['err'];
			$vimeoErrorInfo['message'] = "Error fecthing user info from vimeo - {$response['err']['msg']}";
			$vimeoException = new zen\Exception\VimeoOauthException(json_encode($vimeoErrorInfo), 1);
			throw $vimeoException;
		}

		// Create a response from the request
		return array(
			'uid' => $response['person']['id'],
			'name' => $response['person']['display_name'],
			'nickname' => $response['person']['username'],
		);
	}
}