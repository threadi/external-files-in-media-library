<?php
/**
 * Tests a scenario.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Scenarios;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test this scenario.
 */
class ImportUnknownFileTypeViaHttp extends externalFilesTests {

	/**
	 * Test to successfully import an unknown file type via HTTP.
	 *
	 * @return void
	 */
	public function test_import_unknown_file_type_via_http(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'zip', 'http' ) );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}

	/**
	 * Test for a failed import of an unknown file type via HTTP.
	 *
	 * @return void
	 */
	public function test_import_faulty_unknown_file_type_via_http(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_faulty_test_file( 'zip', 'http' ) );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}
}
