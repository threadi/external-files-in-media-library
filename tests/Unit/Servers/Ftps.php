<?php
/**
 * Tests with a real FTPS server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

/**
 * Object to run all FTP tests with an FTPS server (encrypted control and data connections).
 */
class Ftps extends Ftp {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftps';
}
