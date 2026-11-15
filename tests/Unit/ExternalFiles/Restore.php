<?php
/**
 * Tests for the restore of exported files at their original path.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the restore of exported files at their original path.
 */
class Restore extends externalFilesTests {

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-restore-' . uniqid() . '/';
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

		// enable the export, which deletes the local files.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 0 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
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
	 * Create an exported image and return its ID.
	 *
	 * @param int $width  The width.
	 * @param int $height The height.
	 *
	 * @return int
	 */
	private function create_exported_image( int $width = 800, int $height = 600 ): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-restore-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		return $attachment_id;
	}

	/**
	 * Test the restore of an exported image at its original path.
	 *
	 * @return void
	 */
	public function test_restore_file(): void {
		// create an image, remember its local data and export it.
		$file          = trailingslashit( get_temp_dir() ) . 'efml-restore-single.jpg';
		$image         = imagecreatetruecolor( 800, 600 );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$metadata      = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$sizes = wp_list_pluck( $metadata['sizes'], 'file' );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );

		// remove the local files, so the file must be downloaded (the export in the tests does not delete them).
		$directory = trailingslashit( dirname( trailingslashit( wp_get_upload_dir()['basedir'] ) . $attached_file ) );
		wp_delete_file( $directory . wp_basename( $attached_file ) );
		foreach ( $sizes as $size_file ) {
			wp_delete_file( $directory . $size_file );
		}
		$this->assertEquals( 1, \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->count_files() );

		// restore it.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id ) );

		// it is a normal local file at its original path with its sizes.
		$this->assertEquals( $attached_file, get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileExists( trailingslashit( wp_get_upload_dir()['basedir'] ) . $attached_file );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$restored_metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $restored_metadata );
		$this->assertEquals( $sizes, wp_list_pluck( $restored_metadata['sizes'], 'file' ) );
		foreach ( $sizes as $size_file ) {
			$this->assertFileExists( trailingslashit( dirname( trailingslashit( wp_get_upload_dir()['basedir'] ) . $attached_file ) ) . $size_file );
		}
		foreach ( Files::get_instance()->get_meta_keys() as $meta_key ) {
			$this->assertEmpty( get_post_meta( $attachment_id, $meta_key, true ), $meta_key );
		}
		$this->assertEmpty( wp_get_object_terms( $attachment_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() ) );
		$this->assertEquals( 0, \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->count_files() );

		// the copy on the external source is kept.
		$this->assertFileExists( $this->export_directory . wp_basename( $attached_file ) );
	}

	/**
	 * Test the restore of a scaled image: the scaled file, the original image and the sizes get their former names.
	 *
	 * @return void
	 */
	public function test_restore_scaled_image(): void {
		// create a large image and export it.
		$attachment_id = $this->create_exported_image( 3000, 2000 );
		$paths         = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id );
		$this->assertStringEndsWith( '-scaled.jpg', $paths['file'] );
		$this->assertNotEmpty( $paths['original'] );
		$sizes = Local_Urls::get_instance()->get_local_sizes( $attachment_id );
		$this->assertNotEmpty( $sizes );

		// restore it.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id ) );

		// both files exist and the metadata uses them.
		$base_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
		$this->assertFileExists( $base_dir . $paths['file'] );
		$this->assertFileExists( $base_dir . $paths['original'] );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$this->assertIsArray( $metadata );
		$this->assertEquals( $paths['file'], $metadata['file'] );
		$this->assertEquals( wp_basename( $paths['original'] ), $metadata['original_image'] );

		// the sizes have their former names.
		foreach ( $sizes as $size_name => $size_file ) {
			$this->assertEquals( $size_file, $metadata['sizes'][ $size_name ]['file'] );
			$this->assertFileExists( trailingslashit( dirname( $base_dir . $paths['file'] ) ) . $size_file );
		}
	}

	/**
	 * Test the deletion of the copy on the external source.
	 *
	 * @return void
	 */
	public function test_restore_with_deletion_on_target(): void {
		$attachment_id = $this->create_exported_image();
		$target        = $this->export_directory . wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		$this->assertFileExists( $target );

		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $target );
		$this->assertFileExists( get_attached_file( $attachment_id ) );
	}

	/**
	 * Test that nothing is overwritten, if the original path is used by another file.
	 *
	 * @return void
	 */
	public function test_restore_with_used_path(): void {
		$attachment_id = $this->create_exported_image();
		$paths         = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id );

		// another attachment uses the path now.
		$other_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $other_id, '_wp_attached_file', $paths['file'] );

		// the file is not restored and stays an exported file.
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertGreaterThan( 0, absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) );
	}

	/**
	 * Test the restore of all files via a run in the background: the export is disabled.
	 *
	 * @return void
	 */
	public function test_restore_run(): void {
		$ids = array( $this->create_exported_image(), $this->create_exported_image() );

		// start the run without time to restore files directly.
		add_filter( 'efml_import_run_time_limit', '__return_zero' );
		$result = Import_Runs::get_instance()->start_localize();
		remove_filter( 'efml_import_run_time_limit', '__return_zero' );
		$this->assertTrue( $result['success'] );
		$this->assertGreaterThan( 0, $result['run_id'] );
		$this->assertEquals( 0, absint( get_option( 'eml_export' ) ) );
		$this->assertNotEmpty( Import_Runs::get_instance()->get_active_localize_run() );
		$this->assertEquals( 2, Import_Runs::get_instance()->get_progress()['restore_total'] );

		// a second start uses the same run.
		$this->assertEquals( $result['run_id'], Import_Runs::get_instance()->start_localize()['run_id'] );

		// process it in the background.
		Import_Runs::get_instance()->process();

		// all files are restored.
		foreach ( $ids as $attachment_id ) {
			$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
			$this->assertFileExists( get_attached_file( $attachment_id ) );
		}
		$this->assertEmpty( Import_Runs::get_instance()->get_active_localize_run() );

		// nothing is left to restore.
		$this->assertFalse( Import_Runs::get_instance()->start_localize()['success'] );
	}

	/**
	 * Test that the first files are restored directly during the start of the run.
	 *
	 * @return void
	 */
	public function test_restore_run_directly(): void {
		$attachment_id = $this->create_exported_image();

		// start the run: the file is restored at once, the hint of the finished run shows the result.
		$result = Import_Runs::get_instance()->start_localize( true );
		$this->assertTrue( $result['success'] );
		$this->assertSame( '', $result['message'] );
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_valid() );
		$this->assertFileExists( get_attached_file( $attachment_id ) );
		$this->assertEmpty( Import_Runs::get_instance()->get_active_localize_run() );
	}

	/**
	 * Test that a download, which is not an image (e.g. the error page of an expired link), is not used.
	 *
	 * @return void
	 */
	public function test_restore_with_invalid_download(): void {
		$attachment_id = $this->create_exported_image();

		// replace the file on the external source by an error page.
		$target = $this->export_directory . wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) );
		file_put_contents( $target, '<html><body>Link expired</body></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// remove the local copy, so the file must be downloaded.
		wp_delete_file( trailingslashit( wp_get_upload_dir()['basedir'] ) . \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->get_original_paths( $attachment_id )['file'] );

		// the file is not restored and stays an exported file.
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_file( $attachment_id ) );
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_valid() );
	}

	/**
	 * Test the URLs for the download of an exported file from Dropbox: its path for the API is tried first.
	 *
	 * @return void
	 */
	public function test_dropbox_download_urls(): void {
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $attachment_id, EFML_POST_META_URL, 'https://uc123.dl.dropboxusercontent.com/cd/0/inline/abc/file' );
		update_post_meta( $attachment_id, 'eml_service', 'dropbox' );
		update_post_meta( $attachment_id, 'efml_dropbox_path', '/apps/test/image.jpg' );
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		$urls = apply_filters( 'efml_restore_download_urls', array( $external_file_obj->get_url( true ) ), $external_file_obj );
		$this->assertEquals( array( 'dropbox/apps/test/image.jpg', 'https://uc123.dl.dropboxusercontent.com/cd/0/inline/abc/file' ), $urls );
	}

	/**
	 * Test the dialog on the link to deactivate the plugin.
	 *
	 * @return void
	 */
	public function test_deactivation_dialog(): void {
		$links = array( 'deactivate' => '<a href="plugins.php?action=deactivate&amp;plugin=x" id="deactivate-x">Deactivate</a>' );

		// without exported files the link is unchanged.
		$this->assertEquals( $links, \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->add_deactivation_dialog( $links ) );

		// with exported files it opens the dialog.
		$this->create_exported_image();
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->add_deactivation_dialog( $links );
		$this->assertStringContainsString( 'href="#"', $result['deactivate'] );
		$this->assertStringContainsString( 'easy-dialog-for-wordpress', $result['deactivate'] );
		$this->assertStringContainsString( 'efml_restore_exported_files', html_entity_decode( $result['deactivate'] ) );
		$this->assertStringContainsString( 'plugins.php?action=deactivate', html_entity_decode( $result['deactivate'] ) );
	}

	/**
	 * Test the restore with a time limit, e.g. during the uninstallation.
	 *
	 * @return void
	 */
	public function test_restore_until(): void {
		$this->create_exported_image();
		$this->create_exported_image();

		// no time: nothing is restored.
		$this->assertEquals( 2, \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_until( microtime( true ) - 1 ) );

		// enough time: all are restored.
		$this->assertEquals( 0, \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_until( microtime( true ) + 60 ) );
	}
}
