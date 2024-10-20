<?php
/**
 * This file contains the handling of third party support we provide.
 *
 * This could be another WP-plugin or an external file platform we adapt.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle third party support.
 */
class ThirdPartySupport {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ThirdPartySupport
	 */
	private static ?ThirdPartySupport $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return ThirdPartySupport
	 */
	public static function get_instance(): ThirdPartySupport {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		foreach ( $this->get_third_party_support() as $class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = call_user_func( $class_name . '::get_instance' );
			$obj->init();
		}
	}

	/**
	 * Return list of third party support we implement.
	 *
	 * @return array
	 */
	private function get_third_party_support(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ThirdParty\Downloadlist',
			'ExternalFilesInMediaLibrary\ThirdParty\Exmage',
			'ExternalFilesInMediaLibrary\ThirdParty\GoogleDrive',
			'ExternalFilesInMediaLibrary\ThirdParty\Imgur',
			'ExternalFilesInMediaLibrary\ThirdParty\Massedge',
		);

		/**
		 * Filter the list of third party support.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of third party support.
		 */
		return apply_filters( 'eml_third_party_support', $list );
	}
}
