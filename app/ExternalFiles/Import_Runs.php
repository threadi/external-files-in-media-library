<?php
/**
 * File to handle import runs: imports and synchronizations of directories with many files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use JsonException;
use WP_Admin_Bar;

/**
 * Object to handle import runs.
 *
 * Directories of protocols, which support file lists (see Protocol_Base::supports_file_list()), are imported
 * via import runs:
 * 1. The files of the directory are listed page by page, without loading them. Each file is added as entry
 *    in the queue, which references the run. The credentials and options are saved once in the run.
 * 2. The entries are imported one by one, each as single file.
 *
 * Small directories are imported completely during the request (in the dialog via further AJAX requests).
 * For large directories only the first files are imported directly, all others in the background via
 * WP Cron, independent of the settings of the queue. The progress is shown in the admin bar.
 *
 * Synchronizations use the same way. Files, which are not existing anymore, are only deleted after the
 * complete list of files has been loaded.
 */
class Import_Runs {
	/**
	 * Name of the cron hook to process the runs in the background.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'efml_import_runs';

	/**
	 * Version of the database structure for the runs (incl. the columns in the queue).
	 *
	 * @var string
	 */
	private const DB_VERSION = '1';

	/**
	 * Time in seconds after the last request of the dialog, after which its run is processed in the background.
	 *
	 * @var int
	 */
	private const DIALOG_TIMEOUT = 5 * MINUTE_IN_SECONDS;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Import_Runs
	 */
	private static ?Import_Runs $instance = null;

	/**
	 * The run, whose entry is actually processed.
	 *
	 * @var array<string,mixed>
	 */
	private array $current_run = array();

	/**
	 * The ID of the run, which has been started or resumed in this request by the last call of start().
	 *
	 * @var int
	 */
	private int $started_run_id = 0;

	/**
	 * Marker whether the actual run is processed during the call of a synchronization.
	 *
	 * @var bool
	 */
	private bool $in_sync_call = false;

	/**
	 * Cache for the URLs of all external files in the media library.
	 *
	 * @var array<string,bool>|null
	 */
	private ?array $existing_urls = null;

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
	 * @return Import_Runs
	 */
	public static function get_instance(): Import_Runs {
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
		// process the runs in the background.
		add_action( self::CRON_HOOK, array( $this, 'process' ) );

		// show the progress in the admin bar.
		add_action( 'admin_bar_menu', array( $this, 'add_progress_to_admin_bar' ), 400 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_admin_bar_style' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_admin_bar_style' ) );

		// use the job of the run for its files.
		add_filter( 'efml_import_job_id', array( $this, 'get_job_id' ) );

