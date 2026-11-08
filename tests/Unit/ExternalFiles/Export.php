<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Export.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Export.
 */
class Export extends externalFilesTests {

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-export-' . uniqid() . '/';
		wp_mkdir_p( $this->export_directory );
		add_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );

		// make sure the local service is used as export object.
		if ( false === has_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) ) ) {
			add_filter( 'efml_export_object', array( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), 'change_export_object' ) );
		}

		// create the external source and enable the export for it.
		$this->term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'local', 'file://' . $this->export_directory, array( 'path' => array( 'value' => $this->export_directory ) ) );
		$this->assertGreaterThan( 0, $this->term_id );
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->set_state_for_term( $this->term_id, 1 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
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
	 * Add an external file and return its attachment ID.
	 *
	 * @return int
	 */
	private function add_external_file(): int {
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		return $external_file_obj->get_id();
	}

	/**
	 * Create a file in the export directory with the same name as the given attachment and return its path.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	private function create_file_on_export_target( int $attachment_id ): string {
		$path = $this->export_directory . basename( (string) get_attached_file( $attachment_id, true ) );
		file_put_contents( $path, 'this is a file on the export target' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertFileExists( $path );
		return $path;
	}

	/**
	 * Test if the external source is known as export target.
	 *
	 * @return void
	 */
	public function test_get_export_terms(): void {
		$this->assertContains( $this->term_id, \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->get_export_terms() );
	}

	/**
	 * Test if the deletion of an external file, which has never been exported, does not delete
	 * a file with the same name on the export target.
	 *
	 * @return void
	 */
	public function test_delete_not_exported_file_keeps_file_on_export_target(): void {
		$attachment_id = $this->add_external_file();
		$path          = $this->create_file_on_export_target( $attachment_id );

		// run the task directly.
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( $attachment_id );
		$this->assertFileExists( $path );

		// and delete the attachment itself.
		wp_delete_attachment( $attachment_id, true );
		$this->assertFileExists( $path );
	}

	/**
	 * Test if the deletion of an exported file does also delete the file on the export target.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_deletes_file_on_export_target(): void {
		$attachment_id = $this->add_external_file();
		$path          = $this->create_file_on_export_target( $attachment_id );

		// mark the file as exported to our external source.
		update_post_meta( $attachment_id, 'eml_exported_file', time() );
		update_post_meta( $attachment_id, 'efml_export_sources', array( $this->term_id ) );

		// run the task.
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( $attachment_id );
		$this->assertFileDoesNotExist( $path );

		// the markers must be removed.
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_sources', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_former_export_sources', true ) );
	}

	/**
	 * Test if a file, which has been exported to another external source, is not deleted on our export target.
	 *
	 * @return void
	 */
	public function test_delete_file_exported_to_other_source_keeps_file_on_export_target(): void {
		$attachment_id = $this->add_external_file();
		$path          = $this->create_file_on_export_target( $attachment_id );

		// mark the file as exported to another external source.
		update_post_meta( $attachment_id, 'eml_exported_file', time() );
		update_post_meta( $attachment_id, 'efml_export_sources', array( $this->term_id + 1000 ) );

		// run the task.
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( $attachment_id );
		$this->assertFileExists( $path );
	}

	/**
	 * Test if the exported copy of a file can still be deleted after the file has been switched to local hosting.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_after_cleanup(): void {
		$attachment_id = $this->add_external_file();
		$path          = $this->create_file_on_export_target( $attachment_id );

		// mark the file as exported to our external source.
		update_post_meta( $attachment_id, 'eml_exported_file', time() );
		update_post_meta( $attachment_id, 'efml_export_sources', array( $this->term_id ) );

		// run the cleanup, which is used after the switch to local hosting.
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->cleanup_exported_file( $attachment_id );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_sources', true ) );
		$this->assertEquals( array( $this->term_id ), get_post_meta( $attachment_id, 'efml_former_export_sources', true ) );
		$this->assertFileExists( $path );

		// the deletion must still delete the exported copy.
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( $attachment_id );
		$this->assertFileDoesNotExist( $path );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_former_export_sources', true ) );
	}

	/**
	 * Test if the export during uploads is disabled during the switch to local hosting and enabled afterwards.
	 *
	 * @return void
	 */
	public function test_export_is_paused_during_switch_to_local(): void {
		$export_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance();
		$callback   = array( $export_obj, 'export_file_by_upload' );

		// make sure the hook is set.
		$export_obj->allow_export_checks_after_local_switch();
		$this->assertEquals( 20, has_filter( 'wp_generate_attachment_metadata', $callback ) );

		// before the switch.
		$export_obj->prevent_export_checks_on_local_switch();
		$this->assertFalse( has_filter( 'wp_generate_attachment_metadata', $callback ) );

		// after the switch.
		$export_obj->allow_export_checks_after_local_switch();
		$this->assertEquals( 20, has_filter( 'wp_generate_attachment_metadata', $callback ) );
	}

	/**
	 * Test if the bulk action for the export is only available for users with the capability for it.
	 *
	 * @return void
	 */
	public function test_bulk_action_requires_capability(): void {
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		$export_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance();

		// as user without the capability.
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$this->assertFalse( current_user_can( 'efml_cap_tools_export' ) );
		$this->assertArrayNotHasKey( 'efml-export', $export_obj->add_bulk_action( array() ) );

		// the action itself must not export anything.
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$this->assertGreaterThan( 0, $attachment_id );
		$export_obj->run_bulk_action( 'upload.php', 'efml-export', array( $attachment_id ) );
		$this->assertEmpty( get_post_meta( $attachment_id, 'eml_exported_file', true ) );
		$this->assertFileExists( (string) get_attached_file( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $this->export_directory . basename( (string) get_attached_file( $attachment_id, true ) ) );
	}

	/**
	 * Enable the folders for exported files.
	 *
	 * @param string $structure The structure of the folders.
	 *
	 * @return void
	 */
	private function enable_folders( string $structure = '%year%/%monthnum%' ): void {
		update_option( 'uploads_use_yearmonth_folders', 1 );
		update_option( 'eml_export_folders', 1 );
		update_option( 'eml_export_folder_structure', $structure );
	}

	/**
	 * Test if no folder is used as long as the folders are disabled or WordPress does not use year- and month-based folders.
	 *
	 * @return void
	 */
	public function test_get_export_folder_requires_settings(): void {
		$export_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance();

		// disabled by default.
		update_option( 'uploads_use_yearmonth_folders', 1 );
		delete_option( 'eml_export_folders' );
		$this->assertFalse( $export_obj->is_folder_structure_enabled() );
		$this->assertEquals( '', $export_obj->get_export_folder( 0, time() ) );

		// enabled, but WordPress does not use year- and month-based folders.
		$this->enable_folders();
		update_option( 'uploads_use_yearmonth_folders', 0 );
		$this->assertFalse( $export_obj->is_folder_structure_enabled() );
		$this->assertEquals( '', $export_obj->get_export_folder( 0, time() ) );

		// both enabled.
		update_option( 'uploads_use_yearmonth_folders', 1 );
		$this->assertTrue( $export_obj->is_folder_structure_enabled() );
		$this->assertEquals( wp_date( 'Y/m' ), $export_obj->get_export_folder( 0, time() ) );
	}

	/**
	 * Test if the placeholders are replaced by the date of the upload and the time of the export.
	 *
	 * @return void
	 */
	public function test_get_export_folder_placeholders(): void {
		$this->enable_folders( 'media/%year%/%monthnum%/%day%/%timestamp%' );
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		wp_update_post(
			array(
				'ID'            => $attachment_id,
				'post_date'     => '2024-03-05 10:00:00',
				'post_date_gmt' => '2024-03-05 10:00:00',
			)
		);
		$this->assertEquals( 'media/2024/03/05/1234567890', \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->get_export_folder( $attachment_id, 1234567890 ) );
	}

	/**
	 * Test if the structure is sanitized and folders from hooks could not leave the external source.
	 *
	 * @return void
	 */
	public function test_folder_structure_is_sanitized(): void {
		$export_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance();
		$this->assertEquals( '%year%/%monthnum%', $export_obj->sanitize_folder_structure( '/%year%//%monthnum%/' ) );
		$this->assertEquals( 'abc/%day%', $export_obj->sanitize_folder_structure( 'a b<c>/../%day%' ) );
		$this->assertEquals( '%year%/%monthnum%', $export_obj->sanitize_folder_structure( array() ) );

		// a folder from a hook with relative parts.
		$this->enable_folders();
		$callback = static function (): string {
			return '../../etc/./media';
		};
		add_filter( 'efml_export_folder', $callback );
		$this->assertEquals( 'etc/media', $export_obj->get_export_folder( 0, time() ) );
		remove_filter( 'efml_export_folder', $callback );
	}

	/**
	 * Test if a file is exported to the folder and deleted there again.
	 *
	 * @return void
	 */
	public function test_export_and_delete_file_in_folder(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );

		// enable the export with folders.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		update_term_meta( $this->term_id, 'efml_export_url', 'https://example.com/export/' );
		$this->enable_folders( 'files/%timestamp%' );
		$callback = static function (): string {
			return 'files/fixed';
		};
		add_filter( 'efml_export_folder', $callback );

		// export the file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFileExists( $this->export_directory . 'files/fixed/' . $filename );
		$this->assertFileDoesNotExist( $this->export_directory . $filename );
		$this->assertEquals( array( $this->term_id => 'files/fixed' ), get_post_meta( $attachment_id, 'efml_export_folders', true ) );

		// the deletion uses the saved folder, even if the structure has been changed in the meantime.
		remove_filter( 'efml_export_folder', $callback );
		update_option( 'eml_export_folders', 0 );
		\ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->delete_exported_file( $attachment_id );
		$this->assertFileDoesNotExist( $this->export_directory . 'files/fixed/' . $filename );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_folders', true ) );
	}

	/**
	 * Test if the export without folders is unchanged.
	 *
	 * @return void
	 */
	public function test_export_file_without_folder(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$filename      = basename( (string) get_attached_file( $attachment_id, true ) );
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		update_option( 'eml_export_folders', 0 );
		update_term_meta( $this->term_id, 'efml_export_url', 'https://example.com/export/' );

		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Export::get_instance()->export_file( $attachment_id ) );
		$this->assertFileExists( $this->export_directory . $filename );
		$this->assertEmpty( get_post_meta( $attachment_id, 'efml_export_folders', true ) );
	}
}
