<?php
/**
 * File for handling updates of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Term_Query;

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
		add_action( 'init', array( $this, 'run' ), 20 );
	}

	/**
	 * Run check for updates.
	 *
	 * @return void
	 */
	public function run(): void {
		// bail during uninstallation.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// get installed plugin-version (version of the actual files in this plugin).
		$installed_plugin_version = EFML_PLUGIN_VERSION;

		// get db-version (version, which was last installed).
		$db_plugin_version = get_option( 'efmlVersion', '1.0.0' );

		// bail if version is not a string.
		if ( ! is_string( $db_plugin_version ) ) {
			return;
		}

		// compare version if we are not in development-mode.
		if ( ! Helper::is_development_mode() && version_compare( $installed_plugin_version, $db_plugin_version, '>' ) ) {
			if ( ! defined( 'EFML_UPDATE_RUNNING' ) ) {
				define( 'EFML_UPDATE_RUNNING', 1 );
			}
			if ( version_compare( $db_plugin_version, '2.0.0', '<' ) ) {
				$this->version200();
			}
			if ( version_compare( $db_plugin_version, '2.0.1', '<' ) ) {
				$this->version201();
			}
			if ( version_compare( $db_plugin_version, '3.0.0', '<' ) ) {
				$this->version300();
			}
			if ( version_compare( $db_plugin_version, '4.0.0', '<' ) ) {
				$this->version400();
			}
			if ( version_compare( $db_plugin_version, '5.0.0', '<' ) ) {
				$this->version500();
			}
			if ( version_compare( $db_plugin_version, '5.2.0', '<' ) ) {
				$this->version520();
			}
			if ( version_compare( $db_plugin_version, '5.2.3', '<' ) ) {
				$this->version523();
			}
			if ( version_compare( $db_plugin_version, '5.4.0', '<' ) ) {
				$this->version540();
			}

			// save new plugin-version in the DB.
			update_option( 'efmlVersion', $installed_plugin_version );
		}
	}

	/**
	 * To run on the update to version 2.0.0 or newer.
	 *
	 * @return void
	 */
	private function version200(): void {
		if ( ! get_option( 'efmlVersion', false ) ) {
			// add option for the version of this plugin.
			add_option( 'efmlVersion', '', '', true );
		}

		// run the same tasks for all settings as if we activate the plugin.
		Settings::get_instance()->activation();
	}

	/**
	 * To run on the update to version 2.0.1 or newer.
	 *
	 * @return void
	 */
	private function version201(): void {
		// get the configured roles.
		$roles = get_option( 'eml_allowed_roles' );

		// check for an array.
		if ( ! is_array( $roles ) ) {
			$roles = array();
		}

		// if list is empty, set the defaults.
		if ( empty( $roles ) ) {
			$roles = array( 'administrator', 'editor' );
			update_option( 'eml_allowed_roles', $roles );
		}

		// set capabilities.
		Roles::get_instance()->set( $roles, EFML_CAP_NAME );
	}

	/**
	 * To run on the update to version 3.0.0 or newer.
	 *
	 * @return void
	 */
	private function version300(): void {
		// update database-table for logs.
		Log::get_instance()->install();

		// update database-table for queues.
		Queue::get_instance()->install();

		// set the proxy marker for all files where proxy is enabled.
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

	/**
	 * To run on the update to version 4.0.0 or newer.
	 *
	 * @return void
	 */
	private function version400(): void {
		/**
		 * Update the interval name for file check.
		 */
		// get the file check schedule object.
		$file_check_event_obj = new Check_Files();

		// get the new interval name.
		$interval_name = Helper::map_old_to_new_interval( $file_check_event_obj->get_interval() );

		// save it in setting.
		update_option( 'eml_check_interval', $interval_name );

		// set the new interval.
		$file_check_event_obj->set_interval( $interval_name );

		// reinstall the event.
		$file_check_event_obj->reset();

		/**
		 * Update the interval name for the queue.
		 */
		// get the queue schedule object.
		$queue_event_obj = new Schedules\Queue();

		// get the new interval name.
		$interval_name = Helper::map_old_to_new_interval( $queue_event_obj->get_interval() );

		// save it in setting.
		update_option( 'eml_queue_interval', $interval_name );

		// set the new interval.
		$queue_event_obj->set_interval( $interval_name );

		// reinstall the event.
		$queue_event_obj->reset();
	}

	/**
	 * To run on the update to version 5.0.0 or newer.
	 *
	 * @return void
	 */
	public function version500(): void {
		// enable file hiding.
		update_option( 'eml_directory_listing_hide_not_supported_file_types', 1 );

		// remove not used options.
		delete_option( 'eml_import_errors' );
		delete_option( 'eml_import_files' );
		delete_option( 'eml_import_url_count' );
		delete_option( 'eml_import_url_max' );
		delete_option( 'eml_import_running' );
		delete_option( 'eml_import_title' );

		// update database-table for logs.
		Log::get_instance()->install();

		// update database-table for queues.
		Queue::get_instance()->install();

		// set new options.
		update_option( 'eml_user_settings', 1 );
		update_option( 'eml_import_extensions', ImportDialog::get_instance()->get_default_extensions() );
		update_option( 'eml_export_extensions', ExportDialog::get_instance()->get_default_extensions() );

		// migrate the image proxy setting.
		update_option( 'eml_images_proxy', get_option( 'eml_proxy' ) );
		update_option( 'eml_images_proxy_max_age', get_option( 'eml_proxy_max_age' ) );

		// loop through all saved external sources and add their path meta.
		foreach ( Directory_Listing::get_instance()->get_external_sources() as $term ) {
			update_term_meta( $term->term_id, 'path', $term->name );
		}

		// init the main settings.
		Settings::get_instance()->add_settings();

		// initiate the services.
		Services::get_instance()->init_services();

		// initiate the settings for roles.
		Roles::get_instance()->add_settings();

		// initiate the directory listing settings.
		Directory_Listing::get_instance()->add_settings();

		// add the file types settings.
		File_Types::get_instance()->add_settings();

		// trigger the capability settings.
		Roles::get_instance()->trigger_update();

		// set configured capabilities.
		Roles::get_instance()->install();

		// flush rewrite rules.
		Proxy::get_instance()->set_refresh();

		// run the same tasks for all settings as if we activate the plugin.
		Settings::get_instance()->activation();

		// set caching options.
		add_option( 'efml_directory_listing_used', 0, '', true );

		// convert the installation hash.
		if ( ! defined( 'EDLFW_HASH' ) ) {
			define( 'EDLFW_HASH', get_option( 'edlfw_hash', '' ) );
		}

		// migrate existing external sources to new format.
		$query = array(
			'taxonomy'   => 'edlfw_archive',
			'hide_empty' => false,
		);
		$terms = new WP_Term_Query( $query );
		foreach ( $terms->terms as $term ) {
			// bail if path is set.
			if ( ! empty( get_term_meta( $term->term_id, 'path', true ) ) ) {
				continue;
			}

			// get the name, which contains the URL.
			$url = $term->name;

			// get the protocol handler for this URL.
			$protocol_handler = Protocols::get_instance()->get_protocol_object_for_url( $url );

			// bail if this is not an FTP protocol.
			if ( ! $protocol_handler instanceof FTP ) {
				continue;
			}

			// set path.
			update_term_meta( $term->term_id, 'path', $term->name );

			// create fields.
			$fields                      = $protocol_handler->get_fields();
			$fields['server']['value']   = $url;
			$fields['login']['value']    = Crypt::get_instance()->decrypt( get_term_meta( $term->term_id, 'login', true ) );
			$fields['password']['value'] = Crypt::get_instance()->decrypt( get_term_meta( $term->term_id, 'password', true ) );
			update_term_meta( $term->term_id, 'fields', Crypt::get_instance()->encrypt( Helper::get_json( $fields ) ) );
		}
	}

	/**
	 * To run on the update to version 5.2.0 or newer.
	 *
	 * @return void
	 */
	private function version520(): void {
		// get the proxy object.
		$proxy = Proxy::get_instance();

		// get the proxy cache path.
		$path = $proxy->get_cache_directory();

		// secure it.
		$proxy->secure_cache_directory( $path );
	}

	/**
	 * To run on the update to version 5.2.3 or newer.
	 *
	 * @return void
	 */
	private function version523(): void {
		// encrypt the global Dropbox token.
		$data = get_option( 'efml_dropbox_access_tokens', array() );
		if ( ! empty( $data ) ) {
			update_option( 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $data ) ) );
		}
	}

	/**
	 * To run on the update to version 5.4.0 or newer.
	 *
	 * @return void
	 */
	private function version540(): void {
		global $wpdb;

		// update the table structure.
		Log::get_instance()->install();

		// remove the obsolete unique key on the primary column, if it still exists.
		$index = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM ' . $wpdb->prefix . 'eml_logs WHERE Key_name = %s', array( 'id' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! is_null( $index ) ) {
			$wpdb->query( 'ALTER TABLE ' . $wpdb->prefix . 'eml_logs DROP INDEX `id`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// get the terms with set interval as < 5.4.0 has it done.
		$query      = array(
			'taxonomy'     => Taxonomy::get_instance()->get_name(),
			'hide_empty'   => false,
			'count'        => false,
			'meta_key'     => 'interval', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Filter for meta.
			'meta_compare' => 'EXISTS',
			'fields'       => 'ids',
		);
		$sync_terms = new WP_Term_Query( $query );

		// bail if no terms could be loaded.
		if ( is_array( $sync_terms->terms ) ) { // @phpstan-ignore function.alreadyNarrowedType
			// update their settings.
			foreach ( $sync_terms->terms as $term_id ) {
				// get the schedule object for this term.
				$schedule_obj = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );

				// bail if this term does not have a schedule.
				if ( ! $schedule_obj instanceof Schedules\Synchronization ) {
					continue;
				}

				// set the new state.
				update_term_meta( $term_id, 'sync', 1 );
			}
		}
	}
}
