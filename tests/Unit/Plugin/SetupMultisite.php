<?php
/**
 * Tests for the goal of the setup to use a central media library in a multisite network.
 *
 * Runs only in a multisite: WP_MULTISITE=1 vendor/bin/phpunit --filter SetupMultisite
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Plugin\Network\Media_Library;
use ExternalFilesInMediaLibrary\Plugin\Network\Membership;
use ExternalFilesInMediaLibrary\Plugin\Network\Move;
use ExternalFilesInMediaLibrary\Plugin\Network\Settings;
use ExternalFilesInMediaLibrary\Services\Multisite;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test the goal of the setup for the central media library.
 */
class SetupMultisite extends externalFilesTests {
	/**
	 * The IDs of the websites, which are created one time for all tests of this class.
	 *
	 * @var array<int,int>
	 */
	private static array $sites = array();

	/**
	 * The original URI of the request.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Create a website one time for all tests of this class (see MultisiteNetwork::wpSetUpBeforeClass()).
	 *
	 * @param \WP_UnitTest_Factory $factory The factory of the test framework.
	 *
	 * @return void
	 */
	public static function wpSetUpBeforeClass( \WP_UnitTest_Factory $factory ): void {
		if ( ! is_multisite() ) {
			return;
		}

		$blog_id        = absint( $factory->blog->create() );
		self::$sites[0] = $blog_id;

		// install the tables of the plugin in the website, like the activation does it.
		switch_to_blog( $blog_id );
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->install();
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue::get_instance()->install();
		\ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs::get_instance()->install();
		restore_current_blog();
	}

