<?php
/**
 * File which handles the ssh/sftp support.
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
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Filesystem_SSH2;

/**
 * Object to handle different protocols.
 */
class Sftp extends Protocol_Base {
	/**
	 * List of supported tcp protocols.
	 *
	 * @var array
	 */
	protected array $tcp_protocols = array(
		'sftp' => 22,
	);

	/**
	 * List of SSH connections.
	 *
	 * @var array
	 */
	private array $ssh_connections = array();

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_infos(): array {
		// initialize list of files.
		$files = array();

		// bail if no credentials are set.
		if ( empty( $this->get_login() ) || empty( $this->get_password() ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Missing credentials for import from SFTP-URL %1$s.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

		// get the path from given URL.
		$parse_url = wp_parse_url( $this->get_url() );

		// bail if validation is not resulting in an array.
		if ( ! is_array( $parse_url ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'SFTP-URL %1$s looks not like an URL.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

		// get the host.
		$host = $parse_url['host'];

		// get the path.
		$path = $parse_url['path'];

		// load necessary classes.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		}
		if ( ! class_exists( 'WP_Filesystem_SSH2' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-ssh2.php';
		}

		// define ssh/sftp connection parameter.
		$connection_arguments = array(
			'port'     => $this->get_port_by_protocol( $parse_url['scheme'] ),
			'hostname' => $host,
			'username' => $this->get_login(),
			'password' => $this->get_password(),
		);

		// connect via SSH.
		$ssh_connection = $this->get_ssh_connection( $connection_arguments );

		// bail if connection failed.
		if ( ! $ssh_connection ) {
			return array();
		}

		// if SFTP-path is a directory, import all files from there.
		if ( $ssh_connection->is_dir( $path ) ) {
			// get the files from SFTP directory as list.
			$file_list = $ssh_connection->dirlist( $path );
			if ( empty( $file_list ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'SFTP-directory %1$s returns no files.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

				return array();
			}

			// loop through the matches.
			foreach ( $file_list as $filename => $settings ) {
				// get the file path on sftp.
				$file_path = $path . $filename;

				// get url.
				$url = $this->get_url() . $filename;

				// check for duplicate.
				if ( $this->check_for_duplicate( $url ) ) {
					/* translators: %1$s will be replaced by the file-URL */
					Log::get_instance()->create( sprintf( __( 'Given file %1$s already exist in media library.', 'external-files-in-media-library' ), esc_url( $file_path ) ), esc_url( $file_path ), 'error', 0 );
					continue;
				}

				// get the file data.
				$results = $this->get_url_info( $file_path, $ssh_connection );

				// bail if results are empty.
				if ( empty( $results ) ) {
					continue;
				}

				// add the URL to the results.
				$results['url'] = $url;

				// add file to the list.
				$files[] = $results;
			}
		} else {
			// add file to the list.
			$results = $this->get_url_info( $path, $ssh_connection );

			// bail if results are empty.
			if ( empty( $results ) ) {
				return array();
			}

			// add file to the list.
			$files[] = $results;
		}

		// return resulting list of file with its data.
		return $files;
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string             $file_path The SSH/SFTP path.
	 * @param WP_Filesystem_SSH2 $ssh_connection The SSH/SFTP connection object.
	 *
	 * @return array
	 */
	private function get_url_info( string $file_path, WP_Filesystem_SSH2 $ssh_connection ): array {
		// initialize the file infos array.
		$results = array(
			'title'     => basename( $file_path ),
			'filesize'  => 0,
			'mime-type' => '',
			'tmp-file'  => '',
			'local'     => true,
			'url'       => $file_path,
		);

		// bail if file does not exist.
		if ( ! $ssh_connection->is_readable( $file_path ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'SFTP-URL %1$s does not exist.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

		// get the file contents.
		$file_content = $ssh_connection->get_contents( $file_path );
		if ( empty( $file_content ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'SFTP-URL %1$s returns an empty file.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// save this file in a temporary directory.
		$temp_file = wp_tempnam( $results['title'] );
		if ( $wp_filesystem->put_contents( $temp_file, $file_content ) ) {
			$results['tmp-file'] = $temp_file;
		}

		// get the mime types.
		$mime_type            = wp_check_filetype( $results['title'] );
		$results['mime-type'] = $mime_type['type'];

		// get the size.
		$results['filesize'] = wp_filesize( $temp_file );

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param array  $results List of detected file settings.
		 * @param string $url     The requested external URL.
		 */
		return apply_filters( 'eml_external_file_infos', $results, $file_path );
	}

	/**
	 * Get the SFTP connection for given connection arguments.
	 *
	 * @param array $connection_arguments The arguments for the connection.
	 *
	 * @return false|WP_Filesystem_SSH2
	 */
	private function get_ssh_connection( array $connection_arguments ): false|WP_Filesystem_SSH2 {
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
		if ( ! empty( $this->ssh_connections[ md5( wp_json_encode( $connection_arguments ) ) ] ) ) {
			return $this->ssh_connections[ md5( wp_json_encode( $connection_arguments ) ) ];
		}

		// get the connection.
		$connection = new WP_Filesystem_SSH2( $connection_arguments );

		// bail if connection was not successfully.
		if ( ! $connection->connect() ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'SSH/SFTP-connection failed. Check the server-name %1$s and the given credentials.', 'external-files-in-media-library' ), $connection_arguments['hostname'] ), $this->get_url(), 'error', 0 );
			return false;
		}

		// add connection to the list.
		$this->ssh_connections[ md5( wp_json_encode( $connection_arguments ) ) ] = $connection;

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
		return function_exists( 'ssh2_connect' );
	}

	/**
	 * Files from SFTP should be saved local every time.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * SFTP-urls could not check its availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * SFTP-files could not change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return the link to the given URL.
	 *
	 * @return string
	 */
	public function get_link(): string {
		return basename( $this->get_url() );
	}
}
