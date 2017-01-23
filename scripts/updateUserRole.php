<?php

require_once('vendor/autoload.php');

use Stayfilm\stayzen\ORM as orm;
use Zend\Console\Getopt;

$opts = new Getopt(array(
			'help' => 'Display this help',
			'env|e=s' => 'env',
			'username|u=s' => 'username',
	        'role|r=s' => 'admin, member or guest'
		));

$opts->parse();


if ($opts->help)
{
    echo $opts->getUsageMessage();
	die();
}

if ( ! $opts->env)
{
    die('missing env');
}

if ( ! $opts->username)
{
    die('missing username');
}

if ( ! $opts->role)
{
    die('missing role');
}

$env        = $opts->env;
$username   = $opts->username;
$role       = $opts->role;

$_SERVER['STAYZEN_ENV'] = $env;
require_once 'bootstrap.php';

$user = orm\DataMapperManager::findBy('dbsite.user', 'username', $username);

if ( ! $user )
{
	die("User does not exists");
}

$user->role = $role;

orm\DataMapperManager::update($user);

echo "User role updated successfuly\n";