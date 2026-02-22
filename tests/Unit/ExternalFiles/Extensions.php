<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions.
 */
class Extensions extends externalFilesTests {

	/**
	 * Test if value is an array and is not empty.
	 *
	 * @return void
	 */
	public function test_get_extensions_as_objects(): void {
		$extensions_as_objects = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions::get_instance()->get_extensions_as_objects();
		$this->assertIsArray( $extensions_as_objects );
		$this->assertNotEmpty( $extensions_as_objects );
	}

	/**
	 * Return the list of extensions as list of objects.
	 *
	 * @return iterable
	 */
	public function get_extensions(): iterable {
		$extensions_as_objects = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions::get_instance()->get_extensions_as_objects();
		foreach ( $extensions_as_objects as $extensions_as_object ) {
			yield array( $extensions_as_object );
		}
	}

	/**
	 * Test a single extension given by the data provider.
	 *
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base $obj Object of the extension.
	 *
	 * @dataProvider get_extensions
	 * @return void
	 */
	public function test_extension( \ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base $obj ): void {
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base', $obj );
		$this->assertIsString( $obj->get_name() );
		$this->assertIsString( $obj->get_title() );
		$this->assertIsArray( $obj->get_capability_default() );
		$this->assertIsString( $obj->get_capability_description() );
		$this->assertIsArray( $obj->get_types() );
		$this->assertNotEmpty( $obj->get_types() );
	}
}
