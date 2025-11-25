<?php
/**
 * File to handle the dialog which starts an import.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_User;

/**
 * Object to handle the dialog which starts an import.
 */
class ImportDialog {
	/**
	 * Instance of actual object.
	 *
	 * @var ?ImportDialog
	 */
	private static ?ImportDialog $instance = null;

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
	 * @return ImportDialog
	 */
	public static function get_instance(): ImportDialog {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// main handler for the dialog which is loaded via AJAX.
		add_action( 'wp_ajax_efml_get_import_dialog', array( $this, 'get_import_dialog' ) );

		// use our own hooks to extend or change the dialog.
		add_filter( 'eml_add_dialog', array( $this, 'add_textarea' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_urls' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_credential_fields' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_settings_link' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'prevent_dialog_usage' ), PHP_INT_MAX, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_term' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_fields' ), 10, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_privacy_hint' ), 200, 2 );
		add_filter( 'eml_add_dialog', array( $this, 'add_show_dialog_option' ), 100, 2 );
		add_action( 'eml_import_ajax_start', array( $this, 'save_hide_dialog_option' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ), 100 );
		add_filter( 'eml_dialog_after_adding', array( $this, 'add_log_button' ) );
		add_filter( 'eml_dialog_settings', array( $this, 'set_dialog_settings' ) );

		// add user-specific configuration.
		add_action( 'edit_user_profile', array( $this, 'add_user_settings' ) );
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_settings' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_settings' ) );
	}

	/**
	 * Return the import dialog.
	 *
	 * @return void
	 */
	public function get_import_dialog(): void {
		// check nonce.
		check_ajax_referer( 'efml-import-dialog-nonce', 'nonce' );

		// get settings from request.
		$settings = array();
		if ( isset( $_POST['settings'] ) ) {
			$settings = map_deep( wp_unslash( $_POST['settings'] ), 'wp_kses_post' );
		}

		/**
		 * Filter the given settings for the import dialog.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<string,mixed> $settings The requested settings.
		 */
		$settings = apply_filters( 'eml_dialog_settings', $settings );

		// check number of files.
		$url_count = 1;
		if ( ! empty( $settings['urls'] ) && str_ends_with( $settings['urls'], '/' ) ) {
			$url_count = 2;
		} elseif ( ! isset( $settings['urls'] ) ) {
			$url_count = 2;
		}

		// create dialog.
		$dialog = array(
			'id'        => 'efml-import-dialog',
			'className' => 'eml efml-import-dialog',
			'callback'  => 'document.dispatchEvent(new Event("efml-import-dialog-loaded"));',
			'title'     => _n( 'Add this external file by its URL', 'Add external files by their URLs', $url_count, 'external-files-in-media-library' ),
			'texts'     => array(),
			'buttons'   => array(
				array(
					'action'  => 'efml_process_import_dialog();',
					'variant' => 'primary',
					'text'    => _n( 'Add URL', 'Add URLs', $url_count, 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
				array(
					'action'    => 'window.open("' . Helper::get_plugin_support_url() . '", "_blank" )',
					'className' => 'efml-help-button',
					'variant'   => 'secondary',
					'text'      => __( 'Need help?', 'external-files-in-media-library' ),
				),
			),
		);

		/**
		 * Filter the dialog. This is the main handling to extend the import dialog.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<string,mixed> $dialog The dialog configuration.
		 * @param array<string,mixed> $settings The requested settings.
		 */
		$dialog = apply_filters( 'eml_add_dialog', $dialog, $settings );

		// return the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Add textarea field in dialog where user can enter multiple URLs.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_textarea( array $dialog, array $settings ): array {
		// bail if 'no_textarea' is set in settings.
		if ( isset( $settings['no_textarea'] ) ) {
			return $dialog;
		}

		// add the textarea for entering the external URLs.
		$dialog['texts'][] = '<label for="urls" class="title">' . esc_html__( 'Enter one URL per line for external files you want to insert in your library', 'external-files-in-media-library' ) . ' <a href="' . esc_url( Helper::get_support_url_for_urls() ) . '" target="_blank"><span class="dashicons dashicons-editor-help"></span></a></label><textarea id="urls" name="urls" class="eml_add_external_files" placeholder="https://example.com/file.pdf"></textarea>';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Add hidden field to URLs from settings.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_urls( array $dialog, array $settings ): array {
		// bail if no "urls" are given in settings.
		if ( ! isset( $settings['urls'] ) ) {
			return $dialog;
		}

		// add the textarea for entering the external URLs.
		$dialog['texts'][] = '<input type="hidden" id="urls" name="urls" value="' . esc_attr( $settings['urls'] ) . '">';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Add credentials field in dialog where user can enter credentials for the given URLs.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_credential_fields( array $dialog, array $settings ): array {
		// bail if 'no_credentials' is set in settings.
		if ( isset( $settings['no_credentials'] ) ) {
			return $dialog;
		}

		// add the fields.
		$dialog['texts'][] = '<details><summary>' . __( 'Add credentials to access these URLs', 'external-files-in-media-library' ) . '</summary><div><label for="use_credentials"><input type="checkbox" name="use_credentials" value="1" id="use_credentials"> ' . esc_html__( 'Use below credentials to import these URLs', 'external-files-in-media-library' ) . '</label></div><div><label for="eml_login">' . __( 'Login', 'external-files-in-media-library' ) . ':</label><input type="text" id="login" name="login" value="" autocomplete="off" readonly></div><div><label for="password">' . __( 'Password', 'external-files-in-media-library' ) . ':</label><input type="password" id="password" name="password" value="" autocomplete="off" readonly></div><p><strong>' . __( 'Hint:', 'external-files-in-media-library' ) . '</strong> ' . __( 'Files with credentials are saved locally.', 'external-files-in-media-library' ) . '</p></details>';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Add settings link in dialog, if user has the capability for it.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_settings_link( array $dialog, array $settings ): array {
		// bail if user does not have the capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $dialog;
		}

		// bail if 'no_settings_link' is set in settings.
		if ( isset( $settings['no_settings_link'] ) ) {
			return $dialog;
		}

		// add the link as button.
		$dialog['buttons'][] = array(
			'action'    => 'location.href="' . Helper::get_config_url() . '";',
			'className' => 'settings',
			'text'      => '',
		);

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Prevent usage of dialog. We send a minimal dialog which triggers the automatic import process.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function prevent_dialog_usage( array $dialog, array $settings ): array {
		// bail if "no_dialog" is not set or not true.
		if ( ! isset( $settings['no_dialog'] ) || false === $settings['no_dialog'] ) {
			return $dialog;
		}

		// get only the texts with input field.
		$texts = array();
		foreach ( $dialog['texts'] as $text ) {
			// bail if text does not start with "<input".
			if ( ! str_starts_with( $text, '<input' ) ) {
				continue;
			}

			// add to the list.
			$texts[] = $text;
		}

		// create minimal dialog which should trigger the automatic import process.
		return array(
			'id'        => 'efml-import-dialog',
			'className' => 'eml efml-import-dialog efml-import-dialog-process-now',
			'title'     => __( 'Please wait', 'external-files-in-media-library' ),
			'texts'     => $texts,
			'buttons'   => array(),
		);
	}

	/**
	 * Add hidden field in dialog for term.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_term( array $dialog, array $settings ): array {
		// bail if 'term' is not set in settings.
		if ( ! isset( $settings['term'] ) ) {
			return $dialog;
		}

		// add the hidden input for given term.
		$dialog['texts'][] = '<input type="hidden" name="term" value="' . absint( $settings['term'] ) . '">';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Add hidden fields in dialog for credentials.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_fields( array $dialog, array $settings ): array {
		// bail if 'fields' is not set in settings.
		if ( empty( $settings['fields'] ) ) {
			return $dialog;
		}

		// add marker to use credentials.
		// TODO nötig?
		$dialog['texts'][] = '<input type="hidden" name="use_credentials" value="1">';

		// add the fields.
		$dialog['texts'][] = '<input type="hidden" name="fields" value="' . esc_attr( Helper::get_json( $settings['fields'] ) ) . '">';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Show options on user profile as preset for import dialog options.
	 *
	 * @param WP_User $user The user object.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if customization for this user is not allowed.
		if ( ! $this->is_customization_allowed() ) {
			return;
		}

		$settings = array();

		/**
		 * Filter the possible user settings for import dialog.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,array<string,mixed>> $settings List of settings.
		 */
		$settings = apply_filters( 'efml_user_settings', $settings );

		?>
		<h2 id="efml-settings"><?php echo esc_html__( 'Settings for External files in media library', 'external-files-in-media-library' ); ?></h2>
		<div class="efml-user-settings">
			<p><?php echo esc_html__( 'These are the default values for the options for importing external URLs. They only apply to your WordPress account.', 'external-files-in-media-library' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				foreach ( $settings as $name => $setting ) {
					// get actual value.
					$value = get_user_meta( $user->ID, 'efml_' . $name, true );

					// output.
					?>
							<tr>
								<th scope="row"><label for="efml_<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $setting['label'] ); ?></label></th>
								<td>
								<?php
								switch ( $setting['field'] ) {
									case 'checkbox':
										echo '<input type="checkbox" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" value="1"' . ( 1 === absint( $value ) ? ' checked="checked"' : '' ) . '>';
										break;
									default:
										break;
								}

								// show description for this field, if given.
								if ( isset( $setting['description'] ) ) {
									?>
										<p><?php echo esc_html( $setting['description'] ); ?></p>
										<?php
								}
								?>
								</td>
							</tr>
						<?php
				}
				?>
			</table>
		</div>
		<?php
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
		if ( ! $this->is_customization_allowed() ) {
			return;
		}

		$settings = array();

		/**
		 * Filter the possible user settings for import dialog.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,array<string,mixed>> $settings List of settings.
		 */
		$settings = apply_filters( 'efml_user_settings', $settings );

		// loop through the settings and save them.
		foreach ( $settings as $name => $setting ) {
			// get the settings full name.
			$full_name = 'efml_' . $name;

			// get the value from request depending on field type.
			switch ( $setting['field'] ) {
				case 'checkbox':
					$value = isset( $_POST[ $full_name ] ) ? absint( $_POST[ $full_name ] ) : 0;
					break;
				default:
					$value = isset( $_POST[ $full_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $full_name ] ) ) : '';
			}

			// save it in DB.
			update_user_meta( $user_id, 'efml_' . $name, $value );
		}
	}

	/**
	 * Add checkbox where user can set to hide this dialog for any further requests.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_show_dialog_option( array $dialog, array $settings ): array {
		// bail if 'no_textarea' is not set in settings.
		if ( ! isset( $settings['no_textarea'] ) ) {
			return $dialog;
		}

		// collect the entry.
		$text = '<label for="hide_dialog"><input type="checkbox" name="hide_dialog" id="hide_dialog" value="1" class="eml-use-for-import"> ' . esc_html__( 'Do not display this dialog for future imports. You can disable this setting in your user profile at any time.', 'external-files-in-media-library' );

		// add link to user settings.
		$url   = add_query_arg(
			array(),
			get_admin_url() . 'profile.php'
		);
		$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';

		// end the text.
		$text .= '</label>';

		// add the textarea for entering the external URLs.
		$dialog['texts'][] = $text;

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Save setting to hide the dialog in user meta, if set.
	 *
	 * @return void
	 */
	public function save_hide_dialog_option(): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// bail if "hide_dialog" is not set.
		if ( ! isset( $_POST['hide_dialog'] ) ) {
			return;
		}

		// bail if "hide_dialog" is not 1.
		if ( 1 !== absint( $_POST['hide_dialog'] ) ) {
			return;
		}

		// save the new setting to hide the dialog.
		update_user_meta( get_current_user_id(), 'efml_hide_dialog', 1 );
	}

	/**
	 * Add option for the user-specific setting to hide the dialog.
	 *
	 * @param array<string,array<string,mixed>> $settings List of settings.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_user_setting( array $settings ): array {
		// add our setting.
		$settings['hide_dialog'] = array(
			'label'       => __( 'Hide dialog', 'external-files-in-media-library' ),
			'description' => __( 'When the dialog is hidden, the above settings are used for importing files from any external source.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// add our setting.
		$settings['no_privacy_hint'] = array(
			'label'       => __( 'Hide copyright hint', 'external-files-in-media-library' ),
			'description' => __( 'If enabled the copyright hint in the dialog will be hidden.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}

	/**
	 * Check if actual user could use custom settings for imports.
	 * This depends on the global setting and (if this is enabled) on its role.
	 *
	 * @return bool
	 */
	public function is_customization_allowed(): bool {
		// bail if global setting is disabled.
		if ( 1 !== absint( get_option( 'eml_user_settings' ) ) ) {
			return false;
		}

		// get the list of allowed roles.
		$roles = get_option( 'eml_user_settings_allowed_roles', array() );

		// bail if roles is not an array.
		if ( ! is_array( $roles ) ) {
			return false;
		}

		// check the given roles.
		foreach ( $roles as $role ) {
			// bail if role is not a string.
			if ( ! is_string( $role ) ) {
				continue;
			}

			// check if actual user has this role.
			if ( Helper::has_current_user_role( $role ) ) {
				return true;
			}
		}

		// return false as user does not have an allowed role.
		return false;
	}

	/**
	 * Return list of enabled extensions from settings.
	 *
	 * @return array<int,string>
	 */
	public function get_enabled_extensions(): array {
		// get the value of the setting.
		$setting = get_option( 'eml_import_extensions', array() );

		// if it is not an array, return an empty one.
		if ( ! is_array( $setting ) ) {
			return array();
		}

		// return the setting.
		return $setting;
	}

	/**
	 * Add the log button in import dialog.
	 *
	 * @param array<string,mixed> $dialog The dialog configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function add_log_button( array $dialog ): array {
		// bail if capability is not set.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $dialog;
		}

		// add the log button on dialog.
		$dialog['detail']['buttons'][] = array(
			'action'  => 'location.href="' . Helper::get_log_url() . '";',
			'variant' => 'secondary',
			'text'    => __( 'Go to logs', 'external-files-in-media-library' ),
		);

		// return the dialog.
		return $dialog;
	}

	/**
	 * Add privacy hint on each import dialog with must be checked.
	 *
	 * Hide this hint if it is set in settings.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The requested settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_privacy_hint( array $dialog, array $settings ): array {
		// bail if 'no_privacy_hint' is set in settings and true.
		if ( isset( $settings['no_privacy_hint'] ) && false !== $settings['no_privacy_hint'] ) {
			return $dialog;
		}

		// add the fields.
		$text = '<label for="privacy_hint"><input type="checkbox" name="privacy_hint" id="privacy_hint" value="1" class="eml-use-for-import" required> <strong>' . esc_html__( 'I confirm that I will respect the copyrights of these external files.', 'external-files-in-media-library' ) . '</strong>';

		// add link to user settings.
		$url   = add_query_arg(
			array(),
			get_admin_url() . 'profile.php'
		);
		$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';
		$text .= '</label>';

		// add the text.
		$dialog['texts'][] = $text;

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Set settings for the dialog.
	 *
	 * @param array<string,mixed> $settings The dialog settings.
	 *
	 * @return array<string,mixed>
	 */
	public function set_dialog_settings( array $settings ): array {
		$settings['no_privacy_hint'] = 1 === absint( get_user_meta( get_current_user_id(), 'efml_no_privacy_hint', true ) );
		return $settings;
	}

	/**
	 * Show the form for static view without JS.
	 *
	 * @return void
	 */
	public function get_form(): void {
		$dialog   = array(
			'texts' => array(),
		);
		$settings = array();
		/**
		 * Filter the given settings for the import dialog.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<string,mixed> $settings The requested settings.
		 */
		$settings = apply_filters( 'eml_dialog_settings', $settings );

		/**
		 * Filter the dialog. This is the main handling to extend the import dialog.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<string,mixed> $dialog The dialog configuration.
		 * @param array<string,mixed> $settings The requested settings.
		 */
		$dialog = apply_filters( 'eml_add_dialog', $dialog, $settings );

		// allow necessary fields in kses.
		$allowed_html = array(
			'a'        => array(
				'href'  => true,
				'class' => true,
			),
			'details'  => array(),
			'summary'  => array(),
			'div'      => array(
				'class' => true,
				'style' => true,
			),
			'input'    => array(
				'id'          => true,
				'type'        => true,
				'name'        => true,
				'value'       => true,
				'class'       => true,
				'style'       => true,
				'placeholder' => true,
				'required'    => true,
			),
			'label'    => array(
				'for' => true,
			),
			'textarea' => array(
				'id'          => true,
				'name'        => true,
				'class'       => true,
				'style'       => true,
				'placeholder' => true,
			),
			'p'        => array(
				'class' => true,
			),
		);

		// add our own form.
		echo '</form><form id="eml_add_external_files_form" action="' . esc_url( get_admin_url() . 'admin.php' ) . '" method="post">';
		echo '<input type="hidden" name="action" value="eml_add_external_urls" />';
		wp_nonce_field( 'efml-add-external-files', 'nonce' );

		// show heading.
		echo '<div><strong>' . esc_html__( 'Add external files', 'external-files-in-media-library' ) . '</strong></div>';

		// show the fields.
		foreach ( $dialog['texts'] as $field ) {
			?>
			<div>
			<?php
			echo wp_kses( $field, $allowed_html );
			?>
			</div>
			<?php
		}

		// show buttons.
		?>
		<div><input type="submit" class="button button-primary" name="add_external_files_button" value="<?php echo esc_attr__( 'Add these URLs', 'external-files-in-media-library' ); ?>" /></div>
		<?php
	}
}
