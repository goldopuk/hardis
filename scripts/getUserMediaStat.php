<?php
use Zend\Console\Getopt;
use Stayfilm\stayzen\ORM as orm;

require_once('vendor/autoload.php');

$opts = new Getopt(array(
			'help' => 'Display this help',
			'env|e=s' => 'env',
			'username|u=s' => 'username'
		));

$opts->parse();

if ($opts->help)
{
	echo $opts->getUsageMessage();
	die();
}

if ( ! $opts->env)
{
	die('env missing');
}

if ( ( ! $opts->username) &&  ( ! $opts->cleanall ) )
{
	die('username missing');
}

$env		= $opts->env;
$username	= $opts->username;

$_SERVER['STAYZEN_ENV'] = $env;

require('bootstrap.php');


$userServ = Stayfilm\stayzen\services\UserService::getInstance();
$user = $userServ->getUserByUsername($username);

$midiaServ = \Stayfilm\stayzen\services\MidiaService::getInstance();
$result =  $midiaServ->getUserMediaStat($user);

print_r($result);
