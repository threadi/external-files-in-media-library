<?php
/**
 * Tests for the synchronization of subdirectories with a real FTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SubdirectoriesSyncTrait;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the synchronization of subdirectories via FTP.
 */
class FtpSubdirectories extends externalFilesTests {
	use SubdirectoriesSyncTrait;

	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftp';

	/**
	 * Create the external source with the test files and their subdirectory.
	 *
	 * @return array{url: string, term_id: int, path: string}
	 */
	protected function create_source_with_subdirectories(): array {
		$server    = TestServers::get( $this->server_name, $this );
		$directory = TestServers::create_directory( $server, true );
		$url       = TestServers::get_url( $server, $directory );
		$term_id   = Taxonomy::get_instance()->add( 'ftp', $url, TestServers::get_fields( $server ) );
		$this->assertGreaterThan( 0, $term_id );

		return array(
			'url'     => $url,
			'term_id' => $term_id,
			'path'    => $server['root'] . $directory,
		);
	}

	/**
	 * Files in a subdirectory, which could not be read, are not deleted. An empty subdirectory is no error.
	 *
	 * @return void
	 */
	public function test_files_in_unreadable_subdirectory_are_not_deleted(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );
		$this->add_test_file( $source['path'] . 'other/', 'example-blue.png' );

		$this->sync_source( 'cron', $source );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertTrue( $this->has_file( $source['url'] . 'other/example-blue.png' ) );

		// the subdirectory could not be read by the server anymore, the other one is empty now.
		chmod( $source['path'] . 'subdirectory', 0000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		unlink( $source['path'] . 'other/example-blue.png' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		try {
			$this->sync_source( 'cron', $source );
		} finally {
			chmod( $source['path'] . 'subdirectory', 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}

		// the file in the unreadable subdirectory is kept, the file from the empty subdirectory is deleted.
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'other/example-blue.png' ) );
		$this->assertTrue( $this->has_file( $source['url'] . 'example-red.jpg' ) );
	}

	/**
	 * The directory of a file relative to the external source is returned.
	 *
	 * @return void
	 */
	public function test_relative_directory(): void {
		$protocol_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp( 'ftp://example.com/files' );
		$this->assertSame( '', $protocol_obj->get_relative_directory( 'ftp://example.com/files/a.jpg' ) );
		$this->assertSame( 'sub/deeper', $protocol_obj->get_relative_directory( 'ftp://example.com/files/sub/deeper/a.jpg' ) );
		$this->assertSame( '', $protocol_obj->get_relative_directory( 'ftp://example.com/other/a.jpg' ) );
	}
}
