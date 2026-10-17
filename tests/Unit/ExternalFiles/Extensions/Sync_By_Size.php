<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size.
 */
class Sync_By_Size extends externalFilesTests {
	/**
	 * The term ID of the external source used in the tests.
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

		// create an external source.
		$this->term_id = self::factory()->term->create( array( 'taxonomy' => \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() ) );

		// reset the global settings.
		update_option( 'eml_sync_min_size', 0 );
		update_option( 'eml_sync_max_size', 0 );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size::get_instance()->remove_hooks_after_sync();
		parent::tear_down();
	}

	/**
	 * Return the file data for a file with the given size.
	 *
	 * @param int $size The file size.
	 *
	 * @return array<string,mixed>
	 */
	private function get_file_data( int $size ): array {
		return array(
			'url'      => 'https://example.com/example.pdf',
			'filesize' => $size,
		);
	}

	/**
	 * Return the test cases for the size checks.
	 *
	 * @return array<string,array<int,int|bool|string>>
	 */
	public function provide_sizes(): array {
		return array(
			'no limits'                  => array( 'global', 0, 0, 500, false ),
			'global min, size below'     => array( 'global', 100, 0, 99, true ),
			'global min, size equal'     => array( 'global', 100, 0, 100, false ),
			'global min, size above'     => array( 'global', 100, 0, 101, false ),
			'global max, size below'     => array( 'global', 0, 100, 99, false ),
			'global max, size equal'     => array( 'global', 0, 100, 100, false ),
			'global max, size above'     => array( 'global', 0, 100, 101, true ),
			'global range, size inside'  => array( 'global', 100, 200, 150, false ),
			'global range, size outside' => array( 'global', 100, 200, 250, true ),
			'term min, size below'       => array( 'term', 100, 0, 99, true ),
			'term min, size above'       => array( 'term', 100, 0, 101, false ),
			'term max, size above'       => array( 'term', 0, 100, 101, true ),
			'term range, size inside'    => array( 'term', 100, 200, 150, false ),
		);
	}

	/**
	 * Test the prevention of the synchronization by the file size.
	 *
	 * @dataProvider provide_sizes
	 *
	 * @param string $level The level of the setting ("global" or "term").
	 * @param int    $min_size The minimum size.
	 * @param int    $max_size The maximum size.
	 * @param int    $file_size The file size.
	 * @param bool   $expected The expected result.
	 *
	 * @return void
	 */
	public function test_prevent_sync( string $level, int $min_size, int $max_size, int $file_size, bool $expected ): void {
		if ( 'global' === $level ) {
			update_option( 'eml_sync_min_size', $min_size );
			update_option( 'eml_sync_max_size', $max_size );
		} else {
			update_term_meta( $this->term_id, 'eml_sync_min_size', $min_size );
			update_term_meta( $this->term_id, 'eml_sync_max_size', $max_size );
		}

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size::get_instance();
		$obj->add_hooks_before_sync( '', array(), $this->term_id );
		$this->assertSame( $expected, $obj->prevent_sync( false, $this->get_file_data( $file_size ) ) );
	}

	/**
	 * Test if file data without file size does not result in a warning and are not prevented.
	 *
	 * @return void
	 */
	public function test_prevent_sync_without_file_size(): void {
		update_option( 'eml_sync_min_size', 100 );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size::get_instance();
		$obj->add_hooks_before_sync( '', array(), $this->term_id );
		$this->assertFalse( $obj->prevent_sync( false, array( 'url' => 'https://example.com/example.pdf' ) ) );
	}

	/**
	 * Test if the hooks are removed after the synchronization, so following imports are not affected.
	 *
	 * @return void
	 */
	public function test_hooks_are_removed_after_sync(): void {
		update_option( 'eml_sync_min_size', 100 );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size::get_instance();
		$obj->add_hooks_before_sync( '', array(), $this->term_id );
		$this->assertNotFalse( has_filter( 'efml_prevent_file_import', array( $obj, 'prevent_sync' ) ) );

		// end the synchronization.
		$obj->remove_hooks_after_sync();
		$this->assertFalse( has_filter( 'efml_prevent_file_import', array( $obj, 'prevent_sync' ) ) );

		// a following import of a small file must not be prevented.
		$this->assertFalse( $obj->prevent_sync( false, $this->get_file_data( 10 ) ) );
	}

	/**
	 * Test if the sizes are saved from the fields of the configuration dialog.
	 *
	 * @return void
	 */
	public function test_save_sync_config_uses_the_fields(): void {
		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_By_Size::get_instance();
		$obj->save_sync_config(
			array(
				'term_id'  => $this->term_id,
				'min_size' => '100',
				'max_size' => '2000',
			),
			$this->term_id
		);
		$this->assertEquals( 100, absint( get_term_meta( $this->term_id, 'eml_sync_min_size', true ) ) );
		$this->assertEquals( 2000, absint( get_term_meta( $this->term_id, 'eml_sync_max_size', true ) ) );

		// saving without the fields removes the limits.
		$obj->save_sync_config( array( 'term_id' => $this->term_id ), $this->term_id );
		$this->assertEquals( 0, absint( get_term_meta( $this->term_id, 'eml_sync_min_size', true ) ) );
		$this->assertEquals( 0, absint( get_term_meta( $this->term_id, 'eml_sync_max_size', true ) ) );
	}
}
