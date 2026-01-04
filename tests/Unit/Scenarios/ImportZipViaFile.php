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
class ImportZipViaFile extends externalFilesTests {
	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// allow zip files.
		add_filter( 'efml_get_mime_types', array( $this, 'allow_zip' ) );
	}

	/**
	 * Allow zip to import.
	 *
	 * @param array $mime_types
	 *
	 * @return array
	 */
	public function allow_zip( array $mime_types ): array {
		$mime_types[] = 'application/zip';
		return $mime_types;
	}

	/**
	 * Clean up after the test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'efml_get_mime_types', array( $this, 'allow_zip' ) );
	}

	/**
	 * Test to successfully import a ZIP via HTTP.
	 *
	 * @return void
	 */
	public function test_import_zip_via_file(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'zip', 'file' ) );
		$this->assertIsBool( $result );
		$this->assertTrue( $result );

		// get the file object.
		$external_file_object = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'zip', 'file' ) );
		$this->assertIsObject( $external_file_object );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_object );
		$this->assertEquals( self::get_test_file( 'zip', 'file' ), $external_file_object->get_url( true ) );
	}

	/**
	 * Test for a failed import of a PDF via HTTP.
	 *
	 * @return void
	 */
	public function test_import_faulty_zip_via_file(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_faulty_test_file( 'zip', 'file' ) );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}
}
