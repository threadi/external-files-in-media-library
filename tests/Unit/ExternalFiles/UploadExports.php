<?php
/**
 * Tests for the export of uploads in the background via the queue, right away and the oldest first.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the export of uploads in the background.
 *
 * The tests run in separate processes, as other tests define constants like "EFML_URL_IMPORT_RUNNING", which prevent
 * the export of uploads.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UploadExports extends externalFilesTests {

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

		// forget the file types of former tests, as attachment IDs could be used again after their rollback.
		$property = new \ReflectionProperty( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::class, 'files' );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-uploads-' . uniqid() . '/';
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

		// enable the export.
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
		delete_option( 'efml_export_queue_lock' );
		Import_Runs::get_instance()->cancel_all();
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
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
	 * Upload an image and return its ID.
	 *
	 * @return int
	 */
	private function upload(): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-uploads-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 2400, 1600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Return the export entry of the given attachment in the queue.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>
	 */
	private function get_entry( int $attachment_id ): array {
		$method = new \ReflectionMethod( Queue::class, 'get_export_entry' );
		return (array) $method->invoke( Queue::get_instance(), $attachment_id );
	}

	/**
	 * Return a small size in bytes, from which files are large in the test.
	 *
	 * @return int
	 */
	public function get_small_large_file_size(): int {
		return 1000;
	}

	/**
	 * Test that large uploads are exported in the background, also if the export in background is not enabled.
	 *
	 * @return void
	 */
	public function test_large_uploads_are_exported_in_background(): void {
		update_option( 'eml_export_in_background', 0 );
		$export_obj = Export::get_instance();

		// test 1: 50 MB by default.
		$this->assertSame( 50 * MB_IN_BYTES, $export_obj->get_large_file_size() );

		// test 2: a small upload is exported during the upload.
		$attachment_id = $this->upload();
		$this->assertFalse( $export_obj->is_large_file( $attachment_id ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// test 3: a large upload waits in the queue.
		add_filter( 'efml_export_large_file_size', array( $this, 'get_small_large_file_size' ) );
		$attachment_id = $this->upload();
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertNotEmpty( $this->get_entry( $attachment_id ) );
		$this->assertTrue( $export_obj->is_large_file( $attachment_id ) );

		// test 4: the processing of the queue exports it.
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// test 5: not if the queue is not processed.
		update_option( 'eml_queue_interval', 'eml_disable_check' );
		$attachment_id = $this->upload();
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		update_option( 'eml_queue_interval', 'efml_hourly' );

		// test 6: not if it is disabled.
		remove_filter( 'efml_export_large_file_size', array( $this, 'get_small_large_file_size' ) );
		add_filter( 'efml_export_large_file_size', '__return_zero' );
		$attachment_id = $this->upload();
		$this->assertFalse( $export_obj->is_large_file( $attachment_id ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		remove_filter( 'efml_export_large_file_size', '__return_zero' );
	}

	/**
	 * Test that uploads are exported right away in the background, the oldest first.
	 *
	 * @return void
	 */
	public function test_uploads_are_exported_right_away(): void {
		$first  = $this->upload();
		$second = $this->upload();

		// they wait in the queue and their export is planned right away.
		$this->assertFalse( Files::get_instance()->get_file( $first )->is_valid() );
		$this->assertNotEmpty( $this->get_entry( $first ) );
		$next = wp_next_scheduled( Queue::EXPORT_HOOK );
		$this->assertIsInt( $next );
		$this->assertLessThanOrEqual( time(), $next );

		// the processing exports both.
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertTrue( Files::get_instance()->get_file( $first )->is_valid() );
		$this->assertTrue( Files::get_instance()->get_file( $second )->is_valid() );
		$this->assertEmpty( $this->get_entry( $first ) );
		$this->assertFalse( wp_next_scheduled( Queue::EXPORT_HOOK ) );
	}

	/**
	 * Test that failed exports are not tried again right away, but at the time of their next attempt.
	 *
	 * @return void
	 */
	public function test_failed_export_is_not_retried_right_away(): void {
		global $wpdb;
		$attachment_id = $this->upload();
		$entry         = $this->get_entry( $attachment_id );
		$wpdb->update( $wpdb->prefix . 'eml_queue', array( 'attempts' => 1, 'next_attempt' => time() + 5 * MINUTE_IN_SECONDS ), array( 'id' => absint( $entry['id'] ) ) );

		// WP-Cron removes the event before it runs it.
		wp_clear_scheduled_hook( Queue::EXPORT_HOOK );
		do_action( Queue::EXPORT_HOOK );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Queue::get_instance()->has_new_exports() );

		// the processing is planned at the time of the next attempt.
		$this->assertGreaterThan( time() + 4 * MINUTE_IN_SECONDS, wp_next_scheduled( Queue::EXPORT_HOOK ) );

		// the entry is exported, as soon as it is due.
		$wpdb->update( $wpdb->prefix . 'eml_queue', array( 'next_attempt' => time() - 1 ), array( 'id' => absint( $entry['id'] ) ) );
		do_action( Queue::EXPORT_HOOK );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that only one request processes the exports at the same time.
	 *
	 * @return void
	 */
	public function test_lock(): void {
		$attachment_id = $this->upload();
		add_option( 'efml_export_queue_lock', time() );

		do_action( Queue::EXPORT_HOOK );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// an expired lock is taken over.
		update_option( 'efml_export_queue_lock', time() - HOUR_IN_SECONDS );
		do_action( Queue::EXPORT_HOOK );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}
	/**
	 * Test that files, whose type is not allowed for the export, do not wait for it in the queue.
	 *
	 * @return void
	 */
	public function test_not_allowed_type_is_not_queued(): void {
		update_option( 'eml_allowed_mime_types', array( 'application/pdf' ) );
		$attachment_id = $this->upload();

		$this->assertEmpty( $this->get_entry( $attachment_id ) );
		$this->assertEquals( 0, absint( get_post_meta( $attachment_id, 'efml_export_queued', true ) ) );
		$this->assertFalse( wp_next_scheduled( Queue::EXPORT_HOOK ) );
	}

	/**
	 * Test that entries of files, whose type is not allowed anymore, are removed from the queue without error.
	 *
	 * @return void
	 */
	public function test_entry_of_not_allowed_type_is_removed(): void {
		$attachment_id = $this->upload();
		$this->assertNotEmpty( $this->get_entry( $attachment_id ) );

		update_option( 'eml_allowed_mime_types', array( 'application/pdf' ) );
		do_action( Queue::EXPORT_HOOK );

		$this->assertEmpty( $this->get_entry( $attachment_id ) );
		$this->assertEquals( 0, absint( get_post_meta( $attachment_id, 'efml_export_queued', true ) ) );
		$this->assertEquals( 0, absint( get_post_meta( $attachment_id, 'efml_export_failed', true ) ) );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

}
