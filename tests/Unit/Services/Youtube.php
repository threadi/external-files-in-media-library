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

	/**
	 * Create an external file for a YouTube video like the import does it and return its ID.
	 *
	 * @param string $url The YouTube URL.
	 *
	 * @return int
	 */
	private function create_video( string $url ): int {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'           => $url,
				'post_mime_type' => 'video/mp4',
				'post_title'     => 'video',
			)
		);
		\ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id )->set_url( $url );
		update_post_meta( $attachment_id, '_wp_attached_file', $url );
		return $attachment_id;
	}

	/**
	 * Test that visitors without login see the imported YouTube videos.
	 *
	 * The output hooks are set during init, which runs for every request: also without a user.
	 *
	 * @return void
	 */
	public function test_output_for_visitors(): void {
		wp_set_current_user( 0 );
		\ExternalFilesInMediaLibrary\Services\Youtube::get_instance()->init();
		$url           = 'https://www.youtube.com/watch?v=abcdefghijk';
		$attachment_id = $this->create_video( $url );

		// the shortcode, which is inserted in the classic editor, shows the video.
		$this->assertTrue( shortcode_exists( 'eml_youtube' ) );
		$output = do_shortcode( '[eml_youtube]' . $url . '[/eml_youtube]' );
		$this->assertStringContainsString( '<iframe', $output );
		$this->assertStringContainsString( 'youtube.com', $output );

		// the video block shows the video via the embed URL.
		$output = render_block(
			array(
				'blockName'    => 'core/video',
				'attrs'        => array( 'id' => $attachment_id ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<figure class="wp-block-video"><video></video></figure>',
				'innerContent' => array( '<figure class="wp-block-video"><video></video></figure>' ),
			)
		);
		$this->assertStringContainsString( '<iframe', $output );
		$this->assertStringContainsString( '//www.youtube.com/embed/abcdefghijk', $output );

		// the URL of the attachment is the YouTube URL, without the uploads URL and not via the proxy.
		$this->assertSame( $url, wp_get_attachment_url( $attachment_id ) );
		$this->assertFalse( apply_filters( 'efml_file_prevent_proxied_url', true, \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id ) ) );
	}

	/**
	 * Test that the checks of YouTube URLs are also used without a user, e.g. for the availability check via cron.
	 *
	 * @return void
	 */
	public function test_checks_without_user(): void {
		wp_set_current_user( 0 );
		\ExternalFilesInMediaLibrary\Services\Youtube::get_instance()->init();
		$url = 'https://www.youtube.com/watch?v=abcdefghijk';

		// YouTube answers with a redirect and HTML, both are fine for videos.
		$this->assertContains( 302, apply_filters( 'efml_http_states', array( 200 ), $url ) );
		$this->assertFalse( apply_filters( 'efml_http_check_content_type', true, $url ) );

		// YouTube videos are never saved locally.
		$this->assertFalse( apply_filters( 'efml_http_save_local', true, $url ) );
		$this->assertFalse( apply_filters( 'efml_save_temp_file', true, $url ) );

		// other URLs are not changed.
		$this->assertSame( array( 200 ), apply_filters( 'efml_http_states', array( 200 ), 'https://example.com/video.mp4' ) );
		$this->assertTrue( apply_filters( 'efml_http_check_content_type', true, 'https://example.com/video.mp4' ) );
	}
}
