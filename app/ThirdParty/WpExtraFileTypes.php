<?php
/**
 * File to handle support for plugin "WP Extra File Types".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class WpExtraFileTypes extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?WpExtraFileTypes
	 */
	private static ?WpExtraFileTypes $instance = null;

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
	 * @return WpExtraFileTypes
	 */
	public static function get_instance(): WpExtraFileTypes {
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
		// bail if plugin is not enabled.
		if ( ! Helper::is_plugin_active( 'wp-extra-file-types/wp-extra-file-types.php' ) ) {
			return;
		}

		// use hooks.
		add_filter( 'eml_supported_mime_types', array( $this, 'add_file_types' ) );
	}

	/**
	 * Add file types enabled by this plugin to the list of allowed file types.
	 *
	 * @param array<string,array<string,string>> $possible_file_types List of possible file types in External Files for Media Library.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function add_file_types( array $possible_file_types ): array {
		// get the list from this plugin.
		$plugin_list_of_file_types = get_option( 'wpeft_types', array() );

		// bail if list is empty.
		if ( empty( $plugin_list_of_file_types ) ) {
			return $possible_file_types;
		}

		// bail if list is not an array.
		if ( ! is_array( $plugin_list_of_file_types ) ) {
			return $possible_file_types;
		}

		// add the additional types.
		foreach ( $plugin_list_of_file_types as $ext => $type ) {
			// bail if entry already exist.
			if ( isset( $possible_file_types[ $type ] ) ) {
				continue;
			}

			// add file type to the list.
			$possible_file_types[ $type ] = array(
				'label' => $type,
				'ext'   => $ext,
			);
		}

		// return resulting list of possible file types.
		return $possible_file_types;
	}
}
