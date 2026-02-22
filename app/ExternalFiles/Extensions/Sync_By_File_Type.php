<?php
/**
 * This file controls the option to sync only specific file types.
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
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Handler controls the option for sync only specific file types.
 */
class Sync_By_File_Type extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'sync_by_file_type';

	/**
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'sync_dialog' );

	/**
	 * The synchronized term ID.
	 *
	 * @var int
	 */
	private int $synced_term_id = 0;

	/**
	 * Instance of actual object.
	 *
	 * @var Sync_By_File_Type|null
	 */
	private static ?Sync_By_File_Type $instance = null;

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
	 * @return Sync_By_File_Type
	 */
	public static function get_instance(): Sync_By_File_Type {
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
		add_filter( 'efml_sync_configure_form', array( $this, 'extend_sync_form_in_dialog' ), 10, 2 );
		add_action( 'efml_sync_save_config', array( $this, 'save_sync_config' ), 10, 2 );
		add_action( 'efml_before_sync', array( $this, 'add_hooks_before_sync' ), 10, 3 );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Synchronize by file types', 'external-files-in-media-library' );
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
		$section = $export_tab->add_section( 'sync_by_file_type', 20 );
		$section->set_title( __( 'Synchronization by file types', 'external-files-in-media-library' ) );
		$section->set_callback( array( $this, 'show_info' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_sync_file_types' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$field = new MultiSelect();
		$field->set_title( __( 'File types', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Chose the file types that should be sync from external sources. If none are selected, all file types are allowed.', 'external-files-in-media-library' ) );
		$field->set_options( Helper::get_possible_mime_types_for_settings() );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_sync_extensions' ), true ) );
		$setting->set_field( $field );
	}

	/**
	 * Extend the config dialog for each external source with the selection of allowed file types.
	 *
	 * @param string $form The HTML code for the form.
	 * @param int    $term_id The used term ID.
	 *
	 * @return string
	 */
	public function extend_sync_form_in_dialog( string $form, int $term_id ): string {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $form;
		}

		// get the actual setting for this term.
		$allowed_file_types = get_term_meta( $term_id, 'eml_sync_file_types', true );

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
		$form .= '<div><label for="allowed_file_types">' . __( 'File types:', 'external-files-in-media-library' ) . '</label><select multiple name="allowed_file_types[]" id="allowed_file_types">' . $possible_file_types_html . '</select>' . __( 'Chose the file types that should be sync from external sources. If none are selected, all file types are allowed.', 'external-files-in-media-library' ) . '</div>';

		// return the resulting form.
		return $form;
	}

	/**
	 * Save our custom configuration for allowed file types on a term.
	 *
	 * @param array<string,mixed> $fields List of fields from request.
	 * @param int                 $term_id The used term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function save_sync_config( array $fields, int $term_id ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), SynchronizationDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// get the list of allowed file types from request.
		$allowed_file_types = isset( $_POST['fields']['allowed_file_types'] ) ? array_map( 'wp_kses_post', wp_unslash( $_POST['fields']['allowed_file_types'] ) ) : array();

		// save it on the term.
		update_term_meta( $term_id, 'eml_sync_file_types', $allowed_file_types );
	}

	/**
	 * Add hooks before sync of single URL is starting.
	 *
	 * @param string               $url       The used URL.
	 * @param array<string,string> $term_data The term data.
	 * @param int                  $term_id   The used term ID.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_hooks_before_sync( string $url, array $term_data, int $term_id ): void {
		// get global setting.
		$allow_file_types_global = (array) get_option( 'eml_sync_file_types' );

		// get setting on the term.
		$allow_file_types_term = (array) get_term_meta( $term_id, 'eml_sync_file_types', true );

		// bail if both are empty.
		if ( empty( $allow_file_types_global ) && empty( $allow_file_types_term ) ) {
			return;
		}

		// secure the used term ID.
		$this->synced_term_id = $term_id;

		// add hooks to prevent the synchronization of single files from the given URL by their file type.
		add_filter( 'efml_prevent_file_import', array( $this, 'prevent_sync' ), 10, 2 );
	}

	/**
	 * Prevent the sync of single files by their file type.
	 *
	 * @param bool                $result The return value, should be true to prevent the import.
	 * @param array<string,mixed> $file_data The file data we got from the external source.
	 *
	 * @return bool
	 */
	public function prevent_sync( bool $result, array $file_data ): bool {
		// bail if term ID is missing.
		if ( 0 === $this->synced_term_id ) {
			return $result;
		}

		// get global setting.
		$allow_file_types_global = get_option( 'eml_sync_file_types' );

		// get it as an array.
		if ( ! is_array( $allow_file_types_global ) ) {
			$allow_file_types_global = array();
		}

		// prevent import, if mime type is not in the global list.
		if ( ! empty( $allow_file_types_global ) && ! in_array( $file_data['mime-type'], $allow_file_types_global, true ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Synchronization of file has been prevented as it has a not allowed file type.', 'external-files-in-media-library' ), $file_data['url'], 'info', 2 );

			// return true to prevent the import.
			return true;
		}

		// get setting on the term.
		$allow_file_types_term = get_term_meta( $this->synced_term_id, 'eml_sync_file_types', true );

		// get it as an array.
		if ( ! is_array( $allow_file_types_term ) ) {
			$allow_file_types_term = array();
		}

		// prevent import, if mime type is not in the specific list.
		if ( ! empty( $allow_file_types_term ) && ! in_array( $file_data['mime-type'], $allow_file_types_term, true ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Synchronization of file has been prevented as it has a not allowed file type.', 'external-files-in-media-library' ), $file_data['url'], 'info', 2 );

			// return true to prevent the import.
			return true;
		}

		// return the initial value.
		return $result;
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
}
