<?php
/**
 * This file controls the option to export only specific file types.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\MultiSelect;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;

/**
 * Handler controls the option for export only specific file types.
 */
class Export_By_File_Type extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'export_by_file_type';

	/**
	 * The extension type.
	 *
	 * @var string
	 */
	protected string $extension_type = 'export_dialog';

	/**
	 * Instance of actual object.
	 *
	 * @var Export_By_File_Type|null
	 */
	private static ?Export_By_File_Type $instance = null;

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
	 * @return Export_By_File_Type
	 */
	public static function get_instance(): Export_By_File_Type {
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
		add_filter( 'efml_prevent_export', array( $this, 'prevent_export' ), 10, 2 );
		add_filter( 'efml_export_config_dialog', array( $this, 'extend_export_config_dialog' ), 10, 2 );
		add_action( 'efml_export_save_config', array( $this, 'save_export_config' ) );
		add_filter( 'efml_prevent_export_on_service', array( $this, 'prevent_export_by_service' ), 10, 3 );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 30, 2 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Export by file types', 'external-files-in-media-library' );
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
		if( ! $settings_page instanceof Page ) {
			return;
		}

		// get the export tab.
		$export_tab = $settings_page->get_tab( 'eml_export' );
		if ( ! $export_tab instanceof Tab ) {
			return;
		}

		// add a section.
		$section = $export_tab->add_section( 'export_by_file_type', 20 );
		$section->set_title( __( 'Export by file types', 'external-files-in-media-library' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_file_types' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$field = new MultiSelect();
		$field->set_title( __( 'File types', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Chose the file types that should be exported to external sources. If none are selected, all file types are allowed.', 'external-files-in-media-library' ) );
		$field->set_options( Helper::get_possible_mime_types_for_settings() );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_export_extensions' ), true ) );
		$setting->set_field( $field );
	}

	/**
	 * Prevent the export of a single file by global setting for its file type.
	 *
	 * @param bool $result             True for prevent the export.
	 * @param File $external_file_obj The external file object to use.
	 *
	 * @return bool
	 */
	public function prevent_export( bool $result, File $external_file_obj ): bool {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $result;
		}

		// get the global setting.
		$allowed_file_types = get_option( 'eml_export_file_types' );

		// bail if global setting is empty.
		if ( empty( $allowed_file_types ) ) {
			return $result;
		}

		// return the result if the files mime type is in list.
		$result = ! in_array( $external_file_obj->get_mime_type(), $allowed_file_types, true );

		// log the result.
		Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file type because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

		// return the result.
		return $result;
	}

	/**
	 * Prevent the export of a single file by global setting for its file type.
	 *
	 * @param bool $result             True for prevent the export.
	 * @param File $external_file_obj The external file object to use.
	 * @param int  $term_id           The ID of the external source.
	 *
	 * @return bool
	 */
	public function prevent_export_by_service( bool $result, File $external_file_obj, int $term_id ): bool {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $result;
		}

		// get the global setting.
		$allowed_file_types = get_term_meta( $term_id, 'eml_export_file_types', true );

		// check for an array.
		if ( ! is_array( $allowed_file_types ) ) {
			$allowed_file_types = array();
		}

		// bail if global setting is empty.
		if ( empty( $allowed_file_types ) ) {
			return $result;
		}

		// return the result if the files mime type is in list.
		$result = ! in_array( $external_file_obj->get_mime_type(), $allowed_file_types, true );

		// log the result.
		Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file type because of the external source setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

		// return the result.
		return $result;
	}

	/**
	 * Extend the config dialog for each external source with the selection of allowed file types.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param int                 $term_id The used term ID.
	 *
	 * @return array<string,mixed>
	 */
	public function extend_export_config_dialog( array $dialog, int $term_id ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// get the actual setting for this term.
		$allowed_file_types = get_term_meta( $term_id, 'eml_export_file_types', true );

		// convert it to an array.
		if ( ! is_array( $allowed_file_types ) ) {
			$allowed_file_types = array();
		}

		// get the list of possible file types.
		$possible_file_types      = Helper::get_possible_mime_types_for_settings();
		$possible_file_types_html = '';
		foreach ( $possible_file_types as $key => $value ) {
			$possible_file_types_html .= '<option value="' . $key . '"' . ( in_array( $key, $allowed_file_types, true ) ? ' selected' : '' ) . '>' . $value . '</option>';
		}

		// add the field to the dialog.
		$dialog['texts'][] = '<div><label for="allowed_file_types">' . __( 'File types:', 'external-files-in-media-library' ) . '</label><select multiple name="allowed_file_types[]" id="allowed_file_types">' . $possible_file_types_html . '</select>' . __( 'Chose the file types that should be exported to external sources. If none are selected, all file types are allowed.', 'external-files-in-media-library' ) . '</div>';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Save our custom configuration for allowed file types on a term.
	 *
	 * @param int $term_id The used term ID.
	 *
	 * @return void
	 */
	public function save_export_config( int $term_id ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// get the list of allowed file types from request.
		$allowed_file_types = isset( $_POST['allowed_file_types'] ) ? array_map( 'wp_kses_post', wp_unslash( $_POST['allowed_file_types'] ) ) : array();

		// save it on the term.
		update_term_meta( $term_id, 'eml_export_file_types', $allowed_file_types );
	}

	/**
	 * Remove the option to export a file, if its file type is not listet in global setting for allowed file types.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// bail if export option is not set.
		if ( ! isset( $actions['eml-export-file'] ) ) {
			return $actions;
		}

		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $actions;
		}

		// get the global setting.
		$allowed_file_types = get_option( 'eml_export_file_types' );

		// bail if global setting is empty.
		if ( empty( $allowed_file_types ) ) {
			return $actions;
		}

		// bail if the file type is listed in the global settings.
		if ( in_array( $post->post_mime_type, $allowed_file_types, true ) ) {
			return $actions;
		}

		// remove the option to export this file.
		unset( $actions['eml-export-file'] );

		// return the resulting list of actions.
		return $actions;
	}
}
