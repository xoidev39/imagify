<?php
/**
 * WWC Image Converter Library
 *
 * Provides the WWC_ImageConverter class for converting images to WebP format.
 * The class selects the appropriate cwebp binary based on the operating system
 * and utilizes additional optimizer binaries (jpegtran, optipng, gifsicle, etc.)
 * from the "bin" folder to optimize the uploaded file in-place (overwrite the file)
 * prior to conversion. If the PHP function exec() is not available, it will
 * fall back to PHP's GD functions.
 *
 * @package WWC_Image_Library
 * @version 1.2.1
 * @author Alex Watson
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WWC_ImageConverter' ) ) {

	class WWC_ImageConverter {

		/**
		 * Absolute path to the selected cwebp binary.
		 *
		 * @var string
		 */
		protected $cwebp_cmd;

		/**
		 * Quality setting for WebP conversion (0-100).
		 *
		 * @var int
		 */
		protected $webp_quality;

		/**
		 * Constructor.
		 *
		 * If no binary path is given, auto-detect the correct cwebp binary based on OS.
		 *
		 * @param string $cwebp_cmd    Optional absolute path to the cwebp binary.
		 * @param int    $webp_quality Quality for conversion (default: 80).
		 */
		public function __construct( $cwebp_cmd = '', $webp_quality = 80 ) {
			$this->webp_quality = intval( $webp_quality );
			if ( empty( $cwebp_cmd ) ) {
				$os = PHP_OS;
				if ( stripos( $os, 'WIN' ) !== false ) {
					$bin = 'cwebp.exe';
				} elseif ( stripos( $os, 'Darwin' ) !== false ) {
					$bin = 'cwebp-mac15';
				} elseif ( stripos( $os, 'FreeBSD' ) !== false ) {
					$bin = 'cwebp-fbsd';
				} else {
					$bin = 'cwebp-linux';
				}
				// Giả sử folder "bin" nằm 1 cấp trên file thư viện này.
				$this->cwebp_cmd = __DIR__ . '/../bin/' . $bin;
			} else {
				$this->cwebp_cmd = $cwebp_cmd;
			}
		}

		/**
		 * Converts a given image file to WebP format.
		 *
		 * Optionally applies a watermark first (if a valid watermark object is provided),
		 * then performs additional optimization using extra binaries, overwriting the file
		 * in-place before converting to WebP.
		 *
		 * This method first attempts to use exec() with the bundled binaries. If exec() is
		 * not available, it falls back on PHP's GD imagewebp() function.
		 *
		 * @param string      $source_file Absolute path to the source image.
		 * @param object|null $watermark   Optional watermark object (must implement an apply() method).
		 * @return bool True on success; false otherwise.
		 */
		public function convert( $source_file, $watermark = null, $overwrite = false ) {
			if ( ! file_exists( $source_file ) ) {
				return false;
			}
		
			$temp_file = $source_file;
		
			// Apply watermark nếu có.
			if ( is_object( $watermark ) && method_exists( $watermark, 'apply' ) ) {
				$watermarked = $watermark->apply( $source_file );
				if ( $watermarked && file_exists( $watermarked ) ) {
					$temp_file = $watermarked;
				}
			}
		
			// Optimize the image in-place.
			$temp_file = $this->optimize_image( $temp_file );
		
			// Build output file:
			if ( $overwrite ) {
				$output_file = $source_file;
			} else {
				$output_file = $source_file . '.webp';
			}
		
			// Attempt conversion using exec() with the bundled cwebp binary.
			if ( function_exists( 'exec' ) && is_callable( 'exec' ) ) {
				$input_escaped  = escapeshellarg( $temp_file );
				$output_escaped = escapeshellarg( $output_file );
				$quality_arg    = escapeshellarg( $this->webp_quality );
				$command        = "{$this->cwebp_cmd} -q {$quality_arg} {$input_escaped} -o {$output_escaped}";
				@exec( $command, $output, $return_var );
			}
			// Fallback: use GD's imagewebp() if available.
			elseif ( function_exists( 'imagewebp' ) ) {
				$mime = mime_content_type( $temp_file );
				switch ( strtolower( $mime ) ) {
					case 'image/jpeg':
					case 'image/jpg':
						$image = imagecreatefromjpeg( $temp_file );
						break;
					case 'image/png':
						$image = imagecreatefrompng( $temp_file );
						break;
					case 'image/gif':
						$image = imagecreatefromgif( $temp_file );
					case 'image/webp':
						$image = imagecreatefromwebp( $temp_file );
						break;
					default:
						$image = false;
				}
				if ( $image ) {
					imagewebp( $image, $output_file, $this->webp_quality );
					imagedestroy( $image );
				}
			} else {
				return false;
			}
		
			// Clean up temporary file nếu có (nếu watermark đã tạo ra file tạm).
			if ( $temp_file !== $source_file && file_exists( $temp_file ) ) {
				@unlink( $temp_file );
			}
		
			return file_exists( $output_file );
		}
		

		/**
		 * Optimize an image in-place using additional optimizer binaries.
		 *
		 * This function calls the appropriate binary based on the image mime type.
		 * Instead of creating a new file (.opt), it overwrites the original file.
		 *
		 * @param string $source_file Absolute path to the source image.
		 * @return string The path to the optimized file (same as source) if optimized; otherwise, original.
		 */
		protected function optimize_image( $source_file ) {
			if ( ! function_exists( 'exec' ) || ! is_callable( 'exec' ) ) {
				return $source_file;
			}

			$mime = mime_content_type( $source_file );
			$temp_file = $source_file . '.tmp';

			$optimized = false;
			switch ( $mime ) {
				case 'image/jpeg':
				case 'image/jpg':
					$binary = $this->get_binary_path( 'jpegtran' );
					if ( $binary ) {
						$command = escapeshellarg( $binary ) . ' -copy none -optimize -outfile ' . escapeshellarg( $temp_file ) . ' ' . escapeshellarg( $source_file );
						@exec( $command, $output, $return );
						$optimized = file_exists( $temp_file );
					}
					break;
				case 'image/png':
					$binary = $this->get_binary_path( 'optipng' );
					if ( $binary ) {
						$command = escapeshellarg( $binary ) . ' -o7 -out ' . escapeshellarg( $temp_file ) . ' ' . escapeshellarg( $source_file );
						@exec( $command, $output, $return );
						$optimized = file_exists( $temp_file );
					}
					break;
				case 'image/gif':
					$binary = $this->get_binary_path( 'gifsicle' );
					if ( $binary ) {
						$command = escapeshellarg( $binary ) . ' -O3 ' . escapeshellarg( $source_file ) . ' -o ' . escapeshellarg( $temp_file );
						@exec( $command, $output, $return );
						$optimized = file_exists( $temp_file );
					}
					break;
				default:
					// Các loại file khác không tối ưu.
					break;
			}

			// Nếu tối ưu thành công, ghi đè file gốc.
			if ( $optimized ) {
				// Overwrite original file.
				@rename( $temp_file, $source_file );
				return $source_file;
			} else {
				// Xóa file tạm nếu có.
				if ( file_exists( $temp_file ) ) {
					@unlink( $temp_file );
				}
			}
			return $source_file;
		}

		/**
		 * Get the full path for a given binary from the same bin folder as cwebp.
		 *
		 * @param string $binary_name The base name (e.g. 'jpegtran', 'optipng', 'gifsicle').
		 * @return mixed Full path to the binary if exists and executable; false otherwise.
		 */
		protected function get_binary_path( $binary_name ) {
			$base_dir = dirname( $this->cwebp_cmd );
			$os = PHP_OS;
			if ( stripos( $os, 'WIN' ) !== false ) {
				$file = $binary_name . '.exe';
			} elseif ( stripos( $os, 'Darwin' ) !== false ) {
				if ( file_exists( $base_dir . '/' . $binary_name . '-mac15' ) ) {
					$file = $binary_name . '-mac15';
				} else {
					$file = $binary_name;
				}
			} else {
				if ( file_exists( $base_dir . '/' . $binary_name . '-linux' ) ) {
					$file = $binary_name . '-linux';
				} else {
					$file = $binary_name;
				}
			}
			$path = $base_dir . '/' . $file;
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
			return false;
		}
	}
}
