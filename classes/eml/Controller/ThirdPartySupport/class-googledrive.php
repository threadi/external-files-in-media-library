<?php
/**
 * File to handle support for Google Drive.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller\ThirdPartySupport;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Model\Log;

/**
 * Object to handle support for this plugin.
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
			self::$instance = new GoogleDrive();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
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
		// bail if this is not an imgur-URL.
		if ( ! str_contains( $url, 'google.com' ) ) {
			return $results;
		}

		// list of Imgur-URLs which cannot be used for <img>-elements.
		$blacklist = array(
			'https://drive.google.com/file/',
		);

		// check the URL against the blacklist.
		$match = false;
		foreach( $blacklist as $blacklist_url ) {
			if( str_contains( $url, $blacklist_url ) ) {
				$match = true;
			}
		}

		// bail on no match.
		if( ! $match ) {
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'Given GoogleDrive-URL could not be used as external embed image in websites.', 'external-files-in-media-library' ), esc_url( $url ), 'error', 0 );

		// return result to prevent any further import.
		return true;
	}
}
