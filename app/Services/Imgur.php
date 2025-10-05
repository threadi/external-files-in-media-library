<?php
/**
 * File to handle the Imgur support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle Imgur-support.
 */
class Imgur implements Service {

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
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'eml_http_states', array( $this, 'add_http_state' ), 10, 2 );
		add_filter( 'eml_http_check_content_type_existence', array( $this, 'allow_http_response_without_content_type' ), 10, 2 );
		add_filter( 'eml_http_save_local', array( $this, 'force_local_saving' ), 10, 2 );
		add_filter( 'eml_prevent_import', array( $this, 'check_url' ), 10, 2 );
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 20 );
	}

	/**
	 * Add allowed http state for Imgur.
	 *
	 * @param array<int> $http_states List of HTTP-states.
	 * @param string     $url The used URL.
	 *
	 * @return array<int>
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
		Log::get_instance()->create( __( 'Given Imgur-URL could not be used as external embed image in websites. This URL can be used for embedding, but not for selecting images.', 'external-files-in-media-library' ), esc_url( $url ), 'error' );

		// return result to prevent any further import.
		return true;
	}

	/**
	 * Add help for Imgur usage.
	 *
	 * @param array<array<string,string>> $help_list List of help tabs.
	 *
	 * @return array<array<string,string>>
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Using images from Imgur', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'Please note the following when using Imgur:', 'external-files-in-media-library' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . __( 'URLs beginning with <code>https://imgur</code> can only be used for embedding. They cannot be used in the Media Library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'URLs beginning with <code>https://i.imgur</code> can be used as an image in the Media Library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '</ul>';
		$content .= '<h3>' . __( 'How to add them', 'external-files-in-media-library' ) . '</h3>';
		$content .= '<ol>';
		$content .= '<li>' . __( 'Upload your image on Imgur.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Right-click on the uploaded image and copy its URL.', 'external-files-in-media-library' ) . '</li>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<li>' . sprintf( __( 'Go to Media > <a href="%1$s">Add Media File</a>.', 'external-files-in-media-library' ), esc_url( add_query_arg( array(), get_admin_url() . 'media-new.php' ) ) ) . '</li>';
		$content .= '<li>' . __( 'Click on the button "Add external files".', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Paste the earlier copied URL in the field in the new dialog.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Click on "Add URLs".', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Wait until you get an answer.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Take a look at your added external files in the media library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '</ol>';
		$content .= '<h3>' . __( 'Hints', 'external-files-in-media-library' ) . '</h3>';
		$content .= '<p>' . __( 'Imgur images can not be used on local WordPress installations. Imgur recognizes this and rejects the requests.', 'external-files-in-media-library' ) .'</p>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-imgur',
			'title'   => __( 'Using Imgur', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
