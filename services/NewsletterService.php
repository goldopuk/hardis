<?php

namespace Stayfilm\stayzen\services;

use phpcassa\UUID;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\NlSubscriberModel;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

class NewsletterService extends Service
{

	static protected $_instance = null;
	
	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\NewsletterService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @param string $email
	 * @return Stayfilm\stayzen\ORM\NlSubscriberModel
	 * @throws \Exception
	 */
	function addEmail($email)
	{
		$validator = Validation::createValidator();

		$violations = $validator->validateValue($email, new Email());

		if ($violations->count() > 0)
		{
			throw new \Exception($violations);
		}

		$subscriber = new NlSubscriberModel();
		$subscriber->email = $email;
		$subscriber->isactive = 1;

		return DataMapperManager::create($subscriber);
	}

	/**
	 *
	 * @param string $email
	 * @return Stayfilm\stayzen\ORM\NlSubscriberModel
	 * @throws \Exception
	 */
	function removeEmail($email)
	{
		$sub = DataMapperManager::findBy('nlsubscriber', 'email', $email);

		if (!$sub)
		{
			throw new \Exception("Email $email does not exists");
		}
		
		$sub->isactive = 0;

		return DataMapperManager::update($sub);
	}

	/**
	 *
	 * @param string $email
	 * @return boolean
	 */
	function emailExists($email)
	{
		$sub = DataMapperManager::findBy('nlsubscriber', 'email', $email);
		return (boolean) $sub;
	}
	
	/**
	 *
	 * @param string $email
	 * @return boolean
	 */
	function emailIsActive($email)
	{
		$sub = DataMapperManager::findBy('nlsubscriber', 'email', $email);
		return (boolean) ($sub ? $sub->isactive : null);
	}

}
