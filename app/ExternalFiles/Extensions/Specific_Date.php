<?php
/**
 * This file controls the option to use a specific date for external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;

/**
 * Handler controls how to import external files with a specific date.
 */
class Specific_Date extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'specific_date';

	/**
	 * Instance of actual object.
	 *
	 * @var Specific_Date|null
	 */
	private static ?Specific_Date $instance = null;

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
	 * @return Specific_Date
	 */
	public static function get_instance(): Specific_Date {
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
		// use our own hooks.
		add_filter( 'efml_file_import_attachment', array( $this, 'add_file_date' ), 10, 3 );
		add_filter( 'efml_add_dialog', array( $this, 'add_date_option_in_form' ) );
		add_filter( 'efml_import_options', array( $this, 'add_import_option_to_list' ) );
		add_filter( 'efml_service_rest_file_data', array( $this, 'add_file_date_from_rest_api' ), 10, 3 );
		add_action( 'efml_cli_arguments', array( $this, 'check_cli_arguments' ) );

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
		return __( 'Use specific date', 'external-files-in-media-library' );
	}

	/**
	 * Add specified file date to post array to set the date of the external file during import.
	 *
	 * @param array<string,mixed> $post_array The attachment settings.
	 * @param string              $url        The requested external URL.
	 * @param array<string,mixed> $file_data  List of file settings detected by the importer.
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
		$use_specific_date = isset( $_POST['use_specific_date'] ) ? sanitize_text_field( wp_unslash( $_POST['use_specific_date'] ) ) : '';

		// bail if not set from request.
		if ( empty( $use_specific_date ) ) {
			return $post_array;
		}

		// add the last-modified date.
		$post_array['post_date'] = gmdate( 'Y-m-d H:i:s', (int) strtotime( $use_specific_date ) );

		// return the resulting array.
		return $post_array;
	}

	/**
	 * Add date from REST API response to file data.
	 *
	 * @param array<string,mixed> $results The results to use.
	 * @param string              $file_path The used URL.
	 * @param array<string,mixed> $rest_file_data The REST API response data.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_file_date_from_rest_api( array $results, string $file_path, array $rest_file_data ): array {
		// get value from request.
		$use_specific_date = filter_input( INPUT_POST, 'use_specific_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if not set from request.
		if ( is_null( $use_specific_date ) ) {
			return $results;
		}

		// add the last-modified date.
		$results['last-modified'] = strtotime( $use_specific_date );

		// return the resulting array.
		return $results;
	}

	/**
	 * Add a date selection field to import dialog.
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

		// collect the entry.
		$dialog['texts'][] = '<details><summary>' . __( 'Choose specific date for each file', 'external-files-in-media-library' ) . '</summary><div><input type="date" id="use_specific_date" name="use_specific_date" value="" autocomplete="off"></div></details>';

		// return the resulting fields.
		return $dialog;
	}

	/**
	 * Add option to the list of all options used during an import via queue, if set.
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
		if ( ! isset( $_POST['use_specific_date'] ) ) {
			return $options;
		}

		// add the option to the list.
		$options['use_specific_date'] = sanitize_text_field( wp_unslash( $_POST['use_specific_date'] ) );

		// return the resulting options.
		return $options;
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
		// get the actual setting.
		$value = (string) get_term_meta( $term_id, 'use_specific_date', true );

		// add the HTML-code.
		$form .= '<div><label for="use_specific_date">' . __( 'Choose date for each file:', 'external-files-in-media-library' ) . '</label><input type="date" name="use_specific_date" id="use_specific_date" value="' . esc_attr( $value ) . '"></div>';

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

		// if "use_specific_date" is empty, just remove the setting.
		if ( empty( $fields['use_specific_date'] ) ) {
			delete_term_meta( $term_id, 'use_specific_date' );
			return;
		}

		// save the setting.
		update_term_meta( $term_id, 'use_specific_date', $fields['use_specific_date'] );
	}

	/**
	 * Add setting to import files with the specified date before sync.
	 *
	 * @param string               $url The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id The term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_action_before_sync( string $url, array $term_data, int $term_id ): void {
		// get the actual setting.
		$value = (string) get_term_meta( $term_id, 'use_specific_date', true );

		// if "use_specific_date" is empty, just remove the setting.
		if ( empty( $value ) ) {
			return;
		}

		// set "use_specific_date" to the given date.
		$_POST['use_specific_date'] = $value;
	}

	/**
	 * Check the WP CLI arguments before import of URLs there.
	 *
	 * @param array<string,mixed> $arguments List of WP CLI arguments.
	 *
	 * @return void
	 */
	public function check_cli_arguments( array $arguments ): void {
		$_POST['use_specific_date'] = isset( $arguments['use_specific_date'] ) ? sanitize_text_field( wp_unslash( $arguments['use_specific_date'] ) ) : '';
	}
}
