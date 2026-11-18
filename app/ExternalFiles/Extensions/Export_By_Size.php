<?php
/**
 * This file controls the option to export only specific file sizes.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Number;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\ExportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
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
	 * The extension types.
	 *
	 * @var array<int,string>
	 */
	protected array $extension_types = array( 'export_dialog' );

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
		add_filter( 'efml_export_prevent_reason', array( $this, 'get_prevent_reason' ), 10, 3 );
		add_action( 'efml_table_column_local_file', array( $this, 'show_marker' ) );

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
		$settings_obj = Settings::get_instance()->get_settings_obj();

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
		$section->set_hidden( 1 !== absint( get_option( 'eml_export' ) ) || ! in_array( $this->get_name(), (array) get_option( 'eml_export_extensions' ), true ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_min_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Minimum file size', 'external-files-in-media-library' ) );
		$field->set_description( $this->get_size_description( absint( get_option( 'eml_export_min_size' ) ) ) );
		$field->set_min( 0 );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_max_size' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Maximum file size', 'external-files-in-media-library' ) );
		$field->set_description( $this->get_size_description( absint( get_option( 'eml_export_max_size' ) ) ) );
		$field->set_min( 0 );
		$setting->set_field( $field );
	}

	/**
	 * Return the description for a field of a file size with the given value.
	 *
	 * @param int $size The size in bytes.
	 *
	 * @return string
	 */
	private function get_size_description( int $size ): string {
		// get the description.
		$description = __( 'In Byte. If set to 0 it will be ignored.', 'external-files-in-media-library' );

		// add the value in a readable form.
		if ( $size > 0 ) {
			/* translators: %1$s will be replaced by a file size, e.g. "50 MB". */
			$description .= ' ' . sprintf( __( 'Currently %1$s.', 'external-files-in-media-library' ), size_format( $size, 1 ) );
		}

		// return the description.
		return $description;
	}

	/**
	 * Return the size of the local file of the given attachment or -1, if it does not exist.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	private function get_local_file_size( int $attachment_id ): int {
		// get "WP_Filesystem" object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the absolute file path.
		$file_path = (string) get_attached_file( $attachment_id );

		// bail if file does not exist.
		if ( '' === $file_path || ! $wp_filesystem->exists( $file_path ) ) {
			return -1;
		}

		// return the file size.
		return absint( $wp_filesystem->size( $file_path ) );
	}

	/**
	 * Return the reason, why the given file is not exported due to its size, or an empty string, if it is exported.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $term_id       The ID of an external source to use its limits or 0 for the global limits.
	 *
	 * @return string
	 */
	public function get_size_problem( int $attachment_id, int $term_id = 0 ): string {
		// bail if the extension is not enabled.
		if ( ! in_array( $this->get_name(), ExportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return '';
		}

		// get the limits.
		$min_size = $term_id > 0 ? absint( get_term_meta( $term_id, 'eml_export_min_size', true ) ) : absint( get_option( 'eml_export_min_size' ) );
		$max_size = $term_id > 0 ? absint( get_term_meta( $term_id, 'eml_export_max_size', true ) ) : absint( get_option( 'eml_export_max_size' ) );

		// bail if no limits are set.
		if ( 0 === $min_size && 0 === $max_size ) {
			return '';
		}

		// get the file size.
		$file_size = $this->get_local_file_size( $attachment_id );

		// bail if the file does not exist.
		if ( $file_size < 0 ) {
			return '';
		}

		// return the reason for a too small file.
		if ( $min_size > 0 && $file_size < $min_size ) {
			if ( $term_id > 0 ) {
				/* translators: %1$s will be replaced by the size of the file, %2$s by the minimum size, e.g. "50 MB". */
				return sprintf( __( 'Its size of %1$s is smaller than the minimum file size of %2$s for this external source.', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $min_size, 1 ) );
			}
			/* translators: %1$s will be replaced by the size of the file, %2$s by the minimum size, e.g. "50 MB". */
			return sprintf( __( 'Its size of %1$s is smaller than the minimum file size of %2$s in the settings for the export.', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $min_size, 1 ) );
		}

		// return the reason for a too large file.
		if ( $max_size > 0 && $file_size > $max_size ) {
			if ( $term_id > 0 ) {
				/* translators: %1$s will be replaced by the size of the file, %2$s by the maximum size, e.g. "50 MB". */
				return sprintf( __( 'Its size of %1$s is larger than the maximum file size of %2$s for this external source.', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $max_size, 1 ) );
			}
			/* translators: %1$s will be replaced by the size of the file, %2$s by the maximum size, e.g. "50 MB". */
			return sprintf( __( 'Its size of %1$s is larger than the maximum file size of %2$s in the settings for the export.', 'external-files-in-media-library' ), size_format( $file_size, 1 ), size_format( $max_size, 1 ) );
		}

		// return an empty string as the file is exported.
		return '';
	}

	/**
	 * Prevent the export of a single file by the global limits for the file size.
	 *
	 * @param bool $result             True for prevent the export.
	 * @param File $external_file_obj The external file object to use.
	 *
	 * @return bool
	 */
	public function prevent_export( bool $result, File $external_file_obj ): bool {
		// bail if the export is prevented already.
		if ( $result ) {
			return $result;
		}

		// return whether the size does not match the limits.
		return '' !== $this->get_size_problem( $external_file_obj->get_id() );
	}

	/**
	 * Prevent the export of a single file to an external source by its limits for the file size.
	 *
	 * @param bool $result             True for prevent the export.
	 * @param File $external_file_obj The external file object to use.
	 * @param int  $term_id           The ID of the external source.
	 *
	 * @return bool
	 */
	public function prevent_export_by_service( bool $result, File $external_file_obj, int $term_id ): bool {
		// bail if the export is prevented already.
		if ( $result ) {
			return $result;
		}

		// return whether the size does not match the limits of this external source.
		return '' !== $this->get_size_problem( $external_file_obj->get_id(), $term_id );
	}

	/**
	 * Return the reason, why the export of the given file is prevented, for the log.
	 *
	 * @param string $reason            The reason.
	 * @param File   $external_file_obj The external file object.
	 * @param int    $term_id           The ID of the external source or 0.
	 *
	 * @return string
	 */
	public function get_prevent_reason( string $reason, File $external_file_obj, int $term_id ): string {
		// bail if a reason is known already.
		if ( '' !== $reason ) {
			return $reason;
		}

		// return the reason due to the size.
		return $this->get_size_problem( $external_file_obj->get_id(), $term_id );
	}

	/**
	 * Show a marker for local files, which are not exported due to their size, in the media library.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function show_marker( int $attachment_id ): void {
		// bail if the export is not enabled.
		if ( ! Export::get_instance()->is_enabled() ) {
			return;
		}

		// get the reason.
		$reason = $this->get_size_problem( $attachment_id );

		// bail if the file is exported.
		if ( '' === $reason ) {
			return;
		}

		// show the marker.
		echo ' <span class="dashicons dashicons-media-default" title="' . esc_attr( __( 'This file is not exported.', 'external-files-in-media-library' ) . ' ' . $reason ) . '"></span>';
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
		$dialog['texts'][] = '<div><label for="min_size">' . __( 'Minimum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="min_size" id="min_size" value="' . $min_size . '">' . esc_html( $this->get_size_description( $min_size ) ) . '</div>';
		$dialog['texts'][] = '<div><label for="max_size">' . __( 'Maximum file size:', 'external-files-in-media-library' ) . '</label><input type="number" name="max_size" id="max_size" value="' . $max_size . '">' . esc_html( $this->get_size_description( $max_size ) ) . '</div>';

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

		// get the file size.
		$file_size = $external_file_obj->get_filesize();

		// bail if the file size does match the settings (same rules as for the export itself).
		if ( ! ( $min_size > 0 && $file_size < $min_size ) && ! ( $max_size > 0 && $file_size > $max_size ) ) {
			return $actions;
		}

		// remove the option to export this file.
		unset( $actions['eml-export-file'] );

		// return the resulting list of actions.
		return $actions;
	}
}
