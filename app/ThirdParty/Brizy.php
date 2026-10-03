<?php
/**
 * File to handle support for the plugin "Brizy".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class Brizy extends ThirdParty_Base implements ThirdParty {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Brizy
	 */
	private static ?Brizy $instance = null;

	/**
	 * List of temp files created during this request, indexed by attachment ID.
	 *
	 * @var array<int,string>
	 */
	private array $tmp_files = array();

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
	 * @return Brizy
	 */
	public static function get_instance(): Brizy {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Brizy is not installed.
		if ( ! Helper::is_plugin_active( 'brizy/brizy.php' ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'wp_get_attachment_image_src', array( $this, 'change_image_src' ), 10, 2 );
	}

	/**
	 * Change the image src if it is used during the request from Brizy to generate the images.
	 *
	 * Example: https://localhost/?brizy_media=wp-abcded.jpg&brizy_crop=original
	 *
	 * @param mixed $image The image data (core uses false here if no image is available).
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function change_image_src( mixed $image, mixed $attachment_id ): mixed {
		// bail if no image data are given (core uses false here if no image is available).
		if ( ! is_array( $image ) ) {
			return $image;
		}

		// bail if parameter brizy_media is not set in request.
		$brizy_media = filter_input( INPUT_GET, 'brizy_media', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $brizy_media ) ) {
			return $image;
		}

		// get the external files object for the given attachment.
		$external_files_obj = Files::get_instance()->get_file( absint( $attachment_id ) );

		// bail if image is not an external file.
		if ( ! $external_files_obj->is_valid() ) {
			return $image;
		}

		// get its protocol handler.
		$protocol_handler = $external_files_obj->get_protocol_handler_obj();

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler instanceof Protocol_Base ) {
			return $image;
		}

		// set the fields (for logins in external systems).
		$protocol_handler->set_fields( $external_files_obj->get_fields() );

		// use the already loaded temp file for this attachment, if it has been requested before in this request.
		$attachment_id = absint( $attachment_id );
		if ( isset( $this->tmp_files[ $attachment_id ] ) ) {
			$image[0] = $this->tmp_files[ $attachment_id ];
			return $image;
		}

		// get the temp directory as Brizy need the file local.
		$tmp_file = $protocol_handler->get_temp_file( $external_files_obj->get_url( true ), Helper::get_wp_filesystem() );

		// bail if not tmp file could be loaded.
		if ( ! $tmp_file ) {
			return $image;
		}

		// remember the temp file and remove all of them at the end of this request.
		if ( empty( $this->tmp_files ) ) {
			add_action( 'shutdown', array( $this, 'delete_tmp_files' ) );
		}
		$this->tmp_files[ $attachment_id ] = (string) $tmp_file;

		// add the tmp file to the image data.
		$image[0] = (string) $tmp_file;

		// return the resulting image data.
		return $image;
	}

	/**
	 * Delete the temp files we created during this request.
	 *
	 * @return void
	 */
	public function delete_tmp_files(): void {
		foreach ( $this->tmp_files as $tmp_file ) {
			wp_delete_file( $tmp_file );
		}
		$this->tmp_files = array();
	}
}
