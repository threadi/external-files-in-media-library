<?php
/**
 * File to handle the support of files from ZIP as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\lib\ZipArchiveBrowser;
use WP_Error;
use ZipArchive;

/**
 * Object to handle support of files from ZIP as directory listing.
 */
class Zip extends Directory_Listing_Base implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'zip';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'ZIP';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Zip
	 */
	private static ?Zip $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {    }

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {    }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Zip
	 */
	public static function get_instance(): Zip {
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
		// bail if "ZipArchive" is not available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			return;
		}

		$this->title = __( 'Choose file from a ZIP-File', 'external-files-in-media-library' );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_filter( 'eml_file_check_existence', array( $this, 'is_file_in_zip_file' ), 10, 2 );
		add_filter( 'eml_external_file_infos', array( $this, 'get_file_from_zip' ), 10, 2 );
	}

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array $directory_listing_objects List of directory listing objects.
	 *
	 * @return array
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		$directory_listing_objects[] = $this;
		return $directory_listing_objects;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array
	 */
	public function get_directory_listing( string $directory ): array {
		// bail if "ZipArchive" is not available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'PHP-Modul zip is missing! Please contact your hosting support about this problem.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return empty array.
			return array();
		}

		// prepend the string with file:// if it does not start with it.
		if ( ! str_starts_with( $directory, 'file://' ) ) {
			$directory = 'file://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if handler is not File.
		if ( ! $protocol_handler_obj instanceof Protocols\File ) {
			return array();
		}

		// get the staring directory.
		$parse_url = wp_parse_url( $directory );

		// bail if given string is not a valid URL.
		if ( empty( $parse_url ) ) {
			return array();
		}

		// bail if no path could be excluded.
		if ( empty( $parse_url['path'] ) ) {
			return array();
		}

		// return the resulting list.
		return ZipArchiveBrowser::get_contents( $this, $parse_url['path'] );
	}

	/**
	 * Return the actions.
	 *
	 * @return array
	 */
	public function get_actions(): array {
		return array(
			array(
				'action' => 'efml_import_file( url + file.file, login, password );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Check if given path is a file in a ZIP-file.
	 *
	 * @param bool   $return_value The result (true, if file existence check should be run).
	 * @param string $file_path The path to the file (should contain and not end with '.zip').
	 *
	 * @return bool
	 */
	public function is_file_in_zip_file( bool $return_value, string $file_path ): bool {
		// bail if file path does not contain '.zip'.
		if ( ! str_contains( $file_path, '.zip' ) ) {
			return $return_value;
		}

		// bail if file path does end with '.zip'.
		if ( str_ends_with( $file_path, '.zip' ) ) {
			return $return_value;
		}

		// return false to prevent file check as this seems to be a file in a ZIP-file.
		return false;
	}

	/**
	 * Return info about requested file from ZIP.
	 *
	 * We save the unzipped file in tmp directory for import.
	 *
	 * @param array  $results The result.
	 * @param string $file_path The path to the file (should contain and not end with '.zip').
	 *
	 * @return array
	 */
	public function get_file_from_zip( array $results, string $file_path ): array {
		// bail if file path does not contain '.zip'.
		if ( ! str_contains( $file_path, '.zip' ) ) {
			return $results;
		}

		// bail if file path does end with '.zip'.
		if ( ! str_ends_with( $file_path, '.zip' ) ) {
			return $results;
		}

		// bail if "ZipArchive" is not available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array();
		}

		// get the path to the ZIP from path string.
		$zip_file = substr( $file_path, 0, strpos( $file_path, '.zip' ) ) . '.zip';
		$zip_file = str_replace( 'file://', '', $zip_file );

		// bail if file does not exist.
		if ( ! file_exists( $zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file to use for extracting a file does not exist.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get the path to the file in the ZIP (+4 for .zip and +1 for the starting "/").
		$file = substr( $file_path, strpos( $file_path, '.zip' ) + 5 );

		// get the zip object.
		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_file, ZipArchive::RDONLY );

		// bail if file could not be opened.
		if ( ! $opened ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get info about the file to extract.
		$file_content = $zip->getFromName( $file );

		// bail if no file data could be loaded.
		if ( ! $file_content ) {
			// log event.
			Log::get_instance()->create( __( 'No data of the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get file infos.
		$file_info = pathinfo( $file );

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', wp_tempnam() . '.' . $file_info['extension'] );

		// and save the file there.
		$wp_filesystem->put_contents( $tmp_file, $file_content );

		// add the path to the tmp file to the file infos.
		$results['tmp-file'] = $tmp_file;

		// return resulting file infos.
		return $results;
	}

	/**
	 * Check if given directory is a valid ZIP-file.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if directory is not set.
		if ( empty( $directory ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'No ZIP-file given!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.
			return false;
		}

		// bail if file path does not contain '.zip'.
		if ( ! str_contains( $directory, '.zip' ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'The given path does not end with ".zip"!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.
			return false;
		}

		// bail if file path does end with '.zip'.
		if ( ! str_ends_with( $directory, '.zip' ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'The given path %1$s does not end with ".zip"!', 'external-files-in-media-library' ), $directory ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.

			return false;
		}

		// bail if "ZipArchive" is not available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'PHP-Modul zip is missing! Please contact your hosting support about this problem.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.
			return false;
		}

		// get the path to the ZIP from path string.
		$zip_file = substr( $directory, 0, strpos( $directory, '.zip' ) ) . '.zip';
		$zip_file = str_replace( 'file://', '', $zip_file );

		// bail if file does not exist.
		if ( ! file_exists( $zip_file ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'The given path %1$s does not exist on your server.', 'external-files-in-media-library' ), $zip_file ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.
			return false;
		}

		// get the zip object.
		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_file, ZipArchive::RDONLY );

		// bail if file could not be opened.
		if ( ! $opened ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file ) );

			// add it to the list.
			$this->add_error( $error );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return true as given directory is a valid ZIP-file.
		return true;
	}
}
