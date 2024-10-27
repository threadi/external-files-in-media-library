<?php
/**
 * File to handle support for plugin "Yoast".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class Yoast {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Yoast
	 */
	private static ?Yoast $instance = null;

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
	 * @return Yoast
	 */
	public static function get_instance(): Yoast {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Yoast is not active.
		if ( ! Helper::is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			return;
		}

		add_filter( 'eml_attachment_link', array( $this, 'do_not_touch_attachment_links' ) );
	}

	/**
	 * Whether attachment URL should be changed to external URLs.
	 *
	 * @return bool
	 */
	public function do_not_touch_attachment_links(): bool {
		return method_exists( 'WPSEO_Options', 'get' );
	}
}
