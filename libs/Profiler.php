<?php

namespace Stayfilm\stayzen;

class Profiler
{

	/**
	 *
	 * @var Stayfilm\stayzen\Profiler
	 */
	static private $_instance = null;

	/**
	 *
	 * @var float
	 */
	protected $startTime = 0;

	/**
	 *
	 * @var array
	 */
	protected $markers = array();

	protected $active = TRUE;

	/**
	 *
	 * @var array
	 */
	protected $queries = array();

	/**
	 *
	 * @var array
	 */
	protected $solrQueries = array();

	/**
	 *
	 * @var array
	 */
	protected $records = array();

	/**
	 *
	 * @var array
	 */
	protected $items = array();

	/**
	 *
	 * @return \Stayfilm\stayzen\Profiler
	 */
	static public function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 *
	 * @param string $name
	 */
	function mark($name)
	{
		if ( ! $this->isActive())
		{
			return;
		}

		$this->markers[$name] = microtime();
	}

	function isActive()
	{
		return $this->active;
	}

	function setActive($v)
	{
		$this->active = $v;
	}

	/**
	 *
	 * @param string $point1
	 * @param string $point2
	 * @param int $decimals
	 * @return float
	 * @throws \Exception
	 */
	function elapsedTime($point1, $point2, $decimals = 4)
	{
		if ( ! $this->isActive())
		{
			return;
		}

		if ( ! isset($this->markers[$point1]))
		{
			throw new \Exception("Marker $point1 does not exist");
		}

		if ( ! isset($this->markers[$point2]))
		{
			throw new \Exception("Marker $point2 does not exist");
		}

		list($sm, $ss) = explode(' ', $this->markers[$point1]);
		list($em, $es) = explode(' ', $this->markers[$point2]);

		return number_format(($em + $es) - ($sm + $ss), $decimals);
	}

	/**
	 *
	 * @return int
	 */
	function getQueryCount()
	{
		return count($this->queries);
	}

	/**
	 *
	 * @param string $req
	 * @param float $duration
	 */
	function addQuery($req, $duration)
	{
		$this->queries[] = array('query' => $req, 'duration' => $duration );
	}

	/**
	 *
	 * @param string $queryString
	 * @param string $table
	 * @param float $solrTime
	 * @param float $totalQueryTime
	 */
	function addSolrQuery($queryString, $table, $solrTime, $totalQueryTime)
	{
		$this->solrQueries[] = array('query' => $queryString, 'table' => $table,
			'solrQueryTime' => $solrTime, 'totalQueryTime' => $totalQueryTime );
	}

	/**
	 *
	 * @return float
	 */
	function getSolrQueryTime()
	{
		$time = 0;

		foreach ($this->solrQueries as $row)
		{
			$time += $row['totalQueryTime'];
		}

		return $time;
	}

	/**
	 *
	 * @return int
	 */
	function getSolrQueryCount()
	{
		return count($this->solrQueries);
	}

	/**
	 *
	 * @return array
	 */
	function getSolrQueries()
	{
		return $this->solrQueries;
	}
	/**
	 *
	 * @return array
	 */
	function getQueries()
	{
		return $this->queries;
	}

	function getTotalQueryTime()
	{
		$total = 0;

		foreach ($this->queries as $q)
		{
			$total += $q['duration'];
		}

		return $total;
	}

	/**
	 *
	 * @param string $key
	 * @param string $point1
	 * @param string $point2
	 * @param mixed $infos
	 */
	function add($key, $point1, $point2, $infos)
	{
		if ( ! $this->isActive())
		{
			return;
		}

		$this->records[] = array('key'=> $key, 'duration' => $this->elapsedTime($point1, $point2),
				'infos' => $infos);
	}

	/**
	 *
	 * @return array
	 */
	function getRecords()
	{
		return $this->records;
	}

	/**
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	function addItem($key, $value)
	{
		$this->items[$key] = $value;
	}

	/**
	 *
	 * @param string $key
	 * @return mixed
	 * @throws \Exception
	 */
	function getItem($key)
	{
		if ( ! isset($this->items[$key]))
		{
			throw new \Exception("key $key does not exist");
		}

		return $this->items[$key];
	}

	function log($label, $mark)
	{

		if ( ! $this->isActive())
		{
			return;
		}

		$elapseTime = $this->elapsedTime("{$mark}-start", "{$mark}-end") * 1000;
		info("PROFILER - ($elapseTime ms) $label");
	}
	
}
