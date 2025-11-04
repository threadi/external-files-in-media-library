<?php
/**
 * This file contains the handling of services we provide.
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
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;

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
		foreach ( $this->get_services_as_objects() as $obj ) {
			$obj->activation();
		}
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use our own hooks.
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 20 );
		add_filter( 'eml_dialog_settings', array( $this, 'set_dialog_settings_for_services' ) );
		add_filter( 'eml_add_dialog', array( $this, 'add_service_in_form' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_service_hint_in_form' ), 100, 2 );

		// misc.
		add_action( 'init', array( $this, 'init_settings' ), 15 );
		add_action( 'init', array( $this, 'init_services' ) );
		add_action( 'cli_init', array( $this, 'init_services' ) );
		add_action( 'rest_api_init', array( $this, 'init_services' ) );
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add new tab for services.
		$tab = $settings_page->add_tab( 'services', 110 );
		$tab->set_title( __( 'Services', 'external-files-in-media-library' ) );
		$tab->set_hide_save( true );

		// add tab for hint.
		$main_services_tab = $tab->add_tab( 'services', 0 );
		$main_services_tab->set_title( __( 'Services', 'external-files-in-media-library' ) );
		$main_services_tab->set_hide_save( true );
		$main_services_tab->set_callback( array( $this, 'show_settings_services_hint' ) );
		$tab->set_default_tab( $main_services_tab );
	}

	/**
	 * Initialize all services.
	 *
	 * @return void
	 */
	public function init_services(): void {
		foreach ( $this->get_services_as_objects() as $obj ) {
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
			'ExternalFilesInMediaLibrary\Services\DropBox',
			'ExternalFilesInMediaLibrary\Services\Ftp',
			'ExternalFilesInMediaLibrary\Services\Imgur',
			'ExternalFilesInMediaLibrary\Services\GoogleDrive',
			'ExternalFilesInMediaLibrary\Services\GoogleCloudStorage',
			'ExternalFilesInMediaLibrary\Services\Local',
			'ExternalFilesInMediaLibrary\Services\Rest',
			'ExternalFilesInMediaLibrary\Services\S3',
			'ExternalFilesInMediaLibrary\Services\Vimeo',
			'ExternalFilesInMediaLibrary\Services\WebDav',
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
	 * Return all supported services as objects.
	 *
	 * @return array<int,Service>
	 */
	public function get_services_as_objects(): array {
		// create the list.
		$services = array();

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

			// add object to the list.
			$services[] = $obj;
		}

		// return the resulting list.
		return $services;
	}

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array<array<string>> $help_list List of help tabs.
	 *
	 * @return array<array<string>>
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Get files from external sources', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'The plugin allows you to integrate files from different external sources into your media library.', 'external-files-in-media-library' ) . '</p>';
		$content .= '<h3>' . __( 'How to use', 'external-files-in-media-library' ) . '</h3>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<ol><li>' . sprintf( __( 'Go to Media > <a href="%1$s">Add External Files</a>.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_view_directory_url( false ) ) ) . '</li>';
		$content .= '<li>' . __( 'Choose the external source you want to use.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Enter your credentials to use the service for your external directory, if requested.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Choose the files you want to import into your media library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '</ol>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-directory',
			'title'   => __( 'Using external sources', 'external-files-in-media-library' ),
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

	/**
	 * Format the import dialog settings if "service" is given.
	 *
	 * This will prevent a visible dialog and start the import automatic.
	 *
	 * Exception: the user has configured to show this dialog.
	 *
	 * @param array<string,mixed> $settings The dialog settings.
	 *
	 * @return array<string,mixed>
	 */
	public function set_dialog_settings_for_services( array $settings ): array {
		// bail if "service" is not set.
		if ( ! isset( $settings['service'] ) ) {
			return $settings;
		}

		// set dialog settings for prevent a visible dialog with options.
		$settings['no_textarea']    = true;
		$settings['no_services']    = true;
		$settings['no_credentials'] = true;
		$settings['no_dialog']      = 1 === absint( get_user_meta( get_current_user_id(), 'efml_hide_dialog', true ) );

		// return the resulting dialog settings.
		return $settings;
	}

	/**
	 * Show hint on direct call of services tab under settings.
	 *
	 * @return void
	 */
	public function show_settings_services_hint(): void {
		echo '<h2>' . esc_html__( 'Settings for services', 'external-files-in-media-library' ) . '</h2>';
		echo '<p>' . esc_html__( 'Services help you access external data sources for files.', 'external-files-in-media-library' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Select one of the services to access its settings.', 'external-files-in-media-library' ) . '</strong></p>';
	}

	/**
	 * Add hidden field in dialog for used service.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_service_in_form( array $dialog, array $settings ): array {
		// bail if 'term' is not set in settings.
		if ( ! isset( $settings['service'] ) ) {
			return $dialog;
		}

		// add the hidden input for given service.
		$dialog['texts'][] = '<input type="hidden" name="service" value="' . esc_attr( $settings['service'] ) . '">';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Add option to import from any service.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_service_hint_in_form( array $dialog, array $settings ): array {
		// bail if block support does not exist.
		if ( ! Helper::is_block_support_enabled() ) {
			return $dialog;
		}

		// bail if "no_services" is set in settings.
		if ( isset( $settings['no_services'] ) ) {
			return $dialog;
		}

		// collect the possible services as list.
		$list = '';
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			// bail if it is disabled.
			if ( $obj->is_disabled() ) {
				continue;
			}

			// hide single import.
			if ( 'import' === $obj->get_name() ) {
				continue;
			}

			// add this service to the list.
			$list .= '<a href="' . Directory_Listing::get_instance()->get_view_directory_url( $obj ) . '" class="button button-secondary">' . esc_html( $obj->get_label() ) . '</a>';
		}

		// bail if list is empty.
		if ( empty( $list ) ) {
			return $dialog;
		}

		// add the hint for local import.
		$dialog['texts'][] = '<details><summary>' . __( 'Or use an external source', 'external-files-in-media-library' ) . '</summary><div class="eml_service_list">' . $list . '</div></details>';

		// return the resulting dialog.
		return $dialog;
	}
}
