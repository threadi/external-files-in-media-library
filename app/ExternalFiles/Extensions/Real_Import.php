<?php
/**
 * This file controls how to import external files for real in media library without external connection.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;

/**
 * Handler controls how to import external files for real in media library without external connection.
 */
class Real_Import extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'real_import';

	/**
	 * Instance of actual object.
	 *
	 * @var Real_Import|null
	 */
	private static ?Real_Import $instance = null;

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
	 * @return Real_Import
	 */
	public static function get_instance(): Real_Import {
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
		add_filter( 'eml_http_save_local', array( $this, 'import_local_on_real_import' ) );
		add_filter( 'eml_file_import_attachment', array( $this, 'add_title_on_real_import' ), 10, 3 );
		add_filter( 'eml_import_no_external_file', array( $this, 'save_file_local' ), 10, 0 );
		add_filter( 'eml_add_dialog', array( $this, 'add_option_in_form' ) );
		add_filter( 'eml_import_options', array( $this, 'add_import_option_to_list' ) );
		add_action( 'eml_cli_arguments', array( $this, 'check_cli_arguments' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Really import each file', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the advanced section.
		$advanced_tab_advanced = $settings_obj->get_section( 'settings_section_dialog' );

		// bail if section could not be loaded.
		if ( ! $advanced_tab_advanced ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_real_import' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => $this->get_title(),
				'description' => __( 'If this option is enabled each external URL will be imported as real file in your media library. They will not be "external files" in your media library.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
	}

	/**
	 * Return true if real import is enabled to force local saving of each file.
	 *
	 * @param bool $result The result.
	 *
	 * @return bool
	 */
	public function import_local_on_real_import( bool $result ): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $result;
		}

		// return true to mark this as local importing file.
		return true;
	}

	/**
	 * Add title for file if real import is enabled.
	 *
	 * @param array<string,mixed> $post_array The attachment settings.
	 * @param string              $url        The requested external URL.
	 * @param array<string,mixed> $file_data  List of file settings detected by importer.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_title_on_real_import( array $post_array, string $url, array $file_data ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $post_array;
		}

		// add the title.
		$post_array['post_title'] = $file_data['title'];

		// return the resulting array.
		return $post_array;
	}

	/**
	 * Save file local if setting during import or global is enabled.
	 *
	 * @return bool
	 */
	public function save_file_local(): bool {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// return whether to import this file as real file and not external.
		return 1 === $real_import;
	}

	/**
	 * Add a checkbox to mark the files to add them real and not as external files.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_in_form( array $dialog ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// get the actual state for the checkbox.
		$checked = 1 === absint( get_option( 'eml_real_import' ) );

		// if user has its own setting, use this.
		if ( ImportDialog::get_instance()->is_customization_allowed() ) {
			$checked = 1 === absint( get_user_meta( get_current_user_id(), 'efml_' . $this->get_name(), true ) );
		}

		// collect the entry.
		$text = '<label for="real_import"><input type="checkbox" name="real_import" id="real_import" value="1" class="eml-use-for-import"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Really import each file. Files are not imported as external files.', 'external-files-in-media-library' );

		// add link to user settings.
		$url   = add_query_arg(
			array(),
			get_admin_url() . 'profile.php'
		);
		$text .= '<a href="' . esc_url( $url ) . '#efml-settings" target="_blank" title="' . esc_attr__( 'Go to user settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-users"></span></a>';

		// add link to global settings.
		if ( current_user_can( 'manage_options' ) ) {
			$text .= '<a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_advanced' ) ) . '" target="_blank" title="' . esc_attr__( 'Go to plugin settings', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-admin-generic"></span></a>';
		}

		// end the text.
		$text .= '</label>';

		// add the field.
		$dialog['texts'][] = $text;

		// return the resulting fields.
		return $dialog;
	}

	/**
	 * Add option to the list of all options used during an import, if set.
	 *
	 * @param array<string,mixed> $options List of options.
	 *
	 * @return array<string,mixed>
	 */
	public function add_import_option_to_list( array $options ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// bail if our option is not set.
		if ( ! isset( $_POST['real_import'] ) ) {
			return $options;
		}

		// add the option to the list.
		$options['real_import'] = absint( $_POST['real_import'] );

		return $options;
	}

	/**
	 * Check the WP CLI arguments before import of URLs there.
	 *
	 * @param array<string,mixed> $arguments List of WP CLI arguments.
	 *
	 * @return void
	 */
	public function check_cli_arguments( array $arguments ): void {
		$_POST['real_import'] = isset( $arguments['real_import'] ) ? 1 : 0;
	}

	/**
	 * Add option for the user-specific setting.
	 *
	 * @param array<string,array<string,mixed>> $settings List of settings.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_user_setting( array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $settings;
		}

		// add our setting.
		$settings[ $this->get_name() ] = array(
			'label'       => __( 'Really import each file', 'external-files-in-media-library' ),
			'description' => __( 'Files are not imported as external files.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}
}
