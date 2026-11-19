<?php
/**
 * Tests for the reliable export of uploads: delay for image optimizers and retries after errors.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads as Delayed_Uploads_Object;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\ThirdParty\Imagify;

/**
 * Object to test the reliable export of uploads.
 *
 * The tests run in separate processes, as other tests define constants like "EFML_URL_IMPORT_RUNNING", which prevent
 * the export of uploads.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DelayedUploads extends externalFilesTests {

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
		$property->setAccessible( true );
		$property->setValue( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance(), array() );

		// create the directory we use as export target and allow it as base directory for local files.
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-delayed-' . uniqid() . '/';
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
		update_option( 'eml_export_in_background', 0 );
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
		remove_all_filters( 'efml_export_upload_delay' );
		remove_all_filters( 'efml_export_upload_ready' );
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
		$file  = trailingslashit( get_temp_dir() ) . 'efml-delayed-' . wp_generate_password( 6, false, false ) . '.jpg';
		$image = imagecreatetruecolor( 2400, 1600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Return whether the given attachment is in the queue for its export.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_queued( int $attachment_id ): bool {
		$method = new \ReflectionMethod( Queue::class, 'get_export_entry' );
		$method->setAccessible( true );
		return ! empty( $method->invoke( Queue::get_instance(), $attachment_id ) );
	}

	/**
	 * Test that uploads are exported immediately as before, if no plugin requests a delay.
	 *
	 * @return void
	 */
	public function test_upload_is_exported_immediately(): void {
		$attachment_id = $this->upload();

		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Delayed_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertFalse( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
	}

	/**
	 * Test that the export waits, if a plugin requests it, and runs via WP-Cron with the files of this time.
	 *
	 * @return void
	 */
	public function test_upload_waits_for_delay(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();

		// the file is still local and waits.
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertTrue( Delayed_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertIsInt( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );

		// simulate an optimizer, which changes the local file meanwhile.
		$file = (string) get_attached_file( $attachment_id );
		$this->assertFileExists( $file );
		$image = imagecreatetruecolor( 2400, 1600 );
		imagejpeg( $image, $file, 10 );
		$optimized_size = (int) filesize( $file );

		// run the planned event.
		do_action( Delayed_Uploads_Object::CRON_HOOK, $attachment_id );

		// the optimized file is exported with its sizes and does not wait anymore.
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Delayed_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		$this->assertArrayHasKey( 'medium', Sizes::get_instance()->get_sizes( $attachment_id ) );
		$exported = $this->export_directory . get_post_meta( $attachment_id, 'efml_export_filename', true );
		$this->assertFileExists( $exported );
		$this->assertEquals( $optimized_size, filesize( $exported ) );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Test that the export is postponed while a plugin is not ready, but one hour at most.
	 *
	 * @return void
	 */
	public function test_export_waits_until_ready(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		add_filter( 'efml_export_upload_ready', '__return_false' );
		$attachment_id = $this->upload();

		// the export is postponed.
		wp_clear_scheduled_hook( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) );
		do_action( Delayed_Uploads_Object::CRON_HOOK, $attachment_id );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertIsInt( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );

		// after one hour, it is exported anyway.
		update_post_meta( $attachment_id, Delayed_Uploads_Object::META, time() - 2 * HOUR_IN_SECONDS );
		do_action( Delayed_Uploads_Object::CRON_HOOK, $attachment_id );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that the integration of an image optimizer only delays images.
	 *
	 * @return void
	 */
	public function test_optimizer_delays_only_images(): void {
		$image_id = self::factory()->attachment->create_object( 'efml-test.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		$pdf_id   = self::factory()->attachment->create_object( 'efml-test.pdf', 0, array( 'post_mime_type' => 'application/pdf' ) );

		$this->assertEquals( 5 * MINUTE_IN_SECONDS, Imagify::get_instance()->set_delay( 0, $image_id ) );
		$this->assertEquals( 0, Imagify::get_instance()->set_delay( 0, $pdf_id ) );
	}

	/**
	 * Test that a failed export of an upload is added to the queue, so it is tried again.
	 *
	 * @return void
	 */
	public function test_failed_export_is_queued(): void {
		// let the export fail: the target is not in the allowed directory anymore.
		add_filter( 'efml_file_base', array( $this, 'get_other_directory' ), 20 );
		$attachment_id = $this->upload();
		remove_filter( 'efml_file_base', array( $this, 'get_other_directory' ), 20 );

		// the file stays local and waits in the queue.
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFileExists( (string) get_attached_file( $attachment_id ) );
		$this->assertTrue( $this->is_queued( $attachment_id ) );
	}

	/**
	 * Test that an export, which has been prevented on purpose, is not added to the queue.
	 *
	 * @return void
	 */
	public function test_prevented_export_is_not_queued(): void {
		add_filter( 'efml_prevent_export', '__return_true' );
		$attachment_id = $this->upload();
		remove_filter( 'efml_prevent_export', '__return_true' );

		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( $this->is_queued( $attachment_id ) );
	}

	/**
	 * Test that sizes, which could not be exported, are exported again later (3 times at most).
	 *
	 * @return void
	 */
	public function test_failed_sizes_are_retried(): void {
		$attachment_id = $this->upload();
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$sizes_obj = Sizes::get_instance();

		// simulate a failed size and plan the retry.
		$method = new \ReflectionMethod( Sizes::class, 'schedule_retry' );
		$method->setAccessible( true );
		$method->invoke( $sizes_obj, $attachment_id );
		$this->assertEquals( 1, (int) get_post_meta( $attachment_id, Sizes::RETRY_META, true ) );
		$this->assertIsInt( wp_next_scheduled( Sizes::RETRY_HOOK, array( $attachment_id ) ) );

		// the retry exports the sizes and ends the attempts.
		wp_clear_scheduled_hook( Sizes::RETRY_HOOK, array( $attachment_id ) );
		do_action( Sizes::RETRY_HOOK, $attachment_id );
		$this->assertEquals( '', get_post_meta( $attachment_id, Sizes::RETRY_META, true ) );
		$this->assertArrayHasKey( 'medium', $sizes_obj->get_sizes( $attachment_id ) );

		// after the maximum of attempts, no further attempt is planned.
		update_post_meta( $attachment_id, Sizes::RETRY_META, 3 );
		$property = new \ReflectionProperty( Sizes::class, 'retry_planned' );
		$property->setAccessible( true );
		$property->setValue( $sizes_obj, array() );
		$method->invoke( $sizes_obj, $attachment_id );
		$this->assertEquals( '', get_post_meta( $attachment_id, Sizes::RETRY_META, true ) );
		$this->assertFalse( wp_next_scheduled( Sizes::RETRY_HOOK, array( $attachment_id ) ) );
	}
	/**
	 * Test that the export waits while an optimizer works on the upload and runs as soon as it reports its end.
	 *
	 * @return void
	 */
	public function test_export_waits_for_busy_optimizer(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();
		$delayed_obj   = Delayed_Uploads_Object::get_instance();

		// the optimizer starts: the export is postponed.
		$delayed_obj->set_busy( $attachment_id );
		wp_clear_scheduled_hook( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) );
		do_action( Delayed_Uploads_Object::CRON_HOOK, $attachment_id );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );

		// the optimizer reports its end: the export runs now, no event is left.
		$delayed_obj->done( $attachment_id );
		$this->assertFalse( $delayed_obj->is_busy( $attachment_id ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
	}

	/**
	 * Test that an upload is exported without delay, if the optimizer has finished its work in the same request.
	 *
	 * @return void
	 */
	public function test_no_delay_after_finished_optimizer(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$finish = static function ( $metadata, $attachment_id ) {
			Delayed_Uploads_Object::get_instance()->done( $attachment_id );
			return $metadata;
		};
		add_filter( 'wp_generate_attachment_metadata', $finish, 15, 2 );
		$attachment_id = $this->upload();
		remove_filter( 'wp_generate_attachment_metadata', $finish, 15 );

		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFalse( Delayed_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
	}

	/**
	 * Test that a due upload, whose event is missing, is exported directly during WP-Cron.
	 *
	 * @return void
	 */
	public function test_due_upload_is_exported_during_cron(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();

		// lose the event and let the delay pass.
		wp_clear_scheduled_hook( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) );
		update_post_meta( $attachment_id, Delayed_Uploads_Object::META, time() - 10 * MINUTE_IN_SECONDS );
		delete_option( \ExternalFilesInMediaLibrary\Plugin\Background_Events::CHECK_OPTION );

		// the check during WP-Cron exports it.
		add_filter( 'wp_doing_cron', '__return_true' );
		\ExternalFilesInMediaLibrary\Plugin\Background_Events::get_instance()->check();
		remove_filter( 'wp_doing_cron', '__return_true' );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test that the planned export is removed together with the file.
	 *
	 * @return void
	 */
	public function test_event_is_removed_with_file(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();
		$this->assertIsInt( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );

		wp_delete_attachment( $attachment_id, true );
		$this->assertFalse( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
	}

	/**
	 * Test that the deactivation removes the planned events and the activation plans them again.
	 *
	 * @return void
	 */
	public function test_events_after_deactivation(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();
		$events_obj    = \ExternalFilesInMediaLibrary\Plugin\Background_Events::get_instance();

		// the deactivation removes all events, the task stays saved.
		$events_obj->remove_all();
		$this->assertFalse( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
		$this->assertTrue( Delayed_Uploads_Object::get_instance()->is_waiting( $attachment_id ) );
		foreach ( $events_obj->get_hooks() as $hook ) {
			$this->assertFalse( wp_next_scheduled( $hook ) );
		}

		// the activation plans it again.
		$events_obj->resume();
		$this->assertIsInt( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
	}

	/**
	 * Test the marker in the media library for uploads, whose export waits.
	 *
	 * @return void
	 */
	public function test_marker_in_media_library(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();

		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Tables::get_instance()->add_media_column_content( 'external_files', $attachment_id );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'efml-export-pending', $html );
	}

	/**
	 * Test that a lost event of a waiting upload is planned again.
	 *
	 * @return void
	 */
	public function test_lost_event_is_planned_again(): void {
		add_filter( 'efml_export_upload_delay', array( Imagify::get_instance(), 'set_delay' ), 10, 2 );
		$attachment_id = $this->upload();

		// lose the event (e.g. another request saved the events at the same time).
		wp_clear_scheduled_hook( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) );
		delete_option( \ExternalFilesInMediaLibrary\Plugin\Background_Events::CHECK_OPTION );

		// the check in the backend plans it again.
		set_current_screen( 'upload' );
		\ExternalFilesInMediaLibrary\Plugin\Background_Events::get_instance()->check();
		$this->assertIsInt( wp_next_scheduled( Delayed_Uploads_Object::CRON_HOOK, array( $attachment_id ) ) );
		set_current_screen( 'front' );
	}

}
