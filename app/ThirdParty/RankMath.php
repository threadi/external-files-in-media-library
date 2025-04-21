<?php
/**
 * File to handle support for plugin "Rank Math".
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
class RankMath extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?RankMath
	 */
	private static ?RankMath $instance = null;

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
	 * @return RankMath
	 */
	public static function get_instance(): RankMath {
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
		// bail if Rank Math is not active.
		if ( ! Helper::is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			return;
		}

		// use our hooks.
		add_filter( 'eml_attachment_link', array( $this, 'do_not_touch_attachment_links' ) );
		add_filter( 'eml_setting_description_attachment_pages', array( $this, 'change_description_for_attachment_pages_setting' ), 10, 0 );
		add_filter( 'eml_setting_readonly', array( $this, 'change_readonly_for_attachment_pages_setting' ), 10, 2 );
	}

	/**
	 * Whether attachment URL should be changed to external URLs.
	 *
	 * @return bool
	 */
	public function do_not_touch_attachment_links(): bool {
		return false;
	}

	/**
	 * Change the description for the attachment pages setting.
	 *
	 * @return string
	 */
	public function change_description_for_attachment_pages_setting(): string {
		return __( 'This is handled by Rank Math.', 'external-files-in-media-library' );
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
