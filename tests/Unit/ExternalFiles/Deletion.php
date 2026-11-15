<?php
/**
 * Tests for the deletion of exported files everywhere.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work as Local_Work_Object;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes as Sizes_Object;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test the deletion of exported files everywhere.
 */
class Deletion extends externalFilesTests {

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-deletion-' . uniqid() . '/';
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

		// make sure the hooks of the export are active (they are only added if the export was enabled during the start).
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}
		if ( false === has_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) ) ) {
			add_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		}

		// allow the work with local files in the tests (they run outside the backend).
		add_filter( 'efml_local_work_allowed', '__return_true' );

	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		remove_all_filters( 'efml_file_prevent_proxied_url' );
		remove_filter( 'efml_local_work_allowed', '__return_true' );
		remove_image_size( 'efml-test' );
		\ExternalFilesInMediaLibrary\ExternalFiles\Local_Work::get_instance()->close_all();
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
	private function create_exported_image( int $width = 3000, int $height = 2000 ): int {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-size-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		return $attachment_id;
	}

	/**
	 * Return the files of the given image in the export directory.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	private function get_exported_files_of( int $attachment_id ): array {
		$name = pathinfo( (string) get_post_meta( $attachment_id, 'efml_export_filename', true ), PATHINFO_FILENAME );
		$name = (string) preg_replace( '/-scaled$/', '', $name );
		return (array) glob( $this->export_directory . $name . '*' );
	}

	/**
	 * Return the local files of the given image in the uploads directory.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	private function get_local_files_of( int $attachment_id ): array {
		$path = \ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls::get_instance()->get_local_paths( $attachment_id )[0];
		$name = (string) preg_replace( '/-scaled$/', '', pathinfo( $path, PATHINFO_FILENAME ) );
		return (array) glob( trailingslashit( wp_get_upload_dir()['basedir'] ) . dirname( $path ) . '/' . $name . '*' );
	}

	/**
	 * Test that the local copies, which have been kept after the export, are deleted together with the image.
	 *
	 * @return void
	 */
	public function test_local_copies_are_deleted(): void {
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		$attachment_id = $this->create_exported_image();
		$local_files   = $this->get_local_files_of( $attachment_id );
		$this->assertNotEmpty( $local_files );

		wp_delete_attachment( $attachment_id, true );

		foreach ( $local_files as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
	}

	/**
	 * Test that the files are deleted on the external source, also if the export has been disabled for it.
	 *
	 * @return void
	 */
	public function test_deletion_with_disabled_export_of_source(): void {
		$attachment_id = $this->create_exported_image();
		$files         = $this->get_exported_files_of( $attachment_id );
		$this->assertNotEmpty( $files );

		// disable the export for this external source.
		foreach ( Export::get_instance()->get_export_terms() as $term_id ) {
			Export::get_instance()->set_state_for_term( $term_id, 0 );
		}

		wp_delete_attachment( $attachment_id, true );

		foreach ( $files as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
	}

	/**
	 * Test that the files are deleted on the external source, also if the export has been disabled completely.
	 *
	 * @return void
	 */
	public function test_deletion_with_disabled_export(): void {
		$attachment_id = $this->create_exported_image();
		$files         = $this->get_exported_files_of( $attachment_id );
		update_option( 'eml_export', 0 );

		wp_delete_attachment( $attachment_id, true );

		foreach ( $files as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
	}

	/**
	 * Test that files, which could not be deleted, are deleted later via the queue.
	 *
	 * @return void
	 */
	public function test_deletion_via_queue(): void {
		global $wpdb;

		$attachment_id = $this->create_exported_image();
		$files         = $this->get_exported_files_of( $attachment_id );

		// delete the image while the service is not available.
		add_filter( 'efml_export_object', '__return_false', 999 );
		wp_delete_attachment( $attachment_id, true );
		remove_filter( 'efml_export_object', '__return_false', 999 );

		// the files still exist and are in the queue.
		foreach ( $files as $file ) {
			$this->assertFileExists( $file );
		}
		$this->assertEquals( 1, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `type` = %s AND `attachment_id` = %d', $wpdb->prefix . 'eml_queue', 'delete', $attachment_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// process the queue.
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->process_queue();

		// the files are deleted, the entry is removed and no post meta of the deleted image is left.
		foreach ( $files as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
		$this->assertEquals( 0, absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `type` = %s AND `attachment_id` = %d', $wpdb->prefix . 'eml_queue', 'delete', $attachment_id ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertEmpty( get_post_meta( $attachment_id ) );
	}
}