	/**
	 * Delete the website of this class with its tables.
	 *
	 * @return void
	 */
	public static function wpTearDownAfterClass(): void {
		$add_plugin_tables = static function ( array $tables ): array {
			global $wpdb;
			foreach ( array( 'eml_logs', 'eml_queue', 'efml_import_runs' ) as $table ) {
				$tables[ $table ] = $wpdb->prefix . $table;
			}
			return $tables;
		};
		add_filter( 'wpmu_drop_tables', $add_plugin_tables );
		foreach ( self::$sites as $blog_id ) {
			if ( get_site( $blog_id ) ) {
				wp_delete_site( $blog_id );
			}
		}
		remove_filter( 'wpmu_drop_tables', $add_plugin_tables );
		self::$sites = array();
	}

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs only in a multisite (WP_MULTISITE=1).' );
		}

		// run as super administrator.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		// the network uses no central media library yet.
		delete_site_option( 'efml_media_library_site_id' );
		delete_site_option( Media_Library::OPTION_GROUPS );
		delete_site_option( Move::OPTION );
		Media_Library::get_instance()->reset_sites();

		// register the hooks of the service like a run via cron does it.
		add_filter( 'wp_doing_cron', '__return_true' );
		Multisite::get_instance()->init();
		remove_filter( 'wp_doing_cron', '__return_true' );

		// set the settings of the plugin in the website, like the activation does it.
		switch_to_blog( self::$sites[0] );
		\ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->save_missing_defaults();
		restore_current_blog();

		// mark the requests as requests of the setup and initialize it.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . \ExternalFilesInMediaLibrary\Plugin\Setup::NAME;
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->clear_answers();
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->init_setup();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( is_multisite() ) {
			\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->clear_answers();

			// the database is reset by the test framework, only the cache must be forgotten.
			delete_site_option( 'efml_media_library_site_id' );
			delete_site_option( 'efml_hide_options' );
			delete_site_option( Media_Library::OPTION_GROUPS );
			Media_Library::get_instance()->reset_sites();

			// restore the URI of the request (only changed, if the test has not been skipped).
			if ( is_null( $this->request_uri ) ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $this->request_uri;
			}
		}
		parent::tear_down();
	}

	/**
	 * Set a value of the setup.
	 *
	 * @param string $field_name The name of the field.
	 * @param mixed  $value      The value.
	 *
	 * @return void
	 */
	private function set_value( string $field_name, mixed $value ): void {
		\easySetupForWordPress\Setup::get_instance()->set_field_value( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME, $field_name, $value );
	}

	/**
	 * Build the steps again for the actual values and return them, like the setup requests them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_steps(): array {
		$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		$request->set_param( 'config_name', \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->prepare_check( null, null, $request );
		return \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
	}

	/**
	 * Test that the goal is offered only to users, who could manage the network, and only once.
	 *
	 * @return void
	 */
	public function test_goal(): void {
		$setup = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance();

		// offered for super administrators, after the other goals.
		$this->assertTrue( $setup->is_network_goal_available() );
		$goals = $setup->get_goals();
		$this->assertArrayHasKey( 'network', $goals );
		$this->assertEquals( 'network', $goals['network']['action'] );
		$this->assertEquals( array( 'sync', 'local', 'ftp', 'offload', 'downloads', 'network' ), array_keys( $goals ) );

		// not for administrators of a single website.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( $setup->is_network_goal_available() );
		$this->assertArrayNotHasKey( 'network', $setup->get_goals() );
	}

	/**
	 * Test the steps of the goal: no service, but the website for the central media library and a confirmation.
	 *
	 * @return void
	 */
	public function test_steps(): void {
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'network' );
		$steps = $this->get_steps();

		// goal, configuration and process.
		$this->assertCount( 3, $steps );
		$this->assertEquals( 'ProgressBar', $steps[3]['runSetup']['type'] );

		// the websites with the main website as default.
		$field = $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CENTRAL ];
		$this->assertEquals( 'SelectControl', $field['type'] );
		$this->assertContains( (string) get_main_site_id(), array_column( $field['options'], 'value' ) );
		$this->assertContains( (string) self::$sites[0], array_column( $field['options'], 'value' ) );
		$this->assertEquals( (string) get_main_site_id(), $field['default'] );

		// the options are disabled by default, the confirmation is required.
		$this->assertEquals( 0, $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_EXISTING ]['default'] );
		$this->assertEquals( 0, $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_HIDE ]['default'] );
		$this->assertTrue( $steps[2][ \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CONFIRM ]['required'] );

		// the hint for the support forum is the last field of each step.
		foreach ( $steps as $fields ) {
			$names = array_keys( $fields );
			$this->assertEquals( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_SUPPORT, end( $names ) );
		}
	}

	/**
	 * Test that nothing is changed without the confirmation.
	 *
	 * @return void
	 */
	public function test_process_without_confirmation(): void {
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'network' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CENTRAL, (string) get_main_site_id() );
		$this->get_steps();

		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->run_process( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );

		$this->assertFalse( Media_Library::get_instance()->is_active() );
		$messages = \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_process_messages();
		$this->assertCount( 1, $messages );
		$this->assertFalse( $messages[0]['success'] );
	}

	/**
	 * Test the process: the chosen website becomes the central media library of all other websites.
	 *
	 * @return void
	 */
	public function test_process(): void {
		$central = get_main_site_id();
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'network' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CENTRAL, (string) $central );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_HIDE, 1 );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CONFIRM, 1 );
		$this->get_steps();

		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->run_process( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );
		foreach ( \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_process_messages() as $message ) {
			$this->assertTrue( $message['success'], $message['text'] );
		}

		// the website is the central media library of the other website, which is connected with it.
		$this->assertTrue( Media_Library::get_instance()->is_central( $central ) );
		$this->assertTrue( Media_Library::get_instance()->is_using_central( self::$sites[0] ) );
		switch_to_blog( self::$sites[0] );
		$this->assertGreaterThan( 0, Membership::get_instance()->get_term_id( $central ) );
		restore_current_blog();
		$this->assertEquals( 1, absint( get_site_option( 'efml_hide_options' ) ) );

		// the values have been removed and the goal is not offered anymore, but the hint on the first step.
		$this->assertEmpty( \easySetupForWordPress\Setup::get_instance()->get_field_values_of( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME ) );
		$this->assertArrayNotHasKey( 'network', \ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->get_goals() );
		$this->assertArrayHasKey( 'network_hint', $this->get_steps()[1] );
		$this->assertStringContainsString( Settings::get_instance()->get_url(), $this->get_steps()[1]['network_hint']['text'] );
	}

	/**
	 * Test that the process does not change existing groups, e.g. if they have been created after the start.
	 *
	 * @return void
	 */
	public function test_process_with_existing_groups(): void {
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_GOAL, 'network' );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CENTRAL, (string) get_main_site_id() );
		$this->set_value( \ExternalFilesInMediaLibrary\Plugin\Setup::FIELD_NETWORK_CONFIRM, 1 );
		$this->get_steps();

		// another administrator creates a group with the other website as central media library meanwhile.
		Settings::get_instance()->apply( self::$sites[0], 0 );
		Media_Library::get_instance()->reset_sites();

		\ExternalFilesInMediaLibrary\Plugin\Setup::get_instance()->run_process( \ExternalFilesInMediaLibrary\Plugin\Setup::NAME );

		// the group is unchanged.
		$this->assertTrue( Media_Library::get_instance()->is_central( self::$sites[0] ) );
		$this->assertFalse( Media_Library::get_instance()->is_central( get_main_site_id() ) );
	}
}
