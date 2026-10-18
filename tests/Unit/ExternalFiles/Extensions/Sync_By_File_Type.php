<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type.
 */
class Sync_By_File_Type extends externalFilesTests {
	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( 'eml_sync_file_types' );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance()->remove_hooks_after_sync();
		parent::tear_down();
	}

	/**
	 * Create an external source.
	 *
	 * @return int
	 */
	private function create_source(): int {
		return self::factory()->term->create( array( 'taxonomy' => \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() ) );
	}

	/**
	 * Return file data for a file with the given mime type.
	 *
	 * @param string $mime_type The mime type.
	 *
	 * @return array<string,string>
	 */
	private function get_file_data( string $mime_type ): array {
		return array(
			'url'       => 'https://example.com/example',
			'mime-type' => $mime_type,
		);
	}

	/**
	 * Test if only the allowed file types of the external source are synchronized.
	 *
	 * @return void
	 */
	public function test_prevent_sync_by_term_setting(): void {
		$term_id = $this->create_source();
		update_term_meta( $term_id, 'eml_sync_file_types', array( 'image/png' ) );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();
		$obj->add_hooks_before_sync( '', array(), $term_id );
		$this->assertNotFalse( has_filter( 'efml_prevent_file_import', array( $obj, 'prevent_sync' ) ) );
		$this->assertFalse( $obj->prevent_sync( false, $this->get_file_data( 'image/png' ) ) );
		$this->assertTrue( $obj->prevent_sync( false, $this->get_file_data( 'application/pdf' ) ) );
	}

	/**
	 * Test if the global setting is used.
	 *
	 * @return void
	 */
	public function test_prevent_sync_by_global_setting(): void {
		update_option( 'eml_sync_file_types', array( 'application/pdf' ) );
		$term_id = $this->create_source();

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();
		$obj->add_hooks_before_sync( '', array(), $term_id );
		$this->assertFalse( $obj->prevent_sync( false, $this->get_file_data( 'application/pdf' ) ) );
		$this->assertTrue( $obj->prevent_sync( false, $this->get_file_data( 'image/png' ) ) );
	}

	/**
	 * Test if no hook is added, if no file types are configured, also if the global option does not exist.
	 *
	 * @return void
	 */
	public function test_no_hook_without_settings(): void {
		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();
		$obj->add_hooks_before_sync( '', array(), $this->create_source() );
		$this->assertFalse( has_filter( 'efml_prevent_file_import', array( $obj, 'prevent_sync' ) ) );
	}

	/**
	 * Test if the settings of an external source are not used for the next external source synchronized in the same request.
	 *
	 * Without this the files of the second external source would not be synchronized and deleted during the cleanup.
	 *
	 * @return void
	 */
	public function test_settings_are_not_used_for_next_source(): void {
		// the first external source allows only PNG.
		$term_a = $this->create_source();
		update_term_meta( $term_a, 'eml_sync_file_types', array( 'image/png' ) );

		// the second external source has no limits.
		$term_b = $this->create_source();

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();

		// sync the first source.
		$obj->add_hooks_before_sync( '', array(), $term_a );
		$this->assertTrue( apply_filters( 'efml_prevent_file_import', false, $this->get_file_data( 'application/pdf' ), '' ) );
		$obj->remove_hooks_after_sync();

		// sync the second source: the PDF must be allowed.
		$obj->add_hooks_before_sync( '', array(), $term_b );
		$this->assertFalse( apply_filters( 'efml_prevent_file_import', false, $this->get_file_data( 'application/pdf' ), '' ) );
	}

	/**
	 * Test if the next source is not affected, even if the after-sync-hook was not run.
	 *
	 * @return void
	 */
	public function test_settings_are_reset_on_next_sync_start(): void {
		$term_a = $this->create_source();
		update_term_meta( $term_a, 'eml_sync_file_types', array( 'image/png' ) );
		$term_b = $this->create_source();

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();
		$obj->add_hooks_before_sync( '', array(), $term_a );
		$obj->add_hooks_before_sync( '', array(), $term_b );
		$this->assertFalse( $obj->prevent_sync( false, $this->get_file_data( 'application/pdf' ) ) );
	}

	/**
	 * Test if file data without a mime type does not result in a warning.
	 *
	 * @return void
	 */
	public function test_prevent_sync_without_mime_type(): void {
		$term_id = $this->create_source();
		update_term_meta( $term_id, 'eml_sync_file_types', array( 'image/png' ) );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_File_Type::get_instance();
		$obj->add_hooks_before_sync( '', array(), $term_id );
		$this->assertTrue( $obj->prevent_sync( false, array( 'url' => 'https://example.com/example' ) ) );
	}
}
