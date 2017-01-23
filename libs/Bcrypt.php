<?php
namespace Stayfilm\stayzen;

/**
* Class Bcrypt
* Crypts strings and compare encrypted strings using Bcrypt algorithm.
* @author Fabiano Simões <fabiano@stayfilm.com>
*/
class Bcrypt
{
	/**
	* Salt prefix.
	* See http://www.php.net/security/crypt_blowfish.php for explanation.
	*/
	protected static $saltPrefix = '2a';

	/**
	* Salt length; Bcrypt requires a 22 character length string.
	*/
	protected static $saltLength = 22;

	/**
	* Default cost. Range is from 4 to 31. 4 is the minimum cpu cost to get the hash.
	*/
	protected static $defaultCost = '8';

	/**
	* Get a random salt string.
	* It is just a random base64 encoded string.
	*/
	private static function getSalt()
	{
		$seed = uniqid(mt_rand(), true);
		$salt = str_replace('+', '.', base64_encode($seed));
		return substr($salt, 0, self::$saltLength);

	}

	/**
	* Build the hash string.
	* @param string $p_stringToHash The string to be hashed.
	* @param integer $p_cost The hashing cost.
	*/
	public static function hash($p_stringToHash, $p_cost = null)
	{
		if(empty($p_cost))
			$p_cost = self::$defaultCost;

		$salt = self::GetSalt();
		$hashString = sprintf('$%s$%02d$%s$', self::$saltPrefix, $p_cost, $salt);
		return crypt($p_stringToHash, $hashString);
	}

	/**
	* Given a string and a hash, check if both are equal.
	* @param string $p_stringHash The string to compare.
	* @param string $p_hash The hash generated.
	*/
	public static function validate($p_stringHash, $p_hash)
	{
		return (crypt($p_stringHash, $p_hash) === $p_hash);
	}

}
