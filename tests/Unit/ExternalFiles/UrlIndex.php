<?php
/**
 * Tests for the index of the URLs of external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Url_Index;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the index of the URLs of external files.
 */
class UrlIndex extends externalFilesTests {

	/**
	 * Prepare each test: the indexing of files saved before is done.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( Url_Index::BACKFILL_OPTION, array( 'done' => true ) );
	}

	/**
	 * Create an attachment with the given URL as external file.
	 *
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	private function create_external_file( string $url ): int {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		Files::get_instance()->get_file( $attachment_id )->set_url( $url );
		return $attachment_id;
	}

	/**
	 * Test that the key is saved, changed and removed together with the URL.
	 *
	 * @return void
	 */
	public function test_key_follows_url(): void {
		$index         = Url_Index::get_instance();
		$url           = 'https://example.com/files/image-' . wp_generate_password( 6, false ) . '.jpg';
		$attachment_id = $this->create_external_file( $url );

		// the key exists and is protected.
		$this->assertEquals( '1', get_post_meta( $attachment_id, $index->get_key( $url ), true ) );
		$this->assertTrue( is_protected_meta( $index->get_key( $url ), 'post' ) );
		$this->assertEquals( $attachment_id, $index->find( $url ) );
		$this->assertEquals( $attachment_id, Files::get_instance()->get_file_by_url( $url )->get_id() );

		// a changed URL changes the key (also if written directly via the functions of WordPress).
		$new_url = $url . '?v=2';
		update_post_meta( $attachment_id, EFML_POST_META_URL, $new_url );
		$this->assertEquals( '', get_post_meta( $attachment_id, $index->get_key( $url ), true ) );
		$this->assertEquals( $attachment_id, $index->find( $new_url ) );
		$this->assertEquals( 0, $index->find( $url ) );

		// a removed URL removes the key.
		Files::get_instance()->get_file( $attachment_id )->remove_url();
		$this->assertEquals( '', get_post_meta( $attachment_id, $index->get_key( $new_url ), true ) );
		$this->assertEquals( 0, $index->find( $new_url ) );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $new_url ) );
	}

	/**
	 * Test the duplicate check of the protocols with the index.
	 *
	 * @return void
	 */
	public function test_duplicate_check(): void {
		$url = 'https://example.com/files/duplicate-' . wp_generate_password( 6, false ) . '.jpg';
		$this->assertFalse( ( new Http( $url ) )->check_for_duplicate( $url ) );
		$this->create_external_file( $url );
		$this->assertTrue( ( new Http( $url ) )->check_for_duplicate( $url ) );
	}

	/**
	 * Test files saved before without key: found by their URL until they are indexed in the background.
	 *
	 * @return void
	 */
	public function test_files_saved_before(): void {
		$index         = Url_Index::get_instance();
		$url           = 'https://example.com/files/old-' . wp_generate_password( 6, false ) . '.jpg';
		$attachment_id = $this->create_external_file( $url );

		// simulate a file saved before: without key, the indexing is not done.
		delete_post_meta( $attachment_id, $index->get_key( $url ) );
		delete_option( Url_Index::BACKFILL_OPTION );

		// it is found by its URL.
		$this->assertEquals( $attachment_id, $index->find( $url ) );

		// the background run saves the key and marks the indexing as done.
		add_filter( 'efml_url_index_backfill_size', array( $this, 'get_large_backfill_size' ) );
		$index->run_backfill();
		remove_filter( 'efml_url_index_backfill_size', array( $this, 'get_large_backfill_size' ) );
		$this->assertTrue( $index->is_complete() );
		$this->assertEquals( '1', get_post_meta( $attachment_id, $index->get_key( $url ), true ) );
		$this->assertEquals( $attachment_id, $index->find( $url ) );

		// without key after the indexing it is not found anymore (e.g. URL saved via SQL).
		delete_post_meta( $attachment_id, $index->get_key( $url ) );
		$this->assertEquals( 0, $index->find( $url ) );
	}

	/**
	 * Return a number of files per step of the background run, which covers all files of the test database.
	 *
	 * @return int
	 */
	public function get_large_backfill_size(): int {
		return 100000;
	}

	/**
	 * Test that the keys of deleted files are removed and that the uninstallation removes all keys.
	 *
	 * @return void
	 */
	public function test_delete_and_uninstall(): void {
		global $wpdb;
		$index = Url_Index::get_instance();

		// deleting the attachment removes its key.
		$url           = 'https://example.com/files/deleted-' . wp_generate_password( 6, false ) . '.jpg';
		$attachment_id = $this->create_external_file( $url );
		wp_delete_attachment( $attachment_id, true );
		$this->assertEquals( 0, $index->find( $url ) );
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `meta_key` = %s', $wpdb->postmeta, $index->get_key( $url ) ) ) );

		// the uninstallation removes all keys and the state.
		$this->create_external_file( 'https://example.com/files/kept-' . wp_generate_password( 6, false ) . '.jpg' );
		$index->uninstall();
		$this->assertEquals( 0, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `meta_key` LIKE %s', $wpdb->postmeta, $wpdb->esc_like( Url_Index::PREFIX ) . '%' ) ) );
		$this->assertFalse( get_option( Url_Index::BACKFILL_OPTION ) );
	}
}
