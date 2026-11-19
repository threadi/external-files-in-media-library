<?php
/**
 * Tests for the setup with the goal "Synchronize files from FTP" against the FTP test server.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use ExternalFilesInMediaLibrary\Plugin\Setup;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;
use WP_REST_Request;

/**
 * Object to test the setup with an FTP server.
 */
class FtpSetup extends externalFilesTests {
	/**
	 * The original URI of the request.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// use an administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// mark the requests as requests of the setup.
		$this->request_uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/easy-setup-for-wordpress/v1/fields/' . Setup::NAME;

		// initialize the setup for this request.
		Setup::get_instance()->clear_answers();
		Setup::get_instance()->init_setup();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Setup::get_instance()->clear_answers();
		if ( is_null( $this->request_uri ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		parent::tear_down();
	}

	/**
	 * Set the values for the FTP server.
	 *
	 * @param array<string,mixed> $server    The server data.
	 * @param string              $directory The directory on the server.
	 * @param string              $password  The password to use.
	 *
	 * @return void
	 */
	private function set_ftp_values( array $server, string $directory, string $password ): void {
		$setup_obj = \easySetupForWordPress\Setup::get_instance();
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_GOAL, 'ftp' );
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_PREFIX . 'ftp__server', TestServers::get_url( $server, $directory ) );
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_PREFIX . 'ftp__login', (string) $server['login'] );
		$setup_obj->set_field_value( Setup::NAME, Setup::FIELD_PREFIX . 'ftp__password', Setup::get_instance()->encrypt_password( $password, Setup::FIELD_PREFIX . 'ftp__password', Setup::NAME ) );
	}

	/**
	 * Build the steps again for the actual values and return them, like the setup requests them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_steps(): array {
		// get the request for the fields.
		$request = new WP_REST_Request( 'GET', '/easy-setup-for-wordpress/v1/fields/' . Setup::NAME );
		$request->set_param( 'config_name', Setup::NAME );

		// run the preparation, which checks the access data and builds the steps again.
		@Setup::get_instance()->prepare_check( null, null, $request ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// return the steps.
		return \easySetupForWordPress\Setup::get_instance()->get_fields( $request );
	}

	/**
	 * Test the check of the access data with a wrong and the right password.
	 *
	 * @return void
	 */
	public function test_check(): void {
		$server    = TestServers::get( 'ftp', $this );
		$directory = TestServers::create_directory( $server );

		// a wrong password fails.
		$this->set_ftp_values( $server, $directory, 'wrong' );
		$steps = $this->get_steps();
		$this->assertStringContainsString( 'efml-setup-check-failed', $steps[3]['check']['text'] );

		// the right password works and shows the options for the synchronization.
		$this->set_ftp_values( $server, $directory, (string) $server['password'] );
		$steps = $this->get_steps();
		$this->assertStringContainsString( 'efml-setup-check-success', $steps[3]['check']['text'] );
		$this->assertArrayHasKey( Setup::OPTION_PREFIX . 'sync', $steps[3] );
		$this->assertArrayHasKey( Setup::OPTION_PREFIX . 'sync_interval', $steps[3] );
	}

	/**
	 * Test the process: the external source is saved and synchronized.
	 *
	 * @return void
	 */
	public function test_process(): void {
		$server    = TestServers::get( 'ftp', $this );
		$directory = TestServers::create_directory( $server );

		// set the values and choose the synchronization.
		$this->set_ftp_values( $server, $directory, (string) $server['password'] );
		$this->get_steps();
		\easySetupForWordPress\Setup::get_instance()->set_field_value( Setup::NAME, Setup::OPTION_PREFIX . 'sync', 1 );
		\easySetupForWordPress\Setup::get_instance()->set_field_value( Setup::NAME, Setup::OPTION_PREFIX . 'sync_interval', 'efml_24hourly' );

		// run the process.
		@Setup::get_instance()->run_process( Setup::NAME ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$messages = Setup::get_instance()->get_process_messages();
		foreach ( $messages as $message ) {
			$this->assertTrue( $message['success'], $message['text'] );
		}

		// the external source is saved with the synchronization and its interval.
		$terms = get_terms(
			array(
				'taxonomy'   => \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
				'meta_key'   => 'type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'ftp', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$this->assertIsArray( $terms );
		$this->assertNotEmpty( $terms );
		$term_id = end( $terms )->term_id;
		$this->assertEquals( 1, absint( get_term_meta( $term_id, 'sync', true ) ) );
		$this->assertEquals( 'efml_24hourly', get_term_meta( $term_id, 'interval', true ) );
		$this->assertEquals( 1, absint( get_option( 'eml_sync' ) ) );

		// the files of the directory have been synchronized.
		$this->assertNotEmpty( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files() );
	}
}
