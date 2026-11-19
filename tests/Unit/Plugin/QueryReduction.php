<?php
/**
 * Tests for the reduction of queries in each request.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations;
use ExternalFilesInMediaLibrary\Plugin\Background_Events;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the reduction of queries in each request.
 *
 * WordPress reads each option, which is not saved or not autoloaded, with an own query in every request.
 */
class QueryReduction extends externalFilesTests {

	/**
	 * Return the autoload value of the given option in the database or null, if it is not saved.
	 *
	 * @param string $option The name of the option.
	 *
	 * @return string|null
	 */
	private function get_autoload( string $option ): ?string {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT `autoload` FROM %i WHERE `option_name` = %s', $wpdb->options, $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_null( $value ) ? null : (string) $value;
	}

	/**
	 * Remove the given settings from the list of checked settings.
	 *
	 * @param array<int,string> $names The names of the settings.
	 *
	 * @return void
	 */
	private function forget_checked_settings( array $names ): void {
		$checked = get_option( Settings::DEFAULTS_OPTION, array() );
		$checked = is_array( $checked ) ? $checked : array();
		foreach ( $names as $name ) {
			unset( $checked[ $name ] );
		}
		update_option( Settings::DEFAULTS_OPTION, $checked, true );
	}

	/**
	 * Test that the default values of settings, which are not saved, are saved as autoloaded options.
	 *
	 * @return void
	 */
	public function test_save_missing_defaults(): void {
		// remove a setting with a default value and change another one.
		delete_option( 'eml_import_min_size' );
		update_option( 'eml_import_max_size', 123 );
		$this->forget_checked_settings( array( 'eml_import_min_size', 'eml_import_max_size' ) );
		$this->assertNull( $this->get_autoload( 'eml_import_min_size' ) );

		// save the missing defaults.
		Settings::get_instance()->save_missing_defaults();

		// the missing setting is saved as autoloaded option with its default value.
		$this->assertContains( $this->get_autoload( 'eml_import_min_size' ), array( 'on', 'yes' ) );
		$this->assertEquals( 0, absint( get_option( 'eml_import_min_size' ) ) );

		// the changed setting keeps its value.
		$this->assertEquals( 123, absint( get_option( 'eml_import_max_size' ) ) );

		// both are marked as checked.
		$checked = get_option( Settings::DEFAULTS_OPTION );
		$this->assertIsArray( $checked );
		$this->assertArrayHasKey( 'eml_import_min_size', $checked );
		$this->assertArrayHasKey( 'eml_import_max_size', $checked );

		// a checked setting is not saved again (e.g. after a reset of its value).
		delete_option( 'eml_import_min_size' );
		Settings::get_instance()->save_missing_defaults();
		$this->assertNull( $this->get_autoload( 'eml_import_min_size' ) );
	}

	/**
	 * Test that the check for the settings request is false outside the settings and could be filtered.
	 *
	 * @return void
	 */
	public function test_is_settings_request(): void {
		// not in the frontend.
		$this->assertFalse( Settings::get_instance()->is_settings_request() );

		// saving the settings via options.php.
		$pagenow = $GLOBALS['pagenow'] ?? '';
		set_current_screen( 'options' );
		$GLOBALS['pagenow'] = 'options.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertTrue( Settings::get_instance()->is_settings_request() );
		$GLOBALS['pagenow'] = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertFalse( Settings::get_instance()->is_settings_request() );
		$GLOBALS['pagenow'] = $pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		set_current_screen( 'front' );

		// via the filter.
		add_filter( 'efml_is_settings_request', '__return_true' );
		$this->assertTrue( Settings::get_instance()->is_settings_request() );
		remove_filter( 'efml_is_settings_request', '__return_true' );
	}

	/**
	 * Test that options without a setting are saved with their default value, if they are missing.
	 *
	 * @return void
	 */
	public function test_get_autoloaded_option(): void {
		delete_option( 'efml_test_autoloaded' );

		// the default value is returned and saved as autoloaded option.
		$this->assertEquals( array(), Helper::get_autoloaded_option( 'efml_test_autoloaded', array() ) );
		$this->assertContains( $this->get_autoload( 'efml_test_autoloaded' ), array( 'on', 'yes' ) );

		// a saved value is returned.
		update_option( 'efml_test_autoloaded', array( 'a' => 1 ) );
		$this->assertEquals( array( 'a' => 1 ), Helper::get_autoloaded_option( 'efml_test_autoloaded', array() ) );
		delete_option( 'efml_test_autoloaded' );

		// a saved option, which is not autoloaded, is changed to autoloaded.
		add_option( 'efml_test_autoloaded', 5, '', false );
		$this->assertContains( $this->get_autoload( 'efml_test_autoloaded' ), array( 'off', 'no' ) );
		$this->assertEquals( 5, Helper::get_autoloaded_option( 'efml_test_autoloaded', 0 ) );
		$this->assertContains( $this->get_autoload( 'efml_test_autoloaded' ), array( 'on', 'yes' ) );
		delete_option( 'efml_test_autoloaded' );

		// a setting, whose default value is set via filter, is saved as well.
		delete_option( 'efml_recreate_sync_schedules' );
		$this->assertEquals( 0, absint( Helper::get_autoloaded_option( 'efml_recreate_sync_schedules', 0 ) ) );
		$this->assertContains( $this->get_autoload( 'efml_recreate_sync_schedules' ), array( 'on', 'yes' ) );
	}

