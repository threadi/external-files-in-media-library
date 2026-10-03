<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Templates.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Templates.
 */
class Templates extends externalFilesTests {

	/**
	 * Test if the path of each template we use is absolute and does exist.
	 *
	 * @return void
	 */
	public function test_get_template(): void {
		foreach ( array( 'youtube.php', 'vimeo.php' ) as $template ) {
			$path = \ExternalFilesInMediaLibrary\Plugin\Templates::get_instance()->get_template( $template );
			$this->assertIsString( $path );
			$this->assertTrue( path_is_absolute( $path ), $path );
			$this->assertFileExists( $path );
		}
	}
}
