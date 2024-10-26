<?php
/**
 * This file contains the install-handling for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;

/**
 * Initialize the plugin, connect all together.
 */
class Install {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Install
	 */
	private static ?Install $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Install
	 */
	public static function get_instance(): Install {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		// initialize Log-database-table.
		$log = Log::get_instance();
		$log->install();

		// add schedule for file-check.
		if ( ! wp_next_scheduled( 'eml_check_files' ) ) {
			if ( ! get_option( 'eml_check_interval', false ) ) {
				update_option( 'eml_check_interval', 'daily' );
			}
			if ( get_option( 'eml_check_interval' ) !== 'eml_disable_check' ) {
				wp_schedule_event( time(), get_option( 'eml_check_interval' ), 'eml_check_files' );
			}
		}

		// install settings.
		Settings::get_instance()->activation();

		// flush rewrite rules.
		Proxy::get_instance()->set_refresh();
	}

	/**
	 * Run during plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		// remove schedule.
		wp_clear_scheduled_hook( 'eml_check_files' );
	}
}