	/**
	 * Test that saved settings, which are not autoloaded, are changed to autoloaded.
	 *
	 * @return void
	 */
	public function test_save_missing_defaults_changes_autoload(): void {
		// save a setting without autoload.
		delete_option( 'eml_import_min_size' );
		add_option( 'eml_import_min_size', 7, '', false );
		$this->forget_checked_settings( array( 'eml_import_min_size' ) );

		// check the settings.
		Settings::get_instance()->save_missing_defaults();

		// it is autoloaded now and keeps its value.
		$this->assertContains( $this->get_autoload( 'eml_import_min_size' ), array( 'on', 'yes' ) );
		$this->assertEquals( 7, absint( get_option( 'eml_import_min_size' ) ) );
	}

	/**
	 * Test that the progress in the admin bar needs no query, if no run has been started since the last check.
	 *
	 * @return void
	 */
	public function test_admin_bar_progress_without_runs(): void {
		global $wpdb;

		// mark the last check without runs after the last start.
		update_option( Import_Runs::STARTED_OPTION, time() - 10, true );
		update_option( Import_Runs::EMPTY_OPTION, time(), true );

		// show the admin bar.
		add_filter( 'show_admin_bar', '__return_true' );
		$this->do_login();
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		$admin_bar = new \WP_Admin_Bar();

		// no query is needed.
		$queries = $wpdb->num_queries;
		Import_Runs::get_instance()->add_progress_to_admin_bar( $admin_bar );
		$this->assertSame( $queries, $wpdb->num_queries );
		$this->assertNull( $admin_bar->get_node( 'efml-import-progress' ) );

		// after a new start, the progress is loaded again.
		update_option( Import_Runs::STARTED_OPTION, time() + 10, true );
		Import_Runs::get_instance()->add_progress_to_admin_bar( $admin_bar );
		$this->assertGreaterThan( $queries, $wpdb->num_queries );
		remove_filter( 'show_admin_bar', '__return_true' );
	}

	/**
	 * Test that saved hints get a marker for "not dismissed", which is autoloaded.
	 *
	 * @return void
	 */
	public function test_dismiss_markers(): void {
		// save a hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_test_marker' );
		$transient_obj->set_message( 'Test' );
		$transient_obj->save();
		$key = Transients::get_instance()->get_slug() . '-dismissed-' . md5( 'efml_test_marker' );
		delete_option( $key );

		// save the markers.
		Helper::save_missing_dismiss_markers();
		$this->assertContains( $this->get_autoload( $key ), array( 'on', 'yes' ) );
		$this->assertFalse( Transients::get_instance()->get_transient_by_name( 'efml_test_marker' )->is_dismissed() );

		// the marker is removed with the hint.
		Helper::delete_hint( 'efml_test_marker' );
		$this->assertNull( $this->get_autoload( $key ) );
	}

	/**
	 * Test that hints are only loaded for the deletion, if they are saved.
	 *
	 * @return void
	 */
	public function test_hints(): void {
		global $wpdb;

		// save a hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_test_hint' );
		$transient_obj->set_message( 'Test' );
		$transient_obj->save();
		$this->assertTrue( Helper::is_hint_saved( 'efml_test_hint' ) );

		// a hint with the same message is unchanged, one with another message not.
		$this->assertTrue( Helper::is_hint_unchanged( 'efml_test_hint', 'Test' ) );
		$this->assertFalse( Helper::is_hint_unchanged( 'efml_test_hint', 'Other' ) );
		$this->assertFalse( Helper::is_hint_unchanged( 'efml_test_hint_missing', 'Test' ) );

		// the deletion of a hint, which is not saved, needs no query.
		$this->assertFalse( Helper::is_hint_saved( 'efml_test_hint_missing' ) );
		$queries = $wpdb->num_queries;
		Helper::delete_hint( 'efml_test_hint_missing' );
		$this->assertSame( $queries, $wpdb->num_queries );

		// a saved hint is deleted.
		Helper::delete_hint( 'efml_test_hint' );
		$this->assertFalse( Helper::is_hint_saved( 'efml_test_hint' ) );
		$this->assertFalse( get_transient( 'efml_test_hint' ) );
	}

	/**
	 * Test that the check for missing events uses an autoloaded option instead of a transient.
	 *
	 * @return void
	 */
	public function test_background_events_check(): void {
		delete_option( Background_Events::CHECK_OPTION );
		set_current_screen( 'dashboard' );
		Background_Events::get_instance()->check();
		set_current_screen( 'front' );

		// the time of the next check is saved as autoloaded option.
		$next = absint( get_option( Background_Events::CHECK_OPTION ) );
		$this->assertGreaterThan( time(), $next );
		$this->assertContains( $this->get_autoload( Background_Events::CHECK_OPTION ), array( 'on', 'yes' ) );
	}

	/**
	 * Test that the option of the running migration stays saved as autoloaded empty option.
	 *
	 * @return void
	 */
	public function test_migration_run_option(): void {
		delete_option( Migrations::RUN_OPTION );
		$this->assertEquals( array(), Migrations::get_instance()->get_run() );
		$this->assertContains( $this->get_autoload( Migrations::RUN_OPTION ), array( 'on', 'yes' ) );

		// a cancelled run leaves the empty option.
		update_option( Migrations::RUN_OPTION, array( 'total' => 1 ), false );
		Migrations::get_instance()->cancel();
		$this->assertEquals( array(), Migrations::get_instance()->get_run() );
		$this->assertContains( $this->get_autoload( Migrations::RUN_OPTION ), array( 'on', 'yes' ) );
	}
}
