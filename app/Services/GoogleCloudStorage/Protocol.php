<?php
/**
 * File which handles the Google Cloud Storage support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleCloudStorage;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleCloudStorage;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not a Google Drive URL.
		if ( ! str_starts_with( $this->get_url(), GoogleCloudStorage::get_instance()->get_url_mark() ) ) {
			return false;
		}

		// return true to use this protocol.
		return true;
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

		// return true as Google Drive URLs are available.
		return true;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get main object.
		$google_cloud_storage_obj = GoogleCloudStorage::get_instance();

		// bail if disabled.
		if ( $google_cloud_storage_obj->is_disabled() ) {
			// log event.
			Log::get_instance()->create( __( 'Authorization JSON and/or bucket missing to connect to Google Cloud Storage!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get the name from the URL.
		$file_name = str_replace( $google_cloud_storage_obj->get_url_mark(), '', $this->get_url() );

		// bail if no file name could be loaded.
		if ( empty( $file_name ) ) {
			// log event.
			Log::get_instance()->create( __( 'Specified URL does not contain a file name from Google Cloud Storage!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// check for duplicate.
		if ( $this->check_for_duplicate( $this->get_url() ) ) {
			Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get storage object.
		$storage = $google_cloud_storage_obj->get_storage_object();

		// get our bucket as object.
		$bucket = $storage->bucket( $google_cloud_storage_obj->get_bucket_name() );

		// bail if bucket does not exist.
		if ( ! $bucket->exists() ) {
			return array();
		}

		// get the requested object.
		$file = $bucket->object( $file_name );

		// get the file infos.
		$file_data = $file->info();

		// initialize basic array for file data.
		$results = array(
			'title'         => basename( $file_data['name'] ),
			'local'         => true,
			'url'           => $this->get_url(),
			'last-modified' => absint( strtotime( $file_data['updated'] ) ),
		);

		// set the file as tmp-file for import.
		$results['tmp-file'] = wp_tempnam();

		// download file to this location.
		$file->downloadToFile( $results['tmp-file'] );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// set the file size.
		$results['filesize'] = $wp_filesystem->size( $results['tmp-file'] );

		// set the mime type.
		$mime_type            = wp_check_filetype( $results['title'] );
		$results['mime-type'] = $mime_type['type'];

		// return the resulting array as list of files (although it is only one).
		return array(
			$results,
		);
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
}
