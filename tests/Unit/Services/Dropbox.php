<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\DropBox.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\DropBox.
 */
class Dropbox extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\Dropbox::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$directory = \ExternalFilesInMediaLibrary\Services\Dropbox::get_instance()->get_directory();
		$this->assertIsString( $directory );
		$this->assertNotEmpty( $directory );
		$this->assertEquals( 'DropBox', $directory );
	}

	/**
	 * Test if the content type check is only disabled for Dropbox URLs.
	 *
	 * @return void
	 */
	public function test_allow_wrong_content_type_only_for_dropbox(): void {
		$obj = \ExternalFilesInMediaLibrary\Services\DropBox::get_instance();
		$this->assertFalse( $obj->allow_wrong_content_type( true, 'https://uc123.dl.dropboxusercontent.com/example.pdf' ) );
		$this->assertTrue( $obj->allow_wrong_content_type( true, 'https://example.com/example.pdf?dropboxusercontent.com' ) );
		$this->assertTrue( $obj->allow_wrong_content_type( true, 'https://dropboxusercontent.com.example.com/example.pdf' ) );
	}
}
