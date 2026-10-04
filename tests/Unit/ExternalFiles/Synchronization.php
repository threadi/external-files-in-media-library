<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Synchronization.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Synchronization.
 */
class Synchronization extends externalFilesTests {

	/**
	 * Test if the synchronized files of an external source are not deleted, if the external source
	 * could not be read during the synchronization (e.g., it is not reachable).
	 *
	 * @return void
	 */
	public function test_sync_does_not_delete_files_if_source_is_not_readable(): void {
		// enable the deletion of unused files after each sync and allow the deletion of synchronized files.
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 0 );

		// create the external source with a URL, which is not readable.
		$url     = self::get_faulty_test_file( 'pdf', 'http' );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'http', $url, array() );
		$this->assertGreaterThan( 0, $term_id );

		// add a file and assign it to this external source as synchronized file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$attachment_id = $external_file_obj->get_id();
		wp_set_object_terms( $attachment_id, $term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() );
		\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->mark_as_synced( $external_file_obj );

		// run the synchronization.
		\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->sync( $url, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id ), $term_id );

		// the file must still exist.
		$this->assertInstanceOf( \WP_Post::class, get_post( $attachment_id ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) ) );

		// reset the settings.
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
	}

	/**
	 * Marker whether the synchronization was marked as running during the import.
	 *
	 * @var bool
	 */
	private bool $was_running_during_import = false;

	/**
	 * Save whether the synchronization is marked as running.
	 *
	 * @return void
	 */
	public function check_running_state(): void {
		$this->was_running_during_import = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->is_running();
	}

	/**
	 * Test if a synchronization is only marked as running while it is running.
	 *
	 * @return void
	 */
	public function test_is_running(): void {
		$sync_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance();

		// not running before the synchronization.
		$this->assertFalse( $sync_obj->is_running() );

		// create the external source with a URL, which is not readable.
		$url     = self::get_faulty_test_file( 'pdf', 'http' );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'http', $url, array() );
		$this->assertGreaterThan( 0, $term_id );

		// run the synchronization and check the state during the import.
		add_action( 'efml_before_import', array( $this, 'check_running_state' ) );
		$sync_obj->sync( $url, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id ), $term_id );
		remove_action( 'efml_before_import', array( $this, 'check_running_state' ) );
		$this->assertTrue( $this->was_running_during_import );

		// not running after the synchronization.
		$this->assertFalse( $sync_obj->is_running() );
	}
}
