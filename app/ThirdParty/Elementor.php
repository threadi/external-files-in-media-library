<?php
/**
 * File to handle support for plugin "Elementor".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Elementor\Element_Base;
use Elementor\Widget_Video;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to handle support for this plugin.
 */
class Elementor extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Elementor
	 */
	private static ?Elementor $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Elementor
	 */
	public static function get_instance(): Elementor {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'add_youtube_video' ) );
	}

	/**
	 * Switch from local hostet video to YouTube video in output in frontend.
	 *
	 * @param Element_Base $element The basic Elementor widget object.
	 *
	 * @return void
	 */
	public function add_youtube_video( Element_Base $element ): void {
		// bail if this is not the video widget.
		if ( ! $element instanceof Widget_Video ) {
			return;
		}

		// get the settings.
		$settings = $element->get_settings();

		// bail if settings is not an array.
		if ( ! is_array( $settings ) ) {
			return;
		}

		// bail if hostet URL is not set.
		if ( empty( $settings['hosted_url'] ) ) {
			return;
		}

		// bail if not ID is given.
		if ( empty( $settings['hosted_url']['id'] ) ) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
			return;
		}

		// get the attachment ID.
		$attachment_id = absint( $settings['hosted_url']['id'] );

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if external file obj could not be loaded.
		if ( ! $external_file_obj ) {
			return;
		}

		// remove the local hosted marker.
		$element->set_settings( 'video_type', 'youtube' );
		$element->delete_setting( 'hosted_url' );
		$element->delete_setting( '__dynamic__' );

		// set the YouTube URL.
		$element->set_settings( 'youtube_url', $external_file_obj->get_url( true ) );
	}
}
