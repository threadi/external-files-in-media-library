<?php
/**
 * This file controls the option to sync only specific file types.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\SynchronizationDialog;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Handler controls the option for sync only specific file types.
 */
class Sync_By_Size extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'sync_by_size';

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
	 * @var Sync_By_Size|null
	 */
	private static ?Sync_By_Size $instance = null;

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
	 * @return Sync_By_Size
	 */
	public static function get_instance(): Sync_By_Size {
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
		return __( 'Synchronize by file size', 'external-files-in-media-library' );
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
		$section = $export_tab->add_section( 'sync_by_file_size', 20 );
		$section->set_title( __( 'Synchronization by file size', 'external-files-in-media-library' ) );
		$section->set_callback( array( $this, 'show_info' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_sync_min_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number();
		$field->set_title( __( 'Minimum file size', 'external-files-in-media-library' ) );
		$field->set_description( __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) );
		$field->set_min( 0 );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_sync_extensions' ), true ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_sync_max_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number();
		$field->set_title( __( 'Maximum file size', 'external-files-in-media-library' ) );
		$field->set_description( __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) );
		$field->set_min( 0 );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_sync_extensions' ), true ) );
		$setting->set_field( $field );
	}

	/**
	 * Extend the config dialog for each external source with settings for max. and min. file sizes.
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
		$min_size = absint( get_term_meta( $term_id, 'eml_sync_min_size', true ) );
		$max_size = absint( get_term_meta( $term_id, 'eml_sync_max_size', true ) );

		$form .= '<div><label for="min_size">' . __( 'Minimum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="min_size" id="min_size" value="' . $min_size . '">' . __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) . '</div>';
		$form .= '<div><label for="max_size">' . __( 'Maximum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="max_size" id="max_size" value="' . $max_size . '">' . __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) . '</div>';

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

		// get the values.
		$min_size = absint( filter_input( INPUT_POST, 'min_size', FILTER_SANITIZE_NUMBER_INT ) );
		$max_size = absint( filter_input( INPUT_POST, 'max_size', FILTER_SANITIZE_NUMBER_INT ) );

		// save them on the term.
		update_term_meta( $term_id, 'eml_sync_min_size', $min_size );
		update_term_meta( $term_id, 'eml_sync_max_size', $max_size );
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
		// secure the used term ID.
		$this->synced_term_id = $term_id;

		// add hooks to prevent the synchronization of single files from the given URL by their file type.
		add_filter( 'efml_prevent_file_import', array( $this, 'prevent_sync' ), 10, 2 );
	}

	/**
	 * Prevent the sync of single files by their file size.
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

		// get the file size from the file data.
		$file_size = $file_data['filesize'];

		// get the global setting.
		$min_size = absint( get_option( 'eml_sync_min_size' ) );
		$max_size = absint( get_option( 'eml_sync_max_size' ) );

		// prevent export if file size is lower than the minimum size, if configured.
		if ( $min_size > 0 && $file_size < $min_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the synchronization of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $file_data['url'], 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// prevent export if file size is higher than the maximum size, if configured.
		if ( $max_size > 0 && $file_size > $max_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the synchronization of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $file_data['url'], 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// get the setting for this term.
		$min_size = absint( get_term_meta( $this->synced_term_id, 'eml_sync_min_size', true ) );
		$max_size = absint( get_term_meta( $this->synced_term_id, 'eml_sync_max_size', true ) );

		// prevent export if file size is lower than the minimum size, if configured.
		if ( $min_size > 0 && $file_size < $min_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the synchronization of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $file_data['url'], 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// prevent export if file size is higher than the maximum size, if configured.
		if ( $max_size > 0 && $file_size > $max_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the synchronization of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $file_data['url'], 'info', 2 );

			// return true to prevent the export.
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
