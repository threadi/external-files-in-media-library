<?php
/**
 * File which handles the DropBox support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\DropBox;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'dropbox';

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Check if URL is compatible with the given protocol.
	 *
	 * @return bool
	 */
	public function is_url_compatible(): bool {
		// bail if this is not a DropBox URL.
		if ( ! str_starts_with( strtolower( $this->get_url() ), DropBox::get_instance()->get_name() ) ) {
			return false;
		}

		// return true to use this protocol.
		return true;
	}

	/**
	 * Check format of given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function check_url( string $url ): bool {
		// bail if empty URL is given.
		if ( empty( $url ) ) {
			return false;
		}

		// return true as DropBox URLs are available.
		return true;
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>> List of files with its infos.
	 */
	public function get_url_infos(): array {
		// get the DropBox object.
		$dropbox_obj = DropBox::get_instance();

		// set the fields.
		$dropbox_obj->set_fields( $this->get_fields() );

		// get the client with the given token.
		$client = new Client( $dropbox_obj->get_access_token() );

		// prepare the entries array.
		$entries = array();

		// remove our marker from the URL.
		$url = str_replace( $dropbox_obj->get_name(), '', strtolower( $this->get_url() ) );

		// if the URL is only "/", get all files from dropbox.
		if ( '/' === $url ) {
			$files_from_dropbox = $client->listFolder( '/', true );
			if ( ! empty( $files_from_dropbox['entries'] ) ) {
				$entries = $files_from_dropbox['entries'];
			}
		} else {
			// get the file data.
			try {
				$entries[] = $client->getMetadata( $url );
			} catch ( ClientException | \Spatie\Dropbox\Exceptions\BadRequest $e ) {
				// log this event.
				Log::get_instance()->create( __( 'Error during request of DropBox file:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e ) . '</code>', '', 'error' );

				// create the error entry.
				$error_obj = new Url_Result();
				/* translators: %1$s will be replaced by a URL. */
				$error_obj->set_result_text( sprintf( __( 'Error during request of DropBox file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_url() ) ) );
				$error_obj->set_url( $this->get_url() );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );
			}
		}

		// bail if file_data is empty (e.g. if error occurred).
		if ( empty( $entries ) ) {
			return array();
		}

		/**
		 * Run action if we have files to check via Dropbox-protocol.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param string $url   The URL to import.
		 * @param array<int|string,mixed> $entries List of matches (the URLs).
		 */
		do_action( 'efml_dropbox_directory_import_files', $url, $entries );

		// loop through all files and add them with their URL infos to the list of all files.
		$files = array();
		foreach ( $entries as $file_data ) {
			// bail if this is an error.
			if ( 'folder' === $file_data['.tag'] ) {
				continue;
			}

			// set file URL.
			$file_url = $dropbox_obj->get_name() . $file_data['path_display'];

			/**
			 * Run action just before the file check via Dropbox-protocol.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param string $file_url   The URL to import.
			 */
			do_action( 'efml_dropbox_directory_import_file_check', $file_url );

			// bail if this an AJAX-request and the file already exist in media library.
			if ( wp_doing_ajax() && Files::get_instance()->get_file_by_url( $file_url ) ) {
				Log::get_instance()->create( __( 'The specified URL already exist in your media library.', 'external-files-in-media-library' ), $this->get_url(), 'error', 0, Import::get_instance()->get_identifier() );

				// do nothing more.
				continue;
			}

			// initialize basic array for file data.
			$results = array(
				'title'         => $file_data['name'],
				'local'         => true,
				'url'           => $file_url,
				'last-modified' => absint( strtotime( $file_data['client_modified'] ) ),
			);

			// get mime type.
			$mime_type = wp_check_filetype( $results['title'] );

			// get WP Filesystem-handler.
			$wp_filesystem = Helper::get_wp_filesystem();

			// get the tmp file name.
			$tmp_file_name = wp_tempnam();

			// set the file as tmp-file for import.
			$results['tmp-file'] = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

			// get the file from DropBox.
			$content = stream_get_contents( $client->download( $file_data['path_lower'] ) );

			// bail if content could not be loaded.
			if ( ! is_string( $content ) ) { // @phpstan-ignore function.alreadyNarrowedType
				return array();
			}

			// and save this content als tmp-file.
			try {
				$wp_filesystem->put_contents( $results['tmp-file'], $content );
				$wp_filesystem->delete( $tmp_file_name );
			} catch ( Error $e ) {
				// create the error entry.
				$error_obj = new Url_Result();
				/* translators: %1$s will be replaced by a URL. */
				$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_url() ) ) );
				$error_obj->set_url( $this->get_url() );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );

				// add log entry.
				Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url(), 'error' );

				// do nothing more.
				return array();
			}

			// set the file size.
			$results['filesize'] = absint( $file_data['size'] );

			// set the mime type.
			$results['mime-type'] = $mime_type['type'];

			// add to the list.
			$files[] = $results;
		}

		// return the resulting array as list of files.
		return $files;
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		return true;
	}

	/**
	 * Return whether this URL could change its hosting.
	 *
	 * @return bool
	 */
	public function can_change_hosting(): bool {
		return false;
	}

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return DropBox::get_instance()->get_label();
	}

	/**
	 * Return whether this URL could be checked for availability.
	 *
	 * @return bool
	 */
	public function can_check_availability(): bool {
		return false;
	}

	/**
	 * Return whether URLs with this protocol are reachable via HTTP.
	 *
	 * This is not the availability of the URL.
	 *
	 * @return bool
	 */
	public function is_url_reachable(): bool {
		return false;
	}
}
