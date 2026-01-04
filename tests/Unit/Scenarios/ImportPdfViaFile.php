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
class ImportPdfViaFile extends externalFilesTests {
	/**
	 * Test to successfully import a PDF via file.
	 *
	 * @return void
	 */
	public function test_import_pdf_via_file(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'file' ) );
		$this->assertIsBool( $result );
		$this->assertTrue( $result );

		// get the file size of our test file.
		$file_size = \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem()->size( self::get_test_file( 'pdf', 'file' ) );

		// get the file object.
		$external_file_object = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'file' ) );
		$this->assertIsObject( $external_file_object );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_object );
		$this->assertEquals( self::get_test_file( 'pdf', 'file' ), $external_file_object->get_url( true ) );
		$this->assertEquals( $file_size, $external_file_object->get_filesize() );
	}

	/**
	 * Test for a failed import of a PDF via file.
	 *
	 * @return void
	 */
	public function test_failed_import_pdf_via_http(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_faulty_test_file( 'pdf', 'file' ) );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}
}
