<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Export_By_Size.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Export_By_Size.
 */
class Export_By_Size extends externalFilesTests {
	/**
	 * The attachment ID of the external file.
	 *
	 * @var int
	 */
	private int $attachment_id = 0;

	/**
	 * The file size of the external file.
	 *
	 * @var int
	 */
	private int $file_size = 0;

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// enable the extension.
		update_option( 'eml_export_extensions', array( 'export_by_size' ) );

		// import an external file.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );
		$this->attachment_id = $external_file_obj->get_id();
		$this->file_size     = $external_file_obj->get_filesize();
		$this->assertGreaterThan( 10, $this->file_size );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'eml_export_extensions' );
		delete_option( 'eml_export_min_size' );
		delete_option( 'eml_export_max_size' );
		parent::tear_down();
	}

	/**
	 * Return the row actions after our filter.
	 *
	 * @param int $min_size The minimum size.
	 * @param int $max_size The maximum size.
	 *
	 * @return array<string,string>
	 */
	private function get_actions( int $min_size, int $max_size ): array {
		update_option( 'eml_export_min_size', $min_size );
		update_option( 'eml_export_max_size', $max_size );

		$post = get_post( $this->attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		return \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Export_By_Size::get_instance()->change_media_row_actions( array( 'eml-export-file' => 'export' ), $post );
	}

	/**
	 * Test if the export action is kept if no sizes are configured.
	 *
	 * @return void
	 */
	public function test_action_is_kept_without_sizes(): void {
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( 0, 0 ) );
	}

	/**
	 * Test if the export action is kept, if only a minimum size is set and the file is bigger.
	 *
	 * @return void
	 */
	public function test_action_is_kept_with_min_size_only(): void {
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( $this->file_size - 10, 0 ) );
	}

	/**
	 * Test if the export action is removed, if the file is smaller than the minimum size.
	 *
	 * @return void
	 */
	public function test_action_is_removed_below_min_size(): void {
		$this->assertArrayNotHasKey( 'eml-export-file', $this->get_actions( $this->file_size + 10, 0 ) );
	}

	/**
	 * Test if the export action is kept, if only a maximum size is set and the file is smaller.
	 *
	 * @return void
	 */
	public function test_action_is_kept_with_max_size_only(): void {
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( 0, $this->file_size + 10 ) );
	}

	/**
	 * Test if the export action is removed, if the file is bigger than the maximum size.
	 *
	 * @return void
	 */
	public function test_action_is_removed_above_max_size(): void {
		$this->assertArrayNotHasKey( 'eml-export-file', $this->get_actions( 0, $this->file_size - 10 ) );
	}

	/**
	 * Test the boundaries: the same rules as for the export itself must be used.
	 *
	 * @return void
	 */
	public function test_action_boundaries(): void {
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( $this->file_size, 0 ) );
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( 0, $this->file_size ) );
		$this->assertArrayHasKey( 'eml-export-file', $this->get_actions( $this->file_size, $this->file_size ) );
	}
}
