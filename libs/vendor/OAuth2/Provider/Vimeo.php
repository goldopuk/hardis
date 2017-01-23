<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class OAuth2_Provider_Vimeo extends OAuth2_Provider
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
		return 'https://api.vimeo.com/oauth/authorize';
	}

	public function url_access_token()
	{
		return 'https://api.vimeo.com/oauth/access_token';
	}

	public function get_user_info(OAuth2_Token_Access $token)
	{
		$t = explode('.',$token->access_token);
		$url = 'https://api.vimeo.com/v1/users/'.http_build_query(array(
			'access_token' => $token->access_token,
		));

		$user = json_decode(file_get_contents($url));
		return array(
			'uid' => $user->data->id,
			'nickname' => $user->data->username,
			'name' => $user->data->full_name,
			'image' => $user->data->profile_picture,
			'urls' => array(
			'website' => $user->data->website,
			),
		);
	}
}
