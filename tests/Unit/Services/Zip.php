<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Zip.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Zip.
 */
class Zip extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\Zip::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$directory = \ExternalFilesInMediaLibrary\Services\Zip::get_instance()->get_directory();
		$this->assertIsString( $directory );
		$this->assertEmpty( $directory );
	}

	/**
	 * Test if the ZIP protocol is only used for HTTP-URLs of files in a ZIP-file.
	 *
	 * @return void
	 */
	public function test_protocol_is_url_compatible(): void {
		$urls = array(
			'https://example.com/example.zip/example.pdf' => true,
			'http://example.com/example.zip/'              => true,
			'https://example.com/example.zip'              => false,
			'https://example.com/example.pdf'              => false,
			'file:///var/www/example.zip/example.pdf'      => false,
			'ftp://example.com/example.zip/example.pdf'    => false,
			''                                             => false,
		);
		foreach ( $urls as $url => $expected ) {
			$protocol_obj = new \ExternalFilesInMediaLibrary\Services\Zip\Protocol( $url );
			$this->assertSame( $expected, $protocol_obj->is_url_compatible(), $url );
		}
	}
}
