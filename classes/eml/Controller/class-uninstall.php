<?php
/**
 * This file contains the uninstall-handling for this plugin.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use threadi\eml\Model\External_File;
use threadi\eml\Model\Log;
use threadi\eml\Transients;

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
		$external_files_obj = External_Files::get_instance();

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
				if ( ! $external_file_obj instanceof External_File ) {
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
			'eml_delete_on_deinstallation',
			'eml_switch_on_uninstallation',
			'eml_disable_attachment_pages',
			'eml_check_interval',
			'eml_allowed_mime_types',
			'eml_images_mode',
			'eml_log_mode',
			'eml_allowed_roles',
			'eml_user_assign',
			'eml_proxy',
			'eml_proxy_max_age',
			'eml_import_url_count',
			'eml_import_url_max',
			'eml_import_running',
			'eml_import_title',
			'eml_import_errors',
		);
		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// cleanup own cache.
		$external_files_obj->delete_cache_directory();

		// delete Log-database-table.
		$log = Log::get_instance();
		$log->uninstall();
	}
}
