<?php
/**
 * File to handle support for plugin "Download List with Icons".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to handle support for this plugin.
 */
class Downloadlist extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Downloadlist
	 */
	private static ?Downloadlist $instance = null;

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
	 * @return Downloadlist
	 */
	public static function get_instance(): Downloadlist {
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
		add_filter( 'downloadlist_rel_attribute', array( $this, 'set_rel_attribute' ), 10, 2 );
	}

	/**
	 * Set the rel-attribute for external files.
	 *
	 * @param string $rel_attribute The rel-value.
	 * @param array<string,string>  $file The file-attributes.
	 *
	 * @return string
	 */
	public function set_rel_attribute( string $rel_attribute, array $file ): string {
		// bail if array is empty.
		if ( empty( $file ) ) {
			return $rel_attribute;
		}

		// bail if id is not given.
		if ( empty( $file['id'] ) ) {
			return $rel_attribute;
		}

		// check if this is an external file.
		$external_file_obj = Files::get_instance()->get_file( (int) $file['id'] );

		// quick return the given $url if file is not a URL-file.
		if ( false === $external_file_obj ) {
			return $rel_attribute;
		}

		// return the original URL if this URL-file is not valid or not available or a not allowed mime type.
		if ( false === $external_file_obj->is_valid() || false === $external_file_obj->is_available() || false === $external_file_obj->is_mime_type_allowed() ) {
			return $rel_attribute;
		}

		// return external value for rel-attribute.
		return 'external';
	}
}
