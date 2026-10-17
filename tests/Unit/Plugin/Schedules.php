<?php
/**
 * Tests for the schedules of this plugin, based on ExternalFilesInMediaLibrary\Plugin\Schedules_Base.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the schedules of this plugin.
 */
class Schedules extends externalFilesTests {
	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		_set_cron_array( array() );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		_set_cron_array( array() );
		remove_all_filters( 'efml_schedule_interval' );
		remove_all_filters( 'eml_schedule_interval' );
		remove_all_filters( 'eml_current_language' );
		parent::tear_down();
	}

	/**
	 * Return a schedule object for the tests.
	 *
	 * @return \ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files
	 */
	private function get_schedule(): \ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files {
		return new \ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files();
	}

	/**
	 * Return the name of an existing interval, which is not the default interval of the schedule.
	 *
	 * @return string
	 */
	private function get_other_interval(): string {
		$schedule = $this->get_schedule();
		foreach ( array_keys( wp_get_schedules() ) as $interval ) {
			if ( $interval !== $schedule->get_default_interval() ) {
				return $interval;
			}
		}
		$this->fail( 'No other interval found.' );
	}

	/**
	 * Test if the schedule is installed with the given interval.
	 *
	 * @return void
	 */
	public function test_install(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( $schedule->get_default_interval() );

		$this->assertFalse( wp_next_scheduled( $schedule->get_name(), $schedule->get_args() ) );
		$this->assertTrue( $schedule->install() );
		$timestamp = wp_next_scheduled( $schedule->get_name(), $schedule->get_args() );
		$this->assertIsInt( $timestamp );
		$this->assertEquals( $schedule->get_default_interval(), wp_get_schedule( $schedule->get_name(), $schedule->get_args() ) );

		// a second installation with the same interval does not change the event.
		$this->assertTrue( $schedule->install() );
		$this->assertSame( $timestamp, wp_next_scheduled( $schedule->get_name(), $schedule->get_args() ) );
	}

	/**
	 * Test if the schedule is rescheduled if the interval has been changed.
	 *
	 * @return void
	 */
	public function test_install_with_changed_interval(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( $schedule->get_default_interval() );
		$this->assertTrue( $schedule->install() );

		$other_interval = $this->get_other_interval();
		$schedule->set_interval( $other_interval );
		$this->assertTrue( $schedule->install() );
		$this->assertEquals( $other_interval, wp_get_schedule( $schedule->get_name(), $schedule->get_args() ) );
	}

	/**
	 * Test if a not existing interval results in the default interval.
	 *
	 * @return void
	 */
	public function test_install_with_unknown_interval(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( 'not_existing_interval' );
		$this->assertTrue( $schedule->install() );
		$this->assertEquals( $schedule->get_default_interval(), wp_get_schedule( $schedule->get_name(), $schedule->get_args() ) );
	}

	/**
	 * Test if the schedule is removed.
	 *
	 * @return void
	 */
	public function test_delete(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( $schedule->get_default_interval() );
		$schedule->install();
		$schedule->delete();
		$this->assertFalse( wp_next_scheduled( $schedule->get_name(), $schedule->get_args() ) );
	}

	/**
	 * Test if the interval could be changed by the filter.
	 *
	 * @return void
	 */
	public function test_interval_filter(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( 'efml_24hourly' );

		add_filter(
			'efml_schedule_interval',
			static function () {
				return 'efml_filtered';
			}
		);
		$this->assertEquals( 'efml_filtered', $schedule->get_interval() );
	}

	/**
	 * Test if the deprecated filter for the language does not change the interval of schedules.
	 *
	 * @return void
	 */
	public function test_language_filter_does_not_change_interval(): void {
		$schedule = $this->get_schedule();
		$schedule->set_interval( 'efml_24hourly' );

		add_filter(
			'eml_current_language',
			static function () {
				return 'de';
			}
		);
		$this->assertEquals( 'efml_24hourly', $schedule->get_interval() );
	}

	/**
	 * Test if the old filter for the interval is still used (as deprecated filter).
	 *
	 * @return void
	 */
	public function test_deprecated_interval_filter(): void {
		$this->setExpectedDeprecated( 'eml_schedule_interval' );

		$schedule = $this->get_schedule();
		$schedule->set_interval( 'efml_24hourly' );

		add_filter(
			'eml_schedule_interval',
			static function () {
				return 'efml_deprecated';
			}
		);
		$this->assertEquals( 'efml_deprecated', $schedule->get_interval() );
	}
}
