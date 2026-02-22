<?php
/**
 * This file controls the option to send an email after a task for external files has been run.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog;
use WP_Term;

/**
 * Handler controls how to import external files with a specific date.
 */
class Email extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'email';

	/**
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'import_dialog', 'sync_dialog' );

	/**
	 * Instance of actual object.
	 *
	 * @var Email|null
	 */
	private static ?Email $instance = null;

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
	 * @return Email
	 */
	public static function get_instance(): Email {
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
		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 20 );

		// use our own hooks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_option_on_sync_config' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_filter( 'efml_sync_validate_config', array( $this, 'validate_sync_config' ), 10, 3 );
		add_action( 'efml_after_sync', array( $this, 'send_mail_after_sync' ), 10, 3 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Email notification', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get the export tab.
		$export_tab = $settings_page->get_tab( 'synchronization' );
		if ( ! $export_tab instanceof Tab ) {
			return;
		}

		// add a section.
		$section = $export_tab->add_section( 'sync_email', 20 );
		$section->set_title( __( 'Email notification', 'external-files-in-media-library' ) );
		$section->set_callback( array( $this, 'show_info' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'email_sync_email' );
		$setting->set_section( $section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$field = new Text();
		$field->set_title( __( 'Email', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Enter the email address to which a notification should be sent after each synchronization. You can also set this for each external source.', 'external-files-in-media-library' ) );
		$field->set_placeholder( 'info@example.com' );
		$field->set_sanitize_callback( array( $this, 'sanitize_email' ) );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_sync_extensions' ), true ) );
		$setting->set_field( $field );
	}

	/**
	 * Add a config on sync configuration form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_option_on_sync_config( string $form, int $term_id ): string {
		// bail if extension is disabled.
		if ( ! in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $form;
		}

		// get actual email.
		$email = get_term_meta( $term_id, 'email', true );

		// add the HTML-code.
		$form .= '<div><label for="email">' . __( 'Send email after sync to:', 'external-files-in-media-library' ) . '</label><input type="email" id="email" name="email" value="' . esc_attr( $email ) . '" placeholder="info@example.com"></div>';

		// return the resulting html-code for the form.
		return $form;
	}

	/**
	 * Save the custom sync configuration for an external directory.
	 *
	 * @param array<string,string> $fields List of fields.
	 *
	 * @return void
	 */
	public function save_sync_settings( array $fields ): void {
		// bail if extension is disabled.
		if ( ! in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if "use_specific_date" is empty, just remove the setting.
		if ( empty( $fields['email'] ) ) {
			delete_term_meta( $term_id, 'email' );
			return;
		}

		// bail if given string is not an email.
		if ( ! is_email( $fields['email'] ) ) {
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'email', $fields['email'] );
	}

	/**
	 * Validate the email from config dialog for sync.
	 *
	 * @param bool                $result The result.
	 * @param array<string,mixed> $fields The submitted fields.
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return bool
	 */
	public function validate_sync_config( bool $result, array $fields, array $dialog ): bool {
		// bail if email is not in list of fields.
		if ( ! isset( $fields['email'] ) ) {
			return $result;
		}

		// bail if email field is empty.
		if ( empty( $fields['email'] ) ) {
			return $result;
		}

		// bail if email is valid.
		if ( is_email( $fields['email'] ) ) {
			return $result;
		}

		// add a hint in the dialog.
		$dialog['texts'][] = '<p>' . __( 'Given email is not valid!', 'external-files-in-media-library' ) . '</p>';
		wp_send_json( array( 'detail' => $dialog ) );

		// return true to prevent any further processing.
		return true; // @phpstan-ignore deadCode.unreachable
	}

	/**
	 * Send an email after sync has been completed.
	 *
	 * @param string               $url The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id The used term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function send_mail_after_sync( string $url, array $term_data, int $term_id ): void {
		// get the to-email from settings.
		$to = get_term_meta( $term_id, 'email', true );

		// bail if no email is given.
		if ( empty( $to ) ) {
			return;
		}

		// get the term.
		$term = get_term_by( 'term_id', $term_id, Taxonomy::get_instance()->get_name() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// define mail.
		$subject = '[' . get_option( 'blogname' ) . '] ' . __( 'Synchronisation completed', 'external-files-in-media-library' );
		/* translators: %1$s will be replaced by a title. */
		$body    = sprintf( __( 'The synchronization of %1$s has been successfully completed.', 'external-files-in-media-library' ), esc_html( $term->name ) ) . '<br><br>' . __( 'This email was generated by the WordPress plugin <em>External files for Media Library</em> based on the settings in your project.', 'external-files-in-media-library' );
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// send mail.
		wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Show info about disabled settings.
	 *
	 * @return void
	 */
	public function show_info(): void {
		// bail if extension is enabled.
		if ( in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// show hint to enable the extension.
		echo esc_html__( 'Enable the extension in the settings above to use these options.', 'external-files-in-media-library' );
	}

	/**
	 * Validate the given email-address.
	 *
	 * @param null|string $value The value.
	 *
	 * @return string
	 */
	public function sanitize_email( null|string $value ): string {
		// convert it to a string.
		if ( ! is_string( $value ) ) {
			$value = '';
		}

		// get option.
		$option = str_replace( 'sanitize_option_', '', (string) current_filter() );

		// check if the given email is valid.
		if ( ! empty( $value ) && ! is_email( $value ) ) {
			/* translators: %1$s will be replaced by the name of the used interval */
			add_settings_error( $option, $option, sprintf( __( 'The given email %1$s is not valid.', 'external-files-in-media-library' ), esc_html( $value ) ) );
		}

		// return the value.
		return $value;
	}
}
