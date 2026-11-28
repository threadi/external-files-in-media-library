<?php
/**
 * Tests for the synchronization of subdirectories with a real SFTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

/**
 * Object to test the synchronization of subdirectories via SFTP.
 */
class SftpSubdirectories extends FtpSubdirectories {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'sftp';

	/**
	 * Create the external source with the test files and their subdirectory.
	 *
	 * @return array{url: string, term_id: int, path: string}
	 */
	protected function create_source_with_subdirectories(): array {
		$server    = \ExternalFilesInMediaLibrary\Tests\TestServers::get( $this->server_name, $this );
		$directory = \ExternalFilesInMediaLibrary\Tests\TestServers::create_directory( $server, true );
		$url       = \ExternalFilesInMediaLibrary\Tests\TestServers::get_url( $server, $directory );
		$term_id   = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'sftp', $url, \ExternalFilesInMediaLibrary\Tests\TestServers::get_fields( $server ) );
		$this->assertGreaterThan( 0, $term_id );

		return array(
			'url'     => $url,
			'term_id' => $term_id,
			'path'    => $server['root'] . $directory,
		);
	}
}
