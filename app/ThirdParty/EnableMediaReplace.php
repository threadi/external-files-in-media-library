<?php
/**
 * File to handle support for plugin "Enable Media Replace".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Post;

/**
 * Object to handle support for this plugin.
 */
class EnableMediaReplace {

	/**
	 * Instance of actual object.
	 *
	 * @var ?EnableMediaReplace
	 */
	private static ?EnableMediaReplace $instance = null;

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
	 * @return EnableMediaReplace
	 */
	public static function get_instance(): EnableMediaReplace {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Yoast is not active.
		if ( ! Helper::is_plugin_active( 'enable-media-replace/enable-media-replace.php' ) ) {
			return;
		}

		add_action( 'add_meta_boxes_attachment', array( $this, 'remove_media_box' ), 20, 1 );
		add_filter( 'media_row_actions', array( $this, 'remove_row_actions' ), 20, 2 );
	}

	/**
	 * Remove media boxes this plugin adds if the attachment file is an external file.
	 *
	 * @param WP_Post $post The requested post as object.
	 *
	 * @return void
	 */
	public function remove_media_box( WP_Post $post ): void {
		// get file by its ID.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if the file is not an external file-URL.
		if ( ! $external_file_obj ) {
			return;
		}

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// remove the boxes.
		remove_meta_box( 'emr-replace-box', 'attachment', 'side' );
		remove_meta_box( 'emr-showthumbs-box', 'attachment', 'side' );
	}

	/**
	 * Remove actions from this plugin in row listing.
	 *
	 * @param array   $actions List if actions.
	 * @param WP_Post $post The post as object.
	 *
	 * @return array
	 */
	public function remove_row_actions( array $actions, WP_Post $post ): array {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if file is not an external file.
		if ( ! $external_file_obj ) {
			return $actions;
		}

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		if ( isset( $actions['media_replace'] ) ) {
			unset( $actions['media_replace'] );
		}
		if ( isset( $actions['remove_background'] ) ) {
			unset( $actions['remove_background'] );
		}

		// return resulting list of actions.
		return $actions;
	}
}
