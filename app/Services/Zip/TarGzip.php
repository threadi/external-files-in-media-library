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
use Exception;
use PharData;
use PharFileInfo;
use RecursiveIteratorIterator;

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

			// return empty array as we cannot get infos about a file, which does not exist.
			return false;
		}

		// open the tar.gz file and extract it to .tar to use its result.
		try {
			$phar   = new PharData( $tmp_zip_file );
			$result = $phar->decompress();
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $zip_file, 'error' );

			// do nothing more.
			return false;
		}

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
		return str_contains( $this->get_path_for_compatibility_check( $file ), '.tar.gz' );
	}

	/**
	 * Return whether the given file is in a zip.
	 *
	 * @return bool
	 */
	public function is_file_in_zip(): bool {
		return ! str_ends_with( $this->get_zip_file(), '.tar.gz' );
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

		// collect the entries of the archive, also from its subdirectories.
		$entries = array();
		foreach ( new RecursiveIteratorIterator( $zip_file ) as $file ) {
			// bail if this is not PharFileInfo.
			if ( ! $file instanceof PharFileInfo ) {
				continue;
			}

			// add the entry.
			$entries[] = array(
				'name'  => $this->get_path_in_archive( $zip_file, $file ),
				'size'  => absint( $file->getSize() ),
				'mtime' => absint( $file->getMTime() ),
			);
		}

		// return the resulting list.
		return $this->get_listing_of_entries( $entries );
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
		// bail if the given path does not contain a file in an archive.
		if ( ! str_contains( $file_to_extract, '.tar.gz/' ) ) {
			return array();
		}

		// get the path to the file in the archive (+7 for .tar.gz and +1 for the starting "/") we want to extract.
		$file_name = substr( $file_to_extract, absint( strpos( $file_to_extract, '.tar.gz/' ) ) + 8 );

		// bail if no file name is given.
		if ( empty( $file_name ) ) {
			return array();
		}

		// get the archive object for the given file.
		$zip_file = $this->get_object( $file_to_extract );

		// bail if zip could not be opened.
		if ( ! $zip_file instanceof PharData ) {
			return array();
		}

		// bail if file does not exist in the archive.
		if ( ! isset( $zip_file[ $file_name ] ) || ! $zip_file[ $file_name ] instanceof PharFileInfo ) { // @phpstan-ignore instanceof.alwaysTrue
			return array();
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $zip_file[ $file_name ]->getFilename() );

		// bail if file type is not allowed.
		if ( ! $this->is_mime_type_allowed( (string) $mime_type['type'] ) ) {
			return array();
		}

		// get the content of the file, if it is not too large.
		$content = $this->get_content( $zip_file[ $file_name ], $file_name );
		if ( ! is_string( $content ) ) {
			return array();
		}

		// get the tmp file name.
		$extracted_file = wp_tempnam();

		// change the tmp file name for import.
		$extracted_file = str_replace( '.tmp', '', $extracted_file . '.' . $mime_type['ext'] );

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// save content in the tmp file.
		$wp_filesystem->put_contents( $extracted_file, $content );

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
		// if path does not end with ".tar.gz/", it is a single file request.
		if ( ! str_ends_with( $this->get_zip_file(), '.tar.gz/' ) ) {
			return $this->get_file_info_from_zip( $this->get_zip_file() );
		}

		// get the zip object.
		$zip_file = $this->get_object( $this->get_zip_file() );

		// bail if zip could not be opened.
		if ( ! $zip_file instanceof PharData ) {
			return array();
		}

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// collect the files.
		$results = array();

		// get the list of files in this archive, also in its subdirectories, and return their data.
		foreach ( new RecursiveIteratorIterator( $zip_file ) as $file ) {
			// bail if this is not PharFileInfo or a directory.
			if ( ! $file instanceof PharFileInfo || $file->isDir() ) {
				continue;
			}

			// get content type of this file.
			$mime_type = wp_check_filetype( $file->getFilename() );

			// bail if file type is not allowed.
			if ( ! $this->is_mime_type_allowed( (string) $mime_type['type'] ) ) {
				continue;
			}

			// get the content of the file, if it is not too large.
			$content = $this->get_content( $file, $this->get_path_in_archive( $zip_file, $file ) );
			if ( ! is_string( $content ) ) {
				continue;
			}

			// get the tmp file name.
			$tmp_file = wp_tempnam();

			// change the tmp file name for import.
			$tmp_file = str_replace( '.tmp', '', $tmp_file . '.' . $mime_type['ext'] );

			// save content in the tmp file.
			$wp_filesystem->put_contents( $tmp_file, $content );

			// collect the entry.
			$entry = array(
				'title'         => $file->getFilename(),
				'local'         => true,
				'last-modified' => absint( $file->getMTime() ),
				'tmp-file'      => $tmp_file,
				'mime-type'     => $mime_type['type'],
				'url'           => $this->get_zip_file() . $this->get_path_in_archive( $zip_file, $file ),
				'filesize'      => absint( $file->getSize() ),
			);

			// add the entry to the list.
			$results[] = $entry;
		}

		// return the file list.
		return $results;
	}

	/**
	 * Return the path of the given file in the archive, e.g. "docs/file.pdf".
	 *
	 * @param PharData     $archive The archive.
	 * @param PharFileInfo $file The file in the archive.
	 *
	 * @return string
	 */
	private function get_path_in_archive( PharData $archive, PharFileInfo $file ): string {
		// the path name is "phar://<path of the archive>/<path in the archive>".
		$prefix    = 'phar://' . $archive->getPath() . '/';
		$path_name = $file->getPathname();

		// use the file name, if the path does not start with the archive.
		if ( ! str_starts_with( $path_name, $prefix ) ) {
			return $file->getFilename();
		}

		// return the path in the archive.
		return substr( $path_name, strlen( $prefix ) );
	}

	/**
	 * Return the content of a file in the archive, if it is not larger than the maximum size.
	 *
	 * @param PharFileInfo $file The file in the archive.
	 * @param string       $name The name of the file in the archive.
	 *
	 * @return string|false The content or false if it could not be read or is too large.
	 */
	private function get_content( PharFileInfo $file, string $name ): string|false {
		// bail if the archive says that the file is too large.
		if ( ! $this->is_file_size_allowed( absint( $file->getSize() ), $name ) ) {
			return false;
		}

		// open the file in the archive as stream to read only up to the maximum size.
		$stream = fopen( $file->getPathname(), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Opens the stream of a file in the archive (phar://), not a file of the filesystem.

		// bail if the stream could not be opened.
		if ( ! is_resource( $stream ) ) {
			return false;
		}

		// read the content.
		$content = $this->read_limited(
			static function ( int $length ) use ( $stream ): string|false {
				return fread( $stream, max( 1, $length ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reads the stream of a file in the archive.
			},
			$name
		);
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the stream of a file in the archive.

		// return the content.
		return $content;
	}
}
