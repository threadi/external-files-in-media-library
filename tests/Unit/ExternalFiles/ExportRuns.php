<?php
/**
 * Tests for the export of the local files in the media library via runs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the export of the local files in the media library.
 */
class ExportRuns extends externalFilesTests {

	/**
	 * The directory we use as export target.
	 *
	 * @var string
	 */
	private string $export_directory = '';

	/**
	 * The term ID of the external source we use as export target.
	 *
	 * @var int
	 */
	private int $term_id = 0;

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-export-run-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the external source and enable the export for it.
		$this->term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		$this->assertGreaterThan( 0, $this->term_id );
		Export::get_instance()->set_state_for_term( $this->term_id, 1 );
		update_term_meta( $this->term_id, 'efml_export_url', 'https://example.com/export/' );

		// enable the export.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_main_source', $this->term_id );

		// start without runs and queue entries.
		Queue::get_instance()->clear();

		// exclude the local files of other tests from the export (the database is reset after each test).
		foreach ( Export::get_instance()->get_local_files_for_export( 0, PHP_INT_MAX ) as $attachment_id ) {
			update_post_meta( $attachment_id, 'eml_exported_file', 1 );
		}
		$this->assertEquals( 0, Export::get_instance()->count_local_files_for_export() );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Queue::get_instance()->clear();
		wp_clear_scheduled_hook( Import_Runs::CRON_HOOK );
		remove_all_filters( 'efml_export_run_page_size' );
		remove_all_filters( 'efml_is_website_copy' );
		remove_all_filters( 'efml_prevent_export' );
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->export_directory );
		foreach ( array( 'efml_export_run_finished' ) as $name ) {
			Transients::get_instance()->get_transient_by_name( $name )->delete();
		}
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
	 * Add the given number of local files to the media library and return their IDs.
	 *
	 * @param int $count The number of files.
	 *
	 * @return array<int,int>
	 */
	private function add_local_files( int $count ): array {
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$ids[] = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		}
		return $ids;
	}

	/**
	 * Return the export entries of the given run in the queue.
	 *
	 * @param int $run_id The ID of the run.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_entries( int $run_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE `run_id` = %d ORDER BY `id` ASC', array( $wpdb->prefix . 'eml_queue', $run_id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Test the selection of the local files, which could be exported.
	 *
	 * @return void
	 */
	public function test_local_files_for_export(): void {
		$export = Export::get_instance();
		$before = $export->count_local_files_for_export();

		// add local files.
		$ids = $this->add_local_files( 3 );

		// add a file with a mime type, which is not allowed.
		$not_allowed = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'application/x-efml-test',
				'file'           => 'test.efmltest',
			)
		);

		// add a file, which is already an external file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );

		// add a file, which waits for its export in the queue.
		$queued = $this->add_local_files( 1 )[0];
		$this->assertTrue( Queue::get_instance()->add_export( $queued ) );

		// only the 3 local files are counted.
		$this->assertEquals( $before + 3, $export->count_local_files_for_export() );

		// they are listed in pages by their ID.
		$list = $export->get_local_files_for_export( $ids[0] - 1, 2 );
		$this->assertEquals( array( $ids[0], $ids[1] ), $list );
		$this->assertEquals( array( $ids[2] ), $export->get_local_files_for_export( $ids[1], 10 ) );
		$this->assertNotContains( $not_allowed, $export->get_local_files_for_export( 0, 1000 ) );
		$this->assertNotContains( $queued, $export->get_local_files_for_export( 0, 1000 ) );
	}

	/**
	 * Test the button to start the export in the export settings.
	 *
	 * @return void
	 */
	public function test_settings_button(): void {
		// add the settings in the backend.
		set_current_screen( 'options-general.php' );
		update_option( 'efml_directory_listing_used', 1 );
		add_filter( 'efml_is_settings_request', '__return_true' );
		Export::get_instance()->add_settings();
		remove_filter( 'efml_is_settings_request', '__return_true' );
		set_current_screen( 'front' );

		// get the setting with the button.
		$setting = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_settings_obj()->get_setting( 'eml_export_local_files_now' );
		$this->assertInstanceOf( \easySettingsForWordPress\Setting::class, $setting );
		$field = $setting->get_field();
		$this->assertInstanceOf( \easySettingsForWordPress\Fields\Button::class, $field );

		// the dialog names the main external source and starts the export via request.
		$data = $field->get_data_as_array();
		$this->assertArrayHasKey( 'dialog', $data );
		$dialog = json_decode( $data['dialog'], true );
		$this->assertIsArray( $dialog );
		$term = get_term( $this->term_id );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertStringContainsString( $term->name, implode( ' ', $dialog['texts'] ) );
		$this->assertStringContainsString( 'efml_export_local_files', $dialog['buttons'][0]['action'] );
	}

	/**
	 * Test if the export could not be started without the required settings.
	 *
	 * @return void
	 */
	public function test_start_requires_settings(): void {
		$this->add_local_files( 1 );

		// without enabled export.
		update_option( 'eml_export', 0 );
		$result = Import_Runs::get_instance()->start_export();
		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['run_id'] );
		$this->assertNotEmpty( $result['message'] );
		update_option( 'eml_export', 1 );

		// without the export of local files.
		update_option( 'eml_export_local_files', 0 );
		$this->assertFalse( Import_Runs::get_instance()->start_export()['success'] );
		update_option( 'eml_export_local_files', 1 );

		// on a copy of the website.
		add_filter( 'efml_is_website_copy', '__return_true' );
		$this->assertFalse( Import_Runs::get_instance()->start_export()['success'] );
		remove_filter( 'efml_is_website_copy', '__return_true' );

		// no run has been created.
		$this->assertEmpty( Import_Runs::get_instance()->get_active_export_run() );
	}

	/**
	 * Test the export of local files in the background.
	 *
	 * @return void
	 */
	public function test_export_in_background(): void {
		$ids = $this->add_local_files( 3 );

		// list 2 files at once.
		add_filter(
			'efml_export_run_page_size',
			static function (): int {
				return 2;
			}
		);

		// start the export.
		$result = Import_Runs::get_instance()->start_export();
		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['run_id'] );
		$this->assertNotFalse( wp_next_scheduled( Import_Runs::CRON_HOOK ) );

		// the first page is listed directly, the files are not exported yet, but marked.
		$run = Import_Runs::get_instance()->get_run( $result['run_id'] );
		$this->assertEquals( 'export', $run['type'] );
		$this->assertEquals( 'listing', $run['state'] );
		$this->assertEquals( 2, absint( $run['total'] ) );
		$entries = $this->get_entries( $result['run_id'] );
		$this->assertCount( 2, $entries );
		$this->assertEquals( 'export', $entries[0]['type'] );
		$this->assertEquals( $ids[0], absint( $entries[0]['attachment_id'] ) );
		$this->assertGreaterThan( 0, absint( get_post_meta( $ids[0], 'efml_export_queued', true ) ) );
		$this->assertFalse( Files::get_instance()->get_file( $ids[0] )->is_valid() );

		// the run is shown in the progress and in the queue.
		$progress = Import_Runs::get_instance()->get_progress();
		$this->assertEquals( 1, $progress['exports'] );
		$this->assertEquals( 2, $progress['export_total'] );
		$this->assertStringContainsString( 'Export', Import_Runs::get_instance()->get_description( $result['run_id'] ) );

		// starting it again does not start a second run.
		$again = Import_Runs::get_instance()->start_export();
		$this->assertTrue( $again['success'] );
		$this->assertEquals( $result['run_id'], $again['run_id'] );

		// the queue itself does not process the entries of the run.
		$this->assertEmpty( Queue::get_instance()->get_urls( 'new', true, false ) );

		// process the run in the background.
		Import_Runs::get_instance()->process();

		// all files are exported now.
		foreach ( $ids as $attachment_id ) {
			$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
			$this->assertFileExists( $this->export_directory . basename( (string) get_attached_file( $attachment_id, true ) ) );
			$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_queued', true ) );
		}

		// the run is finished and removed.
		$this->assertEmpty( $this->get_entries( $result['run_id'] ) );
		$this->assertEmpty( Import_Runs::get_instance()->get_active_export_run() );
		$this->assertEquals( 0, Import_Runs::get_instance()->get_progress()['exports'] );

		// the hint shows the result.
		$transient_obj = Transients::get_instance()->get_transient_by_name( 'efml_export_run_finished' );
		$this->assertStringContainsString( '3 files exported, 0 files not exported', $transient_obj->get_message() );
		$this->assertEquals( 'success', $transient_obj->get_type() );

		// nothing is left to export.
		$this->assertFalse( Import_Runs::get_instance()->start_export()['success'] );
	}

	/**
	 * Test the handling of files, which could not be exported.
	 *
	 * @return void
	 */
	public function test_failed_export(): void {
		$ids = $this->add_local_files( 2 );

		// prevent the export of the first file.
		$failing  = $ids[0];
		$callback = static function ( bool $prevent, \ExternalFilesInMediaLibrary\ExternalFiles\File $external_file_obj ) use ( $failing ): bool {
			return $prevent || $failing === $external_file_obj->get_id();
		};
		add_filter( 'efml_prevent_export', $callback, 10, 2 );

		// export the files.
		$result = Import_Runs::get_instance()->start_export();
		Import_Runs::get_instance()->process();

		// the second file is exported, the first file is marked and its entry stays in the queue as error.
		$this->assertTrue( Files::get_instance()->get_file( $ids[1] )->is_valid() );
		$this->assertFalse( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertGreaterThan( 0, absint( get_post_meta( $ids[0], 'efml_export_failed', true ) ) );
		$this->assertEmpty( get_post_meta( $ids[0], 'efml_export_queued', true ) );
		$entries = $this->get_entries( $result['run_id'] );
		$this->assertCount( 1, $entries );
		$this->assertEquals( 'error', $entries[0]['state'] );

		// the hint links to the log and to the queue.
		$transient_obj = Transients::get_instance()->get_transient_by_name( 'efml_export_run_finished' );
		$this->assertStringContainsString( '1 files exported, 1 files not exported', $transient_obj->get_message() );
		$this->assertStringContainsString( esc_url( \ExternalFilesInMediaLibrary\Plugin\Helper::get_log_url() ), $transient_obj->get_message() );
		$this->assertEquals( 'error', $transient_obj->get_type() );

		// export it again via the queue, after the problem has been solved.
		remove_filter( 'efml_prevent_export', $callback );
		Import_Runs::get_instance()->process_entry_by_id( absint( $entries[0]['id'] ) );
		$this->assertTrue( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertEmpty( get_post_meta( $ids[0], 'efml_export_failed', true ) );
		$this->assertEmpty( $this->get_entries( $result['run_id'] ) );
	}

	/**
	 * Test if the export is paused on a copy of the website.
	 *
	 * @return void
	 */
	public function test_paused_on_copy(): void {
		$ids    = $this->add_local_files( 1 );
		$result = Import_Runs::get_instance()->start_export();

		// the website is a copy now.
		add_filter( 'efml_is_website_copy', '__return_true' );
		wp_clear_scheduled_hook( Import_Runs::CRON_HOOK );
		Import_Runs::get_instance()->process();

		// nothing has been exported, the run is still active and checked again later.
		$this->assertFalse( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertNotEmpty( Import_Runs::get_instance()->get_active_export_run() );
		$this->assertGreaterThan( time() + 30 * MINUTE_IN_SECONDS, absint( wp_next_scheduled( Import_Runs::CRON_HOOK ) ) );

		// on the main website the export continues.
		remove_filter( 'efml_is_website_copy', '__return_true' );
		Import_Runs::get_instance()->process();
		$this->assertTrue( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertEmpty( $this->get_entries( $result['run_id'] ) );
	}

	/**
	 * Test if the export is cancelled, if it is disabled in the meantime.
	 *
	 * @return void
	 */
	public function test_cancelled_if_disabled(): void {
		$ids    = $this->add_local_files( 1 );
		$result = Import_Runs::get_instance()->start_export();

		// disable the export.
		update_option( 'eml_export', 0 );
		Import_Runs::get_instance()->process();

		// the run is cancelled, the file stays local without marker.
		$this->assertFalse( Files::get_instance()->get_file( $ids[0] )->is_valid() );
		$this->assertEmpty( Import_Runs::get_instance()->get_active_export_run() );
		$this->assertEmpty( $this->get_entries( $result['run_id'] ) );
		$this->assertEmpty( get_post_meta( $ids[0], 'efml_export_queued', true ) );

		// the hint names the reason.
		$transient_obj = Transients::get_instance()->get_transient_by_name( 'efml_export_run_finished' );
		$this->assertStringContainsString( 'not enabled', $transient_obj->get_message() );
	}

	/**
	 * Test if clearing the queue cancels the export.
	 *
	 * @return void
	 */
	public function test_cancelled_by_clearing_the_queue(): void {
		$ids = $this->add_local_files( 1 );
		Import_Runs::get_instance()->start_export();
		$this->assertNotEmpty( Import_Runs::get_instance()->get_active_export_run() );

		// clear the queue.
		Queue::get_instance()->clear();

		// the run is cancelled.
		$this->assertEmpty( Import_Runs::get_instance()->get_active_export_run() );
		$this->assertEmpty( get_post_meta( $ids[0], 'efml_export_queued', true ) );
		Import_Runs::get_instance()->process();
		$this->assertFalse( Files::get_instance()->get_file( $ids[0] )->is_valid() );
	}

	/**
	 * Test if deleted files are removed from the run.
	 *
	 * @return void
	 */
	public function test_deleted_file_is_removed_from_run(): void {
		$ids    = $this->add_local_files( 2 );
		$result = Import_Runs::get_instance()->start_export();

		// delete the first file.
		wp_delete_attachment( $ids[0], true );
		Import_Runs::get_instance()->process();

		// the second file is exported.
		$this->assertTrue( Files::get_instance()->get_file( $ids[1] )->is_valid() );
		$this->assertEmpty( $this->get_entries( $result['run_id'] ) );
		$transient_obj = Transients::get_instance()->get_transient_by_name( 'efml_export_run_finished' );
		$this->assertStringContainsString( '1 files exported', $transient_obj->get_message() );
	}
}
