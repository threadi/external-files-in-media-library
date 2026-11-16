<?php
/**
 * Tests for the deletion of the local copies of exported files afterward.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Copies as Local_Copies_Object;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations;
use ExternalFilesInMediaLibrary\ExternalFiles\Restore;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the deletion of the local copies of exported files.
 */
class LocalCopies extends externalFilesTests {

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
		$this->export_directory = trailingslashit( get_temp_dir() ) . 'efml-local-copies-' . uniqid() . '/';
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

		// enable the export, which keeps the local files.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		update_option( 'eml_export_do_not_delete_local_files', 1 );
		update_option( 'eml_export_folders', 0 );
		update_option( 'eml_export_in_background', 0 );
		if ( false === has_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ) ) ) {
			add_filter( 'wp_update_attachment_metadata', array( Export::get_instance(), 'update_attachment_metadata' ), 10, 2 );
		}
		Local_Copies_Object::get_instance()->reset_cache();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_export_directory' ) );
		Import_Runs::get_instance()->cancel_all();
		Local_Copies_Object::get_instance()->reset_cache();
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
	 * Create an exported image with local copies and return its ID and its local files.
	 *
	 * @param int $width  The width.
	 * @param int $height The height.
	 *
	 * @return array{id: int, files: array<int,string>}
	 */
	private function create_exported_image( int $width = 800, int $height = 600 ): array {
		$file  = trailingslashit( get_temp_dir() ) . 'efml-local-copies-' . wp_generate_password( 6, false ) . '.jpg';
		$image = imagecreatetruecolor( $width, $height );
		imagejpeg( $image, $file );
		$attachment_id = self::factory()->attachment->create_upload_object( $file );
		wp_delete_file( $file );
		$this->assertIsInt( $attachment_id );

		// collect its local files.
		$local    = (string) get_attached_file( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$files    = array( $local );
		foreach ( (array) ( $metadata['sizes'] ?? array() ) as $size ) {
			$files[] = trailingslashit( dirname( $local ) ) . $size['file'];
		}
		if ( ! empty( $metadata['original_image'] ) ) {
			$files[] = trailingslashit( dirname( $local ) ) . $metadata['original_image'];
		}

		// export it.
		$this->assertTrue( Export::get_instance()->export_file( $attachment_id ) );
		foreach ( $files as $local_file ) {
			$this->assertFileExists( $local_file );
		}

		// return the data.
		return array(
			'id'    => $attachment_id,
			'files' => array_values( array_unique( $files ) ),
		);
	}

	/**
	 * Test the deletion of the local copies of an exported image.
	 *
	 * @return void
	 */
	public function test_delete_local_copies(): void {
		$image = $this->create_exported_image( 3000, 2000 );

		// it has local copies.
		$this->assertTrue( Local_Copies_Object::get_instance()->has_files() );
		$this->assertEquals( 1, Local_Copies_Object::get_instance()->count_files() );

		// delete them via the run.
		$result = Import_Runs::get_instance()->start_delete_local();
		$this->assertTrue( $result['success'] );

		// the local files are deleted, the image is still an exported file with its files on the external source.
		foreach ( $image['files'] as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
		$external_file_obj = Files::get_instance()->get_file( $image['id'] );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertFileExists( $this->export_directory . wp_basename( $external_file_obj->get_url( true ) ) );
		$this->assertTrue( Sizes::get_instance()->has_sizes( $image['id'] ) );
		foreach ( Sizes::get_instance()->get_sizes( $image['id'], true ) as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}
		$this->assertArrayNotHasKey( 'sizes', (array) wp_get_attachment_metadata( $image['id'], true ) );

		// no local copies are left.
		Local_Copies_Object::get_instance()->reset_cache();
		$this->assertFalse( Local_Copies_Object::get_instance()->has_files() );
		$this->assertFalse( Import_Runs::get_instance()->start_delete_local()['success'] );

		// it could be restored afterward.
		$this->assertTrue( Restore::get_instance()->restore_file( $image['id'] ) );
		$this->assertFileExists( $image['files'][0] );
	}

	/**
	 * Test that the local copies are kept, if the file could not be loaded from its external source.
	 *
	 * @return void
	 */
	public function test_keep_if_not_on_external_source(): void {
		$image = $this->create_exported_image();

		// remove the file on the external source.
		wp_delete_file( $this->export_directory . wp_basename( Files::get_instance()->get_file( $image['id'] )->get_url( true ) ) );

		// the local copies are kept.
		$this->assertFalse( Local_Copies_Object::get_instance()->delete_local_copies( $image['id'] ) );
		foreach ( $image['files'] as $file ) {
			$this->assertFileExists( $file );
		}
	}

	/**
	 * Test that only exported sizes are deleted, after the missing sizes have been exported.
	 *
	 * @return void
	 */
	public function test_sizes_are_exported_before(): void {
		$image = $this->create_exported_image();

		// simulate an export without sizes (before 6.0.0).
		foreach ( Sizes::get_instance()->get_sizes( $image['id'] ) as $size ) {
			wp_delete_file( $this->export_directory . $size['file'] );
		}
		delete_post_meta( $image['id'], Sizes::META );
		$this->assertFalse( Sizes::get_instance()->has_sizes( $image['id'] ) );

		// the sizes are exported, then the local copies are deleted.
		$this->assertTrue( Local_Copies_Object::get_instance()->delete_local_copies( $image['id'] ) );
		$this->assertTrue( Sizes::get_instance()->has_sizes( $image['id'] ) );
		foreach ( Sizes::get_instance()->get_sizes( $image['id'] ) as $size ) {
			$this->assertFileExists( $this->export_directory . $size['file'] );
		}
		foreach ( $image['files'] as $file ) {
			$this->assertFileDoesNotExist( $file );
		}
	}

	/**
	 * Test that sizes, which are not on the external source, are kept.
	 *
	 * @return void
	 */
	public function test_not_exported_size_is_kept(): void {
		$image = $this->create_exported_image();

		// forget the export of one size.
		$data = Sizes::get_instance()->get_data( $image['id'] );
		$main = $data['main'];
		$name = (string) array_key_first( $data['terms'][ $main ] );
		$file = trailingslashit( dirname( $image['files'][0] ) ) . (string) \ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls::get_instance()->get_local_sizes( $image['id'] )[ $name ];
		unset( $data['terms'][ $main ][ $name ] );
		Sizes::get_instance()->save_data( $image['id'], $data );

		// delete the local copies.
		$this->assertTrue( Local_Copies_Object::get_instance()->delete_local_copies( $image['id'] ) );

		// the size is kept, the file itself is deleted.
		$this->assertFileExists( $file );
		$this->assertFileDoesNotExist( $image['files'][0] );
	}

	/**
	 * Test that files taken over from other offload plugins keep their local files.
	 *
	 * @return void
	 */
	public function test_migrated_file_keeps_local_files(): void {
		$image = $this->create_exported_image();
		update_post_meta( $image['id'], Migrations::BACKUP_META, array( 'plugin' => 'test' ) );

		$this->assertEquals( 0, Local_Copies_Object::get_instance()->count_files() );
		$this->assertFalse( Local_Copies_Object::get_instance()->delete_local_copies( $image['id'] ) );
		$this->assertFileExists( $image['files'][0] );
	}

	/**
	 * Test that the deletion is not started on a copy of the website.
	 *
	 * @return void
	 */
	public function test_not_on_copy(): void {
		$image = $this->create_exported_image();
		add_filter( 'efml_is_website_copy', '__return_true' );
		$result = Import_Runs::get_instance()->start_delete_local();
		remove_filter( 'efml_is_website_copy', '__return_true' );
		$this->assertFalse( $result['success'] );
		$this->assertFileExists( $image['files'][0] );
	}
}
