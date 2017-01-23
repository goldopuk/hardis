<?php
namespace Stayfilm\stayzen\zend\logger\Formatter;

use Zend\Log\Formatter\Simple;

class SimpleFormatter extends Simple
{

	public function format($event)
	{
		$message = parent::format($event);

		if (is_array($message) || is_object($message)) {
			$message = print_r($message, true);
		}

		if (class_exists('UserSession'))
		{
			$message = (\UserSession::getUsername() ? \UserSession::getUsername() : 'guest') . ' - '  . $message;
		}
		else
		{
			$message = "unknown - $message";
		}

		$message = str_replace("\n", " ", $message);
		$message = str_replace("\r", " ", $message);

		return preg_replace('/[\s\n\r]+/', ' ', $message);
	}

}
