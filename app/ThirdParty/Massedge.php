<?php
/**
 * File to handle support for plugin "Export Media Library".
 *
 * @source https://wordpress.org/plugins/export-media-library/
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this plugin.
 */
class Massedge {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Massedge
	 */
	private static ?Massedge $instance = null;

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
	 * @return Massedge
	 */
	public static function get_instance(): Massedge {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Massedge();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'massedge-wp-eml/export/add_attachment', array( $this, 'prevent_external_attachment_in_export' ), 10, 2 );
	}

	/**
	 * Prevent usage of external hosted attachments by the export via https://wordpress.org/plugins/export-media-library/
	 *
	 * @param array $value The values.
	 * @param array $params The params.
	 *
	 * @return array
	 */
	public function prevent_external_attachment_in_export( array $value, array $params ): array {
		if ( isset( $params['attachment_id'] ) ) {
			// get the external file object.
			$external_file_obj = Files::get_instance()->get_file( $params['attachment_id'] );

			// check if the file is an external file, an image and if it is really external hosted.
			if (
				$external_file_obj
				&& $external_file_obj->is_valid()
				&& false === $external_file_obj->is_locally_saved()
				&& $external_file_obj->is_image()
			) {
				return array();
			}
		}
		return $value;
	}
}
