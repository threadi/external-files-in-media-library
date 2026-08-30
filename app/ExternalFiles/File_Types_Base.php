<?php
/**
 * File, which provide the base functions for each file type we support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to provide the base functions for each file type we support.
 */
class File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array();

	/**
	 * The size (e.g. for images).
	 *
	 * @var array<int>
	 */
	private array $size = array();

	/**
	 * The external file object.
	 *
	 * @var File|false
	 */
	protected File|false $external_file_obj = false;

	/**
	 * The mime type.
	 *
	 * @var string
	 */
	private string $mime_type = '';

	/**
	 * Initialize this object.
	 *
	 * @param File|false $external_file_obj The external file as object or false.
	 */
	public function __construct( false|File|string $external_file_obj ) {
		if ( $external_file_obj instanceof File ) {
			$this->external_file_obj = $external_file_obj;
		}
	}

	/**
	 * Return whether the file is compatible with this object.
	 *
	 * @return bool
	 */
	public function is_file_compatible(): bool {
		// bail if list of possible mime types in the object is empty.
		if ( empty( $this->get_mime_types() ) ) {
			return false;
		}

		// set the mime type.
		$mime_type = $this->get_mime_type();

		// get the external file object.
		$external_file_obj = $this->get_file();

		// get mime type from external file object, if set.
		if ( $external_file_obj ) {
			// use the mime type from the external file object.
			$mime_type = $external_file_obj->get_mime_type();
		}

		// check the mime types.
		$result = in_array( $mime_type, $this->get_mime_types(), true );

		// show deprecated warning for the old hook name.
		$result = apply_filters_deprecated( 'eml_file_type_compatibility_result', array( $result, $external_file_obj, $mime_type ), '5.0.0', 'efml_file_type_compatibility_result' );

		/**
		 * Filter the result of file type compatibility check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $result The result (true or false).
		 * @param File|false $external_file_obj The external file object.
		 * @param string $mime_type The used mime type (added in 3.0.0).
		 */
		return apply_filters( 'efml_file_type_compatibility_result', $result, $external_file_obj, $mime_type );
	}

	/**
	 * Output the given proxied file.
	 *
	 * @return void
	 */
	public function get_proxied_file(): void {}

	/**
	 * Return the external file object.
	 *
	 * @return File|false
	 */
	protected function get_file(): File|false {
		return $this->external_file_obj;
	}

	/**
	 * Return the mime types this object could be used for.
	 *
	 * @return array|string[]
	 */
	private function get_mime_types(): array {
		$mime_type         = $this->mime_types;
		$external_file_obj = $this->get_file();

		// show deprecated warning for the old hook name.
		$mime_type = apply_filters_deprecated( 'eml_file_type_supported_mime_types', array( $mime_type, $external_file_obj ), '5.0.0', 'efml_file_type_supported_mime_types' );

		/**
		 * Filter the supported mime types of single file type.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $mime_type List of mime types.
		 * @param File|false $external_file_obj The file object.
		 */
		return apply_filters( 'efml_file_type_supported_mime_types', $mime_type, $external_file_obj );
	}

	/**
	 * Set metadata for the file by given file data.
	 *
	 * @return void
	 */
	public function set_metadata(): void {}

	/**
	 * Return the object name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the file type title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return the configured dimensions as array (0 => width, 1 => height).
	 *
	 * @return array<int>
	 */
	protected function get_dimensions(): array {
		return $this->size;
	}

	/**
	 * Set the dimensions to use.
	 *
	 * @param array<int> $size The dimensions as array (0 => width, 1 => height).
	 *
	 * @return void
	 */
	public function set_dimensions( array $size ): void {
		$this->size = $size;
	}

	/**
	 * Return whether this file should be saved locally.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return false;
	}

	/**
	 * Return whether this file should be proxied.
	 *
	 * @return bool
	 */
	public function is_proxy_enabled(): bool {
		return false;
	}

	/**
	 * Return whether files of this type are proxied by default.
	 *
	 * @return bool
	 */
	public function is_proxy_default_enabled(): bool {
		return true;
	}

	/**
	 * Return the default proxy max age.
	 *
	 * @return int
	 */
	public function get_default_proxy_max_age(): int {
		return 24;
	}

	/**
	 * Return true if cache age has been reached its expiration.
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		return false;
	}

	/**
	 * Return whether this file type has thumbs.
	 *
	 * @return bool
	 */
	public function has_thumbs(): bool {
		return false;
	}

	/**
	 * Return the given mime type.
	 *
	 * @return string
	 */
	protected function get_mime_type(): string {
		return $this->mime_type;
	}

	/**
	 * Set the mime-type of the file.
	 *
	 * @param string $mime_type The given mime type.
	 *
	 * @return void
	 */
	public function set_mime_type( string $mime_type ): void {
		$this->mime_type = $mime_type;
	}

	/**
	 * Send the HTTP headers for a proxied file.
	 *
	 * @param string $cached_file The absolute path to the cached file.
	 *
	 * @return void
	 */
	protected function send_proxy_headers( string $cached_file ): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// bail if no file is set.
		if ( ! $external_file_obj instanceof File ) {
			return;
		}

		// use the attachment title as filename - it has been sanitized during import.
		$filename = sanitize_file_name( $external_file_obj->get_title() );

		// use a fallback if the title results in an empty string.
		if ( '' === $filename ) {
			$filename = 'download';
		}

		// create an ASCII-only variant for clients which do not support RFC 5987.
		$filename_ascii = (string) preg_replace( '/[^\x20-\x7E]/', '_', $filename );
		$filename_ascii = str_replace( array( '"', '\\' ), '_', $filename_ascii );

		// send the headers.
		header( 'Content-Type: ' . $external_file_obj->get_mime_type() );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: ' . $this->get_content_disposition() . '; filename="' . $filename_ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $filename ) );
		header( 'Content-Length: ' . (string) wp_filesize( $cached_file ) );
	}

	/**
	 * Return the content disposition for files of this type.
	 *
	 * @return string
	 */
	protected function get_content_disposition(): string {
		return 'inline';
	}

	/**
	 * Return whether files of this type should be delivered with range support.
	 *
	 * @return bool
	 */
	protected function supports_ranges(): bool {
		return false;
	}

	/**
	 * Deliver the cached file, with support for range requests if enabled.
	 *
	 * @param string $cached_file The absolute path to the cached file.
	 *
	 * @return void
	 */
	protected function deliver_file( string $cached_file ): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// bail if no file is set.
		if ( ! $external_file_obj instanceof File ) {
			exit;
		}

		// use the real size of the cached file, not the size from the import.
		$filesize = absint( wp_filesize( $cached_file ) );

		// bail if the cached file is empty.
		if ( 0 === $filesize ) {
			exit;
		}

		// send the basic headers (Content-Type, nosniff, Content-Disposition).
		$this->send_proxy_headers( $cached_file );

		// deliver the complete file if this type does not support ranges.
		if ( ! $this->supports_ranges() ) {
			header( 'Content-Length: ' . $filesize );
			$this->read_file_part( $cached_file, 0, $filesize - 1 );
			exit;
		}

		// announce range support.
		header( 'Accept-Ranges: bytes' );

		// get the requested range.
		$range = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '';

		// deliver the complete file if no range has been requested.
		if ( '' === $range ) {
			header( 'Content-Length: ' . $filesize );
			$this->read_file_part( $cached_file, 0, $filesize - 1 );
			exit;
		}

		// bail with 416 if the range could not be parsed - multipart ranges are not supported.
		if ( 1 !== preg_match( '/^bytes=(\d*)-(\d*)$/', $range, $matches ) ) {
			header( 'Content-Range: bytes */' . $filesize );
			status_header( 416 );
			exit;
		}

		// resolve the requested start and end byte.
		if ( '' === $matches[1] ) {
			// a suffix range requests the last n bytes.
			$length = min( absint( $matches[2] ), $filesize );
			$start  = $filesize - $length;
			$end    = $filesize - 1;
		} else {
			$start = absint( $matches[1] );
			$end   = '' === $matches[2] ? $filesize - 1 : absint( $matches[2] );

			// limit the end to the last byte of the file.
			if ( $end > $filesize - 1 ) {
				$end = $filesize - 1;
			}
		}

		// bail with 416 if the resulting range is not satisfiable.
		if ( $start > $end || $start >= $filesize ) {
			header( 'Content-Range: bytes */' . $filesize );
			status_header( 416 );
			exit;
		}

		// send the partial response.
		status_header( 206 );
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize );
		header( 'Content-Length: ' . ( $end - $start + 1 ) );
		$this->read_file_part( $cached_file, $start, $end );
		exit;
	}

	/**
	 * Output a part of the given file in chunks.
	 *
	 * @param string $file The absolute path to the file.
	 * @param int    $start The first byte to output.
	 * @param int    $end The last byte to output.
	 *
	 * @return void
	 */
	private function read_file_part( string $file, int $start, int $end ): void {
		// remove any output buffering to not hold the file in memory.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// open the file.
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// bail if the file could not be opened.
		if ( false === $handle ) {
			exit;
		}

		// jump to the first requested byte.
		fseek( $handle, $start ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// output the requested bytes in chunks of 512 KB.
		$remaining = $end - $start + 1;
		while ( $remaining > 0 && ! feof( $handle ) ) {
			$buffer = fread( $handle, (int) min( 524288, $remaining ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			// stop on read errors.
			if ( false === $buffer ) {
				break;
			}

			echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput
			$remaining -= strlen( $buffer );
			flush();
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}
