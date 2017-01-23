<?php

namespace Stayfilm\stayzen\ORM;

/**
 *
 */
class IdentityMap
{
	/**
	 *
	 * @var array
	 */
	protected $map = array();

	/**
	 *
	 * @param string $key
	 * @param string $id
	 * @param boolean $value
	 */
	function add($key, $id, $value)
	{
		if ( ! is_string($key) || ! (is_string($id) || is_int($id)) )
		{
			return false; // for composite keys, it does not work
		}

		if (!isset($this->map[$key]))
		{
			$this->map[$key] = array();
		}

		$this->map[$key][$id] = $value;

		return true;
	}

	/**
	 *
	 * @param string $key
	 * @param string $id
	 * @return boolean
	 */
	function has($key, $id)
	{
		if ( ! is_string($key) || ! (is_string($id) || is_int($id)) )
		{
			return false;
		}

		return isset($this->map[$key][$id]);
	}

	/**
	 *
	 * @param string $key
	 * @param string $id
	 * @return mixed
	 * @throws \Exception
	 */
	function get($key, $id)
	{
		if (!$this->has($key, $id))
		{
			throw new \Exception("Key $key, id $id does not exist in map");
		}

		return $this->map[$key][$id];
	}

	/**
	 *
	 * @param type $key
	 * @param type $id
	 * @return type
	 */
	function remove($key, $id)
	{
		if ( ! $this->has($key, $id))
		{
			return;
		}

		if ( ! is_string($key) || ! (is_string($id) || is_int($id)) )
		{
			return false; // for composite keys, it does not work
		}

		unset($this->map[$key][$id]);
	}
}
