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

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;

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
	 * Run activation tasks on each supported ThirdParty-plugin.
	 *
	 * @return void
	 */
	public function activation(): void {
		foreach ( $this->get_services() as $service_class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $service_class_name ) ) {
				continue;
			}

			// get class name with method.
			$class_name = $service_class_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a service object.
			if ( ! $obj instanceof Service ) {
				continue;
			}

			// run its activation.
			$obj->activation();
		}
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use hook.
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 20 );
		add_action( 'init', array( $this, 'init_services' ) );
	}

	/**
	 * Initialize all services.
	 *
	 * @return void
	 */
	public function init_services(): void {
		// initiate each supported service.
		foreach ( $this->get_services() as $service_class_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $service_class_name ) ) {
				continue;
			}

			// get class name with method.
			$class_name = $service_class_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a service object.
			if ( ! $obj instanceof Service ) {
				continue;
			}

			// initialize this object.
			$obj->init();
		}
	}

	/**
	 * Return list of services support we implement.
	 *
	 * @return array<string>
	 */
	private function get_services(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\Services\Ftp',
			'ExternalFilesInMediaLibrary\Services\Imgur',
			'ExternalFilesInMediaLibrary\Services\GoogleDrive',
			'ExternalFilesInMediaLibrary\Services\Local',
			'ExternalFilesInMediaLibrary\Services\Rest',
			'ExternalFilesInMediaLibrary\Services\Vimeo',
			'ExternalFilesInMediaLibrary\Services\Youtube',
			'ExternalFilesInMediaLibrary\Services\Zip',
		);

		/**
		 * Filter the list of third party support.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<string> $list List of third party support.
		 */
		return apply_filters( 'eml_services_support', $list );
	}

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array<array<string>> $help_list List of help tabs.
	 *
	 * @return array<array<string>>
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Get files from external directory', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'The plugin allows you to integrate files from external directories into your media library.', 'external-files-in-media-library' ) . '</p>';
		$content .= '<h3>' . __( 'How to use', 'external-files-in-media-library' ) . '</h3>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<ol><li>' . sprintf( __( 'Go to Media > <a href="%1$s">Import from directory</a>.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_view_directory_url( false ) ) ) . '</li>';
		$content .= '<li>' . __( 'Choose the service you want to use.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Enter your credentials to use the service for your external directory.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Choose the files you want to import in your media library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '</ol>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-directory',
			'title'   => __( 'Using directories', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Return service by its name.
	 *
	 * @param string $method The name of the method.
	 *
	 * @return Directory_Listing_Base|false
	 */
	public function get_service_by_name( string $method ): Directory_Listing_Base|false {
		$directory_listing_obj = false;
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			// bail if name does not match.
			if ( $method !== $obj->get_name() ) {
				continue;
			}

			$directory_listing_obj = $obj;
		}

		// return resulting object.
		return $directory_listing_obj;
	}
}
