<?php
/**
 * File which handles the WebDAV support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\WebDav;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\WebDav;
use Sabre\HTTP\ClientHttpException;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * Internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'webdav';

	/**
	 * List of supported tcp protocols with their ports.
	 *
	 * @var array<string,int>
	 */
	protected array $tcp_protocols = array(
		'http'  => 80,
		'https' => 443,
	);

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol by compare the protocol handler
	 * and the start of the given URL with the supported protocols of this protocol handler
	 * (e.g. 'http' or 'ftp').
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// get listing_base_object_name from request.
		$service_name = filter_input( INPUT_POST, 'listing_base_object_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// try to get service name from other request param, if it is not yes set.
		if ( is_null( $service_name ) ) {
			$service_name = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		// try to get service name from other request param, if it is not yes set.
		if ( is_null( $service_name ) ) {
			$service_name = filter_input( INPUT_POST, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		// check for file object if service is still null.
		if( is_null( $service_name ) ) {
			// TODO klären!
			//$service_name = $this->get_name();
		}

		// return result of comparing the given service name with ours.
		return WebDav::get_instance()->get_name() === $service_name;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// bail if empty URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// return true as DropBox URLs are available.
		return true;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		$directory = $this->get_url();

		// get the staring directory.
		$parse_url = wp_parse_url( $this->get_url() );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			// create the error entry.
			$error_obj = new Url_Result();
			$error_obj->set_result_text( __( 'Got faulty URL.', 'external-files-in-media-library' ) );
			$error_obj->set_url( $this->get_url() );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// do nothing more.
			return array();
		}

		// set the requested domain.
		$domain = $parse_url['scheme'] . '://' . $parse_url['host'];

		// get the path.
		$path = isset( $parse_url['path'] ) ? $parse_url['path'] : '';

		/**
		 * Filter the WebDAV path.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param string $path The path to use after the given domain.
		 * @param string $login The login to use.
		 * @param string $domain The domain to use.
		 * @param string $directory The requested URL.
		 */
		$path = apply_filters( 'efml_service_webdav_path', $path, $this->get_login(), $domain, $directory );

		// create settings array for request.
		$settings = array(
			'baseUri'  => $domain . $path,
			'userName' => $this->get_login(),
			'password' => $this->get_password(),
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
		$client = WebDav::get_instance()->get_client( $settings, $domain, $directory );

		// get the directory listing for the given path from the external WebDAV.
		try {
			// get the object by direct request.
			$directory_list = $client->propFind( '', array(), 1 );

			// bail if returned array contains only 1 entry and index with path does not exist.
			if ( 1 === count( $directory_list ) && empty( $directory_list[ $path ] ) ) {
				// create the error entry.
				$error_obj = new Url_Result();
				$error_obj->set_result_text( __( 'Got empty response from WebDAV for given file.', 'external-files-in-media-library' ) );
				$error_obj->set_url( $this->get_url() );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );

				// do nothing more.
				return array();
			}

			// collect the files.
			$listing = array();

			// set the used domain.
			$url = $domain . $path;

			/**
			 * Run action if we have files to check via WebDav-protocol.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param string $url   The URL to import.
			 * @param array<string> $directory_list List of matches (the URLs).
			 */
			do_action( 'eml_webdav_directory_import_files', $url, $directory_list );

			// loop through the results and add each to the response.
			foreach ( $directory_list as $file_name => $setting ) {
				/**
				 * Run action just before the file check via WebDAV-protocol.
				 *
				 * @since 5.0.0 Available since 5.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 */
				do_action( 'eml_webdav_directory_import_file_check', $domain . $file_name );

				// bail if resource type is not null.
				if ( ! is_null( $setting['{DAV:}resourcetype'] ) ) {
					continue;
				}

				// initialize basic array for file data.
				$results = array(
					'title'         => basename( $file_name ),
					'local'         => true,
					'url'           => $domain . $file_name,
					'last-modified' => absint( strtotime( $setting['{DAV:}getlastmodified'] ) ),
				);

				// get mime type.
				$mime_type = wp_check_filetype( $results['title'] );

				// set the file size.
				$results['filesize'] = absint( $setting['{DAV:}getcontentlength'] );

				// set the mime type.
				$results['mime-type'] = $mime_type['type'];

				// set the file as tmp-file for import.
				$results['tmp-file'] = wp_tempnam();

				// get WP Filesystem-handler.
				$wp_filesystem = Helper::get_wp_filesystem();

				// set settings for new sabre-client object.
				$settings = array(
					'baseUri'  => $domain . $file_name,
					'userName' => $this->get_login(),
					'password' => $this->get_password(),
				);

				// get a new client.
				$client = WebDav::get_instance()->get_client( $settings, $domain, $directory );

				// get the file data.
				$file_data = $client->request( 'GET' );

				// save the content.
				$wp_filesystem->put_contents( $results['tmp-file'], $file_data['body'] );

				// add file to the list.
				$listing[] = $results;
			}

			// return the resulting array as list of files (although it is only one).
			return $listing;
		} catch ( ClientHttpException $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			$error_obj->set_result_text( __( 'Error occurred during requesting this file.', 'external-files-in-media-library' ) );
			$error_obj->set_url( $this->get_url() );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code><br><br>' . __( 'Domain:', 'external-files-in-media-library' ) . ' <code>' . $domain . '</code><br><br>' . __( 'Path:', 'external-files-in-media-library' ) . ' <code>' . $path . '</code><br><br>' . __( 'Settings:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $settings ) . '</code>', $directory, 'error' );

			// do nothing more.
			return array();
		}
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return WebDav::get_instance()->get_label();
	}
}
