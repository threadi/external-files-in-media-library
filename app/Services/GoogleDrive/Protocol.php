<?php
/**
 * File which handles the Google Drive support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception;
use JsonException;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * Return whether this protocol could be used for the given URL.
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
		if ( ! str_starts_with( $this->get_url(), GoogleDrive::get_instance()->get_url_mark() ) ) {
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
	 * Check the availability of a given URL.
	 *
	 * @return array<int,array<string,mixed>> List of files with its infos.
	 * @throws JsonException Could throw exception.
	 */
	public function get_url_infos(): array {
		// get the Google Drive object.
		$google_drive_obj = GoogleDrive::get_instance();

		// get the ID from the URL.
		$file_id = str_replace( $google_drive_obj->get_url_mark(), '', $this->get_url() );

		// if file id is "eflm-import-all" do this.
		if ( 'eflm-import-all' === $file_id ) {
			return $this->import_all_files();
		}

		// bail if no file id could be loaded.
		if ( empty( $file_id ) ) {
			// log event.
			Log::get_instance()->create( __( 'Given URL does not contain a file ID from Google Drive!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// check for duplicate.
		if ( $this->check_for_duplicate( $this->get_url() ) ) {
			Log::get_instance()->create( __( 'Given URL already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get the access token of the actual user.
		$access_token = $google_drive_obj->get_access_token();

		// bail if no access token is given.
		if ( empty( $access_token ) ) {
			// log event.
			Log::get_instance()->create( __( 'Access token missing to connect to Google Drive!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get the client.
		$client_obj = new Client( $access_token );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			// log event.
			Log::get_instance()->create( __( 'Google Drive client could not be initiated!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// connect to Google Drive.
		$service = new Drive( $client );

		// get the file.
		try {
			$response  = $service->files->get( $file_id, array( 'alt' => 'media' ) );
			$file_data = $service->files->get( $file_id, array( 'fields' => 'createdTime,name' ) );
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'Google Drive client could not download the requested file! Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', esc_url( $this->get_url() ), 'error' );

			return array();
		}

		// initialize basic array for file data.
		$results = array(
			'title'         => $file_data->getName(),
			'local'         => true,
			'url'           => $this->get_url(),
			'last-modified' => absint( strtotime( $file_data->getCreatedTime() ) ),
		);

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// set the file as tmp-file for import.
		$results['tmp-file'] = wp_tempnam();
		// and save the file there.
		$wp_filesystem->put_contents( $results['tmp-file'], $response->getBody()->getContents() );

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

	/**
	 * Import all files from Google Drive.
	 *
	 * @return array<int,array<string,mixed>>
	 * @throws JsonException Could throw exception.
	 */
	private function import_all_files(): array {
		// get the Google Drive object.
		$google_drive_obj = GoogleDrive::get_instance();

		// get the access token of the actual user.
		$access_token = $google_drive_obj->get_access_token();

		// bail if no access token is given.
		if ( empty( $access_token ) ) {
			// log event.
			Log::get_instance()->create( __( 'Access token missing to connect to Google Drive!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list and to nothing more.
			return array();
		}

		// get the client.
		$client_obj = new Client( $access_token );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			// log event.
			Log::get_instance()->create( __( 'Google Drive client could not be initiated!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// connect to Google Drive.
		$service = new Drive( $client );

		// collect the request query.
		$query = array(
			'fields'   => 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),shared,starred,sharedWithMeTime,description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,hasThumbnail,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken',
			'pageSize' => 1000,
			'orderBy'  => 'name_natural',
		);

		// get the files.
		try {
			$results = $service->files->listFiles( $query );
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'List of files could not be loaded from Google Drive. Error:', 'external-files-in-media-library' ), esc_url( $this->get_url() ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get list of files.
		$files = $results->getFiles();

		// bail if list is empty.
		if ( empty( $files ) ) {
			return array();
		}

		// create list of files to import.
		$list = array();

		// loop through the files and add them to the list.
		foreach ( $files as $file_obj ) {
			// bail if this is not a file object.
			if ( ! $file_obj instanceof DriveFile ) {
				continue;
			}

			// hide trashed files.
			if ( $file_obj->getTrashed() ) {
				continue;
			}

			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() . $file_obj->getId() ) ) {
				Log::get_instance()->create( __( 'Given URL already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() . $file_obj->getId() ), 'error' );

				continue;
			}

			// get the file.
			try {
				$response = $service->files->get( $file_obj->getId(), array( 'alt' => 'media' ) );
			} catch ( Exception $e ) {
				// log event.
				Log::get_instance()->create( __( 'Google Drive client could not download a requested file during mass-import! Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', esc_url( $this->get_url() . $file_obj->getId() ), 'error' );

				continue;
			}

			// initialize basic array for file data.
			$entry = array(
				'title'         => $file_obj->getName(),
				'local'         => true,
				'url'           => $this->get_url() . $file_obj->getId(),
				'last-modified' => absint( strtotime( $file_obj->getCreatedTime() ) ),
			);

			// get WP Filesystem-handler.
			$wp_filesystem = Helper::get_wp_filesystem();

			// set the file as tmp-file for import.
			$entry['tmp-file'] = wp_tempnam();
			// and save the file there.
			$wp_filesystem->put_contents( $entry['tmp-file'], $response->getBody()->getContents() );

			// set the file size.
			$entry['filesize'] = $wp_filesystem->size( $entry['tmp-file'] );

			// set the mime type.
			$mime_type          = wp_check_filetype( $entry['title'] );
			$entry['mime-type'] = $mime_type['type'];

			// add entry to the list.
			$list[] = $entry;
		}

		// return resulting list of files.
		return $list;
	}
}
