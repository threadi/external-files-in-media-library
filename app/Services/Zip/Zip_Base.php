<?php
/**
 * File to handle zip objects.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Zip;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle zip objects for different zip formats.
 */
class Zip_Base {
	/**
	 * The ZIP file to use.
	 *
	 * @var string
	 */
	private string $zip_file = '';

	/**
	 * Return if the given file is compatible with this object.
	 *
	 * @param string $file The file to check.
	 *
	 * @return bool
	 */
	public function is_compatible( string $file ): bool {
		if ( empty( $file ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Return the path of the given file or URL, which should be used for compatibility checks.
	 *
	 * For URLs only the path is used, so a host like "gzip.example.com" does not influence the check.
	 *
	 * @param string $file The file or URL.
	 *
	 * @return string
	 */
	protected function get_path_for_compatibility_check( string $file ): string {
		// get the host of this URL.
		$host = wp_parse_url( $file, PHP_URL_HOST );

		// return the given string if it is not a URL with a host.
		if ( ! is_string( $host ) || empty( $host ) ) {
			return $file;
		}

		// return only the path of this URL.
		return (string) wp_parse_url( $file, PHP_URL_PATH );
	}

	/**
	 * Return the directory listing of a given file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_directory_listing(): array {
		return array();
	}

	/**
	 * Return whether files with the given mime type are allowed to be listed and extracted.
	 *
	 * Like for all other external files, only the mime types allowed in the settings are used.
	 *
	 * @param string $mime_type The mime type.
	 *
	 * @return bool
	 */
	protected function is_mime_type_allowed( string $mime_type ): bool {
		return ! empty( $mime_type ) && in_array( $mime_type, Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Return the directory listing for the given entries of the archive.
	 *
	 * The result contains the archive itself and each directory in it as own entry, each with its files and
	 * its direct subdirectories. Files with a type, which is not allowed in the settings, are not listed.
	 *
	 * @param array<int,array{name: string, size: int, mtime: int}> $entries List of entries with their path in the archive.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_listing_of_entries( array $entries ): array {
		// the key and the URL of the archive itself.
		$root = $this->get_zip_file();

		// collect the list of files and directories in the root of the archive.
		$listing = array(
			'title' => basename( $root ),
			'files' => array(),
			'dirs'  => array(),
		);

		// collect the directories of the archive with their contents.
		$folders = array();

		foreach ( $entries as $entry_data ) {
			// get the path in the archive, always with slashes.
			$name = ltrim( str_replace( '\\', '/', $entry_data['name'] ), '/' );

			// bail if name is empty.
			if ( empty( $name ) ) {
				continue;
			}

			// get the directories and the file name of this entry (entries ending with a slash are directories).
			$parts    = explode( '/', $name );
			$filename = array_pop( $parts );

			// bail if this is a file with a type, which is not allowed.
			$mime_type = array( 'type' => '' );
			if ( ! empty( $filename ) ) {
				$mime_type = wp_check_filetype( $filename );
				if ( ! $this->is_mime_type_allowed( (string) $mime_type['type'] ) ) {
					continue;
				}
			}

			// add each directory of this path to the list, if it is not already there.
			$parent = $root;
			$path   = '';
			foreach ( $parts as $directory ) {
				// bail if directory name is empty.
				if ( '' === $directory ) {
					continue;
				}

				// get the key of this directory.
				$path .= $directory . '/';
				$index = $root . '/' . $path;

				// add it to the list of directories and to its parent directory.
				if ( ! isset( $folders[ $index ] ) ) {
					$folders[ $index ] = array(
						'title' => $directory,
						'files' => array(),
						'dirs'  => array(),
					);

					// create the entry for the parent.
					$sub = array(
						'title' => $directory,
						'files' => array(),
						'dirs'  => array(),
					);

					// add it to the parent.
					if ( $root === $parent ) {
						$listing['dirs'][ $index ] = $sub;
					} else {
						$folders[ $parent ]['dirs'][ $index ] = $sub;
					}
				}

				// use this directory as parent for the next one.
				$parent = $index;
			}

			// bail if this entry is a directory.
			if ( empty( $filename ) ) {
				continue;
			}

			// collect the data of the file.
			$entry = array(
				'title'         => $filename,
				'file'          => $root . '/' . $name,
				'filesize'      => absint( $entry_data['size'] ),
				'mime-type'     => $mime_type['type'],
				'icon'          => '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( (string) $mime_type['type'] ) . '"></span>',
				'last-modified' => Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $entry_data['mtime'] ) ) ),
				'preview'       => '',
			);

			// add the file to its directory.
			if ( $root === $parent ) {
				$listing['files'][] = $entry;
			} else {
				$folders[ $parent ]['files'][] = $entry;
			}
		}

		// return the resulting list.
		return array_merge( array( 'completed' => true ), array( $root => $listing ), $folders );
	}

	/**
	 * Return the info about a single file in the zip.
	 *
	 * @param string $file_to_extract The file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_file_info_from_zip( string $file_to_extract ): array {
		if ( empty( $file_to_extract ) ) {
			return array();
		}
		return array();
	}

	/**
	 * Return the zip file to use by this object.
	 *
	 * @return string
	 */
	public function get_zip_file(): string {
		return $this->zip_file;
	}

	/**
	 * Set the zip file to use by this object.
	 *
	 * @param string $zip_file The file to use.
	 *
	 * @return void
	 */
	public function set_zip_file( string $zip_file ): void {
		$this->zip_file = $zip_file;
	}

	/**
	 * Return list of files in zip to import (and extract) in media library.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_files_from_zip(): array {
		return array();
	}

	/**
	 * Mark if this handler can be used.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return false;
	}

	/**
	 * Return whether this file could be opened.
	 *
	 * @return bool
	 */
	public function can_file_be_opened(): bool {
		return false;
	}

	/**
	 * Return whether the given file is in a zip.
	 *
	 * @return bool
	 */
	public function is_file_in_zip(): bool {
		return false;
	}

	/**
	 * Return the maximum size in bytes for a single file extracted from an archive.
	 *
	 * Uses the setting in MB or, if it is not set, the maximum upload size of WordPress.
	 *
	 * @return int
	 */
	public function get_max_file_size(): int {
		// get the setting.
		$max_size = absint( get_option( 'eml_zip_max_file_size', 0 ) ) * MB_IN_BYTES;

		// use the maximum upload size of WordPress, if no size is set.
		if ( 0 === $max_size ) {
			$max_size = absint( wp_max_upload_size() );
		}

		$instance = $this;
		/**
		 * Filter the maximum size in bytes for a single file extracted from an archive (ZIP, .tar.gz or .gz).
		 *
		 * @since 5.5.2 Available since 5.5.2.
		 * @param int      $max_size The maximum size in bytes.
		 * @param Zip_Base $instance The archive object.
		 */
		return absint( apply_filters( 'efml_zip_max_file_size', $max_size, $instance ) );
	}

	/**
	 * Return whether a file with the given size could be extracted from the archive. Logs it if not.
	 *
	 * @param int    $size The size of the extracted file in bytes, as given by the archive.
	 * @param string $file The name of the file in the archive.
	 *
	 * @return bool
	 */
	protected function is_file_size_allowed( int $size, string $file ): bool {
		// bail if the size is allowed.
		if ( $size <= $this->get_max_file_size() ) {
			return true;
		}

		// log this event.
		$this->log_too_large_file( $file );

		// return false as the file is too large.
		return false;
	}

	/**
	 * Read the content of a file from an archive with the given callback in parts, until the maximum size is reached.
	 *
	 * The size given by the archive could be wrong (e.g. in manipulated archives), so the read data is counted.
	 *
	 * @param callable $read Callback, which returns the next part of the content with the given length or '' / false at the end.
	 * @param string   $file The name of the file in the archive.
	 *
	 * @return string|false The content or false if it is too large.
	 */
	protected function read_limited( callable $read, string $file ): string|false {
		// get the maximum size.
		$max_size = $this->get_max_file_size();

		// read the content in parts.
		$content = '';
		while ( true ) {
			$part = $read( 65536 );

			// bail on the end of the content.
			if ( ! is_string( $part ) || '' === $part ) {
				break;
			}

			// add the part.
			$content .= $part;

			// bail if the maximum size is reached.
			if ( strlen( $content ) > $max_size ) {
				$this->log_too_large_file( $file );
				return false;
			}
		}

		// return the content.
		return $content;
	}

	/**
	 * Log that a file of the archive is too large.
	 *
	 * @param string $file The name of the file in the archive.
	 *
	 * @return void
	 */
	private function log_too_large_file( string $file ): void {
		/* translators: %1$s will be replaced by the file name, %2$s by the maximum size. */
		Log::get_instance()->create( sprintf( __( 'The file %1$s in the archive is larger than the allowed size of %2$s and will not be extracted.', 'external-files-in-media-library' ), '<code>' . esc_html( $file ) . '</code>', size_format( $this->get_max_file_size() ) ), $this->get_zip_file(), 'error' );
	}
}
