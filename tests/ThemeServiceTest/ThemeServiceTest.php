<?php

use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\ORM\SchemaManager;
use \Stayfilm\stayzen\ORM\ThemeModel;
use \Stayfilm\stayzen\ORM\SubthemeModel;
use \Stayfilm\stayzen\ORM\GenreModel;
use Stayfilm\stayzen\services AS s;
/**
 * @extends PHPUnit_Framework_TestCase
 */
class ThemeServiceTest extends PHPUnit_Framework_TestCase {

	static function setUpBeforeClass()
	{
		DataMapperManager::truncateTables('dbsite', array('theme', 'subtheme', 'theme_subtheme'));
	}

	function testTheme() {

		$themeServ = s\ThemeService::getInstance();

		$theme = new ThemeModel();
		$theme->name = "toto";
		$theme = $themeServ->create($theme);


		$subtheme = new SubthemeModel();
		$subtheme->name = "subtheme";
		$subtheme = $themeServ->createSubtheme($subtheme);

		$themeServ->addSubtheme($theme, $subtheme);

		sleep(1);

		$subtheme = new SubthemeModel();
		$subtheme->name = "subtheme2";
		$subtheme = $themeServ->createSubtheme($subtheme);

		$themeServ->addSubtheme($theme, $subtheme);


		$subs = $themeServ->getSubthemes($theme);

		$this->assertEquals(2, count($subs));

		$subtheme = new SubthemeModel();
		$subtheme->name = "subtheme3";
		$subtheme = $themeServ->createSubtheme($subtheme);

		$themeServ->addSubtheme($theme, $subtheme);

		$subs = $themeServ->getSubthemes($theme);
		$this->assertEquals(3, count($subs));
		//$this->assertEquals(1, $subs[0]->isactive);

		$genderServ = s\GenreService::getInstance();

		$gender = new GenreModel();
		$gender->name = "gender 1";
		$gender = $genderServ->create($gender);

	}
}