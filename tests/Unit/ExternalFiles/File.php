<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\File.
 *
 * @package external-files-in-media-library
 */

namespace Unit\Plugin;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Pdf;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\File.
 */
class File extends WP_UnitTestCase {

	/**
	 * The URL of the file to use for testings.
	 *
	 * @var string
	 */
	private string $url = 'https://plugins.svn.wordpress.org/external-files-in-media-library/assets/example_en.pdf';

	/**
	 * Prepare a file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( $this->url );
	}

	/**
	 * Return the file object of the test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_file_object(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		// get the file object.
		$file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $this->url );
		$this->assertIsNotBool( $file_obj );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file_obj );

		return $file_obj;
	}

	/**
	 * Test if the returning variable is a string and the used test URL.
	 *
	 * @return void
	 */
	public function test_get_url(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get its unproxied URL.
		$url = $file_obj->get_url( true );
		$this->assertIsString( $url );
		$this->assertEquals( $this->url, $url );

		// get its proxied URL.
		$url = $file_obj->get_url();
		$this->assertIsString( $url );
		$this->assertNotEquals( $this->url, $url );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_date(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the date.
		$date = $file_obj->get_date();
		$this->assertIsString( $date );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_fields(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the fields array.
		$fields = $file_obj->get_fields();
		$this->assertIsArray( $fields );
	}

	/**
	 * Test if the returning variable is a string and the required extension for the test URL:
	 *
	 * @return void
	 */
	public function test_get_file_extension(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the fields array.
		$extension = $file_obj->get_file_extension();
		$this->assertIsString( $extension );
		$this->assertEquals( 'pdf', $file_obj->get_file_extension() );
	}

	/**
	 * Test if the returning variable is an object and equal to the matching object for the test URL file type.
	 *
	 * @return void
	 */
	public function test_get_file_type_obj(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the fields array.
		$file_type = $file_obj->get_file_type_obj();
		$this->assertIsObject( $file_type );
		$this->assertInstanceOf( Pdf::class, $file_type );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_get_filesize(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the file size.
		$size = $file_obj->get_filesize();
		$this->assertIsInt( $size );
		$this->assertEquals( 19011, $size );
	}

	/**
	 * Test if the returning variable is a string and matching the test file mime type.
	 *
	 * @return void
	 */
	public function test_get_mime_type(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the mime type.
		$mime_type = $file_obj->get_mime_type();
		$this->assertIsString( $mime_type );
		$this->assertEquals( 'application/pdf', $mime_type );
	}

	/**
	 * Test if the returning variable is an object and matching the test object protocol handler.
	 *
	 * @return void
	 */
	public function test_get_protocol_handler_obj(): void {
		// get the file object.
		$file_obj = $this->get_file_object();

		// get the protocol handler.
		$protocol_handler = $file_obj->get_protocol_handler_obj();
		$this->assertIsObject( $protocol_handler );
		$this->assertInstanceOf( Http::class, $protocol_handler );
	}
}
