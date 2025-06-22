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
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use function cli\err;

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
		add_filter( 'eml_add_dialog', array( $this, 'add_option_for_local_import' ), 10, 2 );
		add_filter( 'efml_service_local_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 2 );
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
	 * Add option to import from local directory.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function add_option_for_local_import( array $dialog, array $settings ): array {
		// bail if "no_services" is set in settings.
		if ( isset( $settings['no_services'] ) ) {
			return $dialog;
		}

		// add the entry.
		$dialog['texts'][] = '<details><summary>' . __( 'Or add from local server directory', 'external-files-in-media-library' ) . '</summary><div><label for="eml_local"><a href="' . Directory_Listing::get_instance()->get_view_directory_url( \easyDirectoryListingForWordPress\Listings\Local::get_instance() ) . '" class="button button-secondary">' . esc_html__( 'Add from local server directory', 'external-files-in-media-library' ) . '</a></label></div></details>';

		// return resulting dialog.
		return $dialog;
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
				'action' => 'efml_save_as_directory( "local", actualDirectoryPath + "/", "", "", "" );',
				'label'  => __( 'Save this directory as directory archive', 'external-files-in-media-library' ),
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
}
