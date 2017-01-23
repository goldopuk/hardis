<?php
namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen\ORM\Model;
/**
 * Description of UserModel
 *
 * @author julien
 */
class UserModel extends Model
{

	const STATUS_ACTIVE = 1;

	const STATUS_INACTIVE = 0;

	/**
	 *
	 */
	protected $name = 'dbsite.user';

	/**
	 *
	 * @return string
	 */
	function getPrettyName()
	{
		$name = '';

		if ($this->firstname) {
			$name = ucfirst($this->firstname);

			if ($this->lastname) {
				$name .= ' ' . ucfirst($this->lastname);
			}
		} else {
			$name = strtolower($this->username);
		}

		return $name;
	}

	function getPublicData()
	{
		return $this->email;
	}

	function getLang()
	{
		if ( ! $this->languages)
		{
			return NULL;
		}

		$locale = $this->languages;

		list($lang) = explode("_", $locale);

		return $lang;
	}

	/**
	 *
	 * @param string $name
	 * @return mixed
	 * @throws \Exception
	 */
	public function __get($name)
	{
		$val = parent::__get($name);

		if ($name === 'photo')
		{
			return str_replace('http://', 'https://', $val);
		}

		return $val;
	}
}
