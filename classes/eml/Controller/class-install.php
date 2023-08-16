<?php
/**
 * This file contains the install-handling for this plugin.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Helper;
use threadi\eml\Model\Log;

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
		global $wp_roles;

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

		// disable attachment-pages of URL-files on activation.
		if ( ! get_option( 'eml_disable_attachment_pages', false ) ) {
			update_option( 'eml_disable_attachment_pages', 1 );
		}

		// enable the deletion of all URL-files during uninstallation of this plugin.
		if ( ! get_option( 'eml_delete_on_deinstallation', false ) ) {
			update_option( 'eml_delete_on_deinstallation', 1 );
		}

		// set default mime-types we enable.
		if ( ! get_option( 'eml_allowed_mime_types' ) ) {
			update_option( 'eml_allowed_mime_types', array( 'application/pdf', 'image/jpeg', 'image/png' ) );
		}

		// set image-mode to external hosting.
		if ( ! get_option( 'eml_images_mode' ) ) {
			update_option( 'eml_images_mode', 'external' );
		}

		// set log-mode to normal.
		if ( ! get_option( 'eml_log_mode' ) ) {
			update_option( 'eml_log_mode', '0' );
		}

		// set user roles who can add external files.
		if ( ! get_option( 'eml_allowed_roles' ) ) {
			// define list of allowed roles.
			$user_roles = array(
				'administrator',
				'editor',
			);

			// set capabilities.
			helper::set_capabilities( $user_roles );

			// set the defined on-install supported roles in the settings.
			update_option( 'eml_allowed_roles', $user_roles );
		}

		// user new files should be assigned to (only fallback).
		if ( ! get_option( 'eml_user_assign' ) ) {
			// set him as fallback-user.
			update_option( 'eml_user_assign', helper::get_first_administrator_user() );
		}

		// enable the image-proxy initially.
		if ( ! get_option( 'eml_proxy' ) ) {
			// set him as fallback-user.
			update_option( 'eml_proxy', 1 );
		}

		// set max age for files in proxy-cache.
		if ( ! get_option( 'eml_proxy_max_age' ) ) {
			// set him as fallback-user.
			update_option( 'eml_proxy_max_age', 24 );
		}

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
