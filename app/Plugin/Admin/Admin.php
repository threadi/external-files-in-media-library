<?php
/**
 * This file contains an object which handles the admin tasks of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Forms;
use ExternalFilesInMediaLibrary\ExternalFiles\Tables;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Logs;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Initialize the admin tasks for this plugin.
 */
class Admin {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Admin
	 */
	private static ?Admin $instance = null;

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
	 * @return Admin
	 */
	public static function get_instance(): Admin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the backend forms for external files.
		Forms::get_instance()->init();

		// initialize the table extensions.
		Tables::get_instance()->init();

		// initialize the files object.
		Files::get_instance()->init();

		// add admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dialog_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'trigger_mime_warning' ) );
		add_action( 'admin_init', array( $this, 'check_php' ) );

		// use our own hooks.
		add_action( 'eml_admin_settings_tab_general', array( $this, 'get_settings_tab_general' ) );
		add_action( 'eml_admin_settings_tab_logs', array( $this, 'get_settings_tab_logs' ) );

		// misc.
		add_filter( 'plugin_action_links_' . plugin_basename( EML_PLUGIN ), array( $this, 'add_setting_link' ) );
	}

	/**
	 * Register the settings for this plugin.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		global $wp_roles;

		/**
		 * General Section.
		 */
		add_settings_section(
			'settings_section_main',
			__( 'General Settings', 'external-files-in-media-library' ),
			'__return_true',
			'eml_settings_page'
		);

		// set description for disabling the attachment pages.
		$description = __( 'Each file in media library has a attachment page which could be called in frontend. With this option you can disable this attachment page for files with URLs.', 'external-files-in-media-library' );
		if ( method_exists( 'WPSEO_Options', 'get' ) ) {
			$description = __( 'This is handled by Yoast SEO.', 'external-files-in-media-library' );
		}

		// Disable the attachment page.
		add_settings_field(
			'eml_disable_attachment_pages',
			__( 'Disable the attachment page for URL-files', 'external-files-in-media-library' ),
			array( $this, 'get_checkbox_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for'   => 'eml_disable_attachment_pages',
				'fieldId'     => 'eml_disable_attachment_pages',
				/* translators: %1$s is replaced with "string" */
				'description' => $description,
				'readonly'    => false !== method_exists( 'WPSEO_Options', 'get' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_disable_attachment_pages', array( 'sanitize_callback' => array( $this, 'validate_checkbox' ) ) );

		// interval-setting for automatic file-check.
		$values = array(
			'eml_disable_check' => __( 'Disable the check', 'external-files-in-media-library' ),
		);
		foreach ( wp_get_schedules() as $name => $interval ) {
			$values[ $name ] = $interval['display'];
		}
		add_settings_field(
			'eml_check_interval',
			__( 'Set interval for file-check', 'external-files-in-media-library' ),
			array( $this, 'get_select_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for'   => 'eml_check_interval',
				'fieldId'     => 'eml_check_interval',
				'description' => __( 'Defines the time interval in which files with URLs are automatically checked for its availability.', 'external-files-in-media-library' ),
				'values'      => $values,
			)
		);
		register_setting( 'eml_settings_group', 'eml_check_interval', array( 'sanitize_callback' => array( $this, 'validate_interval_select' ) ) );

		// get possible mime types.
		$mime_types = array();
		foreach ( Helper::get_possible_mime_types() as $mime_type => $settings ) {
			$mime_types[ $mime_type ] = $settings['label'];
		}

		// select allowed mime-types.
		add_settings_field(
			'eml_allowed_mime_types',
			__( 'Select allowed mime-types', 'external-files-in-media-library' ),
			array( $this, 'get_multiselect_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for'   => 'eml_allowed_mime_types',
				'fieldId'     => 'eml_allowed_mime_types',
				'values'      => $mime_types,
				/* translators: %1$s will be replaced by the external hook-documentation-URL */
				'description' => sprintf( __( 'Choose the mime-types you wish to allow as external URL. If you change this setting, already used external files will not change their accessibility in frontend. If you miss a mime-type, take a look <a href="%1$s" target="_blank">at our hooks (opens new window)</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_hook_url() ) ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_allowed_mime_types', array( 'sanitize_callback' => array( $this, 'validate_allowed_mime_types' ) ) );

		// Log-mode.
		add_settings_field(
			'eml_log_mode',
			__( 'Log-mode', 'external-files-in-media-library' ),
			array( $this, 'get_select_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for' => 'eml_log_mode',
				'fieldId'   => 'eml_log_mode',
				'values'    => array(
					'0' => __( 'normal', 'external-files-in-media-library' ),
					'1' => __( 'log warnings', 'external-files-in-media-library' ),
					'2' => __( 'log all', 'external-files-in-media-library' ),
				),
			)
		);
		register_setting( 'eml_settings_group', 'eml_log_mode' );

		// Delete all data on uninstallation.
		add_settings_field(
			'eml_delete_on_deinstallation',
			__( 'Delete all data on uninstallation', 'external-files-in-media-library' ),
			array( $this, 'get_checkbox_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for'   => 'eml_delete_on_deinstallation',
				'fieldId'     => 'eml_delete_on_deinstallation',
				'description' => __( 'If this option is enabled all URL-files will be deleted during deinstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_delete_on_deinstallation', array( 'sanitize_callback' => array( $this, 'validate_checkbox' ) ) );

		// Switch all external files to local hosting during uninstallation.
		add_settings_field(
			'eml_switch_on_uninstallation',
			__( 'Switch external files  to local hosting during uninstallation', 'external-files-in-media-library' ),
			array( $this, 'get_checkbox_field' ),
			'eml_settings_page',
			'settings_section_main',
			array(
				'label_for'   => 'eml_switch_on_uninstallation',
				'fieldId'     => 'eml_switch_on_uninstallation',
				'description' => __( 'If this option is enabled all external files will be saved local during uninstallation of this plugin.', 'external-files-in-media-library' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_switch_on_uninstallation', array( 'sanitize_callback' => array( $this, 'validate_checkbox' ) ) );

		/**
		 * Files Section.
		 */
		add_settings_section(
			'settings_section_add_files',
			__( 'Adding files', 'external-files-in-media-library' ),
			'__return_true',
			'eml_settings_page'
		);

		// get user roles.
		$user_roles = array();
		if ( ! empty( $wp_roles->roles ) ) {
			foreach ( $wp_roles->roles as $slug => $role ) {
				$user_roles[ $slug ] = $role['name'];
			}
		}

		// Set roles to allow adding external URLs.
		add_settings_field(
			'eml_allowed_roles',
			__( 'Select user roles', 'external-files-in-media-library' ),
			array( $this, 'get_multiselect_field' ),
			'eml_settings_page',
			'settings_section_add_files',
			array(
				'label_for'   => 'eml_allowed_roles',
				'fieldId'     => 'eml_allowed_roles',
				'values'      => $user_roles,
				'description' => __( 'Select roles which should be allowed to add external files.', 'external-files-in-media-library' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_allowed_roles', array( 'sanitize_callback' => array( $this, 'set_capability' ) ) );

		$users = array();
		foreach ( get_users() as $user ) {
			$users[ $user->ID ] = $user->display_name;
		}

		// User new files should be assigned to.
		add_settings_field(
			'eml_user_assign',
			__( 'User new files should be assigned to', 'external-files-in-media-library' ),
			array( $this, 'get_select_field' ),
			'eml_settings_page',
			'settings_section_add_files',
			array(
				'label_for'   => 'eml_user_assign',
				'fieldId'     => 'eml_user_assign',
				'description' => __( 'This is only a fallback if the actual user is not available (e.g. via CLI-import). New files are normally assigned to the user who add them.', 'external-files-in-media-library' ),
				'values'      => $users,
			)
		);
		register_setting( 'eml_settings_group', 'eml_user_assign' );

		/**
		 * Images Section.
		 */
		add_settings_section(
			'settings_section_images',
			__( 'Images Settings', 'external-files-in-media-library' ),
			'__return_true',
			'eml_settings_page'
		);

		// Image-mode.
		add_settings_field(
			'eml_images_mode',
			__( 'Mode for image handling', 'external-files-in-media-library' ),
			array( $this, 'get_select_field' ),
			'eml_settings_page',
			'settings_section_images',
			array(
				'label_for'   => 'eml_images_mode',
				'fieldId'     => 'eml_images_mode',
				'description' => __( 'Defines how external images are handled.', 'external-files-in-media-library' ),
				'values'      => array(
					'external' => __( 'host them extern', 'external-files-in-media-library' ),
					'local'    => __( 'download and host them local', 'external-files-in-media-library' ),
				),
			)
		);
		register_setting( 'eml_settings_group', 'eml_images_mode' );

		// Enable proxy in frontend.
		add_settings_field(
			'eml_proxy',
			__( 'Enable proxy for images', 'external-files-in-media-library' ),
			array( $this, 'get_checkbox_field' ),
			'eml_settings_page',
			'settings_section_images',
			array(
				'label_for'   => 'eml_proxy',
				'fieldId'     => 'eml_proxy',
				'description' => __( 'This option is only available if images are hosted external. If this option is disabled, external images will be embedded with their external URL. To prevent privacy protection issue you could enable this option to load the images locally.', 'external-files-in-media-library' ),
				'readonly'    => 'external' !== get_option( 'eml_images_mode', '' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_proxy', array( 'sanitize_callback' => array( $this, 'validate_checkbox' ) ) );

		// Max age for cached files.
		add_settings_field(
			'eml_proxy_max_age',
			__( 'Max age for cached images in proxy in hours', 'external-files-in-media-library' ),
			array( $this, 'get_number_field' ),
			'eml_settings_page',
			'settings_section_images',
			array(
				'label_for'   => 'eml_proxy_max_age',
				'fieldId'     => 'eml_proxy_max_age',
				'description' => __( 'Defines how long images, which are loaded via our own proxy, are saved locally. After this time their cache will be renewed.', 'external-files-in-media-library' ),
				'readonly'    => 'external' !== get_option( 'eml_images_mode', '' ),
			)
		);
		register_setting( 'eml_settings_group', 'eml_proxy_max_age', array( 'sanitize_callback' => array( $this, 'validate_number' ) ) );
	}

	/**
	 * Add settings-page in admin-menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'Settings for External files in Media Library', 'external-files-in-media-library' ),
			__( 'External files in Medias Library', 'external-files-in-media-library' ),
			'manage_options',
			'eml_settings',
			array( $this, 'get_admin_settings_page' )
		);
	}

	/**
	 * Define settings-page for this plugin.
	 *
	 * @return void
	 */
	public function get_admin_settings_page(): void {
		// check nonce.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'eml-settings' ) ) {
			// redirect user back.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// check user capabilities.
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}

		// get the active tab from the $_GET param.
		$default_tab = null;
		$tab         = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab;

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="?page=eml_settings" class="nav-tab
			<?php
			if ( null === $tab ) :
				?>
				nav-tab-active
				<?php
				endif;
			?>
			"><?php esc_html_e( 'General Settings', 'external-files-in-media-library' ); ?></a>
				<a href="?page=eml_settings&tab=logs" class="nav-tab
			<?php
			if ( 'logs' === $tab ) :
				?>
				nav-tab-active
				<?php
				endif;
			?>
			"><?php esc_html_e( 'Logs', 'external-files-in-media-library' ); ?></a>
				<a href="https://wordpress.org/support/plugin/external-files-in-media-library/" class="nav-tab nav-tab-help" target="_blank"><?php esc_html_e( 'Questions? Check our forum', 'external-files-in-media-library' ); ?></a>
			</nav>

			<div class="tab-content">
				<?php
				$tab = ( null === $tab ? 'general' : $tab );
				/**
				 * Run tasks to show settings.
				 *
				 * @since 1.0.0 Available since 1.0.0.
				 */
				do_action( 'eml_admin_settings_tab_' . $tab );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Add general settings for this plugin.
	 *
	 * @return void
	 */
	public function get_settings_tab_general(): void {
		// check user capabilities.
		if ( false === current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<form method="POST" action="<?php echo esc_url( get_admin_url() ); ?>options.php">
			<?php
			settings_fields( 'eml_settings_group' );
			do_settings_sections( 'eml_settings_page' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Add general settings for this plugin.
	 *
	 * @return void
	 */
	public function get_settings_tab_logs(): void {
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
			<?php $log->display(); ?>
		</div>
		<?php
	}

	/**
	 * Show a checkbox in settings.
	 *
	 * @param array $attr List of settings.
	 *
	 * @return void
	 */
	public function get_checkbox_field( array $attr ): void {
		if ( ! empty( $attr['fieldId'] ) ) {
			// get title.
			$title = '';
			if ( isset( $attr['title'] ) ) {
				$title = $attr['title'];
			}

			// set readonly.
			$readonly = '';
			if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
			}

			?>
			<input type="checkbox" id="<?php echo esc_attr( $attr['fieldId'] ); ?>"
					name="<?php echo esc_attr( $attr['fieldId'] ); ?>"
					value="1"
				<?php
				echo esc_attr( $readonly );
				echo 1 === absint( get_option( $attr['fieldId'], 0 ) ) ? ' checked="checked"' : '';
				?>
					class="eml-field-width"
					title="<?php echo esc_attr( $title ); ?>"
			>
			<?php

			// show optional description for this checkbox.
			if ( ! empty( $attr['description'] ) ) {
				echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
			}
		}
	}

	/**
	 * Show a number-field in settings.
	 *
	 * @param array $attr List of settings.
	 *
	 * @return void
	 */
	public function get_number_field( array $attr ): void {
		if ( ! empty( $attr['fieldId'] ) ) {
			// get title.
			$title = '';
			if ( isset( $attr['title'] ) ) {
				$title = $attr['title'];
			}

			// get value.
			$value = get_option( $attr['fieldId'], 0 );

			// set readonly.
			$readonly = '';
			if ( isset( $attr['readonly'] ) && false !== $attr['readonly'] ) {
				$readonly = ' disabled="disabled"';
			}

			?>
			<input type="number" id="<?php echo esc_attr( $attr['fieldId'] ); ?>"
					name="<?php echo esc_attr( $attr['fieldId'] ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					step="1"
					min="0"
					max="10000"
					class="eml-field-width"
					title="<?php echo esc_attr( $title ); ?>"
				<?php
				echo esc_attr( $readonly );
				?>
			>
			<?php

			// show optional description for this checkbox.
			if ( ! empty( $attr['description'] ) ) {
				echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
			}
		}
	}

	/**
	 * Validate the checkbox-value.
	 *
	 * @param ?int $value The checkbox-value.
	 *
	 * @return       ?int
	 * @noinspection PhpUnused
	 */
	public function validate_checkbox( ?int $value ): ?int {
		return absint( $value );
	}

	/**
	 * Show select-field with given values.
	 *
	 * @param array $attr   Settings as array.
	 *
	 * @return void
	 */
	public function get_select_field( array $attr ): void {
		if ( ! empty( $attr['fieldId'] ) && ! empty( $attr['values'] ) ) {
			// get value from config.
			$value = get_option( $attr['fieldId'], '' );

			// get title.
			$title = '';
			if ( isset( $attr['title'] ) ) {
				$title = $attr['title'];
			}

			?>
			<select id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>" class="eml-field-width" title="<?php echo esc_attr( $title ); ?>">
				<?php
				foreach ( $attr['values'] as $key => $label ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php echo ( $value === (string) $key ? ' selected="selected"' : '' ); ?>><?php echo esc_html( $label ); ?></option>
					<?php
				}
				?>
			</select>
			<?php
			if ( ! empty( $attr['description'] ) ) {
				echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
			}
		} elseif ( empty( $attr['values'] ) && ! empty( $attr['noValues'] ) ) {
			echo '<p>' . esc_html( $attr['noValues'] ) . '</p>';
		}
	}

	/**
	 * Validate the interval-selection-value.
	 *
	 * @param string $value Interval-setting.
	 *
	 * @return       string
	 * @noinspection PhpUnused
	 */
	public function validate_interval_select( string $value ): string {
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

		// return value    for option-value.
		return $value;
	}

	/**
	 * Show multiselect-field with given values.
	 *
	 * @param array $attr List of settings.
	 *
	 * @return       void
	 * @noinspection PhpUnused
	 */
	public function get_multiselect_field( array $attr ): void {
		if ( ! empty( $attr['fieldId'] ) && ! empty( $attr['values'] ) ) {
			// get value from config.
			$actual_values = get_option( $attr['fieldId'], array() );
			if ( empty( $actual_values ) ) {
				$actual_values = array();
			}

			// if $actualValues is a string, convert it.
			if ( ! is_array( $actual_values ) ) {
				$actual_values = explode( ',', $actual_values );
			}

			// use values as key if set.
			if ( ! empty( $attr['useValuesAsKeys'] ) ) {
				$new_array = array();
				foreach ( $attr['values'] as $value ) {
					$new_array[ $value ] = $value;
				}
				$attr['values'] = $new_array;
			}

			// get title.
			$title = '';
			if ( isset( $attr['title'] ) ) {
				$title = $attr['title'];
			}

			?>
			<select id="<?php echo esc_attr( $attr['fieldId'] ); ?>" name="<?php echo esc_attr( $attr['fieldId'] ); ?>[]" multiple class="eml-field-width" title="<?php echo esc_attr( $title ); ?>">
				<?php
				foreach ( $attr['values'] as $key => $value ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>"<?php echo in_array( $key, $actual_values, true ) ? ' selected="selected"' : ''; ?>><?php echo esc_html( $value ); ?></option>
					<?php
				}
				?>
			</select>
			<?php
			if ( ! empty( $attr['description'] ) ) {
				echo '<p>' . wp_kses_post( $attr['description'] ) . '</p>';
			}
		}
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
	public function set_capability( ?array $values ): array {
		if ( ! is_array( $values ) ) {
			$values = array();
		}

		// set capabilities.
		Helper::set_capabilities( $values );

		// return given value.
		return $values;
	}


	/**
	 * Checks on each admin-initialization.
	 *
	 * @return void
	 */
	public function trigger_mime_warning(): void {
		// bail if mime types are allowed.
		if ( ! empty( Helper::get_allowed_mime_types() ) ) {
			return;
		}

		// trigger warning as no mime types are allowed.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_dismissible_days( 14 );
		$transient_obj->set_name( 'eml_missing_mime_types' );
		$transient_obj->set_message( __( 'External files could not be used as no mime-types are allowed.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'error' );
		$transient_obj->save();
	}

	/**
	 * Validate the value from number-field.
	 *
	 * @param string|null $value Variable to validate.
	 * @return int
	 * @noinspection PhpUnused
	 */
	public function validate_number( string|null $value ): int {
		return absint( $value );
	}

	/**
	 * Add WP Dialog Easy scripts in wp-admin.
	 */
	public function add_dialog_scripts(): void {
		// define paths: adjust if necessary.
		$path = trailingslashit( plugin_dir_path( EML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';
		$url  = trailingslashit( plugin_dir_url( EML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// embed the dialog-components JS-script.
		$script_asset_path = $path . 'build/index.asset.php';

		// bail if file does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'easy-dialog-for-wordpress',
			$url . 'build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// embed the dialog-components CSS-script.
		$admin_css      = $url . 'build/style-index.css';
		$admin_css_path = $path . 'build/style-index.css';
		wp_enqueue_style(
			'easy-dialog-for-wordpress',
			$admin_css,
			array( 'wp-components' ),
			filemtime( $admin_css_path )
		);
	}

	/**
	 * Check if website is using a valid SSL and show warning if not.
	 *
	 * @return void
	 */
	public function check_php(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if WordPress is in developer mode.
		if ( function_exists( 'wp_is_development_mode' ) && wp_is_development_mode( 'plugin' ) ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_php_hint' ) );
			return;
		}

		// bail if PHP >= 8.1 is used.
		if ( version_compare( PHP_VERSION, '8.1', '>' ) ) {
			return;
		}

		// show hint for necessary configuration to restrict access to application files.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_php_hint' );
		$transient_obj->set_dismissible_days( 90 );
		$transient_obj->set_message( '<strong>' . __( 'Your website is using an outdated PHP-version!', 'external-files-in-media-library' ) . '</strong><br>' . __( 'Future versions of <i>External Files in Media Library</i> will no longer be compatible with PHP 8.0 or older. These versions <a href="https://www.php.net/supported-versions.php" target="_blank">are outdated</a> since December 2023. To continue using the plugins new features, please update your PHP version.', 'external-files-in-media-library' ) . '<br>' . __( 'Talk to your hosting support team about this.', 'external-files-in-media-library' ) );
		$transient_obj->save();
	}

	/**
	 * Add link to settings in plugin list.
	 *
	 * @param array $links List of links.
	 *
	 * @return array
	 */
	public function add_setting_link( array $links ): array {
		// add link to settings.
		$links[] = "<a href='" . esc_url( Helper::get_config_url() ) . "'>" . __( 'Settings', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of links.
		return $links;
	}
}
