<?php
/**
 * File to handle support for the Google Drive platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle support for this platform.
 */
class GoogleDrive {

	/**
	 * Instance of actual object.
	 *
	 * @var ?GoogleDrive
	 */
	private static ?GoogleDrive $instance = null;

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
	 * @return GoogleDrive
	 */
	public static function get_instance(): GoogleDrive {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'eml_blacklist', array( $this, 'check_url' ), 10, 2 );
	}

	/**
	 * Check if given URL is using a not possible Google Drive-URL.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function check_url( bool $results, string $url ): bool {
		// bail if this is not a Google-URL.
		if ( ! str_contains( $url, 'google.com' ) ) {
			return $results;
		}

		// list of Google Drive-URLs which cannot be used for <img>-elements.
		$blacklist = array(
			'https://drive.google.com/file/',
		);

		// check the URL against the blacklist.
		$match = false;
		foreach ( $blacklist as $blacklist_url ) {
			if ( str_contains( $url, $blacklist_url ) ) {
				$match = true;
			}
		}

		// bail on no match => GoogleDrive URL could be used.
		if ( ! $match ) {
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'Given GoogleDrive-URL could not be used as external embed image in websites.', 'external-files-in-media-library' ), esc_url( $url ), 'error', 0 );

		// return result to prevent any further import.
		return true;
	}
}
