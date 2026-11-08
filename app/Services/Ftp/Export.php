<?php
/**
 * File to handle export tasks for FTP.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Ftp;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Filesystem_FTPext;

/**
 * Object for export files to FTP.
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
		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $target );

		// bail if the detected protocol handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a FTP-URL.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// set the login.
		$protocol_handler_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $target );

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			// log this event.
			Log::get_instance()->create( __( 'Got wrong object to load FTP-data.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the file path.
		$file_path = get_attached_file( $attachment_id, true );

		// bail if no file could be found.
		if ( ! is_string( $file_path ) ) {
			return false;
		}

		// get the local WP_Filesystem.
		$wp_filesystem_local = Helper::get_wp_filesystem();

		// bail if source file does not exist.
		if ( ! $wp_filesystem_local->exists( $file_path ) ) {
			return false;
		}

		// get the URL parts.
		$parse_url = wp_parse_url( $target );

		// bail if path could not be found.
		if ( empty( $parse_url['path'] ) ) {
			return false;
		}

		// bail if target file does already exist.
		if ( $ftp_connection->exists( $parse_url['path'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'The file already exists on the FTP server and will not be overwritten. The file has not been exported.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// upload the file to FTP.
		if ( ! $this->upload_file( $ftp_connection, $file_path, $parse_url['path'] ) ) {
			// remove the incomplete file, as the server may have created it before the transfer failed.
			// otherwise a new try would fail, as the target file exists.
			// delete it directly, as WordPress checks the existence first, which needs a data connection.
			if ( $ftp_connection->link instanceof \FTP\Connection ) {
				@ftp_delete( $ftp_connection->link, $parse_url['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			// log this event.
			Log::get_instance()->create( __( 'File could not be saved on the FTP server.', 'external-files-in-media-library' ), $target, 'error' );

			return false;
		}

		// return the URL of this file.
		return $target;
	}

	/**
	 * Upload a local file to the given path on the FTP server.
	 *
	 * The file is transferred directly from its path via ftp_put(), so it is not loaded completely
	 * into memory (like with put_contents() of WordPress, which also creates a temporary copy).
	 * Without a native FTP connection the file is transferred via put_contents() as before.
	 *
	 * @param WP_Filesystem_FTPext $ftp_connection The FTP connection.
	 * @param string               $file_path The local path of the file.
	 * @param string               $target_path The path on the FTP server.
	 *
	 * @return bool
	 */
	private function upload_file( WP_Filesystem_FTPext $ftp_connection, string $file_path, string $target_path ): bool {
		// use the native FTP connection, if available.
		if ( $ftp_connection->link instanceof \FTP\Connection ) {
			// bail if the transfer failed.
			if ( ! @ftp_put( $ftp_connection->link, $target_path, $file_path, FTP_BINARY ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return false;
			}

			// set the permissions like put_contents() of WordPress does it.
			$ftp_connection->chmod( $target_path );

			// return true as the file has been uploaded.
			return true;
		}

		// get the file content.
		$content = Helper::get_wp_filesystem()->get_contents( $file_path );

		// bail if no content could be loaded.
		if ( ! is_string( $content ) ) {
			return false;
		}

		// put the content to FTP.
		return $ftp_connection->put_contents( $target_path, $content );
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
		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $url );

		// bail if the detected protocol handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			// log this event.
			Log::get_instance()->create( __( 'Given path is not a FTP-URL.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// set the login.
		$protocol_handler_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $url );

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			// log this event.
			Log::get_instance()->create( __( 'Got wrong object to load FTP-data.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// get the URL parts.
		$parse_url = wp_parse_url( $url );

		// bail if path could not be found.
		if ( empty( $parse_url['path'] ) ) {
			return false;
		}

		// bail if file does not exist.
		if ( ! $ftp_connection->exists( $parse_url['path'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'File to delete does not exist in FTP directory.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

		// delete the file.
		if ( ! $ftp_connection->delete( $parse_url['path'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not delete file from FTP.', 'external-files-in-media-library' ), $url, 'error' );

			// do nothing more.
			return false;
		}

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
	 * Return whether a file with the given target path already exists on the FTP server.
	 *
	 * @param string              $target The target path of the file on the external source.
	 * @param array<string,mixed> $credentials The credentials to use.
	 *
	 * @return bool
	 */
	public function file_exists_on_target( string $target, array $credentials ): bool {
		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $target );

		// bail if the detected protocol handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			return false;
		}

		// set the login.
		$protocol_handler_obj->set_fields( isset( $credentials['fields'] ) ? $credentials['fields'] : array() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $target );

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			return false;
		}

		// get the URL parts.
		$parse_url = wp_parse_url( $target );

		// bail if path could not be found.
		if ( empty( $parse_url['path'] ) ) {
			return false;
		}

		// return whether the file exists.
		return $ftp_connection->exists( $parse_url['path'] );
	}
}
