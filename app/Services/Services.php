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
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;

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
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use hook.
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 20 );

		// initiate each supported service.
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

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array $help_list List of help tabs.
	 *
	 * @return array
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
}
