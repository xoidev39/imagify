<?php
/**
 * WWC Watermark Library
 *
 * Provides the WWC_Watermark class to apply a proportionally scaled watermark
 * on a given image. The watermark image is resized based on the main image's width
 * and merged while preserving transparency.
 *
 * @package WWC_Image_Library
 * @version 1.2.1
 * @author Alex Watson
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WWC_Watermark' ) ) {

	class WWC_Watermark {

		/**
		 * Absolute path to the watermark image.
		 *
		 * @var string
		 */
		protected $watermark_image;

		/**
		 * Watermark position: 'top-left', 'top-right', 'bottom-left', 'bottom-right', or 'center'.
		 *
		 * @var string
		 */
		protected $position;

		/**
		 * Opacity (0-100, where 100 is fully opaque).
		 *
		 * @var int
		 */
		protected $opacity;

		/**
		 * Scale percentage relative to the main image's width.
		 *
		 * @var int
		 */
		protected $scale;

		/**
		 * Margin offset.
		 *
		 * @var int
		 */
		protected $margin;

		/**
		 * Margin unit, either 'px' or '%'.
		 *
		 * @var string
		 */
		protected $margin_unit;

		/**
		 * Constructor.
		 *
		 * @param string $watermark_image Absolute path to the watermark image.
		 * @param string $position        Watermark position (default: 'bottom-right').
		 * @param int    $opacity         Opacity (default: 80).
		 * @param int    $scale           Scale percentage (default: 20).
		 * @param int    $margin          Margin value (default: 10).
		 * @param string $margin_unit     Margin unit ('px' or '%', default: 'px').
		 */
		public function __construct( $watermark_image, $position = 'bottom-right', $opacity = 80, $scale = 20, $margin = 10, $margin_unit = 'px' ) {
			$this->watermark_image = $watermark_image;
			$this->position        = $position;
			$this->opacity         = intval( $opacity );
			$this->scale           = intval( $scale );
			$this->margin          = intval( $margin );
			$this->margin_unit     = $margin_unit;
		}

		/**
		 * Applies the watermark to the given source image.
		 *
		 * The watermark image is resized relative to the source width and merged
		 * preserving transparency.
		 *
		 * @param string $source_file Absolute path to the source image.
		 * @return mixed Path to the temporary watermarked image or false on failure.
		 */
		public function apply( $source_file ) {
			if ( ! file_exists( $source_file ) ) {
				return false;
			}

			// Load source image based on its MIME type.
			$mime = mime_content_type( $source_file );
			switch ( strtolower( $mime ) ) {
				case 'image/jpeg':
				case 'image/jpg':
					$source_img = imagecreatefromjpeg( $source_file );
					break;
				case 'image/png':
					$source_img = imagecreatefrompng( $source_file );
					break;
				case 'image/bmp':
					$source_img = function_exists('imagecreatefrombmp') ? imagecreatefrombmp( $source_file ) : false;
					break;
				case 'image/gif':
					$source_img = imagecreatefromgif( $source_file );
					break;
				default:
					return false;
			}
			if ( ! $source_img ) {
				return false;
			}

			$src_width  = (int) imagesx( $source_img );
			$src_height = (int) imagesy( $source_img );

			// Load watermark image.
			if ( ! file_exists( $this->watermark_image ) ) {
				imagedestroy( $source_img );
				return false;
			}
			$wm_info = getimagesize( $this->watermark_image );
			$wm_mime = isset( $wm_info['mime'] ) ? $wm_info['mime'] : '';
			switch ( strtolower( $wm_mime ) ) {
				case 'image/png':
					$wm_img_orig = imagecreatefrompng( $this->watermark_image );
					break;
				case 'image/jpeg':
				case 'image/jpg':
					$wm_img_orig = imagecreatefromjpeg( $this->watermark_image );
					break;
				case 'image/bmp':
					$wm_img_orig = function_exists('imagecreatefrombmp') ? imagecreatefrombmp( $this->watermark_image ) : false;
					break;
				case 'image/gif':
					$wm_img_orig = imagecreatefromgif( $this->watermark_image );
					break;
				case 'image/webp':
					$wm_img_orig = function_exists('imagecreatefromwebp') ? imagecreatefromwebp( $this->watermark_image ) : false;
					break;
				default:
					$wm_img_orig = false;
					break;
			}
			if ( ! $wm_img_orig ) {
				imagedestroy( $source_img );
				return false;
			}
			$wm_orig_width  = (int) imagesx( $wm_img_orig );
			$wm_orig_height = (int) imagesy( $wm_img_orig );

			// Calculate desired watermark dimensions.
			$desired_wm_width  = (int) round( $src_width * ( $this->scale / 100 ) );
			$scale_factor      = $desired_wm_width / $wm_orig_width;
			$desired_wm_height = (int) round( $wm_orig_height * $scale_factor );

			// Create a resized watermark resource.
			$wm_resized = imagecreatetruecolor( $desired_wm_width, $desired_wm_height );
			imagealphablending( $wm_resized, false );
			imagesavealpha( $wm_resized, true );
			$transparent = imagecolorallocatealpha( $wm_resized, 0, 0, 0, 127 );
			imagefill( $wm_resized, 0, 0, $transparent );
			imagecopyresampled( 
				$wm_resized, 
				$wm_img_orig, 
				0, 0, 0, 0, 
				$desired_wm_width, 
				$desired_wm_height, 
				$wm_orig_width, 
				$wm_orig_height 
			);

			// Adjust watermark opacity if less than 100.
			if ( $this->opacity < 100 ) {
				$wm_resized = self::applyOpacityToImage( $wm_resized, $this->opacity );
			}

			// Calculate margin in pixels.
			if ( $this->margin_unit === '%' ) {
				$margin_px = (int) round( min( $src_width, $src_height ) * ( $this->margin / 100 ) );
			} else {
				$margin_px = $this->margin;
			}

			// Determine destination coordinates.
			switch ( $this->position ) {
				case 'top-left':
					$dest_x = $margin_px;
					$dest_y = $margin_px;
					break;
				case 'top-right':
					$dest_x = (int) round( $src_width - $desired_wm_width - $margin_px );
					$dest_y = $margin_px;
					break;
				case 'bottom-left':
					$dest_x = $margin_px;
					$dest_y = (int) round( $src_height - $desired_wm_height - $margin_px );
					break;
				case 'center':
					$dest_x = (int) round( ( $src_width - $desired_wm_width ) / 2 );
					$dest_y = (int) round( ( $src_height - $desired_wm_height ) / 2 );
					break;
				case 'bottom-right':
				default:
					$dest_x = (int) round( $src_width - $desired_wm_width - $margin_px );
					$dest_y = (int) round( $src_height - $desired_wm_height - $margin_px );
					break;
			}

			// Create output image.
			$output_img = imagecreatetruecolor( $src_width, $src_height );
			if ( strtolower( $mime ) === 'image/png' ) {
				imagealphablending( $output_img, false );
				imagesavealpha( $output_img, true );
				$transparent_bg = imagecolorallocatealpha( $output_img, 0, 0, 0, 127 );
				imagefill( $output_img, 0, 0, $transparent_bg );
			}
			imagecopy( $output_img, $source_img, 0, 0, 0, 0, $src_width, $src_height );

			// Merge watermark onto the output image.
			imagecopy( $output_img, $wm_resized, $dest_x, $dest_y, 0, 0, $desired_wm_width, $desired_wm_height );

			// Save combined image to a temporary file.
			$temp_file = tempnam( sys_get_temp_dir(), 'wwc_' );
			if ( strtolower( $mime ) === 'image/jpeg' ) {
				imagejpeg( $output_img, $temp_file, 100 );
			} elseif ( strtolower( $mime ) === 'image/png' ) {
				imagepng( $output_img, $temp_file );
			} elseif ( strtolower( $mime ) === 'image/gif' ) {
				imagegif( $output_img, $temp_file );
			} else {
				imagedestroy( $output_img );
				return false;
			}

			// Clean up.
			imagedestroy( $source_img );
			imagedestroy( $wm_img_orig );
			imagedestroy( $wm_resized );
			imagedestroy( $output_img );

			return $temp_file;
		}

		/**
		 * Adjusts the opacity of an image resource.
		 *
		 * Iterates through each pixel and adjusts its alpha channel while preserving color.
		 *
		 * @param resource $img     The source image resource.
		 * @param int      $opacity The desired opacity (0-100).
		 * @return resource         New image resource with adjusted opacity.
		 */
		protected static function applyOpacityToImage( $img, $opacity ) {
			$w = imagesx( $img );
			$h = imagesy( $img );
			$new_img = imagecreatetruecolor( $w, $h );
			imagealphablending( $new_img, false );
			imagesavealpha( $new_img, true );
			$transparent = imagecolorallocatealpha( $new_img, 0, 0, 0, 127 );
			imagefill( $new_img, 0, 0, $transparent );

			for ( $x = 0; $x < $w; $x++ ) {
				for ( $y = 0; $y < $h; $y++ ) {
					$rgba = imagecolorat( $img, $x, $y );
					$colors = imagecolorsforindex( $img, $rgba );
					$alpha = $colors['alpha'];
					$new_alpha = 127 - ((127 - $alpha) * ($opacity / 100));
					$new_color = imagecolorallocatealpha( $new_img, $colors['red'], $colors['green'], $colors['blue'], (int) round( $new_alpha ) );
					imagesetpixel( $new_img, $x, $y, $new_color );
				}
			}
			return $new_img;
		}
	}
}
