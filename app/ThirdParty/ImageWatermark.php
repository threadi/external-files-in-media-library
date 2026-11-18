<?php
/**
 * File to handle support for the plugin "Image Watermark".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Restore;
use ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle support for this plugin.
 *
 * The plugin adds a watermark to the files of images: during the upload, before they are exported, and later by
 * request of the user (single images and bulk action in the media library). For exported images, a working copy is
 * saved before (see ExternalFiles\Working_Copies), the changed files are exported at the end of the request. The
 * backups of the original images, which the plugin saves in its own directory, are exported as backups beside the
 * image and restored with the working copy, so the watermark could be removed later.
 *
 * The plugin reads the image of the watermark only from the uploads directory. This image is therefore not exported,
 * and restored as local file, if it has been exported before (e.g. directly after its upload in the settings).
 */
class ImageWatermark extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ImageWatermark
	 */
	private static ?ImageWatermark $instance = null;

	/**
	 * The actions of the plugin.
	 */
	private const ACTIONS = array( 'applywatermark', 'removewatermark' );

	/**
	 * The number of images, which the plugin handles in one request of its bulk action.
	 */
	private const BULK_BATCH_SIZE = 20;

	/**
	 * Marker for a request of the plugin, which changes images.
	 *
	 * @var bool
	 */
	private bool $running = false;

	/**
	 * The name of the option of the plugin.
	 */
	private const OPTION = 'image_watermark_options';

	/**
	 * List of attachment IDs, whose prevented export has been logged in this request.
	 *
	 * @var array<int,bool>
	 */
	private array $logged = array();

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
	 * @return ImageWatermark
	 */
	public static function get_instance(): ImageWatermark {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the plugin files of the supported plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'image-watermark/image-watermark.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		// bail if they are added already.
		if ( false !== has_action( 'iw_after_apply_watermark', array( $this, 'end' ) ) ) {
			return;
		}

		parent::add_hooks();

		// provide working copies before the plugin changes exported images by request of the user.
		add_action( 'wp_ajax_iw_watermark_bulk_action', array( $this, 'start_single' ), 0 );
		add_action( 'load-upload.php', array( $this, 'start_bulk' ), 0 );

		// finish the working copy after the watermark has been added.
		add_action( 'iw_after_apply_watermark', array( $this, 'end' ) );

		// show the local file of images with working copy to the plugin.
		add_filter( 'get_post_metadata', array( $this, 'get_local_attached_file' ), 10, 4 );

		// keep the image of the watermark local, as the plugin reads it only from the uploads directory.
		add_filter( 'efml_prevent_export', array( $this, 'prevent_export_of_watermark_image' ), 10, 2 );
		add_action( 'add_option_' . self::OPTION, array( $this, 'restore_watermark_image' ) );
		add_action( 'update_option_' . self::OPTION, array( $this, 'restore_watermark_image' ) );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'restore_watermark_image_before_upload' ) );
	}

	/**
	 * Return the attachment ID of the image, which the plugin uses as watermark, or 0, if it uses a text.
	 *
	 * @return int
	 */
	public function get_watermark_image_id(): int {
		// get the settings of the plugin.
		$options = get_option( self::OPTION );

		// bail if they are not valid.
		if ( ! is_array( $options ) || ! isset( $options['watermark_image'] ) || ! is_array( $options['watermark_image'] ) ) {
			return 0;
		}

		// bail if the watermark is a text (the plugin uses an image by default).
		if ( isset( $options['watermark_image']['type'] ) && 'image' !== $options['watermark_image']['type'] ) {
			return 0;
		}

		// return the ID of the image.
		return isset( $options['watermark_image']['url'] ) ? absint( $options['watermark_image']['url'] ) : 0;
	}

	/**
	 * Prevent the export of the image, which the plugin uses as watermark.
	 *
	 * @param bool $result            The result.
	 * @param File $external_file_obj The file object.
	 *
	 * @return bool
	 */
	public function prevent_export_of_watermark_image( bool $result, File $external_file_obj ): bool {
		// bail if the export is prevented already or this is not the image of the watermark.
		if ( $result || 0 === $external_file_obj->get_id() || $external_file_obj->get_id() !== $this->get_watermark_image_id() ) {
			return $result;
		}

		// log the reason once in this request.
		if ( empty( $this->logged[ $external_file_obj->get_id() ] ) ) {
			$this->logged[ $external_file_obj->get_id() ] = true;
			/* translators: %1$s will be replaced by the name of a plugin. */
			Log::get_instance()->create( sprintf( __( 'This image is not exported, as it is used as watermark by %1$s, which reads it only from the uploads directory.', 'external-files-in-media-library' ), 'Image Watermark' ), (string) wp_get_attachment_url( $external_file_obj->get_id() ), 'info', 2 );
		}

		// prevent the export.
		return true;
	}

	/**
	 * Restore the image, which the plugin uses as watermark, as local file, if it has been exported (e.g. directly
	 * after its upload in the settings of the plugin, before it has been chosen as watermark).
	 *
	 * @return void
	 */
	public function restore_watermark_image(): void {
		// get the ID of the image.
		$attachment_id = $this->get_watermark_image_id();

		// bail if there is none or it is not exported.
		if ( 0 === $attachment_id || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			return;
		}

		// restore it as local file, the copy on the external source is kept.
		Restore::get_instance()->restore_file( $attachment_id );
	}

	/**
	 * Restore the image of the watermark before an upload, so the plugin could add the watermark to the new image.
	 *
	 * @param mixed $file The file data of the upload.
	 *
	 * @return mixed
	 */
	public function restore_watermark_image_before_upload( mixed $file ): mixed {
		$this->restore_watermark_image();
		return $file;
	}

	/**
	 * Return the local file of an exported image with working copy as "_wp_attached_file" during the requests of the
	 * plugin, as it uses this value for the path of its backup (e.g. to remove the watermark).
	 *
	 * @param mixed  $value         The value (null to use the value in the database).
	 * @param int    $attachment_id The attachment ID.
	 * @param string $meta_key      The key.
	 * @param bool   $single        Whether a single value is requested.
	 *
	 * @return mixed
	 */
	public function get_local_attached_file( mixed $value, int $attachment_id, string $meta_key, bool $single ): mixed {
		// bail if this is not the requested key or not a request of the plugin.
		if ( '_wp_attached_file' !== $meta_key || ! $this->running || null !== $value ) {
			return $value;
		}

		// get the local file of the working copy (without this filter, as it reads the value, too).
		$this->running = false;
		$local_file    = Working_Copies::get_instance()->get_local_file( $attachment_id );
		$this->running = true;

		// bail if it has none.
		if ( '' === $local_file ) {
			return $value;
		}

		// return its path relative to the uploads directory.
		$relative = _wp_relative_upload_path( $local_file );
		return array( $relative );
	}

	/**
	 * Return the delay for the export of an upload: none, as the plugin adds the watermark during the upload before
	 * the export.
	 *
	 * @param int $delay         The delay in seconds.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function set_delay( int $delay, int $attachment_id ): int {
		return $delay;
	}

	/**
	 * Provide the working copy of an exported image, before the plugin adds or removes the watermark of a single
	 * image via AJAX.
	 *
	 * @return void
	 */
	public function start_single(): void {
		// bail if this is not a valid request of the plugin (the plugin checks it again).
		if ( ! isset( $_POST['_iw_nonce'], $_POST['iw-action'], $_POST['attachment_id'] ) || ! is_string( $_POST['_iw_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_iw_nonce'] ) ), 'image-watermark' ) ) {
			return;
		}

		// bail if this is not an action of the plugin.
		if ( ! is_string( $_POST['iw-action'] ) || ! in_array( sanitize_key( wp_unslash( $_POST['iw-action'] ) ), self::ACTIONS, true ) ) {
			return;
		}

		// the plugin needs the image of the watermark as local file.
		$this->restore_watermark_image();

		// provide the working copy, it is finished at the end of this request.
		$this->running = $this->provide_working_copy( absint( $_POST['attachment_id'] ) );
	}

	/**
	 * Provide the working copies of exported images, before the plugin adds or removes the watermark via its bulk
	 * action in the media library.
	 *
	 * @return void
	 */
	public function start_bulk(): void {
		// get the action.
		$action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			$action = isset( $_REQUEST['action2'] ) && is_string( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		// bail if this is not an action of the plugin.
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return;
		}

		// bail if the nonce is not valid.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! is_string( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-media' ) ) {
			return;
		}

		// the plugin needs the image of the watermark as local file.
		$this->restore_watermark_image();

		// get the images, which the plugin handles in this request.
		$attachment_ids = isset( $_REQUEST['media'] ) && is_array( $_REQUEST['media'] ) ? array_map( 'absint', wp_unslash( $_REQUEST['media'] ) ) : array();

		// provide their working copies, they are finished at the end of this request.
		foreach ( array_slice( $attachment_ids, 0, self::BULK_BATCH_SIZE ) as $attachment_id ) {
			if ( $this->provide_working_copy( $attachment_id ) ) {
				$this->running = true;
			}
		}
	}

	/**
	 * Mark the end of the work of the plugin with an image.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end( mixed $attachment_id ): void {
		$this->done( absint( $attachment_id ) );
	}

	/**
	 * Return the variants of the given local file: none, the plugin changes the file itself.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_variant_paths( string $path ): array {
		return array();
	}

	/**
	 * Return the backup of the original image, which the plugin saves in its own directory with the path of the image
	 * relative to the uploads directory.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		// get the directory of the backups.
		$backup_dir = defined( 'IMAGE_WATERMARK_BACKUP_DIR' ) && is_string( constant( 'IMAGE_WATERMARK_BACKUP_DIR' ) ) ? (string) constant( 'IMAGE_WATERMARK_BACKUP_DIR' ) : trailingslashit( wp_get_upload_dir()['basedir'] ) . 'iw-backup';

		// get the path of the file relative to the uploads directory.
		$base = trailingslashit( wp_normalize_path( wp_get_upload_dir()['basedir'] ) );
		$path = wp_normalize_path( $path );
		if ( ! str_starts_with( $path, $base ) ) {
			return array();
		}

		// return the backup.
		return array( trailingslashit( wp_normalize_path( $backup_dir ) ) . substr( $path, strlen( $base ) ) => $this->get_backup_name( $path, 'iw-backup' ) );
	}
}
