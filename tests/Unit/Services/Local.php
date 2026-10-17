<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Local.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Local.
 */
class Local extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\Local::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$directory = \ExternalFilesInMediaLibrary\Services\Local::get_instance()->get_directory();
		$this->assertIsString( $directory );
		$this->assertEmpty( $directory );
	}

	/**
	 * Test if generic directories (like "vendor") are hidden in the listing and other directories are not.
	 *
	 * @return void
	 */
	public function test_hide_generic_directories(): void {
		// create the directories.
		$base = trailingslashit( get_temp_dir() ) . 'efml-local-' . wp_generate_password( 8, false ) . '/';
		wp_mkdir_p( $base . 'node_modules' );
		wp_mkdir_p( $base . 'vendor' );
		wp_mkdir_p( $base . 'images' );
		wp_mkdir_p( $base . 'vendor/package' );
		file_put_contents( $base . 'vendor.pdf', 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$obj = \ExternalFilesInMediaLibrary\Services\Local::get_instance();

		// generic directories are hidden.
		$this->assertTrue( $obj->hide_generic_directories( false, $base . 'node_modules', $base ) );
		$this->assertTrue( $obj->hide_generic_directories( false, $base . 'vendor', $base ) );

		// other directories are not hidden, also inside a generic directory.
		$this->assertFalse( $obj->hide_generic_directories( false, $base . 'images', $base ) );
		$this->assertFalse( $obj->hide_generic_directories( false, $base . 'vendor/package', $base . 'vendor/' ) );

		// files are not changed.
		$this->assertFalse( $obj->hide_generic_directories( false, $base . 'vendor.pdf', $base ) );

		// the result of other filters is kept.
		$this->assertTrue( $obj->hide_generic_directories( true, $base . 'images', $base ) );
	}
}
