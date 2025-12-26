<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Files.
 *
 * @package external-files-in-media-library
 */

namespace Unit\Plugin;

use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Files.
 */
class Files extends WP_UnitTestCase {
	use CustomAssertTrait;

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
	 * Return the external file object for our test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_external_file_object_of_test_url(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		return \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $this->url );
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
		$attachment_url = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_attachment_url( $this->url, $this->get_attachment_id() );
		$this->assertIsString( $attachment_url );
		$this->assertEquals( 'http://example.org/?emlproxy=example_en.pdf', $attachment_url );
	}

	/**
	 * Test if the returning variable is a string and the used test URL.
	 *
	 * @return void
	 */
	public function test_get_attachment_link(): void {
		$attachment_url = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_attachment_link( $this->url, $this->get_attachment_id() );
		$this->assertIsString( $attachment_url );
		$this->assertEquals( $this->url, $attachment_url );
	}

	/**
	 * Test if the returning variable is an array which contains our test file.
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
	 * Test if the returning variable is an object and the external file object for our test URL.
	 *
	 * @return void
	 */
	public function test_get_file(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $this->get_attachment_id() );
		$this->assertIsObject( $file );
		$this->assertInstanceOf(\ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file );
		$this->assertEquals( $this->get_attachment_id(), $file->get_id() );
	}

	/**
	 * Test if the returning variable is an array which contains our test file.
	 *
	 * @return void
	 */
	public function test_get_file_by_url(): void {
		$file = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( $this->url );
		$this->assertIsObject( $file );
		$this->assertInstanceOf(\ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file );
		$this->assertEquals( $this->get_attachment_id(), $file->get_id() );
	}

	/**
	 * Test if the returning variable is an array which contains our test file.
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
	 * Test if the returning variable is an array which contains our test file.
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
		$attachment_id = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->add_urls_by_hook( 0, $this->url );
		$this->assertIsInt( $attachment_id );
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
}
