<?php
/**
 * File to handle export tasks for Dropbox.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\DropBox;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use Spatie\Dropbox\Client;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

/**
 * Object for export files to Dropbox.
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
		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $target );

		// bail if the detected protocol handler is not our own Protocol.
		if ( ! $protocol_handler_obj instanceof Protocol ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a Dropbox-URL.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the main object.
		$dropbox_obj = DropBox::get_instance();

		// set the credentials.
		$dropbox_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the file path.
		$file_path = wp_get_original_image_path( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			return false;
		}

		// get the local WP_Filesystem.
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			return false;
		}

		// get the target file name.
		$dropbox_target = str_replace( 'DropBox', '', $target );

		// define the chunk size for the transfer parts.
		$chunk_size = 8 * 1024 * 1024;

		// open the file handle.
		$handle = fopen( $file_path, 'rb' );

		// bail if handle could not be loaded.
		if ( ! $handle ) {
			return false;
		}

		/**
		 * Transfer the file per chunk.
		 *
		 * Hint: This is not compatible with WCS, but we need to read chunks here, which is not possible with WP_Filesystem (at present).
		 */

		/**
		 * Step 1: Start the session to upload the file.
		 */
		$first_chunk = fread( $handle, $chunk_size );

		// bail if chunk could not be loaded.
		if ( ! $first_chunk ) {
			return false;
		}

		// create the first request.
		$start = $this->dropbox_curl_request(
			'https://content.dropboxapi.com/2/files/upload_session/start',
			array(
				'Authorization: Bearer ' . $dropbox_obj->get_access_token(),
				'Content-Type: application/octet-stream',
				'Dropbox-API-Arg: ' . wp_json_encode( array( 'close' => false ), JSON_UNESCAPED_SLASHES ),
			),
			$first_chunk
		);

		// bail if start is not an array.
		if ( ! is_array( $start ) ) {
			return false;
		}

		// get the session ID.
		$session_id = $start['session_id'];

		// get the offset.
		$offset = strlen( $first_chunk );

		/**
		 * Step 2: add the chunks of the file in session.
		 */
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, $chunk_size );

			// bail if chunk could not be loaded.
			if ( ! $chunk ) {
				continue;
			}

			// send request to upload this part.
			$result = $this->dropbox_curl_request(
				'https://content.dropboxapi.com/2/files/upload_session/append_v2',
				array(
					'Authorization: Bearer ' . $dropbox_obj->get_access_token(),
					'Content-Type: application/octet-stream',
					'Dropbox-API-Arg: ' . wp_json_encode(
						array(
							'cursor' => array(
								'session_id' => $session_id,
								'offset'     => $offset,
							),
							'close'  => false,
						),
						JSON_UNESCAPED_SLASHES
					),
				),
				$chunk
			);

			// bail if result is not an array.
			if ( ! is_array( $result ) ) {
				return false;
			}

			// add offset.
			$offset += strlen( $chunk );
		}

		// close the file handle.
		fclose( $handle );

		/**
		 * Step 3: save the session content as file in the Dropbox.
		 */
		$result = $this->dropbox_curl_request(
			'https://content.dropboxapi.com/2/files/upload_session/finish',
			array(
				'Authorization: Bearer ' . $dropbox_obj->get_access_token(),
				'Content-Type: application/octet-stream',
				'Dropbox-API-Arg: ' . wp_json_encode(
					array(
						'cursor' => array(
							'session_id' => $session_id,
							'offset'     => $offset,
						),
						'commit' => array(
							'path'       => $dropbox_target,
							'mode'       => 'add',
							'autorename' => true,
							'mute'       => false,
						),
					),
					JSON_UNESCAPED_SLASHES
				),
			)
		);

		// bail if result is not an array.
		if ( ! is_array( $result ) ) {
			return false;
		}

		// save the Dropbox path for this file.
		update_post_meta( $attachment_id, 'efml_dropbox_path', $result['path_lower'] );

		// get the shared files to get the public URL of the uploaded file.
		$client = new Client( $dropbox_obj->get_access_token() );

		// create the shared link for this file.
		try {
			$shared_file_data = $client->createSharedLinkWithSettings( $dropbox_target );

			// if we got share file data, use the URL from there.
			if ( ! empty( $shared_file_data ) ) {
				$url = add_query_arg(
					array(
						'raw' => 1,
					),
					$shared_file_data['url']
				);

				// get the header of this URL to get the forward URL Dropbox is using.
				$response    = wp_remote_head( $url );
				$headers_obj = wp_remote_retrieve_headers( $response );

				// bail if header results not in the expected object.
				if ( ! $headers_obj instanceof CaseInsensitiveDictionary ) {
					return false;
				}

				// get the headers.
				$header = $headers_obj->getAll();

				// return the generic URL if no location returned.
				if ( empty( $header['location'] ) ) {
					return $url;
				}

				// return the location we got.
				return $header['location'];
			}
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error occurred during request for public Dropbox URL of a given file:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $target, 'error' );

			// return empty array to not load anything more.
			return false;
		}

		// otherwise try to find the URL in all shared links.
		$url          = '';
		$shared_files = $client->listSharedLinks();

		// search for the one we uploaded.
		foreach ( $shared_files as $shared_file ) {
			// bail if file id does not match.
			if ( $shared_file['id'] !== $result['id'] ) {
				continue;
			}

			// get the file URL.
			$url = $shared_file['url'];
		}

		// if no URL could be found, return false.
		if ( empty( $url ) ) {
			return false;
		}

		// return the URL for this file.
		return $url;
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url The URL to delete.
	 * @param array<string,mixed> $credentials The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $url );

		// bail if the detected protocol handler is not our own Protocol.
		if ( ! $protocol_handler_obj instanceof Protocol ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a Dropbox-URL.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// get the Dropbox path for this file.
		$dropbox_path = get_post_meta( $attachment_id, 'efml_dropbox_path', true );

		// bail if path is not set.
		if ( empty( $dropbox_path ) ) {
			return false;
		}

		// get the main object.
		$dropbox_obj = DropBox::get_instance();

		// set the fields.
		$dropbox_obj->set_fields( $credentials['fields'] );

		// get the shared files to get the public URL of the uploaded file.
		try {
			$client = new Client( $dropbox_obj->get_access_token() );

			// delete the file.
			$client->delete( $dropbox_path );
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error occurred during request to delete a file from Dropbox:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $url, 'error' );

			// return empty array to not load anything more.
			return false;
		}

		// return true as file has been deleted.
		return true;
	}

	/**
	 * Send request to Dropbox API using curl.
	 *
	 * Hint: The usage of curl is not compatible with WCS, but we need to handle chunks here, which is not possible with WP_Filesystem (at present).
	 *
	 * @param string           $url The Dropbox API URL to use.
	 * @param array<int,mixed> $headers The headers to use.
	 * @param string           $body The box to use.
	 *
	 * @return array<string,mixed>|bool
	 */
	private function dropbox_curl_request( string $url, array $headers, string $body = '' ): array|bool {
		// create the request.
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		// send the request.
		$response = curl_exec( $ch );
		$err      = curl_error( $ch );
		$status   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		// close the connection.
		curl_close( $ch );

		// bail on error.
		if ( $err ) {
			Log::get_instance()->create( __( 'Error during request to DropBox:', 'external-files-in-media-library' ) . ' <code>' . $err . '</code>', '', 'error' );
			return false;
		}

		// bail if status is something >= 400.
		if ( $status >= 400 ) {
			Log::get_instance()->create( __( 'Error during request to DropBox:', 'external-files-in-media-library' ) . ' <code>' . $response . '</code>', '', 'error' );
			return false;
		}

		// bail if response is not a string.
		if ( ! is_string( $response ) ) {
			Log::get_instance()->create( __( 'Result is not a string after request to DropBox:', 'external-files-in-media-library' ) . ' <code>' . $response . '</code>', '', 'error' );
			return false;
		}

		// return the resulting response as array.
		return json_decode( $response, true );
	}
}
