<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Protocols;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp.
 */
class Ftp extends externalFilesTests {
	/**
	 * Test if a failed FTP connection does not result in reading the local file with the same path.
	 *
	 * @return void
	 */
	public function test_get_temp_file_does_not_read_local_files(): void {
		// create a local file.
		$local_file = trailingslashit( get_temp_dir() ) . 'efml-secret-' . wp_generate_password( 8, false ) . '.txt';
		file_put_contents( $local_file, 'secret' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		// request it via FTP without credentials and with the local filesystem.
		$url = 'ftp://ftp.example.com' . $local_file;
		$obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Ftp( $url );
		$this->assertFalse( $obj->get_temp_file( $url, \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem() ) );
		$this->assertFalse( $obj->get_temp_file( $url, \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem( 'direct' ) ) );
	}
}
