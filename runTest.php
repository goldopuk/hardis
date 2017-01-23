<?php

define('DS', DIRECTORY_SEPARATOR);

if (count($argv) == 2)
{
	$env = $argv[1];
}
else
{
	$env = 'phpunit';
}

$_SERVER['STAYZEN_ENV'] = $env;

$pp = popen('.' . DS . 'vendor' . DS . 'bin' . DS . 'phpunit .' . DS . 'tests', "r");

while( ! feof($pp))
{
	$char =  fread($pp, 1);

	print($char);
}

$returnCode = pclose($pp);

exit($returnCode);
