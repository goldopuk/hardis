<?
namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen as zen;

class DenounceModel extends Model
{
	const STATUS_INACTIVE  = 0;
	const STATUS_ACTIVE    = 1;

	protected $name = "dbsite.denounce";
}

class FriendshipRequestCoreModel extends Model
{
	protected $name = 'dbsite.friendshiprequestcore';
}

class LogModel extends Model
{
	protected $name = 'dbsite.log';
}

class FriendshipRequestModel extends Model
{
	const PENDING = 0;
	const ACCEPTED = 1;
	const REJECTED = 2;

	protected $name = 'dbsite.friendshiprequest';
}

class GalleryModel extends Model
{
	protected $name = 'dbsite.gallery';
}

class GenreModel extends Model
{
	protected $name = 'dbsite.genre';
}

class InviteModel extends Model
{
	protected $name = 'dbsite.invite';
}

class JobModel extends Model
{
	const FAILURE = 'FAILURE';
	const CANCELED = 'CANCELED';
	const SUCCESS = 'SUCCESS';
	const PENDING = 'PENDING';
	const INVALID = 'INVALID';

	const TYPE_MELIES = 'timeline';
	const TYPE_SOCIALNETWORK = 'socialnetwork';
	const TYPE_IMAGEANALYZER = 'imageanalyzer';

	protected $name = 'dbsite.job';

	public function addData($key, $additionalData)
	{
		$data = $this->data;

		if (isset($data[$key]))
		{
			throw new \Exception("Key {$key} already exists in job data.");
		}

		$data[$key] = $additionalData;

		$this->data = $data;
	}

	public function getData($key)
	{
		if (isset($this->data[$key])) {
			return $this->data[$key];
		}

		return null;
	}
}

class MovieCommentCoreModel extends Model
{
	const ACTIVE = 1;
	const DELETED = 2;
	const PENDING = 3;


	protected $name = 'dbsite.moviecommentcore';
}

class MovieCommentModel extends Model
{
	protected $name = 'dbsite.moviecomment';

