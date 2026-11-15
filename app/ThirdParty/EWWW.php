<?php
/**
 * File to handle support for the plugin "EWWW Image Optimizer".
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
 * It optimizes the local files of uploads after the upload, so their export waits for it. Its variants in other
 * formats are named like "image.jpg.webp" or "image.webp".
 */
class EWWW extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?EWWW
	 */
	private static ?EWWW $instance = null;

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
	 * @return EWWW
	 */
	public static function get_instance(): EWWW {
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
		return array( 'ewww-image-optimizer/ewww-image-optimizer.php', 'ewww-image-optimizer-cloud/ewww-image-optimizer-cloud.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		parent::add_hooks();

		// export a waiting upload as soon as its optimization has ended.
		add_action( 'ewww_image_optimizer_after_optimize_attachment', array( $this, 'end' ) );
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * EWWW adds the extension (e.g. "image.jpg.webp") or replaces it (e.g. "image.webp"), depending on its settings.
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
				$base . '.webp' => 'image/webp',
			)
		);
	}

	/**
	 * Mark the end of the optimization of an upload, so its export could run.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end( mixed $attachment_id ): void {
		if ( is_numeric( $attachment_id ) && absint( $attachment_id ) > 0 ) {
			Delayed_Uploads::get_instance()->done( (int) $attachment_id );
		}
	}

}
