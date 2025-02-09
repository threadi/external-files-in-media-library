<?php
/**
 * File to handle any export of settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Button;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Initialize the export support.
 */
class Export {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Export
	 */
	private static ?Export $instance = null;

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
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use hooks.
		add_action( 'admin_action_eml_setting_export', array( $this, 'run' ) );
	}

	/**
	 * Add export settings.
	 *
	 * @param Settings $settings_obj The settings object.
	 *
	 * @return void
	 */
	public function add_settings( Settings $settings_obj ): void {
		// the import/export section in advanced.
		$advanced_tab_importexport = $settings_obj->get_tab( 'eml_advanced' )->get_section( 'settings_section_advanced_importexport' );

		// create export URL.
		$export_url = add_query_arg(
			array(
				'action' => 'eml_setting_export',
				'nonce'  => wp_create_nonce( 'eml-setting-export' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create export dialog.
		$dialog = array(
			'title'   => __( 'Export plugin settings', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the button below to export the actual settings.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'You can import this JSON-file in other projects using the WordPress plugin <i>External Files in Media Library</i>.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'closeDialog();location.href="' . $export_url . '";',
					'variant' => 'primary',
					'text'    => __( 'Export now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_settings' );
		$setting->set_section( $advanced_tab_importexport );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Export', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Export now', 'external-files-in-media-library' ) );
		$field->set_button_url( $export_url );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$setting->set_field( $field );
	}

	/**
	 * Run the export and
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function run(): void {
		// check referer.
		check_admin_referer( 'eml-setting-export', 'nonce' );

		// get the settings as array.
		$settings = Settings::get_instance()->get_settings();

		// bail if list is empty.
		if ( empty( $settings ) ) {
			// show hint.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_type( 'error' );
			$transient_obj->set_name( 'eml_export_error' );
			$transient_obj->set_message( '<strong>' . __( 'Export could not run!', 'external-files-in-media-library' ) . '</strong><br>' . __( 'No settings could be loaded.', 'external-files-in-media-library' ) );
			$transient_obj->save();

			// redirect user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// array for the export.
		$export_settings = array();

		// convert this array to a simple one with "setting_name" > "value".
		foreach ( $settings as $settings_obj ) {
			// bail if this setting is not a Setting object.
			if ( ! $settings_obj instanceof Setting ) {
				continue;
			}

			// bail if export is prevented.
			if ( $settings_obj->is_export_prevented() ) {
				continue;
			}

			// add to export array.
			$export_settings[ $settings_obj->get_name() ] = $settings_obj->get_value();
		}

		// create filename for JSON-download-file.
		$filename = gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '_External_Files_in_Media_library_Settings.json';
		/**
		 * File the filename for JSON-download of all settings.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param string $filename The generated filename.
		 */
		$filename = apply_filters( 'eml_settings_export_filename', $filename );

		// set header for response as JSON-download.
		header( 'Content-type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );
		echo wp_json_encode( $export_settings );
		exit;
	}
}
