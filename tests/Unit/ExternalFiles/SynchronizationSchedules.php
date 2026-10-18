<?php
/**
 * Tests for the schedules of the synchronization.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the schedules of the synchronization: their creation, recreation and check.
 */
class SynchronizationSchedules extends externalFilesTests {
	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( Synchronization::get_instance()->get_sync_terms() as $term_id ) {
			$schedule = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );
			if ( $schedule ) {
				$schedule->delete();
			}
		}
		update_option( 'eml_sync_set_automatic', 0 );
		update_option( 'eml_sync', 0 );
		delete_option( 'efml_recreate_sync_schedules' );
		parent::tear_down();
	}

	/**
	 * Create an external source and return its term ID.
	 *
	 * @param string $name A name for the URL.
	 *
	 * @return int
	 */
	private function create_source( string $name = 'source' ): int {
		$term_id = Taxonomy::get_instance()->add( 'http', 'https://example.com/' . $name . '/', array() );
		$this->assertGreaterThan( 0, $term_id );
		return $term_id;
	}

	/**
	 * Move the event of the schedule of the given external source to the given time.
	 *
	 * @param int $term_id The term ID.
	 * @param int $timestamp The new time.
	 *
	 * @return void
	 */
	private function move_event( int $term_id, int $timestamp ): void {
		$schedule = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );
		$this->assertNotFalse( $schedule );
		$event = $schedule->get_event();
		$this->assertIsObject( $event );
		wp_unschedule_event( $event->timestamp, $schedule->get_name(), $schedule->get_args() );
		wp_schedule_event( $timestamp, $event->schedule, $schedule->get_name(), $schedule->get_args() );
	}

	/**
	 * Test that a new external source gets a schedule, if the automatic synchronization is enabled.
	 *
	 * @return void
	 */
	public function test_new_source_with_automatic_synchronization(): void {
		// disabled: no schedule.
		$term_id = $this->create_source( 'disabled' );
		Synchronization::get_instance()->added_new_directory( $term_id );
		$this->assertFalse( Synchronization::get_instance()->get_schedule_by_term_id( $term_id ) );
		$this->assertNotContains( $term_id, Synchronization::get_instance()->get_sync_terms() );

		// enabled: a schedule with the default interval.
		update_option( 'eml_sync_set_automatic', 1 );
		$term_id = $this->create_source( 'enabled' );
		Synchronization::get_instance()->added_new_directory( $term_id );
		$schedule = Synchronization::get_instance()->get_schedule_by_term_id( $term_id );
		$this->assertNotFalse( $schedule );
		$this->assertSame( 'efml_hourly', get_term_meta( $term_id, 'interval', true ) );
		$this->assertContains( $term_id, Synchronization::get_instance()->get_sync_terms() );
		$this->assertSame( $term_id, $schedule->get_args()['term_id'] );
		$this->assertSame( 'http', $schedule->get_args()['method'] );
	}

	/**
	 * Test the recreation of missing schedules, e.g. after an update.
	 *
	 * @return void
	 */
	public function test_recreate_schedules(): void {
		update_option( 'eml_sync_set_automatic', 1 );
		$first  = $this->create_source( 'first' );
		$second = $this->create_source( 'second' );
		Synchronization::get_instance()->added_new_directory( $first );
		Synchronization::get_instance()->added_new_directory( $second );

		// remove the events, like they could be missing after a deactivation.
		foreach ( array( $first, $second ) as $term_id ) {
			Synchronization::get_instance()->get_schedule_by_term_id( $term_id )->delete();
			$this->assertFalse( Synchronization::get_instance()->get_schedule_by_term_id( $term_id ) );
		}

		// without the marker nothing happens (the activation of the plugin for the tests has set it).
		delete_option( 'efml_recreate_sync_schedules' );
		Synchronization::get_instance()->recreate_schedules();
		$this->assertFalse( Synchronization::get_instance()->get_schedule_by_term_id( $first ) );

		// with the marker, all schedules are created again and the marker is removed.
		update_option( 'efml_recreate_sync_schedules', 1 );
		Synchronization::get_instance()->recreate_schedules();
		foreach ( array( $first, $second ) as $term_id ) {
			$this->assertNotFalse( Synchronization::get_instance()->get_schedule_by_term_id( $term_id ) );
		}
		$this->assertSame( 0, absint( get_option( 'efml_recreate_sync_schedules' ) ) );
	}

	/**
	 * Test the check of the schedules for the site health.
	 *
	 * @return void
	 */
	public function test_check_cron(): void {
		// without synchronized external sources all is fine.
		$this->assertSame( 'good', Synchronization::get_instance()->check_cron()['status'] );

		// a synchronized external source with a future event is fine.
		update_option( 'eml_sync_set_automatic', 1 );
		$term_id = $this->create_source();
		Synchronization::get_instance()->added_new_directory( $term_id );
		$this->move_event( $term_id, time() + HOUR_IN_SECONDS );
		$this->assertSame( 'good', Synchronization::get_instance()->check_cron()['status'] );

		// an event, which should have been run, results in a hint.
		$this->move_event( $term_id, time() - HOUR_IN_SECONDS );
		$result = Synchronization::get_instance()->check_cron();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'was not executed', $result['description'] );
		$this->assertSame( '', $result['actions'] );

		// a missing event results in a hint with an action to recreate it.
		Synchronization::get_instance()->get_schedule_by_term_id( $term_id )->delete();
		$result = Synchronization::get_instance()->check_cron();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'efml_create_sync_cron', $result['actions'] );
	}

	/**
	 * Test the check of the interval setting.
	 *
	 * @return void
	 */
	public function test_sanitize_interval_setting(): void {
		global $wp_settings_errors;
		add_filter( 'sanitize_option_efml_test_interval', array( Synchronization::get_instance(), 'sanitize_interval_setting' ) );
		update_option( 'efml_test_interval', 'hourly' );
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// a known interval and the value to disable the check are allowed.
		$this->assertSame( 'daily', apply_filters( 'sanitize_option_efml_test_interval', 'daily' ) );
		$this->assertSame( 'eml_disable_check', apply_filters( 'sanitize_option_efml_test_interval', 'eml_disable_check' ) );

		// an unknown interval keeps the saved value and an empty value is not allowed, both with an error.
		$this->assertSame( 'hourly', apply_filters( 'sanitize_option_efml_test_interval', 'not-existing' ) );
		$this->assertSame( '', apply_filters( 'sanitize_option_efml_test_interval', '' ) );
		$this->assertCount( 2, get_settings_errors( 'efml_test_interval' ) );

		remove_filter( 'sanitize_option_efml_test_interval', array( Synchronization::get_instance(), 'sanitize_interval_setting' ) );
		delete_option( 'efml_test_interval' );
	}

	/**
	 * Test whether the synchronization is in use.
	 *
	 * @return void
	 */
	public function test_is_in_use(): void {
		update_option( 'eml_sync', 1 );
		$this->assertFalse( Synchronization::get_instance()->is_in_use() );

		// with a synchronized external source.
		update_option( 'eml_sync_set_automatic', 1 );
		Synchronization::get_instance()->added_new_directory( $this->create_source() );
		$this->assertTrue( Synchronization::get_instance()->is_in_use() );

		// not if the synchronization is disabled.
		update_option( 'eml_sync', 0 );
		$this->assertFalse( Synchronization::get_instance()->is_in_use() );
	}
}
