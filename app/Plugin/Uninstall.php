<?php
/**
 * This file contains the uninstall-handling for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transient;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_User;

/**
 * Uninstall this plugin.
 */
class Uninstall {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Uninstall
	 */
	private static ?Uninstall $instance = null;

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
	 * @return Uninstall
	 */
	public static function get_instance(): Uninstall {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Run uninstallation of this plugin.
	 *
	 * Hint:
	 * First set all settings as if the plugin is active.
	 * Then delete all these settings from DB and disable all.
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			define( 'EFML_DEINSTALLATION_RUNNING', 1 );
		}

		/**
		 * Set all settings.
		 */

		// initialize the plugin.
		Init::get_instance()->init();

		/**
		 * Run the global init to initialize all components.
		 */
		do_action( 'init' );

		// enable the settings.
		\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->activation();

		// clean managed settings.
		\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->delete_settings();

		// remove schedules.
		Schedules::get_instance()->delete_all();

		// get external files object.
		$external_files_obj = Files::get_instance();

		// delete transients.
		foreach ( Transients::get_instance()->get_transients( false, true ) as $transient_obj ) {
			// bail if the object is not ours.
			if ( ! $transient_obj instanceof Transient ) { // @phpstan-ignore instanceof.alwaysTrue
				continue;
			}

			// delete transient-data.
			$transient_obj->delete();
			$transient_obj->delete_dismiss();

			// backward-compatibility to < 2.0.0.
			delete_option( 'pi-dismissed-' . md5( $transient_obj->get_name() ) );
		}

		// delete files managed by this plugin, if option is enabled for it.
		if ( 1 === absint( get_option( 'eml_delete_on_deinstallation' ) ) ) {
			foreach ( $external_files_obj->get_files() as $external_file_obj ) {
				$external_file_obj->delete();
			}
		} elseif ( 1 === absint( get_option( 'eml_switch_on_uninstallation' ) ) ) {
			// switch hosting of files to local if option is enabled for it.
			foreach ( $external_files_obj->get_files() as $external_file_obj ) {
				// bail if file is already local hosted.
				if ( $external_file_obj->is_locally_saved() ) {
					continue;
				}

				// switch the hosting of this file to local.
				$external_file_obj->switch_to_local();
			}
		}

		// remove user-specific settings.
		$users = get_users();
		foreach ( $users as $user ) {
			// bail if user is not a "WP_User" object.
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			// loop through all extension and remove their settings.
			foreach ( Extensions::get_instance()->get_extensions_as_objects() as $extension_obj ) {
				delete_user_meta( $user->ID, 'efml_' . $extension_obj->get_name() );
			}

			// loop through all services and remove their settings.
			foreach ( Services::get_instance()->get_services_as_objects() as $service_obj ) {

				foreach ( $service_obj->get_user_settings() as $user_setting_name => $user_setting ) {
					delete_user_meta( $user->ID, 'efml_' . $user_setting_name );
				}
			}

			// and also the "hide_dialog" and the copyright setting.
			delete_user_meta( $user->ID, 'efml_hide_dialog' );
			delete_user_meta( $user->ID, 'efml_no_privacy_hint' );
		}

		// delete options this plugin has used.
		$options = array(
			'eml_sync_url_count',
			'eml_sync_url_max',
			'eml_sync_running',
			'eml_sync_title',
			'eml_sync_errors',
			'eml_transients',
			'efmlVersion',
			'eml_schedules',
			'efml_admin_id',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// remove custom transient.
		delete_transient( 'eml_aws_s3_regions' );

		// remove capability from roles.
		Roles::get_instance()->uninstall();

		// run the uninstallation tasks for each file handling extension.
		Extensions::get_instance()->uninstall();

		// cleanup own cache.
		Proxy::get_instance()->delete_cache_directory();

		// cleanup saved external sources.
		Taxonomy::get_instance()->uninstall();

		// reset the intro.
		Intro::get_instance()->reset_intro();

		// delete Log-database-table.
		Log::get_instance()->uninstall();
	}
}
