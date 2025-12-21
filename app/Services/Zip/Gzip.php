<?php
/**
 * File to handle .gz-files.
 *
 * These packages contain only single files.
 * To use multiple files in a .gz, use @TarGzip.
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

/**
 * Object to handle gzip files.
 */
class Gzip extends Zip_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Gzip
	 */
	private static ?Gzip $instance = null;

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
	 * @return Gzip
	 */
	public static function get_instance(): Gzip {
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
	 * @return string|false
	 */
	private function get_object( string $file ): string|false {
		// get the path to the ZIP from path string.
		$zip_file = substr( $file, 0, absint( strpos( $file, '.gz' ) ) ) . '.gz';

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
			Log::get_instance()->create( __( 'GZIP-file could not be saved as temp file.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// do nothing more.
			return false;
		}

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $tmp_zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'GZIP-file to use for extracting a file does not exist.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return the opened object.
		$zip = gzopen( $tmp_zip_file, 'r' );

		// bail if file could not be opened.
		if ( ! $zip ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return the path of the tmp file.
		return $tmp_zip_file;
	}

	/**
	 * Mark if this handler can be used.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return function_exists( 'gzopen' ) && function_exists( 'finfo_open' );
	}

	/**
	 * Return if the given file is compatible with this object.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_compatible( string $file ): bool {
		return str_contains( $file, '.gz' ) && ! str_contains( $file, '.tar.gz' );
	}

	/**
	 * Return whether the given file is in a zip.
	 *
	 * @return bool
	 */
	public function is_file_in_zip(): bool {
		return ! str_ends_with( $this->get_zip_file(), '.gz' ) && ! str_contains( $this->get_zip_file(), '.tar.gz' );
	}

	/**
	 * Return the directory listing of a given file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_directory_listing(): array {
		// get the tmp file for this package.
		$tmp_file = $this->get_object( $this->get_zip_file() );

		// bail if ZIP could not be opened.
		if ( ! is_string( $tmp_file ) ) {
			return array();
		}

		// get the content of the zip package (it is a single file).
		ob_start();
		readgzfile( $tmp_file );
		$content = ob_get_clean();
		ob_end_clean();

		// bail if no contents could be read.
		if ( ! $content ) {
			return array();
		}

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the tmp file name.
		$extracted_file = wp_tempnam();

		// save content in the tmp file.
		$wp_filesystem->put_contents( $extracted_file, $content );

		// initiate the file info.
		$file_info = finfo_open( FILEINFO_MIME_TYPE );

		// bail if file info could not be loaded.
		if ( ! $file_info ) {
			return array();
		}

		// get the content type of this file.
		$mime_type = finfo_file( $file_info, $extracted_file );

		// bail if mime type could not be loaded.
		if ( ! is_string( $mime_type ) ) {
			return array();
		}

		// close the file info.
		finfo_close( $file_info );

		// collect the entry.
		$entry = array(
			'title'         => basename( $this->get_zip_file(), '.gz' ),
			'file'          => $this->get_zip_file() . '/' . basename( $this->get_zip_file(), '.gz' ),
			'filesize'      => absint( filesize( $tmp_file ) ),
			'mime-type'     => $mime_type,
			'icon'          => '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type ) . '"></span>',
			'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', time() ) ),
			'preview'       => '',
		);

		// return the file "list".
		return array(
			'completed'           => true,
			$this->get_zip_file() => array(
				'title' => $this->get_zip_file(),
				'files' => array( $entry ),
				'dirs'  => array(),
			),
		);
	}

	/**
	 * Return whether this file could be opened.
	 *
	 * @return bool
	 */
	public function can_file_be_opened(): bool {
		return is_string( $this->get_object( $this->get_zip_file() ) );
	}

	/**
	 * Return info about single file in zip.
	 *
	 * @param string $file_to_extract The file.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_file_info_from_zip( string $file_to_extract ): array {
		// get the path to the file in the ZIP (+4 for .zip and +1 for the starting "/") we want to extract.
		$file_to_extract = substr( $file_to_extract, strpos( $file_to_extract, '.zip' ) + 5 );

		// get the zip object for the given file.
		$tmp_file = $this->get_object( $file_to_extract );

		// bail if zip could not be opened.
		if ( ! is_string( $tmp_file ) ) {
			return array();
		}

		// collect the entry data.
		return array( // @phpstan-ignore return.type
			'filesize'      => absint( filesize( $tmp_file ) ),
			'last-modified' => time(),
			'tmp-file'      => $tmp_file,
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
		$tmp_file = $this->get_object( $this->get_zip_file() );

		// bail if zip could not be opened.
		if ( ! is_string( $tmp_file ) ) {
			return array();
		}

		// get the content of the zip package (it is a single file).
		ob_start();
		readgzfile( $tmp_file );
		$content = ob_get_clean();
		ob_end_clean();

		// bail if no contents could be read.
		if ( ! $content ) {
			return array();
		}

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the tmp file name.
		$extracted_file = wp_tempnam();

		// save content in the tmp file.
		$wp_filesystem->put_contents( $extracted_file, $content );

		// initiate the file info.
		$file_info = finfo_open( FILEINFO_MIME_TYPE );

		// bail if file info could not be loaded.
		if ( ! $file_info ) {
			return array();
		}

		// get the content type of this file.
		$mime_type = finfo_file( $file_info, $extracted_file );

		// close the file info.
		finfo_close( $file_info );

		// rename the tmp file depending on the now detected mime type.
		$extensions = Helper::get_possible_mime_types();
		if ( ! empty( $extensions[ $mime_type ] ) ) {
			$extracted_file_with_ext = str_replace( '.tmp', '', $extracted_file . '.' . $extensions[ $mime_type ]['ext'] );
			if ( $wp_filesystem->move( $extracted_file, $extracted_file_with_ext ) ) {
				$extracted_file = $extracted_file_with_ext;
			}
		}

		// return the resulting file data.
		return array(
			array(
				'title'         => basename( $this->get_zip_file() ),
				'local'         => true,
				'last-modified' => time(),
				'tmp-file'      => $extracted_file,
				'mime-type'     => $mime_type,
				'url'           => $this->get_zip_file(),
				'filesize'      => absint( filesize( $tmp_file ) ),
			),
		);
	}
}
