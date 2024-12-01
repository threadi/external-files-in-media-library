<?php
/**
 * File to handle support for Youtube videos.
 *
 * TODO:
 * - auch in Elementor testen
 * - wenn die Ausgabe geht, dann Import von Kanälen per YouTube API ergänzen
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Templates;

/**
 * Object to handle support for this plugin.
 */
class Youtube {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Youtube
	 */
	private static ?Youtube $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Youtube
	 */
	public static function get_instance(): Youtube {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Youtube();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'eml_filter_url_response', array( $this, 'get_video_data' ), 10, 2 );
		add_filter( 'eml_file_prevent_proxied_url', array( $this, 'prevent_proxied_url' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'render_video_block' ), 10, 2 );
		add_filter( 'media_send_to_editor', array( $this, 'get_video_shortcode' ), 10, 2 );
		add_shortcode( 'eml_youtube', array( $this, 'render_video_shortcode' ) );
	}

	/**
	 * Check if given URL during import is a YouTube video and set its data.
	 *
	 * @param array  $results The result as array for file import.
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	public function get_video_data( array $results, string $url ): array {
		// bail if this is not a YouTube-URL.
		if ( ! $this->is_youtube_video( $url ) ) {
			return $results;
		}

		// initialize basic array for file data.
		return array(
			'title'     => basename( $url ),
			'filesize'  => 1,
			'mime-type' => 'video/mp4',
			'local'     => false,
			'url'       => $url,
			'tmp-file' => ''
		);
	}

	/**
	 * Prevent usage of proxied URL for YouTube URLs.
	 *
	 * @param bool $result The result.
	 * @param File $external_file_object The file object.
	 *
	 * @return bool
	 */
	public function prevent_proxied_url( bool $result, File $external_file_object ): bool {
		// bail if file is not a YouTube-video.
		if( ! $this->is_youtube_video( $external_file_object->get_url( true ) ) ) {
			return $result;
		}

		// return false to prevent the usage of proxied URLs for this file.
		return false;
	}

	/**
	 * Check if given URL is a YouTube-video.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_youtube_video( string $url ): bool {
		return str_contains( $url, 'youtube.com' );
	}

	/**
	 * Get embed URL for given YouTube URL.
	 *
	 * @source https://stackoverflow.com/questions/19050890/find-youtube-link-in-php-string-and-convert-it-into-embed-code
	 * @param string $youtube_url The given YouTube-URL.
	 *
	 * @return string
	 */
	private function get_embed_url( string $youtube_url ): string {
		// define the regex.
		$short_url_regex = '/youtu.be\/([a-zA-Z0-9_-]+)\??/i';
		$long_url_regex = '/youtube.com\/((?:embed)|(?:watch))((?:\?v\=)|(?:\/))([a-zA-Z0-9_-]+)/i';

		$youtube_id = false;
		if ( preg_match( $long_url_regex, $youtube_url, $matches ) ) {
			$youtube_id = $matches[count($matches) - 1];
		}

		if ( preg_match( $short_url_regex, $youtube_url, $matches ) ) {
			$youtube_id = $matches[count($matches) - 1];
		}

		// bail if YouTube ID could not be determined.
		if( !$youtube_id ) {
			return '';
		}

		// return the embed URL.
		return 'https://www.youtube.com/embed/' . $youtube_id ;
	}

	/**
	 * Render the Video block to show an external filed YouTube video.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block The block configuration.
	 *
	 * @return string
	 */
	public function render_video_block( string $block_content, array $block ): string {
		// bail if this is not core/video.
		if( 'core/video' !== $block['blockName'] ) {
			return $block_content;
		}

		// bail if id is not given.
		if( empty( $block['attrs']['id'] ) ) {
			return $block_content;
		}

		// get the attachment ID.
		$attachment_id = absint( $block['attrs']['id'] );

		// bail if ID is not given.
		if( 0 === $attachment_id ) {
			return $block_content;
		}

		// get external file object for this ID.
		$external_file_object = Files::get_instance()->get_file( $attachment_id );

		// bail if file is not an external file.
		if( ! $external_file_object ) {
			return $block_content;
		}

		// bail if file is not a YouTube file.
		if( ! $this->is_youtube_video( $external_file_object->get_url( true ) ) ) {
			return $block_content;
		}

		// get embed URL.
		$url = $this->get_embed_url( $external_file_object->get_url( true ) );

		// set sizes.
		$size_w = 560;
		$size_h = 320;

		// get output.
		ob_start();
		?><figure><?php
		require_once Templates::get_instance()->get_template( 'youtube.php' );
		?></figure><?php
		$content = ob_get_contents();
		ob_end_clean();

		// return resulting output.
		return $content;
	}

	/**
	 * Change return value for Youtube-videos chosen from media library.
	 *
	 * @param string $html The output.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_video_shortcode( string $html, int $attachment_id ): string {
		// get external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file.
		if( ! $external_file_obj ) {
			return $html;
		}

		// bail if this is not a YouTube video.
		if( ! $this->is_youtube_video( $external_file_obj->get_url( true ) ) ) {
			return $html;
		}

		// return the YouTube Shortcode.
		return '[eml_youtube]' . $external_file_obj->get_url( true ) . '[/eml_youtube]';
	}

	/**
	 * Render the shortcode to output YouTube videos generated by our own plugin.
	 *
	 * @param array  $attributes List of attributes.
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function render_video_shortcode( array $attributes, string $url ): string {
		// get external file object by given URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// bail if this is not an external file.
		if( ! $external_file_obj ) {
			return '';
		}

		// get width.
		$width = 560;
		if( ! empty( $attributes['width'] ) ) {
			$width = $attributes['width'];
		}

		// get height:
		$height = 315;
		if( ! empty( $attributes['height'] ) ) {
			$height = $attributes['height'];
		}

		// return the HTML-code to output a YouTube Video.
		return '<iframe width="' . absint( $width ) . '" height="' . absint( $height ) . '" src="' . esc_url( $this->get_embed_url( $external_file_obj->get_url( true ) ) ) .  '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>';
	}
}
