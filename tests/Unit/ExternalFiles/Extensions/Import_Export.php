<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Import_Export.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Import_Export.
 */
class Import_Export extends externalFilesTests {
	/**
	 * The JSON used in the tests.
	 *
	 * @var string
	 */
	private string $json = '{"url":"https://example.com/from-json.pdf"}';

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $_POST['import_json'] );
		parent::tear_down();
	}

	/**
	 * Test if the URL from the JSON is only used, if the JSON import is enabled.
	 *
	 * @return void
	 */
	public function test_set_urls_for_import(): void {
		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Import_Export::get_instance();

		// not set in request.
		$this->assertSame( array( 'original' ), $obj->set_urls_for_import( array( 'original' ), $this->json ) );

		// disabled in request.
		$_POST['import_json'] = 0;
		$this->assertSame( array( 'original' ), $obj->set_urls_for_import( array( 'original' ), $this->json ) );

		// enabled in request.
		$_POST['import_json'] = 1;
		$this->assertSame( array( 'https://example.com/from-json.pdf' ), $obj->set_urls_for_import( array( 'original' ), $this->json ) );
	}

	/**
	 * Test if invalid JSON does not change the URLs.
	 *
	 * @return void
	 */
	public function test_set_urls_for_import_with_invalid_json(): void {
		$_POST['import_json'] = 1;
		$obj                  = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Import_Export::get_instance();
		$this->assertSame( array( 'original' ), $obj->set_urls_for_import( array( 'original' ), 'no json' ) );
		$this->assertSame( array( 'original' ), $obj->set_urls_for_import( array( 'original' ), '{"title":"without url"}' ) );
	}

	/**
	 * Test if the fields are not changed, if the JSON import is disabled.
	 *
	 * @return void
	 */
	public function test_set_fields_for_import_disabled(): void {
		$_POST['import_json'] = 0;
		$fields               = array( 'login' => array( 'value' => 'x' ) );
		$this->assertSame( $fields, \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Import_Export::get_instance()->set_fields_for_import( $fields, array(), $this->json ) );
	}
}
