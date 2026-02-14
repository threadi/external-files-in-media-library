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
	 * Test if the returning variable is boolean.
	 *
	 * @return void
	 */
	public function test_get_protocol_object_for_empty_url(): void {
		$protocol_handler_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocol_object_for_url( '' );
		$this->assertIsBool( $protocol_handler_obj );
		$this->assertFalse( $protocol_handler_obj );
	}

	/**
	 * Test if the returning variable is boolean.
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

	/**
	 * Return the list of file types as list of objects for a PDF as test file.
	 *
	 * @return iterable
	 */
	public function get_protocols(): iterable {
		$test_file = self::get_test_file( 'pdf', 'http' );
		$protocol_names = \ExternalFilesInMediaLibrary\ExternalFiles\Protocols::get_instance()->get_protocols();
		foreach ( $protocol_names as $protocol_name ) {
			// bail if name is not an existing class.
			if ( ! class_exists( $protocol_name ) ) {
				continue;
			}

			// get the object.
			$obj = new $protocol_name( $test_file );

			// return it.
			yield array( $obj );
		}
	}

	/**
	 * Test a single extension given by the data provider.
	 *
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $obj Object of the extension.
	 *
	 * @dataProvider get_protocols
	 * @return void
	 */
	public function test_protocol( \ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base $obj ): void {
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base', $obj );
		$this->assertIsString( $obj->get_name() );
		$this->assertIsString( $obj->get_title() );
		$this->assertIsBool( $obj->is_url_compatible() );
		$this->assertIsString( $obj->get_url() );
		$this->assertIsBool( $obj->check_url( $obj->get_url() ) );
		$this->assertIsBool( $obj->check_availability( $obj->get_url() ) );
		$this->assertIsArray( $obj->get_url_infos() );
		$this->assertIsBool( $obj->should_be_saved_local() );
		$this->assertIsArray( $obj->get_fields() );
		$this->assertIsBool( $obj->check_for_duplicate( $obj->get_url()) );
		$this->assertIsBool( $obj->can_change_hosting() );
		$this->assertIsString( $obj->get_link() );
		$this->assertIsArray( $obj->get_url_info( $obj->get_url() ) );
		$this->assertIsBool( $obj->is_url_reachable() );
	}
}
