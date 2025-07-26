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
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
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
	 * @return void
	 */
	public function run(): void {
		if ( ! defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			define( 'EFML_DEINSTALLATION_RUNNING', 1 );
		}

		// remove schedules.
		Schedules::get_instance()->delete_all();

		// get external files object.
		$external_files_obj = Files::get_instance();

		// delete transients.
		$transients_obj = Transients::get_instance();
		foreach ( $transients_obj->get_transients() as $transient_obj ) {
			// delete transient-data.
			$transient_obj->delete();

			// delete dismiss-marker for this transient.
			delete_option( 'efiml-dismissed-' . md5( $transient_obj->get_name() ) );

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
			// bail if user is not WP_User.
			if ( ! $user instanceof WP_User ) {
				continue;
			}

			// loop through all extension and remove their settings.
			foreach ( Extensions::get_instance()->get_extensions_as_objects() as $extension_obj ) {
				delete_user_meta( $user->ID, 'efml_' . $extension_obj->get_name() );
			}

			// and also the "hide_dialog" setting.
			delete_user_meta( $user->ID, 'efml_hide_dialog' );
		}

		// run the uninstallation tasks for each file handling extension.
		Extensions::get_instance()->uninstall();

		// delete options this plugin has used.
		$options = array(
			'eml_sync_url_count',
			'eml_sync_url_max',
			'eml_sync_running',
			'eml_sync_title',
			'eml_sync_errors',
			'eml_transients',
			'efmlVersion',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// remove capability from roles.
		Roles::get_instance()->uninstall();

		// clean managed settings.
		\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->delete_settings();

		// cleanup own cache.
		Proxy::get_instance()->delete_cache_directory();

		// cleanup directory archive.
		Taxonomy::get_instance()->uninstall();

		// delete Log-database-table.
		Log::get_instance()->uninstall();
	}
}
