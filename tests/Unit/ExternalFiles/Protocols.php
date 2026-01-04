<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Protocols.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Protocols.
 */
class Protocols extends externalFilesTests {
	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_empty_url(): void {
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( '' );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );
	}

	/**
	 * Test if the returning variable is a boolean.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_invalid_url(): void {
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( 'example.com' );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );
	}

	/**
	 * Test if the returning variable is a boolean
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_faulty_url(): void {
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( self::get_faulty_test_file( 'pdf', 'http' ) );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );
	}

	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_valid_url(): void {
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsNotBool( $protocol_handler_obj );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http::class, $protocol_handler_obj  );
	}

	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_external_file(): void {
		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );

		// get the external file object for our test URL.
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );

		// get the protocol for this object.
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_external_file( $external_file_obj );
		$this->assertIsNotBool( $protocol_handler_obj );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http::class, $protocol_handler_obj  );
	}
}
