<?php
/**
 * File to handle support for the plugin "TinyPNG – JPEG, PNG & WebP image compression".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;


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
	public function get_plugin_files(): array {
		return array( 'tiny-compress-images/tiny-compress-images.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		parent::add_hooks();

		// export a waiting upload or a working copy as soon as its compression has ended.
		add_action( 'tiny_image_after_compression', array( $this, 'end' ) );

		// provide the working copy of an exported image, if it is compressed via the media library.
		add_action( 'wp_ajax_tiny_compress_image_from_library', array( $this, 'start_manual' ), 0 );
	}

	/**
	 * Provide the working copy of an exported image, before TinyPNG compresses it by request in the media library. It
	 * is finished at the end of the request.
	 *
	 * @return void
	 */
	public function start_manual(): void {
		// bail if the request is not valid (the nonce of TinyPNG is used).
		if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_nonce'] ) ), 'tiny-compress' ) ) {
			return;
		}

		// get the attachment ID.
		$attachment_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;

		// provide the working copy.
		$this->provide_working_copy( $attachment_id );
	}

	/**
	 * Return the possible backup of the given file, which TinyPNG saves in "wp-content/uploads/tinify_backup/" (path
	 * => name on the external source).
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		// get the uploads directory.
		$upload_dir = trailingslashit( wp_normalize_path( wp_get_upload_dir()['basedir'] ) );
		$path       = wp_normalize_path( $path );

		// bail if the file is not in the uploads directory.
		if ( ! str_starts_with( $path, $upload_dir ) ) {
			return array();
		}

		// return the path of the backup.
		return array(
			$upload_dir . 'tinify_backup/' . substr( $path, strlen( $upload_dir ) ) => $this->get_backup_name( $path, 'tinify-backup' ),
		);
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
		if ( is_numeric( $attachment_id ) ) {
			$this->done( absint( $attachment_id ) );
		}
	}
}
