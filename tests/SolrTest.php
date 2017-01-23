<?php

use Stayfilm\stayzen\Application;

// use Stayfilm\stayzen\utilities;

/**
 * @extends PHPUnit_Framework_TestCase
 */
class SolrTest extends PHPUnit_Framework_TestCase
{
	public function testSolr()
	{
		$this->markTestSkipped();
		
		$config = Application::$config->solr;
		
		$solrConf  = array();
		//$solrConf['endpoint'] = array();
		
		$arr = array();
		$arr['host'] = $config['host'];
		$arr['port'] = $config['port'];
		$arr['path'] = sprintf($config['path'], 'user');

		$solrConf['user'] = $arr;
		
		$arr = array();
		$arr['host'] = $config['host'];
		$arr['port'] = $config['port'];
		$arr['path'] = sprintf($config['path'], 'movie');

		$solrConf['movie'] = $arr;
		
		$config = array();
		$config['endpoint'] = $solrConf;
		
		// test request to user core
		$client = new Solarium\Client($config);
		$query = $client->createSelect();
		$query->setQuery('firstname:Lucas')
				->setOmitHeader(false);
		$resultset = $client->execute($query, 'user');
		$this->assertSame(0, $resultset->getStatus());
		$this->assertSame(2, $resultset->getNumFound());

		// test request to movie core
		$query = $client->createSelect();
		$query->setQuery('name:viajante')
				->setOmitHeader(false);;
		$resultset = $client->execute($query, 'movie');
		$this->assertSame(2, $resultset->getNumFound());
		$this->assertSame(0, $resultset->getStatus());
		
		// test request to movie core
		$query = $client->createSelect();
		$query->setQuery('tag:viagem')
				->setOmitHeader(false);
		$resultset = $client->execute($query, 'movie');
		$this->assertSame(2, $resultset->getNumFound());
		$this->assertSame(0, $resultset->getStatus());
	}

}