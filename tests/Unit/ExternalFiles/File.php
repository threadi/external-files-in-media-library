<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\File.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\File.
 */
class File extends externalFilesTests {
	/**
	 * Prepare a file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
	}

	/**
	 * Return the file object of the test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_file_object(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		// get the file object.
		$file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsNotBool( $file_obj );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file_obj );

		// return the object.
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
		$this->assertEquals( self::get_test_file( 'pdf', 'http' ), $url );

		// get its URL with disabled proxy for PDF, which must be the external URL.
		update_option( 'eml_pdf_mode', 'external' );
		update_option( 'eml_pdf_proxy', 0 );
		$url = $file_obj->get_url();
		$this->assertIsString( $url );
		$this->assertEquals( self::get_test_file( 'pdf', 'http' ), $url );

		// get its URL with enabled proxy for PDF, which must be the proxied URL.
		update_option( 'eml_pdf_proxy', 1 );
		$url = $file_obj->get_url();
		$this->assertIsString( $url );
		$this->assertEquals( 'http://example.org/?emlproxy=' . basename( self::get_test_file( 'pdf', 'http' ) ), $url );

		// reset the setting.
		update_option( 'eml_pdf_proxy', 0 );
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
	 * Test if the returning variable is a string, and the required extension for the test URL:
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
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Pdf::class, $file_type );
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
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http::class, $protocol_handler );
	}

	/**
	 * Test the file name, which is used if the file is saved locally.
	 *
	 * @return void
	 */
	public function test_get_local_file_name(): void {
		$file_obj = $this->get_file_object();

		// test 1: the title is a valid file name.
		$file_obj->set_title( 'example.pdf' );
		$this->assertSame( 'example.pdf', $file_obj->get_local_file_name() );

		// test 2: the title is not a file name, so the name from the URL is used.
		$file_obj->set_title( 'My document' );
		$this->assertSame( 'example.pdf', $file_obj->get_local_file_name() );

		// test 3: the URL does not contain a file name with extension, so the title with the extension of the mime type is used.
		$file_obj->set_url( 'https://example.com/download?id=5&format=pdf' );
		$this->assertSame( 'My-document.pdf', $file_obj->get_local_file_name() );

		// test 4: the query string of the URL is never part of the name.
		$file_obj->set_url( 'https://example.com/files/report.pdf?version=2' );
		$this->assertSame( 'report.pdf', $file_obj->get_local_file_name() );
	}
}
