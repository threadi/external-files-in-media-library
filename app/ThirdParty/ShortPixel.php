<?php
/**
 * File to handle support for the plugin "ShortPixel Image Optimizer".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads;

/**
 * Object to handle support for this plugin.
 *
 * It optimizes the local files of uploads after the upload via its API, so their export waits until it reports the
 * end of the optimization. Its variants in other formats are named like "image.webp" or "image.jpg.webp".
 */
class ShortPixel extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ShortPixel
	 */
	private static ?ShortPixel $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return ShortPixel
	 */
	public static function get_instance(): ShortPixel {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the main files of the plugin, relative to the plugin directory.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'shortpixel-image-optimiser/wp-shortpixel.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		parent::add_hooks();

		// wait for the end of the optimization of new uploads via the API of ShortPixel.
		add_action( 'shortpixel_start_image_optimisation', array( $this, 'start' ) );
		add_action( 'shortpixel_image_optimised', array( $this, 'end' ) );

		// wait during conversions (e.g. PNG to JPEG), for which ShortPixel asks offload plugins to wait.
		add_action( 'shortpixel/converter/prevent-offload', array( $this, 'start' ) );
		add_action( 'shortpixel/converter/prevent-offload-off', array( $this, 'end_conversion' ) );

		// finish a working copy after ShortPixel restored the image from its backup.
		add_action( 'shortpixel_after_restore_image', array( $this, 'end' ) );
	}

	/**
	 * Return the possible local backup of the given file, which ShortPixel saves in its backup directory (path =>
	 * name on the external source).
	 *
	 * The directory is given by ShortPixel itself (e.g. "wp-content/uploads/ShortpixelBackups/wp-content/uploads/…").
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		// bail if the file system of ShortPixel is not available.
		if ( ! function_exists( 'wpSPIO' ) ) {
			return array();
		}

		// get the backup directory of this file from ShortPixel.
		try {
			$filesystem = wpSPIO()->filesystem();
			if ( ! is_object( $filesystem ) || ! method_exists( $filesystem, 'getFile' ) || ! method_exists( $filesystem, 'getBackupDirectory' ) ) {
				return array();
			}
			$directory = $filesystem->getBackupDirectory( $filesystem->getFile( $path ) );
		} catch ( \Throwable $e ) {
			return array();
		}

		// bail if it does not exist.
		if ( ! is_object( $directory ) || ! method_exists( $directory, 'getPath' ) ) {
			return array();
		}

		// return the path of the backup.
		return array(
			trailingslashit( (string) $directory->getPath() ) . wp_basename( $path ) => $this->get_backup_name( $path, 'shortpixel-backup' ),
		);
	}

	/**
	 * Mark the end of a conversion (e.g. PNG to JPEG) of an upload, so its export could run.
	 *
	 * The optimization itself could still run, so a working copy is not finished now.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end_conversion( mixed $attachment_id ): void {
		if ( is_numeric( $attachment_id ) && absint( $attachment_id ) > 0 ) {
			Delayed_Uploads::get_instance()->done( (int) $attachment_id );
		}
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * ShortPixel replaces the extension (e.g. "image.webp"), or adds it if such a file exists already.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_variant_paths( string $path ): array {
		// get the path without the extension.
		$info = pathinfo( $path );
		$base = trailingslashit( $info['dirname'] ?? '' ) . $info['filename'];

		// return the possible paths.
		return array_merge(
			parent::get_variant_paths( $path ),
			array(
				$base . '.avif' => 'image/avif',
				$base . '.webp' => 'image/webp',
			)
		);
	}

	/**
	 * Mark an upload as busy, as ShortPixel will optimize or convert it.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function start( mixed $attachment_id ): void {
		if ( is_numeric( $attachment_id ) && absint( $attachment_id ) > 0 ) {
			Delayed_Uploads::get_instance()->set_busy( (int) $attachment_id );
		}
	}

	/**
	 * Mark the end of the optimization or conversion of an upload, so its export could run.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end( mixed $attachment_id ): void {
		if ( is_numeric( $attachment_id ) ) {
			$this->done( absint( $attachment_id ) );
		}
	}
}
