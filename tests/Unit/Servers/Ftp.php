<?php
/**
 * Tests with a real FTP server.
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
use ExternalFilesInMediaLibrary\Tests\StreamWrapperSpy;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the FTP support with a real FTP server.
 */
class Ftp extends externalFilesTests {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftp';

	/**
	 * Return the data of the test server or skip the test.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_server(): array {
		return TestServers::get( $this->server_name, $this );
	}

	/**
	 * Return the directory listing of the FTP service for the given path.
	 *
	 * @param string $path The path on the server.
	 * @param string $password The password, if it should differ.
	 * @param bool   $preview True to enable the preview.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_listing( string $path, string $password = '', bool $preview = false ): array {
		$server = $this->get_server();

		// get the service with fresh errors and the credentials of the test server.
		$service = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance();
		$errors  = new \ReflectionProperty( $service, 'errors' );
		$errors->setValue( $service, array() );
		$service->set_fields( TestServers::get_fields( $server, $password ) );

		// set the preview.
		\easyDirectoryListingForWordPress\Init::get_instance()->set_preview_state( $preview );

		// PHP reports failed FTP commands as warnings, which are expected in some tests.
		$listing = @$service->get_directory_listing( TestServers::get_url( $server, $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// reset the settings for other tests.
		\easyDirectoryListingForWordPress\Init::get_instance()->set_preview_state( false );
		$service->set_fields( array() );

		return $listing;
	}

	/**
	 * Return the error messages of the FTP service.
	 *
	 * @return string
	 */
	protected function get_listing_errors(): string {
		$messages = array();
		foreach ( \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_errors() as $error ) {
			$messages = array_merge( $messages, $error->get_error_messages() );
		}
		return implode( ' ', $messages );
	}

	/**
	 * Import the given URL with the credentials of the test server.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	protected function import( string $url ): bool {
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $this->get_server() ) );
		$result = @$import->add_url( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$import->set_fields( array() );
		return $result;
	}

	/**
	 * Test the listing of a directory with files and a subdirectory.
	 *
	 * @return void
	 */
	public function test_directory_listing(): void {
		// use an own directory, as the root of the server contains the directories of other tests.
		$directory = TestServers::create_directory( $this->get_server(), true );
		$listing   = $this->get_listing( $directory );
		$this->assertSame( '', $this->get_listing_errors() );

		// all files with an allowed file type are listed, also the broken GIF.
		$titles = array_column( $listing['files'], 'title' );
		sort( $titles );
		$this->assertSame( array( 'example-blue.png', 'example-broken.gif', 'example-green.jpg', 'example-red.jpg' ), array_values( array_intersect( $titles, array( 'example-blue.png', 'example-broken.gif', 'example-green.jpg', 'example-red.jpg' ) ) ) );

		// each test file has a URL with the port of the server and its size.
		foreach ( $listing['files'] as $file ) {
			if ( ! str_starts_with( $file['title'], 'example' ) ) {
				continue;
			}
			$this->assertStringStartsWith( TestServers::get_url( $this->get_server() ), $file['file'] );
			$this->assertGreaterThan( 0, $file['filesize'] );
		}

		// the subdirectory is listed.
		$this->assertArrayHasKey( TestServers::get_url( $this->get_server(), $directory . 'subdirectory/' ), $listing['dirs'] );
	}

	/**
	 * Test the listing of a subdirectory.
	 *
	 * @return void
	 */
	public function test_directory_listing_of_subdirectory(): void {
		$listing = $this->get_listing( '/subdirectory/' );
		$this->assertSame( '', $this->get_listing_errors() );
		$this->assertSame( array( 'example-grey.jpg' ), array_column( $listing['files'], 'title' ) );
		$this->assertEmpty( $listing['dirs'] );
	}

	/**
	 * Test the preview in the listing: images get a thumbnail, the broken GIF not.
	 *
	 * @return void
	 */
	public function test_directory_listing_with_preview(): void {
		$listing = $this->get_listing( TestServers::create_directory( $this->get_server() ), '', true );
		$this->assertSame( '', $this->get_listing_errors() );

		$previews = array_column( $listing['files'], 'preview', 'title' );
		$this->assertStringContainsString( '<img', $previews['example-red.jpg'] );
		$this->assertStringContainsString( '<img', $previews['example-blue.png'] );
		$this->assertSame( '', $previews['example-broken.gif'] );
	}