		// add new files to the list of existing URLs.
		add_action( 'efml_after_file_save', array( $this, 'add_existing_url' ) );
	}

	/**
	 * Return the name of the database table for the runs.
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'efml_import_runs';
	}

	/**
	 * Return the name of the database table for the queue.
	 *
	 * @return string
	 */
	private function get_queue_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'eml_queue';
	}

	/**
	 * Install the database table.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;

		// set collate.
		$charset_collate = $wpdb->get_charset_collate();

		// table for the runs.
		$sql = 'CREATE TABLE ' . $this->get_table_name() . " (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`time` datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
			`type` varchar(20) DEFAULT 'import' NOT NULL,
			`url` text NOT NULL,
			`url_hash` char(32) DEFAULT '' NOT NULL,
			`fields` text NOT NULL,
			`options` text NOT NULL,
			`term_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
			`user_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
			`job_id` varchar(40) DEFAULT '' NOT NULL,
			`cursor` longtext NOT NULL,
			`state` varchar(20) DEFAULT 'listing' NOT NULL,
			`background` tinyint(1) unsigned DEFAULT 0 NOT NULL,
			`listing_failed` tinyint(1) unsigned DEFAULT 0 NOT NULL,
			`total` int(10) unsigned DEFAULT 0 NOT NULL,
			`processed` int(10) unsigned DEFAULT 0 NOT NULL,
			`errors` int(10) unsigned DEFAULT 0 NOT NULL,
			`skipped` int(10) unsigned DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY url_hash (url_hash),
			KEY state (state)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // @phpstan-ignore requireOnce.fileNotFound
		dbDelta( $sql );

		// save the version of the database structure.
		update_option( 'efml_import_runs_db_version', self::DB_VERSION );
	}

	/**
	 * Install the database structure, if it is missing or outdated (e.g. if files of a development version have
	 * been updated without running the update of the plugin).
	 *
	 * @return void
	 */
	private function maybe_install(): void {
		// bail if the structure is actual.
		if ( self::DB_VERSION === get_option( 'efml_import_runs_db_version' ) ) {
			return;
		}

		// add the columns of the queue for the runs.
		Extensions\Queue::get_instance()->install();

		// add the table for the runs.
		$this->install();
	}

	/**
	 * Remove the database table and the schedule.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// remove the schedule.
		wp_clear_scheduled_hook( self::CRON_HOOK );

		// remove the table.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $this->get_table_name() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Return whether the given URL should be imported via an import run.
	 *
	 * @param Protocol_Base $protocol_handler_obj The protocol object for the URL.
	 *
	 * @return bool
	 */
	public function is_run_possible( Protocol_Base $protocol_handler_obj ): bool {
		// bail if an entry of a run is actually processed (each entry is a single file).
		if ( ! empty( $this->current_run ) ) {
			return false;
		}

		// bail if the protocol does not support file lists.
		if ( ! $protocol_handler_obj->supports_file_list() ) {
			return false;
		}

		// bail if this is not a directory.
		if ( ! $protocol_handler_obj->is_directory() ) {
			return false;
		}

		// make sure the database structure exists.
		$this->maybe_install();

		// use a run for this directory.
		return true;
	}

	/**
	 * Start the import run for the given directory URL or resume an unfinished run for it.
	 *
	 * @param string                            $url                  The URL of the directory.
	 * @param Protocol_Base                     $protocol_handler_obj The protocol object for the URL.
	 * @param array<string,array<string,mixed>> $fields               The fields for the connection.
	 *
	 * @return bool|null True if the run has been started or resumed, null if no run could be created (the URL is
	 *                   imported without run then).
	 */
	public function start( string $url, Protocol_Base $protocol_handler_obj, array $fields ): ?bool {
		// get the synchronization, if one is running.
		$sync_term_id = Synchronization::get_instance()->get_running_term_id();

		// get the type of this run.
		$type = $sync_term_id > 0 ? 'sync' : 'import';

		// reset the marker for the run started by this call.
		$this->started_run_id = 0;

		// check for an unfinished run for this URL.
		$run = $this->get_active_run_by_url( $url, $type );

		// a synchronization starts always a new run, as the markers of the synced files have been reset.
		if ( ! empty( $run ) && 'sync' === $type ) {
			$this->cancel( $run );
			$run = array();
		}

		// resume an unfinished import in the dialog of the same user, which is not running in the background.
		if ( ! empty( $run ) ) {
			if ( ! $this->is_dialog_request() || absint( $run['user_id'] ) !== get_current_user_id() || 1 === absint( $run['background'] ) ) {
				// add a hint to the result.
				$result_obj = new Url_Result();
				$result_obj->set_url( $url );
				$result_obj->set_result_text( __( 'The files of this URL are already being imported in the background.', 'external-files-in-media-library' ) );
				Results::get_instance()->add( $result_obj );
				return true;
			}
		} else {
			// create the run.
			$run = $this->create( $url, $type, $fields, $sync_term_id > 0 ? $sync_term_id : $this->get_term_id_for_import() );

			// bail if the run could not be created: the directory is imported without run.
			if ( empty( $run ) ) {
				return null;
			}
		}

		// remember the run of this call.
		$this->started_run_id = absint( $run['id'] );

		// mark the run as part of the actual synchronization call.
		$this->in_sync_call = 'sync' === $type;

		// run it.
		try {
			$this->work( absint( $run['id'] ), $this->get_deadline(), $this->is_dialog_request() );
		} finally {
			$this->in_sync_call = false;
		}

		// get the run in its actual state.
		$run = $this->get_run( absint( $run['id'] ) );

		// bail if the run could not be loaded.
		if ( empty( $run ) ) {
			return false;
		}

		// return true as long as the run is not finished.
		if ( ! $this->is_finished( $run ) ) {
			return true;
		}

		// add a hint, if the directory does not contain any file.
		if ( 'done' === $run['state'] && 0 === absint( $run['total'] ) && 0 === absint( $run['skipped'] ) ) {
			$result_obj = new Url_Result();
			$result_obj->set_url( $url );
			$result_obj->set_result_text( __( 'No files to import were found.', 'external-files-in-media-library' ) );
			Results::get_instance()->add( $result_obj );
		}

		// add a hint, if all files of the directory are already in the media library.
		if ( 0 === absint( $run['total'] ) && absint( $run['skipped'] ) > 0 ) {
			$result_obj = new Url_Result();
			$result_obj->set_url( $url );
			$result_obj->set_result_text( __( 'All files of this directory are already in your media library.', 'external-files-in-media-library' ) );
			Results::get_instance()->add( $result_obj );
		}

		// return whether any file has been imported.
		return absint( $run['processed'] ) > 0;
	}

	/**
	 * Return the ID of the run, which has been started or resumed by the last call of start() in this request.
	 *
	 * @return int
	 */
	public function get_started_run_id(): int {
		return $this->started_run_id;
	}

	/**
	 * Return the data of the given run.
	 *
	 * @param int $run_id The ID of the run.
	 *
	 * @return array<string,mixed>
	 */
	public function get_run( int $run_id ): array {
		global $wpdb;
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `id` = %d', array( $this->get_table_name(), $run_id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return whether the given run is finished (incl. failed and cancelled runs).
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool
	 */
	public function is_finished( array $run ): bool {
		return in_array( $run['state'], array( 'done', 'failed', 'cancelled' ), true );
	}

	/**
	 * Return whether the files of the given run have been listed completely and the run is done.
	 *
	 * A run without any file is not complete, as an error of the external source could not be excluded then.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool
	 */
	public function is_listing_complete( array $run ): bool {
		return 'done' === $run['state'] && 0 === absint( $run['listing_failed'] ) && absint( $run['total'] ) > 0;
	}

	/**
	 * Return the unfinished run for the given URL and type.
	 *
	 * @param string $url  The URL.
	 * @param string $type The type.
	 *
	 * @return array<string,mixed>
	 */
	private function get_active_run_by_url( string $url, string $type ): array {
		global $wpdb;
		return Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE `url_hash` = %s AND `type` = %s AND `state` IN (%s, %s) ORDER BY `id` DESC LIMIT 1', array( $this->get_table_name(), md5( $url ), $type, 'listing', 'processing' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return the list of unfinished runs.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_active_runs(): array {
		global $wpdb;
		return Helper::get_db_results( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE `state` IN (%s, %s) ORDER BY `id` ASC', array( $this->get_table_name(), 'listing', 'processing' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Create a new run.
	 *
	 * @param string                            $url     The URL of the directory.
	 * @param string                            $type    The type (import or sync).
	 * @param array<string,array<string,mixed>> $fields  The fields for the connection.
	 * @param int                               $term_id The ID of the external source.
	 *
	 * @return array<string,mixed>
	 */
	private function create( string $url, string $type, array $fields, int $term_id ): array {
		global $wpdb;

		/**
		 * Filter the options used for the import of this URL. Same as for the queue.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $options List of options.
		 * @param string $url The used URL.
		 */
		$options = apply_filters( 'efml_import_options', array(), $url );

		// convert the options to JSON.
		$options_json = wp_json_encode( $options );

		// add the run.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->get_table_name(),
			array(
				'time'       => gmdate( 'Y-m-d H:i:s' ),
				'type'       => $type,
				'url'        => $url,
				'url_hash'   => md5( $url ),
				'fields'     => ! empty( $fields ) ? Crypt::get_instance()->encrypt( Helper::get_json( $fields ) ) : '',
				'options'    => $options_json ? $options_json : '',
				'term_id'    => $term_id,
				'user_id'    => Helper::get_current_user_id(),
				'job_id'     => uniqid( '', true ),
				'cursor'     => '',
				'state'      => 'listing',
				'background' => $this->is_dialog_request() || Helper::is_cli() ? 0 : 1,
			)
		);

		// bail on error.
		if ( false === $result ) {
			/* translators: %1$s is replaced by an error-code. */
			Log::get_instance()->create( sprintf( __( 'Database-Error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_json_encode( $wpdb->last_error ) . '</code>' ), $url, 'error' );
			return array();
		}

		// get the ID of the run (before the log entry is added).
		$run_id = absint( $wpdb->insert_id );

		// log this event.
		Log::get_instance()->create( __( 'The files of this directory will be listed and imported one by one.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// return the run.
		return $this->get_run( $run_id );
	}

	/**
	 * Return the term ID of the external source for an import, if one is used.
	 *
	 * @return int
	 */
	private function get_term_id_for_import(): int {
		// get the term ID from request.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );

		// use the term ID of the import object, if none is in the request.
		if ( 0 === $term_id ) {
			$term_id = Import::get_instance()->get_term_id();
		}

		// return the term ID.
		return $term_id;
	}

	/**
	 * Return whether this is the request of the import dialog, which shows the progress and loads more.
	 *
	 * @return bool
	 */
	private function is_dialog_request(): bool {
		return defined( 'EFML_URL_IMPORT_RUNNING' ) && wp_doing_ajax();
	}

	/**
	 * Return the time, until which a request could work on runs.
	 *
	 * @return float
	 */
	private function get_deadline(): float {
		// no limit via WP CLI.
		if ( Helper::is_cli() ) {
			return PHP_FLOAT_MAX;
		}

		/**
		 * Filter the time in seconds, which a request could use to work on import runs.
		 *
		 * The time is counted from the start of the request and shortened to 60 percent of "max_execution_time"
		 * of the hosting.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $seconds The time in seconds.
		 */
		$seconds = absint( apply_filters( 'efml_import_run_time_limit', 20 ) );

		// get the max execution time of the hosting.
		$max_execution_time = absint( ini_get( 'max_execution_time' ) );
		if ( $max_execution_time > 0 ) {
			$seconds = min( $seconds, (int) floor( $max_execution_time * 0.6 ) );
		}

		// get the start of this request, as the time is used for the complete request (e.g. for multiple runs).
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );

		// return the deadline, but use at least some seconds to work on the runs, if the request is already long running.
		return max( $start + $seconds, microtime( true ) + min( $seconds, 3 ) );
	}

	/**
	 * Return the count of files, from which on an import from the dialog is moved to the background.
	 *
	 * @return int
	 */
	private function get_background_threshold(): int {
		/**
		 * Filter the count of files, from which on the import of a directory in the dialog is done in the background.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $threshold The count of files.
		 */
		return absint( apply_filters( 'efml_background_import_threshold', 500 ) );
	}

	/**
	 * Return the count of files, which are imported directly, if an import from the dialog is moved to the background.
	 *
	 * @return int
	 */
	private function get_direct_count(): int {
		/**
		 * Filter the count of files, which are imported directly in the dialog, if the import of the directory
		 * is done in the background.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $count The count of files.
		 */
		return absint( apply_filters( 'efml_background_import_direct_count', 20 ) );
	}

	/**
	 * Work on the given run until it is finished or the deadline is reached.
	 *
	 * @param int   $run_id The ID of the run.
	 * @param float $deadline The deadline as timestamp with microseconds.
	 * @param bool  $is_dialog Whether this is the request of the import dialog.
	 *
	 * @return void
	 */
	private function work( int $run_id, float $deadline, bool $is_dialog = false ): void {
		// get the run.
		$run = $this->get_run( $run_id );

		// bail if run could not be loaded or is finished.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return;
		}

		// count the directly imported files, if the run will be moved to the background.
		$direct_count = 0;

		// load the URLs of the existing files again, as they could have been changed since the last run.
		$this->existing_urls = null;

		// mark the activity of the dialog, so the run is not processed in the background at the same time.
		if ( $is_dialog ) {
			$this->update( $run_id, array( 'time' => gmdate( 'Y-m-d H:i:s' ) ) );
		}

		// work on the run.
		while ( microtime( true ) < $deadline ) {
			// list the next page of files.
			if ( 'listing' === $run['state'] ) {
				$this->list_next_page( $run );
				$run = $this->get_run( $run_id );

				// move the import into the background, if it contains too many files for the dialog.
				if ( $is_dialog && 0 === absint( $run['background'] ) && absint( $run['total'] ) > $this->get_background_threshold() ) {
					$this->update( $run_id, array( 'background' => 1 ) );
					$run = $this->get_run( $run_id );
				}
				continue;
			}

			// bail if the run is finished.
			if ( $this->is_finished( $run ) ) {
				break;
			}

			// in the dialog only the first files are imported directly, if the run is in the background.
			if ( $is_dialog && 1 === absint( $run['background'] ) && $direct_count >= $this->get_direct_count() ) {
				break;
			}

			// import the next file.
			if ( ! $this->process_next_entry( $run ) ) {
				// finish the run if no more entries exist.
				$this->finish( $run_id );
				break;
			}
			++$direct_count;

			// get the run in its actual state.
			$run = $this->get_run( $run_id );
		}

		// get the run in its actual state.
		$run = $this->get_run( $run_id );

		// bail if it could not be loaded or is finished.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return;
		}

		// in the dialog: load more or show the hint for the import in the background.
		if ( $is_dialog ) {
			if ( 0 === absint( $run['background'] ) ) {
				// let the dialog load more.
				update_option( 'eml_import_url_loading_more_' . get_current_user_id(), 1 );

				// process it in the background, if the dialog is closed before the import is finished.
				$this->schedule( self::DIALOG_TIMEOUT );
				return;
			}

			// show the hint.
			$this->add_background_result( $run );

			// mark it as background run, so it is processed by WP Cron.
			$this->update( $run_id, array( 'background' => 1 ) );
		}

		// process the rest in the background.
		$this->schedule();
	}

	/**
	 * Add the hint about the import in the background to the results of the dialog.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function add_background_result( array $run ): void {
		// create the text.
		if ( 'listing' === $run['state'] ) {
			/* translators: %1$d will be replaced by a number. */
			$text = sprintf( _n( '%1$d file has been imported. The directory contains many files: they are now listed and imported in the background.', '%1$d files have been imported. The directory contains many files: they are now listed and imported in the background.', absint( $run['processed'] ), 'external-files-in-media-library' ), absint( $run['processed'] ) );
		} else {
			$remaining = max( 0, absint( $run['total'] ) - absint( $run['processed'] ) - absint( $run['errors'] ) );
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$text = sprintf( __( '%1$d files have been imported. The other %2$d files will be imported in the background.', 'external-files-in-media-library' ), absint( $run['processed'] ), $remaining );
		}

		// add the result.
		$result_obj = new Url_Result();
		$result_obj->set_url( (string) $run['url'] );
		$result_obj->set_result_text( $text );
		$result_obj->set_error( false );
		Results::get_instance()->add( $result_obj );

		// log this event.
		Log::get_instance()->create( $text, (string) $run['url'], 'info', 2, Import::get_instance()->get_identifier() );
	}

	/**
	 * Return the protocol object for the given run.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return Protocol_Base|false
	 */
	private function get_protocol( array $run ): Protocol_Base|false {
		// get the fields.
		$fields = $this->get_fields( $run );

		// get the protocol object.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( (string) $run['url'], $fields );

		// bail if no protocol could be found.
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		// set the fields.
		$protocol_handler_obj->set_fields( $fields );

		// return the object.
		return $protocol_handler_obj;
	}

	/**
	 * Return the fields of the given run.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_fields( array $run ): array {
		// bail if no fields are set.
		if ( empty( $run['fields'] ) || ! is_string( $run['fields'] ) ) {
			return array();
		}

		// decrypt them.
		$fields = json_decode( Crypt::get_instance()->decrypt( $run['fields'] ), true );

		// return the fields.
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * List the next page of files of the given run and add them to the queue.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function list_next_page( array $run ): void {
		global $wpdb;

		// get the ID.
		$run_id = absint( $run['id'] );

		// get the protocol object.
		$protocol_handler_obj = $this->get_protocol( $run );

		// get the page of files.
		$page = $protocol_handler_obj ? $protocol_handler_obj->get_file_list( (string) $run['cursor'] ) : false;

		// stop the listing on errors: the files listed until now are still imported, but nothing is deleted on synchronization.
		if ( false === $page ) {
			Log::get_instance()->create( __( 'The files of the directory could not be listed completely. The files listed until now are imported, but no files are deleted during a synchronization.', 'external-files-in-media-library' ), (string) $run['url'], 'error', 0, Import::get_instance()->get_identifier() );
			$this->update(
				$run_id,
				array(
					'state'          => 0 === absint( $run['total'] ) ? 'failed' : 'processing',
					'listing_failed' => 1,
				)
			);
			return;
		}

		// get the files.
		$files = $page['files'];

		/**
		 * Filter the files of a directory, which will be added to the queue of the import run.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,array<string,mixed>> $files List of files with their URLs.
		 * @param array<string,mixed> $run The run.
		 */
		$files = apply_filters( 'efml_import_run_files', $files, $run );

		// collect the entries for the queue.
		$entries = array();
		$skipped = 0;
		foreach ( $files as $file ) {
			// bail if no URL is given.
			if ( empty( $file['url'] ) ) {
				continue;
			}

			// get the URL.
			$file_url = (string) $file['url'];

			// skip files, which are already in the media library (not during synchronization, which updates them).
			if ( 'import' === $run['type'] && ( $this->is_existing_url( $file_url ) || ( ! empty( $file['media-url'] ) && $this->is_existing_url( (string) $file['media-url'] ) ) ) ) {
				++$skipped;
				continue;
			}

			// add the entry.
			$entries[ md5( $file_url ) ] = $file_url;
		}

		// remove the files, which are already in the queue for this run.
		$inserted = $this->add_entries( $run_id, $entries );

		// log the skipped files.
		if ( $skipped > 0 ) {
			/* translators: %1$d will be replaced by a number. */
			Log::get_instance()->create( sprintf( _n( '%1$d file has been skipped, as it is already in your media library.', '%1$d files have been skipped, as they are already in your media library.', $skipped, 'external-files-in-media-library' ), $skipped ), (string) $run['url'], 'info', 1, Import::get_instance()->get_identifier() );
		}

		// update the run.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `cursor` = %s, `state` = %s, `total` = `total` + %d, `skipped` = `skipped` + %d WHERE `id` = %d', array( $this->get_table_name(), $page['cursor'], '' === $page['cursor'] ? 'processing' : 'listing', $inserted, $skipped, $run_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// update the progress for the dialogs.
		$this->update_progress( $this->get_run( $run_id ) );
	}

	/**
	 * Add the given URLs as entries of the given run to the queue and return the count of new entries.
	 *
	 * URLs, which are already in the queue for this run, are not added again.
	 *
	 * @param int                  $run_id  The ID of the run.
	 * @param array<string,string> $entries List of URLs with their MD5 hashes as keys.
	 *
	 * @return int
	 */
	private function add_entries( int $run_id, array $entries ): int {
		global $wpdb;

		// bail if list is empty.
		if ( empty( $entries ) ) {
			return 0;
		}

		// get the hashes, which are already in the queue for this run.
		$placeholders = implode( ',', array_fill( 0, count( $entries ), '%s' ) );
		$existing     = $wpdb->get_col( $wpdb->prepare( 'SELECT `url_hash` FROM %i WHERE `run_id` = %d AND `url_hash` IN (' . $placeholders . ')', array_merge( array( $this->get_queue_table_name(), $run_id ), array_keys( $entries ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		foreach ( $existing as $hash ) {
			unset( $entries[ $hash ] );
		}

		// bail if no new entries remain.
		if ( empty( $entries ) ) {
			return 0;
		}

		// insert them in blocks.
		$time = gmdate( 'Y-m-d H:i:s' );
		foreach ( array_chunk( $entries, 100, true ) as $chunk ) {
			$values = array();
			$args   = array( $this->get_queue_table_name() );
			foreach ( $chunk as $hash => $url ) {
				$values[] = '(%s, %s, %s, %s, %s, %s, %s, %d)';
				array_push( $args, $time, $url, $hash, '', 'new', '', 'import', $run_id );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (`time`, `url`, `url_hash`, `fields`, `state`, `options`, `type`, `run_id`) VALUES ' . implode( ', ', $values ), $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		// return the count of new entries.
		return count( $entries );
	}

	/**
	 * Return whether the given URL is already used by an external file in the media library.
	 *
	 * The URLs of all external files are loaded once per request. The check could be disabled via the hook
	 * "efml_duplicate_check" like the check of each file during the import.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	private function is_existing_url( string $url ): bool {
		global $wpdb;

		// URLs are saved decoded in media library, so compare the decoded form.
		$url = urldecode( $url );

		$false = false;
		/**
		 * Filter to prevent duplicate check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $false Must be true to prevent check.
		 * @param string $url The used URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'efml_duplicate_check', $false, $url ) ) {
			return false;
		}

		// load the URLs, if not done yet.
		if ( is_null( $this->existing_urls ) ) {
			$urls                = $wpdb->get_col( $wpdb->prepare( 'SELECT `meta_value` FROM %i WHERE `meta_key` = %s', array( $wpdb->postmeta, EFML_POST_META_URL ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->existing_urls = array_fill_keys( array_map( 'strval', $urls ), true );
		}

		// return whether the URL is in the list.
		return isset( $this->existing_urls[ $url ] );
	}

	/**
	 * Add the URL of a new external file to the list of existing URLs, if it is loaded.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_existing_url( File $external_file_obj ): void {
		// bail if the list is not loaded.
		if ( is_null( $this->existing_urls ) ) {
			return;
		}

		// add the URL.
		$this->existing_urls[ $external_file_obj->get_url( true ) ] = true;
	}

	/**
	 * Claim and import the next entry of the given run.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool False if no more entries exist.
	 */
	private function process_next_entry( array $run ): bool {
		global $wpdb;

		// get the next entries and claim one of them (another request could process the same run).
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT `id` FROM %i WHERE `run_id` = %d AND `state` = %s ORDER BY `id` ASC LIMIT 5', array( $this->get_queue_table_name(), absint( $run['id'] ), 'new' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// bail if no entries exist anymore.
		if ( empty( $ids ) ) {
			return false;
		}

		// claim the first free one.
		foreach ( $ids as $id ) {
			if ( $this->claim( absint( $id ) ) ) {
				$this->process_entry( absint( $id ), $run );
				return true;
			}
		}

		// all entries were claimed by another request: try again.
		return true;
	}

	/**
	 * Claim the given entry for processing in this request.
	 *
	 * @param int $id The ID of the entry.
	 *
	 * @return bool
	 */
	private function claim( int $id ): bool {
		global $wpdb;
		return 1 === $wpdb->query( $wpdb->prepare( 'UPDATE %i SET `state` = %s, `time` = %s WHERE `id` = %d AND `state` = %s', array( $this->get_queue_table_name(), 'processing', gmdate( 'Y-m-d H:i:s' ), $id, 'new' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Import the given entry of the queue, which belongs to a run, e.g. on request in the queue table.
	 *
	 * @param int $id The ID of the entry.
	 *
	 * @return void
	 */
	public function process_entry_by_id( int $id ): void {
		global $wpdb;

		// get the run ID of the entry.
		$run_id = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT `run_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// get the run.
		$run = $this->get_run( $run_id );

		// bail if run does not exist.
		if ( empty( $run ) ) {
			return;
		}

		// set the entry to processing.
		$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'processing' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// a former error is counted again with the new result.
		if ( absint( $run['errors'] ) > 0 ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` - 1 WHERE `id` = %d AND `errors` > 0', array( $this->get_table_name(), $run_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// import it.
		$this->process_entry( $id, $run );
	}

	/**
	 * Import the given entry of the given run.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_entry( int $id, array $run ): void {
		global $wpdb;

		// get the URL.
		$url = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT `url` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// get the import object.
		$import = Import::get_instance();

		// save the actual settings of the import object, to restore them afterward.
		$previous_fields  = $import->get_fields();
		$previous_term_id = $import->get_term_id();

		// set the settings of the run.
		$import->set_fields( $this->get_fields( $run ) );
		$import->set_term_id( absint( $run['term_id'] ) );

		// set the options of the run in the request, as the extensions use them from there.
		$original_post_values = $this->set_options( $run );

		// mark the run as actual run.
		$this->current_run = $run;

		// import the file.
		$success = false;
		try {
			// update the title of the progress in the dialogs.
			$this->update_progress_title( $run, $url );

			// import it.
			$success = $import->add_url( $url );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the import of this URL via queue:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error', 0, Import::get_instance()->get_identifier() );
		} finally {
			// restore the settings.
			$this->current_run = array();
			$import->set_fields( $previous_fields );
			$import->set_term_id( $previous_term_id );
			$this->restore_options( $original_post_values );
		}

		// update the entry and the counter of the run.
		if ( $success ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		// update the progress for the dialogs.
		$this->update_progress( $this->get_run( absint( $run['id'] ) ) );
	}

	/**
	 * Set the options of the given run in the request and return the original values.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return array<string,array<int,mixed>|null>
	 */
	private function set_options( array $run ): array {
		// collect the original values.
		$original_post_values = array();

		// bail if no options are set.
		if ( empty( $run['options'] ) ) {
			return $original_post_values;
		}

		// get them.
		try {
			$options = json_decode( (string) $run['options'], true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			Log::get_instance()->create( __( 'Error decoding options to import this URL via queue:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', (string) $run['url'], 'error', 0, Import::get_instance()->get_identifier() );
			return $original_post_values;
		}

		// bail if options are not an array.
		if ( ! is_array( $options ) ) {
			return $original_post_values;
		}

		// set them in the request.
		foreach ( $options as $key => $value ) {
			$original_post_values[ $key ] = array_key_exists( $key, $_POST ) ? array( $_POST[ $key ] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Only saved to restore the value after the import.
			$_POST[ $key ]                = $value;
		}

		// return the original values.
		return $original_post_values;
	}

	/**
	 * Restore the given original values of the request.
	 *
	 * @param array<string,array<int,mixed>|null> $original_post_values The original values.
	 *
	 * @return void
	 */
	private function restore_options( array $original_post_values ): void {
		foreach ( $original_post_values as $key => $value ) {
			if ( is_array( $value ) ) {
				$_POST[ $key ] = $value[0];
				continue;
			}
			unset( $_POST[ $key ] );
		}
	}

	/**
	 * Return the job ID of the run, whose entry is actually processed, so all its files belong to one job.
	 *
	 * @param string $job_id The job ID.
	 *
	 * @return string
	 */
	public function get_job_id( string $job_id ): string {
		// bail if no run is processed.
		if ( empty( $this->current_run['job_id'] ) ) {
			return $job_id;
		}

		// return the job ID of the run.
		return (string) $this->current_run['job_id'];
	}

	/**
	 * Return the run, whose entry is actually processed.
	 *
	 * @return array<string,mixed>
	 */
	public function get_current_run(): array {
		return $this->current_run;
	}

	/**
	 * Finish the given run, if no entries are left.
	 *
	 * @param int $run_id The ID of the run.
	 *
	 * @return void
	 */
	private function finish( int $run_id ): void {
		global $wpdb;

		// get the run.
		$run = $this->get_run( $run_id );

		// bail if run could not be loaded or is finished.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return;
		}

		// bail if entries are still processed by another request.
		$processing = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `run_id` = %d AND `state` IN (%s, %s)', array( $this->get_queue_table_name(), $run_id, 'new', 'processing' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $processing > 0 ) {
			return;
		}

		// detect whether entries have been deleted in the queue: the run is cancelled then.
		$state = absint( $run['processed'] ) + absint( $run['errors'] ) < absint( $run['total'] ) ? 'cancelled' : 'done';

		// set the new state.
		$this->update( $run_id, array( 'state' => $state ) );
		$run['state'] = $state;

		// log this event.
		/* translators: %1$d, %2$d and %3$d will be replaced by numbers. */
		Log::get_instance()->create( sprintf( __( 'Import of the directory has been finished: %1$d files imported, %2$d errors, %3$d files skipped.', 'external-files-in-media-library' ), absint( $run['processed'] ), absint( $run['errors'] ), absint( $run['skipped'] ) ), (string) $run['url'], 'cancelled' === $state || absint( $run['errors'] ) > 0 ? 'error' : 'success', 1, Import::get_instance()->get_identifier() );

		// complete the synchronization, if it is not done by the call of the synchronization itself.
		if ( 'sync' === $run['type'] && ! $this->in_sync_call ) {
			Synchronization::get_instance()->complete( (string) $run['url'], absint( $run['term_id'] ), $this->is_listing_complete( $run ) );
		}

		/**
		 * Run tasks after an import run has been finished.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,mixed> $run The run.
		 */
		do_action( 'efml_import_run_finished', $run );
	}

	/**
	 * Cancel the given run: its entries are removed from the queue.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function cancel( array $run ): void {
		global $wpdb;

		// remove the entries.
		$wpdb->delete( $this->get_queue_table_name(), array( 'run_id' => absint( $run['id'] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// mark the run as cancelled.
		$this->update( absint( $run['id'] ), array( 'state' => 'cancelled' ) );
	}

	/**
	 * Cancel all unfinished runs, e.g. if the queue has been cleared.
	 *
	 * @return void
	 */
	public function cancel_all(): void {
		foreach ( $this->get_active_runs() as $run ) {
			$this->cancel( $run );
		}
		$this->remove_finished_runs();
	}

	/**
	 * Update the given run with the given values.
	 *
	 * @param int                 $run_id The ID of the run.
	 * @param array<string,mixed> $values The values.
	 *
	 * @return void
	 */
	private function update( int $run_id, array $values ): void {
		global $wpdb;
		$wpdb->update( $this->get_table_name(), $values, array( 'id' => $run_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Process the unfinished runs in the background (via WP Cron).
	 *
	 * @return void
	 */
	public function process(): void {
		// bail if uninstallation is running.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// get the deadline.
		$deadline = $this->get_deadline();

		// make sure the database structure exists.
		$this->maybe_install();

		// reset entries, whose processing has been aborted (e.g. by a timeout).
		$this->reset_aborted_entries();

		// work on the runs.
		foreach ( $this->get_active_runs() as $run ) {
			// bail if deadline is reached.
			if ( microtime( true ) >= $deadline ) {
				break;
			}

			// do not process runs, which are actually imported in the dialog.
			if ( $this->is_in_dialog( $run ) ) {
				continue;
			}

			// work on it.
			$this->work_in_background( $run, $deadline );
		}

		// remove the finished runs, which have no entries in the queue anymore.
		$this->remove_finished_runs();

		// plan the next processing, if unfinished runs exist (later, if they are only imported in the dialog).
		$delay = 0;
		$runs  = $this->get_active_runs();
		foreach ( $runs as $run ) {
			$delay = $this->is_in_dialog( $run ) ? self::DIALOG_TIMEOUT : 0;
			if ( 0 === $delay ) {
				break;
			}
		}
		if ( ! empty( $runs ) ) {
			$this->schedule( $delay );
		}
	}

	/**
	 * Return whether the given run is actually imported in the dialog.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool
	 */
	private function is_in_dialog( array $run ): bool {
		return 0 === absint( $run['background'] ) && strtotime( (string) $run['time'] . ' UTC' ) > time() - self::DIALOG_TIMEOUT;
	}

	/**
	 * Work on the given run in the background.
	 *
	 * For synchronizations the hooks of the synchronization are used during the work.
	 *
	 * @param array<string,mixed> $run The run.
	 * @param float               $deadline The deadline.
	 *
	 * @return void
	 */
	private function work_in_background( array $run, float $deadline ): void {
		// work on imports.
		if ( 'sync' !== $run['type'] ) {
			$this->work( absint( $run['id'] ), $deadline );
			return;
		}

		// get the synchronization object.
		$synchronization = Synchronization::get_instance();

		// start the part of the synchronization.
		$synchronization->start_part( (string) $run['url'], absint( $run['term_id'] ) );

		// work on it.
		try {
			$this->work( absint( $run['id'] ), $deadline );
		} finally {
			// end the part of the synchronization.
			$synchronization->end_part( (string) $run['url'], absint( $run['term_id'] ) );
		}
	}

	/**
	 * Reset entries, whose processing has been aborted more than 30 minutes ago (e.g. by a timeout).
	 *
	 * @return void
	 */
	private function reset_aborted_entries(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `state` = %s WHERE `state` = %s AND `run_id` > 0 AND `time` < %s', array( $this->get_queue_table_name(), 'new', 'processing', gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Remove the finished runs, which have no entries in the queue anymore.
	 *
	 * @return void
	 */
	public function remove_finished_runs(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE r FROM %i r WHERE r.`state` IN (%s, %s, %s) AND NOT EXISTS (SELECT 1 FROM %i q WHERE q.`run_id` = r.`id`)', array( $this->get_table_name(), 'done', 'failed', 'cancelled', $this->get_queue_table_name() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Plan the processing of the runs in the background, if not planned yet.
	 *
	 * @param int $delay The delay in seconds.
	 *
	 * @return void
	 */
	public function schedule( int $delay = 0 ): void {
		// bail if it is already planned.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// plan it.
		wp_schedule_single_event( time() + $delay, self::CRON_HOOK );
	}

	/**
	 * Update the progress of the dialogs for imports and synchronizations.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function update_progress( array $run ): void {
		// bail if run is empty.
		if ( empty( $run ) ) {
			return;
		}

		// update the progress of the synchronization.
		if ( 'sync' === $run['type'] ) {
			update_option( 'eml_sync_url_max', absint( $run['total'] ) );
			update_option( 'eml_sync_url_count', absint( $run['processed'] ) + absint( $run['errors'] ) );
			return;
		}

		// bail if this is not the request of the dialog.
		if ( ! $this->is_dialog_request() ) {
			return;
		}

		// update the progress of the import dialog.
		update_option( 'eml_import_url_max_' . get_current_user_id(), absint( $run['total'] ) );
		update_option( 'eml_import_url_count_' . get_current_user_id(), absint( $run['processed'] ) + absint( $run['errors'] ) );
	}

	/**
	 * Update the title of the progress in the dialogs for the given file.
	 *
	 * @param array<string,mixed> $run The run.
	 * @param string              $url The URL of the file.
	 *
	 * @return void
	 */
	private function update_progress_title( array $run, string $url ): void {
		// bail if this is not the request of the dialog.
		if ( 'sync' === $run['type'] || ! $this->is_dialog_request() ) {
			return;
		}

		/* translators: %1$s will be replaced by the URL. */
		update_option( 'eml_import_title_' . get_current_user_id(), sprintf( __( 'Import file %1$s ..', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $url ) ) ) );
	}

	/**
	 * Return the progress of all unfinished runs.
	 *
	 * @return array{runs: int, total: int, done: int, listing: int}
	 */
	public function get_progress(): array {
		global $wpdb;

		// get the sums.
		$result = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS `runs`, SUM(`total`) AS `total`, SUM(`processed` + `errors`) AS `done`, SUM(`state` = %s) AS `listing` FROM %i WHERE `state` IN (%s, %s)', array( 'listing', $this->get_table_name(), 'listing', 'processing' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return the values.
		return array(
			'runs'    => absint( $result['runs'] ?? 0 ),
			'total'   => absint( $result['total'] ?? 0 ),
			'done'    => absint( $result['done'] ?? 0 ),
			'listing' => absint( $result['listing'] ?? 0 ),
		);
	}

	/**
	 * Return whether the progress should be shown in the admin bar.
	 *
	 * @return bool
	 */
	private function is_progress_visible(): bool {
		// bail if admin bar is not visible.
		if ( ! is_admin_bar_showing() ) {
			return false;
		}

		// bail if user has not the capability to import files.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return false;
		}

		// show it if unfinished runs exist.
		return $this->get_progress()['runs'] > 0;
	}

	/**
	 * Show the progress of the import runs in the admin bar, as long as they are running.
	 *
	 * @param WP_Admin_Bar $admin_bar The admin bar object.
	 *
	 * @return void
	 */
	public function add_progress_to_admin_bar( WP_Admin_Bar $admin_bar ): void {
		// bail if the progress should not be shown.
		if ( ! $this->is_progress_visible() ) {
			return;
		}

		// get the progress.
		$progress = $this->get_progress();

		// calculate the percentage.
		$percentage = $progress['total'] > 0 ? (int) floor( $progress['done'] / $progress['total'] * 100 ) : 0;

		// create the title-attribute.
		/* translators: %1$d and %2$d will be replaced by numbers. */
		$title = sprintf( __( '%1$d of %2$d external files imported', 'external-files-in-media-library' ), $progress['done'], $progress['total'] );
		if ( $progress['listing'] > 0 ) {
			$title .= ' ' . __( '(more files are being listed)', 'external-files-in-media-library' );
		}

		// get the link to the queue.
		$url = '';
		if ( current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() ) ) {
			$url = Settings::get_instance()->get_url( 'eml_queue_table' );
		}

		// add the entry.
		$admin_bar->add_menu(
			array(
				'id'     => 'efml-import-progress',
				'parent' => null,
				'group'  => null,
				'title'  => '<span class="ab-label">' . esc_html__( 'External files:', 'external-files-in-media-library' ) . '</span> <progress value="' . absint( $percentage ) . '" max="100"></progress>',
				'href'   => $url,
				'meta'   => array(
					'title' => $title,
				),
			)
		);
	}

	/**
	 * Add the style for the progress in the admin bar.
	 *
	 * @return void
	 */
	public function add_admin_bar_style(): void {
		// bail if the progress should not be shown.
		if ( ! $this->is_progress_visible() ) {
			return;
		}

		// add the style.
		wp_add_inline_style( 'admin-bar', '#wp-admin-bar-efml-import-progress progress { height: 12px; width: 80px; vertical-align: middle; } #wp-admin-bar-efml-import-progress progress::-moz-progress-bar { background-color: var(--wp-admin-theme-color, #3858e9); } #wp-admin-bar-efml-import-progress progress::-webkit-progress-value { background-color: var(--wp-admin-theme-color, #3858e9); }' );
	}

	/**
	 * Return a description of the run of the given entry in the queue, e.g. for the queue table.
	 *
	 * @param int $run_id The ID of the run.
	 *
	 * @return string
	 */
	public function get_description( int $run_id ): string {
		// get the run.
		$run = $this->get_run( $run_id );

		// bail if run does not exist.
		if ( empty( $run ) ) {
			return '';
		}

		// get the name of the external source, if it is set.
		$source = (string) $run['url'];
		if ( absint( $run['term_id'] ) > 0 ) {
			$term = get_term( absint( $run['term_id'] ), Taxonomy::get_instance()->get_name() );
			if ( $term instanceof \WP_Term ) {
				$source = $term->name;
			}
		}

		// return the description.
		if ( 'sync' === $run['type'] ) {
			/* translators: %1$s will be replaced by the name of the external source. */
			return sprintf( __( 'Synchronization of %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $source ) ) );
		}

		/* translators: %1$s will be replaced by the URL of the directory. */
		return sprintf( __( 'Import from %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $source ) ) );
	}
}
