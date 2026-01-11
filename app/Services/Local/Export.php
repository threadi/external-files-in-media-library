<?php
/**
 * File to handle export tasks for local.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Local;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object for export files to local hosting.
 */
class Export extends Export_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Export|null
	 */
	private static ?Export $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// remove protocol from target.
		$local_target = str_replace( 'file://', '', $target );

		// get the WP filesystem object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the file path.
		$file_path = get_attached_file( $attachment_id );

		// bail if file path could not be loaded.
		if ( ! is_string( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not load file path for given attachment ID.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the upload dir.
		$upload_dir = wp_get_upload_dir();

		// if upload dir is not in this path, add it.
		if ( ! str_contains( $file_path, $upload_dir['basedir'] ) ) {
			$file_path = trailingslashit( $upload_dir['basedir'] ) . $file_path;
		}

		// bail if source file does not exist.
		if ( ! $wp_filesystem->exists( $file_path ) ) {
			/* translators: %1$s will be replaced by the service title. */
			Log::get_instance()->create( sprintf( __( 'Local path %1$s does not exist.', 'external-files-in-media-library' ), '<em>' . $file_path . '</em>' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// bail if target file does already exist.
		if ( $wp_filesystem->exists( $local_target ) ) {
			/* translators: %1$s will be replaced by the service title. */
			Log::get_instance()->create( sprintf( __( 'Target file %1$s already exist.', 'external-files-in-media-library' ), '<em>' . $target . '</em>' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// copy file to the given local directory.
		if ( ! $wp_filesystem->copy( $file_path, $local_target ) ) {
			/* translators: %1$s and %2$s will be replaced by filenames. */
			Log::get_instance()->create( sprintf( __( 'Could not copy file from %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . $file_path . '</em>', '<em>' . $local_target . '</em>' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		/* translators: %1$s and %2$s will be replaced by filenames. */
		Log::get_instance()->create( sprintf( __( 'Successfully copied file from %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . $file_path . '</em>', '<em>' . $local_target . '</em>' ), $target, 'info', 2 );

		// return the file URL.
		return $target;
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url           The URL to delete.
	 * @param array<string,mixed> $credentials   The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// get the WP filesystem object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $url ) ) {
			return false;
		}

		// delete the file.
		$wp_filesystem->delete( $url );

		// return true as file has been deleted.
		return true;
	}

	/**
	 * Return whether this export requires a specific URL.
	 *
	 * If this is false, the external plattform must create this URL.
	 *
	 * @return bool
	 */
	public function is_url_required(): bool {
		return true;
	}
}
