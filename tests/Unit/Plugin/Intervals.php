<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Intervals.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Intervals.
 */
class Intervals extends externalFilesTests {

	/**
	 * Test if each interval with minutes in its name returns the matching time.
	 *
	 * @return void
	 */
	public function test_minute_intervals_match_their_name(): void {
		$intervals = array(
			15 => \ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly15::get_instance(),
			20 => \ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly20::get_instance(),
			30 => \ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly30::get_instance(),
		);
		foreach ( $intervals as $minutes => $interval_obj ) {
			$this->assertEquals( $minutes * MINUTE_IN_SECONDS, $interval_obj->get_time(), $interval_obj->get_name() );
		}
	}

	/**
	 * Test if no two intervals use the same time, as the time is used to detect the interval.
	 *
	 * @return void
	 */
	public function test_interval_times_are_unique(): void {
		$times = array();
		foreach ( glob( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'app/Plugin/Intervals/*.php' ) as $file ) {
			$class_name = '\ExternalFilesInMediaLibrary\Plugin\Intervals\\' . basename( $file, '.php' );
			$times[]    = $class_name::get_instance()->get_time();
		}
		$this->assertNotEmpty( $times );
		$this->assertEquals( count( $times ), count( array_unique( $times ) ) );
	}
}
