<?php
/**
 * File to handle support for the plugin "EWWW Image Optimizer".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies;

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

		// export a waiting upload or a working copy as soon as its optimization has ended.
		add_action( 'ewww_image_optimizer_after_optimize_attachment', array( $this, 'end' ) );

		// provide the working copy of an exported image, if it is optimized or restored via the media library.
		add_action( 'admin_action_ewww_image_optimizer_manual_optimize', array( $this, 'start_manual' ), 0 );
		add_action( 'admin_action_ewww_image_optimizer_manual_restore', array( $this, 'start_manual' ), 0 );
		add_action( 'admin_action_ewww_image_optimizer_manual_image_restore', array( $this, 'start_manual' ), 0 );
		add_action( 'wp_ajax_ewww_manual_optimize', array( $this, 'start_manual' ), 0 );
		add_action( 'wp_ajax_ewww_manual_restore', array( $this, 'start_manual' ), 0 );
		add_action( 'wp_ajax_ewww_manual_image_restore', array( $this, 'start_manual' ), 0 );

		// show the actions of EWWW for exported images in the media library.
		add_filter( 'ewww_image_optimizer_is_remote_file', array( $this, 'is_remote_file' ), 10, 3 );
	}

	/**
	 * Mark exported images without local files as offloaded files for the column of EWWW in the media library, so it
	 * shows its actions (e.g. "Optimize now!") for them. Their working copy is provided as soon as one of them is used.
	 *
	 * Only used for the column, not for the bulk optimization of EWWW: it needs the working copies before (settings →
	 * "Export" → "Prepare exported images for image optimizers"), as it reads the sizes from the metadata.
	 *
	 * @param mixed $is_remote     Whether the file is an offloaded file.
	 * @param mixed $file_path     The path of the file (empty, if it does not exist locally).
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function is_remote_file( mixed $is_remote, mixed $file_path, mixed $attachment_id ): mixed {
		// bail if it is marked as offloaded file already or if no ID is given.
		if ( true === $is_remote || ! is_numeric( $attachment_id ) ) {
			return $is_remote;
		}

		// bail if the column is not shown (in the list view or for the status of an image).
		if ( ! doing_action( 'manage_media_custom_column' ) && ! doing_action( 'wp_ajax_ewww_manual_get_status' ) ) {
			return $is_remote;
		}

		// bail if the image has a working copy (its local files are used) or could not get one.
		$working_copies = Working_Copies::get_instance();
		if ( $working_copies->is_active( absint( $attachment_id ) ) || ! $working_copies->is_possible( absint( $attachment_id ) ) ) {
			return $is_remote;
		}

		// it is an offloaded file.
		return true;
	}

	/**
	 * Provide the working copy of an exported image, before EWWW optimizes or restores it by request in the media
	 * library. It is finished at the end of the request.
	 *
	 * @return void
	 */
	public function start_manual(): void {
		// bail if the request is not valid (the nonce of EWWW is used).
		if ( ! isset( $_REQUEST['ewww_manual_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['ewww_manual_nonce'] ) ), 'ewww-manual' ) ) {
			return;
		}

		// get the attachment ID.
		$attachment_id = isset( $_REQUEST['ewww_attachment_ID'] ) ? absint( wp_unslash( $_REQUEST['ewww_attachment_ID'] ) ) : 0;

		// get whether EWWW optimizes the image in the background: it only adds it to its queue during this request, so
		// the working copy is finished, as soon as EWWW reports the end of its work.
		$action     = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$background = in_array( $action, array( 'ewww_image_optimizer_manual_optimize', 'ewww_manual_optimize' ), true ) && $this->is_background_optimization();

		// provide the working copy.
		$this->provide_working_copy( $attachment_id, ! $background );
	}

	/**
	 * Return whether EWWW optimizes images in the background (like it decides it for a manual optimization).
	 *
	 * @return bool
	 */
	private function is_background_optimization(): bool {
		// bail if the function of EWWW is not available.
		if ( ! function_exists( 'ewww_image_optimizer_background_mode_enabled' ) ) {
			return false;
		}

		// bail if the background mode is disabled.
		if ( ! ewww_image_optimizer_background_mode_enabled() ) {
			return false;
		}

		// return the setting of EWWW for manual optimizations (they are deferred by EWWW).
		return (bool) apply_filters( 'ewww_image_optimizer_background_optimization', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Return the possible local backup of the given file, which EWWW saves in its backup directory (e.g.
	 * "wp-content/ewww/image-backup/uploads/"), as path => name on the external source.
	 *
	 * The path is given by EWWW itself, so its settings (e.g. another directory) are used.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		global $eio_backup;

		// bail if the backup object of EWWW is not available.
		if ( ! is_object( $eio_backup ) || ! method_exists( $eio_backup, 'get_backup_location' ) ) {
			return array();
		}

		// get the path of the backup (empty, if EWWW saves no local backups).
		$backup_path = $eio_backup->get_backup_location( $path );

		// bail if it is not available.
		if ( ! is_string( $backup_path ) || '' === $backup_path || $backup_path === $path ) {
			return array();
		}

		// return the path of the backup.
		return array(
			$backup_path => $this->get_backup_name( $path, 'ewww-backup' ),
		);
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
		if ( is_numeric( $attachment_id ) ) {
			$this->done( absint( $attachment_id ) );
		}
	}

}
