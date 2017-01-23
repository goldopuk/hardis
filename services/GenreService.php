<?php
namespace Stayfilm\stayzen\services;

use \Stayfilm\stayzen\ORM\DataMapperManager;
use \Stayfilm\stayzen\ORM\GenreTemplateModel;
use \Stayfilm\stayzen\Application;
use Stayfilm\stayzen\services as serv;

class GenreService extends TableService
{
	static protected $_instance = null;

	protected $table = 'dbsite.genre';

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\GenreService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	* Get all active movie genres;
	* @return array \Stayfilm\stayzen\ORM\GenreModel
	*/
	public function getMovieGenres($onlyActive = TRUE)
	{
		$idGlobalCustomer = Application::$config->global_customer; // must ALWAYS be defined in config.
		$genresGlobal = DataMapperManager::findAll('dbsite.genrecustomer', array(), 100);
		$movieGenres = array();

		foreach ($genresGlobal as $genreCustomer)
		{
			$genre = $this->getGenre($genreCustomer->idgenre);

			if ($onlyActive && $genre->isactive === 0)
			{
				continue;
			}

			if (STAYZEN_ENV === 'prod' && ($genre->isactive !== 1 && $genre->isactive !== 3))
			{
				continue;
			}

			if (STAYZEN_ENV === 'staging' && ($genre->isactive !== 2 && $genre->isactive !== 3))
			{
				continue;
			}

			if ($genreCustomer->idcustomer == $idGlobalCustomer)
			{
				$movieGenres[] = $genre;
			}
		}

		return $movieGenres;
	}

	public function getMovieGenresByCustomer($idcustomer, $onlyActive = TRUE)
	{
		$movieGenres = array();
		$genresCustomer = DataMapperManager::findAllBy('dbsite.genrecustomer', array('idcustomer'), array($idcustomer), array(), 100);

		foreach ($genresCustomer as $genreCustomer)
		{
			$genre = $this->getGenre($genreCustomer->idgenre);

			if ( $onlyActive && $genre->isactive === 0)
			{
				continue;
			}

			if (STAYZEN_ENV === 'prod' && ($genre->isactive !== 1 && $genre->isactive !== 3))
			{
				continue;
			}

			if (STAYZEN_ENV === 'staging' && ($genre->isactive !== 2 && $genre->isactive !== 3))
			{
				continue;
			}

			if ($genreCustomer->idcustomer === $idcustomer)
			{
				$movieGenres[] = $genre;
			}
		}

		return $movieGenres;
	}

	public function getGenresByUser($user, $onlyActive = TRUE)
	{
		$userServ = UserService::getInstance();
		$campServ = CampaignService::getInstance();
		$idGlobalCustomer = Application::$config->global_customer; // must ALWAYS be defined in config.

		$genres = $this->getMovieGenresByCustomer($idGlobalCustomer);

		$activeCampaigns = $campServ->getActiveCampaigns();

		foreach ($activeCampaigns as $c)
		{
			$list = $this->getMovieGenresByCustomer($c->idcustomer);

			foreach ($list as $genre)
			{
				if ($onlyActive && $genre->isactive === 0)
				{
					continue;
				}

				// Customer Area - Durex
				if ($genre->slug === 'durex' &&  ! $userServ->getConfigValue($user, 'voucher_durex'))
				{
					continue;
				}

				$genres[] = $genre;
			}
		}

		return $genres;
	}

	public function getGenre($idgenre)
	{
		// isactive field:
		// 0 - inactive
		// 1 - prod
		// 2 - staging
		// 3 - staging and prod
		return DataMapperManager::findByKey('dbsite.genre', $idgenre);
	}

	public function getGenres()
	{
		// isactive field:
		// 0 - inactive
		// 1 - prod
		// 2 - staging
		// 3 - staging and prod
		return DataMapperManager::findAll('dbsite.genre');
	}

	public function getGenreBySlug($slug)
	{
		$fields = array();
		$fields[0] = 'slug';

		$values = array();
		$values[0] = $slug;

		return DataMapperManager::findBy('dbsite.genre', $fields, $values);
	}

	public function getTemplatesByGenre($idgenre = NULL, $all = FALSE)
	{
		if ( ! $idgenre)
		{
			throw new \Exception('Missing idgenre.');
		}

		$fields = $values = $selectFields = array();

		$fields[] = 'idgenre';
		$values[] = $idgenre;
		$selectFields[] = 'idtemplate';
		$selectFields[] = 'isactive';
		$selectFields[] = 'data';
		$selectFields[] = 'name';
		$selectFields[] = 'image';
		$selectFields[] = 'thumbnail';

		$result = array();
		$genreTemplates = DataMapperManager::findAllBy('dbsite.genretemplate', $fields, $values, $selectFields, 100);

		if ($all)
		{
			return $genreTemplates;
		}

		foreach ($genreTemplates as $genreTemplate)
		{
			if ($genreTemplate->isactive)
			{
				$result[] = $genreTemplate;
			}
		}

		return $result;
	}

