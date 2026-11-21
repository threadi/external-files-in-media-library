<?php
/**
 * This file contains a controller-object to handle the queue for external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Number;
use easySettingsForWordPress\Fields\Select;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Intervals;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use JsonException;
use mysqli_result;
use wpdb;

/**
 * Controller for queue tasks.
 */
class Queue extends Extension_Base {
	/**
	 * The context for the encryption of the fields (access data) of an entry.
	 *
	 * Fields saved before this context has been used stay readable.
	 */
	public const FIELDS_CONTEXT = 'queue_fields';

	/**
	 * Name of the WP-Cron hook, which exports the files in the queue right away (independent of its interval).
	 *
	 * @var string
	 */
	public const EXPORT_HOOK = 'efml_process_export_queue';

	/**
	 * Name of the option, which locks the processing of the exports (time of the start).
	 *
	 * @var string
	 */
	private const EXPORT_LOCK = 'efml_export_queue_lock';

	/**
	 * The version of the structure of the database table.
	 *
	 * 2: ID as BIGINT with primary key, state as VARCHAR, indexes for state and type and for attachment and type.
	 * 3: column "next_attempt" with the time of the next attempt of a failed export or deletion.
	 *
	 * @var string
	 */
	private const DB_VERSION = '3';

	/**
	 * Name of the option with the version of the structure of the database table.
	 *
	 * @var string
	 */
	public const DB_VERSION_OPTION = 'efml_queue_db_version';

	/**
	 * Whether entries are processed by request of the user, also if their next attempt is not due yet.
	 *
	 * @var bool
	 */
	private bool $force = false;

	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'queue';

