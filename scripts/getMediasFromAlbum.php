<?php
use Zend\Console\Getopt;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\services as serv;

require_once('vendor/autoload.php');

$opts = new Getopt(array(
			'help' => 'Display this help',
			'env|e=s' => 'env',
			'username|u=s' => 'username',
			'idalbum|a=s' => 'album uid'
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
$idalbum    = $opts->idalbum;

$_SERVER['STAYZEN_ENV'] = $env;

require('bootstrap.php');

$userServ = Stayfilm\stayzen\services\UserService::getInstance();
$user = $userServ->getUserByUsername($username);

if ( ! $user)
{
	throw new \Exception("user does not exist in db");
}

$albumServ = serv\AlbumService::getInstance();

$album = $albumServ->getAlbum($user, $idalbum);

if ( ! $album)
{
	throw new \Exception("Album does not exidt");
}

list($medias) = $albumServ->getMedias($album);

pre($medias);

