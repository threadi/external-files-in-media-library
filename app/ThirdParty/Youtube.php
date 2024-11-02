<?php
/**
 * File to handle support for Google Drive.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle support for this plugin.
 */
class Youtube {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Youtube
	 */
	private static ?Youtube $instance = null;

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
	 * @return Youtube
	 */
	public static function get_instance(): Youtube {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Youtube();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'eml_filter_url_response', array( $this, 'get_video_data' ), 10, 2 );
	}

	/**
	 * Check if given URL is a YouTube video and set its import data.
	 *
	 * @param array  $results The result as array for file import.
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	public function get_video_data( array $results, string $url ): array {
		// bail if this is not a YouTube-URL.
		if ( ! str_contains( $url, 'youtube.com' ) ) {
			return $results;
		}

		// initialize basic array for file data.
		$results = array(
			'title'     => basename( $url ),
			'filesize'  => 1,
			'mime-type' => 'video/mp4',
			'local'     => false,
			'url'       => $url,
		);

		return $results;
	}
}
