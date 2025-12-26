<?php
/**
 * File to handle zip objects.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Zip;

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
	 * Return the directory listing of a given file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_directory_listing(): array {
		return array();
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
}
