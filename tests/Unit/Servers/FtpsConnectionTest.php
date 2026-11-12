<?php
/**
 * Tests for the complete connection test of FTPS with a real FTPS server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

/**
 * Object to test the connection test of FTPS with a real FTPS server.
 */
class FtpsConnectionTest extends FtpConnectionTest {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftps';
}
