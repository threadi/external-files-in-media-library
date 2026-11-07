<?php
/**
 * File for the object to handle base tasks for export.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object as base for export tasks.
 */
class Export_Base {
	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		if ( $attachment_id > 0 ) {
			return false;
		}
		if ( empty( $target ) ) {
			return false;
		}
		if ( empty( $credentials ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url The URL to delete.
	 * @param array<string,mixed> $credentials The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		if ( empty( $url ) ) {
			return false;
		}
		if ( empty( $credentials ) ) {
			return false;
		}
		if ( $attachment_id > 0 ) {
			return false;
		}
		return false;
	}

	/**
	 * Return whether this export requires a specific URL.
	 *
	 * If this is false, the external plattform must create this URL.
	 *
	 * @return bool
	 */
	public function is_url_required(): bool {
		return false;
	}

	/**
	 * Return whether a file with the given target path already exists on the external source.
	 *
	 * Exported files are saved flat in the directory of the external source. This check is used to
	 * get a unique file name before a new file is saved and exported, so it does not overwrite
	 * an existing file there. Return false if the check is not possible for this service.
	 *
	 * @since 5.7.0 Available since 5.7.0.
	 *
	 * @param string              $target The target path of the file on the external source.
	 * @param array<string,mixed> $credentials The credentials to use.
	 *
	 * @return bool
	 */
	public function file_exists_on_target( string $target, array $credentials ): bool {
		if ( empty( $target ) ) {
			return false;
		}
		if ( empty( $credentials ) ) {
			return false;
		}
		return false;
	}
}
