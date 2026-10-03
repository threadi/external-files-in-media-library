<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Youtube.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Youtube.
 */
class Youtube extends externalFilesTests {

	/**
	 * Test if the shortcode does not fail without attributes, as WordPress older than 6.5
	 * uses an empty string instead of an array in this case.
	 *
	 * @return void
	 */
	public function test_render_video_shortcode_without_attributes(): void {
		$service_obj = \ExternalFilesInMediaLibrary\Services\Youtube::get_instance();

		// without a URL.
		$this->assertSame( '', $service_obj->render_video_shortcode( '', null ) );

		// with a URL, which is not in the media library.
		$this->assertSame( '', $service_obj->render_video_shortcode( '', 'https://www.youtube.com/watch?v=abcdefghijk' ) );
		$this->assertSame( '', $service_obj->render_video_shortcode( array(), 'https://www.youtube.com/watch?v=abcdefghijk' ) );
	}
}
