<?php
/**
 * File which handles the AWS S3 support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\S3;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Aws\S3\Exception\S3Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\S3;

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
		// bail if this is not an AWS S3 URL.
		if ( ! str_starts_with( $this->get_url(), S3::get_instance()->get_url_mark( '' ) ) ) {
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

		// return true as AWS S3 URLs are available.
		return true;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>|bool> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get fields.
		$fields = $this->get_fields();

		// remove our marker from the URL.
		$url = str_replace( S3::get_instance()->get_url_mark( $fields['bucket']['value'] ), '', $this->get_url() );

		// get our own S3 object.
		$s3 = S3::get_instance();
		$s3->set_fields( $fields );

		// get the S3Client.
		$s3_client = $s3->get_s3_client();

		// get list of directories and files in given bucket.
		try {
			// get mime type.
			$mime_type = wp_check_filetype( basename( $url ) );

			// list of files.
			$results = array();

			// get the WP_Filesystem.
			$wp_filesystem = Helper::get_wp_filesystem();

			// if mime type check results with false values, it is a directory.
			if ( ! empty( $mime_type ) && false === $mime_type['ext'] ) { // @phpstan-ignore empty.variable
				// use the directory listing to get the dirs and files.
				$files = $s3->get_directory_listing( '/' );

				/**
				 * Run action if we have files to check via AWS S3-protocol.
				 *
				 * @since 5.0.0 Available since 5.0.0.
				 *
				 * @param string $url   The URL to import.
				 * @param array<int|string,mixed> $files List of matches (the URLs).
				 */
				do_action( 'eml_s3_directory_import_files', $url, $files );

				// loop through all dirs and get infos about its files.
				foreach ( $files as $dir => $dir_data ) {
					$dir = (string) $dir;
					/**
					 * Run action just before the file check via AWS S3-protocol.
					 *
					 * @since 5.0.0 Available since 5.0.0.
					 *
					 * @param string $file_url   The URL to import.
					 */
					do_action( 'eml_s3_directory_import_file_check', $dir );

					// bail if files is empty.
					if ( empty( $dir_data['files'] ) ) {
						continue;
					}

					// set counter for files which has been loaded from Google Drive.
					$loaded_files = 0;

					// add each file to the list.
					foreach ( $dir_data['files'] as $file ) {
						// bail if this an AJAX-request and the file already exist in media library.
						if ( wp_doing_ajax() && Files::get_instance()->get_file_by_title( $file['title'] ) ) {
							continue;
						}

						// bail if limit for loaded files has been reached and this is an AJAX-request.
						if ( wp_doing_ajax() && $loaded_files > absint( get_option( 'eml_s3_import_limit', 10 ) ) ) {
							// set marker to load more.
							$results['load_more'] = true;
							continue;
						}

						// create the array for the file data.
						$entry = array(
							'title'         => basename( $file['file'] ),
							'local'         => ! $s3->is_file_public_available( $file['s3_key'], $s3_client ),
							'url'           => $file['file'],
							'last-modified' => $file['last-modified'],
							'filesize'      => $file['filesize'],
							'mime-type'     => $file['mime-type'],
						);

						// get mime type.
						$mime_type = wp_check_filetype( $file['title'] );

						// get the tmp file name.
						$tmp_file_name = wp_tempnam();

						// generate tmp file path.
						$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

						// delete the tmp file name.
						$wp_filesystem->delete( $tmp_file_name );

						// set query for the file and save it in tmp dir.
						$query = array(
							'Bucket' => $this->get_fields()['bucket']['value'],
							'Key'    => str_replace( S3::get_instance()->get_url_mark( $this->get_fields()['bucket']['value'] ), '', $file['file'] ),
							'SaveAs' => $tmp_file,
						);

						// try to load the requested bucket to save the tmp file.
						$s3_client->getObject( $query );

						// add tmp file to the list.
						$entry['tmp-file'] = $tmp_file;

						// update the counter.
						++$loaded_files;

						// add entry to the list of files.
						$results[] = $entry;
					}
				}
			} else {
				// get tmp file name.
				$tmp_file_name = wp_tempnam();

				// generate tmp file path.
				$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

				// delete the tmp file.
				$wp_filesystem->delete( $tmp_file_name );

				// set query for the file and save it in tmp dir.
				$query = array(
					'Bucket' => $this->get_fields()['bucket']['value'],
					'Key'    => $url,
					'SaveAs' => $tmp_file,
				);

				// try to load the requested bucket.
				$result = $s3_client->getObject( $query );

				// check the public permissions.
				$public_access_allowed = $s3->is_file_public_available( $url, $s3_client );
				$url                   = $this->get_url();
				if ( $public_access_allowed ) {
					$url = $result->get( '@metadata' )['effectiveUri'];
				}

				// create the array for the file data.
				$entry = array(
					'title'         => basename( $url ),
					'local'         => ! $public_access_allowed,
					'url'           => $url,
					'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $result->get( 'LastModified' )->format( 'U' ) ) ) ),
					'filesize'      => $result->get( 'ContentLength' ),
					'mime-type'     => $result->get( 'ContentType' ),
					'tmp-file'      => $tmp_file,
				);

				// add entry to the list of files.
				$results[] = $entry;
			}
			return $results;
		} catch ( S3Exception $e ) {
			Log::get_instance()->create( __( 'Error during request of AWS S3 file. See the logs for details.', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error', 0, Import::get_instance()->get_identified() );
			Log::get_instance()->create( __( 'Error during request of AWS S3 file:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error' );
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
		return S3::get_instance()->get_label();
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
}
