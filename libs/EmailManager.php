<?php
namespace Stayfilm\stayzen;

use TijsVerkoyen\CssToInlineStyles as inliner;
use \Stayfilm\stayzen\ORM as orm;
use phpcassa\UUID;
use Guzzle\Http\Client;
use Guzzle\Plugin\Async\AsyncPlugin;
use \Stayfilm\stayzen\services as serv;

/**
 * Class' Description
 *
 * @author Lucas Garcia Daveis
 */
class EmailManager
{
	static $instance = null;

	protected $emails = array();


	protected function __construct()
	{
	}


	/**
	 *
	 * @return EmailManager
	 */
	public static function getInstance()
	{
		if ( ! isset(self::$instance))
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function registerEmail($emailName, $emailInstance)
	{
		$this->emails[$emailName] = $emailInstance;
	}

	public function registerSendEmailUrl($url)
	{
		$this->sendEmailUrl = $url;
	}

	public function getEmailInstance($name)
	{
		return isset($this->emails[$name]) ? $this->emails[$name] : null;
	}

	/**
	 *
	 * @param type $mail
	 */
	public function send($email)
	{
		$user = $email->getRecipientUser();

		$reflectorExc = new \ReflectionClass($email);
		$className = $reflectorExc->getShortName();

		if ($user)
		{
			$userServ = serv\UserService::getInstance();

			$unregisteredEmails = $userServ->getEmailConfig($user);

			if (in_array($className, $unregisteredEmails))
			{
				return;
			}
		}

		$emailConf = Application::$config->email;

		if ($className === 'ExceptionOccurred')
		{
			$transport = $emailConf->transport_exception;
		}
		else
		{
			$transport = $emailConf->transport;
		}

		$from    = $emailConf->mail_from;
		$to      = $email->getEmail();
		$subject = $email->getSubject();

		debug('Sending email to ' . $to);

		$body = $email->getBody();

		if (STAYZEN_ENV !== 'prod' || $emailConf->debug )
		{
			//TODO : move that code to AbsEmail
			$data = array();
			$data['originalEmail'] = htmlentities($to);
			$to = $emailConf->debug_addresses;

$debugHTML =<<<txt
<h1><font color="#ff0000">ATENTION: THIS IS A DEBUG E-MAIL</font></h1>
Original e-mail:{$data['originalEmail']}
<br />
<br />
<hr />
txt;
			$body = str_replace('{{debug}}', $debugHTML, $body);
			$subject = "DEBUG EMAIL - $subject";
		}
		else
		{
			$body = str_replace('{{debug}}', '', $body);
		}

		$premailer = new inliner\CssToInlineStyles($body);
		$premailer->setUseInlineStylesBlock();
		$premailer->setStripOriginalStyleTags();
		$premailer->setCleanup();

		$body = $premailer->convert();

		info('Sending email to ' . $to);

		switch ($transport)
		{
			case 'sendgrid':

				$sendGrid = new \SendGrid($emailConf->sendgrid_username, $emailConf->sendgrid_password);
				$mail = new \SendGrid\Mail();
				$to = explode(',', $to);

				foreach($to as $t)
				{
					$mail->addTo($t);
				}

				$mail->setFrom($from)->setSubject($subject)->setHtml($body);
				$mail->setFromName('Stayfilm');
				$mail->setReplyTo($from);
				$sendGrid->smtp->send($mail);

				break;
			case 'mail':
				@mail($to, $subject, $body);
				break;
			case 'smtp':

				$mail = new \PHPMailer;
				$mail->isSMTP();
				$mail->SMTPDebug = 0;// 1, 2
				$mail->Host       = $emailConf->phpmailer_host;
				$mail->Port       = $emailConf->phpmailer_port;
				$mail->SMTPAuth   = $emailConf->phpmailer_smtpauth;
				$mail->SMTPSecure = $emailConf->phpmailer_smtpsecure;
				$mail->Username   = $emailConf->phpmailer_username;
				$mail->Password   = $emailConf->phpmailer_password;

				$mail->From = $from;
				$mail->FromName = 'Stayfilm';

				$to = explode(',', $to);

				foreach($to as $t)
				{
					$mail->addAddress($t);  // Add a recipient
				}

				$mail->isHTML(true);

				$mail->Subject = $subject;
				$mail->Body    = $body;

				if ( ! $mail->send()) {
					throw new \Exception("Fail to send email");
				}

				break;
			case 'queue':
				$emailModel = new ORM\EmailModel();
				$emailConf  = Application::$config->email;

				$emailModel->idemail  = UUID::uuid4()->string;
				$emailModel->mailfrom = $from;
				$emailModel->mailto   = $to;
				$emailModel->subject  = $subject;
				$emailModel->bodyhtml = $body;

				$emailServ = serv\EmailService::getInstance();

				$inserted = $emailServ->create($emailModel);

				if ( ! $this->sendEmailUrl)
				{
					throw new \Exception('Missing sendEmailUrl');
				}

				$client = new Client($this->sendEmailUrl);

				$client->addSubscriber(new AsyncPlugin());

				//$request = $client->post('', array(), array('email' => 'olaaaaaaa'));//serialize($email)
				$request = $client->get();

				$query = $request->getQuery();

				$query->set('idemail', $inserted->idemail);

				try
				{
					$request->send();
				} catch (Exception $ex)
				{
					throw $ex;
				}

				break;
			default:
				throw new \Exception("Email transport invalid {$emailConf->transport}");
		}

		return;
	}
}
