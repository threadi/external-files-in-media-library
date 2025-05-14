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
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Import;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Setting;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files;
use ExternalFilesInMediaLibrary\Plugin\Tables\Logs;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\ThirdParty\ThirdPartySupport;
use WP_User;

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
		add_action( 'init', array( $this, 'add_settings' ) );
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ) );
		add_action( 'admin_action_eml_disable_gprd_hint', array( $this, 'disable_gprd_hint_by_request' ) );
	}

	/**
	 * Return the menu slug for the settings.
	 *
	 * @return string
	 */
	private function get_menu_slug(): string {
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
	 * @param string $url The URL to filter for.
	 *
	 * @return string
	 */
	public function get_url( string $tab = '', string $url = '' ): string {
		// define base array.
		$array = array(
			'page' => $this->get_menu_slug(),
		);

		// add tab, if set.
		if ( ! empty( $tab ) ) {
			$array['tab'] = $tab;
		}

		// add URL, if set.
		if ( ! empty( $url ) ) {
			$array['url'] = $url;
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
		$settings_obj->set_menu_title( __( 'External files in Medias Library', 'external-files-in-media-library' ) );
		$settings_obj->set_title( __( 'Settings for External files in Media Library', 'external-files-in-media-library' ) );
		$settings_obj->set_menu_slug( $this->get_menu_slug() );
		$settings_obj->set_menu_parent_slug( $this->get_php_page() );

		/**
		 * Configure all tabs for this object.
		 */
		// the general tab.
		$general_tab = $settings_obj->add_tab( 'eml_general' );
		$general_tab->set_name( 'eml_general' );
		$general_tab->set_title( __( 'General Settings', 'external-files-in-media-library' ) );

		// the permissions tab.
		$permissions_tab = $settings_obj->add_tab( 'eml_permissions' );
		$permissions_tab->set_title( __( 'Permissions', 'external-files-in-media-library' ) );

		// the audio tab.
		$audio_tab = $settings_obj->add_tab( 'eml_audio' );
		$audio_tab->set_title( __( 'Audio', 'external-files-in-media-library' ) );

		// the images tab.
		$images_tab = $settings_obj->add_tab( 'eml_images' );
		$images_tab->set_title( __( 'Images', 'external-files-in-media-library' ) );

		// the video tab.
		$video_tab = $settings_obj->add_tab( 'eml_video' );
		$video_tab->set_title( __( 'Videos', 'external-files-in-media-library' ) );

		// the proxy tab.
		$proxy_tab = $settings_obj->add_tab( 'eml_proxy' );
		$proxy_tab->set_title( __( 'Proxy', 'external-files-in-media-library' ) );

		// the advanced tab.
		$advanced_tab = $settings_obj->add_tab( 'eml_advanced' );
		$advanced_tab->set_title( __( 'Advanced', 'external-files-in-media-library' ) );

		// the logs tab.
		$logs_tab = $settings_obj->add_tab( 'eml_logs' );
		$logs_tab->set_title( __( 'Logs', 'external-files-in-media-library' ) );
		$logs_tab->set_callback( array( $this, 'show_logs' ) );

		// the helper tab.
		$helper_tab = $settings_obj->add_tab( 'eml_helper' );
		$helper_tab->set_title( __( 'Questions? Check our forum', 'external-files-in-media-library' ) );
		$helper_tab->set_url( Helper::get_plugin_support_url() );
		$helper_tab->set_url_target( '_blank' );
		$helper_tab->set_tab_class( 'nav-tab-help' );

		// set the default tab.
		$settings_obj->set_default_tab( $general_tab );

		/**
		 * Configure all sections for this settings object.
		 */
		// the main section.
		$general_tab_main = $general_tab->add_section( 'settings_section_main' );
		$general_tab_main->set_title( __( 'General Settings', 'external-files-in-media-library' ) );
		$general_tab_main->set_setting( $settings_obj );

		// the files section.
		$permissions_tab_files = $permissions_tab->add_section( 'settings_section_add_files' );
		$permissions_tab_files->set_title( __( 'Permissions to add files', 'external-files-in-media-library' ) );
		$permissions_tab_files->set_setting( $settings_obj );

		// the audio section.
		$audio_tab_audios = $audio_tab->add_section( 'settings_section_audio' );
		$audio_tab_audios->set_title( __( 'Audio Settings', 'external-files-in-media-library' ) );
		$audio_tab_audios->set_callback( array( $this, 'show_protocol_hint' ) );
		$audio_tab_audios->set_setting( $settings_obj );

		// the images section.
		$images_tab_images = $images_tab->add_section( 'settings_section_images' );
		$images_tab_images->set_title( __( 'Images Settings', 'external-files-in-media-library' ) );
		$images_tab_images->set_callback( array( $this, 'show_protocol_hint' ) );
		$images_tab_images->set_setting( $settings_obj );

		// the videos section.
		$videos_tab_videos = $video_tab->add_section( 'settings_section_images' );
		$videos_tab_videos->set_title( __( 'Video Settings', 'external-files-in-media-library' ) );
		$videos_tab_videos->set_callback( array( $this, 'show_protocol_hint' ) );
		$videos_tab_videos->set_setting( $settings_obj );

		// the proxy section.
		$proxy_tab_proxy = $proxy_tab->add_section( 'settings_section_proxy' );
		$proxy_tab_proxy->set_title( __( 'Proxy settings', 'external-files-in-media-library' ) );
		$proxy_tab_proxy->set_setting( $settings_obj );

		// the advanced section.
		$advanced_tab_advanced = $advanced_tab->add_section( 'settings_section_advanced' );
		$advanced_tab_advanced->set_title( __( 'Advanced settings', 'external-files-in-media-library' ) );
		$advanced_tab_advanced->set_setting( $settings_obj );

		/**
		 * Add the settings to the settings object.
		 */
		// set description for disabling the attachment pages.
		$description = __( 'Each file in media library has a attachment page which could be called in frontend. With this option you can disable this attachment page for files with URLs.', 'external-files-in-media-library' );
		/**
		 * Filter the description to setting to disable the attachment pages.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $description The description.
		 */
		$description = apply_filters( 'eml_setting_description_attachment_pages', $description );

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
		$field->set_description( sprintf( __( 'Choose the mime-types you wish to allow as external URL. If you change this setting, already used external files will not change their accessibility in frontend. If you miss a mime-type, take a look <a href="%1$s" target="_blank">at our hooks (opens new window)</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_mimetypes_doc_url() ) ) );
		$field->set_options( $mime_types );
		$field->set_sanitize_callback( array( $this, 'validate_allowed_mime_types' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_check_interval' );
		$setting->set_section( $general_tab_main );
		$setting->set_type( 'string' );
		$setting->set_default( 'daily' );
		$setting->set_help( __( 'Defines the time interval in which files with URLs are automatically checked for its availability.', 'external-files-in-media-library' ) );
		$field = new Select();
		$field->set_title( __( 'Set interval for file-check', 'external-files-in-media-library' ) );
		$field->set_description( $setting->get_help() );
		$field->set_options( Helper::get_intervals() );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$setting->set_save_callback( array( $this, 'update_interval_setting' ) );
		$setting->set_field( $field );

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
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_use_file_dates' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Use external file dates', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all external files will be saved in media library with the date set by the external location. If the external location does not set any date the actual date will be used.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

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
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// get user roles.
		$user_roles = array();
		if ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$user_roles[ $slug ] = $role['name'];
			}
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_allowed_roles' );
		$setting->set_section( $permissions_tab_files );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator', 'editor' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Select user roles', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles which should be allowed to add external files.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$field->set_sanitize_callback( array( $this, 'set_capabilities' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		$users               = array();
		$first_administrator = 0;
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'eml_settings' === filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			foreach ( get_users() as $user ) {
				// bail if user is not WP_User.
				if ( ! $user instanceof WP_User ) {
					continue;
				}

				// add to the list.
				$users[ $user->ID ] = $user->display_name;
			}
			$first_administrator = Helper::get_first_administrator_user();
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_user_assign' );
		$setting->set_section( $permissions_tab_files );
		$setting->set_type( 'integer' );
		$setting->set_default( $first_administrator );
		$field = new Select();
		$field->set_title( __( 'User new files should be assigned to', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This is only a fallback if the actual user is not available (e.g. via CLI-import). New files are normally assigned to the user who add them.', 'external-files-in-media-library' ) );
		$field->set_options( $users );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_images_mode' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for image handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external images are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_mode' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for audio handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external audios are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_proxy' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for audios', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if audios are hosted external. If this option is disabled, external audios will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the audios locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_video_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_audio_proxy_max_age' );
		$setting->set_section( $audio_tab_audios );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached audio in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long audios, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_proxy' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$setting->set_save_callback( array( $this, 'update_proxy_setting' ) );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for images', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if images are hosted external. If this option is disabled, external images will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the images locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_images_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_proxy_max_age' );
		$setting->set_section( $images_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached images in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long images, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
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
				'description' => __( 'Defines the maximum timeout for any external request for files.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_mode' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'string' );
		$setting->set_default( 'external' );
		$field = new Select();
		$field->set_title( __( 'Mode for video handling', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how external video are handled.', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'external' => __( 'host them extern', 'external-files-in-media-library' ),
				'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_proxy' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for videos', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if videos are hosted external. If this option is disabled, external videos will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the videos locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_video_mode', '' ) );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_video_proxy_max_age' );
		$setting->set_section( $videos_tab_videos );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 * 7 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached video in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long videos, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
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
		$setting->set_default( 0 );
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
		$advanced_tab_importexport = $advanced_tab->add_section( 'settings_section_advanced_importexport' );
		$advanced_tab_importexport->set_title( __( 'Export & Import settings', 'external-files-in-media-library' ) );
		$advanced_tab_importexport->set_setting( $settings_obj );

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

		// add import/export settings.
		Import::get_instance()->add_settings( $settings_obj, $advanced_tab_importexport );
		Export::get_instance()->add_settings( $settings_obj, $advanced_tab_importexport );

		// initialize this settings object.
		$settings_obj->init();
	}

	/**
	 * Validate the interval setting.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_interval_setting( string $value ): string {
		// get option.
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		// bail if value is empty.
		if ( empty( $value ) ) {
			add_settings_error( $option, $option, __( 'An interval has to be set.', 'external-files-in-media-library' ) );
			return '';
		}

		// bail if value is 'eml_disable_check'.
		if ( 'eml_disable_check' === $value ) {
			return $value;
		}

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $value ] ) ) {
			/* translators: %1$s will be replaced by the name of the used interval */
			add_settings_error( $option, $option, sprintf( __( 'The given interval %1$s does not exists.', 'external-files-in-media-library' ), esc_html( $value ) ) );
		}

		// return the value.
		return $value;
	}

	/**
	 * Update the schedule if interval has been changed.
	 *
	 * @param string|null $value The given value for the interval.
	 *
	 * @return string
	 */
	public function update_interval_setting( string|null $value ): string {
		// check if value is null.
		if ( is_null( $value ) ) {
			$value = '';
		}

		// get check files-schedule-object.
		$check_files_schedule = new Check_Files();

		// if new value is 'eml_disable_check' remove the schedule.
		if ( 'eml_disable_check' === $value ) {
			$check_files_schedule->delete();
		} else {
			// set the new interval.
			$check_files_schedule->set_interval( $value );

			// reset the schedule.
			$check_files_schedule->reset();
		}

		// return the new value to save it via WP.
		return $value;
	}

	/**
	 * Validate allowed mime-types.
	 *
	 * @param ?array<string> $values List of mime-types to check.
	 *
	 * @return       array<string>
	 * @noinspection PhpUnused
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
	 * Set capabilities after saving settings.
	 *
	 * @param array<string>|null $values The setting.
	 *
	 * @return array<string>
	 * @noinspection PhpUnused
	 */
	public function set_capabilities( ?array $values ): array {
		// check if value is not an array.
		if ( ! is_array( $values ) ) {
			$values = array();
		}

		// set capabilities.
		Helper::set_capabilities( $values );

		// return given value.
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
			<?php
			$log->views();
			$log->display();
			?>
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
		echo esc_html__( 'These settings only apply to files that are provided via http. Files from other protocols (such as FTP) are generally only saved locally without a proxy.', 'external-files-in-media-library' );
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
			// bail if setting is not a Setting object.
			if ( ! $settings_obj instanceof Setting ) {
				continue;
			}

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
	 * Check the change of proxy-setting.
	 *
	 * @param string $new_value The old value.
	 * @param string $old_value The new value.
	 *
	 * @return int
	 */
	public function update_proxy_setting( string $new_value, string $old_value ): int {
		// convert the values.
		$new_value_int = absint( $new_value );
		$old_value_int = absint( $old_value );

		// bail if value has not been changed.
		if ( $new_value_int === $old_value_int ) {
			return $old_value_int;
		}

		// show hint to reset the proxy-cache.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_proxy_changed' );
		$transient_obj->set_message( '<strong>' . __( 'The proxy state has been changed.', 'external-files-in-media-library' ) . '</strong> ' . __( 'We recommend emptying the cache of the proxy. Click on the button below to do this.', 'external-files-in-media-library' ) . '<br><a href="#" class="button button-primary easy-dialog-for-wordpress" data-dialog="' . esc_attr( $this->get_proxy_reset_dialog() ) . '">' . esc_html__( 'Reset now', 'external-files-in-media-library' ) . '</a>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// return the new value.
		return $new_value_int;
	}

	/**
	 * Return the proxy reset dialog configuration.
	 *
	 * @return string
	 */
	private function get_proxy_reset_dialog(): string {
		$dialog_config = array(
			'title'   => __( 'Reset proxy cache', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the following button to reset the proxy cache.', 'external-files-in-media-library' ) . '</strong></p>',
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
}
