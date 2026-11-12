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
	 * Return whether this service supports folders for exported files.
	 *
	 * If true, the target of an exported file could contain folders below the directory of the external source
	 * (e.g. "…/2026/11/file.jpg"), according to the folder structure in the export settings. The service must use
	 * the complete target for the export, the deletion and the check for existing files, and create missing folders.
	 * Services without support always get a target directly in the directory of the external source.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_folders(): bool {
		return false;
	}

	/**
	 * Return whether this service could save exported files privately.
	 *
	 * If true, the export dialog of the external source offers the option to save files privately. Then the
	 * credentials given to export_file() contain "private" => true and the service must not require, that the
	 * exported file is public available. The returned URL is only used to identify the file on the external source.
	 * Private files are always delivered via the proxy of this plugin, either from its cache or via a redirect to a
	 * signed URL (see Protocol_Base::get_signed_url()).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_private_files(): bool {
		return false;
	}

	/**
	 * Return whether a file with the given target path already exists on the external source.
	 *
	 * Exported files are saved in the directory of the external source (or in a folder below, if the service
	 * supports folders and they are enabled). This check is used to
	 * get a unique file name before a new file is saved and exported, so it does not overwrite
	 * an existing file there. Return false if the check is not possible for this service.
	 *
	 * @since 6.0.0 Available since 6.0.0.
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
