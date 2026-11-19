<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Protocols;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File.
 */
class File extends externalFilesTests {
	/**
	 * The directory for the test files.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare each test: a directory for the test files inside the allowed directory for local files.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$upload_dir      = wp_upload_dir();
		$this->directory = trailingslashit( $upload_dir['basedir'] ) . 'efml-protocol-file-' . strtolower( wp_generate_password( 8, false, false ) ) . '/';
		wp_mkdir_p( $this->directory );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Helper::get_wp_filesystem()->delete( $this->directory, true );
		parent::tear_down();
	}

	/**
	 * Test that the temporary file of a local file is a complete copy of it.
	 *
	 * @return void
	 */
	public function test_get_temp_file_copies_the_file(): void {
		$file = $this->directory . 'example.jpg';
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', $file );

		$url      = 'file://' . $file;
		$obj      = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File( $url );
		$tmp_file = $obj->get_temp_file( $url, Helper::get_wp_filesystem() );
		$this->assertIsString( $tmp_file );
		$this->assertNotSame( $file, $tmp_file );
		$this->assertStringEndsWith( '.jpg', $tmp_file );
		$this->assertFileEquals( $file, $tmp_file );

		// the cleanup deletes only the temporary file, not the original.
		$obj->cleanup_temp_file( $tmp_file );
		$this->assertFileDoesNotExist( $tmp_file );
		$this->assertFileExists( $file );
	}

	/**
	 * Test that an empty local file results in no temporary file.
	 *
	 * @return void
	 */
	public function test_get_temp_file_with_empty_file(): void {
		$file = $this->directory . 'empty.jpg';
		touch( $file );

		$temp_files = glob( trailingslashit( get_temp_dir() ) . '*.jpg' );
		$url        = 'file://' . $file;
		$obj        = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File( $url );
		$this->assertFalse( @$obj->get_temp_file( $url, Helper::get_wp_filesystem() ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( $temp_files, glob( trailingslashit( get_temp_dir() ) . '*.jpg' ) );
	}

	/**
	 * Test that a file outside the allowed directory for local files is not copied.
	 *
	 * @return void
	 */
	public function test_get_temp_file_outside_allowed_directory(): void {
		$url = 'file:///etc/hostname';
		$obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File( $url );
		$this->assertFalse( @$obj->get_temp_file( $url, Helper::get_wp_filesystem() ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
