<?php
/**
 * File to handle the Imgur support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle Imgur-support.
 */
class Imgur {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Imgur
	 */
	private static ?Imgur $instance = null;

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
	 * @return Imgur
	 */
	public static function get_instance(): Imgur {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Imgur();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'eml_http_states', array( $this, 'add_http_state' ), 10, 2 );
		add_filter( 'eml_http_check_content_type_existence', array( $this, 'allow_http_response_without_content_type' ), 10, 2 );
		add_filter( 'eml_http_save_local', array( $this, 'force_local_saving' ), 10, 2 );
		add_filter( 'eml_blacklist', array( $this, 'check_url' ), 10, 2 );
	}

	/**
	 * Add allowed http state for Imgur.
	 *
	 * @param array  $http_states List of HTTP-states.
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	public function add_http_state( array $http_states, string $url ): array {
		// bail if this is not an imgur-URL.
		if ( ! str_contains( $url, 'imgur' ) ) {
			return $http_states;
		}

		// add the states Imgur is sending.
		$http_states[] = 429;

		// return the resulting list.
		return $http_states;
	}

	/**
	 * Do not check for content type.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function allow_http_response_without_content_type( bool $results, string $url ): bool {
		// bail if this is not an imgur-URL.
		if ( ! str_contains( $url, 'imgur' ) ) {
			return $results;
		}

		// do not check for content type.
		return false;
	}

	/**
	 * Force local saving for Imgur files.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function force_local_saving( bool $results, string $url ): bool {
		// bail if this is not an imgur-URL.
		if ( ! str_contains( $url, 'imgur' ) ) {
			return $results;
		}

		// force local saving for Imgur files.
		return true;
	}

	/**
	 * Check if given URL is using a not possible Imgur-domain.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function check_url( bool $results, string $url ): bool {
		// bail if this is not an imgur-URL.
		if ( ! str_contains( $url, 'imgur' ) ) {
			return $results;
		}

		// list of Imgur-URLs which cannot be used for <img>-elements.
		$blacklist = array(
			'http://imgur.com',
			'https://imgur.com',
		);

		// check the URL against the blacklist.
		$match = false;
		foreach ( $blacklist as $blacklist_url ) {
			if ( str_contains( $url, $blacklist_url ) ) {
				$match = true;
			}
		}

		// bail on no match.
		if ( ! $match ) {
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'Given Imgur-URL could not be used as external embed image in websites.', 'external-files-in-media-library' ), esc_url( $url ), 'error', 0 );

		// return result to prevent any further import.
		return true;
	}
}
