<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Admin_Bar;

/**
 * Object to test the import runs, which import directories file by file, also in the background.
 *
 * The tests use local directories, as their protocol supports file lists.
 */
class Import_Runs extends externalFilesTests {
	/**
	 * The local directory with the files.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		global $wpdb;

		parent::set_up();

		// start without runs and entries in the queue.
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'efml_import_runs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'eml_queue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// create the directory in the uploads, which are allowed for local files.
		$upload_dir      = wp_upload_dir();
		$this->directory = trailingslashit( $upload_dir['basedir'] ) . 'efml-runs-' . strtolower( wp_generate_password( 8, false, false ) ) . '/';
		wp_mkdir_p( $this->directory );

		// list only few files per page to test the paging.
		add_filter( 'efml_file_list_page_size', array( $this, 'get_page_size' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wpdb;

		remove_filter( 'efml_file_list_page_size', array( $this, 'get_page_size' ) );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_all_filters( 'efml_background_import_threshold' );
		remove_all_filters( 'efml_background_import_direct_count' );
		wp_clear_scheduled_hook( \ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::CRON_HOOK );
		Helper::get_wp_filesystem()->delete( $this->directory, true );

		// remove the runs and the entries of the queue.
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'efml_import_runs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'eml_queue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		parent::tear_down();
	}

	/**
	 * Return the page size for the file lists.
	 *
	 * @return int
	 */
	public function get_page_size(): int {
		return 2;
	}

