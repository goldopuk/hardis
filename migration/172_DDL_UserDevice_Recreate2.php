<?php

use \Stayfilm\stayzen\Application;

class Migration_172 extends Migration
{
	/*
	 *
	 */
	function rollforward()
	{
		print(__METHOD__ . "\n");

		try
		{
			$dbsite = array();
			$arr = array();

			try //Drops first
			{
				$dbsite[] = 'DROP TABLE userdevice';

				$arr['dbsite'] = $dbsite;
				$this->executeCql($arr);
			}
			catch(Exception $e)
			{
				//
			}

			unset($dbsite);
			unset($arr);

			$dbsite[] = 'CREATE TABLE userdevice (
							iduser uuid,
							iddevice text,
							created timestamp,
							updated timestamp,
							PRIMARY KEY (iduser, iddevice)
						  )';

			$dbsite[] = 'GRANT MODIFY ON userdevice TO stayuser';
			$dbsite[] = 'GRANT SELECT ON userdevice TO stayuser';

			$arr['dbsite'] = $dbsite;
			$this->executeCql($arr);

			Migration::updateVersion(172);
		}
		catch (Exception $e)
		{
			throw new Exception( 'Error to create new table userdevice', 0, $e);
		}
	}
	/**
	 *
	 */
	function rollback()
	{
		print(__METHOD__ . "\n");

		try
		{
			$dbsite = array();
			$arr = array();

			try //Drops first
			{
				$dbsite[] = 'DROP TABLE userdevice';

				$arr['dbsite'] = $dbsite;
				$this->executeCql($arr);
			}
			catch(Exception $e)
			{
				//
			}

			Migration::updateVersion(171);
		}
		catch (Exception $e)
		{
			throw new Exception( 'Error to drop table userdevice', 0, $e);
		}
	}
}