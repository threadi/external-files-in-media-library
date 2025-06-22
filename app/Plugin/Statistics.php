<?php
/**
 * This file contains an object to handle statistics for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to handle statistics.
 */
class Statistics {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Statistics
	 */
	private static ?Statistics $instance = null;

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
	 * @return Statistics
	 */
	public static function get_instance(): Statistics {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
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
		add_action( 'init', array( $this, 'init_statistics' ), 30 );

		// use our own hooks.
		add_action( 'eml_after_file_save', array( $this, 'add_file_count' ) );
		add_action( 'eml_after_file_save', array( $this, 'add_file_sizes' ) );
		add_action( 'eml_file_delete', array( $this, 'sub_file_count' ) );
		add_action( 'eml_file_delete', array( $this, 'sub_file_sizes' ) );

		// add actions.
		add_action( 'admin_action_eml_recalc_files', array( $this, 'recalc_files_by_request' ) );
	}

	/**
	 * Initiate the statistics settings tab.
	 *
	 * @return void
	 */
	public function init_statistics(): void {
		// get the settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add tab.
		$tab = $settings_page->add_tab( 'eml_statistics', 110 );
		$tab->set_title( __( 'Statistics', 'external-files-in-media-library' ) );
		$tab->set_hide_save( true );

		// add section for file statistics.
		$section_files = $tab->add_section( 'section_file_statistics', 10 );
		$section_files->set_title( __( 'Files', 'external-files-in-media-library' ) );

		// add setting for file count.
		$file_count_setting = $settings_obj->add_setting( 'eml_file_count' );
		$file_count_setting->set_section( $section_files );
		$file_count_setting->set_type( 'integer' );
		$file_count_setting->set_default( 0 );
		$file_count_setting->prevent_export( true );
		$file_count_setting->set_field(
			array(
				'type'  => 'Value',
				'title' => __( 'External file counter', 'external-files-in-media-library' ),
			)
		);

		// add setting for file sizes.
		$file_size_setting = $settings_obj->add_setting( 'eml_file_sizes' );
		$file_size_setting->set_section( $section_files );
		$file_size_setting->set_type( 'integer' );
		$file_size_setting->set_default( 0 );
		$file_size_setting->set_autoload( false );
		$file_size_setting->prevent_export( true );
		$file_size_setting->set_field(
			array(
				'type'        => 'Value',
				'title'       => __( 'External file sizes', 'external-files-in-media-library' ),
				'description' => __( 'The value is in bytes.', 'external-files-in-media-library' ),
			)
		);

		// create re-calc URL.
		$url = add_query_arg(
			array(
				'action' => 'eml_recalc_files',
				'nonce'  => wp_create_nonce( 'eml-recalc-files' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create import dialog.
		$dialog = array(
			'title'   => __( 'Reset file statistics', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the button below to reset the file statistics for external files.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Reset now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add re-calc button.
		$setting = $settings_obj->add_setting( 'eml_file_re_calc' );
		$setting->set_section( $section_files );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$setting->set_autoload( false );
		$setting->prevent_export( true );
		$field = new Button();
		$field->set_title( __( 'Reset file statistics', 'external-files-in-media-library' ) );
		$field->set_button_title( __( 'Reset now', 'external-files-in-media-library' ) );
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$setting->set_field( $field );
	}

	/**
	 * Return the actual file count.
	 *
	 * @return int
	 */
	private function get_file_count(): int {
		return absint( get_option( 'eml_file_count' ) );
	}

	/**
	 * Add 1 to the file count.
	 *
	 * @return void
	 */
	public function add_file_count(): void {
		update_option( 'eml_file_count', $this->get_file_count() + 1 );
	}

	/**
	 * Sub 1 from the file count.
	 *
	 * @return void
	 */
	public function sub_file_count(): void {
		update_option( 'eml_file_count', $this->get_file_count() - 1 );
	}

	/**
	 * Set file count to specific value.
	 *
	 * @param int $new_value The new value.
	 *
	 * @return void
	 */
	private function set_file_count( int $new_value ): void {
		update_option( 'eml_file_count', $new_value );
	}

	/**
	 * Return the actual file count.
	 *
	 * @return int
	 */
	private function get_file_sizes(): int {
		return absint( get_option( 'eml_file_sizes' ) );
	}

	/**
	 * Add the file size to the total.
	 *
	 * @param File $external_file_obj The external file as object.
	 *
	 * @return void
	 */
	public function add_file_sizes( File $external_file_obj ): void {
		update_option( 'eml_file_sizes', $this->get_file_sizes() + $external_file_obj->get_filesize() );
	}

	/**
	 * Subtract the file size from the total number.
	 *
	 * @param File $external_file_obj The external file as object.
	 *
	 * @return void
	 */
	public function sub_file_sizes( File $external_file_obj ): void {
		update_option( 'eml_file_sizes', $this->get_file_sizes() - $external_file_obj->get_filesize() );
	}

	/**
	 * Set file sizes to specific value.
	 *
	 * @param int $new_value The new value.
	 *
	 * @return void
	 */
	private function set_file_sizes( int $new_value ): void {
		update_option( 'eml_file_sizes', $new_value );
	}

	/**
	 * Recalc the file statistic by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function recalc_files_by_request(): void {
		// check nonce.
		check_admin_referer( 'eml-recalc-files', 'nonce' );

		// get all files.
		$files = Files::get_instance()->get_files();

		// get referer.
		$referer = wp_get_referer();

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// if no files could be loaded, set all settings to 0.
		if ( empty( $files ) ) {
			$this->set_file_count( 0 );
			$this->set_file_sizes( 0 );

			// trigger ok message.
			$transients_obj = Transients::get_instance();
			$transient_obj  = $transients_obj->add();
			$transient_obj->set_name( 'eml_recalc_ok' );
			$transient_obj->set_message( __( '<strong>The statistics has been reset.</strong> No files have been found.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( $referer );
			exit;
		}

		// loop through the list and count the values.
		$file_count = 0;
		$file_size  = 0;
		foreach ( $files as $file ) {
			++$file_count;
			$file_size += $file->get_filesize();
		}

		// save the new values.
		$this->set_file_count( $file_count );
		$this->set_file_sizes( $file_size );

		// trigger ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_recalc_ok' );
		$transient_obj->set_message( __( 'The statistics has been reset.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}
}
