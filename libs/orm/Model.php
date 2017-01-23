<?php

namespace Stayfilm\stayzen\ORM;
use Stayfilm\stayzen as zen;

/**
 * Description of Model
 *
 * @author julien
 */
class Model
{

	const NEW_  = 1;
	const SYNC  = 2;
	const DIRTY = 3;
	const SOLR  = 4;

	/**
	 *
	 * @var string - model name ex: dbsite.user
	 */
	protected $name;

	/**
	 *
	 * @var int
	 */
	protected $status;

	/**
	 *
	 * @var array
	 */
	protected $attrs = array();

	/**
	 *
	 * @var array
	 */
	protected $modifiedAttrs = array();

	/**
	 *
	 */
	public function __construct()
	{
		$this->status = self::NEW_;
	}

	protected $additionalData = array();

	/**
	 * @param bool $format
	 * @param bool $includeAdditionalData
	 * @return array
	 */
	public function getAttrs($format = false, $includeAdditionalData = FALSE)
	{
		if ( ! $format)
		{
			$attrs =  array_merge($this->attrs, $this->modifiedAttrs);
		}
		else
		{
			$attrs = array();

			$schemaM = zen\Application::getSchemaManager();

			foreach ($this->attrs as $field => $value)
			{
				$type = $schemaM->getColumnType($this->getModelName(), $field, $this->getKeyspaceName());

				if ($type === 'timestamp')
				{
					$attrs[$field] = date('Y-m-d H:i:s', $value);
				}
				else
				{
					$attrs[$field] = $value;
				}
			}
		}

		if ($includeAdditionalData)
		{
			$attrs = array_merge($this->additionalData, $attrs);
		}

		return $attrs;
	}

	/**
	 *
	 * @return array
	 */
	public function getOrigAttrs()
	{
		return $this->attrs;
	}

	public function unsetField($key)
	{
		unset($this->attrs[$key]);
		unset($this->modifiedAttrs[$key]);
	}

	/**
	 *
	 * @return array
	 */
	public function getModifiedAttrs()
	{
		return $this->modifiedAttrs;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isNew()
	{
		return $this->status === self::NEW_;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isDirty()
	{
		return $this->status === self::DIRTY;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isSync()
	{
		return $this->status === self::SYNC;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isSolr()
	{
		return $this->status === self::SOLR;
	}

	/**
	 *
	 */
	public function setAsSync()
	{
		$this->attrs = array_merge($this->attrs, $this->modifiedAttrs);
		$this->modifiedAttrs = array();
		$this->status = self::SYNC;
	}

	/**
	 *
	 */
	public function setAsSolr()
	{
		$this->status = self::SOLR;
	}

	public function addData($key, $value)
	{
		$this->additionalData[$key] = $value;
	}

	public function getData($key)
	{
		if (isset($this->additionalData[$key]))
		{
			return $this->additionalData[$key];
		}

		return NULL;
	}

	/**
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		if ($this->status === self::NEW_)
		{
			$this->attrs[$name] = $value;
		}
		else
		{
			if (isset($this->modifiedAttrs[$name]))
			{
				$this->modifiedAttrs[$name] = $value;
				$this->status = self::DIRTY;
			}
			else
			{
				if (isset($this->attrs[$name]) && $this->attrs[$name] !== $value)
				{
					$this->modifiedAttrs[$name] = $value;
					$this->status = self::DIRTY;
				}
				elseif ( ! isset($this->attrs[$name]))
				{
					$this->modifiedAttrs[$name] = $value;
					$this->status = self::DIRTY;
				}
				// else do nothing
			}
		}
	}

	/**
	 *
	 * @param string $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name)
	{

		if (isset($this->modifiedAttrs[$name]))
		{
			return $this->modifiedAttrs[$name];
		}

		if (isset($this->attrs[$name]))
		{
			return $this->attrs[$name];
		}

		return null;
	}

	/**
	 *
	 * @return array
	 */
	protected function parseName()
	{
		if (strpos($this->name, '.') !== false) {
			list($db, $table) = explode('.', $this->name);
		} else {
			$table = $this->name;
			$db = 'dbsite';
		}

		return array($db, $table);
	}

	/**
	 *
	 * @return string
	 */
	public function getKeyspaceName()
	{
		list($keyspace) = $this->parseName();
		return $keyspace;
	}

	/**
	 *
	 * @return string
	 */
	public function getModelName()
	{
		list(, $table) = $this->parseName();
		return $table;
	}

	public function getFullModelName()
	{
		return $this->name;
	}

	public function __toString()
	{
		return json_encode($this->getAttrs());
	}

}
