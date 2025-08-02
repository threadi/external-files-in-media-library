<?php
/**
 * This file controls the option to use the original date of external files.
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
 * Handler controls how to import external files with their original dates.
 */
class Dates extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'dates';

	/**
	 * Instance of actual object.
	 *
	 * @var Dates|null
	 */
	private static ?Dates $instance = null;

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
	 * @return Dates
	 */
	public static function get_instance(): Dates {
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
		add_filter( 'eml_file_import_attachment', array( $this, 'add_file_date' ), 10, 3 );
		add_filter( 'eml_add_dialog', array( $this, 'add_date_option_in_form' ) );
		add_filter( 'eml_import_options', array( $this, 'add_import_option_to_list' ) );
		add_action( 'eml_cli_arguments', array( $this, 'check_cli_arguments' ) );
		add_filter( 'efml_user_settings', array( $this, 'add_user_setting' ) );
		add_filter( 'efml_service_rest_file_data', array( $this, 'add_file_date_from_rest_api' ), 10, 3 );

		// sync tasks.
		add_filter( 'efml_sync_configure_form', array( $this, 'add_option_on_sync_config' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_settings' ) );
		add_action( 'efml_before_sync', array( $this, 'add_action_before_sync' ), 10, 3 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Use external file dates', 'external-files-in-media-library' );
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
		$setting = $settings_obj->add_setting( 'eml_use_file_dates' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => $this->get_title(),
				'description' => __( 'If this option is enabled all external files will be saved in media library with the date set by the external location. If the external location does not set any date the actual date will be used.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
	}

	/**
	 * Add file date to post array to set the date of the external file during import.
	 *
	 * @param array<string,mixed> $post_array The attachment settings.
	 * @param string              $url        The requested external URL.
	 * @param array<string,mixed> $file_data  List of file settings detected by importer.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_file_date( array $post_array, string $url, array $file_data ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$use_date = isset( $_POST['use_dates'] ) ? absint( $_POST['use_dates'] ) : -1;

		// bail if not set from request.
		if ( -1 === $use_date ) {
			return $post_array;
		}

		// bail if not enabled in request.
		if ( 0 === $use_date ) {
			return $post_array;
		}

		// bail if no last-modified is given.
		if ( empty( $file_data['last-modified'] ) ) {
			return $post_array;
		}

		// add the last-modified date.
		$post_array['post_date'] = gmdate( 'Y-m-d H:i:s', $file_data['last-modified'] );

		// return the resulting array.
		return $post_array;
	}

	/**
	 * Add date from REST API response to file data.
	 *
	 * @param array<string,mixed>  $results The results to use.
	 * @param string $file_path The used URL.
	 * @param array<string,mixed>  $rest_file_data The REST API response data.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_file_date_from_rest_api( array $results, string $file_path, array $rest_file_data ): array {
		// get value from request.
		$use_date = isset( $_POST['use_dates'] ) ? absint( $_POST['use_dates'] ) : -1;

		// bail if not set from request.
		if ( -1 === $use_date ) {
			return $results;
		}

		// bail if not enabled in request.
		if ( 0 === $use_date ) {
			return $results;
		}

		// bail if no last-modified is given.
		if ( empty( $rest_file_data['date'] ) ) {
			return $results;
		}

		// add the last-modified date.
		$results['last-modified'] = strtotime( $rest_file_data['date'] );

		// return the resulting array.
		return $results;
	}

	/**
	 * Add a checkbox to mark the files to use their external dates.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_date_option_in_form( array $dialog ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// get the actual state for the checkbox.
		$checked = 1 === absint( get_option( 'eml_use_file_dates' ) );

		// if user has its own setting, use this.
		if ( ImportDialog::get_instance()->is_customization_allowed() ) {
			$checked = 1 === absint( get_user_meta( get_current_user_id(), 'efml_' . $this->get_name(), true ) );
		}

		// collect the entry.
		$text = '<label for="use_dates"><input type="checkbox" name="use_dates" id="use_dates" value="1" class="eml-use-for-import"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Use dates of the external files.', 'external-files-in-media-library' );

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
		if ( ! isset( $_POST['use_dates'] ) ) {
			return $options;
		}

		// add the option to the list.
		$options['use_dates'] = absint( $_POST['use_dates'] );

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
		$_POST['use_dates'] = isset( $arguments['use_dates'] ) ? 1 : 0;
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
			'label'       => __( 'Use dates of the external files', 'external-files-in-media-library' ),
			'description' => __( 'If enabled we try to use the dates external files have. External files without this information will be saved with the actual date.', 'external-files-in-media-library' ),
			'field'       => 'checkbox',
		);

		// return the settings.
		return $settings;
	}

	/**
	 * Add config on sync configuration form.
	 *
	 * @param string $form The HTML-code of the form.
	 * @param int    $term_id The term ID.
	 *
	 * @return string
	 */
	public function add_option_on_sync_config( string $form, int $term_id ): string {
		// get the actual setting.
		$checked = 1 === absint( get_term_meta( $term_id, 'use_dates', true ) );

		// add the HTML-code.
		$form .= '<div><label for="use_dates"><input type="checkbox" name="use_dates" id="use_dates" value="1"' . ( $checked ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Use dates of the external files.', 'external-files-in-media-library' ) . '</label></div>';

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
		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// if "use_dates" is 0, just remove the setting.
		if ( 0 === absint( $fields['use_dates'] ) ) {
			delete_term_meta( $term_id, 'use_dates' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'use_dates', 1 );
	}

	/**
	 * Add setting to import files with its dates before sync.
	 *
	 * @param string               $url The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id The term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_action_before_sync( string $url, array $term_data, int $term_id ): void {
		// bail if settings is not set.
		if( 1 !== absint( get_term_meta( $term_id, 'use_dates', true ) ) ) {
			return;
		}

		// set use_dates to 1.
		$_POST['use_dates'] = 1;
	}
}
