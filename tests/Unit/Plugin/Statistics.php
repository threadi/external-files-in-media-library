<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Statistics.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Statistics.
 */
class Statistics extends externalFilesTests {

	/**
	 * Test if the list of sizes per service is cached, also if no external hosted file exist,
	 * to prevent the calculation on every request.
	 *
	 * @return void
	 */
	public function test_sizes_per_service_are_cached_if_empty(): void {
		$statistics_obj = \ExternalFilesInMediaLibrary\Plugin\Statistics::get_instance();

		// remove the cache.
		$statistics_obj->clear_sizes_per_service_cache();
		$this->assertFalse( get_transient( \ExternalFilesInMediaLibrary\Plugin\Statistics::CACHE_KEY ) );

		// get the list.
		$html = $statistics_obj->get_sizes_per_service_as_html();
		$this->assertIsString( $html );
		$this->assertNotEmpty( $html );

		// the result must be in the cache now.
		$this->assertEquals( $html, get_transient( \ExternalFilesInMediaLibrary\Plugin\Statistics::CACHE_KEY ) );
	}

	/**
	 * Test if the cache is cleared if a new external file has been added.
	 *
	 * @return void
	 */
	public function test_cache_is_cleared_after_adding_a_file(): void {
		// fill the cache.
		\ExternalFilesInMediaLibrary\Plugin\Statistics::get_instance()->get_sizes_per_service_as_html();
		$this->assertNotFalse( get_transient( \ExternalFilesInMediaLibrary\Plugin\Statistics::CACHE_KEY ) );

		// add a file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );

		// the cache must be empty now.
		$this->assertFalse( get_transient( \ExternalFilesInMediaLibrary\Plugin\Statistics::CACHE_KEY ) );
	}
}
