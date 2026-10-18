<?php
/**
 * File to give tests access to the FTP, FTPS, SFTP and HTTP test servers.
 *
 * The servers are started with "bin/test-servers.sh start" (see there).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Object to get the data of the test servers.
 */
class TestServers {
	/**
	 * List of directories created during the actual test.
	 *
	 * @var array<int,string>
	 */
	private static array $directories = array();

	/**
	 * Return the directory of the test servers.
	 *
	 * Must match the directory in bin/test-servers.sh.
	 *
	 * @return string
	 */
	public static function get_directory(): string {
		// use the directory from the environment, if set.
		$directory = getenv( 'EFML_TEST_SERVERS_DIR' );
		if ( is_string( $directory ) && ! empty( $directory ) ) {
			return $directory;
		}

		// get the name of the actual user like "id -un".
		$user = getenv( 'USER' );
		if ( function_exists( 'posix_geteuid' ) && function_exists( 'posix_getpwuid' ) ) {
			$user_data = posix_getpwuid( posix_geteuid() );
			if ( is_array( $user_data ) ) {
				$user = $user_data['name'];
			}
		}

		// get the temp directory like "${TMPDIR:-/tmp}".
		$tmp_dir = getenv( 'TMPDIR' );
		if ( ! is_string( $tmp_dir ) || empty( $tmp_dir ) ) {
			$tmp_dir = '/tmp';
		}

		return untrailingslashit( $tmp_dir ) . '/efml-test-servers-' . $user;
	}

