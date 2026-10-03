<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Local\Export.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Local\Export.
 */
class LocalExport extends externalFilesTests {

	/**
	 * The directory which is allowed for local files.
	 *
	 * @var string
	 */
	private string $allowed_directory = '';

	/**
	 * A directory which is not allowed for local files.
	 *
	 * @var string
	 */
	private string $forbidden_directory = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create the directories.
		$this->allowed_directory   = trailingslashit( get_temp_dir() ) . 'efml-allowed-' . uniqid() . '/';
		$this->forbidden_directory = trailingslashit( get_temp_dir() ) . 'efml-forbidden-' . uniqid() . '/';
		wp_mkdir_p( $this->allowed_directory );
		wp_mkdir_p( $this->forbidden_directory );

		// allow only one of them.
		add_filter( 'efml_file_base', array( $this, 'get_allowed_directory' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_file_base', array( $this, 'get_allowed_directory' ) );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->allowed_directory );
		\ExternalFilesInMediaLibrary\Plugin\Helper::delete_directory_recursively( $this->forbidden_directory );
		parent::tear_down();
	}

	/**
	 * Return the allowed directory.
	 *
	 * @return string
	 */
	public function get_allowed_directory(): string {
		return $this->allowed_directory;
	}

	/**
	 * Test if a file in the allowed directory is deleted.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_in_allowed_directory(): void {
		$path = $this->allowed_directory . 'example.pdf';
		file_put_contents( $path, 'test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// with protocol.
		$this->assertTrue( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->delete_exported_file( 'file://' . $path, array(), 0 ) );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Test if a file outside the allowed directory is not deleted.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_outside_allowed_directory(): void {
		$path = $this->forbidden_directory . 'wp-config.php';
		file_put_contents( $path, 'test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// directly and via a path, which leaves the allowed directory.
		$this->assertFalse( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->delete_exported_file( 'file://' . $path, array(), 0 ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->delete_exported_file( 'file://' . $this->allowed_directory . '../' . basename( $this->forbidden_directory ) . '/wp-config.php', array(), 0 ) );
		$this->assertFileExists( $path );
	}

	/**
	 * Test if a directory is never deleted.
	 *
	 * @return void
	 */
	public function test_delete_exported_file_does_not_delete_directories(): void {
		$directory = $this->allowed_directory . 'sub/';
		wp_mkdir_p( $directory );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->delete_exported_file( 'file://' . $directory, array(), 0 ) );
		$this->assertDirectoryExists( $directory );
	}

	/**
	 * Test if a file is exported to the allowed directory, but not to another directory.
	 *
	 * @return void
	 */
	public function test_export_file(): void {
		$attachment_id = self::factory()->attachment->create_upload_object( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'tests/Data/example.pdf' );
		$this->assertGreaterThan( 0, $attachment_id );

		// export to the not allowed directory.
		$target = 'file://' . $this->forbidden_directory . 'example.pdf';
		$this->assertFalse( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->export_file( $attachment_id, $target, array() ) );
		$this->assertFileDoesNotExist( $this->forbidden_directory . 'example.pdf' );

		// export to the allowed directory.
		$target = 'file://' . $this->allowed_directory . 'example.pdf';
		$this->assertEquals( $target, \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->export_file( $attachment_id, $target, array() ) );
		$this->assertFileExists( $this->allowed_directory . 'example.pdf' );

		// a second export must not overwrite the existing file.
		$this->assertFalse( \ExternalFilesInMediaLibrary\Services\Local\Export::get_instance()->export_file( $attachment_id, $target, array() ) );
	}
}