	public function getGenreTemplate($idgenre = NULL, $idtemplate = NULL)
	{
		if ( ! $idgenre)
		{
			throw new \Exception('Missing idgenre.');
		}

		if ( ! $idtemplate)
		{
			throw new \Exception('Missing idtemplate.');
		}

		$fields = $values = array();

		$fields[] = 'idgenre';
		$fields[] = 'idtemplate';
		$values[] = $idgenre;
		$values[] = $idtemplate;

		return DataMapperManager::findBy('dbsite.genretemplate', $fields, $values);
	}

	public function updateGenreTemplate($idgenre, $idtemplate, $name, $isactive = NULL)
	{
		if ( ! $idgenre || ! $idtemplate || ! $name)
		{
			throw new \Exception('Missing parameters to update genretemplate table.');
		}

		$genreTemplate = new GenreTemplateModel();
		$genreTemplate->idtemplate = $idtemplate;
		$genreTemplate->idgenre = $idgenre;
		$genreTemplate->name =  $name;

		if ($isactive != NULL)
		{
			$genreTemplate->isactive = $isactive;
		}

		DataMapperManager::create($genreTemplate);
	}

	public function saveGenreTemplate($idgenre, $idtemplate, $description = '', $photos = '', $videos = '',
		$onlyPhotos = '', $intervention_color = '', $intervention_text = '', $imageUrl = '', $thumbnailUrl = '')
	{
		if ( ! $idgenre || ! $idtemplate)
		{
			throw new \Exception('Missing parameters to update genretemplate table.');
		}

		$genreTemplate = $this->getGenreTemplate($idgenre, $idtemplate);

		$data = array();
		$data = $genreTemplate->data ? json_decode($genreTemplate->data, TRUE) : array();
		$data['photos'] = $photos;
		$data['videos'] = $videos;
		$data['only_photos'] = $onlyPhotos;

		$genreTemplate->idtemplate = $idtemplate;
		$genreTemplate->idgenre = $idgenre;
		$genreTemplate->name =  $description;
		$genreTemplate->data = (string)json_encode($data);
		$genreTemplate->image = $imageUrl;
		$genreTemplate->thumbnail = $thumbnailUrl;

		DataMapperManager::update($genreTemplate);
	}

	public function deleteGenreTemplate($idgenre, $idtemplate)
	{
		if ( ! $idgenre || ! $idtemplate)
		{
			throw new \Exception('Missing parameters to update genretemplate table.');
		}

		$genreTemplate = $this->getGenreTemplate($idgenre, $idtemplate);

		DataMapperManager::delete($genreTemplate);
	}

	public function getFunGalleryGenres()
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();
		$genreServ = serv\GenreService::getInstance();

		$funGenresObjs = array();
		$funGenres = $keyStoreServ->get('fun', 'gallery', 'genres');

		if (isset($funGenres['genres']) && $funGenres['genres'])
		{
			$baseUrlImages = $funGenres['genres']['genres_url_images'];

			foreach ($funGenres['genres']['slugs'] as $genreSlug)
			{
				$genre = $genreServ->getGenreBySlug($genreSlug);

				if ($genre && $genre->isactive)
				{
					$genre->galleryImageUrl = $baseUrlImages . $genreSlug . '.jpg';
					$funGenresObjs[] = $genre;
				}
			}
		}

