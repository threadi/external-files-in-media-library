<?php
/**
 * Tests with a real SFTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the SFTP support with a real SFTP server.
 */
class Sftp extends externalFilesTests {
	/**
	 * Return the data of the test server or skip the test.
	 *
	 * @return array<string,mixed>
	 */
	private function get_server(): array {
		return TestServers::get( 'sftp', $this );
	}

	/**
	 * Import the given URL with the credentials of the test server.
	 *
	 * @param string $url The URL.
	 * @param string $password The password, if it should differ.
	 *
	 * @return bool
	 */
	private function import( string $url, string $password = '' ): bool {
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $this->get_server(), $password ) );
		$result = @$import->add_url( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$import->set_fields( array() );
		return $result;
	}

	/**
	 * Test the import of a single file.
	 *
	 * @return void
	 */
	public function test_import_single_file(): void {
		$url = TestServers::get_url( $this->get_server(), '/example-blue.png' );
		$this->assertTrue( $this->import( $url ) );

		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertSame( 'image/png', get_post_mime_type( $external_file_obj->get_id() ) );
	}

	/**
	 * Test the import of a file, which does not exist.
	 *
	 * @return void
	 */
	public function test_import_missing_file(): void {
		$this->assertFalse( $this->import( TestServers::get_url( $this->get_server(), '/does-not-exist.jpg' ) ) );
	}

	/**
	 * Test the import with a wrong password.
	 *
	 * @return void
	 */
	public function test_import_with_wrong_password(): void {
		$this->assertFalse( $this->import( TestServers::get_url( $this->get_server(), '/example-red.jpg' ), 'wrong-password' ) );
	}

	/**
	 * Test the import of a directory: all files with an allowed file type are imported.
	 *
	 * @return void
	 */
	public function test_import_directory(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$this->assertTrue( $this->import( TestServers::get_url( $server, $directory ) ) );

		foreach ( array( 'example-red.jpg', 'example-green.jpg', 'example-blue.png' ) as $filename ) {
			$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( TestServers::get_url( $server, $directory . $filename ) ), $filename );
		}
	}
}
