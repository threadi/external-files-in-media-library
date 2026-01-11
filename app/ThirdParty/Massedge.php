<?php
/**
 * File to handle support for the plugin "Export Media Library".
 *
 * @source https://wordpress.org/plugins/export-media-library/
 *
 * @noinspection PhpPluralMixedCanBeReplacedWithArrayInspection
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to handle support for this plugin.
 */
class Massedge extends ThirdParty_Base implements ThirdParty {

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
	 * @param array<mixed> $value The values.
	 * @param array<int>   $params The params.
	 *
	 * @return array<mixed>
	 */
	public function prevent_external_attachment_in_export( array $value, array $params ): array {
		// bail if no attachment ID is given.
		if ( ! isset( $params['attachment_id'] ) ) {
			return $value;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $params['attachment_id'] );

		// check if the file is an external file, could be proxied and if it is external hosted.
		if (
			$external_file_obj->is_valid()
			&& false === $external_file_obj->is_locally_saved()
			&& $external_file_obj->get_file_type_obj()->is_proxy_enabled()
		) {
			return array();
		}

		// return the given value.
		return $value;
	}
}
