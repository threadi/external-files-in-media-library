<?php
/**
 * File which handles the file-protocol support.
 *
 * Hint:
 * Files loaded with this protocol MUST be saved local to use them via http.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Filesystem_Base;

/**
 * Object to handle the file protocol.
 */
class File extends Protocol_Base {
	/**
	 * Internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'file';

	/**
	 * List of supported tcp protocols.
	 *
	 * @var array<string,int>
	 */
	protected array $tcp_protocols = array(
		'file' => -1,
	);

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Local files', 'external-files-in-media-library' );
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>> List of file-infos.
	 */
	public function get_url_infos(): array {
		// sanitize the local file path.
		$url = $this->sanitize_local_path( $this->get_url() );

		// initialize list of files.
		$files = array();

		// get actual object for hooks.
		$instance = $this;

		/**
		 * Filter the file with custom import methods.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,array<string,mixed>> $array Result list with infos.
		 * @param string $url The URL to import.
		 * @param File $instance The actual protocol object.
		 */
		$results = apply_filters( 'efml_filter_file_response', $files, $url, $instance );
		if ( ! empty( $results ) ) {
			// return the result as array for import this as single URL.
			if ( isset( $results['title'] ) ) { // @phpstan-ignore isset.offset
				return array( $results );
			}

			// return the result as list of files.
			return $results; // @phpstan-ignore return.type
		}

		// get WP_Filesystem object.
		$wp_filesystem = Helper::get_wp_filesystem();

		// check if given URL is a directory.
		if ( $wp_filesystem->is_dir( $url ) ) {
			// show deprecated hint for old hook.
			do_action_deprecated( 'eml_file_directory_import_start', array( $url ), '5.0.0', 'efml_file_directory_import_start' );

			/**
			 * Run action on beginning of presumed directory import via file-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 */
			do_action( 'efml_file_directory_import_start', $url );

			// get the files.
			$file_list = $wp_filesystem->dirlist( $url );

			// bail if list could not be loaded.
			if ( ! is_array( $file_list ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Files could not be loaded from directory.', 'external-files-in-media-library' ), $url, 'error', 0, Import::get_instance()->get_identifier() );

				// add the result to the list.
				$result = new Results\Url_Result();
				$result->set_url( $url );
				$result->set_result_text( __( 'Files could not be loaded from directory.', 'external-files-in-media-library' ) );
				$result->set_error( true );
				Results::get_instance()->add( $result );

				// do nothing more.
				return array();
			}

			/**
			 * Run action if we have files to check via FILE-protocol.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param string $url   The URL to import.
			 * @param array<string,array<string,mixed>> $file_list List of files.
			 */
			do_action( 'efml_file_directory_import_files', $url, $file_list );

			// show progress.
			/* translators: %1$s is replaced by a URL. */
			$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( __( 'Check files from presumed directory path %1$s', 'external-files-in-media-library' ), $url ), count( $file_list ) ) : '';

			// loop through the directory.
			foreach ( $file_list as $file => $settings ) {
				// get file path.
				$file_path = $url . $file;

				// bail if this is not a file.
				if ( 'd' === $settings['type'] ) {
					// show progress.
					$progress ? $progress->tick() : '';

					// bail to next file.
					continue;
				}

				// check for duplicate.
				if ( $this->check_for_duplicate( $file_path ) ) {
					// log this event.
					Log::get_instance()->create( __( 'This file is already in your media library.', 'external-files-in-media-library' ), $file_path, 'error', 0, Import::get_instance()->get_identifier() );

					// add the result to the list.
					$result = new Results\Url_Result();
					$result->set_url( $file_path );
					$result->set_result_text( __( 'This file is already in your media library.', 'external-files-in-media-library' ) );
					$result->set_error( true );
					Results::get_instance()->add( $result );

					// do nothing more.
					continue;
				}

				// show deprecated hint for old hook.
				do_action_deprecated( 'eml_file_directory_import_file_check', array( $file_path ), '5.0.0', 'efml_file_directory_import_file_check' );

				/**
				 * Run action just before the file check via file-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_path   The filepath to import.
				 */
				do_action( 'efml_file_directory_import_file_check', $file_path );

				// get file data.
				$results = $this->get_url_info( $file_path );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail if results are empty.
				if ( empty( $results ) ) {
					// bail to next file.
					continue;
				}

				// show deprecated hint for old hook.
				do_action_deprecated( 'eml_file_directory_import_file_before_to_list', array( $file_path, $file_list ), '5.0.0', 'efml_file_directory_import_file_before_to_list' );

				/**
				 * Run action just before the file is added to the list via file-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_path   The filepath to import.
				 * @param array<int|string,mixed> $file_list List of files.
				 */
				do_action( 'efml_file_directory_import_file_before_to_list', $file_path, $file_list );

				// add file to the list.
				$files[] = $results;
			}

			// finish the progress.
			$progress ? $progress->finish() : '';
		} else {
			// check for duplicate.
			if ( $this->check_for_duplicate( $url ) ) {
				Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), $url, 'error', 0, Import::get_instance()->get_identifier() );
				return array();
			}

			// gert file data.
			$results = $this->get_url_info( $url );

			// bail if results are empty.
			if ( empty( $results ) ) {
				return array();
			}

