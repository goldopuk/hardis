<?php

/**
 * Transform operations on image.
 *
 * @author Fabiano Simões <fabiano@stayfilm.com>
 */
class ImageTransform
{
	/**
	 * Resize Image
	 *
	 * Takes the source image and resizes it to the specified width & height or proportionally if crop is off.
	 * @access public
	 * @author Jay Zawrotny <jayzawrotny@gmail.com>
	 * @license Do whatever you want with it.
	 * @param string $source_image The location to the original raw image.
	 * @param string $destination_filename The location to save the new image.
	 * @param int $newWidth The desired width of the new image
	 * @param int $newHeight The desired height of the new image.
	 * @param int $quality The quality of the JPG to produce 1 - 100
	 * @param bool $crop Whether to crop the image or not. It always crops from the center.
	 */
	// @codingStandardsIgnoreStart
	public function ImageResize($source_image, $destination_filename, $newWidth = 1280, $newHeight = 720, $quality = 70, $crop = true, $respectRatio = true)
	{
	// @codingStandardsIgnoreEnd
		if ( ! $newWidth)
		{
			throw new \Exception('missing newwidth');
		}

		if ( ! $newHeight)
		{
			throw new \Exception('missing newHeight');
		}

		$arr = getimagesize($source_image);

		if ( ! isset($arr[0]) || ! isset($arr[1]) || ! isset($arr['mime']))
		{
			throw new \Exception("Error getting the image size $source_image OR mime type");
		}

		$origWidth  = $arr[0];
		$origHeight = $arr[1];
		$mime       = $arr['mime'];

		if ($respectRatio)
		{
			// landscape
			if ($origWidth > $origHeight)
			{
				if ($newWidth > $origWidth)
				{
					$newWidth = $origWidth;
					$newHeight = $origHeight;
				}
				else
				{
					$newHeight = (integer)($origHeight * $newWidth / $origWidth);
				}
			}
			else //portrait
			{
				if ($newHeight > $origHeight)
				{
					$newWidth = $origWidth;
					$newHeight = $origHeight;
				}
				else
				{
					$newWidth = (integer)($origWidth * $newHeight / $origHeight);
				}

			}
		} // else respectRation is FALSE and no need to change the value of newWidth and newheight

		switch ($mime)
		{
			case 'image/gif':
					$get_func = 'imagecreatefromgif';
					$suffix = ".gif";
			break;
			case 'image/jpeg';
					$get_func = 'imagecreatefromjpeg';
					$suffix = ".jpg";
			break;
			case 'image/png':
					$get_func = 'imagecreatefrompng';
					$suffix = ".png";
			break;
		}

		$img_original = call_user_func($get_func, $source_image);
		$src_x = 0;
		$src_y = 0;

		$current_ratio        = round($origWidth / $origHeight, 2);
		$desired_ratio_after  = round( $newWidth / $newHeight, 2 );
		$desired_ratio_before = round( $newHeight / $newWidth, 2 );


		/**
		 * If the crop option is left on, it will take an image and best fit it
		 * so it will always come out the exact specified size.
		 */
		if ($crop)
		{
			/**
			 * create empty image of the specified size
			 */
			$new_image = imagecreatetruecolor($newWidth, $newHeight);

			/**
			 * Landscape Image
			 */
			if ($current_ratio > $desired_ratio_after)
			{
				$newWidth = $origWidth * $newHeight / $origHeight;
			}

			/**
			 * Nearly square ratio image.
			 */
			if ($current_ratio > $desired_ratio_before && $current_ratio < $desired_ratio_after )
			{
				if( $origWidth > $origHeight )
				{
						$newHeight = max( $newWidth, $newHeight );
						$newWidth = $origWidth * $newHeight / $origHeight;
				}
				else
				{
						$newHeight = $origHeight * $newWidth / $origWidth;
				}
			}

			/**
			 * Portrait sized image
			 */
			if( $current_ratio < $desired_ratio_before  )
			{
					$newHeight = $origHeight * $newWidth / $origWidth;
			}

			/**
			 * Find out the ratio of the original photo to it's new, thumbnail-based size
			 * for both the width and the height. It's used to find out where to crop.
			 */
			$width_ratio = $origWidth / $newWidth;
			$height_ratio = $origHeight / $newHeight;

			/**
			 * Calculate where to crop based on the center of the image
			 */
			$src_x = floor( ( ( $newWidth - $newWidth ) / 2 ) * $width_ratio );
			$src_y = round( ( ( $newHeight - $newHeight ) / 2 ) * $height_ratio );
		}
		/**
		 * Don't crop the image, just resize it proportionally
		 */
		else
		{
			$new_image = imagecreatetruecolor( $newWidth, $newHeight );
		}

		/**
		 * Where all the real magic happens
		 */
		if ( ! imagecopyresampled( $new_image, $img_original, 0, 0, $src_x, $src_y, $newWidth, $newHeight, $origWidth, $origHeight ))
		{
			throw new \Exception("imagecopyresampled() failed ");
		}

		/**
		 * Save it as a JPG File with our $destination_filename param.
		 */
		if ( ! imagejpeg( $new_image, $destination_filename, $quality  ) )
		{
			throw new \Exception("imagejpeg() failed ");
		}

		/**
		 * Destroy the evidence!
		 */
		if ( !  imagedestroy( $new_image))
		{
			throw new \Exception("imagejpeg() failed ");
		}

		if ( ! imagedestroy( $img_original ))
		{
			throw new \Exception("imagejpeg() failed ");
		}


		/**
		 * Return true because it worked and we're happy. Let the dancing commence!
		 */
		return true;
	}
}
