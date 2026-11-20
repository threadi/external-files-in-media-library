<?php
/**
 * File to handle support for the plugin "Regenerate Thumbnails".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Error;
use WP_REST_Request;

/**
 * Object to handle support for this plugin.
 *
 * The plugin gets the original image via wp_get_original_image_path(): for exported images, Local_Work loads their
 * local files and exports the new sizes. Imported external files have no local files, their sizes are created by
 * the proxy. The plugin would use their URL: it would check the file via the protocol of its URL (e.g. an anonymous
 * login on an FTP server) and create the sizes on the external source, if it could write there (a local directory).
 * The plugin is prevented for these files with a hint.
 */
class RegenerateThumbnails extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?RegenerateThumbnails
	 */
	private static ?RegenerateThumbnails $instance = null;

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
	 * @return RegenerateThumbnails
	 */
	public static function get_instance(): RegenerateThumbnails {
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
		return array( 'regenerate-thumbnails/regenerate-thumbnails.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not active.
		if ( ! Helper::is_plugin_active( 'regenerate-thumbnails/regenerate-thumbnails.php' ) ) {
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
		if ( false !== has_filter( 'rest_dispatch_request', array( $this, 'prevent_for_files_without_local_files' ) ) ) {
			return;
		}

		add_filter( 'rest_dispatch_request', array( $this, 'prevent_for_files_without_local_files' ), 10, 3 );
	}

	/**
	 * Prevent the creation of the sizes and the details of external files, which have no local files.
	 *
	 * The plugin uses its REST API for the regeneration of single images and for the bulk regeneration. The answer is
	 * an error like the errors of the plugin, so it shows the hint and continues with the next image.
	 *
	 * Runs after the check of the permissions of the route.
	 *
	 * @param mixed           $result  The result (null to run the callback of the route).
	 * @param WP_REST_Request $request The request.
	 * @param string          $route   The pattern of the route.
	 *
	 * @return mixed
	 */
	public function prevent_for_files_without_local_files( mixed $result, WP_REST_Request $request, string $route ): mixed {
		// bail if the request is answered already or this is not a route for a single image (the given route is the
		// pattern of the route, the requested route contains the ID).
		if ( null !== $result || 1 !== preg_match( '#^/regenerate-thumbnails/v\d+/(regenerate|attachmentinfo)/(\d+)$#', $request->get_route(), $matches ) ) {
			return $result;
		}

		// get the attachment ID.
		$attachment_id = absint( $matches[2] );

		// bail if this file could be used locally.
		if ( ! Local_Work::get_instance()->is_without_local_files( $attachment_id ) ) {
			return $result;
		}

		// return the error.
		return new WP_Error(
			'efml_external_file_without_local_files',
			__( 'This file is an external file without local files. Its thumbnails could not be regenerated, they are created by the proxy of your website when they are requested.', 'external-files-in-media-library' ),
			array(
				'status'     => 400,
				'attachment' => get_post( $attachment_id ),
			)
		);
	}
}
