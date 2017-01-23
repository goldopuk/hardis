<?php

namespace Stayfilm\stayzen\services;

use Stayfilm\stayzen\services as serv;
use Stayfilm\stayzen as zen;

class MobileService extends Service
{
	static protected $_instance = null;

	/**
	 * DO NOT DELETE - For INTELISENSE
	 *
	 * @return \Stayfilm\stayzen\services\MobileService
	 */
	static public function getInstance()
	{
		return parent::getInstance();
	}

	/**
	 *
	 * @return array
	 */
	public function getGalleryList($device = NULL)
	{
		$genreServ = serv\GenreService::getInstance();
		$campaignServ = serv\CampaignService::getInstance();
		$keyStoreServ = serv\KeyStoreService::getInstance();

		$galleryKey = 'fun';

		if ($device && $device === 'windows-phone')
		{
			$galleryKey = 'windows-phone';
		}

		$list = $keyStoreServ->get($galleryKey, 'gallery', 'list');

		$finalList = array();

		if ($list)
		{
			foreach ($list['list'] as $item)
			{
				if ($item['type'] === 'genre')
				{
					$object = $genreServ->getGenreBySlug($item['slug']);

					if ( ! $object || ! $object->isactive)
					{
						continue;
					}

					$object->addData('imageUrl', zen\Application::$config->fun_gallery_base_image_url . 'genres/' . $object->slug . '.jpg');

				}
				else if ($item['type'] === 'campaign')
				{
					$object = $campaignServ->getCampaignBySlug($item['slug']);

					if ( ! $object || ! $object->isactive)
					{
						continue;
					}

					$object->addData('imageUrl', zen\Application::$config->fun_gallery_base_image_url . 'campaigns/' . $object->slug . '.jpg');
				}

				$finalList[] = $object;
			}
		}

		return $finalList;
	}

	public function getMovieMakerGenreList($campaign, $device = NULL, $listKey = NULL)
	{
		$genreServ = serv\GenreService::getInstance();
		$campaignServ = serv\CampaignService::getInstance();
		$keyStoreServ = serv\KeyStoreService::getInstance();

		// Genres by campaign will be available under staff approval. for now, get from keystore.
		//$genres = $genreServ->getGenresByCampaign($campaign);

		$genreListKey = 'fun';

		if ($device && $device === 'windows-phone')
		{
			$genreListKey = 'windows-phone';
		}

		if ($listKey)
		{
			$genreListKey = $listKey;
		}

		$list = $keyStoreServ->get($genreListKey, 'moviemaker', 'genre_list');

		$genres = array();
		$genreBaseImageUrl = '';

		if ($list && $list['genre_list'] && $list['genre_list']['slugs'])
		{
			foreach ($list['genre_list']['slugs'] as $slug)
			{
				$object = $genreServ->getGenreBySlug($slug);

				if ( ! $object || ! $object->isactive)
				{
					continue;
				}

				$genres[] = $object;
			}

			$genreBaseImageUrl = $list['genre_list']['fun_moviemaker_genre_base_image_url'];
		}

		$finalList = array();

		foreach ($genres as $genre)
		{
			$genre->addData('imageUrl', $genreBaseImageUrl . $genre->slug . '.jpg');

			$genreConfig = $genreServ->getConfig($genre, $campaign);

			$genreCampaign = $campaignServ->getCampaignBySlug($genreConfig['campaignslug']);

			$genreConfig['campaign'] = json_decode($genreCampaign, TRUE);

			$genre->addData('config', $genreConfig);

			$finalList[] = $genre;
		}

		return $finalList;
	}

}
