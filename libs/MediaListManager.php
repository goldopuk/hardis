<?php
namespace Stayfilm\stayzen;

use Stayfilm\stayzen\exception as ex;

class MediaListManager
{
	static function getRankedMedias(array $mediaList, $genreTemplate = NULL)
	{
		if (count($mediaList) === 0)
		{
			return array();
		}

		for ($x = 0; $x < count($mediaList); $x++)
		{
			for ($y = 0; $y < count($mediaList) - 1; $y++)
			{
				if((float)$mediaList[$y + 1]['ranking'] > (float)$mediaList[$y]['ranking'])
				{
					$temp  = $mediaList[$y + 1];
					$mediaList[$y + 1] = $mediaList[$y];
					$mediaList[$y] = $temp;
				}
			}
		}

		if ($genreTemplate)
		{
			$photoNumber = $genreTemplate->getRequiredPhotoCount();
			$videoNumber = $genreTemplate->getRequiredVideoCount();
		}
		else
		{
			$photoNumber = 0;
			$videoNumber = 0;
		}

		$photoCount = 0;
		$videoCount = 0;

		$rankedMedias = array();

		if (($photoNumber + $videoNumber) === 0)
		{
			$rankedMedias = $mediaList;
		}
		else
		{
			foreach ($mediaList as $media)
			{
				if ($media['media_type'] === 1 && $photoCount === $photoNumber)
				{
					continue;
				}

				if ($media['media_type'] === 2 && $videoCount === $videoNumber)
				{
					continue;
				}

				$rankedMedias[] = $media;

				if ($media['media_type'] === 1)
				{
					$photoCount++;
				}

				if ($media['media_type'] === 2)
				{
					$videoCount++;
				}
			}
		}

		return array_slice($rankedMedias, 0, Application::$config->melies_max_objects);
	}
}
