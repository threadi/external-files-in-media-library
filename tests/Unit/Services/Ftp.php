<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Ftp.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Ftp.
 */
class Ftp extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$directory = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_directory();
		$this->assertIsString( $directory );
		$this->assertEmpty( $directory );
	}

	/**
	 * Test if values are encrypted and decrypted, also the value "0".
	 *
	 * @return void
	 */
	public function test_encrypt_and_decrypt_value(): void {
		$obj = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance();

		foreach ( array( 'my-password', '0', 'äöü €' ) as $value ) {
			$encrypted = $obj->encrypt_value( $value );
			$this->assertNotEquals( $value, $encrypted );
			$this->assertSame( $value, $obj->decrypt_value( $encrypted ) );
		}

		// empty values.
		$this->assertSame( '', $obj->encrypt_value( '' ) );
		$this->assertSame( '', $obj->encrypt_value( null ) );
		$this->assertSame( '', $obj->decrypt_value( '' ) );
		$this->assertSame( '', $obj->decrypt_value( null ) );
	}
}
