<?php
/**
 * File to handle support for the plugin "Perfect Images" (formerly "WP Retina 2x").
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Work;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Object to handle support for this plugin.
 *
 * The plugin creates retina files ("image-300x200@2x.jpg") and files in modern formats ("image-300x200.jpg.webp")
 * beside the local files of an image, when its metadata are created. They are exported, deleted and restored together
 * with the image: the WebP and AVIF files as variants, which the proxy delivers, the retina files like backups, which
 * are not delivered (the plugin only uses local retina files).
 *
 * Its functions in its dashboard work with the attached file, which is the URL of an external file. They would read
 * from and write to the external source directly (e.g. replace the file in a local directory used as external source)
 * or change the metadata of the file. They are prevented for external files without local files. The regeneration
 * of the sizes of exported images uses their local files for this moment (see Local_Work).
 */
class PerfectImages extends Image_Optimizer_Base {

	/**
	 * The namespace of the REST API of the plugin.
	 */
	private const REST_NAMESPACE = '/wp-retina-2x/v1/';

	/**
	 * Instance of actual object.
	 *
	 * @var ?PerfectImages
	 */
	private static ?PerfectImages $instance = null;

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
	 * @return PerfectImages
	 */
	public static function get_instance(): PerfectImages {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the main files of the plugin, relative to the plugin directory.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'wp-retina-2x/wp-retina-2x.php', 'wp-retina-2x-pro/wp-retina-2x-pro.php' );
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * The export of uploads does not wait: the plugin creates its files while WordPress creates the metadata, before
	 * the upload is exported.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		// bail if they are added already.
		if ( false !== has_filter( 'efml_image_variants', array( $this, 'add_variants' ) ) ) {
			return;
		}

		// export, delete and restore the files of the plugin together with the image.
		add_filter( 'efml_image_variants', array( $this, 'add_variants' ), 10, 3 );

		// use the local files of an exported image, if the plugin creates its sizes again.
		add_action( 'wr2x_before_generate_thumbnails', array( $this, 'start_regeneration' ) );

		// prevent the functions of the plugin, which would work with the external source directly.
		add_filter( 'rest_dispatch_request', array( $this, 'prevent_for_external_files' ), 10, 3 );
	}

	/**
	 * Return the possible retina files of the given local file (path => name on the external source).
	 *
	 * They are exported like backups: beside the image, with their own name, but not delivered.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		// get the retina file ("image-300x200@2x.jpg").
		$info   = pathinfo( $path );
		$retina = trailingslashit( $info['dirname'] ?? '' ) . $info['filename'] . '@2x' . ( ! empty( $info['extension'] ) ? '.' . $info['extension'] : '' );

		// return it and its files in modern formats ("image-300x200@2x.jpg.webp").
		$paths = array();
		foreach ( array( $retina, $retina . '.webp', $retina . '.avif' ) as $retina_path ) {
			$paths[ $retina_path ] = wp_basename( $retina_path );
		}
		return $paths;
	}

	/**
	 * Load the local files of an exported image, before the plugin creates its sizes again.
	 *
	 * The plugin uses the attached file for it, which is the local file of the image then. The new sizes and the files
	 * of the plugin are exported by Local_Work.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function start_regeneration( mixed $attachment_id ): void {
		// bail if no ID is given.
		if ( ! is_numeric( $attachment_id ) ) {
			return;
		}

		// get the local work object.
		$attachment_id  = absint( $attachment_id );
		$local_work_obj = Local_Work::get_instance();

		// bail if the local files are in use already or could not be used.
		if ( $local_work_obj->is_open( $attachment_id ) || ! $local_work_obj->is_allowed( $attachment_id ) ) {
			return;
		}

		// load them.
		$local_work_obj->open( $attachment_id, 'regenerate' );
	}

	/**
	 * Prevent the functions of the plugin for external files, which would work with their external source directly.
	 *
	 * The plugin uses the attached file (the URL of an external file) to replace the image, to create its sizes in
	 * other ways (optimized or via AI), to create, delete or optimize its retina and WebP files. Only the creation of
	 * the sizes of exported images is possible, as their local files are used for it.
	 *
	 * Runs after the check of the permissions of the route. Returns an answer in the format of the plugin.
	 *
	 * @param mixed           $result  The result (null to run the callback of the route).
	 * @param WP_REST_Request $request The request.
	 * @param string          $route   The pattern of the route.
	 *
	 * @return mixed
	 */
	public function prevent_for_external_files( mixed $result, WP_REST_Request $request, string $route ): mixed {
		// bail if the request is answered already or this is not a route of the plugin, which works with the files.
		if ( null !== $result || 1 !== preg_match( '#^' . preg_quote( self::REST_NAMESPACE, '#' ) . '(replace|regenerate|optimize|build_retina|build_webp|delete_retina|delete_webp)/?$#', $request->get_route(), $matches ) ) {
			return $result;
		}

		// get the attachment ID from the JSON body or the parameters (the replacement is a form upload).
		$params        = (array) $request->get_json_params();
		$attachment_id = isset( $params['mediaId'] ) ? absint( $params['mediaId'] ) : absint( $request->get_param( 'mediaId' ) );

		// bail if no ID is given or the user is not allowed to edit the file (the plugin answers it; the route to
		// replace a file is open for everyone and checks the permission in its callback).
		if ( 0 === $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return $result;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file or it is saved locally: the plugin works with its local files.
		if ( ! $external_file_obj->is_valid() || $external_file_obj->is_locally_saved() ) {
			return $result;
		}

		// allow the creation of the sizes of exported images: their local files are used for it.
		if ( 'regenerate' === $matches[1] && empty( $params['optimized'] ) && empty( $params['ai_generate'] ) && Local_Work::get_instance()->is_allowed( $attachment_id ) ) {
			return $result;
		}

		// get the message.
		$message = __( 'This file is an external file. Perfect Images could not use this function for it, as it would change the file on its external source directly.', 'external-files-in-media-library' );
		if ( ! Local_Work::get_instance()->is_without_local_files( $attachment_id ) ) {
			$message .= ' ' . __( 'Use "Regenerate" to create its sizes and the retina and WebP files again.', 'external-files-in-media-library' );
		}

		// return the answer in the format of the plugin.
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			200
		);
	}
}
