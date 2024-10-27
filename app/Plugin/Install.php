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
 * Object which handles the installation of this plugin.
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

		// show welcome message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_dismissible_days( 2 );
		$transient_obj->set_name( 'eml_welcome' );
		/* translators: %1$s will be replaced by the URL where user can add media files. */
		$transient_obj->set_message( sprintf( __( '<strong>Your have installed <i>External files for media library</i> - great and thank you!</strong> You can now immediately add external URLs to your media library <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_add_media_url() ) ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();
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
