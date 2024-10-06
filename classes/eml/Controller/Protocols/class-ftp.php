<?php
/**
 * File which handles the ftp support.
 *
 * Hint:
 * Files loaded with this protocol MUST be saved local to use them via http.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller\Protocols;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Controller\Protocol_Base;
use threadi\eml\Model\Log;
use WP_Filesystem_FTPext;

/**
 * Object to handle different protocols.
 */
class Ftp extends Protocol_Base {
	/**
	 * List of supported tcp protocols.
	 *
	 * @var array
	 */
	protected array $tcp_protocols = array(
		'ftp',
		'ftps',
		'sftp'
	);

	/**
	 * Check the given file-url regarding its string.
	 *
	 * Return true if file-url is ok.
	 * Return false if file-url is not ok
	 *
	 * @return bool
	 */
	public function check_url(): bool {
		// check for duplicate.
		if ( $this->check_for_duplicate() ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given url %s already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

		// all ok with the url.
		$return = true;
		/**
		 * Filter the resulting for checking an external URL.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param bool $return The result of this check.
		 * @param string $url The requested external URL.
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'eml_check_url', $return, $this->get_url() );
	}

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability(): bool {
		return true;
	}

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_file_infos(): array {
		// initialize the file infos array.
		$results = array(
			'title' => basename( $this->get_url() ),
			'filesize'  => 0,
			'mime-type' => '',
			'tmp-file' => '',
			'local' => true,
		);

		// bail if no credentials are set.
		if( empty( $this->get_login() ) || empty( $this->get_password() ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Missing credentials for FTP-URL %1$s.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );
			return $results;
		}

		// get the path from given URL.
		$parse_url = parse_url( $this->get_url() );

		// bail if validation is not resulting in an array.
		if( ! is_array( $parse_url ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'FTP-URL %1$s looks not like an URL.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );
			return $results;
		}

		// get the host.
		$host = $parse_url['host'];

		// get the path.
		$path = $parse_url['path'];

		// Typically this is not defined, so we set it up just in case
		if ( ! defined( 'FS_CONNECT_TIMEOUT' ) ) {
			define( 'FS_CONNECT_TIMEOUT', 30 );
		}

		// load necessary classes.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		}
		if ( ! class_exists( 'WP_Filesystem_FTPext' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-ftpext.php' );
		}

		// define ftp connection.
		$connection_arguments = array(
			'port' => 21,
			'hostname' => $host,
			'username' => $this->get_login(),
			'password' => $this->get_password()
		);

		// connect via FTP.
		$ftp_connection = new WP_Filesystem_FTPext( $connection_arguments );
		if ( ! $ftp_connection->connect() ) {
			Log::get_instance()->create( sprintf( __( 'FTP-connection failed. Check the server-name %1$s and the given credentials.', 'external-files-in-media-library' ), $host ), $this->get_url(), 'error', 0 );
			return $results;
		}

		// check if file exists.
		if( ! $ftp_connection->is_file( $path ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'FTP-URL %1$s does not exist.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );
			return $results;
		}

		// get the file contents.
		$file_content = $ftp_connection->get_contents( $path );
		if ( empty( $file_content ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'FTP-URL %1$s returns an empty file.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );
			return $results;
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// save this file in a temporary directory.
		$temp_file = wp_tempnam( $results['title'] );
		if( $wp_filesystem->put_contents( $temp_file, $file_content ) ) {
			$results['tmp-file'] = $temp_file;
		}

		// get the mime types.
		$mime_type = wp_check_filetype( $results['title'] );
		$results['mime-type'] = $mime_type['type'];

		// get the size.
		$results['filesize'] = wp_filesize( $temp_file );

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param array $results List of detected file settings.
		 * @param string $url The requested external URL.
		 */
		return apply_filters( 'eml_external_file_infos', $results, $this->get_url() );
	}

	/**
	 * Files from FTP should be saved local every time.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * FTP-urls could not check its availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * FTP-files could not change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}
}
