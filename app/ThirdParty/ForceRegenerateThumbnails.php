<?php
/**
 * File to handle support for the plugin "Force Regenerate Thumbnails".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 *
 * The plugin searches the local file of an image itself and stops, if it does not exist. For exported images, their
 * local files are loaded before (in the backend and via its WP CLI command), so the plugin creates their sizes again
 * and Local_Work exports them. Imported external files without local files are skipped: their sizes are created by
 * the proxy, and the plugin would check their URL via its protocol (e.g. with an anonymous login on an FTP server).
 */
class ForceRegenerateThumbnails extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ForceRegenerateThumbnails
	 */
	private static ?ForceRegenerateThumbnails $instance = null;

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
	 * @return ForceRegenerateThumbnails
	 */
	public static function get_instance(): ForceRegenerateThumbnails {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the plugin files of the supported plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'force-regenerate-thumbnails/force-regenerate-thumbnails.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not active.
		if ( ! Helper::is_plugin_active( 'force-regenerate-thumbnails/force-regenerate-thumbnails.php' ) ) {
			return;
		}

		// use the hooks.
		$this->add_hooks();
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		// bail if they are added already.
		if ( false !== has_filter( 'regenerate_thumbs_skip_image', array( $this, 'prepare_image' ) ) ) {
			return;
		}

		add_filter( 'regenerate_thumbs_skip_image', array( $this, 'prepare_image' ), 10, 2 );
	}

	/**
	 * Prepare an image, before the plugin creates its sizes again: skip external files without local files and load
	 * the local files of exported images.
	 *
	 * The plugin uses this filter for each image in the backend and in its WP CLI command, after it has checked the
	 * permissions and before it searches the local file. The local files are removed again after the export of the new
	 * sizes or at the end of the request.
	 *
	 * @param mixed $skip          Whether the image is skipped.
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function prepare_image( mixed $skip, mixed $attachment_id ): mixed {
		// bail if the image is skipped already or no ID is given.
		if ( true === $skip || ! is_numeric( $attachment_id ) ) {
			return $skip;
		}

		// get the local work object.
		$attachment_id  = absint( $attachment_id );
		$local_work_obj = Local_Work::get_instance();

		// skip external files without local files.
		if ( $local_work_obj->is_without_local_files( $attachment_id ) ) {
			return true;
		}

		// load the local files of an exported image, if they could be used in this request.
		if ( ! $local_work_obj->is_open( $attachment_id ) && $local_work_obj->is_allowed( $attachment_id ) ) {
			$local_work_obj->open( $attachment_id, 'regenerate' );
		}

		// return the value unchanged.
		return $skip;
	}
}
