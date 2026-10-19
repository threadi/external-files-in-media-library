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

	/**
	 * Test if the protection against the deletion of synchronized files is active again after deleting synchronized files.
	 *
	 * @return void
	 */
	public function test_prevent_deletion_is_active_after_deleting_synced_files(): void {
		$obj      = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance();
		$priority = has_filter( 'pre_delete_attachment', array( $obj, 'prevent_deletion' ) );

		$term_id = self::factory()->term->create( array( 'taxonomy' => \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() ) );
		$obj->delete_synced_files( $term_id );

		$this->assertSame( $priority, has_filter( 'pre_delete_attachment', array( $obj, 'prevent_deletion' ) ) );
	}

	/**
	 * The hooks, which have been active during the import of the synchronization.
	 *
	 * @var array<string,mixed>
	 */
	private array $hooks_during_import = array();

	/**
	 * Save the hooks and credentials, which are active during the import of the synchronization.
	 *
	 * @return void
	 */
	public function check_hooks_during_import(): void {
		$sync_obj                  = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance();
		$this->hooks_during_import = array(
			'duplicate_check' => has_filter( 'efml_duplicate_check', array( $sync_obj, 'disable_duplicate_check' ) ),
			'mark_as_synced'  => has_action( 'efml_after_file_save', array( $sync_obj, 'mark_as_synced' ) ),
			'sftp_file_check' => has_action( 'efml_sftp_directory_import_file_check', array( $sync_obj, 'update_url_count' ) ),
			'fields'          => \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->get_fields(),
		);
	}

	/**
	 * Test if the credentials and hooks of a synchronization are only used during its import and are removed afterward,
	 * so following imports in the same request are not changed by them.
	 *
	 * @return void
	 */
	public function test_credentials_and_hooks_are_removed_after_sync(): void {
		$sync_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance();
		$import   = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance();

		// create the external source with credentials.
		$url     = self::get_test_file( 'pdf', 'http' );
		$fields  = array(
			'login'    => array( 'value' => 'user' ),
			'password' => array( 'value' => 'secret' ),
		);
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'http', $url, $fields );
		$this->assertGreaterThan( 0, $term_id );
		$term_data = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id );

		// run the synchronization and check the state during the import.
		add_action( 'efml_before_import', array( $this, 'check_hooks_during_import' ) );
		$sync_obj->sync( $url, $term_data, $term_id );
		remove_action( 'efml_before_import', array( $this, 'check_hooks_during_import' ) );

		// the hooks and credentials have been used during the import.
		$this->assertSame( 10, $this->hooks_during_import['duplicate_check'] );
		$this->assertSame( 10, $this->hooks_during_import['mark_as_synced'] );
		$this->assertSame( 10, $this->hooks_during_import['sftp_file_check'] );
		$this->assertSame( $term_data['fields'] ?? array(), $this->hooks_during_import['fields'] );

		// they are removed after the synchronization.
		$this->assertFalse( has_filter( 'efml_duplicate_check', array( $sync_obj, 'disable_duplicate_check' ) ) );
		$this->assertFalse( has_action( 'efml_after_file_save', array( $sync_obj, 'mark_as_synced' ) ) );
		$this->assertFalse( has_filter( 'efml_file_import_attachment', array( $sync_obj, 'get_attachment_id' ) ) );
		$this->assertSame( array(), $import->get_fields() );
		$this->assertSame( 0, $import->get_term_id() );

		// a following import of the same URL is detected as duplicate again.
		$count = $this->get_media_file_count();
		$this->assertGreaterThan( 0, $count );
		$this->assertFalse( $import->add_url( $url ) );
		$this->assertSame( $count, $this->get_media_file_count() );
	}

	/**
	 * Test if credentials set before a synchronization are restored afterward.
	 *
	 * @return void
	 */
	public function test_previous_credentials_are_restored_after_sync(): void {
		$import = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance();
		$fields = array( 'login' => array( 'value' => 'other' ) );
		$import->set_fields( $fields );

		// run the synchronization of a not readable source.
		$url     = self::get_faulty_test_file( 'pdf', 'http' );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'http', $url, array() );
		\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->sync( $url, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id ), $term_id );

		// the previous credentials are used again.
		$this->assertSame( $fields, $import->get_fields() );
		$import->set_fields( array() );
	}
}
