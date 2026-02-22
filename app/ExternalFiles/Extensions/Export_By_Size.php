<?php
/**
 * This file controls the option to export only specific file sizes.
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
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;

/**
 * Handler controls the option for export only specific file sizes.
 */
class Export_By_Size extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'export_by_size';

	/**
	 * The extension type.
	 *
	 * @var string
	 */
	protected string $extension_type = 'export_dialog';

	/**
	 * Instance of actual object.
	 *
	 * @var Export_By_Size|null
	 */
	private static ?Export_By_Size $instance = null;

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
	 * @return Export_By_Size
	 */
	public static function get_instance(): Export_By_Size {
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
		return __( 'Export by file size', 'external-files-in-media-library' );
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
		$export_tab = $settings_page->get_tab( 'eml_export' );
		if ( ! $export_tab instanceof Tab ) {
			return;
		}

		// add a section.
		$section = $export_tab->add_section( 'export_by_file_size', 20 );
		$section->set_title( __( 'Export by file size', 'external-files-in-media-library' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_min_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number();
		$field->set_title( __( 'Minimum file size', 'external-files-in-media-library' ) );
		$field->set_description( __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) );
		$field->set_min( 0 );
		$field->set_readonly( ! in_array( $this->get_name(), (array) get_option( 'eml_export_extensions' ), true ) );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_max_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number();
		$field->set_title( __( 'Maximum file size', 'external-files-in-media-library' ) );
		$field->set_description( __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) );
		$field->set_min( 0 );
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
		$min_size = absint( get_option( 'eml_export_min_size' ) );
		$max_size = absint( get_option( 'eml_export_max_size' ) );

		// get "WP_Filesystem" object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the absolute file path.
		$file_path = (string) get_attached_file( $external_file_obj->get_id() );

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return $result;
		}

		// get the file size.
		$file_size = $wp_filesystem->size( $file_path );

		// prevent export if file size is lower than the minimum size, if configured.
		if ( $min_size > 0 && $file_size < $min_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// prevent export if file size is higher than the maximum size, if configured.
		if ( $max_size > 0 && $file_size > $max_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file size because of the global setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// return the previous resulting value.
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

		// get the setting for this term.
		$min_size = absint( get_term_meta( $term_id, 'eml_export_min_size', true ) );
		$max_size = absint( get_term_meta( $term_id, 'eml_export_max_size', true ) );

		// get "WP_Filesystem" object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the absolute file path.
		$file_path = (string) get_attached_file( $external_file_obj->get_id() );

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return $result;
		}

		// get the file size.
		$file_size = $wp_filesystem->size( $file_path );

		// prevent export if file size is lower than the minimum size, if configured.
		if ( $min_size > 0 && $file_size < $min_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file size because of the external source setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// prevent export if file size is higher than the maximum size, if configured.
		if ( $max_size > 0 && $file_size > $max_size ) {
			// log the result.
			Log::get_instance()->create( __( 'Result to prevent the export of this file due to its file size because of the external source setting:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $result ) . '</code>', $external_file_obj->get_url( true ), 'info', 2 );

			// return true to prevent the export.
			return true;
		}

		// return the previous resulting value.
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
		$min_size = absint( get_term_meta( $term_id, 'eml_export_min_size', true ) );
		$max_size = absint( get_term_meta( $term_id, 'eml_export_max_size', true ) );

		// add the field to the dialog.
		$dialog['texts'][] = '<div><label for="min_size">' . __( 'Minimum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="min_size" id="min_size" value="' . $min_size . '">' . __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) . '</div>';
		$dialog['texts'][] = '<div><label for="max_size">' . __( 'Maximum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="max_size" id="max_size" value="' . $max_size . '">' . __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' ) . '</div>';

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
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return;
		}

		// get the values.
		$min_size = absint( filter_input( INPUT_POST, 'min_size', FILTER_SANITIZE_NUMBER_INT ) );
		$max_size = absint( filter_input( INPUT_POST, 'max_size', FILTER_SANITIZE_NUMBER_INT ) );

		// save them on the term.
		update_term_meta( $term_id, 'eml_export_min_size', $min_size );
		update_term_meta( $term_id, 'eml_export_max_size', $max_size );
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

		// get the external file object, even if it is not an external file.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// get the global settings.
		$min_size = absint( get_option( 'eml_export_min_size' ) );
		$max_size = absint( get_option( 'eml_export_max_size' ) );

		// bail if no sizes are given.
		if ( 0 === $min_size && 0 === $max_size ) {
			return $actions;
		}

		// bail if the file size does match the settings.
		if ( ( $min_size > 0 && $external_file_obj->get_filesize() > $min_size ) && ( $max_size > 0 && $external_file_obj->get_filesize() < $max_size ) ) {
			return $actions;
		}

		// remove the option to export this file.
		unset( $actions['eml-export-file'] );

		// return the resulting list of actions.
		return $actions;
	}
}
