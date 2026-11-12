<?php
/**
 * File to handle service objects.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easySettingsForWordPress\Fields\Select;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class Service_Base extends Directory_Listing_Base {
	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = '';

	/**
	 * Marker for sync support (false to enable it).
	 *
	 * @var bool
	 */
	protected bool $sync_disabled = false;

	/**
	 * The user.
	 *
	 * @var WP_User|false
	 */
	private WP_User|false $user = false;

	/**
	 * Marker whether values of fields could be defined via constants (true) or not (false).
	 *
	 * The constants are used for the fields of the service object and for the fields of each protocol,
	 * which is using this service (see @Protocol_Base::get_fields()).
	 *
	 * @var bool
	 */
	protected bool $fields_via_constants = true;

	/**
	 * Name of the field, which contains the URL of the server this service connects to.
	 *
	 * If set, the values of the constants are only used for connections to the host defined
	 * by the constant for this field.
	 *
	 * @var string
	 */
	protected string $constants_host_field = '';

	/**
	 * List of fields, which identify the target of this service besides the server (e.g. a bucket).
	 *
	 * If set, the values of the constants are only used for fields, whose values for these fields are empty or
	 * equal to the values of the constants. Saved external sources with other values are not usable.
	 *
	 * @var array<int,string>
	 */
	protected array $constants_target_fields = array();

	/**
	 * Marker whether the values of the constants are values of the fields of this service (true) or not (false).
	 *
	 * Services, which use other credentials than their fields (e.g. API credentials for OAuth), set this to false.
	 * The values of their constants are then never added to any fields.
	 *
	 * @var bool
	 */
	protected bool $constants_in_fields = true;

	/**
	 * Marker whether the fields have been set from outside (e.g. from a request or a saved external source).
	 *
	 * @var bool
	 */
	private bool $fields_set_externally = false;

	/**
	 * Marker whether constants should not be used for the fields (used to load the field definitions).
	 *
	 * @var bool
	 */
	private bool $constants_disabled = false;

	/**
	 * The definitions of the fields without values from constants (cache).
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private ?array $field_definitions = null;

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'        => '',
		'github_user' => '',
		'github_slug' => '',
		'plugin_slug' => '',
	);

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add settings.
		add_action( 'init', array( $this, 'add_base_settings' ), 20 );
		add_action( 'personal_options_update', array( $this, 'save_user_settings' ) );

		// use our own hooks.
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// use the mode "global" if the credentials are defined via constants.
		add_filter( 'option_eml_' . $this->get_name() . '_credentials_vault', array( $this, 'force_global_mode_via_constants' ) );
		add_filter( 'pre_update_option_eml_' . $this->get_name() . '_credentials_vault', array( $this, 'prevent_mode_change_via_constants' ) );
	}

	/**
	 * Return "global" as mode, if the credentials of this service are defined via constants.
	 *
	 * @param mixed $value The saved mode.
	 *
	 * @return mixed
	 */
	public function force_global_mode_via_constants( mixed $value ): mixed {
		// bail if mode is not forced by constants.
		if ( ! $this->is_mode_forced_by_constants() ) {
			return $value;
		}

		// return the mode used with constants.
		return $this->get_mode_for_constants();
	}

	/**
	 * Return the mode, which is used if the credentials of this service are defined via constants.
	 *
	 * @return string
	 */
	protected function get_mode_for_constants(): string {
		return 'global';
	}

	/**
	 * Prevent saving of the mode, if it is forced by constants, so the saved mode is used again after removing them.
	 *
	 * @param mixed $value The new value.
	 *
	 * @return mixed
	 */
	public function prevent_mode_change_via_constants( mixed $value ): mixed {
		// bail if mode is not forced by constants.
		if ( ! $this->is_mode_forced_by_constants() ) {
			return $value;
		}

		// return the saved mode without our filter.
		return $this->get_saved_mode();
	}

	/**
	 * Return whether the mode is forced to "global", as the credentials are defined via constants.
	 *
	 * @return bool
	 */
	public function is_mode_forced_by_constants(): bool {
		// bail if constants are not used at the moment (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return false;
		}

		// return whether all required values are defined via constants.
		return ! empty( $this->get_constant_values() );
	}

	/**
	 * Return the mode saved in the database, without the mode forced by constants.
	 *
	 * @return string
	 */
	private function get_saved_mode(): string {
		// get the option name.
		$option_name = 'eml_' . $this->get_name() . '_credentials_vault';

		// remove our filter for the mode temporarily, if it is set.
		$has_filter = false !== has_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		if ( $has_filter ) {
			remove_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		}

		// get the saved mode.
		$mode = get_option( $option_name, 'manually' );

		// add our filter again.
		if ( $has_filter ) {
			add_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		}

		// return the saved mode.
		return is_string( $mode ) ? $mode : 'manually';
	}

	/**
	 * Add this object to the list of directory listing objects.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		$directory_listing_objects[] = $this;
		return $directory_listing_objects;
	}

	/**
	 * Add main settings for each service.
	 *
	 * @return void
	 */
	public function add_base_settings(): void {
		// bail if not subtab slug is given.
		if ( empty( $this->get_settings_subtab_slug() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( $settings_obj->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( 'services' );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add a new tab for settings.
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 90 );
		$tab->set_title( $this->get_label() );

		// add a section for file statistics.
		$section = $tab->add_section( 'section_' . $this->get_name() . '_main', 10 );
		/* translators: %1$s will be replaced by the service title. */
		$section->set_title( sprintf( __( 'Settings for %1$s', 'external-files-in-media-library' ), $this->get_label() ) );
		$section->set_callback( array( $this, 'show_hint_for_permissions' ) );

		// show mode selection if this service is using credentials.
		if ( $this->has_credentials() ) {
			// add setting where to save the credentials for this service, if enabled.
			$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_credentials_vault' );
			$setting->set_type( 'string' );
			$setting->set_default( 'manually' );
			$setting->set_section( $section );
			$field = new Select( $settings_obj );
			$field->set_title( __( 'Location where credentials are stored', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by the service title (e.g. DropBox). */
			$field->set_description( sprintf( __( 'This setting determines where %1$s access data is stored. Depending on this setting, the access data must be entered each time a connection is made, or it can be stored for each user or all users in this project. This only affects the import of files. This does not affect files in the media library.', 'external-files-in-media-library' ), $this->get_label() ) );
			$field->set_options( $this->get_modes() );

			// fix the mode to "global", if the credentials are defined via constants.
			if ( $this->is_mode_forced_by_constants() ) {
				$field->set_readonly( true );
				/* translators: %1$s will be replaced by the service title (e.g. DropBox). */
				$field->set_description( sprintf( __( 'The access data for %1$s are defined as constants, e.g. in your wp-config.php. Therefore, they are used for the whole website and this setting could not be changed.', 'external-files-in-media-library' ), $this->get_label() ) );
			}
			if ( method_exists( $setting, 'set_reload_on_save' ) ) { // @phpstan-ignore function.alreadyNarrowedType
				$setting->set_reload_on_save( true );
			}
			$setting->set_field( $field );
		}

		// show hint if settings of this service are defined via constants.
		$constants = $this->get_defined_constants_for_fields();
		if ( ! empty( $constants ) ) {
			$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_constants_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo( $settings_obj );
			$field->set_title( __( 'Defined in wp-config.php', 'external-files-in-media-library' ) );
			$missing_constants = $this->get_missing_constants_for_fields();
			if ( empty( $missing_constants ) ) {
				/* translators: %1$s will be replaced by a list of constant names. */
				$description = sprintf( __( 'The following settings are defined as constants, e.g. in your wp-config.php. They are used for every connection to this service and replace the values entered here, in the user profiles or in your external sources: %1$s', 'external-files-in-media-library' ), $this->get_constant_list_for_output( $constants ) );

				// add hint for the host binding, if used.
				if ( ! empty( $this->constants_host_field ) ) {
					$description .= ' ' . __( 'The values are only used for connections to the server defined by the constants.', 'external-files-in-media-library' );
				}
			} else {
				/* translators: %1$s will be replaced by a list of constant names, %2$s by a list of constant names. */
				$description = '<strong>' . sprintf( __( 'The constants %1$s are defined, e.g. in your wp-config.php, but they are not used, because the constants for all required settings must be defined. The following constants are missing: %2$s', 'external-files-in-media-library' ), $this->get_constant_list_for_output( $constants ), $this->get_constant_list_for_output( $missing_constants ) ) . '</strong>';
			}
			$field->set_description( $description );
			$setting->set_field( $field );
		}

		$instance = $this;
		/**
		 * Run additional tasks after the main settings of a service have been added, e.g. to add further settings.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param Section $section The main settings section of this service.
		 * @param Service_Base $instance The service object.
		 */
		do_action( 'efml_service_main_settings', $section, $instance );
	}

	/**
	 * Return whether files with the given mime type should be hidden in the directory listing.
	 *
	 * This is the case if the setting to hide not supported file types is enabled and the mime type is
	 * not allowed in the settings.
	 *
	 * @param string $mime_type The mime type.
	 *
	 * @return bool
	 */
	protected function is_mime_type_hidden( string $mime_type ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return false;
		}

		// return whether this file type is not allowed.
		return ! in_array( $mime_type, Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Return list of possible modes this service can be run.
	 *
	 * @return array<string,string>
	 */
	private function get_modes(): array {
		$modes = array(
			'manually' => __( 'Enter on each connection', 'external-files-in-media-library' ),
			'user'     => __( 'User-specific', 'external-files-in-media-library' ),
			'global'   => __( 'One for this website', 'external-files-in-media-library' ),
		);

		$instance = $this;

		/**
		 * Filter the list of possible modes of this service.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,string> $modes List of modes.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_modes', $modes, $instance );
	}

	/**
	 * Return the forward URL after activating this as plugin.
	 *
	 * @return string
	 */
	public function get_forward_url(): string {
		return Directory_Listing::get_instance()->get_view_directory_url( $this );
	}

	/**
	 * Return the URL of the web interface of the platform of this service (e.g. the website of DropBox).
	 *
	 * Used for the link in the directory listing and in the info box of external files of this service. Return an
	 * empty string if the service has no such platform.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return '';
	}

	/**
	 * Return whether a specific service is used.
	 *
	 * @param string $mode The name of the service.
	 *
	 * @return bool
	 */
	protected function is_mode( string $mode ): bool {
		return $mode === $this->get_mode();
	}

	/**
	 * Return the actual mode this service is using.
	 *
	 * @return string
	 */
	protected function get_mode(): string {
		// use the mode for constants, if the credentials are defined via constants.
		if ( $this->is_mode_forced_by_constants() ) {
			return $this->get_mode_for_constants();
		}

		// return the saved mode.
		return $this->get_saved_mode();
	}

	/**
	 * Show hint where to edit permissions to use this service.
	 *
	 * @return void
	 */
	public function show_hint_for_permissions(): void {
		/* translators: %1$s will be replaced by the service name, %2$s by a URL. */
		echo wp_kses_post( sprintf( __( 'Set permission who could use the %1$s service <a href="%2$s">here</a>.', 'external-files-in-media-library' ), $this->get_label(), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_permissions' ) ) );
	}

	/**
	 * Return the settings slug.
	 *
	 * @return string
	 */
	protected function get_settings_tab_slug(): string {
		return $this->settings_tab;
	}

	/**
	 * Return the settings sub tab slug.
	 *
	 * @return string
	 */
	protected function get_settings_subtab_slug(): string {
		return $this->settings_sub_tab;
	}

	/**
	 * Show user settings table, if settings are defined for this object.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return void
	 */
	protected function get_user_settings_table( int $user_id ): void {
		// get the settings.
		$settings = $this->get_user_settings();

		// bail if no settings are defined.
		if ( empty( $settings ) ) {
			return;
		}

		// show settings as table.
		?>
		<table class="form-table" role="presentation">
			<?php
			foreach ( $settings as $name => $setting ) {
				// show only a hint, if the value of this setting is defined via constant.
				$constant_hint = $this->get_constant_hint_for_field( $this->get_field_name_of_user_setting( (string) $name ) );
				if ( '' !== $constant_hint ) {
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $setting['label'] ); ?></th>
						<td><p><?php echo wp_kses_post( $constant_hint ); ?></p></td>
					</tr>
					<?php
					continue;
				}

				// get actual value.
				$value = Crypt::get_instance()->decrypt( get_user_meta( $user_id, 'efml_' . $name, true ) );

				// if no value is set, use the default value, if set.
				if ( empty( $value ) && ! empty( $setting['default'] ) ) {
					$value = $setting['default'];
				}

				// get the placeholder for this field.
				$placeholder = '';
				if ( ! empty( $setting['placeholder'] ) ) {
					$placeholder = $setting['placeholder'];
				}

				// output.
				?>
				<tr>
					<th scope="row"><label for="efml_<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $setting['label'] ); ?></label></th>
					<td>
						<?php
						switch ( $setting['field'] ) {
							case 'checkbox':
								echo '<input type="checkbox" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" value="1"' . ( 1 === absint( $value ) ? ' checked="checked"' : '' ) . ( ! empty( $setting['readonly'] ) ? ' disabled="disabled"' : '' ) . '>';
								break;
							case 'password':
								echo '<input type="password" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								break;
							case 'textarea':
								echo '<textarea id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . ' placeholder="' . esc_attr( $placeholder ) . '">' . esc_html( $value ) . '</textarea>';
								break;
							case 'text':
								echo '<input type="text" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								break;
							case 'select':
								echo '<select id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								foreach ( $setting['options'] as $key => $label ) {
									echo '<option value="' . esc_attr( $key ) . '"' . ( $key === $value ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
								}
								echo '</select>';
								break;
							default:
								break;
						}

						// show description for this field, if given.
						if ( isset( $setting['description'] ) ) {
							?>
							<p><?php echo wp_kses_post( $setting['description'] ); ?></p>
							<?php
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		return array();
	}

	/**
	 * Save user-specific options.
	 *
	 * @param int $user_id The ID of the user.
	 *
	 * @return void
	 */
	public function save_user_settings( int $user_id ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		// loop through the settings and save them.
		foreach ( $this->get_user_settings() as $name => $setting ) {
			// bail if the value of this setting is defined via constant.
			if ( ! is_null( $this->get_constant_value( $this->get_field_name_of_user_setting( (string) $name ) ) ) ) {
				continue;
			}

			// get the settings full name.
			$full_name = 'efml_' . $name;

			// get the value from request depending on field type.
			switch ( $setting['field'] ) {
				case 'checkbox':
					$value = isset( $_POST[ $full_name ] ) ? absint( $_POST[ $full_name ] ) : 0;
					break;
				case 'textarea':
					$value = isset( $_POST[ $full_name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $full_name ] ) ) : '';
					break;
				default:
					$value = isset( $_POST[ $full_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $full_name ] ) ) : '';
			}

			// save the value in the database.
			update_user_meta( $user_id, 'efml_' . $name, Crypt::get_instance()->encrypt( (string) $value ) );
		}
	}

	/**
	 * Return the config URL.
	 *
	 * @return string
	 */
	protected function get_config_url(): string {
		// use the directory URL in the manual mode.
		if ( $this->is_mode( 'manually' ) ) {
			return Directory_Listing::get_instance()->get_view_directory_url( $this );
		}

		// use the global settings in the global mode.
		if ( $this->is_mode( 'global' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}
			return Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
		}

		// use the profile in user mode.
		return get_admin_url() . 'profile.php#efml-' . $this->get_name();
	}

	/**
	 * Return the tab with the settings of this service. Returns false, if this service has no settings.
	 *
	 * @return Tab|false
	 */
	private function get_settings_tab(): Tab|false {
		// bail if this service has no own settings tab.
		if ( empty( $this->get_settings_subtab_slug() ) ) {
			return false;
		}

		// get the settings page.
		$settings_page = Settings::get_instance()->get_settings_obj()->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return false;
		}

		// get the tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return false;
		}

		// return the tab of this service, if it exists.
		return $services_tab->get_tab( $this->get_settings_subtab_slug() );
	}

	/**
	 * Return the URL of the settings of this service. Returns an empty string, if this service has no settings.
	 *
	 * @return string
	 */
	public function get_settings_url(): string {
		// bail if the tab of this service does not exist.
		if ( ! $this->get_settings_tab() instanceof Tab ) {
			return '';
		}

		// return the URL.
		return Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
	}

	/**
	 * Return the title of this service in the settings: the title of its tab or its label.
	 *
	 * @return string
	 */
	public function get_settings_title(): string {
		// get the tab.
		$tab = $this->get_settings_tab();

		// use the title of the tab, if set.
		if ( $tab instanceof Tab && '' !== $tab->get_title() ) {
			return $tab->get_title();
		}

		// return the label.
		return $this->get_label();
	}

	/**
	 * Return the user to use.
	 *
	 * @return WP_User|false
	 */
	protected function get_user(): WP_User|false {
		// if no user is set, use the current one.
		if ( empty( $this->user ) ) {
			return wp_get_current_user();
		}

		// return the configured user.
		return $this->user;
	}

	/**
	 * Set user this object should use.
	 *
	 * @param WP_User $user The user object.
	 *
	 * @return void
	 */
	public function set_user( WP_User $user ): void {
		$this->user = $user;
	}

	/**
	 * Return whether this object does not allow sync.
	 *
	 * @return bool
	 */
	public function is_sync_disabled(): bool {
		return $this->sync_disabled;
	}

	/**
	 * Initiate the WP CLI support for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Encrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function encrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->encrypt( $value );
	}

	/**
	 * Decrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function decrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * Values of fields, which are defined as constants (e.g. in wp-config.php), replace the configured values.
	 * The name of the constant is "EFML_" + the service name + "_" + the field name in uppercase,
	 * e.g. "EFML_FTP_PASSWORD" for the field "password" of the service "ftp".
	 *
	 * The constants are only used if the constants for all required fields are defined.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// get the fields.
		$fields = parent::get_fields();

		// bail if constants should not be used (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return $fields;
		}

		// replace the values with the values of constants, if they are defined. The values of the target fields
		// are only checked, if the fields have been set from outside (e.g. by a saved external source).
		$fields = $this->apply_constants_to_fields( $fields, '', false, $this->fields_set_externally );

		$instance = $this;
		/**
		 * Filter the fields of a service incl. their values before they are used.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,array<string,mixed>> $fields List of fields.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_fields', $fields, $instance );
	}

	/**
	 * Set the fields.
	 *
	 * @param array<string,array<string,mixed>> $fields List of fields.
	 *
	 * @return void
	 */
	public function set_fields( array $fields ): void {
		parent::set_fields( $fields );
		$this->fields_set_externally = ! empty( $fields );
	}

	/**
	 * Return the configuration for the directory listing in the backend.
	 *
	 * Values of credential fields, which are defined as constants, are never sent to the browser.
	 * They are set again on the server via @get_fields() for each request.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		// get the configuration.
		$config = parent::get_config();

		// bail if no fields are set.
		if ( empty( $config['fields'] ) || ! is_array( $config['fields'] ) ) {
			return $config;
		}

		// check each field.
		foreach ( $config['fields'] as $name => $field ) {
			// bail if field is not defined via constant.
			if ( ! is_array( $field ) || empty( $field['constant'] ) ) {
				continue;
			}

			// mark field as not editable and not required in the form.
			$config['fields'][ $name ]['readonly']     = true;
			$config['fields'][ $name ]['not_required'] = true;

			// bail if this is the field with the server, which is visible to show which server is used.
			if ( $name === $this->constants_host_field && ( empty( $field['type'] ) || 'password' !== $field['type'] ) ) {
				continue;
			}

			// bail if this is not a credential field.
			if ( empty( $field['credential'] ) && ( empty( $field['type'] ) || 'password' !== $field['type'] ) ) {
				continue;
			}

			// remove the value and show a hint instead.
			$config['fields'][ $name ]['value'] = '';
			/* translators: %1$s will be replaced by the name of a constant. */
			$config['fields'][ $name ]['placeholder'] = sprintf( __( 'Defined via %1$s', 'external-files-in-media-library' ), $field['constant'] );
		}

		// return the resulting configuration.
		return $config;
	}

	/**
	 * Return whether values of fields of this service could be defined via constants.
	 *
	 * @return bool
	 */
	public function is_fields_via_constants_enabled(): bool {
		return $this->fields_via_constants;
	}

	/**
	 * Return the name of the constant, which could be used to define the value of the given field.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	public function get_constant_name_for_field( string $field_name ): string {
		// create the name.
		$constant_name = 'EFML_' . strtoupper( (string) preg_replace( '/[^a-zA-Z0-9]+/', '_', $this->get_name() . '_' . $field_name ) );

		$instance = $this;
		/**
		 * Filter the name of the constant for a field of a service.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $constant_name The name of the constant.
		 * @param string $field_name The name of the field.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_field_constant_name', $constant_name, $field_name, $instance );
	}

	/**
	 * Return the definitions of the fields, which values could be defined via constants.
	 *
	 * The definitions are loaded without any values from constants. Services, which do not use
	 * fields for their credentials (e.g. via OAuth), could override this to define their own list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_constant_field_definitions(): array {
		// bail if this service does not support constants.
		if ( ! $this->is_fields_via_constants_enabled() ) {
			return array();
		}

		// return the cached definitions, if set.
		if ( is_array( $this->field_definitions ) ) {
			return $this->field_definitions;
		}

		// load the definitions of the fields without the actual values of this object
		// (services are singletons, so the actual fields are saved and restored afterward).
		$actual_fields            = $this->fields;
		$this->fields             = array();
		$this->constants_disabled = true;
		try {
			$fields = $this->get_fields();
		} finally {
			$this->fields             = $actual_fields;
			$this->constants_disabled = false;
		}

		// collect the definitions.
		$definitions = array();
		foreach ( $fields as $name => $field ) {
			// bail if field is not an array.
			if ( ! is_array( $field ) ) { // @phpstan-ignore function.alreadyNarrowedType
				continue;
			}

			// bail if field could not be set via constant (e.g., a hidden field or a file upload).
			if ( isset( $field['type'] ) && in_array( $field['type'], array( 'hidden', 'file' ), true ) ) {
				continue;
			}

			// add the field without any value.
			unset( $field['value'], $field['readonly'] );
			$definitions[ (string) $name ] = $field;
		}

		// save them in the cache.
		$this->field_definitions = $definitions;

		// return the resulting list.
		return $definitions;
	}

	/**
	 * Return the list of required fields, which must all be defined via constants to use any constant.
	 *
	 * @return array<int,string>
	 */
	public function get_required_fields_for_constants(): array {
		// collect the required fields.
		$required_fields = array();
		foreach ( $this->get_constant_field_definitions() as $name => $field ) {
			// bail if field is not required.
			if ( ! empty( $field['not_required'] ) ) {
				continue;
			}

			// add it to the list.
			$required_fields[] = $name;
		}

		$instance = $this;
		/**
		 * Filter the list of fields of a service, which must all be defined via constants,
		 * before any constant of this service is used.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $required_fields List of field names.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_required_fields_for_constants', $required_fields, $instance );
	}

	/**
	 * Return the values of all fields, which are defined via constants.
	 *
	 * Returns an empty list if the constant for any required field is missing.
	 *
	 * @return array<string,mixed>
	 */
	public function get_constant_values(): array {
		// bail if constants are not used at the moment (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return array();
		}

		// get the definitions.
		$definitions = $this->get_constant_field_definitions();

		// bail if no definitions are given.
		if ( empty( $definitions ) ) {
			return array();
		}

		// bail if any required field is not defined via constant.
		if ( ! empty( $this->get_missing_constants_for_fields( false ) ) ) {
			return array();
		}

		// collect the values.
		$values = array();
		foreach ( array_keys( $definitions ) as $name ) {
			// get the constant name for this field.
			$constant_name = $this->get_constant_name_for_field( $name );

			// bail if constant is not defined.
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			// add the value.
			$values[ $name ] = constant( $constant_name );
		}

		// return the resulting list.
		return $values;
	}

	/**
	 * Return the value of a single field, if it is defined via constant and all required constants are defined.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string|null
	 */
	public function get_constant_value( string $field_name ): ?string {
		// get the values.
		$values = $this->get_constant_values();

		// bail if field is not set.
		if ( ! isset( $values[ $field_name ] ) || ! is_scalar( $values[ $field_name ] ) ) {
			return null;
		}

		// return the value.
		return (string) $values[ $field_name ];
	}

	/**
	 * Replace the values of the given fields with the values of the constants of this service.
	 *
	 * If a URL is given and this service defines a host field, the constants are only used if the URL
	 * is using the host of this field. Otherwise, values from constants are removed from the fields,
	 * so they are never sent to other servers.
	 *
	 * If this service defines target fields (e.g. a bucket), the constants are only used if the given fields
	 * do not contain other values for them.
	 *
	 * @param array<string,mixed> $fields              List of fields.
	 * @param string              $url                 The URL, which should be used with these fields (optional).
	 * @param bool                $add_missing         Whether fields, which are not in the list, should be added.
	 * @param bool                $check_target_fields Whether the values of the target fields should be checked.
	 *
	 * @return array<string,mixed>
	 */
	public function apply_constants_to_fields( array $fields, string $url = '', bool $add_missing = true, bool $check_target_fields = true ): array {
		// bail if the constants of this service are not values of its fields.
		if ( ! $this->constants_in_fields ) {
			return $fields;
		}

		// get the values of the constants.
		$values = $this->get_constant_values();

		// bail if no values are defined.
		if ( empty( $values ) ) {
			return $fields;
		}

		// bail if the URL is not using the host of the constants.
		if ( ! $this->is_url_matching_constants( $url ) ) {
			return $this->remove_constant_values_from_fields( $fields );
		}

		// bail if the fields are for another target (e.g. another bucket).
		if ( $check_target_fields && ! $this->is_fields_matching_constants( $fields ) ) {
			return $this->remove_constant_values_from_fields( $fields );
		}

		// get the definitions.
		$definitions = $this->get_constant_field_definitions();

		// set the values.
		foreach ( $values as $name => $value ) {
			// add the field, if it is missing.
			if ( ! isset( $fields[ $name ] ) || ! is_array( $fields[ $name ] ) ) {
				// bail if missing fields should not be added.
				if ( ! $add_missing ) {
					continue;
				}

				// add the definition of this field.
				$fields[ $name ] = isset( $definitions[ $name ] ) ? $definitions[ $name ] : array( 'name' => $name );
			}

			// use the value of the constant.
			$fields[ $name ]['value']    = $value;
			$fields[ $name ]['readonly'] = true;
			$fields[ $name ]['constant'] = $this->get_constant_name_for_field( $name );
		}

		// return the resulting fields.
		return $fields;
	}

	/**
	 * Return whether the given URL is using the host, which is defined via constant for this service.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_url_matching_constants( string $url ): bool {
		// bail if no URL is given.
		if ( '' === $url ) {
			return true;
		}

		// bail if this service does not use a host field.
		if ( empty( $this->constants_host_field ) ) {
			return true;
		}

		// get the value of the host field.
		$server = $this->get_constant_value( $this->constants_host_field );

		// bail if no server is defined via constant (should not happen as it is a required field).
		if ( is_null( $server ) ) {
			return true;
		}

		// compare the hosts.
		$server_host = $this->get_host_from_url( $server );
		$result      = '' !== $server_host && $server_host === $this->get_host_from_url( $url );

		$instance = $this;
		/**
		 * Filter whether the given URL is using the server defined via constants for this service.
		 * Only if true is returned, the values of the constants are used for this URL.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result True if the URL is matching.
		 * @param string $url The URL.
		 * @param string $server The server defined via constant.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_url_matching_constants', $result, $url, $server, $instance );
	}

	/**
	 * Return whether the given URL could not be used, because the credentials of this service are defined
	 * via constants for another server.
	 *
	 * Used for saved external sources: sources on other servers are not usable as long as the constants are defined.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_url_blocked_by_constants( string $url ): bool {
		return $this->is_target_blocked_by_constants( $url, array() );
	}

	/**
	 * Return whether the given URL and fields could not be used, because the credentials of this service are
	 * defined via constants for another target (another server or e.g. another bucket).
	 *
	 * @param string              $url    The URL to check.
	 * @param array<string,mixed> $fields The fields to check (e.g. of a saved external source).
	 *
	 * @return bool
	 */
	public function is_target_blocked_by_constants( string $url, array $fields ): bool {
		// bail if this service does not use a host field or target fields.
		if ( empty( $this->constants_host_field ) && empty( $this->constants_target_fields ) ) {
			return false;
		}

		// bail if not all required values are defined via constants.
		if ( empty( $this->get_constant_values() ) ) {
			return false;
		}

		// return true if the URL is not using the server of the constants.
		if ( ! empty( $this->constants_host_field ) && '' !== $url && ! $this->is_url_matching_constants( $url ) ) {
			return true;
		}

		// return whether the fields are for another target.
		return ! $this->is_fields_matching_constants( $fields );
	}

	/**
	 * Return whether the values of the target fields (e.g. a bucket) in the given fields are empty or equal to
	 * the values defined via constants.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return bool
	 */
	public function is_fields_matching_constants( array $fields ): bool {
		// check each target field.
		$result = true;
		foreach ( $this->constants_target_fields as $field_name ) {
			// get the value of the constant.
			$constant_value = $this->get_constant_value( $field_name );

			// bail if this field is not defined via constant.
			if ( is_null( $constant_value ) ) {
				continue;
			}

			// bail if the field is not set or is set via constant.
			if ( ! isset( $fields[ $field_name ] ) || ! is_array( $fields[ $field_name ] ) || ! empty( $fields[ $field_name ]['constant'] ) ) {
				continue;
			}

			// get the value.
			$value = isset( $fields[ $field_name ]['value'] ) && is_scalar( $fields[ $field_name ]['value'] ) ? trim( (string) $fields[ $field_name ]['value'] ) : '';

			// bail if no value is set.
			if ( '' === $value ) {
				continue;
			}

			// compare the values.
			if ( strtolower( $value ) !== strtolower( trim( $constant_value ) ) ) {
				$result = false;
			}
		}

		$instance = $this;
		/**
		 * Filter whether the given fields are for the target (e.g. a bucket) defined via constants.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result True if the fields are matching.
		 * @param array<string,mixed> $fields List of fields.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_fields_matching_constants', $result, $fields, $instance );
	}

	/**
	 * Return the host of the given URL in lowercase.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function get_host_from_url( string $url ): string {
		// add a scheme, if it is missing, so the host could be detected.
		if ( ! str_contains( $url, '://' ) ) {
			$url = 'http://' . $url;
		}

		// get the host.
		$host = wp_parse_url( $url, PHP_URL_HOST );

		// bail if no host could be detected.
		if ( ! is_string( $host ) ) {
			return '';
		}

		// return the host.
		return strtolower( $host );
	}

	/**
	 * Remove all values, which are set via constants, from the given fields.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return array<string,mixed>
	 */
	private function remove_constant_values_from_fields( array $fields ): array {
		foreach ( $fields as $name => $field ) {
			// bail if field is not set via constant.
			if ( ! is_array( $field ) || empty( $field['constant'] ) ) {
				continue;
			}

			// remove the value.
			$fields[ $name ]['value'] = '';
			unset( $fields[ $name ]['constant'], $fields[ $name ]['readonly'] );
		}

		// return the resulting fields.
		return $fields;
	}

	/**
	 * Return the list of defined constants for fields of this service.
	 *
	 * @return array<int,string>
	 */
	public function get_defined_constants_for_fields(): array {
		// collect the defined constants of this service.
		$list = array();
		foreach ( array_keys( $this->get_constant_field_definitions() ) as $name ) {
			// get the constant name.
			$constant_name = $this->get_constant_name_for_field( $name );

			// bail if constant is not defined.
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			// add it to the list.
			$list[] = $constant_name;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of constants for required fields, which are not defined.
	 *
	 * @param bool $only_if_any_defined True to return an empty list, if no constant of this service is defined.
	 *
	 * @return array<int,string>
	 */
	public function get_missing_constants_for_fields( bool $only_if_any_defined = true ): array {
		// bail if no constant is defined and this is requested.
		if ( $only_if_any_defined && empty( $this->get_defined_constants_for_fields() ) ) {
			return array();
		}

		// collect the missing constants.
		$list = array();
		foreach ( $this->get_required_fields_for_constants() as $name ) {
			// get the constant name.
			$constant_name = $this->get_constant_name_for_field( $name );

			// bail if constant is defined.
			if ( defined( $constant_name ) ) {
				continue;
			}

			// add it to the list.
			$list[] = $constant_name;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the name of the field for the given name of a user setting (e.g. "login" for "ftp_login").
	 *
	 * @param string $setting_name The name of the user setting.
	 *
	 * @return string
	 */
	private function get_field_name_of_user_setting( string $setting_name ): string {
		// get the prefix of the user settings of this service.
		$prefix = $this->get_name() . '_';

		// bail if the name does not start with the prefix.
		if ( ! str_starts_with( $setting_name, $prefix ) ) {
			return $setting_name;
		}

		// return the name without the prefix.
		return substr( $setting_name, strlen( $prefix ) );
	}

	/**
	 * Return the hint for a field, whose value is defined via constant. Returns an empty string, if it is not.
	 *
	 * Only the value of the field with the server is shown, other values are never shown.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	protected function get_constant_hint_for_field( string $field_name ): string {
		// get the value of the constant.
		$value = $this->get_constant_value( $field_name );

		// bail if the value is not defined via constant.
		if ( is_null( $value ) ) {
			return '';
		}

		// get the name of the constant.
		$constant_name = $this->get_constant_name_for_field( $field_name );

		// show the value for the server.
		if ( $field_name === $this->constants_host_field ) {
			/* translators: %1$s will be replaced by a value, %2$s by the name of a constant. */
			return sprintf( __( '%1$s (defined via %2$s)', 'external-files-in-media-library' ), '<code>' . esc_html( $value ) . '</code>', '<code>' . esc_html( $constant_name ) . '</code>' );
		}

		/* translators: %1$s will be replaced by the name of a constant. */
		return sprintf( __( 'Defined via %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $constant_name ) . '</code>' );
	}

	/**
	 * Add a non-editable hint as setting, if the value of the given field is defined via constant.
	 *
	 * Services use this for their global settings, so the values saved in the database are not changed.
	 *
	 * @param Section $section    The section for the setting.
	 * @param string  $field_name The name of the field.
	 * @param string  $title      The title of the setting.
	 *
	 * @return bool True if the value is defined via constant and the hint has been added.
	 */
	protected function add_constant_hint_setting( Section $section, string $field_name, string $title ): bool {
		// get the hint.
		$hint = $this->get_constant_hint_for_field( $field_name );

		// bail if the value is not defined via constant.
		if ( '' === $hint ) {
			return false;
		}

		// add the hint as setting.
		$settings_obj = Settings::get_instance()->get_settings_obj();
		$setting      = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_' . $field_name . '_constant_hint' );
		$setting->set_section( $section );
		$setting->set_show_in_rest( false );
		$setting->prevent_export( true );
		$field = new TextInfo( $settings_obj );
		$field->set_title( $title );
		$field->set_description( $hint );
		$setting->set_field( $field );

		// return true as the hint has been added.
		return true;
	}

	/**
	 * Return a list of constant names for output.
	 *
	 * @param array<int,string> $constants List of constant names.
	 *
	 * @return string
	 */
	private function get_constant_list_for_output( array $constants ): string {
		return '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $constants ) ) . '</code>';
	}

	/**
	 * Return whether this service is using credentials by checking its field configuration.
	 *
	 * @return bool
	 */
	protected function has_credentials(): bool {
		// set initial value.
		$has_credentials = false;

		// check fields for credentials fields.
		foreach ( $this->get_fields() as $setting ) {
			// bail if field has not the credential marker.
			if ( empty( $setting['credential'] ) ) {
				continue;
			}

			// bail if it is a credential field but not required.
			if ( ! empty( $setting['not_required'] ) ) {
				continue;
			}

			// mark that this service is using credentials.
			$has_credentials = true;
		}

		// return the result.
		return $has_credentials;
	}

	/**
	 * Return whether this listing could also be used to export files.
	 *
	 * @return Export_Base|bool
	 */
	public function get_export_object(): Export_Base|bool {
		return false;
	}

	/**
	 * Return the plugin slug for this service.
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string {
		return $this->source_config['plugin_slug'];
	}

	/**
	 * Return whether this service is based on a plugin.
	 *
	 * @return bool
	 */
	public function is_plugin(): bool {
		return ! empty( $this->get_plugin_slug() );
	}

	/**
	 * Return the source configuration if this service is based on an external plugin.
	 *
	 * @return array<string,string>
	 */
	public function get_source_config(): array {
		return $this->source_config;
	}

	/**
	 * Return the plugins main file.
	 *
	 * @return string
	 */
	public function get_plugin_main_file(): string {
		return $this->source_config['plugin_main_file'];
	}

	/**
	 * Return false to mark these service provides editable permissions.
	 *
	 * @return bool
	 */
	public function has_no_editable_permissions(): bool {
		return false;
	}

	/**
	 * Return the permission name to use this listing.
	 *
	 * @return string
	 */
	public function get_permission_name(): string {
		return 'efml_cap_' . $this->get_name();
	}

	/**
	 * Return the default roles, which should be allowed to use this service after install.
	 *
	 * @return array<int,string>
	 */
	public function get_default_roles(): array {
		return array( 'administrator', 'editor' );
	}

	/**
	 * Return whether the hooks for importing files with this service should be used in this request.
	 *
	 * This is the case for users with the capability for this service and for scheduled synchronizations
	 * via cron and WP CLI, which run without a user.
	 *
	 * @return bool
	 */
	protected function is_import_allowed(): bool {
		return defined( 'EFML_SYNC_RUNNING' ) || wp_doing_cron() || Helper::is_cli() || current_user_can( 'efml_cap_' . $this->get_name() );
	}

	/**
	 * Return whether the actual import or synchronization is for this service.
	 *
	 * The import dialog sends the service with the request. A synchronization (also via cron or WP CLI)
	 * sets the external source on the import object instead, which has the service as type.
	 *
	 * @param string $field The name of the field in the request, which contains the service.
	 *
	 * @return bool
	 */
	protected function is_actual_service( string $field = 'service' ): bool {
		// use the service from the request, if given.
		$service = filter_input( INPUT_POST, $field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $service ) ) {
			return $this->get_name() === $service;
		}

		// bail if no external source is used for the actual import.
		$term_id = Import::get_instance()->get_term_id();
		if ( 0 === $term_id ) {
			return false;
		}

		// return whether the external source is from this service.
		return $this->get_name() === get_term_meta( $term_id, 'type', true );
	}
}
