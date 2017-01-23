<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\ThemeModel;
use \Stayfilm\stayzen\ORM as orm;
use \Stayfilm\stayzen\ORM\SubthemeModel;
use phpcassa\Schema\DataType\Int32Type;
use phpcassa\Schema\DataType\IntegerType;
use \Stayfilm\stayzen\Application;
use \Stayfilm\stayzen as zen;

class ThemeService extends TableService
{

	static protected $_instance = null;

	protected $table = 'dbsite.theme';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\ThemeService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @return Stayfilm\stayzen\ORM\ThemeModel list
	 * @throws \Exception
	 */
	public function getThemes()
	{
		return DataMapperManager::findAll('dbsite.theme');
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\ThemeModel $theme
	 * @return Stayfilm\stayzen\ORM\ThemeModel
	 * @throws \Exception
	 */
	public function createTheme($theme)
	{
		$theme = DataMapperManager::create($theme);

		return $theme;
	}

	/**
	 *
	 * @param Stayfilm\stayzen\ORM\ThemeModel $theme
	 * @return Stayfilm\stayzen\ORM\ThemeModel
	 * @throws \Exception
	 */
	public function updateTheme($theme)
	{
		$theme = DataMapperManager::update($theme);

		return $theme;
	}

	public function getSubthemes($theme)
	{
		$query = new \Stayfilm\stayzen\ORM\CQLQuery('theme_subtheme');

		if (is_array($theme))
		{
			$query->where('idtheme', $theme[0]->idtheme, 'bigint'/*$type*/, '=');
		}
		else
		{
			$query->where('idtheme', $theme->idtheme, 'bigint'/*$type*/, '=');
		}

		$rows = $query->select();

		$subs = array();

		foreach ($rows as $cqlrow) {
			foreach ($cqlrow as $cassaCol => $value)
			{
				if ($cassaCol !== 'idsubtheme') {
					continue;
				}

				$idsubtheme = $value;

				$subs[] = DataMapperManager::findByKey('dbsite.subtheme', $idsubtheme);
			}
		}

		return $subs;
	}

	public function createSubtheme($subtheme)
	{
		$subtheme = DataMapperManager::create($subtheme);

		return $subtheme;
	}

	public function addSubtheme($theme, $subtheme)
	{

		$link = new orm\ThemeSubthemeModel();

		$link->idtheme = $theme->idtheme;
		$link->idsubtheme = $subtheme->idsubtheme;
		$link->subtheme = 'just nothing';
		return DataMapperManager::create($link);
	}

	public function getThemeBySlug($slug)
	{
		// THIS IS DECENECESARY TO HAVE A FLAG, BECAUSE THE CODE EVER WAS THIS WAY.
		// THE ONLY THING THAT WE HAVE IN MIND IS THAT BEFORE WE USED THE slug FIELD INDEXED BY SOLR,
		// NOW, WE WILL USE THE slug FIELD INDEX BY CASSANDRA ITSELF.
		$fields = array();
		$fields[0] = 'slug';

		$values = array();
		$values[0] = $slug;

		return orm\DataMapperManager::findBy('dbsite.theme', $fields, $values);
	}

	public function getSubtheme($idsubtheme)
	{
		return ORM\DataMapperManager::findByKey('dbsite.subtheme', $idsubtheme);
	}
}
