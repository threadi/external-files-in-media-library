<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability.
 */
class Availability extends externalFilesTests {
	/**
	 * Name of the option used in this test.
	 *
	 * @var string
	 */
	private string $option = 'efml_test_interval';

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'sanitize_option_' . $this->option );
		delete_option( $this->option );
		parent::tear_down();
	}

	/**
	 * Run the sanitize function for the given value like WordPress does it.
	 *
	 * @param object $obj The object with the sanitize function.
	 * @param string $value The value.
	 *
	 * @return string
	 */
	private function sanitize( object $obj, string $value ): string {
		update_option( $this->option, 'efml_hourly' );
		add_filter( 'sanitize_option_' . $this->option, array( $obj, 'sanitize_interval_setting' ) );
		return sanitize_option( $this->option, $value );
	}

	/**
	 * Return the objects to test.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_objects(): array {
		return array(
			'availability'    => array( \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability::class ),
			'synchronization' => array( \ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::class ),
		);
	}

	/**
	 * Test if an existing interval is saved.
	 *
	 * @dataProvider provide_objects
	 *
	 * @param string $class_name The class to test.
	 *
	 * @return void
	 */
	public function test_sanitize_existing_interval( string $class_name ): void {
		$intervals = array_keys( wp_get_schedules() );
		$this->assertNotEmpty( $intervals );
		$this->assertEquals( $intervals[0], $this->sanitize( $class_name::get_instance(), $intervals[0] ) );
		$this->assertEquals( 'eml_disable_check', $this->sanitize( $class_name::get_instance(), 'eml_disable_check' ) );
	}

	/**
	 * Test if a not existing interval is not saved.
	 *
	 * @dataProvider provide_objects
	 *
	 * @param string $class_name The class to test.
	 *
	 * @return void
	 */
	public function test_sanitize_not_existing_interval( string $class_name ): void {
		$this->assertEquals( 'efml_hourly', $this->sanitize( $class_name::get_instance(), 'not_existing_interval' ) );
	}
}
