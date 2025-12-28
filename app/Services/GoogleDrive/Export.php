<?php
/**
 * File to handle export tasks for Google Drive.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Object for export files to GoogleDrive.
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
		// get the main object.
		$google_drive_object = GoogleDrive::get_instance();

		// set the fields on it.
		$google_drive_object->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the client.
		$client_obj = new Client( $google_drive_object->get_access_token() );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			return false;
		}

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if file path could not be loaded.
		if( ! is_string( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not load file path for given attachment id.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the file mime type.
		$mime_type = get_post_mime_type( $attachment_id );

		// bail if mime type could not be loaded.
		if( ! is_string( $mime_type ) ) {
			return false;
		}

		// set defer for the client to accept big files.
		$client->setDefer( true );

		// connect to Google Drive.
		$service = new Drive( $client );

		// set the query for the file metadata.
		$query = array(
			'name' => basename( $file_path ),
		);

		// add the directory, if set.
		if ( ! empty( $credentials['directory'] ) ) {
			$query['parents'] = array( str_replace( '/', '', $credentials['directory'] ) );
		}

		// set the file metadata.
		$file_meta_data = new DriveFile( $query );

		// create the file per request.
		try {
			$request = $service->files->create( $file_meta_data, array( 'fields' => 'id' ) );

			// set chunk size for upload.
			$chunk_size = 1024 * 1024;
			$media      = new MediaFileUpload(
				$client,
				$request,
				$mime_type,
				'',
				true,
				$chunk_size
			);

			// set the file size.
			$media->setFileSize( absint( filesize( $file_path ) ) );

			/**
			 * Transfer the file per chunk.
			 *
			 * Hint: This is not compatible with WCS, but we need to read chunks here, which is not possible with WP_Filesystem (at present).
			 */
			$result = null;
			$handle = fopen( $file_path, 'rb' );
			if( ! $handle ) {
				return false;
			}
			while ( ! feof( $handle ) ) {
				$chunk  = fread( $handle, $chunk_size );
				$result = $media->nextChunk( $chunk );
			}
			fclose( $handle );

			// disable defer for the client.
			$client->setDefer( false );

			// bail if result code is not 200.
			if ( 200 !== $media->getHttpResultCode() ) {
				// log this event.
				Log::get_instance()->create( __( 'File could not be uploaded to Google Drive. HTTP result code was:', 'external-files-in-media-library' ) . ' <code>' . $media->getHttpResultCode() . '</code>', $target, 'error' );

				// do nothing more.
				return false;
			}

			// bail if result is not a DriveFile.
			if ( ! $result instanceof DriveFile ) {
				return false;
			}

			// get the file ID.
			$file_id = $result->id;

			// save the used file id.
			update_post_meta( $attachment_id, 'efml_google_drive_file_id', $file_id );

			// return the public URL for this file.
			return $google_drive_object->get_public_url_for_file_id( $file_id );
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'File could not be uploaded to Google Drive. Error:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $target, 'error' );

			// return false as we do not get anything.
			return false;
		}
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
		// get the main object.
		$google_drive_object = GoogleDrive::get_instance();

		// set the fields on it.
		$google_drive_object->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the client.
		$client_obj = new Client( $google_drive_object->get_access_token() );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			return false;
		}

		// get the file ID.
		$file_id = get_post_meta( $attachment_id, 'efml_google_drive_file_id', true );

		// bail if file ID is not set.
		if ( empty( $file_id ) ) {
			return false;
		}

		// connect to Google Drive.
		$service = new Drive( $client );

		// delete the file.
		try {
			$service->files->delete( $file_id );

			// return true for success.
			return true;
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'File could not be delete from Google Drive. Error:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $url, 'error' );

			// return false as we do not get anything.
			return false;
		}
	}
}
