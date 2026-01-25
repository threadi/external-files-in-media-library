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
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
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
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

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
			$field = new Select();
			$field->set_title( __( 'Location where credentials are stored', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by the service title (e.g. DropBox). */
			$field->set_description( sprintf( __( 'This setting determines where %1$s access data is stored. Depending on this setting, the access data must be entered each time a connection is made, or it can be stored for each user or all users in this project. This only affects the import of files. The use of files in the media library is not affected.', 'external-files-in-media-library' ), $this->get_label() ) );
			$field->set_options( $this->get_modes() );
			$setting->set_field( $field );
		}
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
		return get_option( 'eml_' . $this->get_name() . '_credentials_vault' );
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
			return \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
		}

		// use the profile in user mode.
		return get_admin_url() . 'profile.php#efml-' . $this->get_name();
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

		// bail if string is empty.
		if ( empty( $value ) ) {
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

		// bail if string is empty.
		if ( empty( $value ) ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}

	/**
	 * Return whether this service is using credentials by checking its field configuration.
	 *
	 * @return bool
	 */
	private function has_credentials(): bool {
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
}