			// add file to the list.
			$files[] = $results;
		}

		// show deprecated hint for old hook.
		$files = apply_filters_deprecated( 'eml_external_files_infos', array( $files, $instance ), '5.0.0', 'efml_external_files_infos' );

		/**
		 * Filter list of files during this import.
		 *
		 * @since 3.0.0 Available since 3.0.0
		 * @param array<int,array<string,mixed>> $files List of files.
		 * @param Protocol_Base $instance The import object.
		 * @param string $url The used URL.
		 */
		return apply_filters( 'efml_external_files_infos', $files, $instance, $url );
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string $url The file path.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		// use file path as name for this in this protocol handler.
		$file_path = $this->sanitize_local_path( $url );

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

		// show deprecated hint for old hook.
		$true = apply_filters_deprecated( 'eml_file_check_existence', array( true, $file_path ), '5.0.0', 'efml_file_check_existence' );

		/**
		 * Filter the check if local file exist.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param bool $true True if filter should be used.
		 * @param string $file_path The absolute file path.
		 */
		if ( apply_filters( 'efml_file_check_existence', $true, $file_path ) && ! file_exists( $file_path ) ) {
			Log::get_instance()->create( __( 'File-URL does not exist.', 'external-files-in-media-library' ), $this->sanitize_local_path( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );
			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the mime types.
		$mime_type            = wp_check_filetype( $results['title'] );
		$results['mime-type'] = $mime_type['type'];

		// get the last modified date.
		$results['last-modified'] = absint( $wp_filesystem->mtime( $file_path ) );

		// get the file content.
		$content = $wp_filesystem->get_contents( $file_path );

		if ( is_string( $content ) ) {
			// set the file as tmp-file for import.
			$results['tmp-file'] = wp_tempnam();

			// set the file as tmp-file for import with appropriate file extension.
			$results['tmp-file'] = str_replace( '.tmp', '', $results['tmp-file'] . '.' . $mime_type['ext'] );

			// and save the file there.
			try {
				$wp_filesystem->put_contents( $results['tmp-file'], $content );
			} catch ( Error $e ) {
				// create the error entry.
				$error_obj = new Url_Result();
				/* translators: %1$s will be replaced by a URL. */
				$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $url ) ) );
				$error_obj->set_url( $url );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );

				// add log entry.
				Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $url, 'error' );

				// do nothing more.
				return array();
			}

			// get the size.
			$results['filesize'] = absint( $wp_filesystem->size( $results['tmp-file'] ) );
		}

		$response_headers = array();

		// show deprecated hint for old hook.
		$results = apply_filters_deprecated( 'eml_external_file_infos', array( $results, $file_path, $response_headers ), '5.0.0', 'efml_external_file_infos' );

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param array<string,mixed>  $results List of detected file settings.
		 * @param string $url     The requested external URL.
		 * @param array<string,mixed> $response_headers The response header.
		 */
		return apply_filters( 'efml_external_file_infos', $results, $file_path, $response_headers );
	}

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * This depends on the hosting, e.g. if necessary libraries are available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Files from local should be saved local every time.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * Local URLs could not check its availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * Local files could not change its hosting.
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

	/**
	 * Return the original local path of the file as temp file for it but without the protocol.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		// bail if URL is empty.
		if ( empty( $url ) ) {
			return false;
		}

		// show deprecated hint for old hook.
		$true = apply_filters_deprecated( 'eml_save_temp_file', array( true, $url ), '5.0.0', 'efml_save_temp_file' );

		/**
		 * Filter whether the given URL should be saved as local temp file.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param bool $true Should be false to prevent the temp generation.
		 * @param string $url The given URL.
		 */
		if ( ! apply_filters( 'efml_save_temp_file', $true, $url ) ) {
			return false;
		}

		// remove protocol and domain from URL.
		$url_info = wp_parse_url( $url );

		// bail if path could not be loaded.
		if ( ! isset( $url_info['path'] ) ) {
			return false;
		}

		// get the file path.
		$file_path = $url_info['path'];

		// get file infos.
		$file_info = pathinfo( $file_path );

		// bail if extension could not be loaded.
		if ( ! isset( $file_info['extension'] ) ) {
			return false;
		}

		// get local WP Filesystem-handler for temp file.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the tmp file name.
		$tmp_file_name = wp_tempnam();

		// set the file as tmp-file for import with appropriate file extension.
		$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $file_info['extension'] );

		// get content of the file.
		$content = $wp_filesystem->get_contents( $file_path );

		// delete the tmp file.
		$wp_filesystem->delete( $tmp_file_name );

		// bail if no content could be loaded.
		if ( ! $content ) {
			return false;
		}

		// and save the file there.
		try {
			$wp_filesystem->put_contents( $tmp_file, $content );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->sanitize_local_path( $this->get_url() ) ) ) );
			$error_obj->set_url( $this->sanitize_local_path( $this->get_url() ) );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->sanitize_local_path( $this->get_url() ), 'error' );

			// do nothing more.
			return false;
		}

		// return the path to the tmp file.
		return $tmp_file;
	}

	/**
	 * Return the sanitized local path.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	private function sanitize_local_path( string $url ): string {
		return 'file://' . realpath( str_replace( 'file://', '', $url ) );
	}
}
