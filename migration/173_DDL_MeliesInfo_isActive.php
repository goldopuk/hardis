<?php

use \Stayfilm\stayzen\Application;

class Migration_173 extends Migration
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
				$dbsite[] = 'ALTER TABLE meliesinfo ADD isactive int';

				$arr['dbsite'] = $dbsite;
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
			throw new Exception( 'Error to create new field on meliesinfo', 0, $e);
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
				$dbsite[] = 'ALTER TABLE meliesinfo DROP isactive';

				$arr['dbsite'] = $dbsite;
				$this->executeCql($arr);
			}
			catch(Exception $e)
			{
				//
			}

			Migration::updateVersion(172);
		}
		catch (Exception $e)
		{
			throw new Exception( 'Error to drop field on meliesinfo', 0, $e);
		}
	}
}