<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\File_Types.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\File_Types.
 */
class File_Types extends externalFilesTests {

	/**
	 * Test if value is an array and is not empty.
	 *
	 * @return void
	 */
	public function test_get_file_types_as_objects(): void {
		$file_types_as_objects = \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance()->get_file_types_as_objects();
		$this->assertIsArray( $file_types_as_objects );
		$this->assertNotEmpty( $file_types_as_objects );
	}

	/**
	 * Return the list of file types as list of objects for an image as test file.
	 *
	 * @return iterable
	 */
	public function get_file_types_for_image(): iterable {
		$file_types_as_objects = \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance()->get_file_types_as_objects( self::get_test_file( 'jpg', 'http' ) );
		foreach ( $file_types_as_objects as $file_type_as_objects ) {
			yield array( $file_type_as_objects );
		}
	}

	/**
	 * Test a single extension given by the data provider.
	 *
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base $obj Object of the extension.
	 *
	 * @dataProvider get_file_types_for_image
	 * @return void
	 */
	public function test_img_on_every_file_type( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base $obj ): void {
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base', $obj );
		$this->assertIsString( $obj->get_name() );
		$this->assertIsString( $obj->get_title() );
		$this->assertIsBool( $obj->is_proxy_default_enabled() );
		$this->assertIsBool( $obj->is_local() );
		$this->assertIsBool( $obj->is_cache_expired() );
		$this->assertIsBool( $obj->is_file_compatible() );
		$this->assertIsBool( $obj->has_thumbs() );
	}

	/**
	 * Return the list of file types as list of objects for a PDF as test file.
	 *
	 * @return iterable
	 */
	public function get_file_types_for_pdf(): iterable {
		$file_types_as_objects = \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance()->get_file_types_as_objects( self::get_test_file( 'pdf', 'http' ) );
		foreach ( $file_types_as_objects as $file_type_as_objects ) {
			yield array( $file_type_as_objects );
		}
	}

	/**
	 * Test a single extension given by the data provider.
	 *
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base $obj Object of the extension.
	 *
	 * @dataProvider get_file_types_for_pdf
	 * @return void
	 */
	public function test_pdf_on_every_file_type( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base $obj ): void {
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base', $obj );
		$this->assertIsString( $obj->get_name() );
		$this->assertIsString( $obj->get_title() );
		$this->assertIsBool( $obj->is_proxy_default_enabled() );
		$this->assertIsBool( $obj->is_local() );
		$this->assertIsBool( $obj->is_cache_expired() );
		$this->assertIsBool( $obj->is_file_compatible() );
		$this->assertIsBool( $obj->has_thumbs() );
	}
}
