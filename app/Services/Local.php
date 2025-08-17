<?php
/**
 * File to handle the local support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle local import support.
 */
class Local implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'local';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_sub_tab = 'eml_local';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local
	 */
	private static ?Local $instance = null;

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
	 * @return Local
	 */
	public static function get_instance(): Local {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_import' ) ) {
			return;
		}

		// add settings.
		add_action( 'init', array( $this, 'init_local' ), 20 );

		// use our own hooks.
		add_filter( 'efml_service_local_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 2 );
		add_filter( 'efml_service_local_directory_loading', array( $this, 'add_upload_dirs' ), 10, 3 );
	}

	/**
	 * Add settings for local support.
	 *
	 * @return void
	 */
	public function init_local(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add new tab for settings.
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 90 );
		$tab->set_title( __( 'Local', 'external-files-in-media-library' ) );

		// add section for file statistics.
		$section = $tab->add_section( 'section_local_main', 10 );
		$section->set_title( __( 'Settings for local', 'external-files-in-media-library' ) );

		// add setting to enable the uploads-loading.
		$setting = $settings_obj->add_setting( 'eml_local_load_upload_dir' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Load upload-directory', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled the local service will also load the upload directory. This might result in long loading times.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
	}

	/**
	 * Update the local service with our custom action for each file.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 * @noinspection PhpArrayAccessCanBeReplacedWithForeachValueInspection
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		// bail if this has already been run.
		if ( defined( 'EML_LOCAL_UPDATED' ) ) {
			return $directory_listing_objects;
		}

		foreach ( $directory_listing_objects as $i => $obj ) {
			if ( ! $obj instanceof \easyDirectoryListingForWordPress\Listings\Local ) {
				continue;
			}

			// set actions for the local object.
			$directory_listing_objects[ $i ]->set_actions( $this->get_actions() );
			$directory_listing_objects[ $i ]->add_global_action( $this->get_global_actions() );
		}

		// mark as updated.
		define( 'EML_LOCAL_UPDATED', time() );

		// return resulting list of objects.
		return $directory_listing_objects;
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "local", "urls": file.file, "term": term } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_global_actions(): array {
		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "local", "urls": actualDirectoryPath, "term": config.term } );',
				'label'  => __( 'Import this directory now', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'efml_save_as_directory( "local", actualDirectoryPath, "", "", "" );',
				'label'  => __( 'Save this directory as your external source', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool   $result The result - should be true to prevent the usage.
	 * @param string $path The file path.
	 *
	 * @return bool
	 */
	public function prevent_not_allowed_files( bool $result, string $path ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// remove the scheme.
		$path = str_replace( 'file://', '', $path );

		// bail for directories.
		if ( is_dir( $path ) ) {
			return $result;
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $path );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Add the uploads directory to the list of all directories to show.
	 *
	 * @param bool                $directory_loading Whether to load the directory.
	 * @param array<string,mixed> $directory_list The list of directories to load.
	 * @param string              $directory The actual requested directory.
	 *
	 * @return bool
	 */
	public function add_upload_dirs( bool $directory_loading, array $directory_list, string $directory ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_local_load_upload_dir' ) ) ) {
			return $directory_loading;
		}

		// bail if directory loading is true.
		if ( false !== $directory_loading ) {
			return true;
		}

		// get the upload dir.
		$upload_dir = wp_get_upload_dir();

		// bail if uploads-directory are in the list.
		if ( isset( $directory_list[ $upload_dir['basedir'] ] ) ) {
			return false;
		}

		// add wp-content-dir only with the upload dir as child.
		$directory_list[ trailingslashit( WP_CONTENT_DIR ) ] = array(
			'title' => basename( WP_CONTENT_DIR ),
			'files' => array(),
			'dirs'  => array(
				trailingslashit( $upload_dir['basedir'] ) => array(
					'title' => basename( $upload_dir['basedir'] ),
					'files' => array(),
					'dirs'  => array(),
				),
			),
		);

		// update the setting.
		set_transient( \easyDirectoryListingForWordPress\Init::get_instance()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree', $directory_list, DAY_IN_SECONDS );

		// return true to force the loading of the upload dirs.
		return true;
	}

	/**
	 * Return the settings slug.
	 *
	 * @return string
	 */
	private function get_settings_tab_slug(): string {
		return $this->settings_tab;
	}

	/**
	 * Return the settings sub tab slug.
	 *
	 * @return string
	 */
	private function get_settings_subtab_slug(): string {
		return $this->settings_sub_tab;
	}
}
