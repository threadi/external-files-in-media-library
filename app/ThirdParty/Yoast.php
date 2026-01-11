<?php
/**
 * File to handle support for the plugin "Yoast".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Field_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class Yoast extends ThirdParty_Base implements ThirdParty {

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

		// use our hooks.
		add_filter( 'efml_attachment_link', array( $this, 'do_not_touch_attachment_links' ) );
		add_filter( 'efml_setting_description_attachment_pages', array( $this, 'change_description_for_attachment_pages_setting' ), 10, 0 );
		add_filter( 'efml_setting_readonly', array( $this, 'change_readonly_for_attachment_pages_setting' ), 10, 2 );
	}

	/**
	 * Whether attachment URL should be changed to external URLs.
	 *
	 * @return bool
	 */
	public function do_not_touch_attachment_links(): bool {
		return method_exists( 'WPSEO_Options', 'get' );
	}

	/**
	 * Change the description for the attachment pages setting.
	 *
	 * @return string
	 */
	public function change_description_for_attachment_pages_setting(): string {
		return __( 'This is handled by Yoast SEO.', 'external-files-in-media-library' );
	}

	/**
	 * Change the read only setting for attachment pages setting.
	 *
	 * @param bool       $actual_value The actual value.
	 * @param Field_Base $field The field as object.
	 *
	 * @return bool
	 */
	public function change_readonly_for_attachment_pages_setting( bool $actual_value, Field_Base $field ): bool {
		// bail if no setting is assigned.
		if ( ! $field->get_setting() ) {
			return $actual_value;
		}

		// bail if setting is not "eml_disable_attachment_pages".
		if ( 'eml_disable_attachment_pages' !== $field->get_setting()->get_name() ) {
			return $actual_value;
		}

		// disable the setting.
		return true;
	}
}
