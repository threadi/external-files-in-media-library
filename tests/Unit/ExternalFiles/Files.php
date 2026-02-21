<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Files.
 */
class Files extends externalFilesTests {
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
	 * Return the external file object for our test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_external_file_object_of_test_url(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		return \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
	}

	/**
	 * Return the attachment ID of our test file.
	 *
	 * @return int
	 */
	private function get_attachment_id(): int {
		return $this->get_external_file_object_of_test_url()->get_id();
	}

	/**
	 * Test if the returning variable is a string and the used test URL.
	 *
	 * @return void
	 */
	public function test_get_attachment_url(): void {
		$attachment_url = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_attachment_url( self::get_test_file( 'pdf', 'http' ), $this->get_attachment_id() );
		$this->assertIsString( $attachment_url );
		$this->assertEquals( 'http://example.org/?emlproxy=' . basename( self::get_test_file( 'pdf', 'http' ) ), $attachment_url );
	}

	/**
	 * Test if the returning variable is a string and the used test URL.
	 *
	 * @return void
	 */
	public function test_get_attachment_link(): void {
		$attachment_url = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_attachment_link( self::get_test_file( 'pdf', 'http' ), $this->get_attachment_id() );
		$this->assertIsString( $attachment_url );
		$this->assertEquals( self::get_test_file( 'pdf', 'http' ), $attachment_url );
	}

	/**
	 * Test if the returning variable is an array, which contains our test file.
	 *
	 * @return void
	 */
	public function test_get_files(): void {
		$files = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_files();
		$this->assertIsArray( $files );
		$this->assertContainsOnlyInstancesOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $files );
		$this->assertArrayHasObjectOfType( 'ExternalFilesInMediaLibrary\ExternalFiles\File', $files );
	}

	/**
	 * Test if the returning variable is an object, and the external file object for our test URL.
	 *
	 * @return void
	 */
	public function test_get_file(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $this->get_attachment_id() );
		$this->assertIsObject( $file );
		$this->assertEquals( $this->get_attachment_id(), $file->get_id() );
	}

	/**
	 * Test if the returning variable is an array, which contains our test file.
	 *
	 * @return void
	 */
	public function test_get_file_by_url(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsObject( $file );
		$this->assertInstanceOf(\ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file );
		$this->assertEquals( $this->get_attachment_id(), $file->get_id() );
	}

	/**
	 * Test if the returning variable is an array, which contains our test file.
	 *
	 * @return void
	 */
	public function test_get_file_by_title(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_title( $this->get_external_file_object_of_test_url()->get_title() );
		$this->assertIsObject( $file );
		$this->assertInstanceOf(\ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file );
		$this->assertEquals( $this->get_attachment_id(), $file->get_id() );
	}

	/**
	 * Test if the returning variable is an array, which contains our test file.
	 *
	 * @return void
	 */
	public function test_get_term_by_attachment_id(): void {
		$term = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_term_by_attachment_id( $this->get_attachment_id() );
		$this->assertFalse( $term );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_add_urls_by_hook(): void {
		$attachment_id = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->add_urls_by_hook( 0, self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsInt( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );
	}

	/**
	 * Test if the returning variable is an integer.
	 *
	 * @return void
	 */
	public function test_add_urls_by_hook_failed(): void {
		$attachment_id = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->add_urls_by_hook( 0, '' );
		$this->assertIsInt( $attachment_id );
		$this->assertEquals( 0, $attachment_id );
	}

	/**
	 * Test if the returning variable is a false-boolean.
	 *
	 * @return void
	 */
	public function test_prevent_images(): void {
		$result = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->prevent_images( false, $this->get_attachment_id() );
		$this->assertIsBool( $result );
		$this->assertFalse( $result );
	}

	/**
	 * Test if debug info return string with service name.
	 *
	 * @return void
	 */
	public function test_show_debug_info(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->show_debug_info( $file );
		$result = ob_get_clean();
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'HTTP', $result );
	}

	/**
	 * Test for an external source for a file, which has not used an external source.
	 *
	 * @return void
	 */
	public function test_show_external_source_info_not_set(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->show_external_source_info( $file );
		$result = ob_get_clean();
		$this->assertIsString( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test for an external source for a file, which has not used an external source.
	 *
	 * @return void
	 */
	public function test_get_external_source_title_not_set(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		ob_start();
		\ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_external_source_title( '', $file->get_id() );
		$result = ob_get_clean();
		$this->assertIsString( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test for metadata for an external file.
	 *
	 * Hint: need to disable the attachment pages.
	 *
	 * @return void
	 */
	public function test_get_attachment_metadata_without_attachment_pages(): void {
		// disable the attachment pages.
		update_option( 'eml_disable_attachment_pages', 1 );

		// get the file.
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );

		// test it.
		$attachment_metadata = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_attachment_metadata( array(), $file->get_id() );
		$this->assertIsArray( $attachment_metadata );
		$this->assertNotEmpty( $attachment_metadata );
		$this->assertArrayHasKey( 'file', $attachment_metadata );
		$this->assertEquals( (string) get_permalink( $file->get_id() ), $attachment_metadata['file'] );

		// reset the attachment pages.
		update_option( 'eml_disable_attachment_pages', 0 );
	}
}
