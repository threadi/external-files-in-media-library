<?php
/**
 * Tests with a real HTTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the HTTP support with a real HTTP server (see tests/Servers/http_server.py).
 */
class Http extends externalFilesTests {
	/**
	 * Return the data of the test server or skip the test.
	 *
	 * @return array<string,mixed>
	 */
	private function get_server(): array {
		return TestServers::get( 'http', $this );
	}

	/**
	 * Return the URL for the given path on the test server.
	 *
	 * @param string $path The path.
	 *
	 * @return string
	 */
	private function get_url( string $path ): string {
		return TestServers::get_url( $this->get_server(), $path );
	}

	/**
	 * Import the given URL.
	 *
	 * @param string $url The URL.
	 * @param bool   $with_credentials True to use the credentials of the test server.
	 * @param string $password The password to use, if it should differ.
	 *
	 * @return bool
	 */
	private function import( string $url, bool $with_credentials = false, string $password = '' ): bool {
		$import = Import::get_instance();
		$import->set_fields( $with_credentials ? TestServers::get_fields( $this->get_server(), $password ) : array() );
		$result = @$import->add_url( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$import->set_fields( array() );
		return $result;
	}

	/**
	 * Test the import of single files with allowed file types.
	 *
	 * @return void
	 */
	public function test_import_single_files(): void {
		foreach ( array( 'example-red.jpg' => 'image/jpeg', 'example-blue.png' => 'image/png' ) as $filename => $mime_type ) {
			$url = $this->get_url( '/files/' . $filename );
			$this->assertTrue( $this->import( $url ), $filename );

			$external_file_obj = Files::get_instance()->get_file_by_url( $url );
			$this->assertInstanceOf( File::class, $external_file_obj, $filename );
			$this->assertSame( $mime_type, get_post_mime_type( $external_file_obj->get_id() ) );
			$this->assertSame( $filename, get_the_title( $external_file_obj->get_id() ) );
		}
	}

	/**
	 * Test that files with a not allowed file type are not imported.
	 *
	 * @return void
	 */
	public function test_import_not_allowed_file_type(): void {
		$this->assertFalse( $this->import( $this->get_url( '/files/example.txt' ) ) );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $this->get_url( '/files/example.txt' ) ) );
	}

	/**
	 * Test that files are not imported if the server answers with an error.
	 *
	 * @return void
	 */
	public function test_import_with_http_errors(): void {
		$this->assertFalse( $this->import( $this->get_url( '/files/does-not-exist.jpg' ) ) );
		$this->assertFalse( $this->import( $this->get_url( '/status/500/example.jpg' ) ) );
		$this->assertFalse( $this->import( $this->get_url( '/status/403/example.jpg' ) ) );
	}

	/**
	 * Test that files without Content-Type are not imported.
	 *
	 * @return void
	 */
	public function test_import_without_content_type(): void {
		$this->assertFalse( $this->import( $this->get_url( '/no-content-type/example-red.jpg' ) ) );
	}

	/**
	 * Test that redirects are not followed: only the status 200 is accepted for a file.
	 *
	 * This is intended: files in the media library (e.g. images) are used directly via their URL,
	 * which should not be a redirect.
	 *
	 * @return void
	 */
	public function test_import_does_not_follow_redirects(): void {
		$this->assertFalse( $this->import( $this->get_url( '/redirect/example-red.jpg' ) ) );
	}

	/**
	 * Test that the file name from the Content-Disposition header is used as title.
	 *
	 * @return void
	 */
	public function test_import_uses_file_name_from_content_disposition(): void {
		$url = $this->get_url( '/disposition/example-green.jpg' );
		$this->assertTrue( $this->import( $url ) );

		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertSame( 'renamed-example-green.jpg', get_the_title( $external_file_obj->get_id() ) );
	}

	/**
	 * Test the import of a file, which is protected with HTTP Basic authentication.
	 *
	 * @return void
	 */
	public function test_import_protected_file(): void {
		$url = $this->get_url( '/protected/example-blue.png' );

		// without or with wrong credentials the file is not imported.
		$this->assertFalse( $this->import( $url ) );
		$this->assertFalse( $this->import( $url, true, 'wrong-password' ) );

		// with credentials it is imported and saved locally, as it is not public.
		$this->assertTrue( $this->import( $url, true ) );
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertTrue( $external_file_obj->is_locally_saved() );
		$this->assertFileEquals( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-blue.png', get_attached_file( $external_file_obj->get_id() ) );
	}

	/**
	 * Test that the import fails if the file could not be downloaded, although its header is available.
	 *
	 * @return void
	 */
	public function test_import_fails_if_download_fails(): void {
		// with credentials the file would be saved locally, so it must be downloaded.
		$url = $this->get_url( '/get-fails/example-red.jpg' );
		$this->assertFalse( $this->import( $url, true ) );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $url ) );
	}

	/**
	 * Test the import of a directory listing: all linked files with an allowed file type are imported.
	 *
	 * @return void
	 */
	public function test_import_directory(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$this->assertTrue( $this->import( $this->get_url( '/files' . $directory ) ) );

		// the files are imported with the port of the server in their URL.
		foreach ( array( 'example-red.jpg', 'example-green.jpg', 'example-blue.png' ) as $filename ) {
			$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $this->get_url( '/files' . $directory . $filename ) ), $filename );
		}

		// files with not allowed file types are not imported.
		$this->assertFalse( Files::get_instance()->get_file_by_url( $this->get_url( '/files' . $directory . 'example.txt' ) ) );
	}

	/**
	 * Test the availability check of an imported file.
	 *
	 * @return void
	 */
	public function test_availability(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$url       = $this->get_url( '/files' . $directory . 'example-red.jpg' );

		$http = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( $url );
		$this->assertTrue( @$http->check_availability( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// after deleting the file on the server it is not available anymore.
		unlink( $server['root'] . $directory . 'example-red.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		$http = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( $url );
		$this->assertFalse( @$http->check_availability( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test the synchronization of a directory listing: new files are imported, deleted files removed.
	 *
	 * @return void
	 */
	public function test_synchronization(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$url       = $this->get_url( '/files' . $directory );

		// remove synchronized files, which are no longer on the server.
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// create the external source and synchronize it.
		$term_id = Taxonomy::get_instance()->add( 'http', $url, array() );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$red = Files::get_instance()->get_file_by_url( $url . 'example-red.jpg' );
		$this->assertInstanceOf( File::class, $red );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $url . 'example-green.jpg' ) );

		// change the files on the server: delete one, add one.
		unlink( $server['root'] . $directory . 'example-green.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/subdirectory/example-grey.jpg', $server['root'] . $directory . 'example-grey.jpg' );

		// synchronize again.
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the unchanged file is still the same attachment, the deleted file is removed, the new one imported.
		$this->assertSame( $red->get_id(), Files::get_instance()->get_file_by_url( $url . 'example-red.jpg' )->get_id() );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $url . 'example-green.jpg' ) );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $url . 'example-grey.jpg' ) );

		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
	}
}
