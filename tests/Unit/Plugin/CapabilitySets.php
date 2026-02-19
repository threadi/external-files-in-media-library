<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\CapabilitySets.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\CapabilitySets.
 */
class CapabilitySets extends externalFilesTests {

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_capability_sets_as_objects(): void {
		$capability_sets = \ExternalFilesInMediaLibrary\Plugin\CapabilitySets::get_instance()->get_capability_sets_as_objects( array() );
		$this->assertIsArray( $capability_sets );
		$this->assertNotEmpty( $capability_sets );
	}
}
