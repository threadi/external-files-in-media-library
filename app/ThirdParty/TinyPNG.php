<?php
/**
 * File to handle support for the plugin "TinyPNG – JPEG, PNG & WebP image compression".
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
 * It compresses the local files of uploads after the upload, so their export waits for it. Its variants in other
 * formats are named like "image.webp" and "image.avif".
 */
class TinyPNG extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?TinyPNG
	 */
	private static ?TinyPNG $instance = null;

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
	 * @return TinyPNG
	 */
	public static function get_instance(): TinyPNG {
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
	protected function get_plugin_files(): array {
		return array( 'tiny-compress-images/tiny-compress-images.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		parent::add_hooks();

		// export a waiting upload as soon as its compression has ended.
		add_action( 'tiny_image_after_compression', array( $this, 'end' ) );
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * TinyPNG replaces the extension (e.g. "image.webp" beside "image.jpg").
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
		return array(
			$base . '.avif' => 'image/avif',
			$base . '.webp' => 'image/webp',
		);
	}

	/**
	 * Mark the end of the compression of an upload, so its export could run.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end( mixed $attachment_id ): void {
		if ( is_numeric( $attachment_id ) && (int) $attachment_id > 0 ) {
			Delayed_Uploads::get_instance()->done( (int) $attachment_id );
		}
	}

}
