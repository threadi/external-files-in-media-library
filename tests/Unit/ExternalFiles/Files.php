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

	/**
	 * Test if our post meta keys are protected against manual changes, but other keys are not.
	 *
	 * @return void
	 */
	public function test_protect_meta_keys(): void {
		// our own keys on posts are protected.
		foreach ( array( EFML_POST_META_URL, EFML_POST_META_AVAILABILITY, 'eml_fields', 'eml_exported_file', 'efml_export_sources' ) as $meta_key ) {
			$this->assertTrue( is_protected_meta( $meta_key, 'post' ), $meta_key );
		}

		// other keys and other object types are not changed.
		$this->assertFalse( is_protected_meta( 'my_custom_field', 'post' ) );
		$this->assertFalse( is_protected_meta( EFML_POST_META_URL, 'term' ) );
		$this->assertTrue( is_protected_meta( '_edit_lock', 'post' ) );
	}

	/**
	 * Test if a user who is allowed to edit an attachment is not allowed to change our post meta fields.
	 *
	 * @return void
	 */
	public function test_post_meta_is_not_editable_by_users(): void {
		// create an author with an own attachment.
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );
		$attachment_id = self::factory()->attachment->create( array( 'post_author' => $user_id ) );

		// the user can edit the attachment, but not our field on it.
		$this->assertTrue( current_user_can( 'edit_post', $attachment_id ) );
		$this->assertTrue( current_user_can( 'edit_post_meta', $attachment_id, 'my_custom_field' ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $attachment_id, EFML_POST_META_URL ) );
		$this->assertFalse( current_user_can( 'add_post_meta', $attachment_id, EFML_POST_META_URL ) );
	}

	/**
	 * Test if our callbacks on image filters of WordPress do not fail if other values than arrays are used,
	 * as WordPress itself and other plugins use e.g. false there.
	 *
	 * @return void
	 */
	public function test_image_filters_with_unexpected_values(): void {
		$files_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance();

		// via the direct calls.
		$this->assertFalse( $files_obj->get_image_srcset( false, array(), '', array(), 0 ) );
		$this->assertFalse( $files_obj->check_srcset_meta( false, array(), '', 0 ) );
		$this->assertFalse( $files_obj->get_attachment_metadata( false, 0 ) );
		$this->assertNull( $files_obj->get_attachment_metadata( null, '0' ) );

		// via the hooks of WordPress.
		$this->assertFalse( apply_filters( 'wp_calculate_image_srcset', false, array(), '', array(), 0 ) );
		$this->assertFalse( apply_filters( 'wp_calculate_image_srcset_meta', false, array(), '', 0 ) );
		$this->assertFalse( apply_filters( 'wp_get_attachment_metadata', false, 0 ) );
	}

	/**
	 * Test if the image filters do not change the values for files, which are not external files.
	 *
	 * @return void
	 */
	public function test_image_filters_for_not_external_files(): void {
		$attachment_id = self::factory()->attachment->create();
		$sources       = array(
			300 => array(
				'url'        => 'https://example.org/image-300x200.jpg',
				'descriptor' => 'w',
				'value'      => 300,
			),
		);
		$image_meta    = array(
			'file'   => 'image.jpg',
			'width'  => 600,
			'height' => 400,
		);
		$files_obj     = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance();
		$this->assertEquals( $sources, $files_obj->get_image_srcset( $sources, array( 300, 200 ), '', $image_meta, $attachment_id ) );
		$this->assertEquals( $image_meta, $files_obj->check_srcset_meta( $image_meta, array( 300, 200 ), '', $attachment_id ) );
	}
}
