<?php
/**
 * This file contains a controller-object to handle the queue for external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\Plugin\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Transients;
use JsonException;
use mysqli_result;
use wpdb;

/**
 * Controller for queue tasks.
 */
class Queue extends Extension_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Queue|null
	 */
	private static ?Queue $instance = null;

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
	 * @return Queue
	 */
	public static function get_instance(): Queue {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// misc.
		add_action( 'admin_action_eml_queue_process', array( $this, 'process_queue_by_request' ) );
		add_action( 'admin_action_eml_queue_clear', array( $this, 'clear_by_request' ) );
		add_action( 'admin_action_eml_queue_clear_errors', array( $this, 'delete_errors_by_request' ) );
		add_action( 'admin_action_eml_queue_delete_entry', array( $this, 'delete_entry_by_request' ) );
		add_action( 'admin_action_eml_queue_process_entry', array( $this, 'process_queue_entry_by_request' ) );

		// use our own hooks.
		add_filter( 'eml_add_dialog', array( $this, 'add_option_in_form' ) );
		add_filter( 'eml_dialog_after_adding', array( $this, 'change_dialog_after_adding' ) );
		add_filter( 'eml_prevent_import', array( $this, 'add_urls_to_queue' ), 10, 4 );
		add_action( 'eml_cli_arguments', array( $this, 'check_cli_arguments' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ) );
	}

	/**
	 * Initialize the settings for this object.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page could not be found.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get the general tab.
		$general_tab = $settings_page->get_tab( 'eml_general' );

		// bail if section could not be loaded.
		if ( ! $general_tab instanceof Tab ) {
			return;
		}

		// add interval setting on main tab.
		$general_tab_main = $general_tab->get_section( 'settings_section_main' );

		// bail if section could not be loaded.
		if ( ! $general_tab_main instanceof Section ) {
			return;
		}

		// create interval setting.
		$setting = $settings_obj->add_setting( 'eml_queue_interval' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'string' );
		$setting->set_default( 'efml_hourly' );
		$field = new Select();
		$field->set_title( __( 'Set interval for queue processing', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines the time interval in which the queue for new URLs will be processed.', 'external-files-in-media-library' ) );
		$field->set_options( Helper::get_intervals() );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$setting->set_save_callback( array( $this, 'update_interval_setting' ) );
		$setting->set_field( $field );

		// add setting for limit.
		$setting = $settings_obj->add_setting( 'eml_queue_limit' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'integer' );
		$setting->set_default( 10 );
		$field = new Number();
		$field->set_title( __( 'To process per queue cycle', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Set the limit of URLs which will be process per cycle.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );

		// add tab for queue table.
		$queue_table_tab = $settings_page->add_tab( 'eml_queue_table', 70 );
		$queue_table_tab->set_title( __( 'Queue', 'external-files-in-media-library' ) );
		$queue_table_tab->set_callback( array( $this, 'show_queue' ) );
	}

	/**
	 * Sanitize the interval setting.
	 *
	 * @param string|null $value The given value.
	 *
	 * @return string
	 */
	public function sanitize_interval_setting( null|string $value ): string {
		// get option.
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		// bail if value is empty.
		if ( empty( $value ) ) {
			add_settings_error( $option, $option, __( 'An interval has to be set.', 'external-files-in-media-library' ) );
			return '';
		}

		// bail if value is 'eml_disable_check'.
		if ( 'eml_disable_check' === $value ) {
			return $value;
		}

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $value ] ) ) {
			/* translators: %1$s will be replaced by the name of the used interval */
			add_settings_error( $option, $option, sprintf( __( 'The given interval %1$s does not exists.', 'external-files-in-media-library' ), esc_html( $value ) ) );
		}

		// return the value.
		return $value;
	}

	/**
	 * Update the schedule if interval has been changed.
	 *
	 * @param string|null $value The given value for the interval.
	 *
	 * @return string
	 */
	public function update_interval_setting( string|null $value ): string {
		// if null is given, use a string.
		$value = Helper::get_as_string( $value );

		// get queue-schedule-object.
		$queue_schedule = new \ExternalFilesInMediaLibrary\Plugin\Schedules\Queue();

		// if new value is 'eml_disable_check' remove the schedule.
		if ( 'eml_disable_check' === $value ) {
			// log event.
			Log::get_instance()->create( __( 'Queue schedule has been disabled.', 'external-files-in-media-library' ), '', 'info', 2 );

			// remove schedule.
			$queue_schedule->delete();
		} else {
			// log event.
			Log::get_instance()->create( __( 'Queue schedule interval has changed.', 'external-files-in-media-library' ), '', 'info', 2 );

			// set the new interval.
			$queue_schedule->set_interval( $value );

			// reset the schedule.
			$queue_schedule->reset();
		}

		// return the new value to save it via WP.
		return $value;
	}

	/**
	 * Install necessary DB-table.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// bail if $wpdb is not.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// set collate.
		$charset_collate = $wpdb->get_charset_collate();

		// table for the queue.
		$sql = 'CREATE TABLE ' . $wpdb->prefix . "eml_queue (
		    `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `url` text DEFAULT '' NOT NULL,
            `login` text DEFAULT '' NOT NULL,
            `password` text DEFAULT '' NOT NULL,
            `state` text DEFAULT '' NOT NULL,
            `options` text DEFAULT '' NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // @phpstan-ignore requireOnce.fileNotFound
		dbDelta( $sql );

		// also run initialisation.
		$this->init();
	}

	/**
	 * Add a URL to the queue.
	 *
	 * @param string $url The URL to add.
	 * @param string $login The login to use.
	 * @param string $password The password to use.
	 *
	 * @return bool
	 */
	private function add_url( string $url, string $login, string $password ): bool {
		global $wpdb;

		// bail if $wpdb is not.
		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		// bail if no URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// bail if given URL is already in queue.
		if ( ! empty( $this->get_url( $url ) ) ) {
			Log::get_instance()->create( __( 'URL is already in queue.', 'external-files-in-media-library' ), $url, 'info' );
			return true;
		}

		$options = array();
		/**
		 * Get the options used for import of this URL.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $options List of options.
		 * @param string $url The used URL.
		 */
		$options = apply_filters( 'eml_import_options', $options, $url );

		// convert options to JSON.
		$options_json = wp_json_encode( $options );
		if ( ! $options_json ) {
			$options_json = '';
		}

		// add the URL of this file to the list.
		$result = $wpdb->insert(
			$wpdb->prefix . 'eml_queue',
			array(
				'time'     => gmdate( 'Y-m-d H:i:s' ),
				'url'      => $url,
				'login'    => ! empty( $login ) ? Crypt::get_instance()->encrypt( $login ) : '',
				'password' => ! empty( $password ) ? Crypt::get_instance()->encrypt( $password ) : '',
				'state'    => 'new',
				'options'  => $options_json,
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, $url );

		// return result of the db-insert-statement.
		return false !== $result;
	}

	/**
	 * Process the queue.
	 *
	 * Import all URLs from queue in media library which have the state "new".
	 *
	 * @return void
	 */
	public function process_queue(): void {
		// get the queue.
		$urls_to_import = $this->get_urls();

		// show progress.
		/* translators: %1$d will be replaced by a number. */
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( _n( 'Processing the import of %1$d URL from queue.', 'Processing the import of %1$d URLs from queue.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), count( $urls_to_import ) ) : '';

		// log event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( 'Processing the import of %1$d URL from queue.', 'Processing the import of %1$d URLs from queue.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), '', 'info', 2 );

		/**
		 * Filter the list of URLs from queue before they are processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from queue.
		 */
		$urls_to_import = apply_filters( 'eml_queue_urls', $urls_to_import );

		/**
		 * Run action before queue is processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from queue which will be processed.
		 */
		do_action( 'eml_queue_before_process', $urls_to_import );

		// loop through the queue.
		foreach ( $urls_to_import as $url_data ) {
			// bail if given url_data is not an array.
			if ( ! is_array( $url_data ) ) {
				continue;
			}

			$this->process_entry( absint( $url_data['id'] ) );

			// show progress.
			$progress ? $progress->tick() : '';
		}

		// show end of process.
		$progress ? $progress->finish() : '';

		/**
		 * Run action after queue is processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from queue which has been processed.
		 */
		do_action( 'eml_queue_after_process', $urls_to_import );

		// log event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( 'Processing the import of %1$d URL from queue ended.', 'Processing the import of %1$d URLs from queue ended.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), '', 'info', 2 );
	}

	/**
	 * Process single entry.
	 *
	 * @param int $id The ID to use.
	 *
	 * @return void
	 */
	private function process_entry( int $id ): void {
		// get the entry.
		$url_data = $this->get_url_by_id( $id );

		// bail if no data could be loaded.
		if ( empty( $url_data ) ) {
			return;
		}

		// get the files object.
		$import = Import::get_instance();

		// set the credentials.
		$import->set_login( Crypt::get_instance()->decrypt( $url_data['login'] ) );
		$import->set_password( Crypt::get_instance()->decrypt( $url_data['password'] ) );

		// set the options, if set.
		if ( ! empty( $url_data['options'] ) ) {
			// get them.
			try {
				$options = json_decode( $url_data['options'], true, 512, JSON_THROW_ON_ERROR );

				// set options in POST-request-array as extension use this for their tasks.
				if ( is_array( $options ) ) {
					foreach ( $options as $key => $value ) {
						$_POST[ $key ] = $value;
					}
				}
			} catch ( JsonException $e ) {
				Log::get_instance()->create( __( 'Error decoding options to import this URL via queue:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $url_data['url'], 'error', 0, Import::get_instance()->get_identified() );
			}
		}

		// import the URL.
		if ( $import->add_url( $url_data['url'] ) ) {
			// remove URL from queue.
			$this->remove_url( absint( $url_data['id'] ) );
		} else {
			// mark URL with state "error" to prevent usage.
			$this->set_url_state( absint( $url_data['id'] ), 'error' );
		}
	}

	/**
	 * Process the queue by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function process_queue_by_request(): void {
		// check the nonce.
		check_admin_referer( 'eml-queue-process', 'nonce' );

		// process the queue.
		$this->process_queue();

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_queue_processed' );
		$transient_obj->set_message( __( 'The queue has been processed.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Process single entry of queue by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function process_queue_entry_by_request(): void {
		// check the nonce.
		check_admin_referer( 'eml-queue-process-entry', 'nonce' );

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// delete the entry if id is given.
		if ( $id > 0 ) {
			// process it.
			$this->process_entry( $id );

			// show ok message.
			$transients_obj = Transients::get_instance();
			$transient_obj  = $transients_obj->add();
			$transient_obj->set_name( 'eml_queue_processed' );
			$transient_obj->set_message( '<strong>' . __( 'The entry has been processed.', 'external-files-in-media-library' ) . '</strong>' );
			$transient_obj->set_type( 'success' );
			$transient_obj->save();
		}

		// forward user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Clear the queue.
	 *
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// truncate the content of the table.
		$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->prefix . 'eml_queue' ) );
	}

	/**
	 * Clear the queue by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function clear_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-clear', 'nonce' );

		// clear the queue.
		$this->clear();

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_queue_cleared' );
		$transient_obj->set_message( __( 'The queue has been cleared.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Delete all error entries by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_errors_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-clear-errors', 'nonce' );

		// get the error entries.
		foreach ( $this->get_urls( 'error' ) as $url_data ) {
			// delete them.
			$this->remove_url( absint( $url_data['id'] ) );
		}

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_queue_cleared' );
		$transient_obj->set_message( __( 'Error entries from queue has been deleted.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Return list of URLs from queue table.
	 *
	 * @param string $state The requested state (new or error).
	 * @param bool   $unlimited Whether to call unlimited list (true) or not (false).
	 *
	 * @return array<int,array<string>>
	 */
	public function get_urls( string $state = 'new', bool $unlimited = false ): array {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return array();
		}

		if ( empty( $state ) ) {
			// run query to unlimited list.
			if ( $unlimited ) {
				// return all URLs for requested state.
				return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` as `date`, `url`, `login`, `password`, `state` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s ORDER BY `time` DESC', array( 1 ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
			}

			// return limited URLs for requested state.
			return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` as `date`, `url`, `login`, `password`, `state` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s ORDER BY `time` DESC LIMIT %d', array( 1, absint( get_option( 'eml_queue_limit' ) ) ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
		}

		// run query to unlimited list.
		if ( $unlimited ) {
			// return all URLs for requested state.
			return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` as `date`, `url`, `login`, `password`, `state` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s AND `state` = %s ORDER BY `time` DESC', array( 1, $state ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
		}

		// get limited URLs for requested state.
		return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` as `date`, `url`, `login`, `password`, `state` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s AND `state` = %s ORDER BY `time` DESC LIMIT %d', array( 1, $state, absint( get_option( 'eml_queue_limit' ) ) ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
	}

	/**
	 * Return data of single URL in queue.
	 *
	 * @param string $url The requested URL.
	 *
	 * @return array<string>
	 */
	private function get_url( string $url ): array {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return array();
		}

		// return the data of the single URL.
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `id`, `url`, `login`, `password` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s AND `url` = %s', array( 1, $url ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
	}

	/**
	 * Return data of single ID in queue.
	 *
	 * @param int $id The ID.
	 *
	 * @return array<string>
	 */
	private function get_url_by_id( int $id ): array {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return array();
		}

		// return the data of the single URL.
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `id`, `url`, `login`, `password`, `options` FROM ' . $wpdb->prefix . 'eml_queue WHERE 1 = %s AND `id` = %d', array( 1, $id ) ), ARRAY_A ) ); // @phpstan-ignore argument.type
	}

	/**
	 * Set entry in queue to given state.
	 *
	 * @param int    $id The ID to use.
	 * @param string $state The state to set.
	 *
	 * @return void
	 */
	private function set_url_state( int $id, string $state ): void {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// get the URL from queue for given ID.
		$url      = '';
		$url_data = $this->get_url( $url );
		if ( ! empty( $url_data ) ) {
			$url = $url_data['url'];
		}

		// update the entry.
		$result = $wpdb->update(
			$wpdb->prefix . 'eml_queue',
			array(
				'state' => $state,
			),
			array(
				'id' => $id,
			),
			array(
				'%s',
			),
			array(
				'%d',
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, $url );
	}

	/**
	 * Remove given URL from queue.
	 *
	 * @param int $id The id to use.
	 *
	 * @return void
	 */
	private function remove_url( int $id ): void {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// get the URL from queue for given ID.
		$url      = '';
		$url_data = $this->get_url( $url );
		if ( ! empty( $url_data ) ) {
			$url = $url_data['url'];
		}

		// update the entry.
		$result = $wpdb->delete(
			$wpdb->prefix . 'eml_queue',
			array(
				'id' => $id,
			),
			array(
				'%d',
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, $url );
	}

	/**
	 * Handling of any DB-errors in this object.
	 *
	 * @param mysqli_result|bool|int|null $result The result.
	 * @param string                      $url The URL.
	 *
	 * @return void
	 */
	private function db_error_handling( mysqli_result|bool|int|null $result, string $url ): void {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// bail if result is not false.
		if ( false !== $result ) {
			return;
		}

		// log this event.
		/* translators: %1$s is replaced by an error-code. */
		Log::get_instance()->create( sprintf( __( 'Database-Error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_json_encode( $wpdb->last_error ) . '</code>' ), $url, 'error' );
	}

	/**
	 * Show queue table in backend.
	 *
	 * @return void
	 */
	public function show_queue(): void {
		// if WP_List_Table is not loaded automatically, we need to load it.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore includeOnce.fileNotFound
		}
		$queue = new \ExternalFilesInMediaLibrary\Plugin\Tables\Queue();
		$queue->prepare_items();
		?>
		<div class="wrap eml-queue-table">
			<h2><?php echo esc_html__( 'Queue', 'external-files-in-media-library' ); ?></h2>
			<?php
			$queue->views();
			$queue->display();
			?>
		</div>
		<?php
	}

	/**
	 * Uninstall the queue table.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// bail if $wpdb is not wpdb.
		if ( ! $wpdb instanceof wpdb ) {
			return;
		}

		// remove the table.
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'eml_queue' ) );
	}

	/**
	 * Delete entry by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_entry_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-delete-entry', 'nonce' );

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// delete the entry if id is given.
		if ( $id > 0 ) {
			$this->remove_url( $id );

			// show ok message.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'success' );
			$transient_obj->set_name( 'eml_queue_entry_deleted' );
			$transient_obj->set_message( '<strong>' . __( 'The queue entry has been deleted.', 'external-files-in-media-library' ) . '</strong>' );
			$transient_obj->save();
		}

		// redirect the user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Add a checkbox to mark the fields to add them to queue.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_in_form( array $dialog ): array {
		// bail if queue is disabled.
		if ( 'eml_disable_check' === get_option( 'eml_queue_interval' ) ) {
			return $dialog;
		}

		// we have no global setting to enable the queue in dialog.
		$checked = false;

		// if user has its own setting, use this.
		if ( 1 === absint( get_user_meta( get_current_user_id(), 'efml_add_to_queue', true ) ) ) {
			$checked = true;
		}

		// collect the entry.
		$text = '<label for="add_to_queue"><input type="checkbox" name="add_to_queue" id="add_to_queue" value="1" class="eml-use-for-import"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Add these URLs to the queue that is processed in the background.', 'external-files-in-media-library' );

		// add link to user settings.
		$url   = add_query_arg(
			array(),
			get_admin_url() . 'profile.php'
		);
		$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';

		// add link to global settings.
		if ( current_user_can( 'manage_options' ) ) {
			$text .= '<a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_advanced' ) ) . '" target="_blank" title="' . esc_attr__( 'Go to plugin settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		// end the text.
		$text .= '</label>';

		// add the field.
		$dialog['texts'][] = $text;

		// return the resulting fields.
		return $dialog;
	}

	/**
	 * Add single URL to queue if request for it is set.
	 *
	 * @param bool   $results The return value to use (true to prevent normal import).
	 * @param string $url     The used URL.
	 * @param string $login   The login to use.
	 * @param string $password The password to use.
	 *
	 * @return bool
	 */
	public function add_urls_to_queue( bool $results, string $url, string $login, string $password ): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		if ( ! isset( $_POST['add_to_queue'] ) ) {
			return $results;
		}

		// create result object.
		$result_obj = new Results\Url_Result();

		// add the URL to the queue.
		if ( ! $this->add_url( $url, $login, $password ) ) {
			// define to result object.
			$result_obj->set_url( $url );
			$result_obj->set_result_text( __( 'Specified URL could not be added to queue.', 'external-files-in-media-library' ) );

			// add result to the list.
			Results::get_instance()->add( $result_obj );

			// log event.
			Log::get_instance()->create( __( 'Specified URL could not be added to queue.', 'external-files-in-media-library' ), esc_url( $url ), 'error' );
		} else {
			// define to result object.
			$result_obj->set_url( $url );
			$result_obj->set_error( false );
			$result_obj->set_result_text( __( 'Specified URL has been added to queue.', 'external-files-in-media-library' ) );

			// add result to the list.
			Results::get_instance()->add( $result_obj );

			// log event.
			Log::get_instance()->create( __( 'Specified URL has been added to queue.', 'external-files-in-media-library' ), esc_url( $url ), 'info', 2 );
		}

		// return true to prevent any further processing.
		return true;
	}

	/**
	 * Change dialog configuration after adding files if queue is not empty.
	 *
	 * @param array<string,mixed> $dialog The dialog configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function change_dialog_after_adding( array $dialog ): array {
		// bail if Queue is empty.
		if ( empty( $this->get_urls() ) ) {
			return $dialog;
		}

		// extend the dialog.
		$dialog['detail']['buttons'][] = array(
			'action'  => 'location.href="' . \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_queue_table' ) . '";',
			'variant' => 'secondary',
			'text'    => __( 'Go to queue', 'external-files-in-media-library' ),
		);

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Check the WP CLI arguments before import of URLs there.
	 *
	 * @param array<string,mixed> $arguments List of WP CLI arguments.
	 *
	 * @return void
	 */
	public function check_cli_arguments( array $arguments ): void {
		// bail if argument is not set.
		if ( ! isset( $arguments['queue'] ) ) {
			return;
		}

		// add the marker.
		$_POST['add_to_queue'] = 1;
	}

	/**
	 * Add option for the user-specific setting.
	 *
	 * @param array<string,array<string,mixed>> $settings List of settings.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_user_setting( array $settings ): array {
		// add our setting.
		$settings['add_to_queue'] = array(
			'label'       => __( 'Add URLs to the queue.', 'external-files-in-media-library' ),
			'description' => __( 'The queue is processed in background.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}
}
