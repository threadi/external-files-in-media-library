<?php
/**
 * File to handle .zip-files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Zip;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ZipArchive;

/**
 * Object to handle zip files.
 */
class Zip extends Zip_Base {
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
	 * Return if the given file is compatible with this object.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_compatible( string $file ): bool {
		return str_contains( $file, '.zip' );
	}

	/**
	 * Return the object of this file.
	 *
	 * @param string $file The file to check.
	 *
	 * @return ZipArchive|false
	 */
	private function get_object( string $file ): ZipArchive|false {
		// get the path to the ZIP from path string.
		$zip_file = substr( $file, 0, absint( strpos( $file, '.zip' ) ) ) . '.zip';

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $zip_file );

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return false;
		}

		// get the local tmp file of this zip.
		$tmp_zip_file = $protocol_handler_obj->get_temp_file( $zip_file, $wp_filesystem );

		// bail if no temp zip could be returned.
		if ( ! is_string( $tmp_zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be saved as temp file.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// do nothing more.
			return false;
		}

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $tmp_zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file to use for extracting a file does not exist.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// get the zip object.
		$zip    = new ZipArchive();
		$opened = $zip->open( $tmp_zip_file, ZipArchive::RDONLY );

		// bail if file could not be opened.
		if ( ! $opened ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return the opened zip as object.
		return $zip;
	}

	/**
	 * Return the directory listing of a given file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_directory_listing(): array {
		// open zip file using ZipArchive as readonly.
		$zip = $this->get_object( $this->get_zip_file() );

		// bail if ZIP could not be opened.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// get count of files.
		$file_count = $zip->count();

		// collect the list of files.
		$listing = array(
			'title' => basename( $this->get_zip_file() ),
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
				$entry['file']          = $this->get_zip_file() . '/' . $file_stat['name'];
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
					$index = $this->get_zip_file() . '/' . trailingslashit( $dir_path );
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

		if ( ! empty( $folders ) ) {
			$listing['dirs'][ array_key_first( $folders ) ] = array(
				'title'   => array_key_first( $folders ),
				'folders' => array(),
				'dirs'    => array(),
			);
		}

		// return the resulting list.
		return array_merge( array( 'completed' => true ), array( $this->get_zip_file() => $listing ), $folders );
	}

	/**
	 * Return info about single file in zip.
	 *
	 * @param string $file_to_extract The file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_file_info_from_zip( string $file_to_extract ): array {
		// get the path to the file in the ZIP (+4 for .zip and +1 for the starting "/") we want to extract.
		$file_to_extract = substr( $file_to_extract, strpos( $file_to_extract, '.zip' ) + 5 );

		// get the zip object for the given file.
		$zip = $this->get_object( $this->get_zip_file() );

		// bail if zip could not be opened.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// get the fields JSON from request.
		$fields_json = filter_input( INPUT_POST, 'fields', FILTER_UNSAFE_RAW );

		// decode the fields-JSON to an array.
		$fields = array();
		if ( is_string( $fields_json ) ) {
			$fields = json_decode( $fields_json, true );
		}

		// if password is set, set if on the zip object.
		if ( is_array( $fields ) && ! empty( $fields['zip_password']['value'] ) ) {
			$zip->setPassword( $fields['zip_password']['value'] );
		}

		// get content of the file to extract.
		$file_content = $zip->getFromName( $file_to_extract );

		// bail if no file data could be loaded.
		if ( ! $file_content ) {
			// log event.
			Log::get_instance()->create( __( 'No data of the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $this->get_zip_file(), 'error' );

			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'No data of the file to extract from ZIP could not be loaded. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_zip_file() ) ) );
			$error_obj->set_url( $this->get_zip_file() );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get entry data.
		$file_stat = $zip->statName( $file_to_extract );

		// bail if no stats could be loaded.
		if ( ! is_array( $file_stat ) ) {
			// log event.
			Log::get_instance()->create( __( 'No stats for the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $this->get_zip_file(), 'error' );

			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'No stats for the file to extract from ZIP could not be loaded. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_zip_file() ) ) );
			$error_obj->set_url( $file_to_extract );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $file_stat['name'] );

		// collect the response array.
		$results = array(
			'title'     => basename( $file_stat['name'] ),
			'local'     => true,
			'url'       => $this->get_zip_file(),
			'mime-type' => $mime_type['type'],
		);

		// get file date from zip.
		$results['last-modified'] = absint( $file_stat['mtime'] );

		// get the file size.
		$results['filesize'] = absint( $file_stat['size'] );

		// get tmp file name.
		$tmp_file_name = wp_tempnam();

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// and save the file there.
		try {
			$wp_filesystem->put_contents( $tmp_file, $file_content );
			$wp_filesystem->delete( $tmp_file_name );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_zip_file() ) ) );
			$error_obj->set_url( $file_to_extract );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $file_to_extract, 'error' );

			// do nothing more.
			return array();
		}

		// add the path to the tmp file to the file infos.
		$results['tmp-file'] = $tmp_file;

		// return resulting file infos.
		return $results; // @phpstan-ignore return.type
	}

	/**
	 * Return list of files in zip to import in media library.
	 *
	 *  The file must be extracted in tmp directory to import them as usual URLs.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_files_from_zip(): array {
		// get the zip object.
		$zip = $this->get_object( $this->get_zip_file() );

		// bail if zip could not be loaded.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// if given file is a single file in a ZIP, get its file infos.
		if ( ! str_ends_with( $this->get_zip_file(), '.zip/' ) ) {
			return array( $this->get_file_info_from_zip( $this->get_zip_file() ) );
		}

		// get count of files.
		$file_count = $zip->count();

		// set counter for files which has been loaded from ZIP.
		$loaded_files = 0;

		// create the result array.
		$results = array();

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// loop through the files and create the list.
		for ( $i = 0; $i < $file_count; $i++ ) {
			// get the name.
			$name = $zip->getNameIndex( $i );

			// bail if name could not be read.
			if ( ! is_string( $name ) ) {
				continue;
			}

			// create a pseudo URL for this file.
			$url = trailingslashit( $this->get_zip_file() ) . $name;

			// get entry data.
			$file_stat = $zip->statIndex( $i );

			// bail if file_stat could not be read.
			if ( ! is_array( $file_stat ) ) {
				continue;
			}

			// bail if this an AJAX-request and the file already exist in media library.
			if ( wp_doing_ajax() && Files::get_instance()->get_file_by_title( basename( $name ) ) ) {
				continue;
			}

			// bail if limit for loaded files has been reached and this is an AJAX-request.
			if ( wp_doing_ajax() && $loaded_files > absint( get_option( 'eml_zip_import_limit', 10 ) ) ) {
				// set marker to load more.
				$results['load_more'] = true;
				continue;
			}

			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $name );

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $file_stat['name'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// get tmp file name.
				$tmp_file_name = wp_tempnam();

				// set the file as tmp-file for import.
				$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

				// get info about the file to extract.
				$file_content = $zip->getFromName( $name );

				// bail if no file data could be loaded.
				if ( ! $file_content ) {
					// log event.
					Log::get_instance()->create( __( 'No data of the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $this->get_zip_file(), 'error' );

					// return empty array as we can not get infos about a file which does not exist.
					continue;
				}

				// and save the file there.
				try {
					$wp_filesystem->put_contents( $tmp_file, $file_content );
					$wp_filesystem->delete( $tmp_file_name );
				} catch ( Error $e ) {
					// create the error entry.
					$error_obj = new Url_Result();
					/* translators: %1$s will be replaced by a URL. */
					$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_zip_file() ) ) );
					$error_obj->set_url( $this->get_zip_file() );
					$error_obj->set_error( true );

					// add the error object to the list of errors.
					Results::get_instance()->add( $error_obj );

					// add log entry.
					Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_zip_file(), 'error' );

					// do nothing more.
					continue;
				}

				// collect the entry.
				$entry = array(
					'title'         => basename( $file_stat['name'] ),
					'local'         => true,
					'last-modified' => absint( $file_stat['mtime'] ),
					'tmp-file'      => $tmp_file,
					'mime-type'     => $mime_type['type'],
					'url'           => $url,
					'filesize'      => absint( $file_stat['size'] ),
				);

				// update the counter.
				++$loaded_files;

				// add the entry to the list.
				$results[] = $entry;
			}
		}

		// close the zip handle.
		$zip->close();

		// return the resulting list of files.
		return $results;
	}

	/**
	 * Mark if this handler can be used.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Return whether this file could be opened.
	 *
	 * @return bool
	 */
	public function can_file_be_opened(): bool {
		return $this->get_object( $this->get_zip_file() ) instanceof ZipArchive;
	}

	/**
	 * Return whether the given file is in a zip.
	 *
	 * @return bool
	 */
	public function is_file_in_zip(): bool {
		return ! str_ends_with( $this->get_zip_file(), '.zip' );
	}
}
