<?php
namespace Stayfilm\stayzen\app;

use Stayfilm\stayzen\Application;

define('STAYZEN_ROOT', dirname(realpath(__FILE__)));

require_once(STAYZEN_ROOT . '/libs/Profiler.php');

$profiler = \Stayfilm\stayzen\Profiler::getInstance();
$profiler->mark('application-start');
$profiler->mark('bootstrap-start');
$profiler->mark('include-start');

require_once(STAYZEN_ROOT . '/vendor/autoload.php');
require_once(STAYZEN_ROOT . '/libs/Subscriber.php');
require_once(STAYZEN_ROOT . '/libs/Publisher.php');
require_once(STAYZEN_ROOT . '/libs/ServiceFactory.php');
require_once(STAYZEN_ROOT . '/libs/Service.php');
require_once(STAYZEN_ROOT . '/libs/TableService.php');
require_once(STAYZEN_ROOT . '/libs/Application.php');
require_once(STAYZEN_ROOT . '/libs/Utilities.php');
require_once(STAYZEN_ROOT . '/libs/ImageTransform.php');
require_once(STAYZEN_ROOT . '/libs/Bcrypt.php');
require_once(STAYZEN_ROOT . '/libs/functions.php');
require_once(STAYZEN_ROOT . '/libs/WsdlManager.php');
require_once(STAYZEN_ROOT . '/libs/SecurityManager.php');
require_once(STAYZEN_ROOT . '/libs/EmailManager.php');
require_once(STAYZEN_ROOT . '/libs/MigrationManager.php');
require_once(STAYZEN_ROOT . '/libs/Migration.php');
require_once(STAYZEN_ROOT . '/libs/SolrClient.php');
require_once(STAYZEN_ROOT . '/libs/CassaWriter.php');
require_once(STAYZEN_ROOT . '/libs/InMemoryWriter.php');
require_once(STAYZEN_ROOT . '/libs/SimpleFormatter.php');
require_once(STAYZEN_ROOT . '/libs/orm/IdentityMap.php');
require_once(STAYZEN_ROOT . '/libs/ServiceProfilerProxy.php');
require_once(STAYZEN_ROOT . '/libs/vendor/OAuth2/OAuth2.php');
require_once(STAYZEN_ROOT . '/libs/vendor/OAuth/OAuth.php');
require_once(STAYZEN_ROOT . '/libs/vendor/googleApiPhpClient/Google_Client.php');
require_once(STAYZEN_ROOT . '/libs/vendor/googleApiPhpClient/contrib/Google_PlusService.php');
require_once(STAYZEN_ROOT . '/libs/vendor/phpflickr-3.1.1/phpFlickr.php');
require_once(STAYZEN_ROOT . '/libs/MediaListManager.php');
require_once(STAYZEN_ROOT . '/services/UserService.php');
require_once(STAYZEN_ROOT . '/services/UserSessionService.php');
require_once(STAYZEN_ROOT . '/services/MovieService.php');
require_once(STAYZEN_ROOT . '/services/SearchService.php');
require_once(STAYZEN_ROOT . '/services/PasswordRecoverService.php');
require_once(STAYZEN_ROOT . '/services/ThemeService.php');
require_once(STAYZEN_ROOT . '/services/MidiaService.php');
require_once(STAYZEN_ROOT . '/services/InviteService.php');
require_once(STAYZEN_ROOT . '/services/PartyService.php');
require_once(STAYZEN_ROOT . '/services/AgentService.php');
require_once(STAYZEN_ROOT . '/services/LogService.php');
require_once(STAYZEN_ROOT . '/services/AlbumService.php');
require_once(STAYZEN_ROOT . '/services/DenounceService.php');
require_once(STAYZEN_ROOT . '/services/TimelineService.php');
require_once(STAYZEN_ROOT . '/services/JobService.php');
require_once(STAYZEN_ROOT . '/services/SessionService.php');
require_once(STAYZEN_ROOT . '/services/GenreService.php');
require_once(STAYZEN_ROOT . '/services/MusicService.php');
require_once(STAYZEN_ROOT . '/services/NewsletterService.php');
require_once(STAYZEN_ROOT . '/services/NotificationService.php');
require_once(STAYZEN_ROOT . '/services/SocialService.php');
require_once(STAYZEN_ROOT . '/services/CassaService.php');
require_once(STAYZEN_ROOT . '/services/OAuthService.php');
require_once(STAYZEN_ROOT . '/services/ExceptionService.php');
require_once(STAYZEN_ROOT . '/services/EmailService.php');
require_once(STAYZEN_ROOT . '/services/CustomerService.php');
require_once(STAYZEN_ROOT . '/services/TwitterService.php');
require_once(STAYZEN_ROOT . '/services/GenreTemplateService.php');
require_once(STAYZEN_ROOT . '/services/KeyStoreService.php');
require_once(STAYZEN_ROOT . '/services/MeliesVmService.php');
require_once(STAYZEN_ROOT . '/services/MeliesInfoService.php');
require_once(STAYZEN_ROOT . '/services/ConfigService.php');
require_once(STAYZEN_ROOT . '/services/MobileService.php');
require_once(STAYZEN_ROOT . '/services/SiteRouteService.php');
require_once(STAYZEN_ROOT . '/services/PushNotificationService.php');
require_once(STAYZEN_ROOT . '/services/SocialNetworkService.php');
require_once(STAYZEN_ROOT . '/services/CacheService.php');
require_once(STAYZEN_ROOT . '/libs/exceptions.php');

