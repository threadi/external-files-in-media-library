<?php
/**
 * File to handle support for Vimeo videos.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Templates;

/**
 * Object to handle support for this video plattform.
 */
class Vimeo {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Vimeo
	 */
	private static ?Vimeo $instance = null;

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
	 * @return Vimeo
	 */
	public static function get_instance(): Vimeo {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
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
		add_shortcode( 'eml_vimeo', array( $this, 'render_video_shortcode' ) );
	}

	/**
	 * Check if given URL during import is a Vimeo video and set its data.
	 *
	 * @param array  $results The result as array for file import.
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	public function get_video_data( array $results, string $url ): array {
		// bail if this is not a Vimeo-URL.
		if ( ! $this->is_vimeo_video( $url ) ) {
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
	 * Prevent usage of proxied URL for Vimeo URLs.
	 *
	 * @param bool $result The result.
	 * @param File $external_file_object The file object.
	 *
	 * @return bool
	 */
	public function prevent_proxied_url( bool $result, File $external_file_object ): bool {
		// bail if file is not a Vimeo-video.
		if( ! $this->is_vimeo_video( $external_file_object->get_url( true ) ) ) {
			return $result;
		}

		// return false to prevent the usage of proxied URLs for this file.
		return false;
	}

	/**
	 * Check if given URL is a Vimeo-video.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_vimeo_video( string $url ): bool {
		return str_contains( $url, 'vimeo.com' );
	}

	/**
	 * Render the Video block to show an external filed Vimeo video.
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

		// bail if file is not a Vimeo file.
		if( ! $this->is_vimeo_video( $external_file_object->get_url( true ) ) ) {
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
		require_once Templates::get_instance()->get_template( 'vimeo.php' );
		?></figure><?php
		$content = ob_get_contents();
		ob_end_clean();

		// return resulting output.
		return $content;
	}

	/**
	 * Get embed URL for given Vimeo URL.
	 *
	 * @source https://stackoverflow.com/questions/28563706/how-to-convert-vimeo-url-to-embed-without-letting-go-of-the-text-around-it
	 * @param string $vimeo_url The given Vimeo-URL.
	 *
	 * @return string
	 */
	private function get_embed_url( string $vimeo_url ): string {
		// get the ID from given URL.
		if( preg_match('/\/\/(www\.)?vimeo.com\/(\d+)($|\/)/', $vimeo_url,$matches ) ) {
			// bail if second match does not be the ID.
			if( 0 === absint( $matches[2] ) ) {
				return $vimeo_url;
			}

			// return the player URL.
			return '//player.vimeo.com/video/' . absint( $matches[2] );
		}

		// return the original URL.
		return $vimeo_url;
	}

	/**
	 * Change return value for Vimeo-videos chosen from media library.
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

		// bail if this is not a Vimeo video.
		if( ! $this->is_vimeo_video( $external_file_obj->get_url( true ) ) ) {
			return $html;
		}

		// return the Vimeo Shortcode.
		return '[eml_vimeo]' . $external_file_obj->get_url( true ) . '[/eml_vimeo]';
	}

	/**
	 * Render the shortcode to output Vimeo videos generated by our own plugin.
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
		$size_w = 560;
		if( ! empty( $attributes['width'] ) ) {
			$size_w = $attributes['width'];
		}

		// get height.
		$size_h = 320;
		if( ! empty( $attributes['height'] ) ) {
			$size_h = $attributes['height'];
		}

		// get URL.
		$url = $external_file_obj->get_url( true );

		// get the output of the template.
		ob_start();
		require_once Templates::get_instance()->get_template( 'vimeo.php' );
		$video_html = ob_get_contents();
		ob_end_clean();

		// return the HTML-code to output a Vimeo Video.
		return $video_html;
	}
}
