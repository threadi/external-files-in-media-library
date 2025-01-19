<?php
/**
 * File which handles the FTP support.
 *
 * Hint:
 * Files loaded with this protocol MUST be saved local to use them via http.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Queue;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Filesystem_Base;
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
		'ftp'  => 21,
		'ftps' => 21,
	);

	/**
	 * List of FTP-connections in this object.
	 *
	 * @var array
	 */
	private array $ftp_connections = array();

	/**
	 * Initialize the object to handle FTP-connections.
	 *
	 * @param string $url The URL to use.
	 */
	public function __construct( string $url ) {
		// typically this is not defined, so we set it up just in case.
		if ( ! defined( 'FS_CONNECT_TIMEOUT' ) ) {
			define( 'FS_CONNECT_TIMEOUT', get_option( 'eml_timeout' ) );
		}

		// load necessary classes.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}
		if ( ! class_exists( 'WP_Filesystem_FTPext' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-ftpext.php';
		}

		// call parent constructor.
		parent::__construct( $url );
	}

	/**
	 * Check the given URL regarding its string.
	 *
	 * Return true if URL is ok.
	 * Return false if URL is not ok
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// check for duplicate.
		if ( $this->check_for_duplicate( $url ) ) {
			// log event.
			Log::get_instance()->create( __( 'Given URL already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0 );

			// return false as URL is a duplicate.
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
	 * Check the availability of a given URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability( string $url ): bool {
		return true;
	}

	/**
	 * Check the availability of a given URL.
	 *
	 * @return array List of file-infos.
	 */
	public function get_url_infos(): array {
		// initialize list of files.
		$files = array();

		// bail if no credentials are set.
		if ( empty( $this->get_login() ) || empty( $this->get_password() ) ) {
			Log::get_instance()->create( __( 'Missing credentials for import from FTP-path.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0 );
			return array();
		}

		// get the path from given URL.
		$parse_url = wp_parse_url( $this->get_url() );

		// bail if validation is not resulting in an array.
		if ( ! is_array( $parse_url ) ) {
			Log::get_instance()->create( __( 'FTP-path looks not like an URL.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0 );
			return array();
		}

		// get the host.
		$host = $parse_url['host'];

		// get the path.
		$path = $parse_url['path'];

		// connect via FTP.
		$ftp_connection = $this->get_connection( $this->get_url() );

		// bail if connection failed.
		if ( ! $ftp_connection ) {
			return array();
		}

		// if FTP-path is a directory, import all files from there.
		if ( $ftp_connection->is_dir( $path ) ) {
			/**
			 * Run action on beginning of presumed directory import via FTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 */
			do_action( 'eml_ftp_directory_import_start', $this->get_url() );

			// get the files from FTP directory as list.
			$file_list = $ftp_connection->dirlist( $path );
			if ( empty( $file_list ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( __( 'FTP-directory returns no files.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0 );

				// exit the process.
				return array();
			}

			// convert the dirlist-array to a file_list array with complete URLs as value for queuing.
			$file_list_new = array();
			foreach ( $file_list as $filename => $settings ) {
				$file_list_new[] = $this->get_url() . $filename;
			}

			// add files to list in queue mode.
			if ( $this->is_queue_mode() ) {
				Queue::get_instance()->add_urls( $file_list_new, $this->get_login(), $this->get_password() );
				return array();
			}

			/**
			 * Run action if we have files to check via FTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 * @param array $file_list List of files.
			 */
			do_action( 'eml_ftp_directory_import_files', $this->get_url(), $file_list );

			// show progress.
			/* translators: %1$s is replaced by a URL. */
			$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( __( 'Check files from presumed directory URL %1$s', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), count( $file_list ) ) : '';

			// loop through the matches.
			foreach ( $file_list as $filename => $settings ) {
				// get the file path on FTP.
				$file_path = $path . $filename;

				// get URL.
				$file_url = $this->get_url() . $filename;

				// check for duplicate.
				if ( $this->check_for_duplicate( $file_url ) ) {
					Log::get_instance()->create( __( 'Given file already exist in media library.', 'external-files-in-media-library' ), esc_url( $file_path ), 'error' );

					// show progress.
					$progress ? $progress->tick() : '';

					// bail on duplicate file.
					continue;
				}

				/**
				 * Run action just before the file check via FTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 */
				do_action( 'eml_ftp_directory_import_file_check', $file_url );

				// get the file data.
				$results = $this->get_url_info( $file_path, $ftp_connection );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail if results are empty.
				if ( empty( $results ) ) {
					continue;
				}

				// add the URL to the results.
				$results['url'] = $file_url;

				/**
				 * Run action just before the file is added to the list via FTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 * @param array $file_list_new List of files to process.
				 */
				do_action( 'eml_ftp_directory_import_file_before_to_list', $file_url, $file_list_new );

				// add file to the list.
				$files[] = $results;
			}

			// finish progress.
			$progress ? $progress->finish() : '';
		} else {
			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() ) ) {
				Log::get_instance()->create( __( 'Given URL already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error' );
				return array();
			}

			// add files to list in queue mode.
			if ( $this->is_queue_mode() ) {
				Queue::get_instance()->add_urls( array( $path ), $this->get_login(), $this->get_password() );
				return array();
			}

			// add file to the list.
			$results = $this->get_url_info( $path, $ftp_connection );

			// bail if results are empty.
			if ( empty( $results ) ) {
				return array();
			}

			// add the URL to the results.
			$results['url'] = $this->get_url();

			// add file to the list.
			$files[] = $results;
		}

		/**
		 * Filter list of files during this import.
		 *
		 * @since 3.0.0 Available since 3.0.0
		 * @param array $files List of files.
		 * @param Protocol_Base $this The import object.
		 */
		return apply_filters( 'eml_external_files_infos', $files, $this );
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string               $file_path The FTP path.
	 * @param WP_Filesystem_FTPext $ftp_connection The FTP connection object.
	 *
	 * @return array
	 */
	private function get_url_info( string $file_path, WP_Filesystem_FTPext $ftp_connection ): array {
		// initialize the file infos array.
		$results = array(
			'title'         => basename( $file_path ),
			'filesize'      => 0,
			'mime-type'     => '',
			'tmp-file'      => '',
			'local'         => true,
			'url'           => $file_path,
			'last-modified' => '',
		);

		// get the file contents.
		if ( ! $ftp_connection->is_readable( $file_path ) ) {
			Log::get_instance()->create( __( 'FTP-URL is not readable.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0 );

			// return empty array as we got not the file.
			return array();
		}

		// get the mime types.
		$mime_type            = wp_check_filetype( $results['title'] );
		$results['mime-type'] = $mime_type['type'];

		// get the size.
		$results['filesize'] = $ftp_connection->size( $file_path );

		// get the last modified date.
		$results['last-modified'] = $ftp_connection->mtime( $file_path );

		// get the temp file.
		$results['tmp-file'] = $this->get_temp_file( $file_path, $ftp_connection );

		$response_headers = array();
		/**
		 * Filter the data of a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param array  $results List of detected file settings.
		 * @param string $url     The requested external URL.
		 * @param array $response_headers The response header.
		 */
		return apply_filters( 'eml_external_file_infos', $results, $file_path, $response_headers );
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
	 * FTP-paths could not check its availability.
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

	/**
	 * Get the FTP connection for given connection arguments.
	 *
	 * @param string $url The URL to use for the connection.
	 *
	 * @return false|WP_Filesystem_Base
	 */
	public function get_connection( string $url ): false|WP_Filesystem_Base {
		// get the path from given URL.
		$parse_url = wp_parse_url( $url );

		// bail if validation is not resulting in an array.
		if ( ! is_array( $parse_url ) ) {
			Log::get_instance()->create( __( 'FTP-path looks not like an URL.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0 );
			return false;
		}

		// define ftp connection parameter.
		$connection_arguments = array(
			'port'            => $this->get_port_by_protocol( $parse_url['scheme'] ),
			'hostname'        => $parse_url['host'],
			'username'        => $this->get_login(),
			'password'        => $this->get_password(),
			'connection_type' => $parse_url['scheme'],
		);

		// bail if hostname is not set.
		if ( empty( $connection_arguments['hostname'] ) ) {
			return false;
		}

		// bail if login is not set.
		if ( empty( $connection_arguments['username'] ) ) {
			return false;
		}

		// bail if password is not set.
		if ( empty( $connection_arguments['password'] ) ) {
			return false;
		}

		// check if connection is already in cache.
		if ( ! empty( $this->ftp_connections[ md5( wp_json_encode( $connection_arguments ) ) ] ) ) {
			return $this->ftp_connections[ md5( wp_json_encode( $connection_arguments ) ) ];
		}

		// get the connection.
		$connection = new WP_Filesystem_FTPext( $connection_arguments );

		// bail if connection was not successfully.
		if ( ! $connection->connect() ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'FTP-Connection failed. Check the server-name %1$s and the given credentials. Error: %2$s', 'external-files-in-media-library' ), $connection_arguments['hostname'], '<code>' . wp_json_encode( $connection->errors ) . '</code>' ), $this->get_url(), 'error' );
			return false;
		}

		// add connection to the list.
		$this->ftp_connections[ md5( wp_json_encode( $connection_arguments ) ) ] = $connection;

		// return the connection object.
		return $connection;
	}

	/**
	 * Return whether this protocol could be used.
	 *
	 * This depends on the hosting, e.g. if necessary libraries are available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return function_exists( 'ftp_connect' ) || function_exists( 'ftp_ssl_connect' );
	}

	/**
	 * Return the link to the given URL.
	 *
	 * @return string
	 */
	public function get_link(): string {
		return basename( $this->get_url() );
	}

	/**
	 * Get temp file from given FTP-file.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): false|string {
		// bail if URL is empty.
		if ( empty( $url ) ) {
			return false;
		}

		// remove protocol and domain from URL.
		$url_info = wp_parse_url( $url );
		$file_path = $url_info['path'];

		// get file infos.
		$file_info = pathinfo( $file_path );

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', wp_tempnam() . '.' . $file_info['extension'] );

		// and save the file there.
		$wp_filesystem->put_contents( $tmp_file, $filesystem->get_contents( $file_path ) );

		// return the path to the tmp file.
		return $tmp_file;
	}
}
