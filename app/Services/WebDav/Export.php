<?php
/**
 * File to handle export tasks for WebDAV.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\WebDav;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\WebDav;

/**
 * Object for export files to AWS S3.
 */
class Export extends Export_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Export|null
	 */
	private static ?Export $instance = null;

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
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// match the variables for hook documentation.
		$directory = $target;

		// get the main object.
		$webdav_object = WebDav::get_instance();

		// get the starting directory.
		$parse_url = wp_parse_url( $target );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			return false;
		}

		// get the fields.
		$fields = $credentials['fields'];

		// set the requested domain.
		$domain = $parse_url['scheme'] . '://' . $parse_url['host'];

		// create settings array for request.
		$settings = array(
			'baseUri'  => $domain,
			'userName' => $fields['login']['value'],
			'password' => $fields['password']['value'],
		);

		/**
		 * Filter the WebDAV settings.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<string,string> $settings The settings to use.
		 * @param string $domain The domain to use.
		 * @param string $directory The requested URL.
		 */
		$settings = apply_filters( 'efml_service_webdav_settings', $settings, $domain, $directory );

		// get a new client.
		$client = $webdav_object->get_client( $settings, $domain, $directory );

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not load file path for given attachment id.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the local WP_Filesystem.
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given file does not exist:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $file_path ) . '</code>', $target, 'error' );

			// do nothing more.
			return false;
		}

		// upload the file.
		$result = $client->request( 'PUT', $target, (string) $wp_filesystem_local->get_contents( $file_path ) );

		// list of allowed status codes.
		$status_codes = array( 201, 204 );
		/**
		 * Filter the statusCode after exporting a file to WebDav.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,int> $status_codes List of allowed status codes.
		 */
		if ( ! in_array( absint( $result['statusCode'] ), apply_filters( 'efml_service_webdav_status_codes', $status_codes ), true ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got unsupported status code from WebDav during export of a file:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $target, 'error' );

			// do nothing more.
			return false;
		}

		// return the given target URL.
		return $target;
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url           The URL to delete.
	 * @param array<string,mixed> $credentials   The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// map for hooks.
		$directory = $url;

		// get the main object.
		$webdav_object = WebDav::get_instance();

		// get the starting directory.
		$parse_url = wp_parse_url( $directory );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			return false;
		}

		// get the fields.
		$fields = $credentials['fields'];

		// set the requested domain.
		$domain = $parse_url['scheme'] . '://' . $parse_url['host'];

		// create settings array for request.
		$settings = array(
			'baseUri'  => $domain,
			'userName' => $fields['login']['value'],
			'password' => $fields['password']['value'],
		);

		/**
		 * Filter the WebDAV settings.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<string,string> $settings  The settings to use.
		 * @param string               $domain    The domain to use.
		 * @param string               $directory The requested URL.
		 */
		$settings = apply_filters( 'efml_service_webdav_settings', $settings, $domain, $directory );

		// get a new client.
		$client = $webdav_object->get_client( $settings, $domain, $directory );

		// delete the file.
		try {
			$result = $client->request( 'DELETE', $url );
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error occurred during request to delete a file on WebDav:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $url, 'error' );

			// return empty array to not load anything more.
			return false;
		}

		// list of allowed status codes.
		$status_codes = array( 201, 204 );
		/**
		 * Filter the statusCode after exporting a file to WebDav.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<int,int> $status_codes List of allowed status codes.
		 */
		if ( ! in_array( absint( $result['statusCode'] ), apply_filters( 'efml_service_webdav_status_codes', $status_codes ), true ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got unsupported status code from WebDav during deletion of a file:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $url, 'error' );

			// do nothing more.
			return false;
		}

		// return true as the file has been deleted.
		return true;
	}
}
