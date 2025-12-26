<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Import.
 *
 * @package external-files-in-media-library
 */

namespace Unit\ExternalFiles;

use WP_Query;
use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Import.
 */
class Import extends WP_UnitTestCase {
	/**
	 * The URL of the file to use for testings.
	 *
	 * @var string
	 */
	private string $url = 'https://plugins.svn.wordpress.org/external-files-in-media-library/assets/example_en.pdf';

	/**
	 * Return amount of files in media library.
	 *
	 * @return int
	 */
	private function get_media_file_count(): int {
		$query = array(
			'posts_per_page' => -1,
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'fields'         => 'ids',
		);
		return ( new WP_Query( $query ) )->found_posts;
	}

	/**
	 * Test for adding a file by URL.
	 *
	 * @return void
	 */
	public function test_add_url(): void {
		// test 1: without URL.
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( '' );
		$this->assertIsBool( $add_url_result );
		$this->assertFalse( $add_url_result );
		$this->assertEquals( 0, $this->get_media_file_count() );

		// test 2: invalid URL.
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( 'example.com' );
		$this->assertIsBool( $add_url_result );
		$this->assertFalse( $add_url_result );
		$this->assertEquals( 0, $this->get_media_file_count() );

		// test 3: valid HTTP URL.
		$add_url_result = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( $this->url );
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
		$file_title = \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->optimize_file_title( 'example_en.pdf', $this->url, array( 'mime-type' => 'application/pdf' ) );
		$this->assertIsString( $file_title );
		$this->assertEquals( 'example_en.pdf', $file_title );
	}
}
