<?php
/**
 * This file defines the settings for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\MultiSelect;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Number;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Select;
use ExternalFilesInMediaLibrary\Plugin\Tables\Logs;

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
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the settings.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'add_settings' ) );
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
		// get and configure the basic settings object.
		$settings_obj = Settings\Settings::get_instance();
		$settings_obj->set_menu_title( __( 'External files in Medias Library', 'external-files-in-media-library' ) );
		$settings_obj->set_title( __( 'Settings for External files in Media Library', 'external-files-in-media-library' ) );
		$settings_obj->set_menu_slug( $this->get_menu_slug() );
		$settings_obj->set_menu_parent_slug( $this->get_php_page() );

		// add the settings tabs.
		$general_settings_tab = $settings_obj->add_tab( 'eml_general' );
		$general_settings_tab->set_name( 'eml_general' );
		$general_settings_tab->set_title( __( 'General Settings', 'external-files-in-media-library' ) );

		// set description for disabling the attachment pages.
		$description = __( 'Each file in media library has a attachment page which could be called in frontend. With this option you can disable this attachment page for files with URLs.', 'external-files-in-media-library' );
		if ( method_exists( 'WPSEO_Options', 'get' ) ) {
			$description = __( 'This is handled by Yoast SEO.', 'external-files-in-media-library' );
		}

		// add section.
		$general_settings_tab_main = $general_settings_tab->add_section( 'settings_section_main' );
		$general_settings_tab_main->set_title( __( 'General Settings', 'external-files-in-media-library' ) );
		$general_settings_tab_main->set_setting( $settings_obj );

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_disable_attachment_pages' );
		$setting->set_section( $general_settings_tab_main );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Disable the attachment page for URL-files', 'external-files-in-media-library' ) );
		$field->set_description( $description );
		$field->set_readonly( method_exists( 'WPSEO_Options', 'get' ) );
		$setting->set_field( $field );

		// interval-setting for automatic file-check.
		$values = array(
			'eml_disable_check' => __( 'Disable the check', 'external-files-in-media-library' ),
		);
		foreach ( wp_get_schedules() as $name => $interval ) {
			$values[ $name ] = $interval['display'];
		}

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_check_interval' );
		$setting->set_section( $general_settings_tab_main );
		$setting->set_type( 'string' );
		$setting->set_default( 'daily' );
		$field = new Select();
		$field->set_title( __( 'Set interval for file-check', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines the time interval in which files with URLs are automatically checked for its availability.', 'external-files-in-media-library' ) );
		$field->set_options( $values );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$setting->set_field( $field );

		// get possible mime types.
		$mime_types = array();
		foreach ( Helper::get_possible_mime_types() as $mime_type => $settings ) {
			$mime_types[ $mime_type ] = $settings['label'];
		}

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_allowed_mime_types' );
		$setting->set_section( $general_settings_tab_main );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'application/pdf', 'image/jpeg', 'image/png' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Select allowed mime-types', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by the external hook-documentation-URL */
		$field->set_description( sprintf( __( 'Choose the mime-types you wish to allow as external URL. If you change this setting, already used external files will not change their accessibility in frontend. If you miss a mime-type, take a look <a href="%1$s" target="_blank">at our hooks (opens new window)</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_hook_url() ) ) );
		$field->set_options( $mime_types );
		$field->set_sanitize_callback( array( $this, 'validate_allowed_mime_types' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_delete_on_deinstallation' );
		$setting->set_section( $general_settings_tab_main );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Delete all data on uninstallation', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all URL-files will be deleted during deinstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_switch_on_uninstallation' );
		$setting->set_section( $general_settings_tab_main );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Switch external files  to local hosting during uninstallation', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all external files will be saved local during uninstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );

		// add section.
		$general_settings_tab_files = $general_settings_tab->add_section( 'settings_section_add_files' );
		$general_settings_tab_files->set_title( __( 'Adding files', 'external-files-in-media-library' ) );
		$general_settings_tab_files->set_setting( $settings_obj );

		// get user roles.
		$user_roles = array();
		if ( function_exists( 'wp_roles' ) && ! empty( wp_roles()->roles ) ) {
			foreach ( wp_roles()->roles as $slug => $role ) {
				$user_roles[ $slug ] = $role['name'];
			}
		}

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_allowed_roles' );
		$setting->set_section( $general_settings_tab_files );
		$setting->set_type( 'array' );
		$setting->set_default( array( 'administrator', 'editor' ) );
		$field = new MultiSelect();
		$field->set_title( __( 'Select user roles', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select roles which should be allowed to add external files.', 'external-files-in-media-library' ) );
		$field->set_options( $user_roles );
		$field->set_sanitize_callback( array( $this, 'set_capabilities' ) );
		$setting->set_field( $field );

		$users = array();
		foreach ( get_users() as $user ) {
			$users[ $user->ID ] = $user->display_name;
		}

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_user_assign' );
		$setting->set_section( $general_settings_tab_files );
		$setting->set_type( 'integer' );
		$setting->set_default( Helper::get_first_administrator_user() );
		$field = new Select();
		$field->set_title( __( 'User new files should be assigned to', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This is only a fallback if the actual user is not available (e.g. via CLI-import). New files are normally assigned to the user who add them.', 'external-files-in-media-library' ) );
		$field->set_options( $users );
		$setting->set_field( $field );

		// add section.
		$general_settings_tab_images = $general_settings_tab->add_section( 'settings_section_images' );
		$general_settings_tab_images->set_title( __( 'Images Settings', 'external-files-in-media-library' ) );
		$general_settings_tab_images->set_setting( $settings_obj );

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_images_mode' );
		$setting->set_section( $general_settings_tab_images );
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

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_proxy' );
		$setting->set_section( $general_settings_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable proxy for images', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This option is only available if images are hosted external. If this option is disabled, external images will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the images locally.', 'external-files-in-media-library' ) );
		$field->set_readonly( 'external' !== get_option( 'eml_images_mode', '' ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $general_settings_tab->add_setting( 'eml_proxy_max_age' );
		$setting->set_section( $general_settings_tab_images );
		$setting->set_type( 'integer' );
		$setting->set_default( 24 );
		$field = new Number();
		$field->set_title( __( 'Max age for cached images in proxy in hours', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Defines how long images, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );

		// add the advanced tab.
		$general_advanced_tab = $settings_obj->add_tab( 'eml_advanced' );
		$general_advanced_tab->set_title( __( 'Advanced', 'external-files-in-media-library' ) );

		// add section.
		$general_settings_tab_advanced = $general_advanced_tab->add_section( 'settings_section_advanced' );
		$general_settings_tab_advanced->set_title( __( 'Advanced settings', 'external-files-in-media-library' ) );
		$general_settings_tab_advanced->set_setting( $settings_obj );

		// add setting.
		$setting = $general_advanced_tab->add_setting( 'eml_timeout' );
		$setting->set_section( $general_settings_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( 30 );
		$setting->set_field(
			array(
				'type'        => 'Number',
				'title'       => __( 'Max. Timeout in seconds', 'external-files-in-media-library' ),
				'description' => __( 'Defines the maximum timeout for any external request for files.', 'external-files-in-media-library' ),
			)
		);

		// add setting.
		$setting = $general_advanced_tab->add_setting( 'eml_log_mode' );
		$setting->set_section( $general_settings_tab_advanced );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Select();
		$field->set_title( __( 'Log-mode', 'external-files-in-media-library' ) );
		$field->set_options(
			array(
				'0' => __( 'normal', 'external-files-in-media-library' ),
				'1' => __( 'log warnings', 'external-files-in-media-library' ),
				'2' => __( 'log all', 'external-files-in-media-library' ),
			)
		);
		$setting->set_field( $field );

		// add the logs tab.
		$general_logs_tab = $settings_obj->add_tab( 'eml_logs' );
		$general_logs_tab->set_title( __( 'Logs', 'external-files-in-media-library' ) );
		$general_logs_tab->set_callback( array( $this, 'show_logs' ) );

		// add the helper tab.
		$general_helper_tab = $settings_obj->add_tab( 'eml_helper' );
		$general_helper_tab->set_title( __( 'Questions? Check our forum', 'external-files-in-media-library' ) );
		$general_helper_tab->set_url( Helper::get_plugin_support_url() );
		$general_helper_tab->set_url_target( '_blank' );
		$general_helper_tab->set_tab_class( 'nav-tab-help' );

		// set the default tab.
		$settings_obj->set_default_tab( $general_settings_tab );

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
		if ( empty( $value ) ) {
			return '';
		}

		// disable the check.
		if ( 'eml_disable_check' === $value ) {
			wp_clear_scheduled_hook( 'eml_check_files' );
			return $value;
		}

		// check if given interval exist.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $value ] ) ) {
			add_settings_error( 'eml_check_files', 'eml_check_files', __( 'The given interval does not exists.', 'external-files-in-media-library' ) );
			return '';
		}

		// change the interval.
		wp_clear_scheduled_hook( 'eml_check_files' );
		wp_schedule_event( time(), $value, 'eml_check_files' );

		// return value for option-value.
		return $value;
	}

	/**
	 * Validate allowed mime-types.
	 *
	 * @param ?array $values List of mime-types to check.
	 *
	 * @return       ?array
	 * @noinspection PhpUnused
	 */
	public function validate_allowed_mime_types( ?array $values ): ?array {
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
	 * @param array|null $values The setting.
	 *
	 * @return array
	 * @noinspection PhpUnused
	 */
	public function set_capabilities( ?array $values ): array {
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
			include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}
		$log = new Logs();
		$log->prepare_items();
		?>
		<div class="wrap">
			<div id="icon-users" class="icon32"></div>
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
		// add all settings.
		$this->add_settings();

		// run the installation of them.
		Settings\Settings::get_instance()->activation();
	}
}
