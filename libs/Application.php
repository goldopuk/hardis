<?php
namespace Stayfilm\stayzen;

use phpcassa\Connection\ConnectionPool;
use Stayfilm\stayzen\services\Service;
use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen as zen;
use Stayfilm\stayzen\ORM\CassaClient;
use Stayfilm\stayzen\ORM\CQLQuery;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\ORM\SchemaManager;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Symfony\Component\Yaml\Parser;

/**
 * Application is a static class that bootstrap the application
 *
 * @author julien
 */
class Application
{
	static public $currentLog = array();

	/**
	 *
	 * @var boolean
	 */
	static public $eventDisabled = false;

	/**
	 *
	 * @var boolean
	 */
	static public $logSql = true;

	/**
	 *
	 * @var \Zend\Log\Logger
	 */
	static public $logger;

	/**
	 *
	 * @var \Zend\Config\Config
	 */
	static public $config;

	/**
	 *
	 * @var Stayfilm\stayzen\ORM\SchemaManager
	 */
	static public $schemaManager;

	/**
	 *
	 * @var type StayFilm\stayzen\WsdlManager
	 */
	static public $wsdlManager;

	/**
	 *
	 * @var array
	 */
	static public $cassPools = array();

	/**
	 *
	 * @var StayFilm\stayzen\SolrClient
	 */
	static protected $solrClient;

	/**
	 *
	 * @var type
	 */
	static public $stayzenException;

	/**
	 * example : test, dev env.
	 * must exists in the config.php as a key
	 *
	 * @param string $env
	 */
	static function bootstrap($env)
	{
		self::loadConfig($env);

		$profiler = Profiler::getInstance();
		$profiler->setActive(Application::$config->profiler);

		$cacheServ = serv\CacheService::getInstance();

		self::setupLogger();

		self::initCassaConnections();

		if (strpos(self::$config->log_writers, 'cassandra') !== FALSE)
		{
			self::activateCassaWriter();
		}

		self::initSchemaManager();
		self::configureDataMapper();
		self::configureService();

		self::setupPubSub();

		if ( ! self::isUnitTest())
		{
			self::$config->merge(new \Zend\Config\Config(self::getConfigFromCassa()));
			$cacheServ->set('config', self::$config);
		}
	}

	static function close()
	{
		self::$logger->debug('zen\Application::close()');

		try
		{
			foreach (self::$cassPools as $pool)
			{
				$pool->close();
			}

		} catch (Exception $ex) {
			// by pass
		}
	}

	static function isUnitTest()
	{
		return strpos(STAYZEN_ENV, 'phpunit') !== FALSE;
	}

	/**
	 *
	 * @param string $env
	 * @throws \Exception
	 */
	static function loadConfig($env)
	{

		$profiler = Profiler::getInstance();
		$profiler->mark('loadconfig-start');

		// load config
		$confArray = include STAYZEN_ROOT . '/config/config.php';

		if (!isset($confArray[$env]))
		{
			throw new \Exception("Env $env does not exist in the config");
		}

		$defaultConf = new \Zend\Config\Config($confArray['default']);

		$envConf = new \Zend\Config\Config($confArray[$env]);

		$defaultConf->merge($envConf);

		$localFile = STAYZEN_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'local.php';

		if (file_exists($localFile) && ! self::isUnitTest()) // special case unit case
		{
			$localConf = new \Zend\Config\Config(include($localFile));
			$defaultConf->merge($localConf);
		}

		self::$config = $defaultConf;

		$profiler->mark('loadconfig-end');
	}

	/**
	 *
	 * @param array $config
	 */
	function mergeConfig(array $config)
	{
		zen\Application::$config->merge(new \Zend\Config\Config($config));
	}

	/**
	 *
	 */
	static function setupLogger()
	{
		$profiler = Profiler::getInstance();
		$profiler->mark('logger-start');

		self::$logger = new Logger();

		$file = self::$config->log_file;

		$fileinfo = pathinfo($file);

		if ( ! file_exists($fileinfo['dirname']))
		{
			mkdir($fileinfo['dirname'], 0777, true);
		}

		if ( ! file_exists($file))
		{
			$handle = fopen($file, 'a');
			fclose($handle);
		}

		$filter = new \Zend\Log\Filter\Priority(self::$config->log_level);
		$format = '%timestamp% %priorityName% (%priority%): %message%';
		$formatter = new zen\Zend\Logger\Formatter\SimpleFormatter($format);

		if (strpos(self::$config->log_writers, 'file') !== FALSE)
		{
			$writer = new Stream($file);
			$writer->addFilter($filter);
			$writer->setFormatter($formatter);
			self::$logger->addWriter($writer);
		}

		$inMemoryWriter = new zen\Zend\Logger\Writer\InMemoryWriter();
		$inMemoryWriter->setFormatter($formatter);
		self::$logger->addWriter($inMemoryWriter);

		$msg = <<<TXT
********************************************** STAYZEN INITIALIZATION **********************************************
TXT;
		self::$logger->info($msg);

		$profiler->mark('logger-end');

		$profiler->log('Logger initialized', 'logger');
	}