	/**
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'import_dialog' );

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
		add_action( 'admin_action_efml_create_queue_cron', array( $this, 'add_cron_by_request' ) );

		// use our own hooks.
		add_filter( 'efml_add_dialog', array( $this, 'add_option_in_form' ), 40, 2 );
		add_filter( 'efml_dialog_after_adding', array( $this, 'change_dialog_after_adding' ) );
		add_filter( 'efml_prevent_import', array( $this, 'add_urls_to_queue' ), 100, 3 );
		add_action( 'efml_cli_arguments', array( $this, 'check_cli_arguments' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ) );
		add_filter( 'efml_site_health_endpoints', array( $this, 'add_site_health_endpoint' ) );

		// use WordPress hooks.
		add_action( 'delete_attachment', array( $this, 'remove_export_entries_of_attachment' ) );
		add_action( self::EXPORT_HOOK, array( $this, 'process_exports' ) );
		add_action( 'admin_init', array( $this, 'maybe_install' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Queue', 'external-files-in-media-library' );
	}

	/**
	 * Initialize the settings for this object.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( $settings_obj->get_menu_slug() );

		// bail if page could not be found.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add a tab for queue table as sub-tab of the status tab.
		$queue_table_tab = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->add_sub_tab( 'eml_status', 'eml_queue_table', 20 );

		// bail if tab could not be added.
		if ( ! $queue_table_tab instanceof \easySettingsForWordPress\Tab ) {
			return;
		}
		$queue_table_tab->set_title( __( 'Queue', 'external-files-in-media-library' ) );
		$queue_table_tab->set_callback( array( $this, 'show_queue' ) );

		// add section for queue settings.
		$queue_section = $queue_table_tab->add_section( 'settings_section_queue', 20 );
		$queue_section->set_title( __( 'Settings', 'external-files-in-media-library' ) );

		// create interval setting.
		$queue_interval_setting = $settings_obj->add_setting( 'eml_queue_interval' );
		$queue_interval_setting->set_section( $queue_section );
		$queue_interval_setting->set_type( 'string' );
		$queue_interval_setting->set_default( 'efml_hourly' );
		$field = new Select( $settings_obj );
		$field->set_title( __( 'Set interval for queue processing', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines the time interval in which the queue for new URLs will be processed.', 'external-files-in-media-library' ) );
		$field->set_options( Intervals::get_instance()->get_intervals_for_settings() );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$queue_interval_setting->set_save_callback( array( $this, 'update_interval_setting' ) );
		$queue_interval_setting->set_field( $field );

		// add setting for the limit.
		$setting = $settings_obj->add_setting( 'eml_queue_limit' );
		$setting->set_section( $queue_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 10 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'To process per queue cycle', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Specify the amount of URLs to be processed per cycle.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
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
		$option = str_replace( 'sanitize_option_', '', (string) current_filter() );

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
			add_settings_error( $option, $option, sprintf( __( 'The given interval %1$s does not exist.', 'external-files-in-media-library' ), esc_html( $value ) ) );
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
		} elseif ( $value !== $queue_schedule->get_interval() ) {
			// log event.
			/* translators: %1$s and %2$s will be replaced by intervall names. */
			Log::get_instance()->create( sprintf( __( 'Queue schedule interval has changed from %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . $queue_schedule->get_interval() . '</em>', '<em>' . $value . '</em>' ), '', 'info', 2 );

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
	 * Also updates the structure of existing tables (see DB_VERSION), their entries are kept.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// set collate.
		$charset_collate = $wpdb->get_charset_collate();

		// get the table name.
		$table = $wpdb->prefix . 'eml_queue';

		// change the key of the ID in existing tables, which dbDelta() could not do.
		$this->migrate_primary_key( $table );

		// table for the queue.
		$sql = 'CREATE TABLE ' . $table . " (
		    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
            `url` text NOT NULL,
            `fields` text NOT NULL,
            `state` varchar(20) DEFAULT 'new' NOT NULL,
            `options` text NOT NULL,
            `type` varchar(20) DEFAULT 'import' NOT NULL,
            `attachment_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
            `attempts` smallint(5) unsigned DEFAULT 0 NOT NULL,
            `url_hash` char(32) DEFAULT '' NOT NULL,
            `run_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
            `next_attempt` bigint(20) unsigned DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY url_hash (url_hash),
            KEY run_id (run_id),
            KEY state_type (state,type),
            KEY attachment_type (attachment_id,type)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // @phpstan-ignore requireOnce.fileNotFound
		dbDelta( $sql );

		// add the hash of the URL to existing entries.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `url_hash` = MD5(`url`) WHERE `url_hash` = %s', array( $table, '' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// save the version of the structure.
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		// also run initialization.
		$this->init();
	}

	/**
	 * Add a URL to the queue, e.g. the rest of an import, which has been terminated.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string                            $url The URL to add.
	 * @param array<string,array<string,mixed>> $fields List of fields.
	 *
	 * @return bool
	 */
	public function add( string $url, array $fields ): bool {
		return $this->add_url( $url, $fields );
	}

	/**
	 * Add a URL to the queue.
	 *
	 * @param string                            $url The URL to add.
	 * @param array<string,array<string,mixed>> $fields List of fields.
	 *
	 * @return bool
	 */
	private function add_url( string $url, array $fields ): bool {
		global $wpdb;

		// bail if no URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// bail if given URL is already in the queue.
		if ( ! empty( $this->get_url( $url ) ) ) {
			Log::get_instance()->create( __( 'URL is already in the queue.', 'external-files-in-media-library' ), $url, 'error', 0, Import::get_instance()->get_identifier() );
			return true;
		}

		// show deprecated hint for the old hook.
		$options = apply_filters_deprecated( 'eml_import_options', array( array(), $url ), '5.0.0', 'efml_import_options' );

		/**
		 * Filter the options used for import of this URL via queue.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $options List of options.
		 * @param string $url The used URL.
		 */
		$options = apply_filters( 'efml_import_options', $options, $url );

		// convert options to JSON.
		$options_json = wp_json_encode( $options );
		if ( ! $options_json ) {
			$options_json = '';
		}

		// add the URL of this file to the list.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'eml_queue',
			array(
				'time'     => gmdate( 'Y-m-d H:i:s' ),
				'url'      => $url,
				'url_hash' => md5( $url ),
				'fields'   => ! empty( $fields ) ? Crypt::get_instance()->encrypt( Helper::get_json( $fields ), self::FIELDS_CONTEXT ) : '',
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
	 * Import all URLs from queue in media library, which have the state "new".
	 *
	 * Further attempts of failed exports and deletions are processed at their time via process_exports(), unless
	 * the processing is forced (e.g. by request of the user).
	 *
	 * @param bool $force True to process also the further attempts, which are not due yet.
	 *
	 * @return void
	 */
	public function process_queue( bool $force = false ): void {
		// remember whether the processing is forced.
		$this->force = $force;

		// make sure the structure of the table is actual (e.g. in WP-Cron before the backend has been visited).
		$this->maybe_install();

		// get the queue, without the entries of import runs (they are processed by the runs).
		$urls_to_import = $this->get_urls( 'new', false, false );

		// show progress.
		/* translators: %1$d will be replaced by a number. */
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( _n( 'Processing the import of %1$d URL from the queue.', 'Processing the import of %1$d URLs from the queue.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), count( $urls_to_import ) ) : '';

		// log event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( 'Processing the import of %1$d URL from the queue.', 'Processing the import of %1$d URLs from the queue.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), '', 'info', 2 );

		// show deprecated hint for the old hook.
		$urls_to_import = apply_filters_deprecated( 'eml_queue_urls', array( $urls_to_import ), '5.0.0', 'efml_queue_urls' );

		/**
		 * Filter the list of URLs from the queue before they are processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from the queue.
		 */
		$urls_to_import = apply_filters( 'efml_queue_urls', $urls_to_import );

		// show deprecated hint for the old hook.
		do_action_deprecated( 'eml_queue_before_process', array( $urls_to_import ), '5.0.0', 'efml_queue_before_process' );

		/**
		 * Run action before the queue is processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from the queue, which will be processed.
		 */
		do_action( 'efml_queue_before_process', $urls_to_import );

		// loop through the queue.
		foreach ( $urls_to_import as $url_data ) {
			// bail if given "url_data" is not an array.
			if ( ! is_array( $url_data ) ) {
				continue;
			}

			$this->process_entry( absint( $url_data['id'] ) );

			// show progress.
			if ( $progress ) {
				$progress->tick(); }
		}

		// show end of process.
		if ( $progress ) {
			$progress->finish(); }

		// show deprecated hint for the old hook.
		do_action_deprecated( 'eml_queue_after_process', array( $urls_to_import ), '5.0.0', 'efml_queue_after_process' );

		/**
		 * Run action after the queue is processed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $urls_to_import List of URLs to import from the queue, which has been processed.
		 */
		do_action( 'efml_queue_after_process', $urls_to_import );

		// log event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( 'The processing of the import of %1$d URL from the queue is complete.', 'The processing of the import of %1$d URLs from the queue is complete.', count( $urls_to_import ), 'external-files-in-media-library' ), count( $urls_to_import ) ), '', 'info', 2 );

		// process the import runs too, e.g. if WP Cron did not run their own event.
		Import_Runs::get_instance()->process();

		// the next processing is not forced.
		$this->force = false;
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

		// process an entry of an import run (incl. the export of local files) with the settings of the run.
		if ( absint( $url_data['run_id'] ) > 0 ) {
			Import_Runs::get_instance()->process_entry_by_id( $id );
			return;
		}

		// bail if this is a further attempt of an export or deletion, which is not due yet: it is processed at its
		// time via process_exports(), unless the user requests it.
		if ( ! empty( $url_data['type'] ) && in_array( $url_data['type'], array( 'export', 'delete' ), true ) && ! $this->force && $this->get_next_attempt_of_entry( $id ) > time() ) {
			return;
		}

		// process an export entry.
		if ( ! empty( $url_data['type'] ) && 'export' === $url_data['type'] ) {
			$this->process_export_entry( $url_data );
			return;
		}

		// process an entry to delete exported files.
		if ( ! empty( $url_data['type'] ) && 'delete' === $url_data['type'] ) {
			$this->process_deletion_entry( $url_data );
			return;
		}

		// get the files object.
		$import = Import::get_instance();

		// get the fields. Entries without credentials are saved with an empty string, which results in an empty list.
		$fields = array();
		if ( ! empty( $url_data['fields'] ) && is_string( $url_data['fields'] ) ) { // @phpstan-ignore booleanAnd.rightAlwaysTrue
			$fields = json_decode( Crypt::get_instance()->decrypt( $url_data['fields'], self::FIELDS_CONTEXT ), true );
			if ( ! is_array( $fields ) ) {
				/* translators: %1$d will be replaced by the ID of the entry. */
				Log::get_instance()->create( sprintf( __( 'The saved access data of the entry %1$d in the queue could not be read. The URL is imported without access data.', 'external-files-in-media-library' ), absint( $url_data['id'] ?? 0 ) ), (string) $url_data['url'], 'error' );
				$fields = array();
			}
		}

		// set the fields.
		$import->set_fields( $fields );

		// remember the POST-values we change for this entry to restore them afterwards.
		$original_post_values = array();

		// set the options, if set.
		if ( ! empty( $url_data['options'] ) ) {
			// get them.
			try {
				$options = json_decode( $url_data['options'], true, 512, JSON_THROW_ON_ERROR );

				// set options in POST-request-array as extension use this for their tasks.
				if ( is_array( $options ) ) {
					foreach ( $options as $key => $value ) {
						$original_post_values[ $key ] = array_key_exists( $key, $_POST ) ? array( $_POST[ $key ] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Only saved to restore the value after the import.
						$_POST[ $key ]                = $value;
					}
				}
			} catch ( JsonException $e ) {
				Log::get_instance()->create( __( 'Error decoding options to import this URL via queue:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url_data['url'], 'error', 0, Import::get_instance()->get_identifier() );
			}
		}

		// import the URL.
		try {
			if ( $import->add_url( $url_data['url'] ) ) {
				// remove URL from queue.
				$this->remove_url( absint( $url_data['id'] ) );
			} else {
				// mark URL with state "error" to prevent usage.
				$this->set_url_state( absint( $url_data['id'] ), 'error' );
			}
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the import of this URL via queue:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url_data['url'], 'error', 0, Import::get_instance()->get_identifier() );

			// mark URL with state "error" to prevent that this entry blocks the queue.
			$this->set_url_state( absint( $url_data['id'] ), 'error' );
		} finally {
			// restore the POST-values to prevent that options of this entry are used for the next entry.
			foreach ( $original_post_values as $key => $value ) {
				if ( is_array( $value ) ) {
					$_POST[ $key ] = $value[0];
					continue;
				}
				unset( $_POST[ $key ] );
			}
		}
	}

	/**
	 * Return whether the actual user is allowed to use the actions of the queue (process, clear, delete entries).
	 *
	 * The queue is part of the settings and contains also entries of exports and deletions on external sources,
	 * therefore the capability for the settings is required in addition to the capability for external files.
	 *
	 * @return bool
	 */
	public function is_queue_action_allowed(): bool {
		return current_user_can( EFML_CAP_NAME ) && current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() );
	}

	/**
	 * Process the queue by request.
	 *
	 * @return void
	 */
	public function process_queue_by_request(): void {
		// check the nonce.
		check_admin_referer( 'eml-queue-process', 'nonce' );

		// bail if user has not the capability (the queue is part of the settings).
		if ( ! $this->is_queue_action_allowed() ) {
			return;
		}

		// process the queue, also the further attempts of failed exports, which are not due yet.
		$this->process_queue( true );

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
	 */
	public function process_queue_entry_by_request(): void {
		// check the nonce.
		check_admin_referer( 'eml-queue-process-entry', 'nonce' );

		// bail if user has not the capability (the queue is part of the settings).
		if ( ! $this->is_queue_action_allowed() ) {
			return;
		}

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// delete the entry if ID is given.
		if ( $id > 0 ) {
			// make sure the structure of the table is actual.
			$this->maybe_install();

			// process it, also if its next attempt is not due yet.
			$this->force = true;
			$this->process_entry( $id );
			$this->force = false;

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

		// truncate the content of the table.
		$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->prefix . 'eml_queue' ) );

		// cancel the import runs, as their entries are deleted.
		Import_Runs::get_instance()->cancel_all();

		// remove the markers for planned exports.
		delete_post_meta_by_key( 'efml_export_queued' );
	}

	/**
	 * Clear the queue by request.
	 *
	 * @return void
	 */
	public function clear_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-clear', 'nonce' );

		// bail if user has not the capability (the queue is part of the settings).
		if ( ! $this->is_queue_action_allowed() ) {
			return;
		}

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
	 */
	public function delete_errors_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-clear-errors', 'nonce' );

		// bail if user has not the capability (the queue is part of the settings).
		if ( ! $this->is_queue_action_allowed() ) {
			return;
		}

		// delete the error entries.
		$this->clear_error_entries();

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_queue_cleared' );
		$transient_obj->set_message( __( 'Error entries from the queue has been deleted.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward the user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Return list of URLs from queue table.
	 *
	 * @param string $state The requested state (new or error).
	 * @param bool   $unlimited Whether to call unlimited list (true) or not (false).
	 * @param bool   $with_runs Whether to return the entries of import runs (true) or not (false).
	 *
	 * @return array<int,array<string>>
	 */
	public function get_urls( string $state = 'new', bool $unlimited = false, bool $with_runs = true ): array {
		global $wpdb;

		// collect the conditions.
		$where = array( '1 = 1' );
		$args  = array( $wpdb->prefix . 'eml_queue' );
		if ( ! empty( $state ) ) {
			$where[] = '`state` = %s';
			$args[]  = $state;
		}
		if ( ! $with_runs ) {
			$where[] = '`run_id` = 0';
		}

		// add the limit.
		$limit = '';
		if ( ! $unlimited ) {
			$limit  = ' LIMIT %d';
			$args[] = absint( get_option( 'eml_queue_limit' ) );
		}

		// return the entries.
		return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` AS `date`, `url`, `fields`, `state`, `type`, `attachment_id`, `attempts`, `run_id` FROM %i WHERE ' . implode( ' AND ', $where ) . ' ORDER BY `time` DESC, `id` DESC' . $limit, $args ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only static placeholders, all values passed via prepare().
	}

	/**
	 * Return one page of the entries in the queue, e.g. for the queue table.
	 *
	 * @param string $state  The requested state (empty for all).
	 * @param int    $offset The offset.
	 * @param int    $limit  The count of entries.
	 *
	 * @return array<int,array<string>>
	 */
	public function get_urls_page( string $state, int $offset, int $limit ): array {
		global $wpdb;

		// return the page with the requested state.
		if ( ! empty( $state ) ) {
			return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` AS `date`, `url`, `fields`, `state`, `type`, `attachment_id`, `attempts`, `run_id` FROM %i WHERE `state` = %s ORDER BY `time` DESC, `id` DESC LIMIT %d, %d', array( $wpdb->prefix . 'eml_queue', $state, $offset, $limit ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// return the page of all entries.
		return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `time` AS `date`, `url`, `fields`, `state`, `type`, `attachment_id`, `attempts`, `run_id` FROM %i ORDER BY `time` DESC, `id` DESC LIMIT %d, %d', array( $wpdb->prefix . 'eml_queue', $offset, $limit ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return the count of entries in the queue.
	 *
	 * @param string $state The requested state (empty for all).
	 *
	 * @return int
	 */
	public function count_urls( string $state = '' ): int {
		global $wpdb;

		// return the count of entries with the requested state.
		if ( ! empty( $state ) ) {
			return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `state` = %s', array( $wpdb->prefix . 'eml_queue', $state ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// return the count of all entries.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', array( $wpdb->prefix . 'eml_queue' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return data of single URL in the queue.
	 *
	 * @param string $url The requested URL.
	 *
	 * @return array<string,string>
	 */
	private function get_url( string $url ): array {
		global $wpdb;
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `id`, `url`, `fields` FROM %i WHERE `url_hash` = %s AND `url` = %s AND `type` = %s AND `run_id` = 0', array( $wpdb->prefix . 'eml_queue', md5( $url ), $url, 'import' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return data of single ID in the queue.
	 *
	 * @param int $id The ID.
	 *
	 * @return array<string,string>
	 */
	private function get_url_by_id( int $id ): array {
		global $wpdb;
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `id`, `url`, `fields`, `options`, `type`, `attachment_id`, `attempts`, `run_id` FROM %i WHERE `id` = %d', array( $wpdb->prefix . 'eml_queue', $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Set entry in the queue to given state.
	 *
	 * @param int    $id The ID to use.
	 * @param string $state The state to set.
	 *
	 * @return void
	 */
	private function set_url_state( int $id, string $state ): void {
		global $wpdb;

		// get the URL from queue for given ID.
		$url      = '';
		$url_data = $this->get_url_by_id( $id );
		if ( ! empty( $url_data ) ) {
			$url = $url_data['url'];
		}

		// update the entry.
		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
	 * @param int $id The ID to use.
	 *
	 * @return void
	 */
	private function remove_url( int $id ): void {
		global $wpdb;

		// get the URL from queue for given ID.
		$url      = '';
		$url_data = $this->get_url_by_id( $id );
		if ( ! empty( $url_data ) ) {
			$url = $url_data['url'];

			// remove the marker for a planned export on the attachment.
			if ( ! empty( $url_data['type'] ) && 'export' === $url_data['type'] ) {
				delete_post_meta( absint( $url_data['attachment_id'] ), 'efml_export_queued' );
			}
		}

		// update the entry.
		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// bail if result is not false.
		if ( false !== $result ) {
			return;
		}

		// log this event.
		/* translators: %1$s is replaced by an error-code. */
		Log::get_instance()->create( sprintf( __( 'Database-Error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_json_encode( $wpdb->last_error ) . '</code>' ), $url, 'error' );
	}

	/**
	 * Show queue table in the backend.
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
		<h2><?php echo esc_html__( 'Queue', 'external-files-in-media-library' ); ?></h2>
		<div class="wrap eml-queue-table">
			<?php
			$queue->views();
			$queue->display();
			?>
		</div>
		<?php
	}

	/**
	 * Update the structure of the database table, if it is outdated (e.g. if the files of the plugin have been
	 * replaced without running its update).
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		// bail if the structure is actual.
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		// bail if the table does not exist (it is installed during the activation).
		if ( ! Helper::is_table_available( 'eml_queue', false ) ) {
			return;
		}

		// update it.
		$this->install();
	}

	/**
	 * Change the unique key of the ID to the primary key in an existing table of older versions.
	 *
	 * Older versions used "mediumint(9)" with a unique key, which allows only about 8.4 million entries during the
	 * lifetime of the website. dbDelta() could change the type of a column, but not the unique key to a primary
	 * key, so this is done in one step before (an auto increment column always needs a key).
	 *
	 * @param string $table The name of the table.
	 *
	 * @return void
	 */
	private function migrate_primary_key( string $table ): void {
		global $wpdb;

		// bail if the table does not exist.
		if ( ! Helper::is_table_available( 'eml_queue', false ) ) {
			return;
		}

		// get the keys of the table.
		$keys = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ), ARRAY_A ), 'Key_name' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// bail if it has a primary key already or not the unique key of older versions.
		if ( in_array( 'PRIMARY', $keys, true ) || ! in_array( 'id', $keys, true ) ) {
			return;
		}

		// change it.
		$result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT, DROP INDEX `id`, ADD PRIMARY KEY (`id`)', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// log an error.
		if ( false === $result ) {
			/* translators: %1$s will be replaced by an error message. */
			Log::get_instance()->create( sprintf( __( 'The structure of the queue table could not be updated: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $wpdb->last_error ) . '</code>' ), '', 'error' );
		}
	}

	/**
	 * Uninstall the queue table.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// remove the table.
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'eml_queue' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// remove the version of its structure.
		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Delete entry by request.
	 *
	 * @return void
	 */
	public function delete_entry_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-queue-delete-entry', 'nonce' );

		// bail if user has not the capability (the queue is part of the settings).
		if ( ! $this->is_queue_action_allowed() ) {
			return;
		}

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// delete the entry if ID is given.
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
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_in_form( array $dialog, array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// bail if queue is disabled.
		if ( 'eml_disable_check' === get_option( 'eml_queue_interval' ) ) {
			return $dialog;
		}

		// bail if the used service is "zip".
		if ( ! empty( $settings['service'] ) && 'zip' === $settings['service'] ) {
			return $dialog;
		}

		// we have no global setting to enable the queue in the dialog.
		$checked = false;

		// if user has its own setting, use this.
		if ( 1 === absint( get_user_meta( get_current_user_id(), 'efml_' . $this->get_name(), true ) ) ) {
			$checked = true;
		}

		// detect count of URLs depending on a slash at the end of the given URL.
		$url_count = 1;
		if ( ! empty( $settings['urls'] ) && str_ends_with( $settings['urls'], '/' ) ) {
			$url_count = 2;
		}

		// collect the entry.
		$text = '<label for="add_to_queue"><input type="checkbox" name="add_to_queue" id="add_to_queue" value="1" class="eml-use-for-import"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . _n( 'Add this URL to the queue, which will be processed in the background.', 'Add these URLs to the queue, which will be processed in the background.', $url_count, 'external-files-in-media-library' );

		// add link to user settings.
		if ( ImportDialog::get_instance()->is_customization_allowed() ) {
			$url   = add_query_arg(
				array(),
				get_admin_url() . 'profile.php'
			);
			$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';
		}

		// add link to global settings.
		if ( current_user_can( 'manage_options' ) ) {
			$text .= '<a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_general' ) ) . '" target="_blank" title="' . esc_attr__( 'Go to plugin settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
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
	 * @param bool                              $results The return value to use (true to prevent normal import).
	 * @param string                            $url The URL to use.
	 * @param array<string,array<string,mixed>> $fields  List of fields necessary for the connection.
	 *
	 * @return bool
	 */
	public function add_urls_to_queue( bool $results, string $url, array $fields ): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		if ( ! isset( $_POST['add_to_queue'] ) ) {
			return $results;
		}

		// create result object.
		$result_obj = new Results\Url_Result();
		$result_obj->set_url( $url );

		// add the URL to the queue.
		if ( ! $this->add_url( $url, $fields ) ) {
			// define to result object.
			$result_obj->set_result_text( __( 'Specified URL could not be added to queue.', 'external-files-in-media-library' ) );

			// add result to the list.
			Results::get_instance()->add( $result_obj );

			// log event.
			Log::get_instance()->create( __( 'Specified URL could not be added to queue.', 'external-files-in-media-library' ), esc_url( $url ), 'error' );
		} else {
			// define to result object.
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
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $settings;
		}

		// add our setting.
		$settings[ $this->get_name() ] = array(
			'label'       => __( 'Add URLs to the queue', 'external-files-in-media-library' ),
			'description' => __( 'If enabled new files will be imported with the queue in background.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}

	/**
	 * Return whether the queue is processed in the background (if its schedule is enabled).
	 *
	 * @return bool
	 */
	public function is_processing_enabled(): bool {
		return 'eml_disable_check' !== get_option( 'eml_queue_interval' );
	}

	/**
	 * Add an attachment to the queue for its export to the external sources.
	 *
	 * The file stays a local file in the media library until it has been exported.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $delay         The delay in seconds before its export.
	 *
	 * @return bool True if the attachment is in the queue.
	 */
	public function add_export( int $attachment_id, int $delay = 0 ): bool {
		global $wpdb;

		// bail if no ID is given.
		if ( 0 === $attachment_id ) {
			return false;
		}

		// make sure the structure of the table is actual.
		$this->maybe_install();

		// get the local URL of this file for the display in the queue.
		$url = (string) wp_get_attachment_url( $attachment_id );

		// bail if the file is not exported at all (e.g. its type is not allowed in the settings).
		if ( ! Export::get_instance()->is_exportable( $attachment_id ) ) {
			Log::get_instance()->create( __( 'File has not been added to the queue, as it is not exported (e.g. its type is not allowed in the settings).', 'external-files-in-media-library' ), $url, 'info', 2 );
			return false;
		}

		// bail if this attachment is already in the queue for export.
		if ( ! empty( $this->get_export_entry( $attachment_id ) ) ) {
			Log::get_instance()->create( __( 'File is already in the queue for the export.', 'external-files-in-media-library' ), $url, 'info', 2 );
			return true;
		}

		// add the entry.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'eml_queue',
			array(
				'time'          => gmdate( 'Y-m-d H:i:s' ),
				'url'           => $url,
				'fields'        => '',
				'state'         => 'new',
				'options'       => '',
				'type'          => 'export',
				'attachment_id' => $attachment_id,
				'attempts'      => 0,
				'next_attempt'  => $delay > 0 ? time() + $delay : 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
		);

		// use error-handling.
		$this->db_error_handling( $result, $url );

		// bail on error.
		if ( false === $result ) {
			return false;
		}

		// mark the attachment as planned for export.
		update_post_meta( $attachment_id, 'efml_export_queued', time() );
		delete_post_meta( $attachment_id, 'efml_export_failed' );

		// log this event.
		Log::get_instance()->create( __( 'File has been added to the queue for the export.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// export it in the background right away.
		$this->schedule_exports( $delay );

		// return true as the entry has been added.
		return true;
	}

	/**
	 * Plan the export of the files in the queue in the background, if not planned yet.
	 *
	 * An event, which is planned later, is complemented by an earlier one. WP-Cron does not plan an event within
	 * 10 minutes of an event with the same hook, so the earlier time could be a few minutes later.
	 *
	 * @param int $delay The delay in seconds.
	 *
	 * @return void
	 */
	public function schedule_exports( int $delay = 0 ): void {
		// get the requested time.
		$time = time() + max( 0, $delay );

		// get the next planned event.
		$next = wp_next_scheduled( self::EXPORT_HOOK );

		// bail if an event is planned already, which is not much later.
		if ( false !== $next && $next <= $time + 10 * MINUTE_IN_SECONDS ) {
			return;
		}

		// plan it.
		wp_schedule_single_event( $time, self::EXPORT_HOOK );
	}

	/**
	 * Return whether files wait in the queue for their first export attempt.
	 *
	 * @return bool
	 */
	public function has_new_exports(): bool {
		global $wpdb;

		// bail if the table does not exist (yet).
		if ( ! Helper::is_table_available( 'eml_queue' ) ) {
			return false;
		}

		// return whether such entries exist.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `type` = %s AND `state` = %s AND `run_id` = %d AND `attempts` = %d', array( $wpdb->prefix . 'eml_queue', 'export', 'new', 0, 0 ) ) ) ) > 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return the types of the entries, which are processed via process_exports() at the moment.
	 *
	 * Exports only if the export is enabled, deletions always (exported files are deleted everywhere).
	 *
	 * @return array<int,string>
	 */
	private function get_types_of_exports(): array {
		// deletions are always processed.
		$types = array( 'delete' );

		// exports only, if the export is enabled.
		if ( 1 === absint( get_option( 'eml_export' ) ) ) {
			$types[] = 'export';
		}

		// return the list.
		return $types;
	}

	/**
	 * Return the time of the next attempt of the waiting exports and deletions in the queue.
	 *
	 * Returns 0 if no entry waits, otherwise the earliest time (which could be in the past, if entries are due).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return int
	 */
	public function get_next_attempt(): int {
		global $wpdb;

		// bail if the table does not exist (yet) or its structure is outdated.
		if ( ! Helper::is_table_available( 'eml_queue' ) || self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			return 0;
		}

		// get the types.
		$types = $this->get_types_of_exports();

		// get the earliest time.
		$result = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS `entries`, MIN(`next_attempt`) AS `next` FROM %i WHERE `type` IN (' . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . ') AND `state` = %s AND `run_id` = %d', array_merge( array( $wpdb->prefix . 'eml_queue' ), $types, array( 'new', 0 ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// bail if no entry waits.
		if ( ! is_array( $result ) || 0 === absint( $result['entries'] ?? 0 ) ) {
			return 0;
		}

		// return the time, at least 1 for due entries without a time (e.g. of older versions).
		return max( 1, absint( $result['next'] ?? 0 ) );
	}

	/**
	 * Plan the processing of the waiting exports and deletions at the time of the next attempt.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return void
	 */
	public function schedule_waiting_exports(): void {
		// get the time of the next attempt.
		$next = $this->get_next_attempt();

		// bail if no entry waits.
		if ( 0 === $next ) {
			return;
		}

		// plan it.
		$this->schedule_exports( max( 0, $next - time() ) );
	}

	/**
	 * Return the wait time in seconds before the next attempt after the given number of failed attempts.
	 *
	 * The wait time grows with each attempt: 5 minutes, 15 minutes, 1 hour, then 4 hours.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int    $attempts The number of failed attempts (at least 1).
	 * @param string $type     The type of the entry ("export" or "delete").
	 *
	 * @return int
	 */
	public function get_retry_delay( int $attempts, string $type ): int {
		$delays = array( 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, 4 * HOUR_IN_SECONDS );

		/**
		 * Filter the wait times in seconds before the further attempts of failed exports and deletions in the queue.
		 *
		 * The first value is used after the first failed attempt, the second after the second and so on. The last
		 * value is used for all further attempts. The number of attempts is set via the hooks
		 * "efml_export_queue_max_attempts" and "efml_deletion_queue_max_attempts".
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,int> $delays List of wait times in seconds.
		 * @param string         $type   The type of the entry ("export" or "delete").
		 */
		$delays = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'efml_queue_retry_delays', $delays, $type ) ) ) );

		// use 5 minutes, if the list is empty.
		if ( empty( $delays ) ) {
			return 5 * MINUTE_IN_SECONDS;
		}

		// return the wait time for this attempt.
		return $delays[ min( max( 1, $attempts ), count( $delays ) ) - 1 ];
	}

	/**
	 * Set the time of the next attempt of the given entry and plan the processing.
	 *
	 * @param int $id    The ID of the entry.
	 * @param int $delay The wait time in seconds.
	 *
	 * @return void
	 */
	private function set_next_attempt( int $id, int $delay ): void {
		global $wpdb;

		// update the entry.
		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'eml_queue',
			array(
				'next_attempt' => time() + $delay,
			),
			array(
				'id' => $id,
			),
			array(
				'%d',
			),
			array(
				'%d',
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, '' );

		// plan the processing.
		$this->schedule_exports( $delay );
	}

	/**
	 * Return the time of the next attempt of the given entry (0 if it is due right away).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $id The ID of the entry.
	 *
	 * @return int
	 */
	public function get_next_attempt_of_entry( int $id ): int {
		global $wpdb;

		// bail if the structure of the table is outdated (the entry is due then).
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			return 0;
		}

		// return the time.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT `next_attempt` FROM %i WHERE `id` = %d', array( $wpdb->prefix . 'eml_queue', $id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Export the files in the queue and delete exported files of deleted attachments, the oldest first (via WP-Cron).
	 *
	 * Processes new entries right away and further attempts of failed entries at their time, which grows with each
	 * attempt (see get_retry_delay()), independent of the interval of the queue. Runs until the time limit of the
	 * request, then the next request continues. Afterward, the next processing is planned at the time of the next
	 * attempt.
	 *
	 * @return void
	 */
	public function process_exports(): void {
		global $wpdb;

		// bail if this website is a copy: the entries stay in the queue.
		if ( Site_Copy::get_instance()->is_copy() ) {
			return;
		}

		// make sure the structure of the table is actual (e.g. in WP-Cron before the backend has been visited).
		$this->maybe_install();

		// bail if the structure could not be updated.
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}

		// bail if another request processes the exports (the lock expires after 10 minutes, e.g. after a timeout).
		if ( ! add_option( self::EXPORT_LOCK, time(), '', false ) ) {
			if ( time() - absint( get_option( self::EXPORT_LOCK ) ) < 10 * MINUTE_IN_SECONDS ) {
				return;
			}
			update_option( self::EXPORT_LOCK, time(), false );
		}

		// get the time limit.
		$deadline = Import_Runs::get_instance()->get_deadline();

		// get the types to process (exports only, if the export is enabled).
		$types = $this->get_types_of_exports();

		// process the due entries one by one, the oldest first.
		$last_id  = 0;
		$finished = false;
		while ( microtime( true ) < $deadline ) {
			// get the next entry.
			$id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT `id` FROM %i WHERE `type` IN (' . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . ') AND `state` = %s AND `run_id` = %d AND `next_attempt` <= %d AND `id` > %d ORDER BY `id` ASC LIMIT 1', array_merge( array( $wpdb->prefix . 'eml_queue' ), $types, array( 'new', 0, time(), $last_id ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			// bail if no entry is left.
			if ( 0 === $id ) {
				$finished = true;
				break;
			}
			$last_id = $id;

			// process it.
			$url_data = $this->get_url_by_id( $id );
			if ( empty( $url_data ) ) {
				continue;
			}
			if ( 'delete' === $url_data['type'] ) {
				$this->process_deletion_entry( $url_data );
				continue;
			}
			$this->process_export_entry( $url_data );
		}

		// remove the lock.
		delete_option( self::EXPORT_LOCK );

		// continue in the next request, if entries are left.
		if ( ! $finished ) {
			$this->schedule_exports();
			return;
		}

		// plan the processing of the further attempts at their time.
		$this->schedule_waiting_exports();
	}

	/**
	 * Return whether the given attachment waits in the queue for its export.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function has_export_entry( int $attachment_id ): bool {
		// bail if the table does not exist (yet).
		if ( ! Helper::is_table_available( 'eml_queue' ) ) {
			return false;
		}

		// return whether an open entry exists.
		return ! empty( $this->get_export_entry( $attachment_id ) );
	}

	/**
	 * Return the open export entry for the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string>
	 */
	private function get_export_entry( int $attachment_id ): array {
		global $wpdb;
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `id`, `url`, `state`, `attempts` FROM %i WHERE `type` = %s AND `attachment_id` = %d AND `state` = %s', array( $wpdb->prefix . 'eml_queue', 'export', $attachment_id, 'new' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Process a single export entry of the queue.
	 *
	 * If the export fails, it is tried again during the next run of the queue. After the maximum number
	 * of attempts, the entry gets the state "error" and the attachment is marked as not exported.
	 *
	 * @param array<string,string> $url_data The entry data.
	 *
	 * @return void
	 */
	private function process_export_entry( array $url_data ): void {
		// get the entry ID.
		$id = absint( $url_data['id'] );

		// get the attachment ID.
		$attachment_id = absint( $url_data['attachment_id'] );

		// bail if the attachment does not exist anymore.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			$this->remove_url( $id );
			return;
		}

		// bail if the export is disabled in the meantime (the entry stays in the queue).
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			Log::get_instance()->create( __( 'Export of file from the queue has been skipped, as the export is disabled.', 'external-files-in-media-library' ), $url_data['url'], 'info', 2 );
			return;
		}

		// bail if this website is a copy (e.g. a staging site), the entry stays in the queue until this is the main website.
		if ( Site_Copy::get_instance()->is_copy() ) {
			Log::get_instance()->create( __( 'Export of file from the queue has been skipped, as this website is a copy of another website.', 'external-files-in-media-library' ), $url_data['url'], 'info', 2 );
			return;
		}

		// bail if the file is already an external file (e.g., exported by a manual request).
		if ( Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			$this->remove_url( $id );
			return;
		}

		// remove the entry, if the file is not exported at all (e.g. its type is not allowed in the settings).
		if ( ! Export::get_instance()->is_exportable( (int) $attachment_id ) ) {
			Log::get_instance()->create( __( 'Export of file from the queue has been skipped, as its type is not allowed in the settings or its export is prevented. It stays a local file.', 'external-files-in-media-library' ), $url_data['url'], 'info', 1 );
			$this->remove_url( (int) $id );
			delete_post_meta( (int) $attachment_id, 'efml_export_queued' );
			return;
		}

		// export the file.
		try {
			$result = Export::get_instance()->export_file( $attachment_id );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the export of this file via queue:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url_data['url'], 'error' );

			// mark as failed.
			$result = false;
		}

		// remove the entry if the export was successfully.
		if ( $result ) {
			$this->remove_url( $id );
			return;
		}

		// count this attempt.
		$attempts = absint( $url_data['attempts'] ) + 1;
		$this->set_attempts( $id, $attempts );

		/**
		 * Filter the maximum number of attempts to export a file via the queue.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $max_attempts The maximum number of attempts.
		 * @param int $attachment_id The attachment ID.
		 */
		$max_attempts = absint( apply_filters( 'efml_export_queue_max_attempts', 3, $attachment_id ) );

		// try again later, if the maximum is not reached: the wait time grows with each attempt.
		if ( $attempts < $max_attempts ) {
			// get the wait time.
			$delay = $this->get_retry_delay( $attempts, 'export' );

			// set the time of the next attempt.
			$this->set_next_attempt( $id, $delay );

			/* translators: %1$d and %2$d will be replaced by numbers, %3$d by a number of minutes. */
			Log::get_instance()->create( sprintf( __( 'Export of file via queue failed (attempt %1$d of %2$d). It will be tried again in %3$d minutes.', 'external-files-in-media-library' ), $attempts, $max_attempts, (int) ceil( $delay / MINUTE_IN_SECONDS ) ), $url_data['url'], 'error' );
			return;
		}

		// mark the entry as failed.
		$this->set_url_state( $id, 'error' );

		// mark the attachment.
		delete_post_meta( $attachment_id, 'efml_export_queued' );
		update_post_meta( $attachment_id, 'efml_export_failed', time() );

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( __( 'Export of file via queue failed after %1$d attempts. The file stays a local file in the media library.', 'external-files-in-media-library' ), $attempts ), $url_data['url'], 'error' );
	}

	/**
	 * Add files of an exported file to the queue, which could not be deleted on the given external source.
	 *
	 * They are deleted during the next runs of the queue, also after the attachment has been deleted.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                             $attachment_id The attachment ID.
	 * @param int                             $term_id       The ID of the external source.
	 * @param array<int,array<string,string>> $files         List of files (kind, file, url, target).
	 * @param array<string,string>            $metas         The post meta of the attachment, which the service needs.
	 *
	 * @return bool
	 */
	public function add_deletion( int $attachment_id, int $term_id, array $files, array $metas ): bool {
		global $wpdb;

		// bail if no files are given or the table does not exist.
		if ( empty( $files ) || ! Helper::is_table_available( 'eml_queue' ) ) {
			return false;
		}

		// make sure the structure of the table is actual.
		$this->maybe_install();

		// get the wait time before the first further attempt.
		$delay = $this->get_retry_delay( 1, 'delete' );

		// get the URL for the display in the queue: the file itself or the first file.
		$url = (string) ( $files[0]['url'] ?? '' );

		// remove an open entry for the same file and external source (e.g. if it has been tried again).
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'eml_queue',
			array(
				'type'          => 'delete',
				'attachment_id' => $attachment_id,
				'url_hash'      => md5( $url . '|' . $term_id ),
				'state'         => 'new',
			),
			array( '%s', '%d', '%s', '%s' )
		);

		// add the entry.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'eml_queue',
			array(
				'time'          => gmdate( 'Y-m-d H:i:s' ),
				'url'           => $url,
				'fields'        => '',
				'state'         => 'new',
				'options'       => (string) wp_json_encode(
					array(
						'term_id' => $term_id,
						'files'   => array_values( $files ),
						'metas'   => $metas,
					)
				),
				'type'          => 'delete',
				'attachment_id' => $attachment_id,
				'attempts'      => 0,
				'url_hash'      => md5( $url . '|' . $term_id ),
				'next_attempt'  => time() + $delay,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		// use error-handling.
		$this->db_error_handling( $result, $url );

		// plan the further attempt.
		if ( false !== $result ) {
			$this->schedule_exports( $delay );
		}

		// return whether it has been added.
		return false !== $result;
	}

	/**
	 * Process an entry of the queue to delete exported files.
	 *
	 * If the external source could not be used at the moment (e.g. its service is not available), the entry stays in
	 * the queue without counting an attempt. Otherwise, the remaining files are tried again during the next runs,
	 * until the maximum number of attempts is reached.
	 *
	 * @param array<string,string> $url_data The entry data.
	 *
	 * @return void
	 */
	private function process_deletion_entry( array $url_data ): void {
		// get the entry ID.
		$id = (int) $url_data['id'];

		// bail if this website is a copy (e.g. a staging site), the entry stays in the queue until this is the main website.
		if ( Site_Copy::get_instance()->is_copy() ) {
			return;
		}

		// get the options.
		$options = json_decode( (string) $url_data['options'], true );

		// bail if they are not usable.
		if ( ! is_array( $options ) || empty( $options['term_id'] ) || empty( $options['files'] ) || ! is_array( $options['files'] ) ) {
			$this->remove_url( $id );
			return;
		}

		// get the external source.
		$term_id = absint( $options['term_id'] );

		// mark the entry as failed, if the external source does not exist anymore.
		if ( ! get_term( $term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() ) instanceof \WP_Term ) {
			Log::get_instance()->create( __( 'The exported file could not be deleted, as its external source does not exist anymore.', 'external-files-in-media-library' ), $url_data['url'], 'error' );
			$this->set_url_state( $id, 'error' );
			return;
		}

		// delete the files.
		$remaining = \ExternalFilesInMediaLibrary\ExternalFiles\Deletion::get_instance()->delete_files_of_deleted_attachment( (int) $url_data['attachment_id'], $term_id, $options['files'], isset( $options['metas'] ) && is_array( $options['metas'] ) ? $options['metas'] : array() );

		// bail if the external source could not be used at the moment: the entry stays in the queue and is tried again
		// in an hour, without counting an attempt.
		if ( false === $remaining ) {
			$this->set_next_attempt( $id, HOUR_IN_SECONDS );
			Log::get_instance()->create( __( 'The deletion of the exported file has been skipped, as its external source could not be used at the moment. It will be tried again.', 'external-files-in-media-library' ), $url_data['url'], 'info', 2 );
			return;
		}

		// remove the entry, if all files have been deleted.
		if ( empty( $remaining ) ) {
			$this->remove_url( $id );
			Log::get_instance()->create( __( 'The exported files have been deleted via the queue.', 'external-files-in-media-library' ), $url_data['url'], 'success', 1 );
			return;
		}

		// save the remaining files and count this attempt.
		global $wpdb;
		$options['files'] = array_values( $remaining );
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'eml_queue',
			array( 'options' => (string) wp_json_encode( $options ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		$attempts = (int) $url_data['attempts'] + 1;
		$this->set_attempts( $id, $attempts );

		/**
		 * Filter the maximum number of attempts to delete exported files via the queue.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $max_attempts The maximum number of attempts.
		 * @param int $term_id      The ID of the external source.
		 */
		$max_attempts = absint( apply_filters( 'efml_deletion_queue_max_attempts', 5, $term_id ) );

		// mark the entry as failed, if the maximum is reached.
		if ( $attempts >= $max_attempts ) {
			$this->set_url_state( $id, 'error' );
			/* translators: %1$d will be replaced by a number. */
			Log::get_instance()->create( sprintf( __( 'The exported files could not be deleted after %1$d attempts. Delete them on the external source by hand or try again via the queue.', 'external-files-in-media-library' ), $attempts ), $url_data['url'], 'error' );
			return;
		}

		// try again later: the wait time grows with each attempt.
		$this->set_next_attempt( $id, $this->get_retry_delay( $attempts, 'delete' ) );
	}

	/**
	 * Set the number of attempts for an entry.
	 *
	 * @param int $id The ID of the entry.
	 * @param int $attempts The number of attempts.
	 *
	 * @return void
	 */
	private function set_attempts( int $id, int $attempts ): void {
		global $wpdb;

		// update the entry.
		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'eml_queue',
			array(
				'attempts' => $attempts,
			),
			array(
				'id' => $id,
			),
			array(
				'%d',
			),
			array(
				'%d',
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, '' );
	}

	/**
	 * Remove all export entries of an attachment from the queue, e.g. if it is deleted.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function remove_export_entries_of_attachment( int $attachment_id ): void {
		global $wpdb;

		// bail if the table does not exist for the actual website (e.g. after switching to another website in a multisite).
		if ( ! Helper::is_table_available( 'eml_queue' ) ) {
			return;
		}

		// delete the entries.
		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'eml_queue',
			array(
				'type'          => 'export',
				'attachment_id' => $attachment_id,
			),
			array(
				'%s',
				'%d',
			)
		);

		// use error-handling.
		$this->db_error_handling( $result, '' );
	}

	/**
	 * Remove error entries from queue.
	 *
	 * @return void
	 */
	public function clear_error_entries(): void {
		// get the error entries.
		foreach ( $this->get_urls( 'error', true ) as $url_data ) {
			// delete them.
			$this->remove_url( absint( $url_data['id'] ) );
		}
	}

	/**
	 * Add a custom endpoint for site health.
	 *
	 * @param array<int,array<string,mixed>> $endpoints List of endpoints.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function add_site_health_endpoint( array $endpoints ): array {
		// add the endpoint.
		$endpoints[] = array(
			'label'     => Helper::get_plugin_name() . ' ' . __( 'Queue', 'external-files-in-media-library' ),
			'namespace' => 'efml/v1',
			'route'     => '/queue/',
			'callback'  => array( $this, 'check_cron' ),
			'args'      => array(),
		);

		// return the resulting list of endpoints.
		return $endpoints;
	}

	/**
	 * Return result after checking cronjob-states.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnused
	 */
	public function check_cron(): array {
		// define default results.
		$result = array(
			'label'       => __( 'Queue Cron Check', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'External Files in Media Library', 'external-files-in-media-library' ),
				'color' => 'gray',
			),
			'description' => __( 'To process the queue to import external files, we need a cronjob.<br><strong>All ok with the cronjob!</strong>', 'external-files-in-media-library' ),
			'actions'     => '',
			'test'        => 'efml_test_queue_cron',
		);

		// get scheduled event.
		$schedule_obj    = new \ExternalFilesInMediaLibrary\Plugin\Schedules\Queue();
		$scheduled_event = $schedule_obj->get_event();

		// event does not exist => show error.
		if ( false === $scheduled_event ) {
			$url                   = add_query_arg(
				array(
					'action' => 'efml_create_queue_cron',
					'nonce'  => wp_create_nonce( 'efml-create-queue-schedule' ),
				),
				get_admin_url() . 'admin.php'
			);
			$result['status']      = 'recommended';
			$result['description'] = __( 'Cronjob to process the queue to import external files does not exist!', 'external-files-in-media-library' );
			$result['actions']     = '<p><a href="' . $url . '" class="button button-primary">' . __( 'Recreate the cronjob', 'external-files-in-media-library' ) . '</a></p>';

			// return this result.
			return $result;
		}

		// if scheduled event exist, check if next run is in the past.
		if ( $scheduled_event->timestamp < time() ) { // @phpstan-ignore property.notFound
			$result['status'] = 'recommended';
			/* translators: %1$s will be replaced by the date of the planned next schedule run. */
			$result['description'] = sprintf( __( 'Cronjob to process the queue to import external files should have been run at %1$s, but was not executed!<br><strong>Please check the cron-system of your WordPress-installation.</strong>', 'external-files-in-media-library' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $scheduled_event->timestamp ) ) );

			// return this result.
			return $result;
		}

		// return result.
		return $result;
	}

	/**
	 * Add the queue cron by request.
	 *
	 * @return void
	 */
	public function add_cron_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-create-queue-schedule', 'nonce' );

		// bail if user has not the capability.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// recreate the schedule.
		$schedule_obj = new \ExternalFilesInMediaLibrary\Plugin\Schedules\Queue();
		$schedule_obj->reset();

		// forward user.
		wp_safe_redirect( (string) wp_get_referer() );
	}
}
