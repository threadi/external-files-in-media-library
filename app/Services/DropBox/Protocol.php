<?php
/**
 * File, which handles the DropBox support as own protocol.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\DropBox;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\DropBox;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;
use Spatie\Dropbox\Exceptions\BadRequest;
use WP_Filesystem_Base;

/**
 * Object to handle different protocols.
 */
class Protocol extends Protocol_Base {
	/**
	 * The lifetime of temporary links of DropBox in seconds (4 hours).
	 *
	 * @var int
	 */
	private const TEMPORARY_LINK_LIFETIME = 14400;

	/**
	 * The internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'dropbox';

	/**
	 * Return whether this protocol is available in this hosting.
	 *
	 * This depends on the hosting, e.g., if necessary libraries are available.
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
		$client = $dropbox_obj->get_client();

		// bail if client could not be loaded.
		if ( ! $client instanceof Client ) {
			return array();
		}

		// prepare the entries array.
		$entries = array();

		// remove our marker from the URL.
		$url = (string) preg_replace( '#^' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(/' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(?=/))?#i', '', $this->get_url() );

		// set URL for "/" is it is empty (which would not be load anything).
		if ( empty( $url ) ) {
			$url = '/';
		}

		// if the URL is only "/", get all files from DropBox.
		if ( '/' === $url ) {
			$entries = $this->get_entries_of_folder( $client, '/' );
		} else {
			// get the data for a single file or folder.
			try {
				$metadata = $client->getMetadata( $url );

				// get all files of a folder (also from its subfolders), otherwise use the single file.
				if ( isset( $metadata['.tag'] ) && 'folder' === $metadata['.tag'] ) {
					$entries = $this->get_entries_of_folder( $client, $url );
				} else {
					$entries[] = $metadata;
				}
			} catch ( ClientException | BadRequest $e ) {
				// log this event.
				Log::get_instance()->create( __( 'Error during request of DropBox file:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e ) . '</code>', $url, 'error' );

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

		// bail if "file_data" is empty (e.g., if error occurred).
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

			// bail if this an AJAX-request, and the file already exist in media library.
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

			// set the file size.
			$results['filesize'] = absint( $file_data['size'] );

			// set the mime type.
			$results['mime-type'] = $mime_type['type'];

			// bail if this file must not be loaded during an import (e.g. as it is too large).
			if ( Import::get_instance()->is_file_loading_prevented( $results ) ) {
				continue;
			}

			// get WP Filesystem-handler.
			$wp_filesystem = Helper::get_wp_filesystem();

			// get the tmp file name.
			$tmp_file_name = wp_tempnam();

			// set the file as tmp-file for import.
			$results['tmp-file'] = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

			// save the file from DropBox directly as tmp-file, so it is not loaded completely into memory.
			try {
				$wp_filesystem->delete( $tmp_file_name );
				if ( ! $this->save_stream( $client->download( $file_data['path_lower'] ), $results['tmp-file'] ) ) {
					// clean up the tmp file.
					$wp_filesystem->delete( $results['tmp-file'] );

					// do nothing more.
					return array();
				}
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

	/**
	 * Return temp file from given URL.
	 *
	 * @param string             $url The given URL.
	 * @param WP_Filesystem_Base $filesystem The file system handler.
	 *
	 * @return bool|string
	 */
	public function get_temp_file( string $url, WP_Filesystem_Base $filesystem ): bool|string {
		// use the HTTP handler for public URLs, e.g. of exported files.
		if ( str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ) {
			$http_protocol_handler = new Http( $url );
			$http_protocol_handler->set_fields( $this->get_fields() );

			return $http_protocol_handler->get_temp_file( $url, $filesystem );
		}

		// get the DropBox object.
		$dropbox_obj = DropBox::get_instance();

		// cleanup the URL.
		$url = (string) preg_replace( '#^' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(/' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(?=/))?#i', '', $url );

		// set the fields.
		$dropbox_obj->set_fields( $this->get_fields() );

		// get the client with the given token.
		$client = $dropbox_obj->get_client();

		// bail if client could not be loaded.
		if ( ! $client instanceof Client ) {
			return false;
		}

		// get the file from DropBox.
		$stream = $client->download( $url );

		// get the tmp file name.
		$tmp_file_name = wp_tempnam();

		// get mime type.
		$mime_type = wp_check_filetype( $url );

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

		// remove the file of "wp_tempnam()", if it is not used.
		if ( $tmp_file !== $tmp_file_name ) {
			$filesystem->delete( $tmp_file_name );
		}

		// save the data directly into the tmp file, so it is not loaded completely into memory.
		if ( ! $this->save_stream( $stream, $tmp_file ) ) {
			// clean up the tmp file.
			$filesystem->delete( $tmp_file );

			// return false as we got no file.
			return false;
		}

		// return the name and path of the tmp file.
		return $tmp_file;
	}

	/**
	 * Save the given stream (e.g. a download from DropBox) in the given file.
	 *
	 * The stream is copied in parts, so it is not loaded completely into memory.
	 *
	 * @param mixed  $stream The stream.
	 * @param string $file   The absolute path to the file.
	 *
	 * @return bool True if the stream has been saved.
	 */
	private function save_stream( mixed $stream, string $file ): bool {
		// bail if the stream is not usable.
		if ( ! is_resource( $stream ) ) {
			return false;
		}

		// open the file.
		$handle = fopen( $file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// bail if the file could not be opened.
		if ( false === $handle ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return false;
		}

		// copy the stream into the file.
		$copied = stream_copy_to_stream( $stream, $handle );

		// close both.
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// return whether the stream has been copied.
		return false !== $copied;
	}

	/**
	 * Return a temporary link of DropBox for a private file.
	 *
	 * DropBox creates temporary links via its API, which are valid for 4 hours. To prevent a request for each
	 * delivery, the link is cached as long as it is valid for the requested time range.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url     The URL of the file (as saved on the external file).
	 * @param int    $start   The start of the validity as timestamp.
	 * @param int    $expires The end of the validity as timestamp.
	 *
	 * @return string
	 */
	public function get_signed_url( string $url, int $start, int $expires ): string {
		// bail if URL is empty or a public URL (e.g. of files exported before).
		if ( empty( $url ) || str_starts_with( $url, 'http://' ) || str_starts_with( $url, 'https://' ) ) {
			return '';
		}

		// get the actual time.
		$now = time();

		// bail if the requested validity is longer than the validity of temporary links of DropBox.
		if ( $expires <= $start || $expires - $now > self::TEMPORARY_LINK_LIFETIME - MINUTE_IN_SECONDS ) {
			return '';
		}

		// get the cached link.
		$transient_name = 'efml_dropbox_link_' . md5( $url . (string) wp_json_encode( $this->get_fields() ) );
		$cached         = get_transient( $transient_name );

		// use the cached link, if it is valid long enough.
		if ( is_array( $cached ) && ! empty( $cached['link'] ) && absint( $cached['valid_until'] ) >= $expires ) {
			return (string) $cached['link'];
		}

		// get the DropBox object.
		$dropbox_obj = DropBox::get_instance();

		// set the fields.
		$dropbox_obj->set_fields( $this->get_fields() );

		// get the client with the given token.
		$client = $dropbox_obj->get_client();

		// bail if client could not be loaded.
		if ( ! $client instanceof Client ) {
			return '';
		}

		// cleanup the URL to get the path.
		$path = (string) preg_replace( '#^' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(/' . preg_quote( $dropbox_obj->get_name(), '#' ) . '(?=/))?#i', '', $url );

		// get the temporary link.
		try {
			$link = $client->getTemporaryLink( $path );
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error occurred during request for a temporary DropBox link:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );

			// return empty string to use the cache instead.
			return '';
		}

		// cache the link (with a safety margin).
		$valid_until = $now + self::TEMPORARY_LINK_LIFETIME - MINUTE_IN_SECONDS;
		set_transient(
			$transient_name,
			array(
				'link'        => $link,
				'valid_until' => $valid_until,
			),
			$valid_until - $now
		);

		// return the link.
		return $link;
	}

	/**
	 * Return infos about a single given URL.
	 *
	 * Exported files are available under a public HTTPS-URL, so we use the
	 * HTTP protocol handler to get its infos.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		// get the HTTP protocol handler.
		$http_protocol_handler = new Http( $url );
		$http_protocol_handler->set_fields( $this->get_fields() );

		// return the results from the HTTP handler.
		return $http_protocol_handler->get_url_info( $url );
	}

	/**
	 * Return all entries of the given folder in DropBox, also from its subfolders.
	 *
	 * DropBox returns large folders in multiple parts, which are all loaded.
	 *
	 * @param Client $client The DropBox client.
	 * @param string $path The path of the folder.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_entries_of_folder( Client $client, string $path ): array {
		// get the first part.
		$result  = $client->listFolder( $path, true );
		$entries = ! empty( $result['entries'] ) ? $result['entries'] : array();

		// get the following parts.
		while ( ! empty( $result['has_more'] ) && ! empty( $result['cursor'] ) ) {
			$result = $client->listFolderContinue( $result['cursor'] );
			if ( ! empty( $result['entries'] ) ) {
				$entries = array_merge( $entries, $result['entries'] );
			}
		}

		// return the entries.
		return $entries;
	}
}
