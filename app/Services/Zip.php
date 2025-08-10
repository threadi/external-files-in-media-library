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
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
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
		$this->title = __( 'Extract file(s) from a ZIP-File', 'external-files-in-media-library' );

		// add the directory listing.
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// use our own hooks.
		add_filter( 'eml_file_check_existence', array( $this, 'is_file_in_zip_file' ), 10, 2 );
		add_filter( 'eml_external_file_infos', array( $this, 'get_file' ), 10, 2 );
	}

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
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
	 * @return array<int|string,mixed>
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

		// bail if no scheme could be excluded.
		if ( empty( $parse_url['scheme'] ) ) {
			return array();
		}

		// set variables.
		$zip_file = $parse_url['path'];

		if ( ! file_exists( $zip_file ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'Given file does not exist!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return an empty list as we could not analyse the file.
			return array();
		}

		// open zip file using ZipArchive as readonly.
		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_file, ZipArchive::RDONLY );

		// bail if ZIP could not be opened.
		if ( ! $opened ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'Given file is not a valid ZIP-file!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return empty array.
			return array();
		}

		// get count of files.
		$file_count = $zip->count();

		// collect the list of files.
		$listing = array(
			'title' => basename( $zip_file ),
			'files' => array(),
			'dirs'  => array(),
		);

		// collect folders.
		$folders = array();

		// loop through the files and create the list.
		for ( $i = 0; $i < $file_count; $i++ ) {
			// get the name.
			$name = $zip->getNameIndex( $i );

			// bail if name could not be read.
			if ( ! is_string( $name ) ) {
				continue;
			}

			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $name );

			// get entry data.
			$file_stat = $zip->statIndex( $i );

			// bail if file_stat could not be read.
			if ( ! is_array( $file_stat ) ) {
				continue;
			}

			// collect the entry.
			$entry = array(
				'title' => basename( $file_stat['name'] ),
			);

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $file_stat['name'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $file_stat['name'];
				$entry['filesize']      = absint( $file_stat['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( ( $file_stat['mtime'] ) ) ) );
				$entry['preview']       = '';
			}

			// if array contains more than 1 entry this file is in a directory.
			if ( count( $parts ) > 1 ) {
				$the_keys = array_keys( $parts );
				$last_key = end( $the_keys );
				$last_dir = '';
				$dir_path = '';
				foreach ( $parts as $key => $dir ) {
					// bail if dir is empty.
					if ( empty( $dir ) ) {
						continue;
					}

					// bail for last entry (which is a file).
					if ( $key === $last_key ) {
						// add the file to the last iterated directory.
						$folders[ $last_dir ]['files'][] = $entry;
						continue;
					}

					// add the path.
					$dir_path .= DIRECTORY_SEPARATOR . $dir;

					// add the directory if it does not exist atm in the list.
					$index = $parse_url['scheme'] . $zip_file . '/' . trailingslashit( $dir_path );
					if ( ! isset( $folders[ $index ] ) ) {
						// add the directory to the list.
						$folders[ $index ] = array(
							'title' => $dir,
							'files' => array(),
							'dirs'  => array(),
						);
					}

					// add the directory if it does not exist atm in the main folder list.
					if ( ! empty( $last_dir ) && ! isset( $folders[ $last_dir ]['dirs'][ $index ] ) ) {
						// add the directory to the list.
						$folders[ $last_dir ]['dirs'][ $index ] = array(
							'title' => $dir,
							'files' => array(),
							'dirs'  => array(),
						);
					}

					// mark this dir as last dir for file path.
					$last_dir = $index;
				}
			} else {
				// simply add the entry to the list if no directory data exist.
				$listing['files'][] = $entry;
			}
		}

		// close the zip handle.
		$zip->close();

		// return the resulting list.
		return array_merge( array( 'completed' => true ), array( $parse_url['scheme'] . $zip_file => $listing ), $folders );
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "file://" + url.replace("file://", "") + "/" + file.file, "login": login, "password": password, "term": term } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
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

		// bail if file path does end with '.zip' (if it is the ZIP itself).
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
	 * @param array<string,string> $results The result.
	 * @param string               $file_path The path to the file (should contain and not end with '.zip').
	 *
	 * @return array<string,string>
	 */
	public function get_file( array $results, string $file_path ): array {
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return $results;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return $results;
		}

		// bail if file path does not contain '.zip'.
		if ( ! str_contains( $file_path, '.zip' ) ) {
			return $results;
		}

		// bail if "ZipArchive" is not available.
		if ( ! class_exists( 'ZipArchive' ) ) {
			return array();
		}

		// get the path to the ZIP from path string.
		$zip_file = substr( $file_path, 0, absint( strpos( $file_path, '.zip' ) ) ) . '.zip';
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

		// bail if no file could be found. This is not an error, but used for direct ZIP-upload.
		if ( empty( $file ) ) {
			return $results;
		}

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

		// bail if extension could not be read.
		if ( ! isset( $file_info['extension'] ) ) {
			return array();
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

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
			$error->add( 'efml_service_zip', sprintf( __( 'The given path <code>%1$s</code> does not end with ".zip"!', 'external-files-in-media-library' ), $directory ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to prevent further processing.

			return false;
		}

		// get the path to the ZIP from path string.
		$zip_file = substr( $directory, 0, absint( strpos( $directory, '.zip' ) ) ) . '.zip';
		$zip_file = str_replace( 'file://', '', $zip_file );

		// bail if file does not exist.
		if ( ! file_exists( $zip_file ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'The given path <code>%1$s</code> does not exist on your server.', 'external-files-in-media-library' ), $zip_file ) );

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

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return ! class_exists( 'ZipArchive' );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<span>' . __( 'PHP-Modul zip is missing!', 'external-files-in-media-library' ) . '</span>';
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