	/**
	 * Add the given count of files to the test directory.
	 *
	 * @param int    $count The count.
	 * @param string $prefix The prefix of the file names.
	 *
	 * @return array<int,string> List of the URLs of the files.
	 */
	private function add_files( int $count, string $prefix = 'file' ): array {
		$urls = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$file = $this->directory . $prefix . '-' . $i . '.jpg';
			copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', $file );
			$urls[] = Helper::get_file_url_from_path( $file );
		}
		return $urls;
	}

	/**
	 * Return the runs.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_runs(): array {
		global $wpdb;
		return (array) $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'efml_import_runs', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Return the count of entries of runs in the queue.
	 *
	 * @param string $state The state.
	 *
	 * @return int
	 */
	private function count_run_entries( string $state = '' ): int {
		global $wpdb;
		if ( '' !== $state ) {
			return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `run_id` > 0 AND `state` = %s', array( $wpdb->prefix . 'eml_queue', $state ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `run_id` > 0', array( $wpdb->prefix . 'eml_queue' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Assert that all given URLs are imported once.
	 *
	 * @param array<int,string> $urls The URLs.
	 *
	 * @return void
	 */
	private function assert_imported( array $urls ): void {
		foreach ( $urls as $url ) {
			$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $url ), $url );
		}
	}

	/**
	 * Test the import of a directory: all files are imported via the run in the request.
	 *
	 * @return void
	 */
	public function test_import_of_directory(): void {
		$urls  = $this->add_files( 5 );
		$count = $this->get_media_file_count();

		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );

		$this->assert_imported( $urls );
		$this->assertSame( $count + 5, $this->get_media_file_count() );
		$this->assertSame( 0, $this->count_run_entries() );

		// the run is done and all files belong to its job.
		$runs = $this->get_runs();
		$this->assertCount( 1, $runs );
		$this->assertSame( 'done', $runs[0]['state'] );
		$this->assertSame( '5', $runs[0]['total'] );
		$this->assertSame( '5', $runs[0]['processed'] );
		foreach ( $urls as $url ) {
			$this->assertSame( $runs[0]['job_id'], get_post_meta( Files::get_instance()->get_file_by_url( $url )->get_id(), 'eml_job_id', true ) );
		}

		// finished runs without entries are removed.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->remove_finished_runs();
		$this->assertCount( 0, $this->get_runs() );
	}

	/**
	 * Test the import of a directory, whose files are partially imported before: only the new files are imported.
	 *
	 * @return void
	 */
	public function test_existing_files_are_skipped(): void {
		$urls = $this->add_files( 3 );
		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );
		$count = $this->get_media_file_count();

		// add more files and import the directory again.
		$new_urls = $this->add_files( 2, 'new' );
		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );

		$this->assert_imported( array_merge( $urls, $new_urls ) );
		$this->assertSame( $count + 2, $this->get_media_file_count() );

		// the second run has skipped the existing files.
		$runs = $this->get_runs();
		$this->assertSame( '2', $runs[1]['total'] );
		$this->assertSame( '3', $runs[1]['skipped'] );

		// a third import has nothing to import.
		Results::get_instance()->prepare();
		$this->assertFalse( Import::get_instance()->add_url( 'file://' . $this->directory ) );
		$this->assertSame( $count + 2, $this->get_media_file_count() );
	}

	/**
	 * Test the import, if the table for the runs is missing (e.g. after updating the files of a development
	 * version without the update of the plugin): the table is created on the first import.
	 *
	 * @return void
	 */
	public function test_import_with_missing_table(): void {
		global $wpdb;
		$urls = $this->add_files( 3 );
		$wpdb->query( 'DROP TABLE ' . $wpdb->prefix . 'efml_import_runs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_option( 'efml_import_runs_db_version' );

		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );

		$this->assert_imported( $urls );
		$this->assertCount( 1, $this->get_runs() );
		$this->assertSame( '1', get_option( 'efml_import_runs_db_version' ) );
	}

	/**
	 * Test the import of an empty directory.
	 *
	 * @return void
	 */
	public function test_import_of_empty_directory(): void {
		$this->assertFalse( Import::get_instance()->add_url( 'file://' . $this->directory ) );
		$this->assertSame( 'done', $this->get_runs()[0]['state'] );
	}

	/**
	 * Test the import of a large directory in the dialog: the first files are imported directly, all others in the
	 * background, which is shown in the admin bar.
	 *
	 * @return void
	 */
	public function test_import_in_dialog_continues_in_background(): void {
		$this->do_login();
		$urls  = $this->add_files( 9 );
		$count = $this->get_media_file_count();

		// simulate the request of the import dialog with a low threshold.
		if ( ! defined( 'EFML_URL_IMPORT_RUNNING' ) ) {
			define( 'EFML_URL_IMPORT_RUNNING', true );
		}
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'efml_background_import_threshold',
			static function (): int {
				return 3;
			}
		);
		add_filter(
			'efml_background_import_direct_count',
			static function (): int {
				return 2;
			}
		);
		Results::get_instance()->prepare();

		// run the import.
		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		// only 2 files are imported, the run continues in the background.
		$this->assertSame( $count + 2, $this->get_media_file_count() );
		$runs = $this->get_runs();
		$this->assertSame( '1', $runs[0]['background'] );
		$this->assertNotFalse( wp_next_scheduled( \ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::CRON_HOOK ) );
		$texts = '';
		foreach ( Results::get_instance()->get_results() as $result ) {
			$texts .= $result->get_text();
		}
		$this->assertStringContainsString( 'background', $texts );

		// the progress is shown in the admin bar.
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		add_filter( 'show_admin_bar', '__return_true' );
		$admin_bar = new WP_Admin_Bar();
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->add_progress_to_admin_bar( $admin_bar );
		remove_filter( 'show_admin_bar', '__return_true' );
		$this->assertNotNull( $admin_bar->get_node( 'efml-import-progress' ) );

		// process the rest in the background.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->process();

		$this->assert_imported( $urls );
		$this->assertSame( $count + 9, $this->get_media_file_count() );
		$this->assertSame( 0, $this->count_run_entries() );
		$this->assertCount( 0, $this->get_runs() );
		$this->assertSame( 0, \ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->get_progress()['runs'] );
	}

	/**
	 * Test the import of a small directory in the dialog: the dialog loads more until all files are imported.
	 *
	 * @return void
	 */
	public function test_import_in_dialog_without_background(): void {
		$this->do_login();
		$urls = $this->add_files( 4 );

		// simulate the request of the import dialog.
		if ( ! defined( 'EFML_URL_IMPORT_RUNNING' ) ) {
			define( 'EFML_URL_IMPORT_RUNNING', true );
		}
		add_filter( 'wp_doing_ajax', '__return_true' );
		update_option( 'eml_import_url_loading_more_' . get_current_user_id(), 0 );

		$this->assertTrue( Import::get_instance()->add_url( 'file://' . $this->directory ) );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$this->assert_imported( $urls );
		$this->assertSame( '0', $this->get_runs()[0]['background'] );
		$this->assertSame( 0, absint( get_option( 'eml_import_url_loading_more_' . get_current_user_id() ) ) );
	}

	/**
	 * Test that failed files stay in the queue as errors and could be imported again via the queue.
	 *
	 * @return void
	 */
	public function test_failed_file_stays_in_queue(): void {
		$this->add_files( 2 );

		// the second file could not be imported.
		file_put_contents( $this->directory . 'file-2.jpg', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->assertTrue( @Import::get_instance()->add_url( 'file://' . $this->directory ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$this->assertSame( 1, $this->count_run_entries( 'error' ) );
		$this->assertSame( '1', $this->get_runs()[0]['errors'] );

		// repair the file and import it again via the queue table.
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', $this->directory . 'file-2.jpg' );
		$entry = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->get_urls( 'error', true );
		$this->assertCount( 1, $entry );
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->process_entry_by_id( absint( $entry[0]['id'] ) );

		$this->assert_imported( array( Helper::get_file_url_from_path( $this->directory . 'file-2.jpg' ) ) );
		$this->assertSame( 0, $this->count_run_entries() );
		$this->assertSame( '0', $this->get_runs()[0]['errors'] );
	}

	/**
	 * Test that clearing the queue cancels the runs.
	 *
	 * @return void
	 */
	public function test_clearing_the_queue_cancels_runs(): void {
		$this->add_files( 6 );

		// add entries via a run, which is not finished.
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'efml_import_runs', array( 'url' => 'file://' . $this->directory, 'url_hash' => md5( 'file://' . $this->directory ), 'fields' => '', 'options' => '', 'cursor' => '2', 'state' => 'listing', 'background' => 1, 'total' => 2 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$run_id = $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'eml_queue', array( 'url' => $this->directory . 'file-1.jpg', 'url_hash' => md5( $this->directory . 'file-1.jpg' ), 'fields' => '', 'state' => 'new', 'options' => '', 'run_id' => $run_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->clear();

		$this->assertCount( 0, $this->get_runs() );
		$this->assertSame( 0, $this->count_run_entries() );
	}

	/**
	 * Test the synchronization of a directory via run: changed files are updated and files, which do not exist
	 * anymore, are deleted.
	 *
	 * @return void
	 */
	public function test_synchronization(): void {
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );

		$urls    = $this->add_files( 4 );
		$url     = 'file://' . $this->directory;
		$term_id = Taxonomy::get_instance()->add( 'local', $url, array() );
		$sync    = Synchronization::get_instance();

		// first synchronization imports all files.
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		$this->assert_imported( $urls );
		$this->assertFalse( $sync->is_running() );

		// remove a file and synchronize again: the file is deleted, the others are kept.
		$count = $this->get_media_file_count();
		wp_delete_file( Helper::get_local_path_from_url( $urls[0] ) );
		$completed = did_action( 'efml_sync_completed' );
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );

		$this->assertFalse( Files::get_instance()->get_file_by_url( $urls[0] ) );
		$this->assert_imported( array_slice( $urls, 1 ) );
		$this->assertSame( $count - 1, $this->get_media_file_count() );
		$this->assertSame( $completed + 1, did_action( 'efml_sync_completed' ) );
		$this->assertFalse( $sync->is_running() );

		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
	}

	/**
	 * Test the synchronization, which is continued in the background: files are only deleted at its end.
	 *
	 * @return void
	 */
	public function test_synchronization_in_background(): void {
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );

		$urls    = $this->add_files( 4 );
		$url     = 'file://' . $this->directory;
		$term_id = Taxonomy::get_instance()->add( 'local', $url, array() );
		$sync    = Synchronization::get_instance();
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		$count = $this->get_media_file_count();

		// remove a file and synchronize again, but without time to work on it in this request.
		wp_delete_file( Helper::get_local_path_from_url( $urls[0] ) );
		add_filter( 'efml_import_run_time_limit', '__return_zero' );
		$completed = did_action( 'efml_sync_completed' );
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		remove_filter( 'efml_import_run_time_limit', '__return_zero' );

		// the synchronization is not completed, so nothing is deleted yet.
		$this->assertSame( $completed, did_action( 'efml_sync_completed' ) );
		$this->assertSame( $count, $this->get_media_file_count() );
		$this->assertFalse( $sync->is_running() );

		// complete it in the background.
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->process();

		$this->assertSame( $completed + 1, did_action( 'efml_sync_completed' ) );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $urls[0] ) );
		$this->assert_imported( array_slice( $urls, 1 ) );
		$this->assertSame( $count - 1, $this->get_media_file_count() );
		$this->assertFalse( $sync->is_running() );

		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
	}

	/**
	 * Test the synchronization with an error during the listing: no files are deleted.
	 *
	 * @return void
	 */
	public function test_synchronization_with_listing_error(): void {
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );

		$urls    = $this->add_files( 4 );
		$url     = 'file://' . $this->directory;
		$term_id = Taxonomy::get_instance()->add( 'local', $url, array() );
		$sync    = Synchronization::get_instance();
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		$count = $this->get_media_file_count();

		// the directory could not be listed after the first page.
		$use_failing_protocol = static function ( array $protocols ): array {
			array_unshift( $protocols, Failing_File_Protocol::class );
			return $protocols;
		};
		add_filter( 'efml_protocols', $use_failing_protocol );
		$sync->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		remove_filter( 'efml_protocols', $use_failing_protocol );

		// files, which are not listed, are kept, as the list was not complete.
		$this->assertSame( $count, $this->get_media_file_count() );
		$this->assert_imported( $urls );
		$this->assertSame( '1', $this->get_runs()[1]['listing_failed'] );

		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
	}
}

/**
 * Local protocol, whose file list fails after the first page.
 */
class Failing_File_Protocol extends \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File {
	/**
	 * Return the page of the file list, but fail for all pages after the first page.
	 *
	 * @param string $cursor The cursor.
	 *
	 * @return array{files: array<int,array{url: string, media-url?: string, filesize?: int, last-modified?: int}>, cursor: string}|false
	 */
	public function get_file_list( string $cursor ): array|false {
		if ( '' !== $cursor ) {
			return false;
		}
		return parent::get_file_list( $cursor );
	}
}