	static function activateCassaWriter()
	{
		$cassaWriter = new zen\Zend\Logger\Writer\CassaWriter(zen\ORM\CQLQuery::getConnection('dbsite'), 'logs');
		$formatter = new \Zend\Log\Formatter\Simple("%message%");
		$cassaWriter->setFormatter($formatter);

		$filter = new \Zend\Log\Filter\Priority(self::$config->log_level_cassa);
		$cassaWriter->addFilter($filter);

		self::$logger->addWriter($cassaWriter);
	}

	/**
	 *
	 */
	static function initCassaConnections()
	{
		$profiler = Profiler::getInstance();
		$profiler->mark('connection-start');

		$dbsiteHost = self::$config->database->dbsite->host;

		$dbsiteConf = [
			'username' => self::$config->database->username,
			'password' => self::$config->database->password,
			'hosts'    => is_string($dbsiteHost) ? array($dbsiteHost) : $dbsiteHost->toArray(),
			'keyspace' => '',
			'use_random_nodes' => self::$config->database->use_random_nodes
		];

		if ( ! $dbsiteConf['password'])
		{
			$dbsiteConf['password'] = @file_get_contents(STAYZEN_ROOT . "/CASSA_PWD");
		}

		if ( ! $dbsiteConf['password'])
		{
			throw new \Exception('Missing cassandra password.Did you create the CASSA_PWD file in STAYZEN_ROOT ?');
		}

		$dbsiteConnection = new CassaClient($dbsiteConf);

		CQLQuery::addConnection('cassandra', $dbsiteConnection);

		$profiler->mark('connection-end');

		$profiler->log('Cassandra initialized', 'connection');
	}

	/**
	 *
	 */
	static function configureDataMapper()
	{
		// configure DataMapperManager
		DataMapperManager::init(self::$schemaManager, new orm\IdentityMap());
	}

	/**
	 *
	 */
	static function initSchemaManager()
	{
		$profiler = Profiler::getInstance();
		$profiler->mark('schemamanager-start');

		$cacheServ = serv\CacheService::getInstance();

		$schema = $cacheServ->get('schema');

		if ( ! $schema)
		{
			$yaml = new Parser();
			$schema = $yaml->parse(file_get_contents(self::$config->orm->schema));

			$cacheServ->set('schema', $schema);
		}

		$m = new SchemaManager($schema);
		self::$schemaManager = $m;

		$profiler->mark('schemamanager-end');

		$profiler->log('Schema Manager initialized', 'schemamanager');
	}

	/**
	 *
	 */
	static function initWsdlManager()
	{
		$yaml = new Parser();
		$wsdl = $yaml->parse(file_get_contents(self::$config->wsdl));
		$m = new WsdlManager($wsdl);

		self::$wsdlManager = $m;
	}

	/**
	 *
	 */
	static function configureService()
	{
		Service::setLogger(self::$logger);
	}

	/**
	 *
	 * @return \Stayfilm\stayzen\SolrClient
	 */
	static public function getSolrClient($table, $keyspace = 'dbsite')
	{
		return new SolrClient($table, $keyspace);
	}

	/**
	 *
	 * @return \Stayfilm\stayzen\CassaClient
	 */
	static public function getConnection($db = 'dbsite')
	{
		return CQLQuery::getConnection($db);
	}

	/**
	 *
	 */
	static function setupPubSub()
	{
		$profiler = Profiler::getInstance();
		$profiler->mark('setupPubSub-start');

		$cassaServ    = serv\CassaService::getInstance();
		$notifServ    = serv\NotificationService::getInstance();
		$movieServ    = serv\MovieService::getInstance();
		$socialServ   = serv\SocialService::getInstance();
		$timelineServ = serv\TimelineService::getInstance();
		$userServ     = serv\UserService::getInstance();
		$jobServ      = serv\JobService::getInstance();

		$socialServ->addSubscriber($notifServ);
		$movieServ->addSubscriber($notifServ);
		$movieServ->addSubscriber($cassaServ);
		$socialServ->addSubscriber($cassaServ);
		$socialServ->addSubscriber($timelineServ);
		$userServ->addSubscriber($notifServ);
		$jobServ->addSubscriber($cassaServ);

		$profiler->mark('setupPubSub-end');

		$profiler->log('Publishers/subscribers initialized', 'setupPubSub');
	}

	/**
	 *
	 * @return \Stayfilm\stayzen\SchemaManager
	 */
	static function getSchemaManager()
	{
		return self::$schemaManager;
	}

	static function getRequiredDbVersion()
	{
		return DB_VERSION;
	}

	static function getConfigFromCassa()
	{
		$profiler = Profiler::getInstance();
		$profiler->mark('getConfigFromCassa-start');

		$result = DataMapperManager::findAll('dbsite.config');

		$config = array();

		foreach ($result as $row)
		{
			$config[$row->idconfig] = $row->value;
		}

		$profiler->mark('getConfigFromCassa-end');

		$profiler->log('Config from Cassandra initialized', 'getConfigFromCassa');

		return $config;
	}

}
