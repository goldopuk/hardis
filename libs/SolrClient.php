<?php
namespace Stayfilm\stayzen;

use Solarium\Client;
use Stayfilm\stayzen as zen;
use Solarium\Core\Event\Events;

/**
 * Description of SolrClient
 *
 * @author julien
 */
class SolrClient
{
	/**
	 *
	 * @var \Solarium\Client
	 */
	protected $solarium;
	protected $table;
	protected $keyspace;

	/**
	 *
	 * @var \Stayfilm\stayzen\ORM\SchemaManager
	 */
	protected $schemaManager;

	function __construct($table, $keyspace = 'dbsite')
	{
		$config = Application::$config->solr->toArray();
		$config['hosts'] = explode(',', $config['hosts']);

		$this->table = $table;
		$this->keyspace = $keyspace;

		$this->solarium = new Client();

		$loadbalancer = $this->solarium->getPlugin('loadbalancer');

		$loadbalancer->setOptions(array(
			'failoverenabled' => Application::$config->solarium_loadbalancer_failoverenabled,
			'failovermaxretries' => Application::$config->solarium_loadbalancer_failovermaxretries
		));

		$i = 1;

		foreach ($config['hosts'] as $host)
		{
			$arr = array();
			$arr['host'] = $config['username'] . ':' . $config['password'] . '@' . $host;
			$arr['port'] = $config['port'];

			$realName = $this->getRealName();

			$arr['path'] = sprintf($config['path'], $realName);
			$arr['key'] = "$keyspace.$table-$i";

			$endpoint = $this->solarium->createEndpoint($arr, TRUE);

			$loadbalancer->addEndpoint($endpoint, 100);

			$i++;
		}

		$this->solarium->getPlugin('postbigrequest');

		$this->solarium->getEventDispatcher()->addListener(Events::PRE_EXECUTE_REQUEST, function ($event) {
			$request = $event->getRequest();
			$endpoint = $event->getEndPoint();
			$uri = $endpoint->getBaseUri() . $request->getUri();
			debug($request);
			debug($uri);
		});

		$this->solarium->removeEndpoint('localhost');
	}

	function execute($query, $count = FALSE)
	{
		$profiler = zen\Profiler::getInstance();

		if (Application::$config->profiler)
		{
			$profiler->mark('start');
			$query->setOmitHeader(false);
		}

		$resultSet = $this->solarium->execute($query);

		$modelName = $this->keyspace . "." . $this->table;

		if (Application::$config->profiler)
		{
			$profiler->mark('end');

			$time = $resultSet->getQuerytime();

			if ($time)
			{
				$time = $time / 1000;
			}
			else
			{
				$time = -1;
			}

			$profiler->addSolrQuery($query->getQuery(), $modelName, $time, $profiler->elapsedTime('start', 'end'));
		}

		$list = array();

		foreach ($resultSet as $doc)
		{
			try
			{
				$model = ORM\ModelFactory::build($modelName);
				$model = $this->loadModelFromSolariumResult($model, $doc);
			}
			catch (\Exception $e)
			{
				$model = $doc;
			}

			$list[] = $model;
		}

		return $count ? array($list, $resultSet->getNumFound()) : $list ;
	}

	function getRealName()
	{
		$keyspaceName = zen\Application::$config->database->{$this->keyspace}->keyspace;
		return $keyspaceName . "." . $this->table;
	}

	/**
	 *
	 * @param \Stayfilm\stayzen\ORM\Model $model
	 * @param \Solarium\QueryType\Select\Result\Document $doc
	 * @return \Stayfilm\stayzen\ORM\Model
	 */
	protected function loadModelFromSolariumResult($model, $doc)
	{
		foreach ($doc as $name => $value)
		{
			if ($name === "score") {
				continue;
			}

			try
			{
				$type = Application::$schemaManager->getColumnType($model->getModelName(), $name, $model->getKeyspaceName());
			}
			catch( \Exception $e )
			{
				// Bypass. We do not want fields being mapped that aren't in schema.yml
				continue;
			}

			switch ($type)
			{
				case 'json':
					$value = json_decode($value, true);
					break;
				case 'uuid':
				case 'timestamp':
				default:
					// do nothing
			}

			$model->{$name} = $value;
		}

		$model->setAsSolr();

		return $model;
	}

	/**
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return type
	 * @throws \Exception
	 */
	public function __call($method, $arguments)
	{
		if (method_exists($this->solarium, $method))
		{
			return call_user_func_array(array($this->solarium, $method), $arguments);
		}
		else
		{
			throw new \Exception("Method DB::$method does not exist");
		}
	}

	/**
	 *
	 * @param \Solarium\Core\Query $query
	 * @param string $table
	 * @return array
	 */
	function count($query, $table)
	{
		$resultSet = $this->solarium->execute($query, $table);
		return $resultSet->getNumFound();
	}
	
}
