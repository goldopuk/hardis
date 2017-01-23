<?php
namespace Stayfilm\stayzen\email;

class AbsEmail
{
	public $subject;

	function getRecipientUser()
	{
		return ($this && isset($this->user) && $this->user ? $this->user : null);
	}

	function getSubject()
	{
		return $this->subject;
	}

	function getBody()
	{
		return 'body';
	}

	function getEmail()
	{
		throw new \Exception("get Email missing");
	}

	function getBestLanguage($recipient, $sender = NULL)
	{
		$lang = $recipient->getLang();

		if ( ! $lang && $sender)
		{
			$lang = $sender->getLang();
		}

		$lang =  getLang($lang);

		debug($lang, 'Best language');

		return $lang;
	}

	function getBestLocale($recipient, $sender = NULL)
	{
		$locale = $recipient->languages;

		if ( ! $locale && $sender)
		{
			$locale = $sender->languages;
		}

		debug($locale, 'Best locale');

		return $locale ? $locale : 'en_US';
	}

}
