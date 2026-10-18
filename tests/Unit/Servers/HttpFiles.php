<?php
/**
 * Tests for the handling of external files from a real HTTP server.
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
 * Object to test the switch between local and external hosting and the availability check.
 */
class HttpFiles extends externalFilesTests {
	/**
	 * The data of the used server.
	 *
	 * @var array<string,mixed>
	 */
	private array $server = array();

	/**
	 * The directory on the server.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Import the given file of a new directory on the server as external file.
	 *
	 * @param string $filename The file name.
	 *
	 * @return File
	 */
	private function import( string $filename ): File {
		$this->server    = TestServers::get( 'http', $this );
		$this->directory = TestServers::create_directory( $this->server );
		$url             = TestServers::get_url( $this->server, '/files' . $this->directory . $filename );
		$this->assertTrue( @Import::get_instance()->add_url( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
		return $external_file_obj;
	}

	/**
	 * Return the path of a file on the server.
	 *
	 * @param string $filename The file name.
	 *
	 * @return string
	 */
	private function get_server_path( string $filename ): string {
		return $this->server['root'] . $this->directory . $filename;
	}

	/**
	 * Test the switch of an external file to a local file and back.
	 *
	 * @return void
	 */
	public function test_switch_to_local_and_back(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$attachment_id     = $external_file_obj->get_id();
		$url               = $external_file_obj->get_url( true );

		// switch to local: the file is saved in the uploads directory with its content.
		$this->assertTrue( @$external_file_obj->switch_to_local() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( $external_file_obj->is_locally_saved() );
		$local_file = (string) get_attached_file( $attachment_id, true );
		$this->assertFileEquals( $this->get_server_path( 'example-red.jpg' ), $local_file );
		$this->assertStringStartsWith( wp_get_upload_dir()['baseurl'], (string) wp_get_attachment_url( $attachment_id ) );

		// the external URL is still known.
		$this->assertSame( $url, $external_file_obj->get_url( true ) );

		// switch back to external: the local file is deleted.
		$this->assertTrue( @$external_file_obj->switch_to_external() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
		$this->assertFileDoesNotExist( $local_file );
		$this->assertSame( $url, $external_file_obj->get_url( true ) );
	}

	/**
	 * Test that the switch to local fails without changes, if the file is not available on the server anymore.
	 *
	 * @return void
	 */
	public function test_switch_to_local_with_unavailable_file(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$attachment_id     = $external_file_obj->get_id();
		unlink( $this->get_server_path( 'example-red.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertFalse( @$external_file_obj->switch_to_local() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the file is still the external file.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( $external_file_obj->is_valid() );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
	}

	/**
	 * Test the scheduled availability check: deleted files are marked as unavailable, restored files as available.
	 *
	 * @return void
	 */
	public function test_availability_check(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$attachment_id     = $external_file_obj->get_id();
		$this->assertTrue( $external_file_obj->is_available() );

		// delete the file on the server and run the check like the schedule does.
		$content = (string) file_get_contents( $this->get_server_path( 'example-red.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		unlink( $this->get_server_path( 'example-red.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability::get_instance()->check_files(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( Files::get_instance()->get_file( $attachment_id )->is_available() );

		// restore it.
		file_put_contents( $this->get_server_path( 'example-red.jpg' ), $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability::get_instance()->check_files(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertTrue( Files::get_instance()->get_file( $attachment_id )->is_available() );
	}
}
