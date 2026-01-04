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
class ImportPdfViaHttp extends externalFilesTests {
	/**
	 * Test to successfully import a PDF via HTTP.
	 *
	 * @return void
	 */
	public function test_import_pdf_via_http(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsBool( $result );
		$this->assertTrue( $result );

		// get the file object.
		$external_file_object = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsObject( $external_file_object );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_object );
		$this->assertEquals( self::get_test_file( 'pdf', 'http' ), $external_file_object->get_url( true ) );
	}

	/**
	 * Test for a failed import of a PDF via HTTP.
	 *
	 * @return void
	 */
	public function test_import_faulty_pdf_via_http(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_faulty_test_file( 'pdf', 'http' ) );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}
}
