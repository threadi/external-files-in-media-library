<?php
/**
 * File which handles the Google Drive support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
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
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>|bool> List of files with its infos.
	 * @throws JsonException Could throw exception.
	 */
	public function get_url_infos(): array {
		// get the URL.
		$url = $this->get_url();

		// get the Google Drive object.
		$google_drive_obj = GoogleDrive::get_instance();

		// get the access token of the actual user.
		$access_token = $google_drive_obj->get_access_token();

		// bail if no access token is given.
		if ( empty( $access_token ) ) {
			// log event.
			Log::get_instance()->create( __( 'Access token missing to connect to Google Drive!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get the ID from the URL.
		$file_id = str_replace( $google_drive_obj->get_url_mark(), '', $url );

		// if file id is "eflm-import-all" do this.
		if ( 'eflm-import-all' === $file_id ) {
			return $this->import_all_files();
		}

		// bail if no file id could be loaded.
		if ( empty( $file_id ) ) {
			// log event.
			Log::get_instance()->create( __( 'Specified URL does not contain a file ID from Google Drive!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// check for duplicate.
		if ( $this->check_for_duplicate( $url ) ) {
			Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// get the client.
		$client_obj = new Client( $access_token );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			// log event.
			Log::get_instance()->create( __( 'Google Drive client could not be initiated!', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// connect to Google Drive.
		$service = new Drive( $client );

		// get the file or directory.
		try {
			// get directory data.
			if ( str_ends_with( $file_id, '/' ) ) {
				// get complete directory.
				$directories = GoogleDrive::get_instance()->get_directory_listing( $file_id );

				// collect the results.
				$results = array();

				// get the user ID.
				$user_id = get_current_user_id();

				/**
				 * Run action if we have files to check via Google Drive-protocol.
				 *
				 * @since 5.0.0 Available since 5.0.0.
				 *
				 * @param string $url   The URL to import.
				 * @param array<int|string,mixed> $entries List of matches (the URLs).
				 */
				do_action( 'efml_google_drive_directory_import_files', $url, $directories );

				// loop through the result to get the requested directory with its files.
				foreach ( $directories as $directory => $settings ) {
					// bail if directory does not match and if it is not the main directory.
					if ( $directory !== $file_id && false === stripos( $file_id, GoogleDrive::get_instance()->get_directory() ) ) {
						continue;
					}

					/**
					 * Run action just before the file check via Google Drive-protocol.
					 *
					 * @since 5.0.0 Available since 5.0.0.
					 *
					 * @param int|string $directory   The URL to import.
					 */
					do_action( 'efml_google_drive_directory_import_file_check', $directory );

					// set counter for files which has been loaded from Google Drive.
					$loaded_files = 0;

					// save count of URLs.
					update_option( 'eml_import_url_max_' . $user_id, count( $settings['files'] ) );

					// add the files.
					foreach ( $settings['files'] as $file ) {
						// bail if this an AJAX-request and the file already exist in media library.
						if ( wp_doing_ajax() && Files::get_instance()->get_file_by_title( $file['title'] ) ) {
							continue;
						}

						// bail if limit for loaded files has been reached and this is an AJAX-request.
						if ( wp_doing_ajax() && $loaded_files > absint( get_option( 'eml_google_drive_limit', 10 ) ) ) {
							// set marker to load more.
							$results['load_more'] = true;
							continue;
						}

						// get the file data.
						$entry = $this->get_file_data( $service, $file['file'] );

						// bail if entry is empty.
						if ( empty( $entry ) ) {
							continue;
						}

						// update the counter.
						++$loaded_files;

						// add file to the list.
						$results[] = $entry;
					}
				}

				// return the resulting list of files to import.
				return $results;
			}

			// get the single file data.
			$entry = $this->get_file_data( $service, $file_id );

			// return the resulting array as list of files (although it is only one).
			return array(
				$entry,
			);
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'Google Drive client could not download the requested file! Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', esc_url( $this->get_url() ), 'error' );

			// return an empty list as we could not analyse the file.
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
			'fields'   => 'files(fileExtension,iconLink,id,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,parents,size,hasThumbnail,thumbnailLink),nextPageToken',
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

			// bail if file has no extension.
			if ( empty( $file_obj->getFileExtension() ) ) {
				continue;
			}

			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() . $file_obj->getId() ) ) {
				Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() . $file_obj->getId() ), 'error' );

				continue;
			}

			// get the file.
			try {
				$response = $service->files->get( $file_obj->getId(), array( 'alt' => 'media' ) );
			} catch ( Exception $e ) {
				// log event.
				Log::get_instance()->create( __( 'Google Drive client could not download a requested file during mass-import! Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code><br>' . __( 'Requested file:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $file_obj ) . '</code>', esc_url( $this->get_url() . $file_obj->getId() ), 'error' );

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
			try {
				$wp_filesystem->put_contents( $entry['tmp-file'], $response->getBody()->getContents() );
			} catch ( Error $e ) {
				// create the error entry.
				$error_obj = new Url_Result();
				/* translators: %1$s will be replaced by a URL. */
				$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_url() ) ) );
				$error_obj->set_url( $this->get_url() );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );

				// add log entry.
				Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error' );

				// do nothing more.
				return array();
			}

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

	/**
	 * Return the data-array for a single file from Google Drive.
	 *
	 * @param Drive  $service The Drive object.
	 * @param string $file_id The requested file ID.
	 *
	 * @return array<string,mixed>
	 * @throws Exception Could throw exception.
	 */
	private function get_file_data( Drive $service, string $file_id ): array {
		// get the Google Drive object.
		$google_drive_object = GoogleDrive::get_instance();

		// get file data.
		$file_obj  = $service->files->get( $file_id );
		$response  = $service->files->get( $file_id, array( 'alt' => 'media' ) );
		$file_data = $service->files->get( $file_id, array( 'fields' => 'createdTime,name' ) );

		// check if file could be saved local and set the URL.
		$local = ! $google_drive_object->is_file_public( $file_obj, $service );
		$url   = $google_drive_object->get_url_mark() . $file_id;
		if ( ! $local ) {
			$url = $google_drive_object->get_public_url_for_file_id( $file_id );
		}

		// get the actual user.
		$user_id = get_current_user_id();

		// update title for progress.
		/* translators: %1$s will be replaced by the URL which is imported. */
		update_option( 'eml_import_title_' . $user_id, sprintf( __( 'Get URL %1$s from Google Drive', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $file_data->getName() ) ) ) );

		// update counter for URLs.
		update_option( 'eml_import_url_count_' . $user_id, absint( get_option( 'eml_import_url_count_' . $user_id, 0 ) ) + 1 );

		// initialize basic array for file data.
		$entry = array(
			'title'         => $file_data->getName(),
			'local'         => $local,
			'url'           => $url,
			'last-modified' => absint( strtotime( $file_data->getCreatedTime() ) ),
		);

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// set the file as tmp-file for import.
		$entry['tmp-file'] = wp_tempnam();

		// and save the file there.
		try {
			$wp_filesystem->put_contents( $entry['tmp-file'], $response->getBody()->getContents() );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_url() ) ) );
			$error_obj->set_url( $this->get_url() );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error' );

			// do nothing more.
			return array();
		}

		// set the file size.
		$entry['filesize'] = $wp_filesystem->size( $entry['tmp-file'] );

		// set the mime type.
		$mime_type          = wp_check_filetype( $entry['title'] );
		$entry['mime-type'] = $mime_type['type'];

		// return the entry.
		return $entry;
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * Return whether URLs with this protocol are reachable via HTTP.
	 *
	 * This is not the availability of the URL.
	 *
	 * @return bool
	 */
	public function is_url_reachable(): bool {
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return 'Google Drive';
	}
}
