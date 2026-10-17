<?php
/**
 * Tests with a real FTP server, which returns an unreachable address for the passive mode.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

/**
 * Object to run all FTP tests with a server, which returns an unreachable address for the passive mode,
 * like servers behind NAT often do. The plugin uses the address of the server instead.
 */
class FtpBehindNat extends Ftp {
	/**
	 * The name of the used test server.
	 *
	 * @var string
	 */
	protected string $server_name = 'ftp_nat';
}
