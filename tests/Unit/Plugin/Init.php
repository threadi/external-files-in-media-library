<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Init.
 *
 * @package external-files-in-media-library
 */

namespace Unit\Plugin;

use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Init.
 */
class Init extends WP_UnitTestCase {

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
