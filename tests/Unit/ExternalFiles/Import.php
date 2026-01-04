<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Import.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Import.
 */
class Import extends externalFilesTests {
	/**
	 * Test for adding a file by URL.
	 *
	 * @return void
	 */
	public function test_add_without_url(): void {
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( '' );
		$this->assertIsBool( $add_url_result );
		$this->assertFalse( $add_url_result );
		$this->assertEquals( 0, $this->get_media_file_count() );
	}

	/**
	 * Test for adding a file by URL.
	 *
	 * @return void
	 */
	public function test_add_with_invalid_url(): void {
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( 'example.com' );
		$this->assertIsBool( $add_url_result );
		$this->assertFalse( $add_url_result );
		$this->assertEquals( 0, $this->get_media_file_count() );
	}

	/**
	 * Test for adding a file by URL.
	 *
	 * @return void
	 */
	public function test_add_valid_url(): void {
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsBool( $add_url_result );
		$this->assertTrue( $add_url_result );
		$this->assertEquals( 1, $this->get_media_file_count() );
	}

	/**
	 * Test optimization of file titles.
	 *
	 * @return void
	 */
	public function test_optimize_file_title(): void {
		$file_title = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->optimize_file_title( 'example_en.pdf', self::get_test_file( 'pdf', 'http' ), array( 'mime-type' => 'application/pdf' ) );
		$this->assertIsString( $file_title );
		$this->assertEquals( 'example_en.pdf', $file_title );
	}
}
