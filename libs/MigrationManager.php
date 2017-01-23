<?php

use Stayfilm\stayzen\ORM\CQLQuery;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MigrationManager
 *
 * @author felipe.bezerra
 */
class MigrationManager
{

	/**
	 *
	 * @return array
	 */
	static function getMigrationFiles()
	{
		$arrayfiles = array();
		if (is_dir(MIGRATION_DIR))
		{
			if ($dh = opendir(MIGRATION_DIR))
			{
				while (($file = readdir($dh)) !== false)
				{
					if ($file != "." && $file != "..")
					{
						$pos = strpos($file, '.php');
						if ($pos !== false)
						{
							array_push($arrayfiles, MIGRATION_DIR . DIRECTORY_SEPARATOR . $file);
						}
					}
				}
				closedir($dh);
			}
		}
		return $arrayfiles;
	}

	/**
	 *
	 * @param type $source
	 * @param type $destination
	 * @throws Exception
	 */
	static function migrate($source, $destination)
	{
		info("Migrating from $source to $destination");

		if ($source > $destination)
		{
			$sort = "desc";
		}
		else
		{
			if ($source == $destination)
			{
				echo "Versão já atualizada" . "\n";
				return;
			}
			else
			{
				echo "Incremental" . "\n";
				$sort = "asc";
			}
		}

		// Função que lista todos
		$files = self::getMigrationFiles();

		sort($files);

		if ($sort == "desc")
		{
			$files = array_reverse($files, true);
		}

		$newFiles = array();
		// Varre o array dos arquivos para checar quais estão dentro do intervalo especificado
		foreach ($files as $value)
		{
			$infos = pathinfo($value);

			//print_r($infos);

			$posunderline = strpos($infos['basename'], "_");
			$length = ($posunderline - 0);
			$filenumber = substr($infos['basename'], 0, $length);

			if ($sort == "asc")
			{
				if ($filenumber > $source && $filenumber <= $destination)
				{
					$newFiles[] = $value;
				}
			}
			else
			{
				if ($filenumber <= $source && $filenumber > $destination)
				{
					$newFiles[] = $value;
				}
			}
		}

		foreach ($newFiles as $file)
		{
			$infos = pathinfo($file);
			$match = array();
			preg_match('/^([0-9]+)_/', $infos['basename'], $match);

			$migration = $match[1];

			//echo $migration;

			if (!$migration)
			{
				throw new Exception('migration number missing');
			}

			require($file);

			$classname = "Migration_$migration";

			if (!class_exists($classname))
			{
				throw new Exception("Class $classname does not exist");
			}

			$instance = new $classname();

			if ($sort == 'desc')
			{
				$arr = $instance->rollback();
			}
			else
			{
				$arr = $instance->rollforward();
			}
		}

	}

}