		return $funGenresObjs;
	}

	/**
	 * @param null $campaign
	 * @param bool $onlyActive
	 * @return array
	 * @throws \Exception
	 */
	public function getGenresByCampaign($campaign = NULL, $onlyActive = TRUE, $user = NULL, $useVoucher = NULL)
	{
		if ( ! $campaign)
		{
			throw new \Exception('Missing campaign');
		}

		$userServ = UserService::getInstance();

		$field = array();
		$field[] = 'idcampaign';

		$value = array();
		$value[] = $campaign->idcampaign;

		$campaignGenres = DataMapperManager::findAllBy('dbsite.campaigngenre', $field, $value, array('idgenre', 'genreorder', 'requirevoucher'), 100);

		// Loads the genres from user campaigns, if the user has one.
		if ($user)
		{
			$userCampaign = $userServ->getUserCampaign($user);

			if ($userCampaign)
			{
				$field = array();
				$field[] = 'idcampaign';

				$value = array();
				$value[] = $userCampaign->idcampaign;

				$userCampaignGenre = DataMapperManager::findAllBy('dbsite.campaigngenre', $field, $value, array('idgenre', 'genreorder', 'requirevoucher'), 100);

				$campaignGenres = array_merge($campaignGenres, $userCampaignGenre);
			}
		}

		$isValidVoucher = FALSE;

		if ($useVoucher !== NULL)
		{
			$voucherServ = serv\VoucherService::getInstance();
			$sessionServ = serv\SessionService::getInstance();

			$isValidVoucher = $voucherServ->isValidVoucher($sessionServ->get("{$campaign->slug}_voucher"), $user, $campaign);

			if ( ! $isValidVoucher)
			{
				throw new \Exception('Invalid voucher being used.');
			}
		}

		$campaignGenresFiltered = array();

		foreach ($campaignGenres as $campaignGenre)
		{
			if ($useVoucher && $campaignGenre->requirevoucher && $isValidVoucher)
			{
				$campaignGenresFiltered[] = $campaignGenre;
			}
			else if ( ! $useVoucher && ! $campaignGenre->requirevoucher)
			{
				$campaignGenresFiltered[] = $campaignGenre;
			}
		}

		$campaignGenres = $campaignGenresFiltered;

		usort($campaignGenres, function ($el1, $el2) {
			return $el1->genreorder > $el2->genreorder;
		});

		$field = array();
		$field[] = '@idgenre';

		$value = array();

		$idgenres = array();

		foreach ($campaignGenres as $campaignGenre)
		{
			$idgenres[] = $campaignGenre->idgenre;
		}

		$idgenres = array_unique($idgenres);

		$value[] = $idgenres;

		$genres = DataMapperManager::findAllBy('dbsite.genre', $field, $value, array(), NULL);

		$filteredGenres = array();

		if ($onlyActive)
		{
			foreach ($genres as $genre)
			{
				if ($genre->isactive === 0)
				{
					continue;
				}

				if (STAYZEN_ENV === 'prod' && ($genre->isactive !== 1 && $genre->isactive !== 3))
				{
					continue;
				}

				if (STAYZEN_ENV === 'staging' && ($genre->isactive !== 2 && $genre->isactive !== 3))
				{
					continue;
				}

				$filteredGenres[] = $genre;
			}
		}
		else
		{
			$filteredGenres = $genres;
		}

		return $filteredGenres;
	}

	public function setGenreConfig($genre, $config)
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();

		foreach ($config as $key => $value)
		{
			$keyStoreServ->set('genre', $genre->idgenre, $key, $value);
		}
	}

	public function setCampaignGenreConfig($campaign, $genre, $config)
	{
		$keyStoreServ = serv\KeyStoreService::getInstance();

		$id = array();
		$id[] = $campaign->idcampaign;
		$id[] = $genre->idgenre;

		$campaignGenre = DataMapperManager::findByKey('dbsite.campaigngenre', $id);

		if ( ! $campaignGenre)
		{
			throw new \Exception("no relation between campaign and genre {$campaign->slug} {$genre->idgenre}");
		}

		foreach ($config as $key => $value)
		{
			$keyStoreServ->set('campaigngenre', "{$campaign->slug}:{$genre->idgenre}", $key, $value);
		}
	}

	public function getGenreConfig($genre, $key = NULL)
	{
		if ( ! $genre)
		{
			throw new \Exception('Missing genre parameter.');
		}

		$keyStoreServ = serv\KeyStoreService::getInstance();

		return $keyStoreServ->get('genre', $genre->idgenre, $key);
	}

	public function getCampaignGenreConfig($campaign, $genre, $key = NULL)
	{
		$id = array();
		$id[] = $campaign->idcampaign;
		$id[] = $genre->idgenre;

		$campaignGenre = DataMapperManager::findByKey('dbsite.campaigngenre', $id);

		if ( ! $campaignGenre)
		{
			return array();
		}

		$keyStoreServ = serv\KeyStoreService::getInstance();

		return $keyStoreServ->get('campaigngenre', "{$campaign->slug}:{$genre->idgenre}", $key);
	}

	public function getConfig($genre, $campaign)
	{
		if ( ! $genre || ! $campaign)
		{
			throw new \Exception('Missing parameters genre, campaign');
		}

		$campaignConfig      = array();
		$genreConfig         = array();
		$campaignGenreConfig = array();

		$campaignServ   = CampaignService::getInstance();
		$campaignConfig = $campaignServ->getConfig($campaign);

		$genreConfig = $this->getGenreConfig($genre);

		$id = array();
		$id[] = $campaign->idcampaign;
		$id[] = $genre->idgenre;

		$campaignGenre = DataMapperManager::findByKey('dbsite.campaigngenre', $id);

		if ($campaignGenre)
		{
			$campaignGenreConfig = $this->getCampaignGenreConfig($campaign, $genre);
		}

		$config = array_merge($campaignConfig, $genreConfig, $campaignGenreConfig);

		if ( ! isset($config['campaignslug']))
		{
			$config['campaignslug'] =  $campaign->slug;
		}

		return $config;
	}
}