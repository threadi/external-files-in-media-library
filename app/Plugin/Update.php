<?php
/**
 * File for handling updates of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\ExternalFiles\Queue;
use function ElementorDeps\DI\string;

/**
 * Helper-function for updates of this plugin.
 */
class Update {
	/**
	 * Instance of this object.
	 *
	 * @var ?Update
	 */
	private static ?Update $instance = null;

	/**
	 * Constructor for Init-Handler.
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
	 * @return Update
	 */
	public static function get_instance(): Update {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the Updater.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'run' ) );
	}

	/**
	 * Run check for updates.
	 *
	 * @return void
	 */
	public function run(): void {
		// get installed plugin-version (version of the actual files in this plugin).
		$installed_plugin_version = EFML_PLUGIN_VERSION;

		// get db-version (version which was last installed).
		$db_plugin_version = get_option( 'efmlVersion', '1.0.0' );

		// bail if version is not a string.
		if ( ! is_string( $db_plugin_version ) ) {
			return;
		}

		// compare version if we are not in development-mode.
		if (
			(
				(
					function_exists( 'wp_is_development_mode' ) && false === wp_is_development_mode( 'plugin' )
				)
				|| ! function_exists( 'wp_is_development_mode' )
			)
			&& version_compare( $installed_plugin_version, $db_plugin_version, '>' )
		) {
			if ( ! defined( 'EFML_UPDATE_RUNNING ' ) ) {
				define( 'EFML_UPDATE_RUNNING', 1 );
			}
			$this->version200();
			$this->version201();
			$this->version300();

			// save new plugin-version in DB.
			update_option( 'efmlVersion', $installed_plugin_version );
		}
	}

	/**
	 * To run on update to version 2.0.0 or newer.
	 *
	 * @return void
	 */
	private function version200(): void {
		if ( ! get_option( 'efmlVersion', false ) ) {
			// add option for version of this plugin.
			add_option( 'efmlVersion', '', '', true );
		}

		// run the same tasks for all settings as if we activate the plugin.
		Settings::get_instance()->activation();
	}

	/**
	 * To run on update to version 2.0.1 or newer.
	 *
	 * @return void
	 */
	private function version201(): void {
		// get actual capabilities.
		$caps = get_option( 'eml_allowed_roles' );

		// check for array.
		if ( ! is_array( $caps ) ) {
			$caps = array();
		}

		// if list is empty, set the defaults.
		if ( empty( $caps ) ) {
			$caps = array( 'administrator', 'editor' );
			update_option( 'eml_allowed_roles', $caps );
		}

		// set capabilities.
		Helper::set_capabilities( $caps );
	}

	/**
	 * To run on update to version 3.0.0 or newer.
	 *
	 * @return void
	 */
	private function version300(): void {
		// update database-table for logs.
		Log::get_instance()->install();

		// update database-table for queues.
		Queue::get_instance()->install();

		// set proxy marker for all files where proxy is enabled.
		foreach ( Files::get_instance()->get_files() as $external_file_obj ) {
			// bail if proxy is not enabled for this file.
			if ( ! $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
				continue;
			}

			// add the post meta for proxy time.
			update_post_meta( $external_file_obj->get_id(), 'eml_proxied', time() );
		}

		// flush rewrite rules.
		Proxy::get_instance()->set_refresh();
	}
}
