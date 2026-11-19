<?php
/**
 * Tests for the state of the export in the media library: filter, "Export again", errors in the log and the
 * growing wait times of further attempts in the queue.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Tables;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Query;

/**
 * Object to test the state of the export in the media library.
 *
 * The tests run in separate processes, as other tests define constants like "EFML_URL_IMPORT_RUNNING", which prevent
 * the export of uploads.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ExportStates extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// run as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-states-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the external source and enable the export for it.
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		$this->assertGreaterThan( 0, $term_id );
		update_option( 'eml_export_main_source', 0 );
		Export::get_instance()->set_state_for_term( $term_id, 1 );
		update_term_meta( $term_id, 'efml_export_url', 'https://example.com/export/' );

		// enable the export in the background.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 1 );
		update_option( 'eml_queue_interval', 'efml_hourly' );

		// make sure the export of uploads is active.
		if ( false === has_filter( 'wp_generate_attachment_metadata', array( Export::get_instance(), 'export_file_by_upload' ) ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( Export::get_instance(), 'export_file_by_upload' ), 20, 2 );
		}
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_filter( 'efml_file_base', array( $this, 'get_other_directory' ) );
		delete_option( 'efml_export_queue_lock' );
		Import_Runs::get_instance()->cancel_all();
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		unset( $_REQUEST['query'] );
		parent::tear_down();
	}

	/**
	 * Return the export directory.
	 *
	 * @return string
	 */
	public function get_export_directory(): string {
		return $this->export_directory;
	}

	/**
	 * Return a directory, which is not the export directory (so the export fails).
	 *
	 * @return string
	 */
	public function get_other_directory(): string {
		return trailingslashit( get_temp_dir() ) . 'efml-not-allowed/';
	}

	/**
	 * Let the exports fail (the export target is outside the allowed directory).
	 *
	 * @return void
	 */
	private function let_exports_fail(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		add_filter( 'efml_file_base', array( $this, 'get_other_directory' ) );
	}

	/**
	 * Let the exports work again.
	 *
	 * @return void
	 */
	private function let_exports_work(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_other_directory' ) );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
	}

	/**
	 * Upload an image and return its ID.
	 *
	 * @return int
	 */
	private function upload(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-states-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Upload an image, whose export via the queue fails 3 times, and return its ID.
	 *
	 * @return int
	 */
	private function upload_failed(): int {
		global $wpdb;

		// upload it.
		$attachment_id = $this->upload();

		// let all attempts fail: the further attempts are due right away for this test.
		$this->let_exports_fail();
		for ( $i = 0; $i < 3; $i++ ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `next_attempt` = 0 WHERE `attachment_id` = %d', $wpdb->prefix . 'eml_queue', $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
			do_action( Queue::EXPORT_HOOK );
		}
		$this->let_exports_work();

		// it is marked as failed.
		$this->assertEquals( 'failed', Export::get_instance()->get_export_state( $attachment_id ) );

		// return its ID.
		return $attachment_id;
	}

	/**
	 * Return the IDs of the attachments found with the given value of the filter in the grid view.
	 *
	 * @param string $filter The value of the filter.
	 *
	 * @return array<int,int>
	 */
	private function get_ids_for_filter( string $filter ): array {
		set_current_screen( 'upload' );
		$_REQUEST['query'] = array( 'admin_filter_media_external_files' => $filter );
		$args              = Tables::get_instance()->add_media_filter_to_grid_query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$query             = new WP_Query( $args );
		return array_map( 'absint', $query->posts );
	}

	/**
	 * Test that the filter offers the states of the export after the existing options.
	 *
	 * @return void
	 */
	public function test_filter_options(): void {
		$keys = array_keys( Tables::get_instance()->get_filter_options() );
		$this->assertEquals( array( 'none', 'external', 'non-external', 'export-pending', 'export-failed' ), array_slice( $keys, 0, 5 ) );

		// without export and without files with these states, they are not offered.
		update_option( 'eml_export', 0 );
		$this->assertArrayNotHasKey( 'export-pending', Tables::get_instance()->get_filter_options() );
	}

	/**
	 * Test the filter for the states of the export in the grid view (the same meta query as in the list view).
	 *
	 * @return void
	 */
	public function test_filter_in_grid_view(): void {
		// a failed file, a file in the queue, a waiting upload and an exported file.
		$failed = $this->upload_failed();
		$queued = $this->upload();
		update_option( 'eml_export_in_background', 0 );
		add_filter( 'efml_export_upload_delay', array( $this, 'get_delay' ) );
		$waiting = $this->upload();
		remove_filter( 'efml_export_upload_delay', array( $this, 'get_delay' ) );
		$exported = $this->upload();
		$this->assertTrue( Files::get_instance()->get_file( $exported )->is_valid() );

		// check the results.
		$pending = $this->get_ids_for_filter( 'export-pending' );
		$this->assertContains( $queued, $pending );
		$this->assertContains( $waiting, $pending );
		$this->assertNotContains( $failed, $pending );
		$this->assertNotContains( $exported, $pending );
		$this->assertEquals( array( $failed ), $this->get_ids_for_filter( 'export-failed' ) );
		$this->assertContains( $exported, $this->get_ids_for_filter( 'external' ) );
		$this->assertNotContains( $exported, $this->get_ids_for_filter( 'non-external' ) );

		// unknown values are ignored.
		$this->assertCount( 4, $this->get_ids_for_filter( 'unknown' ) );
	}

	/**
	 * Return a delay for uploads.
	 *
	 * @return int
	 */
	public function get_delay(): int {
		return 5 * MINUTE_IN_SECONDS;
	}

	/**
	 * Test the actions in the media table for a failed file.
	 *
	 * @return void
	 */
	public function test_row_actions(): void {
		$failed  = $this->upload_failed();
		$actions = Tables::get_instance()->add_export_row_actions( array(), get_post( $failed ) );
		$this->assertArrayHasKey( 'efml-export-again', $actions );
		$this->assertArrayHasKey( 'efml-export-errors', $actions );
		$this->assertStringContainsString( 'errors=1', $actions['efml-export-errors'] );
		$this->assertStringContainsString( rawurlencode( wp_basename( (string) wp_get_attachment_url( $failed ) ) ), $actions['efml-export-errors'] );

		// the action "Export file" is not shown for it.
		$this->assertArrayNotHasKey( 'eml-export-file', Export::get_instance()->change_media_row_actions( array(), get_post( $failed ) ) );

		// no action for exported files.
		$exported = $this->upload();
		do_action( Queue::EXPORT_HOOK );
		$this->assertEmpty( Tables::get_instance()->add_export_row_actions( array(), get_post( $exported ) ) );
	}

	/**
	 * Test to export a failed file again.
	 *
	 * @return void
	 */
	public function test_export_failed_file_again(): void {
		global $wpdb;

		$failed = $this->upload_failed();

		// it fails again, if the problem still exists: the file stays failed.
		$this->let_exports_fail();
		$this->assertFalse( Export::get_instance()->export_again( $failed ) );
		$this->assertEquals( 'failed', Export::get_instance()->get_export_state( $failed ) );
		$this->let_exports_work();

		// it is exported, if the problem is solved.
		$this->assertTrue( Export::get_instance()->export_again( $failed ) );
		$this->assertTrue( Files::get_instance()->get_file( $failed )->is_valid() );
		$this->assertEquals( '', Export::get_instance()->get_export_state( $failed ) );
		$this->assertEquals( 0, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `attachment_id` = %d', $wpdb->prefix . 'eml_queue', $failed ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Test to export an upload, which waits for other plugins, right away.
	 *
	 * @return void
	 */
	public function test_export_waiting_upload_again(): void {
		update_option( 'eml_export_in_background', 0 );
		add_filter( 'efml_export_upload_delay', array( $this, 'get_delay' ) );
		$waiting = $this->upload();
		$this->assertTrue( Delayed_Uploads::get_instance()->is_waiting( $waiting ) );

		// it fails: the upload does not wait anymore, but is added to the queue to be tried again.
		$this->let_exports_fail();
		$this->assertFalse( Export::get_instance()->export_again( $waiting ) );
		$this->assertFalse( Delayed_Uploads::get_instance()->is_waiting( $waiting ) );
		$this->assertFalse( wp_next_scheduled( Delayed_Uploads::CRON_HOOK, array( $waiting ) ) );
		$this->assertTrue( Queue::get_instance()->has_export_entry( $waiting ) );
		$this->assertEquals( 'pending', Export::get_instance()->get_export_state( $waiting ) );
		$this->let_exports_work();

		// it is exported now.
		$this->assertTrue( Export::get_instance()->export_again( $waiting ) );
		$this->assertTrue( Files::get_instance()->get_file( $waiting )->is_valid() );
		$this->assertFalse( Queue::get_instance()->has_export_entry( $waiting ) );
	}

	/**
	 * Test that files without failed or waiting export could not be exported again.
	 *
	 * @return void
	 */
	public function test_export_again_only_for_failed_or_waiting_files(): void {
		remove_filter( 'wp_generate_attachment_metadata', array( Export::get_instance(), 'export_file_by_upload' ), 20 );
		$local = $this->upload();
		$this->assertEquals( '', Export::get_instance()->get_export_state( $local ) );
		$this->assertNotEquals( '', Export::get_instance()->get_export_again_problem( $local ) );
		$this->assertFalse( Export::get_instance()->export_again( $local ) );
		$this->assertFalse( Files::get_instance()->get_file( $local )->is_valid() );
	}

	/**
	 * Test the growing wait times of further attempts.
	 *
	 * @return void
	 */
	public function test_retry_delays(): void {
		global $wpdb;

		$queue = Queue::get_instance();
		$this->assertEquals( 5 * MINUTE_IN_SECONDS, $queue->get_retry_delay( 1, 'export' ) );
		$this->assertEquals( 15 * MINUTE_IN_SECONDS, $queue->get_retry_delay( 2, 'export' ) );
		$this->assertEquals( HOUR_IN_SECONDS, $queue->get_retry_delay( 3, 'delete' ) );
		$this->assertEquals( 4 * HOUR_IN_SECONDS, $queue->get_retry_delay( 9, 'delete' ) );

		// a failed attempt gets its next attempt after the wait time.
		$attachment_id = $this->upload();
		$this->let_exports_fail();
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$entry = $wpdb->get_row( $wpdb->prepare( 'SELECT `attempts`, `next_attempt` FROM %i WHERE `attachment_id` = %d', $wpdb->prefix . 'eml_queue', $attachment_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertEquals( 1, absint( $entry['attempts'] ) );
		$this->assertEqualsWithDelta( time() + 5 * MINUTE_IN_SECONDS, absint( $entry['next_attempt'] ), 5 );
		$this->assertEqualsWithDelta( time() + 5 * MINUTE_IN_SECONDS, wp_next_scheduled( Queue::EXPORT_HOOK ), 5 );

		// the second failed attempt waits longer.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `next_attempt` = 0 WHERE `attachment_id` = %d', $wpdb->prefix . 'eml_queue', $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertEqualsWithDelta( time() + 15 * MINUTE_IN_SECONDS, $queue->get_next_attempt(), 5 );

		// the wait times could be changed via hook.
		add_filter(
			'efml_queue_retry_delays',
			static function () {
				return array( 60 );
			}
		);
		$this->assertEquals( 60, $queue->get_retry_delay( 3, 'export' ) );
	}

	/**
	 * Test that entries of older versions with failed attempts, but without time of the next attempt, are due.
	 *
	 * @return void
	 */
	public function test_older_entries_are_due(): void {
		global $wpdb;

		$attachment_id = $this->upload();
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET `attempts` = 1, `next_attempt` = 0 WHERE `attachment_id` = %d', $wpdb->prefix . 'eml_queue', $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertEquals( 1, Queue::get_instance()->get_next_attempt() );

		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that the table of an older version gets the column for the next attempt.
	 *
	 * @return void
	 */
	public function test_table_update(): void {
		global $wpdb;

		// a file in the queue.
		$attachment_id = $this->upload();
		update_option( 'eml_export', 0 );

		// simulate an older version of the structure.
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN `next_attempt`', $wpdb->prefix . 'eml_queue' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		update_option( Queue::DB_VERSION_OPTION, '2' );
		$this->assertNotContains( 'next_attempt', wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'eml_queue' ), ARRAY_A ), 'Field' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// the processing in the background updates the structure itself (e.g. in WP-Cron before the backend is used).
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertEquals( '3', get_option( Queue::DB_VERSION_OPTION ) );

		// the column is added and the version saved, entries are kept.
		Queue::get_instance()->maybe_install();
		$this->assertEquals( '3', get_option( Queue::DB_VERSION_OPTION ) );
		$columns = wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $wpdb->prefix . 'eml_queue' ), ARRAY_A ), 'Field' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertContains( 'next_attempt', $columns );
		$this->assertTrue( Queue::get_instance()->has_export_entry( $attachment_id ) );
	}
}
