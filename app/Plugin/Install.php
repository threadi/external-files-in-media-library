<?php
/**
 * This file contains the install-handling for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions;
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
		// mark that activation is running.
		define( 'EFML_ACTIVATION_RUNNING', 1 );

		// add option for version of this plugin.
		add_option( 'efmlVersion', '', '', true );

		// initialize database-table for logs.
		Log::get_instance()->install();

		// install settings.
		Settings::get_instance()->activation();

		// initialize the extensions.
		Extensions::get_instance()->install();

		// enable the settings.
		\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->activation();

		// flush rewrite rules.
		Proxy::get_instance()->set_refresh();

		// install the schedules.
		Schedules::get_instance()->create_schedules();

		// set capability on roles.
		Roles::get_instance()->install();

		// create the message.
		/* translators: %1$s will be replaced by the URL where user can add media files. */
		$message = sprintf( __( '<strong>Your have installed <i>External files for media library</i> - great and thank you!</strong> You can now immediately add external URLs to your media library <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_add_media_url() ) ) . '<br><br>';

		// add button for intro, if not already closed.
		if( ! Intro::get_instance()->is_closed() ) {
			$message .= '<a href="#" class="button button-primary efml-intro-start">' . __( 'Show me how it works', 'external-files-in-media-library' ) . '</a>';
		}

		// add button to go to "add new files".
		$url = add_query_arg(
			array(
				'action' => 'efml_hide_welcome',
				'forward' => urlencode( Helper::get_add_media_url() ),
			),
			get_admin_url() . 'admin.php'
		);
		$message .= '<a href="' . esc_url( $url ) . '" class="button button-primary">' . __( 'Add your first external file', 'external-files-in-media-library' ) . '</a>';

		// add button to just hide this message and forward to media library
		$url = add_query_arg(
			array(
				'action' => 'efml_hide_welcome',
				'forward' => urlencode( Helper::get_media_library_url() ),
			),
			get_admin_url() . 'admin.php'
		);
		$message .= '<a href="' . esc_url( $url ) . '" class="button button-secondary">' . __( 'Hide this message', 'external-files-in-media-library' ) . '</a>';

		// trigger a welcome message.
		$transients_obj = Transients::get_instance();
		$transient_obj = $transients_obj->add();
		$transient_obj->set_dismissible_days( 2 );
		$transient_obj->set_name( 'eml_welcome' );
		$transient_obj->set_message( $message );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();
	}

	/**
	 * Run during plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivation(): void {
		if ( ! defined( 'EFML_DEACTIVATION_RUNNING' ) ) {
			define( 'EFML_DEACTIVATION_RUNNING', 1 );
		}

		// remove schedules.
		Schedules::get_instance()->delete_all();
	}
}
