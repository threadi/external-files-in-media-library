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

/**
 * Handler controls how to import external files for real in media library without external connection.
 */
class Real_Import extends Extension_Base {
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
		add_filter( 'eml_import_fields', array( $this, 'add_option_in_form' ) );
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
		if( ! $advanced_tab_advanced ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_directory_listing_real_import' );
		$setting->set_section( $advanced_tab_advanced );
		$setting->set_field(
			array(
				'type'        => 'Checkbox',
				'title'       => __( 'Really import each file', 'external-files-in-media-library' ),
				'description' => __( 'If this option is enabled each external URL will be imported as real file in your media library. There will be no "external files".', 'external-files-in-media-library' ),
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
		// bail if setting is disabled to use the generated value.
		if( 1 !== absint( get_option( 'eml_directory_listing_real_import' ) ) ) {
			return $result;
		}

		// return true to force local saving of this file.
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
		// get value from request.
		$real_import = isset( $_POST['additional_fields']['real_import'] ) ? absint( $_POST['additional_fields']['real_import'] ) : -1;

		// bail if either setting is disabled to use the generated value.
		if( 1 !== $real_import && 1 !== absint( get_option( 'eml_directory_listing_real_import' ) ) ) {
			return $post_array;
		}

		// add the title.
		$post_array['post_title'] = $file_data['title'];

		// return the resulting array.
		return $post_array;
	}

	/**
	 * Save file local if global setting is enabled.
	 *
	 * @return bool
	 */
	public function save_file_local(): bool {
		// get value from request.
		$real_import = isset( $_POST['additional_fields']['real_import'] ) ? absint( $_POST['additional_fields']['real_import'] ) : -1;

		// return whether to import this file as real file and not external.
		return 1 === $real_import || ( -1 === $real_import && 1 === absint( get_option( 'eml_directory_listing_real_import' ) ) );
	}

	/**
	 * Add a checkbox to mark the files to add them real and not as external files.
	 *
	 * @param array<int,string> $fields List of fields in form.
	 *
	 * @return array<int,string>
	 */
	public function add_option_in_form( array $fields ): array {
		// add the field to enable queue-upload.
		$fields[] = '<label for="real_import"><input type="checkbox" name="real_import" id="real_import" value="1" class="eml-use-for-import"' . ( 1 === absint( get_option( 'eml_directory_listing_real_import' ) ) ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Really import each file. Files are not imported as external files.', 'external-files-in-media-library' ) . ' <a href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_advanced' ) ) . '" target="_blank"><span class="dashicons dashicons-admin-generic"></span></a></label>';

		// return the resulting fields.
		return $fields;
	}
}
