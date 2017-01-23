<?
namespace Stayfilm\stayzen\email;

use Stayfilm\stayzen as zen;

class AutomaticallyCreatedMovie extends AbsEmail
{
	public $user  = null;
	public $movie = null;

	function configure($user, $movie = NULL)
	{
		$this->user  = $user;
		$this->movie = $movie;
	}

	function getBody()
	{
		$url = zen\Application::$config->base_url . '/movie/watch/' . $this->movie->idmovie;

		$html =<<<TEXT
	<div>Automatically created movie</div>
	Check it out aqui<br />";
	<a href="$url">$url</a>
TEXT;
		return $html;
	}

	function getEmail()
	{
		return $this->user->email;
	}

	function getSubject()
	{
		$params = array();
		$params[] = 'title';

		return "Seu filme {0} foi criado AUTOMATICALLY.";
	}

}
