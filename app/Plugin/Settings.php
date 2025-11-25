<?php
/**
 * This file defines the settings for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Export;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\MultiSelect;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Import;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Tables\Logs;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\ThirdParty\ThirdPartySupport;

/**
 * Object which handles the settings of this plugin.
 */
class Settings {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Settings
	 */
	private static ?Settings $instance = null;

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
	 * @return Settings
	 */
	public static function get_instance(): Settings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init(): void {
		// set all settings for this plugin.
		add_action( 'init', array( $this, 'add_settings' ) );

		// misc.
		add_filter( 'efml_help_tabs', array( $this, 'add_help' ) );

		// set actions.
		add_action( 'admin_action_eml_disable_gprd_hint', array( $this, 'disable_gprd_hint_by_request' ) );
		add_action( 'admin_action_efml_reset', array( $this, 'reset_plugin_by_request' ) );
	}

	/**
	 * Return the menu slug for the settings.
	 *
	 * @return string
	 */
	public function get_menu_slug(): string {
		return 'eml_settings';
	}

	/**
	 * Return the php page the settings will be using.
	 *
	 * @return string
	 */
	private function get_php_page(): string {
		return 'options-general.php';
	}

	/**
	 * Return the link to the settings.
	 *
	 * @param string $tab The tab.
	 * @param string $sub_tab The sub tab.
	 * @param string $url The URL to filter for.
	 *
	 * @return string
	 */
	public function get_url( string $tab = '', string $sub_tab = '', string $url = '' ): string {
		// define base array.
		$array = array(
			'page' => $this->get_menu_slug(),
		);

		// add tab, if set.
		if ( ! empty( $tab ) ) {
			$array['tab'] = $tab;
		}

		// add sbu-tab, if set.
		if ( ! empty( $sub_tab ) ) {
			$array['subtab'] = $sub_tab;
		}

		// add URL, if set.
		if ( ! empty( $url ) ) {
			$array['s'] = $url;
		}

		// return the URL.
		return add_query_arg(
			$array,
			get_admin_url() . $this->get_php_page()
		);
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		/**
		 * Configure the basic settings object.
		 */
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();
		$settings_obj->set_slug( 'eml' );
		$settings_obj->set_plugin_slug( EFML_PLUGIN );
		$settings_obj->set_path( Helper::get_plugin_dir() . '/app/Dependencies/easySettingsForWordPress/' );
		$settings_obj->set_url( Helper::get_plugin_url() . '/app/Dependencies/easySettingsForWordPress/' );
		$settings_obj->set_menu_title( __( 'External files in Medias Library', 'external-files-in-media-library' ) );
		$settings_obj->set_title( __( 'Settings for External files in Media Library', 'external-files-in-media-library' ) );
		$settings_obj->set_menu_slug( $this->get_menu_slug() );
		$settings_obj->set_menu_parent_slug( $this->get_php_page() );
		$settings_obj->set_translations(
			array(
				'title_settings_import_file_missing' => __( 'Required file missing', 'external-files-in-media-library' ),
				'text_settings_import_file_missing'  => __( 'Please choose a JSON-file with settings to import.', 'external-files-in-media-library' ),
				'lbl_ok'                             => __( 'OK', 'external-files-in-media-library' ),
				'lbl_cancel'                         => __( 'Cancel', 'external-files-in-media-library' ),
				'import_title'                       => __( 'Import', 'external-files-in-media-library' ),
				'dialog_import_title'                => __( 'Import plugin settings', 'external-files-in-media-library' ),
				'dialog_import_text'                 => __( 'Click on the button below to chose your JSON-file with the settings.', 'external-files-in-media-library' ),
				'dialog_import_button'               => __( 'Import now', 'external-files-in-media-library' ),
				'dialog_import_error_title'          => __( 'Error during import', 'external-files-in-media-library' ),
				'dialog_import_error_text'           => __( 'The file could not be imported!', 'external-files-in-media-library' ),
				'dialog_import_error_no_file'        => __( 'No file was uploaded.', 'external-files-in-media-library' ),
				'dialog_import_error_no_size'        => __( 'The uploaded file is no size.', 'external-files-in-media-library' ),
				'dialog_import_error_no_json'        => __( 'The uploaded file is not a valid JSON-file.', 'external-files-in-media-library' ),
				'dialog_import_error_no_json_ext'    => __( 'The uploaded file does not have the file extension <i>.json</i>.', 'external-files-in-media-library' ),
				'dialog_import_error_not_saved'      => __( 'The uploaded file could not be saved. Contact your hoster about this problem.', 'external-files-in-media-library' ),
				'dialog_import_error_not_our_json'   => __( 'The uploaded file is not a valid JSON-file with settings for this plugin.', 'external-files-in-media-library' ),
				'dialog_import_success_title'        => __( 'Settings have been imported', 'external-files-in-media-library' ),
				'dialog_import_success_text'         => __( 'Import has been run successfully.', 'external-files-in-media-library' ),
				'dialog_import_success_text_2'       => __( 'The new settings are now active. Click on the button below to reload the page and see the settings.', 'external-files-in-media-library' ),
				'export_title'                       => __( 'Export', 'external-files-in-media-library' ),
				'dialog_export_title'                => __( 'Export plugin settings', 'external-files-in-media-library' ),
				'dialog_export_text'                 => __( 'Click on the button below to export the actual settings.', 'external-files-in-media-library' ),
				'dialog_export_text_2'               => __( 'You can import this JSON-file in other projects using this WordPress plugin or theme.', 'external-files-in-media-library' ),
				'dialog_export_button'               => __( 'Export now', 'external-files-in-media-library' ),
				'table_options'                      => __( 'Options', 'external-files-in-media-library' ),
				'table_entry'                        => __( 'Entry', 'external-files-in-media-library' ),
				'table_no_entries'                   => __( 'No entries found.', 'external-files-in-media-library' ),
				'plugin_settings_title'              => __( 'Settings', 'external-files-in-media-library' ),
				'file_add_file'                      => __( 'Add file', 'external-files-in-media-library' ),
				'file_choose_file'                   => __( 'Choose file', 'external-files-in-media-library' ),
				'file_choose_image'                  => __( 'Upload or choose image', 'external-files-in-media-library' ),
				'drag_n_drop'                        => __( 'Hold to drag & drop', 'external-files-in-media-library' ),
			)
		);

		/**
		 * Add the settings page.
		 */
		$settings_page = $settings_obj->add_page( $this->get_menu_slug() );

		/**
		 * Configure all tabs for this object.
		 */
		// the general tab.
		$general_tab = $settings_page->add_tab( 'eml_general', 10 );
		$general_tab->set_name( 'eml_general' );
		$general_tab->set_title( __( 'General Settings', 'external-files-in-media-library' ) );

		// the permissions tab.
		$permissions_tab = $settings_page->add_tab( 'eml_permissions', 20 );
		$permissions_tab->set_title( __( 'Permissions', 'external-files-in-media-library' ) );

		// the proxy tab.
		$proxy_tab = $settings_page->add_tab( 'eml_proxy', 40 );
		$proxy_tab->set_title( __( 'Proxy', 'external-files-in-media-library' ) );

		// the advanced tab.
		$advanced_tab = $settings_page->add_tab( 'eml_advanced', 50 );
		$advanced_tab->set_title( __( 'Advanced', 'external-files-in-media-library' ) );

		// the logs tab.
		$logs_tab = $settings_page->add_tab( 'eml_logs', 60 );
		$logs_tab->set_title( __( 'Logs', 'external-files-in-media-library' ) );
		$logs_tab->set_callback( array( $this, 'show_logs' ) );

		// the helper tab.
		$helper_tab = $settings_page->add_tab( 'eml_helper', 70 );
		$helper_tab->set_url( Helper::get_plugin_support_url() );
		$helper_tab->set_url_target( '_blank' );
		$helper_tab->set_tab_class( 'nav-tab-help dashicons dashicons-editor-help' );

		// set the default tab.
		$settings_page->set_default_tab( $general_tab );

		/**
		 * Configure all sections for this settings object.
		 */
		// the main section.
		$general_tab_main = $general_tab->add_section( 'settings_section_main', 10 );
		$general_tab_main->set_title( __( 'General Settings', 'external-files-in-media-library' ) );
		$general_tab_main->set_setting( $settings_obj );

		// the dialog section.
		$general_tab_dialog = $general_tab->add_section( 'settings_section_dialog', 20 );
		$general_tab_dialog->set_title( __( 'Options for saving external files', 'external-files-in-media-library' ) );
		$general_tab_dialog->set_setting( $settings_obj );

		// the proxy section.
		$proxy_tab_proxy = $proxy_tab->add_section( 'settings_section_proxy', 10 );
		$proxy_tab_proxy->set_title( __( 'Proxy settings', 'external-files-in-media-library' ) );
		$proxy_tab_proxy->set_setting( $settings_obj );

		// the advanced section.
		$advanced_tab_advanced = $advanced_tab->add_section( 'settings_section_advanced', 10 );
		$advanced_tab_advanced->set_title( __( 'Advanced settings', 'external-files-in-media-library' ) );
		$advanced_tab_advanced->set_setting( $settings_obj );

		/**
		 * Add the settings to the settings object.
		 */
		// set description for disabling the attachment pages.
		$description = __( 'Each file in media library has a attachment page which could be called in frontend. With this option you can disable this attachment page for files with URLs.', 'external-files-in-media-library' );

		// show deprecated warning for old hook name.
		$description = apply_filters_deprecated( 'eml_setting_description_attachment_pages', array( $description ), '5.0.0', 'efml_setting_description_attachment_pages' );

		/**
		 * Filter the description to setting to disable the attachment pages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $description The description.
		 */
		$description = apply_filters( 'efml_setting_description_attachment_pages', $description );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_disable_attachment_pages' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->set_help( '<p>' . $description . '</p>' );
		$field = new Checkbox();
		$field->set_title( __( 'Disable the attachment page for URL-files', 'external-files-in-media-library' ) );
		$field->set_description( $description );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// get possible mime types.
		$mime_types = array();
		foreach ( Helper::get_possible_mime_types() as $mime_type => $settings ) {
			$mime_types[ $mime_type ] = $settings['label'];
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_allowed_mime_types' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'application/pdf', 'image/jpeg', 'image/png' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Select allowed mime-types', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by the external hook-documentation-URL */
		$field->set_description( sprintf( __( 'Select the MIME types that you want to allow as external URLs. Changing this setting does not affect the accessibility of external files already in use in the frontend. If you miss a MIME type, take a look <a href="%1$s" target="_blank">at our hooks (opens new window)</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_mimetypes_doc_url() ) ) );
		$field->set_options( $mime_types );
		$field->set_sanitize_callback( array( $this, 'validate_allowed_mime_types' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_delete_on_deinstallation' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Delete all data on uninstallation', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all URL-files will be deleted during deinstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_switch_on_uninstallation' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Switch external files to local hosting during uninstallation', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all external files will be saved local during uninstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_directory_listing_hide_preview' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Disable file preview in directory listings', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled the file preview in directory listings will not be used. This might increase the loading speed of directory listings.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_directory_listing_hide_not_supported_file_types' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Hide not supported file types in directory listings', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled not supported file types will not be visible in directory listings. Disable this option will not allow you to import these files.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_add_user_agent' );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Add plugin name on User Agent', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled the name of this plugin will be added to the User Agent on each outgoing request.', 'external-files-in-media-library' ),
			)
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_show_all_external_sources' );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Show all external sources', 'external-files-in-media-library' ),
				'description' => __( 'If enabled all saved external sources from each user will be visible to each other user. If disabled only administrators see all saved external sources.', 'external-files-in-media-library' ),
			)
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_play_sound' );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Play sound', 'external-files-in-media-library' ),
				'description' => __( 'If enabled a sound is played if an import is finished.', 'external-files-in-media-library' ),
			)
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_user_assign' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'integer' );
		$setting->set_default( Users::get_instance()->get_first_administrator_user() );
		$field = new Select();
		$field->set_title( __( 'Assign new files to this user', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This is only a workaround if the actual user is not available (e.g. via WP CLI import). New files are normally assigned to the user who adds them.', 'external-files-in-media-library' ) );
		$field->set_options( Roles::get_instance()->get_user_for_settings() );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_timeout' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( 30 );
		$setting->set_field(
			array(
				'type'        => 'Number',
				'title'       => __( 'Max. Timeout in seconds', 'external-files-in-media-library' ),
				'description' => __( 'Sets the maximum timeout for all external requests for files.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_max_execution_check' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable max execution check', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled after every URL during import the max execution of the PHP-processes is checked regarding the PHP-<i>max_execution_time</i>-setting in your hosting.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_log_mode' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( Helper::is_development_mode() ? 2 : 0 );
		$field = new Select();
		$field->set_title( __( 'Log-mode', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				__( 'normal', 'external-files-in-media-library' ),
				__( 'log warnings', 'external-files-in-media-library' ),
				__( 'log all', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );

		// add the import/export section in advanced.
		$advanced_plugin = $advanced_tab->add_section( 'settings_section_advanced_importexport', 20 );
		$advanced_plugin->set_title( __( 'Plugin handling', 'external-files-in-media-library' ) );
		$advanced_plugin->set_setting( $settings_obj );

		// add setting.
		$gprd_hint_setting = $settings_obj->add_setting( 'eml_disable_gprd_warning' );
		$gprd_hint_setting->set_section( $advanced_tab_advanced );
		$gprd_hint_setting->set_show_in_rest( false );
		$gprd_hint_setting->set_type( 'integer' );
		$gprd_hint_setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Disable GPRD-hint', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If disabled we will not warn you about the GPRD regulations regarding external files in websites.', 'external-files-in-media-library' ) );
		$gprd_hint_setting->set_field( $field );

		// add setting to change the proxy path.
		$proxy_path_setting = $settings_obj->add_setting( 'eml_proxy_path' );
		$proxy_path_setting->set_section( $proxy_tab_proxy );
		$proxy_path_setting->set_show_in_rest( false );
		$proxy_path_setting->set_type( 'string' );
		$proxy_path_setting->set_default( 'cache/eml/' );
		$proxy_path_setting->set_save_callback( array( $this, 'save_proxy_path' ) );
		$field = new Text();
		$field->set_title( __( 'Proxy path', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This is the path on the filesystem of your WordPress relativ to the <i>wp-content</i> directory. If you change it, the new directory should not exist. All cached files will be copied to the new path.', 'external-files-in-media-library' ) );
		$proxy_path_setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_proxy_clear' );
		$setting->set_section( $proxy_tab_proxy );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Reset proxy cache', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Reset now', 'external-files-in-media-library' ) );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => $this->get_proxy_reset_dialog() ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_user_settings' );
		$setting->set_section( $general_tab_dialog );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'User-specific settings', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced by a URL. */
				'description' => sprintf( __( 'If this option is enabled WordPress-user can choose their own settings for import of external URLs. Choose in <a href="%1$s">permissions</a> the roles which could use it.', 'external-files-in-media-library' ), esc_url( $this->get_url( 'eml_permissions' ) ) ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );

		// get the available extensions for import.
		$extensions = array();
		foreach ( Extensions::get_instance()->get_extensions_as_objects() as $extension_obj ) {
			$extensions[ $extension_obj->get_name() ] = $extension_obj->get_title();
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_import_extensions' );
		$setting->set_section( $general_tab_dialog );
		$setting->set_type( 'array' );
		$setting->set_default( Extensions::get_instance()->get_default_extensions() );
		$field = new MultiSelect();
		$field->set_title( __( 'Options for import', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select the options you want to have available in your import dialog. You will be able to enable or disable these settings before you add external files.', 'external-files-in-media-library' ) );
		$field->set_options( $extensions );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add import/export settings.
		Import::get_instance()->add_settings( $settings_obj, $advanced_plugin );
		Export::get_instance()->add_settings( $settings_obj, $advanced_plugin );

		// create reset URL.
		$reset_url = add_query_arg(
			array(
				'action' => 'efml_reset',
				'nonce'  => wp_create_nonce( 'external-files-in-media-library-reset' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$reset_dialog = array(
			'title'   => __( 'Reset plugin', 'external-files-in-media-library' ),
			'texts'   => array(
				/* translators: %1$s will be replaced by the plugin name. */
				'<p><strong>' . sprintf( __( 'Do you really want to reset any settings and data for the plugin %1$s?', 'external-files-in-media-library' ), Helper::get_plugin_name() ) . '</strong></p>',
				'<p>' . __( 'This will reset all settings and all external files in your media library.', 'external-files-in-media-library' ) . '</p>',
				'<p><strong>' . __( 'We recommend creating a backup before resetting the plugin.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $reset_url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, reset it', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_reset' );
		$setting->set_section( $advanced_plugin );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Reset plugin', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Reset plugin', 'external-files-in-media-library' ) );
		$field->set_button_url( $reset_url );
		$field->add_data( 'dialog', Helper::get_json( $reset_dialog ) );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$setting->set_field( $field );

		// initialize this settings object.
		$settings_obj->init();
	}

	/**
	 * Validate allowed mime-types.
	 *
	 * @param ?array<string> $values List of mime-types to check.
	 *
	 * @return       array<string>
	 */
	public function validate_allowed_mime_types( ?array $values ): array {
		// check if value is null.
		if ( is_null( $values ) ) {
			$values = array();
		}

		// get the possible mime-types.
		$mime_types = Helper::get_possible_mime_types();

		// check if all mimes in the request are allowed.
		$error = false;
		foreach ( $values as $key => $value ) {
			if ( ! isset( $mime_types[ $value ] ) ) {
				$error = true;
				unset( $values[ $key ] );
			}
		}

		// show error of a not supported mime-type is set.
		if ( $error ) {
			add_settings_error( 'eml_allowed_mime_types', 'eml_allowed_mime_types', __( 'The given mime-type is not supported. Setting will not be saved.', 'external-files-in-media-library' ) );
		}

		// if list is not empty, remove any notification about it.
		if ( ! empty( $values ) ) {
			$transients_obj = Transients::get_instance();
			$transients_obj->get_transient_by_name( 'eml_missing_mime_types' )->delete();
		}

		// return resulting list.
		return $values;
	}

	/**
	 * Show the logs.
	 *
	 * @return void
	 */
	public function show_logs(): void {
		// if WP_List_Table is not loaded automatically, we need to load it.
		if ( ! class_exists( 'WP_List_Table' ) ) {
			include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; // @phpstan-ignore includeOnce.fileNotFound
		}
		$log = new Logs();
		$log->prepare_items();
		?>
		<div class="wrap eml-log-table">
			<h2><?php echo esc_html__( 'Logs', 'external-files-in-media-library' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="eml_settings">
				<input type="hidden" name="tab" value="eml_logs">
				<?php
				$log->search_box( __( 'Search for URL', 'external-files-in-media-library' ), 'link' );
				$log->views();
				$log->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		// run activations on Services.
		Services::get_instance()->activation();

		// run activations on ThirdParty-support.
		ThirdPartySupport::get_instance()->activation();

		// run activation of Synchronization support.
		Synchronization::get_instance()->activation();

		// add all plugin specific settings.
		$this->add_settings();

		// run the installation of them.
		\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->activation();
	}

	/**
	 * Show protocol hint for images and videos.
	 *
	 * @return void
	 */
	public function show_protocol_hint(): void {
		echo wp_kses_post( __( 'These settings only apply to files that are provided via <code>HTTP</code>. Files from other protocols (such as <code>FTP</code>) are generally only saved locally without a proxy.', 'external-files-in-media-library' ) );
	}

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array<array<string,string>> $help_list List of help tabs.
	 *
	 * @return array<array<string,string>>
	 */
	public function add_help( array $help_list ): array {
		$content = '<h1>' . __( 'Settings for External Files in Media Library', 'external-files-in-media-library' ) . '</h1>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<p>' . sprintf( __( 'You can adjust the behavior of the plugin to your own requirements in many places via <a href="%1$s">the settings</a>. The possible settings are described in more detail below.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ) . '</p>';

		// get help texts from each setting, which have one.
		foreach ( \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance()->get_settings() as $settings_obj ) {
			// bail if setting has no help text.
			if ( ! $settings_obj->has_help() ) {
				continue;
			}

			// get the field.
			$field = $settings_obj->get_field();

			// bail if field could not be found.
			if ( ! $field ) {
				continue;
			}

			// add this setting to the help page.
			$content .= '<h3>' . $field->get_title() . '</h3>' . $settings_obj->get_help();
		}

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-setting',
			'title'   => __( 'External Media Files Settings', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Return the link to disable the GPRD-warning.
	 *
	 * @return string
	 */
	public function disable_gprd_hint_url(): string {
		return add_query_arg(
			array(
				'action' => 'eml_disable_gprd_hint',
				'nonce'  => wp_create_nonce( 'eml-disable-gprd-hint' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Disable GPRD-hint by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function disable_gprd_hint_by_request(): void {
		// check referer.
		check_admin_referer( 'eml-disable-gprd-hint', 'nonce' );

		// set the option.
		update_option( 'eml_disable_gprd_warning', 1 );

		// forward user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Check the proxy path if it has been changed.
	 *
	 * @param string $new_value The old value.
	 * @param string $old_value The new value.
	 *
	 * @return string
	 */
	public function save_proxy_path( string $new_value, string $old_value ): string {
		// bail if value has not been changed.
		if ( $new_value === $old_value ) {
			return $old_value;
		}

		// create absolute path for new value.
		$new_value_path = trailingslashit( WP_CONTENT_DIR ) . $new_value;

		// create absolute path for old value.
		$old_value_path = trailingslashit( WP_CONTENT_DIR ) . $old_value;

		// bail if new path already exist.
		if ( file_exists( $new_value_path ) ) {
			return $old_value;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// move all files from old to new directory.
		$wp_filesystem->move( $old_value_path, $new_value_path );

		// return the new value.
		return $new_value;
	}

	/**
	 * Return the proxy reset dialog configuration.
	 *
	 * @return string
	 */
	public function get_proxy_reset_dialog(): string {
		$dialog_config = array(
			'title'   => __( 'Reset proxy cache', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the following button to reset the proxy cache.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'This will remove all files in the cache, including all generated images.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'efml_reset_proxy();',
					'variant' => 'primary',
					'text'    => __( 'Reset now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
		$dialog        = wp_json_encode( $dialog_config );
		if ( ! $dialog ) {
			return '';
		}
		return $dialog;
	}

	/**
	 * Reset the plugin by request.
	 *
	 * @return void
	 */
	public function reset_plugin_by_request(): void {
		// check nonce.
		check_admin_referer( 'external-files-in-media-library-reset', 'nonce' );

		// uninstall all.
		Uninstall::get_instance()->run();

		// run installer tasks.
		Install::get_instance()->activation();

		// forward user to dashboard.
		wp_safe_redirect( get_admin_url() );
	}
}
