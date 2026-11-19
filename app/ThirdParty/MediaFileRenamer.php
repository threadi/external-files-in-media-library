<?php
/**
 * File to handle support for the plugin "Media File Renamer".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Post;
use WP_REST_Request;

/**
 * Object to handle support for this plugin.
 *
 * The plugin renames the files of an image on the local disk. For exported images, whose files are not local, their
 * files are loaded before the plugin renames them. The renamed files are exported afterward by Local_Work.
 */
class MediaFileRenamer extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?MediaFileRenamer
	 */
	private static ?MediaFileRenamer $instance = null;

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
	 * @return MediaFileRenamer
	 */
	public static function get_instance(): MediaFileRenamer {
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
		return array( 'media-file-renamer/media-file-renamer.php', 'media-file-renamer-pro/media-file-renamer-pro.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Media File Renamer is not active.
		if ( ! Helper::is_plugin_active( 'media-file-renamer/media-file-renamer.php' ) && ! Helper::is_plugin_active( 'media-file-renamer-pro/media-file-renamer-pro.php' ) ) {
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
		if ( false !== has_filter( 'rest_dispatch_request', array( $this, 'start_via_rest' ) ) ) {
			return;
		}

		add_filter( 'rest_dispatch_request', array( $this, 'start_via_rest' ), 10, 3 );
		add_filter( 'mfrh_allow_rename', array( $this, 'start_via_filter' ), 10, 3 );
		add_filter( 'efml_export_upload_delay', array( $this, 'set_delay' ), 10, 2 );
	}

	/**
	 * Return the delay in seconds for the export of an upload, as the plugin could rename it after the upload.
	 *
	 * @param int $delay         The delay in seconds.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function set_delay( int $delay, int $attachment_id ): int {
		return max( $delay, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Load the local files of an exported image, before the plugin renames or moves it via its REST API (its
	 * dashboard and the field in the media library use it).
	 *
	 * The plugin checks whether the file exists, before it uses the filter "mfrh_allow_rename". This runs after the
	 * check of the permissions of the route.
	 *
	 * @param mixed           $result  The result (null to run the callback of the route).
	 * @param WP_REST_Request $request The request.
	 * @param string          $route   The route.
	 *
	 * @return mixed
	 */
	public function start_via_rest( mixed $result, WP_REST_Request $request, string $route ): mixed {
		// bail if the request is answered already or this is not a route to rename, move or undo a rename.
		if ( null !== $result || 1 !== preg_match( '#^/media-file-renamer/v\d+/(rename|move|undo)$#', $route ) ) {
			return $result;
		}

		// get the attachment ID from the JSON body (or the parameters).
		$params        = (array) $request->get_json_params();
		$attachment_id = isset( $params['mediaId'] ) ? absint( $params['mediaId'] ) : absint( $request->get_param( 'mediaId' ) );

		// load the local files.
		if ( $attachment_id > 0 && current_user_can( 'edit_post', $attachment_id ) ) {
			$this->load_files( $attachment_id );
		}

		// return the result unchanged.
		return $result;
	}

	/**
	 * Load the local files of an exported image, if the plugin asks whether it may rename it.
	 *
	 * The arguments differ between versions of the plugin, so the attachment is searched in all of them.
	 *
	 * @param mixed ...$args The arguments of the filter "mfrh_allow_rename", the first is returned unchanged.
	 *
	 * @return mixed
	 */
	public function start_via_filter( mixed ...$args ): mixed {
		// get the attachment ID.
		$attachment_id = 0;
		foreach ( $args as $arg ) {
			if ( $arg instanceof WP_Post ) {
				$attachment_id = $arg->ID;
			} elseif ( is_array( $arg ) && isset( $arg['ID'] ) ) {
				$attachment_id = absint( $arg['ID'] );
			}
		}

		// load the local files.
		if ( $attachment_id > 0 ) {
			$this->load_files( $attachment_id );
		}

		// return the value unchanged.
		return $args[0] ?? true;
	}

	/**
	 * Load the local files of the given exported image, also of its sizes, as the plugin renames them, too.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private function load_files( int $attachment_id ): void {
		// get the object.
		$local_work_obj = Local_Work::get_instance();

		// bail if the files are in use already or could not be used.
		if ( $local_work_obj->is_open( $attachment_id ) || ! $local_work_obj->is_allowed( $attachment_id ) ) {
			return;
		}

		// load them.
		$local_work_obj->open( $attachment_id, 'rename', true, true );
	}
}