	/**
	 * Test the listing of a directory, which does not exist.
	 *
	 * @return void
	 */
	public function test_directory_listing_of_missing_directory(): void {
		$this->assertSame( array(), $this->get_listing( '/does-not-exist/' ) );
		$this->assertStringContainsString( 'could not be opened', $this->get_listing_errors() );
	}

	/**
	 * Test the listing with a wrong password.
	 *
	 * @return void
	 */
	public function test_directory_listing_with_wrong_password(): void {
		$this->assertSame( array(), $this->get_listing( '/', 'wrong-password' ) );
		$this->assertNotSame( '', $this->get_listing_errors() );
	}

	/**
	 * Test the import of a single file.
	 *
	 * @return void
	 */
	public function test_import_single_file(): void {
		$url = TestServers::get_url( $this->get_server(), '/example-red.jpg' );
		$this->assertTrue( $this->import( $url ) );

		// the file is in the media library with its URL including the port.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $external_file_obj->get_id() ) );
	}

	/**
	 * Test that deleting an imported file does not connect to the server: nothing has to be deleted there.
	 *
	 * WordPress checks the path of the file with is_dir() and is_file(), which would result in anonymous logins via ftp://.
	 *
	 * @return void
	 */
	public function test_delete_imported_file_does_not_connect_to_server(): void {
		$url = TestServers::get_url( $this->get_server(), TestServers::create_directory( $this->get_server() ) . 'example-red.jpg' );
		$this->assertTrue( $this->import( $url ) );
		$attachment_id = Files::get_instance()->get_file_by_url( $url )->get_id();

		StreamWrapperSpy::start( array( 'ftp', 'ftps' ) );
		$result = wp_delete_attachment( $attachment_id, true );
		StreamWrapperSpy::stop( array( 'ftp', 'ftps' ) );

		$this->assertInstanceOf( \WP_Post::class, $result );
		$this->assertSame( array(), StreamWrapperSpy::$calls );

		// the file on the server still exists.
		$this->assertFileExists( $this->get_server()['root'] . wp_parse_url( $url, PHP_URL_PATH ) );
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

	/**
	 * Test the export of a file to the FTP server and the deletion of the exported file.
	 *
	 * @return void
	 */
	public function test_export_and_delete_exported_file(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );

		// create a local attachment to export.
		$attachment_id = self::factory()->attachment->create_upload_object( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg' );
		$this->assertIsInt( $attachment_id );

		// export it.
		$target      = TestServers::get_url( $server, $directory . 'exported.jpg' );
		$credentials = array( 'fields' => TestServers::get_fields( $server ) );
		$export      = \ExternalFilesInMediaLibrary\Services\Ftp\Export::get_instance();
		$this->assertSame( $target, @$export->export_file( $attachment_id, $target, $credentials ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileEquals( get_attached_file( $attachment_id ), $server['root'] . $directory . 'exported.jpg' );

		// an existing file is not overwritten.
		$this->assertFalse( @$export->export_file( $attachment_id, $target, $credentials ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// delete it.
		$this->assertTrue( @$export->delete_exported_file( $target, $credentials, $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFileDoesNotExist( $server['root'] . $directory . 'exported.jpg' );

		// deleting it again fails.
		$this->assertFalse( @$export->delete_exported_file( $target, $credentials, $attachment_id ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test the synchronization of an external FTP source: new files are imported, deleted files removed.
	 *
	 * @return void
	 */
	public function test_synchronization(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$url       = TestServers::get_url( $server, $directory );

		// remove synchronized files, which are no longer on the server.
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// create the external source.
		$term_id = Taxonomy::get_instance()->add( 'ftp', $url, TestServers::get_fields( $server ) );
		$this->assertGreaterThan( 0, $term_id );

		// first synchronization: the files are imported.
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$red = Files::get_instance()->get_file_by_url( $url . 'example-red.jpg' );
		$this->assertInstanceOf( File::class, $red );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $url . 'example-green.jpg' ) );
		$red_id = $red->get_id();

		// change the files on the server: delete one, add one.
		unlink( $server['root'] . $directory . 'example-green.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/subdirectory/example-grey.jpg', $server['root'] . $directory . 'example-grey.jpg' );

		// second synchronization.
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// the unchanged file is still the same attachment, the deleted file is removed, the new one imported.
		$this->assertSame( $red_id, Files::get_instance()->get_file_by_url( $url . 'example-red.jpg' )->get_id() );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $url . 'example-green.jpg' ) );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $url . 'example-grey.jpg' ) );

		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
	}
}
