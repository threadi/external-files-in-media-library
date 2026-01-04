<?php
/**
 * File to handle .tar.gz-files.
 *
 * These packages contain a list of files.
 * To use single files in a .gz, use @Gzip.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Zip;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use PharData;
use PharFileInfo;

/**
 * Object to handle .tar.gz-files.
 */
class TarGzip extends Zip_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var ?TarGzip
	 */
	private static ?TarGzip $instance = null;

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
	 * @return TarGzip
	 */
	public static function get_instance(): TarGzip {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the object of this file.
	 *
	 * @param string $file The file to check.
	 *
	 * @return PharData|false
	 */
	private function get_object( string $file ): PharData|false {
		// get the path to the ZIP from path string.
		$zip_file = substr( $file, 0, absint( strpos( $file, '.tar.gz' ) ) ) . '.tar.gz';

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

		// open the tar.gz file.
		$phar = new PharData( $tmp_zip_file );

		// extract to .tar and use its result.
		$result = $phar->decompress();

		// bail if file could not be extracted.
		if ( ! $result instanceof PharData ) { // @phpstan-ignore instanceof.alwaysTrue
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return the PharData object.
		return $result;
	}

	/**
	 * Mark if this handler can be used.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return class_exists( 'PharData' );
	}

	/**
	 * Return if the given file is compatible with this object.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_compatible( string $file ): bool {
		return str_contains( $file, '.tar.gz' );
	}

	/**
	 * Return whether the given file is in a zip.
	 *
	 * @return bool
	 */
	public function is_file_in_zip(): bool {
		return ! str_contains( $this->get_zip_file(), '.tar.gz' );
	}

	/**
	 * Return the directory listing of a given file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_directory_listing(): array {
		// get the extracted PharData object for this file.
		$zip_file = $this->get_object( $this->get_zip_file() );

		// bail if ZIP could not be opened.
		if ( ! $zip_file instanceof PharData ) {
			return array();
		}

		// collect the list of files.
		$listing = array(
			'title' => basename( $this->get_zip_file() ),
			'files' => array(),
			'dirs'  => array(),
		);

		// collect folders.
		$folders = array();

		// loop through the files.
		foreach ( $zip_file as $file ) {
			// bail if this is not PharFileInfo.
			if ( ! $file instanceof PharFileInfo ) {
				continue;
			}

			// get the name.
			$name = $file->getFilename();

			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $name );

			// collect the entry.
			$entry = array(
				'title' => basename( $name ),
			);

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $name );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $this->get_zip_file() . '/' . $name;
				$entry['filesize']      = absint( $file->getSize() );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $file->getMTime() ) ) );
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
	 * Return whether this file could be opened.
	 *
	 * @return bool
	 */
	public function can_file_be_opened(): bool {
		return $this->get_object( $this->get_zip_file() ) instanceof PharData;
	}

	/**
	 * Return info about single file in zip.
	 *
	 * @param string $file_to_extract The file.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_file_info_from_zip( string $file_to_extract ): array {
		// get the path to the file in the ZIP (+7 for .tar.gz and +1 for the starting "/") we want to extract.
		$file_to_extract = substr( $file_to_extract, strpos( $file_to_extract, '.tar.gz' ) + 8 );

		// get the zip object for the given file.
		$zip_file = $this->get_object( $file_to_extract );

		// bail if zip could not be opened.
		if ( ! $zip_file instanceof PharData ) {
			return array();
		}

		// get the requested file name.
		$file_name = substr( $this->get_zip_file(), absint( strpos( $this->get_zip_file(), '.tar.gz/' ) ) + 8 );

		// bail if file does not exist in zip.
		if ( ! $zip_file[ $file_name ] instanceof PharFileInfo ) { // @phpstan-ignore instanceof.alwaysTrue
			return array();
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $zip_file[ $file_name ]->getFilename() );

		// get the tmp file name.
		$extracted_file = wp_tempnam();

		// change the tmp file name for import.
		$extracted_file = str_replace( '.tmp', '', $extracted_file . '.' . $mime_type['ext'] );

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// save content in the tmp file.
		$wp_filesystem->put_contents( $extracted_file, $zip_file[ $file_name ]->getContent() );

		// collect the file data.
		return array(
			array(
				'title'         => $zip_file[ $file_name ]->getFilename(),
				'local'         => true,
				'last-modified' => time(),
				'tmp-file'      => $extracted_file,
				'mime-type'     => $mime_type['type'],
				'url'           => $this->get_zip_file(),
				'filesize'      => absint( filesize( $extracted_file ) ),
			),
		);
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
		$zip_file = $this->get_object( $this->get_zip_file() );

		// bail if zip could not be opened.
		if ( ! $zip_file instanceof PharData ) {
			return array();
		}

		// if path does not end with ".tar.gz/", it is a single file request.
		if ( ! str_ends_with( $zip_file, '.tar.gz/' ) ) {
			return $this->get_file_info_from_zip( $zip_file );
		}

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// collect the files.
		$results = array();

		// get the list of files in this zip and return their data.
		foreach ( $zip_file as $file ) {
			// bail if this is not PharFileInfo.
			if ( ! $file instanceof PharFileInfo ) {
				continue;
			}

			// get content type of this file.
			$mime_type = wp_check_filetype( $file->getFilename() );

			// get the tmp file name.
			$tmp_file = wp_tempnam();

			// change the tmp file name for import.
			$tmp_file = str_replace( '.tmp', '', $tmp_file . '.' . $mime_type['ext'] );

			// save content in the tmp file.
			$wp_filesystem->put_contents( $tmp_file, $file->getContent() );

			// collect the entry.
			$entry = array(
				'title'         => $file->getFilename(),
				'local'         => true,
				'last-modified' => absint( $file->getMTime() ),
				'tmp-file'      => $tmp_file,
				'mime-type'     => $mime_type['type'],
				'url'           => $this->get_zip_file(),
				'filesize'      => absint( $file->getSize() ),
			);

			// add the entry to the list.
			$results[] = $entry;
		}

		// return the file list.
		return $results;
	}
}
