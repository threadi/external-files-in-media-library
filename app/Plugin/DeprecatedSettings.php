<?php
/**
 * This file defines the settings of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object, which handles the settings of this plugin.
 */
class DeprecatedSettings {
	/**
	 * Instance of actual object.
	 *
	 * @var ?DeprecatedSettings
	 */
	private static ?DeprecatedSettings $instance = null;

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
	 * @return DeprecatedSettings
	 */
	public static function get_instance(): DeprecatedSettings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return false for request of the page to prevent any other processing.
	 *
	 * @return bool
	 */
	public function get_page(): bool {
		return false;
	}
}
