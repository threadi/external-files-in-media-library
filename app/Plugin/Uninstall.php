<?php
/**
 * This file contains the uninstall-handling for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

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
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run uninstallation of this plugin.
	 *
	 * @return void
	 */
	public function run(): void {
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
		if ( get_option( 'eml_delete_on_deinstallation', false ) ) {
			foreach ( $external_files_obj->get_files_in_media_library() as $external_file_obj ) {
				$external_files_obj->delete_file( $external_file_obj );
			}
		} elseif ( get_option( 'eml_switch_on_uninstallation', false ) ) {
			// switch hosting of files to local if option is enabled for it.
			foreach ( $external_files_obj->get_files_in_media_library() as $external_file_obj ) {
				// bail if this is not an external file object.
				if ( ! $external_file_obj instanceof File ) {
					continue;
				}

				// bail if file is already local hosted.
				if ( $external_file_obj->is_locally_saved() ) {
					continue;
				}

				// switch the hosting of this file to local.
				$external_file_obj->switch_to_local();
			}
		}

		// delete options this plugin has used.
		$options = array(
			'eml_import_url_count',
			'eml_import_url_max',
			'eml_import_running',
			'eml_import_title',
			'eml_import_errors',
			'eml_transients',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// clean managed settings.
		Settings\Settings::get_instance()->delete_settings();

		// cleanup own cache.
		$external_files_obj->delete_cache_directory();

		// delete Log-database-table.
		Log::get_instance()->uninstall();
	}
}