require_once(STAYZEN_ROOT . '/libs/orm/DataMapperManager.php');
require_once(STAYZEN_ROOT . '/libs/orm/DefaultDataMapper.php');
require_once(STAYZEN_ROOT . '/libs/orm/CQLQuery.php');
require_once(STAYZEN_ROOT . '/libs/orm/Model.php');
require_once(STAYZEN_ROOT . '/libs/orm/ModelFactory.php');
require_once(STAYZEN_ROOT . '/libs/orm/SchemaManager.php');
require_once(STAYZEN_ROOT . '/libs/orm/CassaClient.php');
require_once(STAYZEN_ROOT . '/libs/orm/UserModel.php');
require_once(STAYZEN_ROOT . '/libs/orm/MovieModel.php');
require_once(STAYZEN_ROOT . '/libs/orm/PartyInviteModel.php');
require_once(STAYZEN_ROOT . '/libs/orm/dbstayModels.php');
require_once(STAYZEN_ROOT . '/libs/orm/dbsiteModels.php');
require_once(STAYZEN_ROOT . '/libs/vendor/CssToInlineStyles.php');
require_once(STAYZEN_ROOT . '/libs/vendor/Flickr.php');

// Customer Services
require_once(STAYZEN_ROOT . '/services/VoucherService.php');
require_once(STAYZEN_ROOT . '/services/CampaignService.php');

require_once(STAYZEN_ROOT . '/libs/email/AbsEmail.php');
require_once(STAYZEN_ROOT . '/libs/email/AutomaticallyCreatedMovie.php');

$profiler->mark('include-end');

if ( ! isset($_SERVER['STAYZEN_ENV'])) {
	die('Please set up the environment variable STAYZEN_ENV (prod, test, dev... before using the lib');
}

define('STAYZEN_ENV', $_SERVER['STAYZEN_ENV']);
define('STAYZEN_COMMIT', file_exists('COMMIT') ? file_get_contents('COMMIT') : 'NO_COMMIT_FILE_AVAILABLE');

Application::bootstrap(STAYZEN_ENV);

$profiler->mark('bootstrap-end');

$profiler->log('Includes completed', 'include');
$profiler->log("Stayzen bootstraped (" . STAYZEN_ENV . ")", 'bootstrap');

// used in command line script only
// in staycool, another exception_handler is set
set_exception_handler(function ($e) {
	info($e);
	throw $e;
});
