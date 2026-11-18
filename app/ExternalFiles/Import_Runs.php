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
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;
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
 *
 * The export of the local files in the media library also uses runs (type "export"): the local files are listed
 * page by page and exported one by one to the external sources in the background (see start_export()).
 */
class Import_Runs {
	/**
	 * The context for the encryption of the fields (access data) of a run.
	 *
	 * Fields saved before this context has been used stay readable.
	 */
	public const FIELDS_CONTEXT = 'import_run_fields';

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
	 * Start the export of the local files in the media library to the external sources.
	 *
	 * The files are listed page by page and added as entries of the run to the queue. They are exported one by
	 * one in the background via WP Cron, independent of the settings of the queue. Via WP CLI all files are
	 * exported directly.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_export(): array {
		// get the export object.
		$export_obj = Export::get_instance();

		// bail if the files could not be exported at the moment.
		$problem = $export_obj->get_export_run_problem();
		if ( '' !== $problem ) {
			return array(
				'success' => false,
				'message' => $problem,
				'run_id'  => 0,
			);
		}

		// make sure the database structure exists.
		$this->maybe_install();

		// bail if an export is already running.
		$run = $this->get_active_export_run();
		if ( ! empty( $run ) ) {
			return array(
				'success' => true,
				'message' => __( 'The files in your media library are already being exported in the background.', 'external-files-in-media-library' ),
				'run_id'  => absint( $run['id'] ),
			);
		}

		// get the number of files to export.
		$count = $export_obj->count_local_files_for_export();

		// bail if no file could be exported.
		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => __( 'Your media library contains no local files, which could be exported. Files with a mime type not allowed in the plugin settings are not exported.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// create the run.
		$run = $this->create( $this->get_export_url(), 'export', array(), absint( get_option( 'eml_export_main_source' ) ) );

		// bail if the run could not be created.
		if ( empty( $run ) ) {
			return array(
				'success' => false,
				'message' => __( 'The export could not be started. Check the log for details.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the ID of the run.
		$run_id = absint( $run['id'] );

		// export all files directly via WP CLI.
		if ( Helper::is_cli() ) {
			$this->work( $run_id, $this->get_deadline() );

			// get the result.
			$run = $this->get_run( $run_id );

			// return the result.
			return array(
				'success' => 0 === absint( $run['errors'] ?? 0 ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'message' => sprintf( __( '%1$d files have been exported, %2$d files could not be exported.', 'external-files-in-media-library' ), absint( $run['processed'] ?? 0 ), absint( $run['errors'] ?? 0 ) ),
				'run_id'  => $run_id,
			);
		}

		// list the first files directly, so the progress shows them already.
		$this->list_next_page( $run );

		// process the files in the background.
		$this->schedule();

		// return the result.
		return array(
			'success' => true,
			/* translators: %1$d will be replaced by a number. */
			'message' => sprintf( _n( '%1$d file will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', '%1$d files will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', $count, 'external-files-in-media-library' ), $count ),
			'run_id'  => $run_id,
		);
	}

	/**
	 * Return the unfinished export of the local files.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,mixed>
	 */
	public function get_active_export_run(): array {
		// bail if the table does not exist.
		if ( ! Helper::is_table_available( 'efml_import_runs' ) ) {
			return array();
		}

		// return the run.
		return $this->get_active_run_by_url( $this->get_export_url(), 'export' );
	}

	/**
	 * Start the restore of all exported files as local files at their original path.
	 *
	 * The export is disabled before, so new uploads stay local and no file is exported again. Via WP CLI all
	 * files are restored directly, otherwise the files are listed page by page and restored one by one in the
	 * background.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param bool $delete_on_target True to delete the copies on the external sources after the restore.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_localize( bool $delete_on_target = false ): array {
		// make sure the database structure exists.
		$this->maybe_install();

		// bail if a restore is already running.
		$run = $this->get_active_localize_run();
		if ( ! empty( $run ) ) {
			return array(
				'success' => true,
				'message' => __( 'The exported files are already being restored in the background.', 'external-files-in-media-library' ),
				'run_id'  => absint( $run['id'] ),
			);
		}

		// get the number of files to restore.
		$count = Restore::get_instance()->count_files();

		// bail if no file could be restored.
		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => __( 'Your media library contains no exported files, which could be restored.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// disable the export, so new uploads stay local and no file is exported again.
		if ( 1 === absint( get_option( 'eml_export' ) ) ) {
			update_option( 'eml_export', 0 );
			Log::get_instance()->create( __( 'The export has been disabled for the restore of the exported files.', 'external-files-in-media-library' ), '', 'info' );
		}

		// cancel an unfinished export of the local files.
		$export_run = $this->get_active_export_run();
		if ( ! empty( $export_run ) ) {
			$this->cancel( $export_run );
		}

		// create the run.
		$run = $this->create( $this->get_export_url(), 'localize', array(), 0, array( 'delete_on_target' => $delete_on_target ? 1 : 0 ) );

		// bail if the run could not be created.
		if ( empty( $run ) ) {
			return array(
				'success' => false,
				'message' => __( 'The restore could not be started. Check the log for details.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the ID of the run.
		$run_id = absint( $run['id'] );

		// restore all files directly via WP CLI.
		if ( Helper::is_cli() ) {
			$this->work( $run_id, PHP_INT_MAX );

			// get the result.
			$run = $this->get_run( $run_id );

			// return the result.
			return array(
				'success' => 0 === absint( $run['errors'] ?? 0 ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'message' => sprintf( __( '%1$d files have been restored, %2$d files could not be restored.', 'external-files-in-media-library' ), absint( $run['processed'] ?? 0 ), absint( $run['errors'] ?? 0 ) ),
				'run_id'  => $run_id,
			);
		}

		// list the first files directly, so the progress shows them already.
		$this->list_next_page( $run );

		// restore the first files directly, so the user sees the result at once: the others are restored in the background.
		$this->work( $run_id, min( $this->get_deadline(), microtime( true ) + 10 ) );

		// get the run in its actual state.
		$run = $this->get_run( $run_id );

		// return the result, if all files have been restored: the hint of the finished run shows it.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return array(
				'success' => true,
				'message' => '',
				'run_id'  => $run_id,
			);
		}

		// get the number of remaining files.
		$count = (int) max( 0, absint( $run['total'] ) - absint( $run['processed'] ) - absint( $run['errors'] ) );

		// return the result.
		return array(
			'success' => true,
			/* translators: %1$d will be replaced by a number. */
			'message' => sprintf( _n( '%1$d exported file will be restored in the background. The export has been disabled, so new uploads stay local. The admin bar shows the progress, a click on it opens the queue.', '%1$d exported files will be restored in the background. The export has been disabled, so new uploads stay local. The admin bar shows the progress, a click on it opens the queue.', $count, 'external-files-in-media-library' ), $count ),
			'run_id'  => $run_id,
		);
	}

	/**
	 * Start the export of the sizes of exported images, which have been exported without them.
	 *
	 * Via WP CLI all images are processed directly, otherwise the first ones directly and the others one by one in
	 * the background.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_sizes(): array {
		// make sure the database structure exists.
		$this->maybe_install();

		// bail if a run is already active.
		$run = $this->get_active_sizes_run();
		if ( ! empty( $run ) ) {
			return array(
				'success' => true,
				'message' => __( 'The sizes of the exported images are already being exported in the background.', 'external-files-in-media-library' ),
				'run_id'  => absint( $run['id'] ),
			);
		}

		// bail if no external source supports sizes.
		if ( ! Sizes::get_instance()->is_export_of_sizes_possible() ) {
			return array(
				'success' => false,
				'message' => __( 'None of your external sources with enabled export supports the sizes of images at the moment.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the number of images.
		$count = Sizes::get_instance()->count_images_without_sizes();

		// bail if no image is without sizes.
		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => __( 'All exported images have their sizes on the external sources already.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// create the run.
		$run = $this->create( $this->get_export_url(), 'sizes', array(), 0 );

		// bail if the run could not be created.
		if ( empty( $run ) ) {
			return array(
				'success' => false,
				'message' => __( 'The export of the sizes could not be started. Check the log for details.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the ID of the run.
		$run_id = absint( $run['id'] );

		// process all images directly via WP CLI.
		if ( Helper::is_cli() ) {
			$this->work( $run_id, PHP_INT_MAX );

			// get the result.
			$run = $this->get_run( $run_id );

			// return the result.
			return array(
				'success' => 0 === absint( $run['errors'] ?? 0 ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'message' => sprintf( __( 'The sizes of %1$d images have been exported, %2$d images failed.', 'external-files-in-media-library' ), absint( $run['processed'] ?? 0 ), absint( $run['errors'] ?? 0 ) ),
				'run_id'  => $run_id,
			);
		}

		// list the first images directly, so the progress shows them already.
		$this->list_next_page( $run );

		// process the first images directly, the others in the background.
		$this->work( $run_id, min( $this->get_deadline(), microtime( true ) + 10 ) );

		// get the run in its actual state.
		$run = $this->get_run( $run_id );

		// return the result, if all images have been processed: the hint of the finished run shows it.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return array(
				'success' => true,
				'message' => '',
				'run_id'  => $run_id,
			);
		}

		// return the result.
		return array(
			'success' => true,
			/* translators: %1$d will be replaced by a number. */
			'message' => sprintf( _n( 'The sizes of %1$d exported image will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', 'The sizes of %1$d exported images will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', $count, 'external-files-in-media-library' ), $count ),
			'run_id'  => $run_id,
		);
	}

	/**
	 * Start the deletion of the local copies of exported files, whose local files have been kept.
	 *
	 * Via WP CLI all files are processed directly, otherwise the first ones directly and the others one by one in the
	 * background.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_delete_local(): array {
		// make sure the database structure exists.
		$this->maybe_install();

		// bail if a run is already active.
		$run = $this->get_active_delete_local_run();
		if ( ! empty( $run ) ) {
			return array(
				'success' => true,
				'message' => __( 'The local copies of the exported files are already being deleted in the background.', 'external-files-in-media-library' ),
				'run_id'  => absint( $run['id'] ),
			);
		}

		// bail if this website is a copy.
		if ( Local_Copies::get_instance()->is_paused() ) {
			return array(
				'success' => false,
				'message' => __( 'The local copies could not be deleted, as this website is a copy of another website.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the number of files.
		$count = Local_Copies::get_instance()->count_files();

		// bail if no file has local copies.
		if ( 0 === $count ) {
			Local_Copies::get_instance()->reset_cache();
			return array(
				'success' => false,
				'message' => __( 'Your media library contains no exported files with local copies.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// create the run.
		$run = $this->create( $this->get_export_url(), 'delete_local', array(), 0 );

		// bail if the run could not be created.
		if ( empty( $run ) ) {
			return array(
				'success' => false,
				'message' => __( 'The deletion of the local copies could not be started. Check the log for details.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// get the ID of the run.
		$run_id = absint( $run['id'] );

		// process all files directly via WP CLI.
		if ( Helper::is_cli() ) {
			$this->work( $run_id, PHP_INT_MAX );

			// get the result.
			$run = $this->get_run( $run_id );

			// return the result.
			return array(
				'success' => 0 === absint( $run['errors'] ?? 0 ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'message' => sprintf( __( 'The local copies of %1$d exported files have been deleted, %2$d files kept their local copies.', 'external-files-in-media-library' ), absint( $run['processed'] ?? 0 ), absint( $run['errors'] ?? 0 ) ),
				'run_id'  => $run_id,
			);
		}

		// list the first files directly, so the progress shows them already.
		$this->list_next_page( $run );

		// process the first files directly, the others in the background.
		$this->work( $run_id, min( $this->get_deadline(), microtime( true ) + 10 ) );

		// get the run in its actual state.
		$run = $this->get_run( $run_id );

		// return the result, if all files have been processed: the hint of the finished run shows it.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return array(
				'success' => true,
				'message' => '',
				'run_id'  => $run_id,
			);
		}

		// return the result.
		return array(
			'success' => true,
			/* translators: %1$d will be replaced by a number. */
			'message' => sprintf( _n( 'The local copies of %1$d exported file will be deleted in the background. The admin bar shows the progress, a click on it opens the queue.', 'The local copies of %1$d exported files will be deleted in the background. The admin bar shows the progress, a click on it opens the queue.', $count, 'external-files-in-media-library' ), $count ),
			'run_id'  => $run_id,
		);
	}

	/**
	 * Return the unfinished deletion of the local copies of exported files.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,mixed>
	 */
	public function get_active_delete_local_run(): array {
		// bail if the table does not exist.
		if ( ! Helper::is_table_available( 'efml_import_runs' ) ) {
			return array();
		}

		// return the run.
		return $this->get_active_run_by_url( $this->get_export_url(), 'delete_local' );
	}

	/**
	 * Start the creation of local working copies of all exported images (e.g. for image optimizers).
	 *
	 * Via WP CLI all images are processed directly, otherwise the first ones directly and the others one by one in
	 * the background.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_working_copies(): array {
		return $this->start_working_copy_run(
			'working_copy',
			Working_Copies::get_instance()->count_candidates(),
			array(
				'active' => __( 'The working copies of the exported images are already being saved in the background.', 'external-files-in-media-library' ),
				'none'   => __( 'Your media library contains no exported images without working copy.', 'external-files-in-media-library' ),
				'failed' => __( 'The working copies could not be saved. Check the log for details.', 'external-files-in-media-library' ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'cli'    => __( 'The working copies of %1$d exported images have been saved, %2$d images failed.', 'external-files-in-media-library' ),
				/* translators: %1$d will be replaced by a number. */
				'single' => __( 'The working copy of %1$d exported image will be saved in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ),
				/* translators: %1$d will be replaced by a number. */
				'plural' => __( 'The working copies of %1$d exported images will be saved in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Start the finishing of all local working copies: their changed files are exported and the local files removed.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	public function start_finish_working_copies(): array {
		return $this->start_working_copy_run(
			'working_finish',
			Working_Copies::get_instance()->count_active(),
			array(
				'active' => __( 'The optimized images are already being exported in the background.', 'external-files-in-media-library' ),
				'none'   => __( 'Your media library contains no images with working copy.', 'external-files-in-media-library' ),
				'failed' => __( 'The export of the optimized images could not be started. Check the log for details.', 'external-files-in-media-library' ),
				/* translators: %1$d and %2$d will be replaced by numbers. */
				'cli'    => __( 'The changed files of %1$d working copies have been exported, %2$d working copies failed.', 'external-files-in-media-library' ),
				/* translators: %1$d will be replaced by a number. */
				'single' => __( 'The changed files of %1$d working copy will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ),
				/* translators: %1$d will be replaced by a number. */
				'plural' => __( 'The changed files of %1$d working copies will be exported in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Start a run to create or to finish working copies.
	 *
	 * @param string               $type     The type of the run ("working_copy" or "working_finish").
	 * @param int                  $count    The number of images.
	 * @param array<string,string> $messages The messages.
	 *
	 * @return array{success: bool, message: string, run_id: int}
	 */
	private function start_working_copy_run( string $type, int $count, array $messages ): array {
		// make sure the database structure exists.
		$this->maybe_install();

		// bail if a run of this type is already active.
		$run = $this->get_active_run_by_url( $this->get_export_url(), $type );
		if ( ! empty( $run ) ) {
			return array(
				'success' => true,
				'message' => $messages['active'],
				'run_id'  => absint( $run['id'] ),
			);
		}

		// bail if this website is a copy.
		if ( Site_Copy::get_instance()->is_copy() ) {
			return array(
				'success' => false,
				'message' => __( 'This is not possible, as this website is a copy of another website.', 'external-files-in-media-library' ),
				'run_id'  => 0,
			);
		}

		// bail if no image could be processed.
		if ( 0 === $count ) {
			return array(
				'success' => false,
				'message' => $messages['none'],
				'run_id'  => 0,
			);
		}

		// create the run.
		$run = $this->create( $this->get_export_url(), $type, array(), 0 );

		// bail if the run could not be created.
		if ( empty( $run ) ) {
			return array(
				'success' => false,
				'message' => $messages['failed'],
				'run_id'  => 0,
			);
		}

		// get the ID of the run.
		$run_id = absint( $run['id'] );

		// process all images directly via WP CLI.
		if ( Helper::is_cli() ) {
			$this->work( $run_id, PHP_INT_MAX );

			// get the result.
			$run = $this->get_run( $run_id );

			// return the result.
			return array(
				'success' => 0 === absint( $run['errors'] ?? 0 ),
				'message' => sprintf( $messages['cli'], absint( $run['processed'] ?? 0 ), absint( $run['errors'] ?? 0 ) ),
				'run_id'  => $run_id,
			);
		}

		// list the first images directly, so the progress shows them already.
		$this->list_next_page( $run );

		// process the first images directly, the others in the background.
		$this->work( $run_id, min( $this->get_deadline(), microtime( true ) + 10 ) );

		// get the run in its actual state.
		$run = $this->get_run( $run_id );

		// return the result, if all images have been processed: the hint of the finished run shows it.
		if ( empty( $run ) || $this->is_finished( $run ) ) {
			return array(
				'success' => true,
				'message' => '',
				'run_id'  => $run_id,
			);
		}

		// return the result.
		return array(
			'success' => true,
			'message' => sprintf( 1 === $count ? $messages['single'] : $messages['plural'], $count ),
			'run_id'  => $run_id,
		);
	}

	/**
	 * Return the unfinished export of the sizes of exported images.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,mixed>
	 */
	public function get_active_sizes_run(): array {
		// bail if the table does not exist.
		if ( ! Helper::is_table_available( 'efml_import_runs' ) ) {
			return array();
		}

		// return the run.
		return $this->get_active_run_by_url( $this->get_export_url(), 'sizes' );
	}

	/**
	 * Return the unfinished restore of the exported files.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,mixed>
	 */
	public function get_active_localize_run(): array {
		// bail if the table does not exist.
		if ( ! Helper::is_table_available( 'efml_import_runs' ) ) {
			return array();
		}

		// return the run.
		return $this->get_active_run_by_url( $this->get_export_url(), 'localize' );
	}

	/**
	 * Return the URL, which is used for the runs to export the local files.
	 *
	 * @return string
	 */
	private function get_export_url(): string {
		return trailingslashit( home_url() );
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
	 * @param string                            $type    The type (import, sync or export).
	 * @param array<string,array<string,mixed>> $fields  The fields for the connection.
	 * @param int                               $term_id The ID of the external source.
	 * @param array<string,mixed>               $run_options The options of a restore run (e.g. "delete_on_target").
	 *
	 * @return array<string,mixed>
	 */
	private function create( string $url, string $type, array $fields, int $term_id, array $run_options = array() ): array {
		global $wpdb;

		// the options of an import are not used for exports, a restore uses its own options.
		$options_json = '';
		if ( 'localize' === $type ) {
			$options_json = wp_json_encode( $run_options );
		} elseif ( ! in_array( $type, array( 'export', 'sizes', 'delete_local', 'working_copy', 'working_finish' ), true ) ) {
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
		}

		// add the run.
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->get_table_name(),
			array(
				'time'       => gmdate( 'Y-m-d H:i:s' ),
				'type'       => $type,
				'url'        => $url,
				'url_hash'   => md5( $url ),
				'fields'     => ! empty( $fields ) ? Crypt::get_instance()->encrypt( Helper::get_json( $fields ), self::FIELDS_CONTEXT ) : '',
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
		if ( 'export' === $type ) {
			Log::get_instance()->create( __( 'The export of the local files in the media library has been started. They will be listed and exported one by one.', 'external-files-in-media-library' ), '', 'info' );
		} elseif ( 'localize' === $type ) {
			Log::get_instance()->create( __( 'The restore of the exported files has been started. They will be listed and restored one by one at their original path.', 'external-files-in-media-library' ), '', 'info' );
		} elseif ( 'sizes' === $type ) {
			Log::get_instance()->create( __( 'The export of the sizes of exported images has been started. The images will be listed and processed one by one.', 'external-files-in-media-library' ), '', 'info' );
		} elseif ( 'delete_local' === $type ) {
			Log::get_instance()->create( __( 'The deletion of the local copies of exported files has been started. The files will be listed and processed one by one.', 'external-files-in-media-library' ), '', 'info' );
		} elseif ( 'working_copy' === $type ) {
			Log::get_instance()->create( __( 'The creation of local working copies of the exported images has been started. The images will be listed and processed one by one.', 'external-files-in-media-library' ), '', 'info' );
		} elseif ( 'working_finish' === $type ) {
			Log::get_instance()->create( __( 'The export of the optimized images has been started. The working copies will be listed and processed one by one.', 'external-files-in-media-library' ), '', 'info' );
		} else {
			Log::get_instance()->create( __( 'The files of this directory will be listed and imported one by one.', 'external-files-in-media-library' ), $url, 'info', 2 );
		}

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
	public function get_deadline(): float {
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

		// bail if the files of an export could not be exported at the moment.
		if ( 'export' === $run['type'] && ! $this->is_export_possible( $run ) ) {
			return;
		}

		// bail if the deletion of local copies is paused (on a copy of the website).
		if ( $this->is_paused( $run ) ) {
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
		$fields = json_decode( Crypt::get_instance()->decrypt( $run['fields'], self::FIELDS_CONTEXT ), true );

		// log, if they could not be read: the files are imported without access data then.
		if ( ! is_array( $fields ) ) {
			/* translators: %1$d will be replaced by the ID of the run, %2$d by the ID of the external source. */
			Log::get_instance()->create( sprintf( __( 'The saved access data of the run %1$d (external source %2$d) could not be read. Its files are imported without access data. Start the import or the synchronization again.', 'external-files-in-media-library' ), absint( $run['id'] ?? 0 ), absint( $run['term_id'] ?? 0 ) ), (string) ( $run['url'] ?? '' ), 'error' );
			return array();
		}

		// return the fields.
		return $fields;
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

		// list the local files for an export or the exported files for their restore.
		if ( in_array( $run['type'], array( 'export', 'localize', 'sizes', 'delete_local', 'working_copy', 'working_finish' ), true ) ) {
			$this->list_next_export_page( $run );
			return;
		}

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
	 * List the next page of local files for the given export run and add them to the queue.
	 *
	 * The cursor is the ID of the last listed attachment.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function list_next_export_page( array $run ): void {
		global $wpdb;

		// get the ID.
		$run_id = absint( $run['id'] );

		/**
		 * Filter the number of local files, which are listed at once for their export.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $limit The number of files.
		 */
		$limit = max( 1, absint( apply_filters( 'efml_export_run_page_size', 500 ) ) );

		// get the next files.
		if ( 'localize' === $run['type'] ) {
			$attachment_ids = Restore::get_instance()->get_files( absint( $run['cursor'] ), $limit );
		} elseif ( 'delete_local' === $run['type'] ) {
			$attachment_ids = Local_Copies::get_instance()->get_candidates( absint( $run['cursor'] ), $limit );
		} elseif ( 'working_copy' === $run['type'] ) {
			$attachment_ids = Working_Copies::get_instance()->get_candidates( absint( $run['cursor'] ), $limit );
		} elseif ( 'working_finish' === $run['type'] ) {
			$attachment_ids = Working_Copies::get_instance()->get_active( absint( $run['cursor'] ), $limit );
		} elseif ( 'sizes' === $run['type'] ) {
			$attachment_ids = Sizes::get_instance()->get_images_without_sizes( absint( $run['cursor'] ), $limit );
		} else {
			$attachment_ids = Export::get_instance()->get_local_files_for_export( absint( $run['cursor'] ), $limit );
		}

		// add them to the queue: for the deletion of local copies only the files, which have local copies.
		$inserted = $this->add_export_entries( $run_id, 'delete_local' === $run['type'] ? array_values( array_filter( $attachment_ids, array( Local_Copies::get_instance(), 'has_local_copy' ) ) ) : $attachment_ids, (string) $run['type'] );

		// get the new cursor.
		$cursor = empty( $attachment_ids ) ? (string) $run['cursor'] : (string) end( $attachment_ids );

		// update the run: the listing is complete, if fewer files than requested have been found.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `cursor` = %s, `state` = %s, `total` = `total` + %d WHERE `id` = %d', array( $this->get_table_name(), $cursor, count( $attachment_ids ) < $limit ? 'processing' : 'listing', $inserted, $run_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Add the given attachments as export entries of the given run to the queue and return the count of new entries.
	 *
	 * The attachments get the same marker as files, which are added to the queue for their export.
	 *
	 * @param int            $run_id         The ID of the run.
	 * @param array<int,int> $attachment_ids List of attachment IDs.
	 * @param string         $type           The type of the entries ("export" or "localize").
	 *
	 * @return int
	 */
	private function add_export_entries( int $run_id, array $attachment_ids, string $type = 'export' ): int {
		global $wpdb;

		// bail if list is empty.
		if ( empty( $attachment_ids ) ) {
			return 0;
		}

		// insert them in blocks.
		$time = gmdate( 'Y-m-d H:i:s' );
		foreach ( array_chunk( $attachment_ids, 100 ) as $chunk ) {
			$values = array();
			$args   = array( $this->get_queue_table_name() );
			foreach ( $chunk as $attachment_id ) {
				// get the local URL of this file for the display in the queue.
				$url = (string) wp_get_attachment_url( $attachment_id );

				// add the entry.
				$values[] = '(%s, %s, %s, %s, %s, %s, %s, %d, %d, %d)';
				array_push( $args, $time, $url, md5( $url ), '', 'new', '', $type, $attachment_id, 0, $run_id );

				// bail if this is not an export.
				if ( 'export' !== $type ) {
					continue;
				}

				// mark the attachment as planned for export.
				update_post_meta( $attachment_id, 'efml_export_queued', time() );
				delete_post_meta( $attachment_id, 'efml_export_failed' );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (`time`, `url`, `url_hash`, `fields`, `state`, `options`, `type`, `attachment_id`, `attempts`, `run_id`) VALUES ' . implode( ', ', $values ), $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		// return the count of new entries.
		return count( $attachment_ids );
	}

	/**
	 * Return whether the files of the given export run could be exported at the moment.
	 *
	 * On copies of the website (e.g. staging sites) the run is paused without any change. If the export is not
	 * possible anymore for another reason (e.g. it has been disabled), the run is cancelled with a hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool
	 */
	private function is_export_possible( array $run ): bool {
		// pause on copies of the website: the export is continued, if this website is confirmed as main website.
		if ( $this->is_paused( $run ) ) {
			return false;
		}

		// get the reason, if the files could not be exported.
		$problem = Export::get_instance()->get_export_run_problem();

		// bail if the files could be exported.
		if ( '' === $problem ) {
			return true;
		}

		// cancel the run.
		$this->cancel( $run );

		// create the message.
		/* translators: %1$s will be replaced by the reason. */
		$message = sprintf( __( 'The export of the local files in the media library has been cancelled: %1$s', 'external-files-in-media-library' ), $problem );

		// log this event.
		Log::get_instance()->create( $message, '', 'error' );

		// show it as hint.
		if ( ! Helper::is_cli() ) {
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'error' );
			$transient_obj->set_name( 'efml_export_run_finished' );
			$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong> ' . __( 'The files, which have not been exported yet, stay local files. You can start the export again in the export settings.', 'external-files-in-media-library' ) );
			$transient_obj->save();
		}

		// return false as the files could not be exported.
		return false;
	}

	/**
	 * Return whether the given run is paused.
	 *
	 * Exports are paused on copies of the website (e.g. staging sites), as they must not write to external sources.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return bool
	 */
	private function is_paused( array $run ): bool {
		return in_array( $run['type'], array( 'export', 'delete_local', 'working_copy', 'working_finish' ), true ) && Site_Copy::get_instance()->is_copy();
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

		// export the local file of an export run.
		if ( 'export' === $run['type'] ) {
			$this->process_export_entry( $id, $run );
			return;
		}

		// restore the exported file of a restore run.
		if ( 'localize' === $run['type'] ) {
			$this->process_localize_entry( $id, $run );
			return;
		}

		// export the sizes of the exported image of a sizes run.
		if ( 'sizes' === $run['type'] ) {
			$this->process_sizes_entry( $id, $run );
			return;
		}

		// delete the local copies of the exported file of a run to delete local copies.
		if ( 'delete_local' === $run['type'] ) {
			$this->process_delete_local_entry( $id, $run );
			return;
		}

		// create or finish the working copy of the exported image.
		if ( in_array( $run['type'], array( 'working_copy', 'working_finish' ), true ) ) {
			$this->process_working_copy_entry( $id, $run );
			return;
		}

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
	 * Export the local file of the given entry of the given export run.
	 *
	 * Files, which do not exist anymore or which are already external files, are removed from the run. Files,
	 * which could not be exported, stay local files: they are marked in the media library and their entry stays
	 * in the queue, where it could be processed again.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_export_entry( int $id, array $run ): void {
		global $wpdb;

		// get the attachment ID and the local URL.
		$entry         = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `url`, `attachment_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = absint( $entry['attachment_id'] ?? 0 );
		$url           = (string) ( $entry['url'] ?? '' );

		// remove the entry from the run, if the file does not exist anymore or is already an external file.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `total` = `total` - 1 WHERE `id` = %d AND `total` > 0', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			delete_post_meta( $attachment_id, 'efml_export_queued' );
			return;
		}

		// export the file.
		$success = false;
		try {
			$success = Export::get_instance()->export_file( $attachment_id );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the export of this file:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );
		}

		// update the entry, the counter of the run and the markers of the file.
		if ( $success ) {
			// remove all export entries of this file, also from former exports.
			Queue::get_instance()->remove_export_entries_of_attachment( $attachment_id );
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			delete_post_meta( $attachment_id, 'efml_export_queued' );
			update_post_meta( $attachment_id, 'efml_export_failed', time() );
		}
	}

	/**
	 * Restore the exported file of the given entry of the given restore run at its original path.
	 *
	 * Files, which do not exist anymore or which are not exported files anymore, are removed from the run. Files,
	 * which could not be restored, stay exported files and their entry stays in the queue, where it could be
	 * processed again.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_localize_entry( int $id, array $run ): void {
		global $wpdb;

		// get the attachment ID and the URL.
		$entry         = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `url`, `attachment_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = absint( $entry['attachment_id'] ?? 0 );
		$url           = (string) ( $entry['url'] ?? '' );

		// remove the entry from the run, if the file does not exist anymore or is not an exported file anymore.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `total` = `total` - 1 WHERE `id` = %d AND `total` > 0', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		// get the options of the run.
		$options = json_decode( (string) $run['options'], true );

		// restore the file.
		$success = false;
		try {
			$success = Restore::get_instance()->restore_file( $attachment_id, is_array( $options ) && ! empty( $options['delete_on_target'] ) );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the restore of this file:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );
		}

		// update the entry and the counter of the run.
		if ( $success ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Export the sizes of the exported image of the given entry of the given sizes run.
	 *
	 * Images, which do not exist anymore, are not exported images anymore or have their sizes already, are removed
	 * from the run. Images, whose sizes could not be exported, stay in the queue as error.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_sizes_entry( int $id, array $run ): void {
		global $wpdb;

		// get the attachment ID and the URL.
		$entry         = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `url`, `attachment_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = absint( $entry['attachment_id'] ?? 0 );
		$url           = (string) ( $entry['url'] ?? '' );

		// remove the entry from the run, if the image does not exist anymore, is not exported or has its sizes.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) || Sizes::get_instance()->has_sizes( $attachment_id ) ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `total` = `total` - 1 WHERE `id` = %d AND `total` > 0', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		// export the sizes.
		$success = false;
		try {
			$success = Sizes::get_instance()->export_sizes_of_exported_image( $attachment_id );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the export of the sizes of this image:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );
		}

		// update the entry and the counter of the run.
		if ( $success ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Delete the local copies of the exported file of the given entry of the given run.
	 *
	 * Files, which do not exist anymore, are not exported files anymore or have no local copies, are removed from the
	 * run. Files, which keep their local copies (e.g. as they could not be loaded from their external source), stay in
	 * the queue as error.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_delete_local_entry( int $id, array $run ): void {
		global $wpdb;

		// get the attachment ID and the URL.
		$entry         = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `url`, `attachment_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = absint( $entry['attachment_id'] ?? 0 );
		$url           = (string) ( $entry['url'] ?? '' );

		// remove the entry from the run, if the file does not exist anymore, is not exported or has no local copies.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) || ! Local_Copies::get_instance()->has_local_copy( $attachment_id ) ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `total` = `total` - 1 WHERE `id` = %d AND `total` > 0', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		// delete the local copies.
		$success = false;
		try {
			$success = Local_Copies::get_instance()->delete_local_copies( $attachment_id );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the deletion of the local copies of this file:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );
		}

		// update the entry and the counter of the run.
		if ( $success ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Create or finish the working copy of the exported image of the given entry of the given run.
	 *
	 * Images, which do not exist anymore or need nothing to do, are removed from the run. Images, which could not be
	 * processed, stay in the queue as error.
	 *
	 * @param int                 $id  The ID of the entry.
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function process_working_copy_entry( int $id, array $run ): void {
		global $wpdb;

		// get the attachment ID and the URL.
		$entry         = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT `url`, `attachment_id` FROM %i WHERE `id` = %d', array( $this->get_queue_table_name(), $id ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = absint( $entry['attachment_id'] ?? 0 );
		$url           = (string) ( $entry['url'] ?? '' );

		// get the object.
		$working_copies_obj = Working_Copies::get_instance();

		// get whether the working copy is created.
		$create = 'working_copy' === $run['type'];

		// remove the entry from the run, if the image does not exist anymore or nothing has to be done.
		if ( 0 === $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || $create === $working_copies_obj->is_active( $attachment_id ) ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `total` = `total` - 1 WHERE `id` = %d AND `total` > 0', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return;
		}

		// create or finish it.
		$success = false;
		try {
			$success = $create ? $working_copies_obj->create( $attachment_id ) : $working_copies_obj->finish( $attachment_id );
		} catch ( \Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during the work with the working copy of this image:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );
		}

		// update the entry and the counter of the run.
		if ( $success ) {
			$wpdb->delete( $this->get_queue_table_name(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `processed` = `processed` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$wpdb->update( $this->get_queue_table_name(), array( 'state' => 'error' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `errors` = `errors` + 1 WHERE `id` = %d', array( $this->get_table_name(), absint( $run['id'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
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

		// detect whether entries have been deleted in the queue: the run is cancelled then (not for exports, whose
		// entries are also removed, if their files are deleted, and whose other files are exported nevertheless).
		$state = ! in_array( $run['type'], array( 'export', 'localize', 'sizes', 'delete_local', 'working_copy', 'working_finish' ), true ) && absint( $run['processed'] ) + absint( $run['errors'] ) < absint( $run['total'] ) ? 'cancelled' : 'done';

		// set the new state.
		$this->update( $run_id, array( 'state' => $state ) );
		$run['state'] = $state;

		// log the result of an export and show it as hint.
		if ( 'export' === $run['type'] ) {
			$this->finish_export( $run );
		} elseif ( 'localize' === $run['type'] ) {
			$this->finish_localize( $run );
		} elseif ( 'sizes' === $run['type'] ) {
			$this->finish_sizes( $run );
		} elseif ( 'delete_local' === $run['type'] ) {
			$this->finish_delete_local( $run );
		} elseif ( in_array( $run['type'], array( 'working_copy', 'working_finish' ), true ) ) {
			$this->finish_working_copies( $run );
		} else {
			// log this event.
			/* translators: %1$d, %2$d and %3$d will be replaced by numbers. */
			Log::get_instance()->create( sprintf( __( 'Import of the directory has been finished: %1$d files imported, %2$d errors, %3$d files skipped.', 'external-files-in-media-library' ), absint( $run['processed'] ), absint( $run['errors'] ), absint( $run['skipped'] ) ), (string) $run['url'], 'cancelled' === $state || absint( $run['errors'] ) > 0 ? 'error' : 'success', 1, Import::get_instance()->get_identifier() );
		}

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
	 * Log the result of the given finished export run and show it as hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish_export( array $run ): void {
		// get the counts.
		$exported = absint( $run['processed'] );
		$errors   = absint( $run['errors'] );

		// create the message.
		/* translators: %1$d and %2$d will be replaced by numbers. */
		$message = sprintf( __( 'The export of the local files in the media library has been finished: %1$d files exported, %2$d files not exported.', 'external-files-in-media-library' ), $exported, $errors );

		// log this event.
		Log::get_instance()->create( $message, '', $errors > 0 ? 'error' : 'success' );

		// bail if this is WP CLI, which shows the result itself.
		if ( Helper::is_cli() ) {
			return;
		}

		// add the details, if files could not be exported.
		$details = '';
		if ( $errors > 0 ) {
			/* translators: %1$s and %2$s will be replaced by URLs. */
			$details = ' ' . sprintf( __( 'The files, which have not been exported, stay local files and are marked in your media library. The reasons are listed in the <a href="%1$s">log</a>. In the <a href="%2$s">queue</a> you can export them again, e.g. after you solved the problem.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ), esc_url( add_query_arg( array( 'errors' => 1 ), Settings::get_instance()->get_url( 'eml_queue_table' ) ) ) );
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( $errors > 0 ? 'error' : 'success' );
		$transient_obj->set_name( 'efml_export_run_finished' );
		$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
		$transient_obj->save();
	}

	/**
	 * Log the result of the given finished restore run and show it as hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish_localize( array $run ): void {
		// get the counts.
		$restored = absint( $run['processed'] );
		$errors   = absint( $run['errors'] );

		// create the message.
		/* translators: %1$d and %2$d will be replaced by numbers. */
		$message = sprintf( __( 'The restore of the exported files has been finished: %1$d files restored, %2$d files not restored.', 'external-files-in-media-library' ), $restored, $errors );

		// log this event.
		Log::get_instance()->create( $message, '', $errors > 0 ? 'error' : 'success' );

		// bail if this is WP CLI, which shows the result itself.
		if ( Helper::is_cli() ) {
			return;
		}

		// add the details.
		if ( $errors > 0 ) {
			/* translators: %1$s and %2$s will be replaced by URLs. */
			$details = ' ' . sprintf( __( 'The files, which have not been restored, stay exported files. The reasons are listed in the <a href="%1$s">log</a>. In the <a href="%2$s">queue</a> you can restore them again, e.g. after you solved the problem.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ), esc_url( add_query_arg( array( 'errors' => 1 ), Settings::get_instance()->get_url( 'eml_queue_table' ) ) ) );
		} else {
			$details = ' ' . __( 'All files are local files at their original path again. You can deactivate the plugin now without broken files in your contents.', 'external-files-in-media-library' );
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( $errors > 0 ? 'error' : 'success' );
		$transient_obj->set_name( 'efml_localize_run_finished' );
		$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
		$transient_obj->save();
	}

	/**
	 * Log the result of the given finished sizes run and show it as hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish_sizes( array $run ): void {
		// get the counts.
		$processed = absint( $run['processed'] );
		$errors    = absint( $run['errors'] );

		// create the message.
		/* translators: %1$d and %2$d will be replaced by numbers. */
		$message = sprintf( __( 'The export of the sizes of exported images has been finished: %1$d images with exported sizes, %2$d images failed.', 'external-files-in-media-library' ), $processed, $errors );

		// log this event.
		Log::get_instance()->create( $message, '', $errors > 0 ? 'error' : 'success' );

		// bail if this is WP CLI, which shows the result itself.
		if ( Helper::is_cli() ) {
			return;
		}

		// add the details, if images failed.
		$details = '';
		if ( $errors > 0 ) {
			/* translators: %1$s and %2$s will be replaced by URLs. */
			$details = ' ' . sprintf( __( 'The images, which failed, keep their delivery as before. The reasons are listed in the <a href="%1$s">log</a>. In the <a href="%2$s">queue</a> you can process them again, e.g. after you solved the problem.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ), esc_url( add_query_arg( array( 'errors' => 1 ), Settings::get_instance()->get_url( 'eml_queue_table' ) ) ) );
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( $errors > 0 ? 'error' : 'success' );
		$transient_obj->set_name( 'efml_sizes_run_finished' );
		$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
		$transient_obj->save();
	}

	/**
	 * Log the result of the given finished run to delete local copies and show it as hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish_delete_local( array $run ): void {
		// get the counts.
		$processed = absint( $run['processed'] );
		$errors    = absint( $run['errors'] );

		// forget whether local copies exist.
		Local_Copies::get_instance()->reset_cache();

		// create the message.
		/* translators: %1$d and %2$d will be replaced by numbers. */
		$message = sprintf( __( 'The deletion of the local copies of exported files has been finished: %1$d files without local copies now, %2$d files kept their local copies.', 'external-files-in-media-library' ), $processed, $errors );

		// log this event.
		Log::get_instance()->create( $message, '', $errors > 0 ? 'error' : 'success' );

		// bail if this is WP CLI, which shows the result itself.
		if ( Helper::is_cli() ) {
			return;
		}

		// add the details, if files kept their local copies.
		$details = '';
		if ( $errors > 0 ) {
			/* translators: %1$s and %2$s will be replaced by URLs. */
			$details = ' ' . sprintf( __( 'The files, which kept their local copies, are unchanged. The reasons are listed in the <a href="%1$s">log</a>. In the <a href="%2$s">queue</a> you can process them again, e.g. after you solved the problem.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ), esc_url( add_query_arg( array( 'errors' => 1 ), Settings::get_instance()->get_url( 'eml_queue_table' ) ) ) );
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( $errors > 0 ? 'error' : 'success' );
		$transient_obj->set_name( 'efml_delete_local_run_finished' );
		$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
		$transient_obj->save();
	}

	/**
	 * Log the result of the given finished run to create or finish working copies and show it as hint.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return void
	 */
	private function finish_working_copies( array $run ): void {
		// get the counts.
		$processed = absint( $run['processed'] );
		$errors    = absint( $run['errors'] );

		// create the message.
		if ( 'working_copy' === $run['type'] ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$message = sprintf( __( 'The local working copies of the exported images have been saved: %1$d images could be optimized now, %2$d images failed. The changed files are exported as soon as an image optimizer reports the end of its work, otherwise via "Export optimized images" in the export settings.', 'external-files-in-media-library' ), $processed, $errors );
		} else {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$message = sprintf( __( 'The optimized images have been exported: %1$d working copies finished, %2$d working copies failed.', 'external-files-in-media-library' ), $processed, $errors );
		}

		// log this event.
		Log::get_instance()->create( $message, '', $errors > 0 ? 'error' : 'success' );

		// bail if this is WP CLI, which shows the result itself.
		if ( Helper::is_cli() ) {
			return;
		}

		// add the details, if images failed.
		$details = '';
		if ( $errors > 0 ) {
			/* translators: %1$s and %2$s will be replaced by URLs. */
			$details = ' ' . sprintf( __( 'The reasons are listed in the <a href="%1$s">log</a>. In the <a href="%2$s">queue</a> you can process them again, e.g. after you solved the problem.', 'external-files-in-media-library' ), esc_url( Helper::get_log_url() ), esc_url( add_query_arg( array( 'errors' => 1 ), Settings::get_instance()->get_url( 'eml_queue_table' ) ) ) );
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( $errors > 0 ? 'error' : 'success' );
		$transient_obj->set_name( 'efml_working_copies_run_finished' );
		$transient_obj->set_message( '<strong>' . esc_html( $message ) . '</strong>' . $details );
		$transient_obj->save();
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

		// remove the markers of the files, which are planned for export by this run.
		if ( 'export' === $run['type'] ) {
			foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT `attachment_id` FROM %i WHERE `run_id` = %d AND `state` IN (%s, %s)', array( $this->get_queue_table_name(), absint( $run['id'] ), 'new', 'processing' ) ) ) as $attachment_id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				delete_post_meta( absint( $attachment_id ), 'efml_export_queued' );
			}
		}

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

			// do not process runs, which are actually imported in the dialog or which are paused.
			if ( $this->is_in_dialog( $run ) || $this->is_paused( $run ) ) {
				continue;
			}

			// work on it.
			$this->work_in_background( $run, $deadline );
		}

		// remove the finished runs, which have no entries in the queue anymore.
		$this->remove_finished_runs();

		// plan the next processing, if unfinished runs exist (later, if they are only imported in the dialog or paused).
		$delay = 0;
		$runs  = $this->get_active_runs();
		foreach ( $runs as $index => $run ) {
			$run_delay = $this->get_delay( $run );
			$delay     = 0 === $index ? $run_delay : min( $delay, $run_delay );
		}
		if ( ! empty( $runs ) ) {
			$this->schedule( $delay );
		}
	}

	/**
	 * Return the delay in seconds, after which the given unfinished run should be processed again.
	 *
	 * @param array<string,mixed> $run The run.
	 *
	 * @return int
	 */
	private function get_delay( array $run ): int {
		// check runs in the dialog again, after the dialog could have been closed.
		if ( $this->is_in_dialog( $run ) ) {
			return self::DIALOG_TIMEOUT;
		}

		// check paused runs again hourly.
		if ( $this->is_paused( $run ) ) {
			return HOUR_IN_SECONDS;
		}

		// process the run now.
		return 0;
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
		// without alias for the deleted table, as "DELETE <alias> FROM" exists only in MySQL and MariaDB, not in SQLite (e.g. WordPress Playground).
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `state` IN (%s, %s, %s) AND NOT EXISTS (SELECT 1 FROM %i q WHERE q.`run_id` = %i.`id`)', array( $this->get_table_name(), 'done', 'failed', 'cancelled', $this->get_queue_table_name(), $this->get_table_name() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Plan the processing of unfinished runs again (e.g. after the activation of this plugin).
	 *
	 * @return void
	 */
	public function resume(): void {
		// bail if the table does not exist (yet).
		global $wpdb;
		if ( $this->get_table_name() !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->get_table_name() ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return;
		}

		// plan the processing, if runs are unfinished.
		if ( ! empty( $this->get_active_runs() ) ) {
			$this->schedule();
		}
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
	 * The values "exports", "export_total" and "export_done" contain the part of the exports of local files,
	 * the values "restores", "restore_total" and "restore_done" the part of the restores of exported files and the
	 * values "size_runs", "size_total" and "size_done" the part of the exports of sizes of exported images and the values
	 * "cleanups", "cleanup_total" and "cleanup_done" the part of the deletions of local copies of exported files and the
	 * values "working_runs", "working_total" and "working_done" the part of the creation and the finishing of working
	 * copies.
	 *
	 * @return array{runs: int, total: int, done: int, listing: int, exports: int, export_total: int, export_done: int, restores: int, restore_total: int, restore_done: int, size_runs: int, size_total: int, size_done: int, cleanups: int, cleanup_total: int, cleanup_done: int, working_runs: int, working_total: int, working_done: int}
	 */
	public function get_progress(): array {
		global $wpdb;

		// get the sums.
		// with CASE instead of IF(), which exists only in MySQL and MariaDB, not in SQLite (e.g. WordPress Playground).
		$result = Helper::get_db_result( $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS `runs`, SUM(`total`) AS `total`, SUM(`processed` + `errors`) AS `done`, SUM(`state` = %s) AS `listing`, SUM(`type` = %s) AS `exports`, SUM(CASE WHEN `type` = %s THEN `total` ELSE 0 END) AS `export_total`, SUM(CASE WHEN `type` = %s THEN `processed` + `errors` ELSE 0 END) AS `export_done`, SUM(`type` = %s) AS `restores`, SUM(CASE WHEN `type` = %s THEN `total` ELSE 0 END) AS `restore_total`, SUM(CASE WHEN `type` = %s THEN `processed` + `errors` ELSE 0 END) AS `restore_done`, SUM(`type` = %s) AS `size_runs`, SUM(CASE WHEN `type` = %s THEN `total` ELSE 0 END) AS `size_total`, SUM(CASE WHEN `type` = %s THEN `processed` + `errors` ELSE 0 END) AS `size_done`, SUM(`type` = %s) AS `cleanups`, SUM(CASE WHEN `type` = %s THEN `total` ELSE 0 END) AS `cleanup_total`, SUM(CASE WHEN `type` = %s THEN `processed` + `errors` ELSE 0 END) AS `cleanup_done`, SUM(`type` IN (%s, %s)) AS `working_runs`, SUM(CASE WHEN `type` IN (%s, %s) THEN `total` ELSE 0 END) AS `working_total`, SUM(CASE WHEN `type` IN (%s, %s) THEN `processed` + `errors` ELSE 0 END) AS `working_done` FROM %i WHERE `state` IN (%s, %s)', array( 'listing', 'export', 'export', 'export', 'localize', 'localize', 'localize', 'sizes', 'sizes', 'sizes', 'delete_local', 'delete_local', 'delete_local', 'working_copy', 'working_finish', 'working_copy', 'working_finish', 'working_copy', 'working_finish', $this->get_table_name(), 'listing', 'processing' ) ), ARRAY_A ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return the values.
		return array(
			'runs'          => absint( $result['runs'] ?? 0 ),
			'total'         => absint( $result['total'] ?? 0 ),
			'done'          => absint( $result['done'] ?? 0 ),
			'listing'       => absint( $result['listing'] ?? 0 ),
			'exports'       => absint( $result['exports'] ?? 0 ),
			'export_total'  => absint( $result['export_total'] ?? 0 ),
			'export_done'   => absint( $result['export_done'] ?? 0 ),
			'restores'      => absint( $result['restores'] ?? 0 ),
			'restore_total' => absint( $result['restore_total'] ?? 0 ),
			'restore_done'  => absint( $result['restore_done'] ?? 0 ),
			'size_runs'     => absint( $result['size_runs'] ?? 0 ),
			'size_total'    => absint( $result['size_total'] ?? 0 ),
			'size_done'     => absint( $result['size_done'] ?? 0 ),
			'cleanups'      => absint( $result['cleanups'] ?? 0 ),
			'cleanup_total' => absint( $result['cleanup_total'] ?? 0 ),
			'cleanup_done'  => absint( $result['cleanup_done'] ?? 0 ),
			'working_runs'  => absint( $result['working_runs'] ?? 0 ),
			'working_total' => absint( $result['working_total'] ?? 0 ),
			'working_done'  => absint( $result['working_done'] ?? 0 ),
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

		// create the title-attribute for the imports and the exports.
		$titles = array();
		if ( $progress['runs'] > $progress['exports'] + $progress['restores'] + $progress['size_runs'] + $progress['cleanups'] + $progress['working_runs'] ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( '%1$d of %2$d external files imported', 'external-files-in-media-library' ), $progress['done'] - $progress['export_done'] - $progress['restore_done'] - $progress['size_done'] - $progress['cleanup_done'] - $progress['working_done'], $progress['total'] - $progress['export_total'] - $progress['restore_total'] - $progress['size_total'] - $progress['cleanup_total'] - $progress['working_total'] );
		}
		if ( $progress['working_runs'] > 0 ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( 'working copies of %1$d of %2$d exported images processed', 'external-files-in-media-library' ), $progress['working_done'], $progress['working_total'] );
		}
		if ( $progress['cleanups'] > 0 ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( 'local copies of %1$d of %2$d exported files deleted', 'external-files-in-media-library' ), $progress['cleanup_done'], $progress['cleanup_total'] );
		}
		if ( $progress['size_runs'] > 0 ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( 'sizes of %1$d of %2$d exported images exported', 'external-files-in-media-library' ), $progress['size_done'], $progress['size_total'] );
		}
		if ( $progress['exports'] > 0 ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( '%1$d of %2$d local files exported', 'external-files-in-media-library' ), $progress['export_done'], $progress['export_total'] );
		}
		if ( $progress['restores'] > 0 ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			$titles[] = sprintf( __( '%1$d of %2$d exported files restored', 'external-files-in-media-library' ), $progress['restore_done'], $progress['restore_total'] );
		}
		$title = implode( ', ', $titles );
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
		if ( 'localize' === $run['type'] ) {
			return esc_html__( 'Restore of the exported files at their original path', 'external-files-in-media-library' );
		}
		if ( 'sizes' === $run['type'] ) {
			return esc_html__( 'Export of the sizes of exported images', 'external-files-in-media-library' );
		}
		if ( 'delete_local' === $run['type'] ) {
			return esc_html__( 'Deletion of the local copies of exported files', 'external-files-in-media-library' );
		}
		if ( 'working_copy' === $run['type'] ) {
			return esc_html__( 'Local working copies of the exported images', 'external-files-in-media-library' );
		}
		if ( 'working_finish' === $run['type'] ) {
			return esc_html__( 'Export of the optimized images', 'external-files-in-media-library' );
		}
		if ( 'export' === $run['type'] ) {
			// return a general description, if the main external source is not known.
			if ( (string) $run['url'] === $source ) {
				return esc_html__( 'Export of the local files', 'external-files-in-media-library' );
			}

			/* translators: %1$s will be replaced by the name of the external source. */
			return sprintf( __( 'Export of the local files to %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $source ) ) );
		}
		if ( 'sync' === $run['type'] ) {
			/* translators: %1$s will be replaced by the name of the external source. */
			return sprintf( __( 'Synchronization of %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $source ) ) );
		}

		/* translators: %1$s will be replaced by the URL of the directory. */
		return sprintf( __( 'Import from %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $source ) ) );
	}
}
