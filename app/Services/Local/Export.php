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
		// remove protocol from the target.
		$local_target = Helper::get_local_path_from_url( $target );

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
			Log::get_instance()->create( sprintf( __( 'The local path %1$s does not exist.', 'external-files-in-media-library' ), '<em>' . $file_path . '</em>' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// bail if the target is not located in the allowed base directory for local files.
		if ( ! $this->is_target_allowed( $local_target ) ) {
			/* translators: %1$s will be replaced by a path. */
			Log::get_instance()->create( sprintf( __( 'The target %1$s is not located in the allowed directory for local files.', 'external-files-in-media-library' ), '<em>' . esc_html( $local_target ) . '</em>' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// create the folder of the target, if it does not exist (e.g., for the folder structure of exports).
		if ( ! $wp_filesystem->is_dir( dirname( $local_target ) ) && ! wp_mkdir_p( dirname( $local_target ) ) ) {
			/* translators: %1$s will be replaced by a path. */
			Log::get_instance()->create( sprintf( __( 'The folder %1$s could not be created.', 'external-files-in-media-library' ), '<em>' . esc_html( dirname( $local_target ) ) . '</em>' ), $target, 'error' );

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
		Log::get_instance()->create( sprintf( __( 'Successful copied file from %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . $file_path . '</em>', '<em>' . $local_target . '</em>' ), $target, 'info', 2 );

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

		// remove protocol from the URL.
		$local_file = Helper::get_local_path_from_url( $url );

		// bail if file does not exist or is not a file.
		if ( ! $wp_filesystem->exists( $local_file ) || ! $wp_filesystem->is_file( $local_file ) ) {
			return false;
		}

		// bail if the file is not located in the allowed base directory for local files.
		if ( ! Helper::is_local_path_allowed( $local_file ) ) {
			/* translators: %1$s will be replaced by a path. */
			Log::get_instance()->create( sprintf( __( 'The file %1$s is not located in the allowed directory for local files and has not been deleted.', 'external-files-in-media-library' ), '<em>' . esc_html( $local_file ) . '</em>' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// delete the file.
		$wp_filesystem->delete( $local_file );

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

	/**
	 * Return whether this service supports folders for exported files.
	 *
	 * @return bool
	 */
	public function supports_folders(): bool {
		return true;
	}

	/**
	 * Return whether the given target is located in the allowed base directory for local files.
	 *
	 * The folders of the target might not exist yet, so the nearest existing folder is checked. Targets with
	 * relative folders like ".." are never allowed.
	 *
	 * @param string $local_target The local target path.
	 *
	 * @return bool
	 */
	private function is_target_allowed( string $local_target ): bool {
		// bail if the target contains relative folders.
		if ( in_array( '..', explode( '/', str_replace( '\\', '/', $local_target ) ), true ) ) {
			return false;
		}

		// get the nearest existing folder of the target.
		$folder = dirname( $local_target );
		while ( ! is_dir( $folder ) && dirname( $folder ) !== $folder ) {
			$folder = dirname( $folder );
		}

		// return whether this folder is allowed.
		return Helper::is_local_path_allowed( trailingslashit( $folder ) . basename( $local_target ) );
	}

	/**
	 * Return whether a file with the given target path already exists in the local target directory.
	 *
	 * @param string              $target The target path of the file on the external source.
	 * @param array<string,mixed> $credentials The credentials to use (not used for local files).
	 *
	 * @return bool
	 */
	public function file_exists_on_target( string $target, array $credentials ): bool {
		// remove protocol from the target.
		$local_target = str_replace( 'file://', '', $target );

		// bail if the target is not located in the allowed base directory for local files.
		if ( ! Helper::is_local_path_allowed( $local_target ) ) {
			return false;
		}

		// return whether the file exists.
		return Helper::get_wp_filesystem()->exists( $local_target );
	}
}
