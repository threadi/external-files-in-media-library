<?php
/**
 * This file controls the option to import/export the settings of external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Handler controls how to import external files with their original dates.
 */
class Import_Export extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'import_export';

	/**
	 * Instance of actual object.
	 *
	 * @var Import_Export|null
	 */
	private static ?Import_Export $instance = null;

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
	 * @return Import_Export
	 */
	public static function get_instance(): Import_Export {
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

		// bail if this is not enabled.
		if ( 1 !== absint( get_option( 'eml_import_export' ) ) ) {
			return;
		}

		// use our hooks.
		add_filter( 'efml_add_dialog', array( $this, 'add_option_in_form' ), 40, 2 );
		add_filter( 'efml_import_urls', array( $this, 'set_urls_for_import' ), 10, 2 );
		add_filter( 'efml_import_fields', array( $this, 'set_fields_for_import' ), 10, 3 );
		add_action( 'efml_show_file_info', array( $this, 'add_export' ) );

		// add actions.
		add_action( 'admin_action_efml_export_external_file', array( $this, 'export' ) );
	}

	/**
	 * Add settings for the intro.
	 * *
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get settings object.
		$settings_obj = Settings::get_instance();

		// get the main settings page.
		$main_settings_page = $settings_obj->get_page( 'eml_settings' );

		// bail if page could not be loaded.
		if ( ! $main_settings_page instanceof Page ) {
			return;
		}

		// get the advanced tab.
		$advanced_tab = $main_settings_page->get_tab( 'eml_advanced' );

		// bail if page could not be loaded.
		if ( ! $advanced_tab instanceof Tab ) {
			return;
		}

		// get the advanced section.
		$advanced_section = $advanced_tab->get_section( 'settings_section_advanced' );

		// bail if section could not be loaded.
		if ( ! $advanced_section instanceof Section ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_import_export' );
		$setting->set_section( $advanced_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable the import/export of files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled you will be able to import and export any external files as JSON-files. These JSON-files contain all necessary settings to import the specified file.', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Import by JSON', 'external-files-in-media-library' );
	}

	/**
	 * Add option to import a JSON instead of file URLs.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_in_form( array $dialog, array $settings ): array {
		// only add if it is enabled in settings.
		if ( ! in_array( $this->get_name(), ImportDialog::get_instance()->get_enabled_extensions(), true ) ) {
			return $dialog;
		}

		// do not show if "no_textarea" is set to true.
		if ( ! empty( $settings['no_textarea'] ) ) {
			return $dialog;
		}

		// add the entry with the option.
		$dialog['texts'][] = '<label for="import_json"><input type="checkbox" name="import_json" id="import_json" value="1" class="eml-use-for-import"> ' . __( 'Use JSON to import files.', 'external-files-in-media-library' ) . '</label>';

		// return the resulting fields.
		return $dialog;
	}

	/**
	 * Set URLs from JSON for import.
	 *
	 * @param array<int,string> $url_array The URLs to import.
	 * @param string            $urls The original string from request.
	 *
	 * @return array<int,string>
	 */
	public function set_urls_for_import( array $url_array, string $urls ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$use_json_import = isset( $_POST['import_json'] ) ? absint( $_POST['import_json'] ) : -1;

		// bail if not set from request.
		if ( -1 === $use_json_import ) {
			return $url_array;
		}

		// get the JSON from URLs as array.
		$file_data = json_decode( html_entity_decode( $urls ), true );

		// bail if JSON could not be decoded.
		if ( empty( $file_data ) ) {
			return $url_array;
		}

		// bail if URL is not given in JSON.
		if ( empty( $file_data['url'] ) ) {
			return $url_array;
		}

		// return the URL from JSON.
		return array( $file_data['url'] );
	}

	/**
	 * Set URLs from JSON for import.
	 *
	 * @param array<string,mixed> $fields The fields for import.
	 * @param array<int,string>   $url_array The list of URLs to import.
	 * @param string              $urls The original string from request.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function set_fields_for_import( array $fields, array $url_array, string $urls ): array {
		// check nonce.
		if ( isset( $_POST['efml-nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['efml-nonce'] ) ), 'efml-nonce' ) ) {
			exit;
		}

		// get value from request.
		$use_json_import = isset( $_POST['import_json'] ) ? absint( $_POST['import_json'] ) : - 1;

		// bail if not set from request.
		if ( - 1 === $use_json_import ) {
			return $fields;
		}

		// get the JSON from URLs as array.
		$file_data = json_decode( html_entity_decode( $urls ), true );

		// bail if JSON could not be decoded.
		if ( empty( $file_data ) ) {
			return $fields;
		}

		// bail if URL is not given in JSON.
		if ( empty( $file_data['url'] ) ) {
			return $fields;
		}

		// get the entry for the service from the JSON.
		if ( empty( $file_data['service'] ) ) {
			return $fields;
		}

		// get the service.
		$service_obj = Services::get_instance()->get_service_by_name( $file_data['service'] );

		// bail if service could not be found.
		if ( ! $service_obj instanceof Service_Base ) {
			return $fields;
		}

		// bail if no fields are set in JSON.
		if ( empty( $file_data['fields'] ) ) {
			return $fields;
		}

		// return the fields form JSON.
		return $file_data['fields'];
	}

	/**
	 * Add option to export the JSON-data of a single file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_export( File $external_file_obj ): void {
		// create the import URL.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_external_file',
				'nonce'  => wp_create_nonce( 'eml-export-external-file' ),
				'post'   => $external_file_obj->get_id(),
			),
			get_admin_url() . 'admin.php'
		);

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			/* translators: %1$s will be replaced by the file name. */
			'title'     => sprintf( __( 'Export %1$s as JSON', 'external-files-in-media-library' ), $external_file_obj->get_title() ),
			'texts'     => array(
				'<p>' . __( 'You will receive a JSON file that you can use to import this file into another media library.', 'external-files-in-media-library' ) . '</p>',
				'<p><strong>' . __( 'The file may also contain access data. Keep it safe.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, export the file', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		?>
		<li>
			<span id="eml_url_real_import"><span class="dashicons dashicons-database-export"></span> <a href="#" class="easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( Helper::get_json( $dialog ) ); ?>"><?php echo esc_html__( 'Export as JSON', 'external-files-in-media-library' ); ?></a></span>
		</li>
		<?php
	}

	/**
	 * Export a single external file as JSON.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export(): void {
		// check nonce.
		check_admin_referer( 'eml-export-external-file', 'nonce' );

		// get the post ID.
		$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if ID is not given.
		if ( 0 === $post_id ) {
			wp_safe_redirect( (string) wp_get_referer() );
		}

		// get the external file object of this file.
		$external_file_obj = Files::get_instance()->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			wp_safe_redirect( (string) wp_get_referer() );
		}

		// collect the file data for the JSON-file.
		$file_data = array(
			'url'     => $external_file_obj->get_url( true ),
			'type'    => $external_file_obj->get_file_type_obj()->get_name(),
			'service' => $external_file_obj->get_service_name(),
			'fields'  => $external_file_obj->get_fields(),
		);

		// create the filename for the JSON-download-file.
		$filename = gmdate( 'YmdHi' ) . '_' . get_option( 'blogname' ) . '_external_file_' . basename( get_the_title( $post_id ) ) . '.json';
		/**
		 * File the filename for JSON-download of single file.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param string $filename The generated filename.
		 */
		$filename = apply_filters( 'efml_export_file_filename', $filename );

		// set header for response as JSON-download.
		header( 'Content-type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . sanitize_file_name( $filename ) );
		echo wp_json_encode( $file_data );
		exit;
	}
}
