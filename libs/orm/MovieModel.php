<?php
namespace Stayfilm\stayzen\ORM;

use Stayfilm\stayzen\ORM\Model;
use Stayfilm\stayzen\ORM as orm;
use Stayfilm\stayzen\Application;
use Stayfilm\stayzen\services as serv;
/**
 * Description of UserModel
 *
 * @author julien
 */
class MovieModel extends Model
{
	const AD        = 4;
	const PUBLIC_   = 3;
	const FRIEND    = 2;
	const PRIVATE_  = 1;

	const STATUS_DELETED    = 0;
	const STATUS_ACTIVE     = 1;
	const STATUS_DENOUNCE   = 2;
	const STATUS_PENDING    = 3;
	const STATUS_ONAPPROVAL = 4;
	const STATUS_UNAPPROVED = 5;
	const STATUS_UNLISTED_PENDING   = 6;
	const STATUS_UNLISTED_PUBLISHED = 7;

	const TEMPLATE_VERSION_PHOTO       = 1;
	const TEMPLATE_VERSION_PHOTOEVIDEO = 2;

	const ACTION_CURATION = 'curation';
	const ACTION_MONITOR  = 'monitor';

	/**
	 *
	 * @var  \Stayfilm\stayzen\ORM\UserModel
	 */
	protected $user = null;

	/**
	 *
	 * @var \Stayfilm\stayzen\ORM\ThemeModel
	 */
	protected $theme = null;

	/**
	 *
	 * @var string
	 */
	protected $name = 'dbsite.movie';

	/**
	 *
	 * @return \Stayfilm\stayzen\ORM\UserModel
	 */
	function getUser($emptyObject = TRUE, $selectFields = array())
	{
		if ($this->user === NULL) {
			$userServ = \Stayfilm\stayzen\services\UserService::getInstance();
			$this->user =  $userServ->getUserByKey($this->iduser, $selectFields);
		}

		if ($emptyObject && ! $this->user)
		{
			return new UserModel();
		}

		return $this->user;
	}

	/**
	 *
	 * @return \Stayfilm\stayzen\ORM\ThemeModel
	 */
	function getTheme()
	{
		if ($this->theme === NULL && $this->idtheme) {
			$themeServ = serv\ThemeService::getInstance();
			$this->theme =  $themeServ->get($this->idtheme);
		}

		if (!$this->theme)
		{
			return new orm\ThemeModel();
		}

		return $this->theme;
	}

	function getGenre()
	{
		if ($this->genre === NULL && $this->idgenre) {
			$genreServ = serv\GenreService::getInstance();
			$this->genre =  $genreServ->get($this->idgenre);
		}

		if ( ! $this->genre)
		{
			return new orm\GenreModel();
		}

		return $this->genre;
	}

	function getSubtheme()
	{
		if ($this->subtheme === NULL && $this->idsubtheme) {
			$themeServ = serv\ThemeService::getInstance();
			$this->subtheme =  $themeServ->getSubtheme($this->idsubtheme);
		}

		if ( ! $this->subtheme)
		{
			return new SubthemeModel();
		}

		return $this->subtheme;
	}

	/**
	 *
	 * @param string $size
	 * @return string
	 */
	function getImageUrl($size)
	{
		$movieServ = serv\MovieService::getInstance();

		return $movieServ->getImageUrl($this, $size);
	}

	/**
	 *
	 * @return int
	 */
	function commentCount()
	{
		return 9;
	}

	/**
	* @return array
	*/
	static function getPermissionList()
	{
		return array(self::PUBLIC_, self::PRIVATE_, self::FRIEND);
	}

	static function getStatusList()
	{
		return array(self::STATUS_ACTIVE, self::STATUS_DELETED, self::STATUS_DENOUNCE, self::STATUS_PENDING, self::STATUS_ONAPPROVAL,
			self::STATUS_UNAPPROVED, self::STATUS_UNLISTED_PENDING, self::STATUS_UNLISTED_PUBLISHED);
	}

	/**
	 * Get a static const property of this class.
	 * @param type $property
	 * @return type string or null
	 */
	static function getStaticProp($property = NULL)
	{
		if ( ! $property)
		{
			return NULL;
		}

		$movieModel = new MovieModel();
		$className = get_class($movieModel);
		$movieRef = new \ReflectionClass($className);

		return $movieRef->getConstant($property);
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

		if ($name === 'videourl')
		{
			$videoUrl = str_replace('https://', 'http://', $val);

			if (Application::$config->enable_videourl_azure_cdn)
			{
				$containerConf = Application::$config->melies_azure_movie_container;
				$stayfilmCdn = Application::$config->melies_azure_cdn_url;
				$container = str_replace('http://', '', $containerConf['url']);
				$find = strpos($videoUrl, $container);

				if ($find !== FALSE)
				{
					$videoUrl = str_replace($container, $stayfilmCdn, $videoUrl);
				}
			}

			return $videoUrl;
		}

		return $val;
	}
}

