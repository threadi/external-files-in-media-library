<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Protocols.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Protocols.
 */
class Protocols extends WP_UnitTestCase {
	/**
	 * The URL of the file to use for testings.
	 *
	 * @var string
	 */
	private string $url = 'https://plugins.svn.wordpress.org/external-files-in-media-library/assets/example_en.pdf';

	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_url(): void {
		// test 1: check with empty URL.
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( '' );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );

		// test 2: check with invalid URL.
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( 'example.com' );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );

		// test 3: check with our test URL.
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( $this->url );
		$this->assertIsNotBool( $protocol_handler_obj );
		$this->assertInstanceOf( Http::class, $protocol_handler_obj  );
	}

	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_external_file(): void {
		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( $this->url );

		// get the external file object for our test URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $this->url );

		// get the protocol for this object.
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_external_file( $external_file_obj );
		$this->assertIsNotBool( $protocol_handler_obj );
		$this->assertInstanceOf( Http::class, $protocol_handler_obj  );
	}
}
