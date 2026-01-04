<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Forms.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Forms.
 */
class Forms extends externalFilesTests {
	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_add_sound(): void {
		$classes = \ExternalFilesInMediaLibrary\ExternalFiles\Forms::get_instance()->add_sound( '' );
		$this->assertIsString( $classes );
		$this->assertStringContainsString( 'efml-play-found', $classes );
	}
}
