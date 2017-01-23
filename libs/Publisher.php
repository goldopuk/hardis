<?php
namespace Stayfilm\stayzen;

use \Stayfilm\stayzen\Application;

/**
 * Description of Publisher
 *
 * @author julien
 */

abstract class Publisher extends Subscriber
{
	protected $subscribers = array();

	/**
	 *
	 * @param string $eventName
	 * @param mixed $params
	 */
	function fire($eventName, $params)
	{

		if (Application::$eventDisabled)
		{
			return;
		}

		debug(__METHOD__ . " Event : $eventName");

		foreach ($this->subscribers as $sub)
		{
			$sub->handleEvent($eventName, $params);
		}
	}

	/**
	 *
	 * @param type $subscriber
	 */
	function addSubscriber($subscriber)
	{
		if ( ! in_array($subscriber, $this->subscribers))
		{
			$this->subscribers[] = $subscriber;
		}
	}

}