<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Real_Import.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Real_Import.
 */
class Real_Import extends externalFilesTests {

	/**
	 * Test if a URL is imported as real local file, if this is requested for an import
	 * and the extension is not enabled for the synchronization.
	 *
	 * @return void
	 */
	public function test_real_import_saves_local_file(): void {
		// the extension is not enabled for the synchronization.
		update_option( 'eml_sync_extensions', array() );

		// get the amount of files before the import.
		$count = $this->get_media_file_count();

		// import the URL as real file.
		$_POST['real_import'] = 1;
		$result               = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );
		unset( $_POST['real_import'] );
		$this->assertTrue( $result );

		// a file has been added, but not as external file.
		$this->assertSame( $count + 1, $this->get_media_file_count() );
		$this->assertFalse( \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) ) );

		// get the added file.
		$attachments = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
			)
		);
		$this->assertCount( 1, $attachments );

		// it is a complete local file.
		$this->assertSame( 'application/pdf', $attachments[0]->post_mime_type );
		$this->assertNotEmpty( $attachments[0]->post_title );
		$file = get_attached_file( $attachments[0]->ID );
		$this->assertIsString( $file );
		$this->assertFileExists( $file );
		$this->assertEmpty( get_post_meta( $attachments[0]->ID, EFML_POST_META_URL, true ) );
	}
}
