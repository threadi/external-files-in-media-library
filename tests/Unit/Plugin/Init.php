<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Init.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Init.
 */
class Init extends externalFilesTests {

	/**
	 * Test if the returning variable is an array and contains "efml_15minutely".
	 *
	 * @return void
	 */
	public function test_add_cron_intervals(): void {
		$cron_intervalls = \ExternalFilesInMediaLibrary\Plugin\Init::get_instance()->add_cron_intervals( array() );
		$this->assertIsArray( $cron_intervalls );
		$this->assertArrayHasKey( 'efml_15minutely', $cron_intervalls );
	}
}
