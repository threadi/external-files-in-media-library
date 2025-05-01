<?php
/**
 * File which holds the object for any Google Client handling of this plugin.
 *
 * This object also handles the refreshing of expired tokens. This is only processed if the object is requested.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\GoogleDrive;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive;
use JsonException;

/**
 * Handle Google Client as object.
 *
 * @noinspection PhpUnused
 */
class Client {

	/**
	 * The access token.
	 *
	 * @var array<string>
	 */
	private array $access_token;

	/**
	 * If token has been refreshed.
	 *
	 * @var bool
	 */
	private bool $token_refreshed = false;

	/**
	 * Initialize this object.
	 *
	 * @param array<string> $access_token The access token to use.
	 */
	public function __construct( array $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Return the access token (which is an array).
	 *
	 * @return array<string>
	 */
	private function get_access_token(): array {
		return $this->access_token;
	}

	/**
	 * Return the resulting Google Client object.
	 *
	 * @param int $user_id The WordPress user ID (optional).
	 *
	 * @return \Google\Client|false
	 * @throws JsonException Could throw exception.
	 */
	public function get_client( int $user_id = 0 ): \Google\Client|false {
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

			// get new token via our own endpoint.
			$access_token = $google_drive_obj->get_refreshed_token( $client );

			// get access token as JSON for logging.
			$access_token_json = wp_json_encode( $access_token );
			if ( ! $access_token_json ) {
				$access_token_json = '';
			}

			// bail if no access token could be loaded.
			if ( empty( $access_token ) ) {
				// show error on CLI.
				Helper::is_cli() ? \WP_CLI::error( $access_token_json ) : '';

				// log event.
				Log::get_instance()->create( __( 'Got empty response for requested new Google OAuth token!', 'external-files-in-media-library' ), '', 'error' );

				// return false to break the process.
				return false;
			}

			// bail if access token contains an error.
			if ( ! empty( $access_token['error'] ) ) {
				// remove the token.
				$google_drive_obj->delete_access_token();

				// show error on CLI.
				Helper::is_cli() ? \WP_CLI::error( $access_token_json ) : '';

				// log event.
				Log::get_instance()->create( __( 'Got error from Google for requested new OAuth token:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $access_token ) . '</code>', '', 'error' );

				// return false to break the process.
				return false;
			}

			// save it.
			$google_drive_obj->set_access_token( $access_token, $user_id );

			// set it in object.
			$client->setAccessToken( $access_token );

			// mark as refreshed.
			$this->set_as_refreshed();
		}

		// return the resulting client.
		return $client;
	}

	/**
	 * Return true if token has been refreshed.
	 *
	 * @return bool
	 */
	public function has_token_refreshed(): bool {
		return $this->token_refreshed;
	}

	/**
	 * Set token as refreshed.
	 *
	 * @return void
	 */
	private function set_as_refreshed(): void {
		$this->token_refreshed = true;
	}
}
