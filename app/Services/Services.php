<?php
/**
 * This file contains the handling of third party support we provide.
 *
 * This could be an external file platform we adapt.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for specific services.
 */
class Services {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Services
	 */
	private static ?Services $instance = null;

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
	 * @return Services
	 */
	public static function get_instance(): Services {
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
		foreach ( $this->get_services() as $class_name ) {
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
	 * Return list of services support we implement.
	 *
	 * @return array
	 */
	private function get_services(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\Services\Ftp',
			'ExternalFilesInMediaLibrary\Services\Imgur',
			'ExternalFilesInMediaLibrary\Services\GoogleDrive',
			'ExternalFilesInMediaLibrary\Services\Local',
			'ExternalFilesInMediaLibrary\Services\Vimeo',
			'ExternalFilesInMediaLibrary\Services\Youtube',
		);

		/**
		 * Filter the list of third party support.
		 *
		 * @since 2.1.0 Available since 2.1.0.
		 * @param array $list List of third party support.
		 */
		return apply_filters( 'eml_services_support', $list );
	}
}
