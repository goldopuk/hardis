<?php
if ( ! function_exists('pre'))
{
	function pre($v)
	{
		echo '<pre>';
		print_r($v);
		echo '</pre>';
	}
}

function warn()
{
	$args = func_get_args();
	$message = call_user_func_array('_formatLog', $args);
	Stayfilm\stayzen\Application::$logger->warn($message);
}

function info()
{
	$args = func_get_args();
	$message = call_user_func_array('_formatLog', $args);
	Stayfilm\stayzen\Application::$logger->info($message);
}

function error()
{
	$args = func_get_args();
	$message = call_user_func_array('_formatLog', $args);
	Stayfilm\stayzen\Application::$logger->crit($message);
}

function debug()
{
	$args = func_get_args();
	$message = call_user_func_array('_formatLog', $args);
	Stayfilm\stayzen\Application::$logger->debug($message);
}

// @codingStandardsIgnoreStart
function _formatLog()
// @codingStandardsIgnoreStop
{
	$args = func_get_args();

	$list = array();

	foreach ($args as $arg)
	{
		if (is_array($arg) || is_object($arg)) {
			$list[] = print_r($arg, true);
		}
		else
		{
			$list[] = $arg;
		}
	}

	return implode('| ', $list);
}

// from codeigniter. it is used by the libs OAuth
if ( ! function_exists('random_string')) {

	function random_string($type = 'alnum', $len = 8)
	{
		switch($type)
		{
			case 'basic'	: return mt_rand();
				break;
			case 'alnum'	:
			case 'numeric'	:
			case 'nozero'	:
			case 'alpha'	:

					switch ($type)
					{
						case 'alpha'	:	$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
							break;
						case 'alnum'	:	$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
							break;
						case 'numeric'	:	$pool = '0123456789';
							break;
						case 'nozero'	:	$pool = '123456789';
							break;
					}

					$str = '';
					for ($i=0; $i < $len; $i++)
					{
						$str .= substr($pool, mt_rand(0, strlen($pool) -1), 1);
					}
					return $str;
				break;
			case 'unique'	:
			case 'md5'		:

						return md5(uniqid(mt_rand()));
				break;
			case 'encrypt'	:
			case 'sha1'	:

						$CI =& get_instance();
						$CI->load->helper('security');

						return do_hash(uniqid(mt_rand(), TRUE), 'sha1');
				break;
		}
	}
}

function getMicrotimestamp()
{
	$micro = microtime(true);
	$micro = $micro * 1000000;
	$micro = number_format($micro, 0, '', '');

	return $micro;
}
