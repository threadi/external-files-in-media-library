<?php
/**
 * This file contains the install-handling for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\ExternalFiles\Queue;

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
	 * @return Install
	 */
	public static function get_instance(): Install {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		define( 'EML_ACTIVATION_RUNNING', 1 );

		// initialize Log-database-table.
		Log::get_instance()->install();

		// install settings.
		Settings::get_instance()->activation();

		// initialize the queue-handling.
		$files_obj = Queue::get_instance();
		$files_obj->install();
		$files_obj->init_queue();
		Settings\Settings::get_instance()->activation();

		// flush rewrite rules.
		Proxy::get_instance()->set_refresh();

		// install the schedules.
		Schedules::get_instance()->create_schedules();

		// trigger a welcome message if it is not already set.
		$transients_obj = Transients::get_instance();
		if ( ! $transients_obj->get_transient_by_name( 'eml_welcome' )->is_set() ) {
			$transient_obj = $transients_obj->add();
			$transient_obj->set_dismissible_days( 2 );
			$transient_obj->set_name( 'eml_welcome' );
			/* translators: %1$s will be replaced by the URL where user can add media files. */
			$transient_obj->set_message( sprintf( __( '<strong>Your have installed <i>External files for media library</i> - great and thank you!</strong> You can now immediately add external URLs to your media library <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_add_media_url() ) ) );
			$transient_obj->set_type( 'success' );
			$transient_obj->save();
		}
	}

	/**
	 * Run during plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		define( 'EML_DEACTIVATION_RUNNING', 1 );

		// remove schedules.
		Schedules::get_instance()->delete_all();
	}
}