	/**
	 * Return the data of the given server or skip the test, if it is not available.
	 *
	 * If EFML_REQUIRE_TEST_SERVERS is set, the test fails instead (used in GitHub Actions).
	 *
	 * @param string   $name The name of the server: ftp, ftp_nat, ftps, ftps_noreuse, sftp or http.
	 * @param TestCase $test The actual test.
	 *
	 * @return array<string,mixed>
	 */
	public static function get( string $name, TestCase $test ): array {
		// check the necessary PHP extension.
		$extension = 'sftp' === $name ? 'ssh2' : 'ftp';
		if ( ! extension_loaded( $extension ) ) {
			self::not_available( $test, 'PHP extension "' . $extension . '" is missing' . ( 'ssh2' === $extension ? ' (Debian: apt install php-ssh2)' : '' ) . '.' );
		}

		// get the list of servers.
		$file = self::get_directory() . '/servers.json';
		if ( ! file_exists( $file ) ) {
			self::not_available( $test, 'Test servers are not running (start them with "bin/test-servers.sh start").' );
		}
		$servers = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		// bail if the scripts of the servers have been changed after their start: the running servers are outdated.
		foreach ( (array) glob( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Servers/*.py' ) as $script ) {
			if ( filemtime( (string) $script ) > filemtime( $file ) ) {
				self::not_available( $test, 'Test servers are outdated, as ' . basename( (string) $script ) . ' has been changed after their start (restart them with "bin/test-servers.sh start").' );
			}
		}

		// bail if this server is not available.
		if ( ! is_array( $servers ) || empty( $servers[ $name ] ) ) {
			self::not_available( $test, 'Test server "' . $name . '" is unknown.' );
		}
		$server = $servers[ $name ];
		if ( empty( $server['available'] ) ) {
			self::not_available( $test, 'Test server "' . $name . '" is not available: ' . $server['reason'] );
		}

		// bail if the server is not reachable (e.g. stopped servers with an old servers.json).
		$connection = @fsockopen( $server['host'], $server['port'], $error_code, $error_message, 2 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen
		if ( ! $connection ) {
			self::not_available( $test, 'Test server "' . $name . '" is not reachable on port ' . $server['port'] . ' (start the servers with "bin/test-servers.sh start").' );
		}
		fclose( $connection ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $server;
	}

	/**
	 * Return whether the given URL points to one of the running test servers.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	public static function is_test_server_url( string $url ): bool {
		// bail if the URL is not on the local host.
		if ( '127.0.0.1' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return false;
		}

		// get the list of servers.
		$file = self::get_directory() . '/servers.json';
		if ( ! file_exists( $file ) ) {
			return false;
		}
		$servers = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $servers ) ) {
			return false;
		}

		// check the port.
		$port = absint( wp_parse_url( $url, PHP_URL_PORT ) );
		foreach ( $servers as $server ) {
			if ( ! empty( $server['available'] ) && $port === $server['port'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Allow requests of WordPress to the test servers.
	 *
	 * WordPress rejects requests via wp_safe_remote_*() to local addresses and ports other than 80, 443 and 8080.
	 * This allows them only for the test servers, which simulate external servers.
	 *
	 * @return void
	 */
	public static function allow_requests(): void {
		add_filter(
			'http_request_host_is_external',
			static function ( bool $external, string $host, string $url ): bool {
				return $external || self::is_test_server_url( $url );
			},
			10,
			3
		);
		add_filter(
			'http_allowed_safe_ports',
			static function ( array $ports, string $host, string $url ): array {
				if ( self::is_test_server_url( $url ) ) {
					$ports[] = absint( wp_parse_url( $url, PHP_URL_PORT ) );
				}
				return $ports;
			},
			10,
			3
		);
	}

	/**
	 * Return the URL for a path on the given server.
	 *
	 * @param array<string,mixed> $server The server data.
	 * @param string              $path The path.
	 *
	 * @return string
	 */
	public static function get_url( array $server, string $path = '/' ): string {
		return $server['scheme'] . '://' . $server['host'] . ':' . $server['port'] . '/' . ltrim( $path, '/' );
	}

	/**
	 * Return the fields with the credentials of the given server.
	 *
	 * @param array<string,mixed> $server The server data.
	 * @param string              $password The password to use, if it should differ.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields( array $server, string $password = '' ): array {
		return array(
			'server'   => array(
				'name'       => 'server',
				'value'      => self::get_url( $server ),
				'credential' => true,
			),
			'login'    => array(
				'name'       => 'login',
				'value'      => $server['login'],
				'credential' => true,
			),
			'password' => array(
				'name'       => 'password',
				'value'      => empty( $password ) ? $server['password'] : $password,
				'credential' => true,
			),
		);
	}

	/**
	 * Create a directory with copies of the test files on the server and return its path on the server.
	 *
	 * Use it for tests which change or list files, so other tests are not affected.
	 * The directory is deleted after the test (see cleanup()).
	 *
	 * @param array<string,mixed> $server The server data.
	 * @param bool                $with_subdirectories True to copy the subdirectories of the test files too.
	 *
	 * @return string
	 */
	public static function create_directory( array $server, bool $with_subdirectories = false ): string {
		$name = 'test-' . wp_generate_password( 8, false, false );
		$path = trailingslashit( $server['root'] ) . $name;
		self::copy_directory( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp', $path, $with_subdirectories );

		// remember it for the cleanup after the test.
		self::$directories[] = $path;

		return '/' . $name . '/';
	}

	/**
	 * Copy the files of a directory, optionally with its subdirectories.
	 *
	 * @param string $source The source directory.
	 * @param string $target The target directory, which is created.
	 * @param bool   $recursive True to copy the subdirectories too.
	 *
	 * @return void
	 */
	private static function copy_directory( string $source, string $target, bool $recursive ): void {
		mkdir( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		// allow the server to change files, also if the tests run as another user than the servers.
		chmod( $target, 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod

		foreach ( (array) scandir( $source ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( is_dir( $source . '/' . $name ) ) {
				if ( $recursive ) {
					self::copy_directory( $source . '/' . $name, $target . '/' . $name, true );
				}
				continue;
			}
			copy( $source . '/' . $name, $target . '/' . $name );
		}
	}

	/**
	 * Delete the directories, which have been created with create_directory().
	 *
	 * Called after each test, so the directories of the servers do not grow with every run.
	 * Files, which the servers created as another user, may not be deletable: they are removed
	 * with the next start of the servers.
	 *
	 * @return void
	 */
	public static function cleanup(): void {
		foreach ( self::$directories as $path ) {
			self::delete_directory( $path );
		}
		self::$directories = array();
	}

	/**
	 * Delete a directory with its content.
	 *
	 * @param string $path The directory.
	 *
	 * @return void
	 */
	private static function delete_directory( string $path ): void {
		if ( ! is_dir( $path ) || is_link( $path ) ) {
			return;
		}
		foreach ( (array) scandir( $path ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( is_dir( $path . '/' . $name ) && ! is_link( $path . '/' . $name ) ) {
				self::delete_directory( $path . '/' . $name );
				continue;
			}
			@unlink( $path . '/' . $name ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Skip or fail the test, as the server is not available.
	 *
	 * @param TestCase $test The actual test.
	 * @param string   $reason The reason.
	 *
	 * @return never
	 */
	private static function not_available( TestCase $test, string $reason ): never {
		if ( getenv( 'EFML_REQUIRE_TEST_SERVERS' ) ) {
			$test::fail( $reason );
		}
		$test::markTestSkipped( $reason );
	}
}
