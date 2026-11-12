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
use Exception;
use easyDirectoryListingForWordPress\Init;
use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Local\Export;

/**
 * Object to handle local import support.
 */
class Local extends Service_Base implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'local';

	/**
	 * Marker whether values of fields could be defined via constants (true) or not (false).
	 *
	 * Disabled as this service does not use any credentials.
	 *
	 * @var bool
	 */
	protected bool $fields_via_constants = false;

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_local';

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

		// add settings.
		add_action( 'init', array( $this, 'init_local' ), 20 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_service_local_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 2 );
		add_filter( 'efml_service_local_hide_file', array( $this, 'hide_generic_directories' ), 10, 3 );
		add_filter( 'efml_directory_listing_local_directory_loading', array( $this, 'add_upload_dirs' ), 10, 3 );
		add_filter( 'efml_export_object', array( $this, 'change_export_object' ) );
	}

	/**
	 * Add settings for local support.
	 *
	 * @return void
	 */
	public function init_local(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_local' ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

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

		// add a new tab for settings.
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 90 );
		$tab->set_title( __( 'Local', 'external-files-in-media-library' ) );

		// add a section for file statistics.
		$section = $tab->add_section( 'section_local_main', 10 );
		$section->set_title( __( 'Settings for access to local hosting', 'external-files-in-media-library' ) );
		$section->set_callback( array( $this, 'show_hint_for_permissions' ) );

		// add setting to enable the uploads-loading.
		$setting = $settings_obj->add_setting( 'eml_local_load_upload_dir' );
		$setting->set_section( $section );
		$setting->set_default( 0 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Load WordPress upload directory', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled the local service will also load the WordPress upload directory. This might result in long loading times.', 'external-files-in-media-library' ) );
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
		if ( defined( 'EFML_LOCAL_UPDATED' ) ) {
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
		define( 'EFML_LOCAL_UPDATED', time() );

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
				'action' => 'efml_get_import_dialog( { "service": "local", "urls": file.file, "fields": config.fields, "term": term } );',
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
			array(
				'action' => 'efml_connection_test( "local", {}, config.term, actualDirectoryPath );',
				'label'  => __( 'Run connection test', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return whether this service supports the complete connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_connection_test(): bool {
		return true;
	}

	/**
	 * Return the directory of the connection test as local path.
	 *
	 * @return string
	 * @throws Exception If no directory is set.
	 */
	private function get_connection_test_path(): string {
		// get the directory.
		$directory = Helper::get_local_path_from_url( $this->get_connection_test_directory() );

		// bail if no directory is set.
		if ( '' === $directory ) {
			throw new Exception( esc_html__( 'Open the directory to test first.', 'external-files-in-media-library' ) );
		}

		// return the directory.
		return trailingslashit( $directory );
	}

	/**
	 * Check the directory for the connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 * @throws Exception If the directory could not be used.
	 */
	protected function test_connection_list(): string {
		// get the directory.
		$directory = $this->get_connection_test_path();

		// bail if the directory is not located in the allowed base directory for local files.
		if ( ! Helper::is_local_path_allowed( $directory ) ) {
			/* translators: %1$s will be replaced by a path. */
			throw new Exception( esc_html( sprintf( __( 'The directory %1$s is not located in the allowed directory for local files.', 'external-files-in-media-library' ), $directory ) ) );
		}

		// get the WP filesystem object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if the directory does not exist.
		if ( ! $wp_filesystem->is_dir( $directory ) ) {
			/* translators: %1$s will be replaced by a path. */
			throw new Exception( esc_html( sprintf( __( 'The directory %1$s does not exist.', 'external-files-in-media-library' ), $directory ) ) );
		}

		// bail if the directory is not writable.
		if ( ! $wp_filesystem->is_writable( $directory ) ) {
			/* translators: %1$s will be replaced by a path. */
			throw new Exception( esc_html( sprintf( __( 'The directory %1$s is not writable for WordPress. Check its permissions.', 'external-files-in-media-library' ), $directory ) ) );
		}

		// return the message.
		return __( 'The directory could be read.', 'external-files-in-media-library' );
	}

	/**
	 * Copy the test file into the directory.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $local_file The absolute path to the local test file.
	 * @param string $file_name  The name of the test file.
	 *
	 * @return string
	 * @throws Exception If the file could not be copied.
	 */
	protected function test_connection_upload( string $local_file, string $file_name ): string {
		// get the target.
		$target = $this->get_connection_test_path() . $file_name;

		// copy the file.
		if ( ! Helper::get_wp_filesystem()->copy( $local_file, $target ) ) {
			/* translators: %1$s will be replaced by a path. */
			throw new Exception( esc_html( sprintf( __( 'The test file could not be saved as %1$s.', 'external-files-in-media-library' ), $target ) ) );
		}

		// return the URL like the export does it.
		return 'file://' . $target;
	}

	/**
	 * Delete the test file from the directory.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url       The URL of the uploaded file.
	 * @param string $file_name The name of the test file.
	 *
	 * @return void
	 * @throws Exception If the file could not be deleted.
	 */
	protected function test_connection_delete( string $url, string $file_name ): void {
		if ( ! Export::get_instance()->delete_exported_file( $url, array(), 0 ) ) {
			throw new Exception( esc_html__( 'The test file could not be deleted.', 'external-files-in-media-library' ) );
		}
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool   $result The result - should be true to prevent the usage.
	 * @param string $path The file path.
	 *
	 * @return bool Return true to prevent.
	 */
	public function prevent_not_allowed_files( bool $result, string $path ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// remove the scheme.
		$path = Helper::get_local_path_from_url( $path );

		// bail for directories.
		if ( is_dir( $path ) ) {
			return $result;
		}

		// get content-type of this file.
		$mime_type = wp_check_filetype( $path );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Hide generic directories from local listings.
	 *
	 * @param bool   $result The result - should be true to prevent the usage.
	 * @param string $path   The file path.
	 * @param string $directory The used directory.
	 *
	 * @return bool Return true to prevent.
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function hide_generic_directories( bool $result, string $path, string $directory ): bool {
		// bail if this is not a directory.
		if ( ! is_dir( $path ) ) {
			return $result;
		}

		// list of directories to ignore.
		$ignore_list = array(
			'node_modules',
			'vendor',
			'tmp',
		);

		// return true to hide this directory if it is in the ignore list.
		if ( in_array( basename( $path ), $ignore_list, true ) ) {
			return true;
		}

		// return the given result for all other directories.
		return $result;
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
		set_transient( Init::get_instance()->get_prefix() . '_' . get_current_user_id() . '_' . md5( $directory ) . '_tree', $directory_list, DAY_IN_SECONDS );

		// return true to force the loading of the upload dirs.
		return true;
	}

	/**
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Show hint where to edit permissions to use this service.
	 *
	 * @return void
	 */
	public function show_hint_for_permissions(): void {
		/* translators: %1$s will be replaced by a URL. */
		echo wp_kses_post( sprintf( __( 'Set permission who could use this service <a href="%1$s">here</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_permissions' ) ) );
	}

	/**
	 * Change the export object if the local object is called.
	 *
	 * @param object|false $service_object The given object.
	 *
	 * @return object|false
	 */
	public function change_export_object( object|false $service_object ): object|false {
		// bail if this is not the local object from directory listing.
		if ( ! $service_object instanceof \easyDirectoryListingForWordPress\Listings\Local ) {
			return $service_object;
		}

		// return the actual object.
		return $this;
	}

	/**
	 * Return the export object for this service.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		return Export::get_instance();
	}
}
