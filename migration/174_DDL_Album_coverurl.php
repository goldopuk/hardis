<?php

use \Stayfilm\stayzen\Application;

class Migration_174 extends Migration
{
	/*
	 *
	 */
	function rollforward()
	{
		print(__METHOD__ . "\n");

		try
		{
			$dbstay = array();
			$arr = array();

			try //Drops first
			{
				$dbstay[] = 'ALTER TABLE album ADD coverurl varchar';

				$arr['dbstay'] = $dbstay;
				$this->executeCql($arr);
			}
			catch(Exception $e)
			{
				//
			}

			Migration::updateVersion(174);
		}
		catch (Exception $e)
		{
			throw new Exception( 'Error to create new field on album', 0, $e);
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
			$dbstay = array();
			$arr = array();

			try //Drops first
			{
				$dbstay[] = 'ALTER TABLE album DROP coverurl';

				$arr['dbstay'] = $dbstay;
				$this->executeCql($arr);
			}
			catch(Exception $e)
			{
				//
			}

			Migration::updateVersion(173);
		}
		catch (Exception $e)
		{
			throw new Exception( 'Error to drop field on album', 0, $e);
		}
	}
}