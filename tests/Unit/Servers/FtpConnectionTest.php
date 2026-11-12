<?php
/**
 * Tests for the complete connection test of FTP with a real FTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the connection test of FTP with a real FTP server.
 */
class FtpConnectionTest extends externalFilesTests {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftp';

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance();
		$service->set_fields( array() );
		$service->set_connection_test_directory( '' );
		parent::tear_down();
	}

	/**
	 * Run the connection test with the given password in a new directory and return the steps.
	 *
	 * @param string $password The password, if it should differ.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function run_test( string $password = '' ): array {
		$server    = TestServers::get( $this->server_name, $this );
		$directory = TestServers::create_directory( $server );

		$service = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance();
		$service->set_fields( TestServers::get_fields( $server, $password ) );
		$service->set_connection_test_directory( TestServers::get_url( $server, $directory ) );

		// PHP reports failed FTP commands as warnings, which are expected in some tests.
		return @$service->run_connection_test(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test the connection test: the test file is uploaded and deleted, the public availability is not necessary.
	 *
	 * @return void
	 */
	public function test_connection_test(): void {
		$steps = $this->run_test();

		$this->assertCount( 4, $steps );
		$this->assertTrue( $steps[0]['success'], $steps[0]['message'] );
		$this->assertTrue( $steps[1]['success'], $steps[1]['message'] );
		$this->assertTrue( $steps[2]['skipped'] );
		$this->assertTrue( $steps[3]['success'], $steps[3]['message'] );

		// the test file has been deleted on the server.
		$server = TestServers::get( $this->server_name, $this );
		$this->assertEmpty( glob( trailingslashit( $server['root'] ) . 'test-*/efml-connection-test-*' ) );
	}

	/**
	 * Test the connection test with a wrong password.
	 *
	 * @return void
	 */
	public function test_connection_test_with_wrong_password(): void {
		$steps = $this->run_test( 'wrong-password' );

		$this->assertFalse( $steps[0]['success'] );
		$this->assertStringContainsString( 'connection to the FTP server failed', $steps[0]['message'] );
		$this->assertTrue( $steps[1]['skipped'] );
	}
}
