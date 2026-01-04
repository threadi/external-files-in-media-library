<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\WebDav.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\WebDav.
 */
class WebDav extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\WebDav::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}
}