	/**
	 *
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	function getUser()
	{
		if ($this->user === NULL) {
			$userServ = \Stayfilm\stayzen\services\UserService::getInstance();
			$this->user =  $userServ->getUserByKey($this->iduser);
		}

		if (!$this->user)
		{
			return new UserModel();
		}

		return $this->user;
	}

	function getCommentCore()
	{
		return DataMapperManager::findByKey('dbsite.moviecommentcore', $this->idmoviecommentcore);
	}
}

class MovieLikeModel extends Model
{
	protected $name = 'dbsite.movielike';
}

class MovieShareModel extends Model
{
	protected $name = 'dbsite.movieshare';
}

class MusicModel extends Model
{
	protected $name = 'dbsite.music';
}

class NlSubscriberModel extends Model
{
	protected $name = 'dbsite.nlsubscriber';
}

class NotificationCoreModel extends Model
{
	protected $name = 'dbsite.notificationcore';
}

class NotificationModel extends Model
{
	const STATUS_UNREAD  = 0;
	const STATUS_READ    = 1;

	protected $name = 'dbsite.notification';
}

class PasswordRecoverModel extends Model
{
	protected $name = 'dbsite.passwordrecover';
}

class PasswordRecoverAttemptModel extends Model
{
	protected $name = 'dbsite.passwordrecoverattempt';
}

class RecipeModel extends Model
{
	protected $name = 'dbsite.recipe';
}

class SubthemeModel extends Model
{
	protected $name = 'dbsite.subtheme';
}

class ThemeModel extends Model
{
	protected $name = 'dbsite.theme';
}

class ThemeSubthemeModel extends Model
{
	protected $name = 'dbsite.theme_subtheme';

}

class TimelineCoreModel extends Model
{
	protected $name = 'dbsite.timelinecore';
}

class TimelineModel extends Model
{
	const TYPE_MOVIESHARE = 'movie-share';
	const TYPE_MOVIE      = 'movie';
	const TYPE_COMMENT    = 'comment';
	const TYPE_FRIENDSHIP = 'friendship';

	protected $name = 'dbsite.timeline';
}

class UserFriendsModel extends Model
{
	protected $name = 'dbsite.userfriends';
}

class UserSearchModel extends Model
{
	protected $name = 'dbsite.usersearch';

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
}

class MovieSearchModel extends Model
{
	protected $name = 'dbsite.moviesearch';

	/**
	 *
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	function getUser($emptyObject = true)
	{
		if ($this->user === NULL) {
			$userServ = \Stayfilm\stayzen\services\UserService::getInstance();
			$this->user =  $userServ->getUserByKey($this->iduser);
		}

		if ($emptyObject && ! $this->user)
		{
			return new UserModel();
		}

		return $this->user;
	}
}

class UserSessionModel extends Model
{
	protected $name = 'dbsite.usersession';
}

class UserTokenModel extends Model
{
	protected $name = 'dbsite.usertoken';
}

class MovieViewModel extends Model
{
	protected $name = 'dbsite.movieview';
}

class ExceptionModel extends Model
{
	protected $name = 'dbsite.exception';
}

class ConfigModel extends Model
{
	protected $name = 'dbsite.config';
}

class MovieLikeByUserModel extends Model
{
	protected $name = 'dbsite.movielikebyuser';
}

class UserBySnUIDModel extends Model
{
	protected $name = 'dbsite.userbysnuid';
}

class MediaUploadModel extends Model
{
	protected $name = 'dbsite.mediaupload';
}

class SessionModel extends Model
{
	protected $name = 'dbsite.session';
}

class SessionTimeUploadModel extends Model
{
	protected $name = 'dbsite.sessiontime';
}

class CodecStatModel extends Model
{
	protected $name = 'dbsite.codecstat';
}

class SolrPingModel extends Model
{
	protected $name = 'dbsite.solrping';
}

class EmailModel extends Model
{
	protected $name = 'dbsite.email';
}

class MovieViewStatisticModel extends Model
{
	protected $name = 'dbsite.movieviewstatistic';
}

class UserConfigModel extends Model
{
	protected $name = 'dbsite.userconfig';
}

class UserLikeModel extends Model
{
	protected $name = 'dbsite.userlike';
}

class LikeHistoryModel extends Model
{
	protected $name = 'dbsite.likehistory';
}

class GenreTemplateModel extends Model
{
	protected $name = 'dbsite.genretemplate';

	function getRequiredMediaCount()
	{
		return $this->getRequiredPhotoCount() + $this->getRequiredVideoCount();
	}

	function getRequiredPhotoCount()
	{
		if ($this->data)
		{
			$data = json_decode($this->data, TRUE);

			if ($data && isset($data['photos']))
			{
				return (integer)ceil(($data['photos'] > 0 ? $data['photos'] * 1.3 : 0));
			}
		}

		return 0;
	}

	function getRequiredVideoCount()
	{
		if ($this->data)
		{
			$data = json_decode($this->data, TRUE);

			if ($data && isset($data['videos']))
			{
				return (integer)ceil(($data['videos'] > 0 ? $data['videos'] * 1.3 : 0));
			}
		}

		return 0;
	}
}

class MeliesInfoModel extends Model
{
	protected $name = 'dbsite.meliesinfo';
}

class CustomerModel extends Model
{
	protected $name = 'dbsite.customer';
}

class GenreCustomerModel extends Model
{
	protected $name = 'dbsite.genrecustomer';
}

class MediaUploadTempModel extends Model
{
	protected $name = 'dbsite.mediauploadtemp';
}

class VoucherModel extends Model
{
	protected $name = 'dbsite.voucher';
}

class User2CampaignModel extends Model
{
	protected $name = 'dbsite.user2campaign';
}

class CampaignModel extends Model
{
	protected $name = 'dbsite.campaign';
}

class JobPendingModel extends Model
{
	protected $name = 'dbsite.jobpending';
}

class TimelineReferenceModel extends Model
{
	protected $name = 'dbsite.timelinereference';
}

class MovieDataModel extends Model
{
	protected $name = 'dbsite.moviedata';
}

class UserFollowerModel extends Model
{
	protected $name = 'dbsite.userfollower';
}

class UserFollowingModel extends Model
{
	protected $name = 'dbsite.userfollowing';
}

class Customer2MovieModel extends Model
{
	protected $name = 'dbsite.customer2movie';
}

class CampaignGenreModel extends Model
{
	protected $name = 'dbsite.campaigngenre';
}

class SlugCampaignModel extends Model
{
	protected $name = 'dbsite.slugcampaign';
}

class CampaignSlugModel extends Model
{
	protected $name = 'dbsite.campaignslug';
}

class Campaign2MovieModel extends Model
{
	protected $name = 'dbsite.campaign2movie';
}

class Send2ApproveFailedModel extends Model
{
	protected $name = 'dbsite.send2approvefailed';
}

class Send2MonitorFailedModel extends Model
{
	protected $name = 'dbsite.send2monitorfailed';
}

class KeyStoreModel extends Model
{
	protected $name = 'dbsite.keystore';
}

class MeliesVmModel extends Model
{
	const STATUS_FIXED           = '1';
	const STATUS_TERMINATED      = '2';
	const STATUS_CREATING        = '3';
	const STATUS_PENDING         = '4';
	const STATUS_ALIVE           = '5';
	const STATUS_SHUTTING_DOWN   = '6';
	const STATUS_RESERVED        = '7';

	const AWS_PENDING            = '0';
	const AWS_RUNNING            = '16';
	const AWS_SHUTTING_DOWN      = '32';
	const AWS_TERMINATED         = '48';
	const AWS_STOPPING           = '64';
	const AWS_STOPPED            = '80';

	protected $name = 'dbsite.meliesvm';
}

class ImageAnalyzerInfoModel extends Model
{
	protected $name = 'dbsite.imageanalyzerinfo';
}

class SiteRouteModel extends Model
{
	protected $name = 'dbsite.siteroute';
}

class UserDeviceModel extends Model
{
	protected $name = 'dbsite.userdevice';
}

class DeviceUserModel extends Model
{
	protected $name = 'dbsite.deviceuser';
}

class PushNotificationModel extends Model
{
	protected $name = 'dbsite.pushnotification';
}
