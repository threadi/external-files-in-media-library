<?php
/**
 * File to handle support for WordPress-own Consent API, that is actually a separate plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this plugin.
 */
class WpConsentApi extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?WpConsentApi
	 */
	private static ?WpConsentApi $instance = null;

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
	 * @return WpConsentApi
	 */
	public static function get_instance(): WpConsentApi {
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
		add_filter( 'wp_consent_api_registered_' . plugin_basename( EFML_PLUGIN ), array( $this, 'register' ) );
	}

	/**
	 * We simply return true to register the plugin with WP Consent API, although we do not use it
	 * as this plugin does not set any cookies or collect any personal data.
	 *
	 * @return bool
	 */
	public function register(): bool {
		return true;
	}
}
