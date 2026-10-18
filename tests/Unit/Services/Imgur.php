<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Imgur.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Imgur.
 */
class Imgur extends externalFilesTests {
	/**
	 * Test if the special handling for Imgur is only used for URLs on Imgur.
	 *
	 * @return void
	 */
	public function test_special_handling_only_for_imgur(): void {
		$obj = \ExternalFilesInMediaLibrary\Services\Imgur::get_instance();

		// URLs on Imgur.
		$this->assertFalse( $obj->allow_http_response_without_content_type( true, 'https://i.imgur.com/example.jpg' ) );
		$this->assertTrue( $obj->force_local_saving( false, 'https://i.imgur.com/example.jpg' ) );
		$this->assertContains( 429, $obj->add_http_state( array( 200 ), 'https://i.imgur.com/example.jpg' ) );

		// URLs on other hosts, which contain "imgur".
		foreach ( array( 'https://example.com/?imgur', 'https://example.com/imgur.jpg', 'https://myimgur.example.com/a.jpg' ) as $url ) {
			$this->assertTrue( $obj->allow_http_response_without_content_type( true, $url ), $url );
			$this->assertFalse( $obj->force_local_saving( false, $url ), $url );
			$this->assertSame( array( 200 ), $obj->add_http_state( array( 200 ), $url ), $url );
			$this->assertTrue( $obj->check_url( true, $url ), $url );
		}
	}
}
