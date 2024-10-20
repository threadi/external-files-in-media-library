<?php
/**
 * File which handles the file-protocol support.
 *
 * Hint:
 * Files loaded with this protocol MUST be saved local to use them via http.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller\Protocols;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use threadi\eml\Controller\Protocol_Base;
use threadi\eml\Model\Log;

/**
 * Object to handle different protocols.
 */
class File extends Protocol_Base {
	/**
	 * List of supported tcp protocols.
	 *
	 * @var array
	 */
	protected array $tcp_protocols = array(
		'file' => - 1,
	);

	/**
	 * Check the availability of a given file-url.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_infos(): array {
		// initialize list of files.
		$files = array();

		// check if given URL is a directory.
		if( is_dir( $this->get_url() ) ) {
			foreach( scandir( $this->get_url() ) as $file ) {
				// get file path.
				$file_path = $this->get_url() . $file;

				// bail if this is not a file.
				if( ! is_file( $file_path ) ) {
					continue;
				}

				// get file data.
				$results = $this->get_url_info( $file_path );

				// bail if results are empty.
				if( empty( $results ) ) {
					return array();
				}

				// add file to the list.
				$files[] = $results;
			}
		}
		else {
			// gert file data.
			$results = $this->get_url_info( $this->get_url() );

			// bail if results are empty.
			if( empty( $results ) ) {
				return array();
			}

			// add file to the list.
			$files[] = $results;
		}

		return $files;
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string $file_path The file path.
	 *
	 * @return array
	 */
	private function get_url_info( string $file_path ): array {
		// initialize the file infos array.
		$results = array(
			'title'     => basename( $file_path ),
			'filesize'  => 0,
			'mime-type' => '',
			'tmp-file'  => '',
			'local'     => true,
			'url' => $file_path
		);

		// bail if file does not exist.
		if( ! file_exists( $file_path ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'File-URL %1$s does not exist.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// get the file contents.
		$file_content = $wp_filesystem->get_contents( $file_path );
		if ( empty( $file_content ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'File-URL %1$s returns an empty file.', 'external-files-in-media-library' ), $this->get_url() ), $this->get_url(), 'error', 0 );

			return array();
		}

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
	 * Return whether this protocol could be used.
	 *
	 * This depends on the hosting, e.g. if necessary libraries are available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
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
