<?php
/**
 * File to handle any import of settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Button;

/**
 * Initialize the plugin, connect all together.
 */
class Import {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Import
	 */
	private static ?Import $instance = null;

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
	 * @return Import
	 */
	public static function get_instance(): Import {
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
		add_action( 'admin_action_eml_setting_import', array( $this, 'import_via_request' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_script' ) );
		add_action( 'wp_ajax_eml_settings_import_file', array( $this, 'import_via_ajax' ) );
	}

	/**
	 * Add import scripts.
	 *
	 * @return void
	 */
	public function add_script(): void {
		// backend-JS.
		wp_enqueue_script(
			'eml-import-admin',
			plugins_url( '/admin/import.js', EFML_PLUGIN ),
			array( 'jquery' ),
			filemtime( Helper::get_plugin_dir() . '/admin/import.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-import-admin',
			'efmlImportJsVars',
			array(
				'ajax_url'                           => admin_url( 'admin-ajax.php' ),
				'settings_import_file_nonce'         => wp_create_nonce( 'eml-import-settings' ),
				'title_settings_import_file_missing' => __( 'Required file missing', 'external-files-in-media-library' ),
				'text_settings_import_file_missing'  => __( 'Please choose a JSON-file with settings of <i>External Files in Media Library</i> to import.', 'external-files-in-media-library' ),
				'lbl_ok'                             => __( 'OK', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Add import settings.
	 *
	 * @param Settings $settings_obj The settings object.
	 *
	 * @return void
	 */
	public function add_settings( Settings $settings_obj ): void {
		// the import/export section in advanced.
		$advanced_tab_importexport = $settings_obj->get_tab( 'eml_advanced' )->get_section( 'settings_section_advanced_importexport' );

		// create import dialog.
		$dialog = array(
			'title'   => __( 'Import plugin settings', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the button below to chose your JSON-file with the settings.', 'external-files-in-media-library' ) . '</strong></p>',
				'<input type="file" accept="application/json" name="import_settings_file" id="import_settings_file">',
			),
			'buttons' => array(
				array(
					'action'  => 'efml_import_settings_file();',
					'variant' => 'primary',
					'text'    => __( 'Import now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_import_settings' );
		$setting->set_section( $advanced_tab_importexport );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Import', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Import now', 'external-files-in-media-library' ) );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$setting->set_field( $field );
	}

	/**
	 * Run import via request.
	 *
	 * @return void
	 */
	public function import_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-import-settings', 'nonce' );

		// create dialog for response.
		$dialog = array(
			'detail' => array(
				'title'   => __( 'Error during import', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'The file could not be imported!', 'external-files-in-media-library' ) . '</strong></p>',
				),
				'buttons' => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			),
		);

		// bail if no file is given.
		if ( ! isset( $_FILES ) ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'No file was uploaded.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// bail if file has no size.
		if ( isset( $_FILES['file']['size'] ) && 0 === $_FILES['file']['size'] ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file is no size.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// bail if file type is not JSON.
		if ( isset( $_FILES['file']['type'] ) && 'application/json' !== $_FILES['file']['type'] ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file is not a valid JSON-file.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// allow JSON-files.
		add_filter( 'upload_mimes', array( $this, 'allow_json' ) );

		// bail if file type is not JSON.
		if ( isset( $_FILES['file']['name'] ) ) {
			$filetype = wp_check_filetype( sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) );
			if ( 'json' !== $filetype['ext'] ) {
				$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file does not have the file extension <i>.json</i>.', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( $dialog );
			}
		}

		// bail if no tmp_name is available.
		if ( ! isset( $_FILES['file']['tmp_name'] ) ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file could not be saved. Contact your hoster about this problem.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// bail if uploaded file is not readable.
		if ( isset( $_FILES['file']['tmp_name'] ) && ! file_exists( sanitize_text_field( $_FILES['file']['tmp_name'] ) ) ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file could not be saved. Contact your hoster about this problem.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		// get WP Filesystem-handler for read the file.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;
		$file_content = $wp_filesystem->get_contents( sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) ) );

		// convert JSON to array.
		$settings_array = json_decode( $file_content, ARRAY_A );

		// bail if JSON-code does not contain one of our settings.
		if ( ! isset( $settings_array['eml_log_mode'] ) ) {
			$dialog['detail']['texts'][1] = '<p>' . __( 'The uploaded file is not a valid JSON-file with settings for this plugin.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( $dialog );
		}

		/**
		 * Run additional tasks before running the import of settings.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 */
		do_action( 'eml_settings_import' );

		// get the settings object.
		$settings_obj = Settings::get_instance();

		// import the settings.
		foreach ( $settings_array as $field_name => $field_value ) {
			// check if given setting is used in this plugin.
			if ( ! $settings_obj->get_setting( $field_name ) ) {
				continue;
			}

			// update this setting.
			update_option( $field_name, $field_value );
		}

		// return that import was successfully.
		$dialog['detail']['title']                = __( 'Settings have been imported', 'external-files-in-media-library' );
		$dialog['detail']['texts'][0]             = '<p><strong>' . __( 'Import has been run successfully.', 'external-files-in-media-library' ) . '</strong></p>';
		$dialog['detail']['texts'][1]             = '<p>' . __( 'The new settings are now active. Click on the button below to reload the page and see the settings.', 'external-files-in-media-library' ) . '</p>';
		$dialog['detail']['buttons'][0]['action'] = 'location.reload();';
		wp_send_json( $dialog );
	}

	/**
	 * Allow SVG as file-type.
	 *
	 * @param array $file_types List of file types.
	 *
	 * @return array
	 */
	public function allow_json( array $file_types ): array {
		$new_filetypes         = array();
		$new_filetypes['json'] = 'application/json';
		return array_merge( $file_types, $new_filetypes );
	}
}
