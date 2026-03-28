<?php
/**
 * File to handle the standard mode.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Configurations;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Setting;
use ExternalFilesInMediaLibrary\Plugin\Configuration_Base;
use ExternalFilesInMediaLibrary\Plugin\Roles;
use ExternalFilesInMediaLibrary\Plugin\Settings;

/**
 * Object for the standard mode.
 */
class Standard extends Configuration_Base {

	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = 'standard';

	/**
	 * Initialize this object.
	 */
	public function __construct() {}

	/**
	 * Return the title of this object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Default settings', 'external-files-in-media-library' );
	}

	/**
	 * Return additional hints for the dialog to set this mode.
	 *
	 * @return array<int,string>
	 */
	public function get_dialog_hints(): array {
		return array(
			'<p>' . __( 'This will set the services as if the plugin was freshly installed.', 'external-files-in-media-library' ) . '<br>' . __( 'We do not change any other settings or tools.', 'external-files-in-media-library' ) . '<br>' . __( 'Additional plugins for services will not be installed.', 'external-files-in-media-library' ) . '</p>',
		);
	}

	/**
	 * Save the configuration this mode defines.
	 *
	 * @return void
	 */
	public function run(): void {
		Roles::get_instance()->install();
		Roles::get_instance()->trigger_update();

		// get the setting.
		$setting_obj = Settings::get_instance()->get_settings_obj()->get_setting( 'eml_import_extensions' );

		/**
		 * Configuration:
		 * Change settings to hide options.
		 */
		// enable hints for plugins.
		update_option( 'eml_disable_plugin_hints', 0 );

		// enable all options.
		if ( $setting_obj instanceof Setting ) {
			update_option( 'eml_import_extensions', $setting_obj->get_default() );
		}

		// enable user specific settings.
		update_option( 'eml_user_settings', 1 );

		// enable job-link on each file.
		update_option( 'eml_job_show_link', 1 );
	}
}
