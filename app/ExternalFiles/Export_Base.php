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

	/**
	 * Return whether this service could export the sizes of images and their original image.
	 *
	 * If true, the sizes of an exported image (e.g. "image-300x200.jpg") and its original image (for scaled
	 * images) are exported via export_size() to the same folder as the image itself, and deleted via delete_size().
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_sizes(): bool {
		return false;
	}

	/**
	 * Export an additional file of an attachment, e.g. a size of an image or its original image, to this service.
	 *
	 * Returns the URL of the exported file like export_file() and false on error. Unlike export_file() the file
	 * is given via its absolute path. The post meta of the attachment must not be changed, as it belongs to the
	 * main file of the attachment.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $source        The absolute path of the local file.
	 * @param string              $target        The target of the file (like for export_file(), with its file name).
	 * @param array<string,mixed> $credentials   The credentials (incl. "private" like for export_file()).
	 *
	 * @return string|bool
	 */
	public function export_size( int $attachment_id, string $source, string $target, array $credentials ): string|bool {
		if ( $attachment_id > 0 || empty( $source ) || empty( $target ) || empty( $credentials ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Delete an additional file of an attachment, which has been exported via export_size().
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $url           The URL, which export_size() returned.
	 * @param string              $target        The target, which has been used for export_size().
	 * @param array<string,mixed> $credentials   The credentials.
	 *
	 * @return bool
	 */
	public function delete_size( int $attachment_id, string $url, string $target, array $credentials ): bool {
		if ( $attachment_id > 0 || empty( $url ) || empty( $target ) || empty( $credentials ) ) {
			return false;
		}
		return false;
	}
}
