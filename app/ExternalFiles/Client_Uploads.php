<?php
/**
 * File to handle uploads whose image sizes are created in the browser (client-side media processing since WordPress 7.1).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Error;
use WP_REST_Request;

/**
 * Object to handle uploads with client-side media processing.
 *
 * Since WordPress 7.1 the block editor creates the sizes of uploaded images in the browser: it uploads the original
 * image without sizes, sends each size afterward via "/wp/v2/media/<id>/sideload" and saves all sizes via
 * "/wp/v2/media/<id>/finalize" in the metadata of the attachment.
 *
 * The export of such an upload must wait until the sizes have been finalized, otherwise only the original image would
 * be exported and the sizes would be saved beside the URL of the exported file, i.e. not in the uploads directory.
 * The export is therefore started by the finalization. If the browser does not finalize the upload (e.g. if it has
 * been closed), the export starts after one hour via WP-Cron.
 *
 * Sizes for files which are already external files are rejected, as WordPress would save them in a directory named
 * like the URL of the external file.
 */
class Client_Uploads {
	/**
	 * Name of the post meta which marks an upload waiting for its finalization.
	 *
	 * @var string
	 */
	public const META = 'efml_export_after_finalize';

	/**
	 * Name of the WP-Cron hook which exports not finalized uploads.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'efml_export_unfinalized_upload';

	/**
	 * Seconds to wait for the finalization of an upload before it is exported without it.
	 *
	 * @var int
	 */
	private const WAIT = HOUR_IN_SECONDS;

	/**
	 * Marker whether the actual request uploads a file whose sizes are created in the browser.
	 *
	 * @var bool
	 */
	private bool $client_upload = false;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Client_Uploads
	 */
	private static ?Client_Uploads $instance = null;

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
	 * @return Client_Uploads
	 */
	public static function get_instance(): Client_Uploads {
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
		add_filter( 'rest_request_before_callbacks', array( $this, 'check_rest_request' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'reset_marker' ) );
		add_action( self::CRON_HOOK, array( $this, 'export_unfinalized_upload' ) );
	}

	/**
	 * Check the REST API requests for media uploads with sizes created in the browser.
	 *
	 * - Marks uploads without sizes created on the server (parameter "generate_sub_sizes" is false).
	 * - Rejects sizes for files which are already external files.
	 *
	 * @param mixed           $response The response (a WP_Error if the request failed already).
	 * @param mixed           $handler  The handler of the route.
	 * @param WP_REST_Request $request  The request.
	 *
	 * @return mixed
	 */
	public function check_rest_request( mixed $response, mixed $handler, WP_REST_Request $request ): mixed {
		// bail if the request has failed already.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// bail if this is not a POST request.
		if ( 'POST' !== $request->get_method() ) {
			return $response;
		}

		// bail if client-side media processing is not available (before WordPress 7.1 or disabled).
		if ( ! $this->is_client_side_processing_enabled() ) {
			return $response;
		}

		// get the route.
		$route = $request->get_route();

		// mark uploads whose sizes are created in the browser (exactly as WordPress checks it).
		if ( '/wp/v2/media' === $route && false === $request['generate_sub_sizes'] ) {
			$this->client_upload = true;
			return $response;
		}

		// bail if this is not a sideload of a size.
		if ( 1 !== preg_match( '#^/wp/v2/media/(\d+)/sideload$#', $route, $matches ) ) {
			return $response;
		}

		// bail if the file is not an external file.
		if ( ! Files::get_instance()->get_file( (int) $matches[1] )->is_valid() ) {
			return $response;
		}

		// reject the size, as WordPress would save it beside the URL of the external file.
		return new WP_Error(
			'efml_sideload_on_external_file',
			__( 'Sizes could not be added to external files.', 'external-files-in-media-library' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Reset the marker for uploads after each REST API request, as it is only valid for the upload request itself.
	 *
	 * @param mixed $response The response.
	 *
	 * @return mixed
	 */
	public function reset_marker( mixed $response ): mixed {
		$this->client_upload = false;
		return $response;
	}

	/**
	 * Return whether client-side media processing is enabled (available since WordPress 7.1).
	 *
	 * @return bool
	 */
	private function is_client_side_processing_enabled(): bool {
		return function_exists( 'wp_is_client_side_media_processing_enabled' ) && wp_is_client_side_media_processing_enabled();
	}

	/**
	 * Return whether the actual request uploads a file whose sizes are created in the browser.
	 *
	 * @return bool
	 */
	public function is_client_upload(): bool {
		return $this->client_upload;
	}

	/**
	 * Mark the given attachment as waiting for its finalization and plan its export without it.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function wait_for_finalization( int $attachment_id ): void {
		// save the marker.
		update_post_meta( $attachment_id, self::META, time() );

		// plan the export, if the upload is not finalized.
		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $attachment_id ) ) ) {
			wp_schedule_single_event( time() + self::WAIT, self::CRON_HOOK, array( $attachment_id ) );
		}

		// log this event.
		Log::get_instance()->create( __( 'The export of the uploaded file waits until its sizes have been created in the browser.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 2 );
	}

	/**
	 * Return whether the given attachment waits for its finalization.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 * @phpstan-impure
	 */
	public function is_waiting( int $attachment_id ): bool {
		return absint( get_post_meta( $attachment_id, self::META, true ) ) > 0;
	}

	/**
	 * Remove the marker of the given attachment and its planned export.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end_waiting( int $attachment_id ): void {
		// bail if the attachment does not wait.
		if ( ! $this->is_waiting( $attachment_id ) ) {
			return;
		}

		// remove the marker.
		delete_post_meta( $attachment_id, self::META );

		// remove the planned export.
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $attachment_id ) );
	}

	/**
	 * Export an upload which has not been finalized in the browser (via WP-Cron).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function export_unfinalized_upload( int $attachment_id ): void {
		// bail if the attachment does not wait anymore (e.g. it has been finalized or deleted).
		if ( ! $this->is_waiting( $attachment_id ) ) {
			return;
		}

		// bail if the export is not enabled anymore (the file stays local).
		if ( ! Export::get_instance()->is_enabled() ) {
			$this->end_waiting( $attachment_id );
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'The sizes of the uploaded file have not been finalized in the browser. They are created on the server before the file is exported.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );

		// create the missing sizes of images on the server, which runs the export via the hook "wp_generate_attachment_metadata".
		if ( wp_attachment_is_image( $attachment_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php'; // @phpstan-ignore requireOnce.fileNotFound
			wp_update_image_subsizes( $attachment_id );
		}

		// bail if the export has been run (the marker has been removed by it).
		if ( ! $this->is_waiting( $attachment_id ) ) {
			return;
		}

		// get the actual metadata.
		$metadata = wp_get_attachment_metadata( $attachment_id, true );

		// run the export like after an upload (which removes the marker).
		$metadata = Export::get_instance()->export_file_by_upload( $metadata, $attachment_id );

		// save the resulting metadata, as WordPress does it after an upload.
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * Remove the data during the uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		wp_unschedule_hook( self::CRON_HOOK );
		delete_post_meta_by_key( self::META );
	}
}
