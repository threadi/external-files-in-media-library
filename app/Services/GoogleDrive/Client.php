<?php
/**
 * File which holds the object for any Google Client handling of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive;

defined( 'ABSPATH' ) || exit;

/**
 * Handle Google Client as object.
 *
 * @noinspection PhpUnused
 */
class Client {

	/**
	 * The access token.
	 *
	 * @var array
	 */
	private array $access_token;

	/**
	 * Initialize this object.
	 *
	 * @param array $access_token The access token to use.
	 */
	public function __construct( array $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Return the access token (which is an array).
	 *
	 * @return array
	 */
	private function get_access_token(): array {
		return $this->access_token;
	}

	/**
	 * Return the resulting Google Client object.
	 *
	 * @return \Google\Client|false
	 */
	public function get_client(): \Google\Client|false {
		// get Google Drive object.
		$google_drive_obj = GoogleDrive::get_instance();

		// get the access token.
		$access_token = $this->get_access_token();

		// bail if access token is empty.
		if ( empty( $access_token ) ) {
			return false;
		}

		// get the object.
		$client = new \Google\Client();

		// set the access token.
		$client->setAccessToken( $access_token );

		// refresh token if it has been expired.
		if ( $client->isAccessTokenExpired() ) {
			// log event.
			Log::get_instance()->create( __( 'Google OAuth token expired. Requesting a new one now.', 'external-files-in-media-library' ), '', 'info', 2 );

			// add the client id.
			$client->setClientId( $google_drive_obj->get_client_id() );

			// get new token.
			$access_token = $client->fetchAccessTokenWithRefreshToken( $client->getAccessToken() );

			// bail if access token contains an error.
			if ( ! empty( $access_token['error'] ) ) {
				// remove the token.
				$google_drive_obj->delete_access_token();

				// show error on CLI.
				Helper::is_cli() ? \WP_CLI::error( wp_json_encode( $access_token ) ) : '';

				// log event.
				Log::get_instance()->create( __( 'Got error from Google for requested new token:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $access_token ) . '</code>', '', 'info', 2 );

				// return false to break the process.
				return false;
			}

			// save it.
			$google_drive_obj->set_access_token( $access_token );

			// set it in object.
			$client->setAccessToken( $access_token );
		}

		// return the resulting client.
		return $client;
	}
}
