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

/**
 * Handler controls how to import external files for real in media library without external connection.
 */
class Dates extends Extension_Base {
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
		add_filter( 'eml_import_fields', array( $this, 'add_date_option_in_form' ) );
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
		$advanced_tab_advanced = $settings_obj->get_section( 'settings_section_advanced' );

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
				'title'       => __( 'Use external file dates', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled all external files will be saved in media library with the date set by the external location. If the external location does not set any date the actual date will be used.', 'external-files-in-media-library' ),
			)
		);
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
	}

	/**
	 * Add file date to post array to set the date of the external file.
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
		if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$use_date = isset( $_POST['additional_fields']['use_dates'] ) ? absint( $_POST['additional_fields']['use_dates'] ) : -1;

		// bail if not set from request and global setting not enabled.
		if ( -1 === $use_date && 1 !== absint( get_option( 'eml_use_file_dates' ) ) ) {
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
	 * Add a checkbox to mark the files to add them to queue.
	 *
	 * @param array<int,string> $fields List of fields in form.
	 *
	 * @return array<int,string>
	 */
	public function add_date_option_in_form( array $fields ): array {
		// add the field to enable queue-upload.
		$fields[] = '<label for="use_dates"><input type="checkbox" name="use_dates" id="use_dates" value="1" class="eml-use-for-import"' . ( 1 === absint( get_option( 'eml_use_file_dates' ) ) ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Use external file dates.', 'external-files-in-media-library' ) . ' <a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_advanced' ) ) . '" target="_blank"><span class="dashicons dashicons-admin-generic"></span></a></label>';

		// return the resulting fields.
		return $fields;
	}
}
