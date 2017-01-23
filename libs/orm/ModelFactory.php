<?php

namespace Stayfilm\stayzen\ORM;

/**
 * Description of ModelFactory
 *
 * @author julien
 */
class ModelFactory
{
	/**
	 *
	 * @param string $modelName
	 * @return \Stayfilm\stayzen\ORM\Model
	 * @throws Exception
	 */
	static function build($modelName)
	{
		if (strpos($modelName, '.') !== FALSE)
		{
			list($keyspace, $table) = explode('.', $modelName);
		}
		else
		{
			$keyspace = null;
			$table    = $modelName;
		}

		if ($keyspace === 'dbstay' && $table === 'user')
		{
			$classname = 'Stayfilm\stayzen\ORM\UserDbstayModel';
		}
		else if ($keyspace === 'dbsite' && $table === 'theme_subtheme')
		{
			$classname = 'Stayfilm\stayzen\ORM\ThemeSubThemeModel';
		}
		else
		{
			$classname = 'Stayfilm\stayzen\ORM\\' . ucfirst($table) . 'Model';

			if (!class_exists($classname)) {
				throw new \Exception(__METHOD__ . " : classname $classname does not exist");
			}
		}

		return new $classname();
	}

}

