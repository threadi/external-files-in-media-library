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
	 * @param array<int,mixed> $image The image data.
	 * @param mixed            $attachment_id The attachment ID.
	 *
	 * @return array<int,mixed>
	 */
	public function change_image_src( array $image, mixed $attachment_id ): array {
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

		// get the temp directory as Brizy need the file local.
		$tmp_file = $protocol_handler->get_temp_file( $external_files_obj->get_url( true ), Helper::get_wp_filesystem() );

		// bail if not tmp file could be loaded.
		if ( ! $tmp_file ) {
			return $image;
		}

		// add the tmp file to the image data.
		$image[0] = $tmp_file;

		// return the resulting image data.
		return $image;
	}
}
