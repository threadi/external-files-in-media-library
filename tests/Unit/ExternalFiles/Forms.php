<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Forms.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Forms.
 */
class Forms extends externalFilesTests {
	/**
	 * Test if the returning variable is a string and contains the sound marking CSS class.
	 *
	 * @return void
	 */
	public function test_add_sound(): void {
		$classes = \ExternalFilesInMediaLibrary\ExternalFiles\Forms::get_instance()->add_sound( '' );
		$this->assertIsString( $classes );
		$this->assertStringContainsString( 'efml-play-found', $classes );
	}

	/**
	 * Return the test cases for the completion of URLs with the directory of an external source.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_url_with_source_directory(): array {
		return array(
			'relative path'                         => array( 'photos/a.jpg', 'DropBox/', 'DropBox/photos/a.jpg' ),
			'complete DropBox path in subdirectory' => array( 'DropBox/photos/a.jpg', 'DropBox/photos', 'DropBox/photos/a.jpg' ),
			'complete DropBox path with slash'      => array( 'DropBox/photos/sub/a.jpg', 'DropBox/photos/', 'DropBox/photos/sub/a.jpg' ),
			'directory itself'                      => array( 'DropBox/photos', 'DropBox/photos', 'DropBox/photos' ),
			'URL with protocol'                     => array( 'https://example.com/a.jpg', 'https://example.org/dir/', 'https://example.com/a.jpg' ),
			'path on server of source'              => array( '/dir/a.jpg', 'ftp://example.com/dir/', 'ftp://example.com/dir/a.jpg' ),
			'path on server of source with port'    => array( '/dir/a.jpg', 'ftp://example.com:2121/dir/', 'ftp://example.com:2121/dir/a.jpg' ),
		);
	}

	/**
	 * Test the completion of URLs with the directory of an external source, e.g. for files from its listing.
	 *
	 * @dataProvider provide_url_with_source_directory
	 *
	 * @param string $url The given URL.
	 * @param string $directory The directory of the external source.
	 * @param string $expected The expected URL.
	 *
	 * @return void
	 */
	public function test_get_url_with_source_directory( string $url, string $directory, string $expected ): void {
		$this->assertSame( $expected, \ExternalFilesInMediaLibrary\ExternalFiles\Forms::get_instance()->get_url_with_source_directory( $url, $directory ) );
	}
}
