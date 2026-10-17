<?php
/**
 * Tests with a real FTPS server, which does not allow the reuse of the TLS session.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the handling of FTPS servers, which do not allow the reuse of the TLS session.
 *
 * PHP requires the reuse for the data connection, so the list of files can not be loaded.
 */
class FtpsWithoutSessionReuse extends Ftp {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftps_noreuse';

	/**
	 * Test the listing: it fails with a hint to the missing TLS session resumption.
	 *
	 * @return void
	 */
	public function test_directory_listing(): void {
		$this->assertSame( array(), $this->get_listing( '/' ) );
		$this->assertStringContainsString( 'does not allow the reuse of the TLS session', $this->get_listing_errors() );
	}

	/**
	 * Test the listing of a subdirectory: it fails like the listing.
	 *
	 * @return void
	 */
	public function test_directory_listing_of_subdirectory(): void {
		$this->assertSame( array(), $this->get_listing( '/subdirectory/' ) );
		$this->assertStringContainsString( 'does not allow the reuse of the TLS session', $this->get_listing_errors() );
	}

	/**
	 * Test the preview: the listing fails like without preview.
	 *
	 * @return void
	 */
	public function test_directory_listing_with_preview(): void {
		$this->assertSame( array(), $this->get_listing( '/', '', true ) );
		$this->assertStringContainsString( 'does not allow the reuse of the TLS session', $this->get_listing_errors() );
	}

	/**
	 * Test the import of a single file: it fails, as the file can not be loaded.
	 *
	 * @return void
	 */
	public function test_import_single_file(): void {
		$this->assertFalse( $this->import( TestServers::get_url( $this->get_server(), '/example-red.jpg' ) ) );
	}

	/**
	 * Test the deletion of an imported file: nothing can be imported from this server.
	 *
	 * @return void
	 */
	public function test_delete_imported_file_does_not_connect_to_server(): void {
		$this->assertFalse( $this->import( TestServers::get_url( $this->get_server(), '/example-red.jpg' ) ) );
	}

	/**
	 * Test the import of a directory: it fails, as the files can not be listed.
	 *
	 * @return void
	 */
	public function test_import_directory(): void {
		$this->assertFalse( $this->import( TestServers::get_url( $this->get_server(), '/subdirectory/' ) ) );
	}

	/**
	 * Test the export: it fails, as no data connection is possible.
	 *
	 * @return void
	 */
	public function test_export_and_delete_exported_file(): void {
		$server        = $this->get_server();
		$attachment_id = self::factory()->attachment->create_upload_object( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg' );
		$this->assertIsInt( $attachment_id );
		$directory = TestServers::create_directory( $server );
		$target    = TestServers::get_url( $server, $directory . 'exported.jpg' );
		$this->assertFalse( @\ExternalFilesInMediaLibrary\Services\Ftp\Export::get_instance()->export_file( $attachment_id, $target, array( 'fields' => TestServers::get_fields( $server ) ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// no incomplete file is left on the server.
		$this->assertFileDoesNotExist( $server['root'] . $directory . 'exported.jpg' );
	}

	/**
	 * Test the synchronization: no files are imported.
	 *
	 * @return void
	 */
	public function test_synchronization(): void {
		$server  = $this->get_server();
		$url     = TestServers::get_url( $server, '/subdirectory/' );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'ftp', $url, TestServers::get_fields( $server ) );
		@\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->sync( $url, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $url . 'example-grey.jpg' ) );
	}
}
