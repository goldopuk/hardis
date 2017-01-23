<?php
namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen\ORM\Model;
/**
 * Description of PartyModel
 *
 * @author fabiano
 */
class PartyInviteModel extends Model
{
	const STATUS_ACTIVE = 1;

	const STATUS_INACTIVE = 0;

	/**
	 *
	 * @var string
	 */
	protected $name = 'dbsite.partyinvite';
}