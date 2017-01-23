<?php
namespace Stayfilm\stayzen\services;

use Aws\Ec2\Ec2Client;
use Stayfilm\stayzen\ORM\DataMapperManager;
use Stayfilm\stayzen\ORM\MeliesVmModel;

class MeliesVmService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.meliesvm';

	protected $ec2Client;

	public function __construct()
	{
		// These keys should be changed once per year
		$this->ec2Client = Ec2Client::factory( array(
			'key' => 'AKIAJ3TY2FAM2C3IXMEQ',
			'secret' => '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',
			'region' => 'us-east-1'
		) );

		parent::__construct();
	}

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MeliesVmService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	public function awsCreate($imageId = NULL, $tag = NULL)
	{
		$dataResp = array();

		try
		{
			if ($imageId)
			{
				$p_imageId = $imageId;
			}
			else
			{
				throw new Exception('Parameter "imageId" is required');
			}

			if ($tag)
			{
				$p_tag = $tag;
			}
			else
			{
				throw new Exception('Parameter "tag" is required');
			}

			$result = $this->ec2Client->runInstances(array(
				'MinCount' => 1,
				'MaxCount' => 1,
				'ImageId' => $p_imageId,
				'KeyName' => 'Melies',
				'InstanceType' => 'g2.2xlarge',
				'Placement' => array('AvailabilityZone' => 'us-east-1a')
			) );

			$instanceId = $result['Instances'][0]['InstanceId'];

			$dataResp['code'] = 0;
			$dataResp['message'] = 'Instance successfully created!';
			$dataResp['instanceId'] = $instanceId;

			try
			{
				$result = $this->ec2Client->createTags( array(
					'Resources' => array( $instanceId ),
					'Tags' => array(
						array(
							'Key' => 'Name',
							'Value' => $p_tag,
						),
					),
				));
				$dataResp['result'] = $result;
			}
			catch (Exception $e)
			{
				$dataResp['code'] = 1;
				$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
			}
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsAssociateIp($instanceId, $ip)
	{
		$dataResp = array();

		try
		{
			if ($instanceId)
			{
				$p_instanceId = $instanceId;
			}
			else
			{
				throw new Exception('Parameter "instanceId" is required' );
			}

			if ($ip)
			{
				$p_ip = $ip;
			}
			else
			{
				throw new Exception('Parameter "ip" is required' );
			}

			$result = $this->ec2Client->associateAddress(array(
				'InstanceId' => $p_instanceId,
				'PublicIp' => $p_ip
			));

			$dataResp['code'] = 0;
			$dataResp['message'] = 'The IP address has been successfully associated to the instance.';
			$dataResp['result'] = $result;
		}
		catch (Exception $e)
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsAllocateIp()
	{
		$dataResp = array();

		try
		{
			$result = $this->ec2Client->allocateAddress(array('Domain' => 'standard'));

			$dataResp['code'] = 0;
			$dataResp['message'] = 'the ip has been successful allocated';
			$dataResp['result'] = $result; // $result['PublicIp']
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsDisassociateIp($ip)
	{
		$dataResp = array();

		try
		{
			if ($ip)
			{
				$p_ip = $ip;
			}
			else
			{
				throw new \Exception('Parameter "ip" is required');
			}

			$result = $this->ec2Client->disassociateAddress( array(
				'PublicIp' => $p_ip
			) );

			$dataResp['code'] = 0;
			$dataResp['message'] = 'The IP address has been successfully disassociated from the instance.';
			$dataResp['result'] = $result;
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsList()
	{
		$dataResp = array();

		try
		{
			$result = $this->ec2Client->describeInstances( null );

			$reservations = $result['Reservations'];

			$instances = array();

			foreach ( $reservations as $reservation )
			{
				$instance = $reservation['Instances'][0];

				if ($instance)
				{
					$instance_json = array();

					$instance_json['InstanceId'] = $instance['InstanceId'];
					$instance_json['PublicIpAddress'] = isset($instance['PublicIpAddress']) ? $instance['PublicIpAddress'] : '';
					$instance_json['PrivateIpAddress'] = isset($instance['PrivateIpAddress']) ? $instance['PrivateIpAddress'] : '';
					$instance_json['StateCode'] = $instance['State']['Code'];
					$instance_json['StateMessage'] = $this->getAwsStatusName($instance['State']['Code'] * 1);

					$tags = $instance['Tags'];

					if ( $tags )
					{
						foreach ( $tags as $tag )
						{
							if ( $tag['Key'] == "Name" )
							{
								$instance_json['tagName'] = $tag['Value'];
							}
						}
					}

					$instances[] = $instance_json;
				}
			}

			$dataResp['code'] = 0;
			$dataResp['instances'] = $instances;
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsReleaseIp($ip)
	{
		$dataResp = array();

		try
		{
			if ($ip)
			{
				$p_ip = $ip;
			}
			else
			{
				throw new \Exception('Parameter "ip" is required');
			}

			$result = $this->ec2Client->releaseAddress(array(
				'PublicIp' => $p_ip
			));

			$dataResp['code'] = 0;
			$dataResp['message'] = 'The IP address has been successfully released.';
			$dataResp['result'] = $result;
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function awsTerminate($instanceId)
	{
		$dataResp = array();

		try
		{
			if ($instanceId)
			{
				$p_instanceId = $instanceId;
			}
			else
			{
				throw new \Exception('Parameter "instanceId" is required');
			}

			$result = $this->ec2Client->terminateInstances(array('InstanceIds' => array($p_instanceId)));

			$dataResp['code'] = 0;
			$dataResp['message'] = 'The IP address has been successfully released.';
			$dataResp['result'] = $result;
		}
		catch ( Exception $e )
		{
			$dataResp['code'] = 1;
			$dataResp['message'] = "An error has occurred. Message: {$e->getMessage()}";
		}

		return $dataResp;
	}

	public function getAwsStatusName($awsCode = NULL)
	{
		if ( ! $awsCode)
		{
			return 'No code supplied.';
		}

		$desc = '';

		switch ($awsCode)
		{
			case MeliesVmModel::AWS_PENDING:
				$desc = 'Pending';
				break;
			case MeliesVmModel::AWS_RUNNING:
				$desc = 'Running';
				break;
			case MeliesVmModel::AWS_SHUTTING_DOWN:
				$desc = 'Shutting Down';
				break;
			case MeliesVmModel::AWS_TERMINATED:
				$desc = 'Terminated';
				break;
			case MeliesVmModel::AWS_STOPPING:
				$desc = 'Stopping';
				break;
			case MeliesVmModel::AWS_STOPPED:
				$desc = 'Stopped';
				break;
		}

		return $desc;
	}

	public function getMeliesInfoOverallWeight()
	{
		$melieses = DataMapperManager::findAll('dbsite.meliesinfo');

		$totalWeight = 0.0;

		$overall = array();

		foreach ($melieses as $melies)
		{
			$totalWeight += $melies->weight;
		}

		$countMelies = count($melieses);
		$overallWeight = $countMelies > 0 ? $totalWeight / $countMelies : 1;

		$overall['countMelies'] = $countMelies;
		$overall['overallWeight'] = $overallWeight;

		return $overall;
	}

	public function getAwsInstance($instanceId)
	{
		$listInstances = $this->AwsList();
		$foundInstance = NULL;

		if ( ! isset($listInstances['instances']))
		{
			return $foundInstance;
		}

		foreach ($listInstances['instances'] as $instance)
		{
			if (isset($instance['InstanceId']) && $instance['InstanceId'] === $instanceId)
			{
				$foundInstance = $instance;
				break;
			}
		}

		return $foundInstance;
	}
}