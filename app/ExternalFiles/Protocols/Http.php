<?php
/**
 * File which handles the HTTP support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Error;
use WP_Filesystem_Base;

/**
 * Object to handle the HTTP-protocol.
 */
class Http extends Protocol_Base {
	/**
	 * Internal protocol name.
	 *
	 * @var string
	 */
	protected string $name = 'http';

	/**
	 * List of supported tcp protocols.
	 *
	 * @var array<string,int>
	 */
	protected array $tcp_protocols = array(
		'http'  => 80,
		'https' => 443,
	);

	/**
	 * List of http head requests.
	 *
	 * @var array<string,mixed>
	 */
	private array $http_heads = array();

	/**
	 * Return the title of this protocol object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'HTTP(S)', 'external-files-in-media-library' );
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
		// given string is not a url.
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			// log event.
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given string %1$s is not a valid URL.', 'external-files-in-media-library' ), esc_html( $url ) ), esc_html( $url ), 'error', 0, Import::get_instance()->get_identifier() );

			// return that given string is not a valid URL.
			return false;
		}

		// all ok with the url.
		$return = apply_filters_deprecated( 'eml_check_url', array( true, $url ), '5.0.0', 'efml_check_url' );

		/**
		 * Filter the resulting for checking an external URL.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param bool $return The result of this check.
		 * @param string $url The requested external URL.
		 */
		return apply_filters( 'efml_check_url', $return, $url );
	}

	/**
	 * Check the availability of a given URL via http(s) incl. check of its content-type.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability( string $url ): bool {
		// get the header-data of this url.
		$response = $this->get_http_header( $url );

		// bail if http_response is not set.
		if ( empty( $response['http_response'] ) ) {
			return false;
		}

		// URL returns not compatible HTTP-state.
		if ( ! in_array( $response['http_response']->get_status(), $this->get_allowed_http_states( $url ), true ) ) {
			// log this event.
			/* translators: %1$d will be replaced by the HTTP-Status. */
			Log::get_instance()->create( sprintf( __( 'Specified URL response with HTTP-status %1$d.', 'external-files-in-media-library' ), '<em>' . $response['http_response']->get_status() . '</em>' ), esc_url( $url ), 'error', 0, Import::get_instance()->get_identifier() );

			// return false as file is not available.
			return false;
		}

		// request does not have a content-type header.
		$response_headers_obj = $response['http_response']->get_headers();
		$true                 = apply_filters_deprecated( 'eml_http_check_content_type_existence', array( true, $url ), '5.0.0', 'efml_http_check_content_type_existence' );
		/**
		 * Filter for check if file has content-type given.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $true True if content type check should be run.
		 * @param string $url The used URL.
		 */
		if ( false === $response_headers_obj->offsetExists( 'content-type' ) && apply_filters( 'efml_http_check_content_type_existence', $true, $url ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Specified URL response without mime-type.', 'external-files-in-media-library' ), esc_url( $url ), 'error', 0, Import::get_instance()->get_identifier() );

			// return false as file has wrong content type.
			return false;
		}

		// request does not have a valid content-type.
		$response_headers = $response_headers_obj->getAll();
		$true             = apply_filters_deprecated( 'eml_http_check_content_type', array( true, $url ), '5.0.0', 'efml_http_check_content_type' );

		/**
		 * Filter for check of file content type during availability check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $true True if content type check should be run.
		 * @param string $url The used URL.
		 */
		if ( isset( $response_headers['content-type'] ) && ! empty( $response_headers['content-type'] && apply_filters( 'efml_http_check_content_type', $true, $url ) ) && false === in_array( Helper::get_content_type_from_string( $response_headers['content-type'] ), Helper::get_allowed_mime_types(), true ) ) {
			// log this event.
			/* translators: %1$s will be replaced by its Mime-Type */
			Log::get_instance()->create( sprintf( __( 'Specified URL response with a not allowed mime-type %1$s.', 'external-files-in-media-library' ), '<code>' . $response_headers['content-type'] . '</code>' ), esc_url( $url ), 'error', 0, Import::get_instance()->get_identifier() );

			// return false as file has wrong content type.
			return false;
		}

		// show deprecated hint for old hook.
		$return = apply_filters_deprecated( 'eml_check_url_availability', array( true, $url ), '5.0.0', 'efml_check_url_availability' );

		/**
		 * Filter the result of checking an external URL.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param bool $return The result of this check.
		 * @param string $url The requested external URL.
		 */
		if ( apply_filters( 'efml_check_url_availability', $return, $url ) ) {
			// file is available.
			Log::get_instance()->create( __( 'The specified URL is available.', 'external-files-in-media-library' ), esc_url( $url ), 'success', 2, Import::get_instance()->get_identifier() );

			// return true as file is available.
			return true;
		}

		// return false as file is not available.
		return false;
	}

	/**
	 * Return the http head of given URL.
	 *
	 * Respect cache of results to prevent multiple requests per URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return array<string,mixed>
	 */
	private function get_http_header( string $url ): array {
		// bail if result is known.
		if ( ! empty( $this->http_heads[ $url ] ) ) {
			return $this->http_heads[ $url ];
		}

		// get the header-data of this url.
		$response = wp_safe_remote_head( $url, $this->get_header_args() );

		$instance = $this;
		/**
		 * Filter the HTTP header response for an external URL.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array|WP_Error $response The response.
		 * @param HTTP $instance The actual object.
		 * @param string $url The requested URL.
		 */
		$response = apply_filters( 'efml_http_header_response', $response, $instance, $url );

		// request resulted in an error.
		if ( is_wp_error( $response ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during request the HTTP header data of URL:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $response ) . '</code>', $url, 'error', 0, Import::get_instance()->get_identifier() );

			// return empty array.
			return array();
		}

		// save in cache.
		$this->http_heads[ $url ] = $response;

		// return results.
		return $this->http_heads[ $url ];
	}

	/**
	 * Return infos to each given URL.
	 *
	 * @return array<int|string,array<string,mixed>> List of files from the given URL with its infos.
	 */
	public function get_url_infos(): array {
		// initialize list of files.
		$files = array();

		// get the header-data of this url.
		$response = $this->get_http_header( $this->get_url() );

		// bail if response is empty.
		if ( empty( $response ) ) {
			return array();
		}

		// get header from response.
		$response_headers_obj = $response['http_response']->get_headers();
		$response_headers     = $response_headers_obj->getAll();

		// if content-type is "text/html" it could be a directory listing.
		if ( ! empty( $response_headers['content-type'] ) && $this->is_content_type_for_multiple_files( Helper::get_content_type_from_string( $response_headers['content-type'] ), $this->get_url() ) ) {
			$instance = $this;

			// show deprecated hint for old hook.
			$results = apply_filters_deprecated( 'eml_filter_url_response', array( array(), $this->get_url(), $instance ), '5.0.0', 'efml_filter_url_response' );

			/**
			 * Filter the URL with custom import methods.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array<int,array<string,mixed>> $array Result list with infos.
			 * @param string $url The URL to import.
			 * @param Http $instance The actual protocol object.
			 */
			$results = apply_filters( 'efml_filter_url_response', $results, $this->get_url(), $instance );
			if ( ! empty( $results ) ) {
				// bail if URL is already in media library.
				if ( $this->check_for_duplicate( $this->get_url() ) ) {
					Log::get_instance()->create( __( 'The specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );

					// return empty array to prevent import of this URL.
					return array();
				}

				// return the result as array for import this as single URL.
				if ( isset( $results['title'] ) ) { // @phpstan-ignore isset.offset
					return array( $results );
				}

				// return the result as list of files.
				return $results; // @phpstan-ignore return.type
			}

			// show deprecated hint for old hook.
			do_action_deprecated( 'eml_http_directory_import_start', array( $this->get_url() ), '5.0.0', 'efml_http_directory_import_start' );

			/**
			 * Run action on beginning of presumed directory import.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 */
			do_action( 'efml_http_directory_import_start', $this->get_url() );

			// get WP Filesystem-handler.
			$wp_filesystem = Helper::get_wp_filesystem();

			// get temp file.
			$tmp_file = $this->get_temp_file( $this->get_url(), $wp_filesystem );

			// bail if tmp file could not be loaded.
			if ( ! is_string( $tmp_file ) ) {
				return array();
			}

			// get the content.
			$content = $wp_filesystem->get_contents( $tmp_file );

			// delete the temporary file.
			$this->cleanup_temp_file( $tmp_file );

			// bail if saving has been failed.
			if ( ! $content ) {
				Log::get_instance()->create( __( 'The presumed directory URL could not be loaded.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );
				return array();
			}

			// get the URL.
			$url = $this->get_url();

			// show deprecated hint for old hook.
			$matches = apply_filters_deprecated( 'eml_http_directory_regex', array( array(), $content, $url ), '5.0.0', 'efml_http_directory_regex' );

			/**
			 * Filter the content with regex via HTTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param array<int,array<string>> $results The results.
			 * @param string $content The content to parse.
			 * @param string $url The used URL.
			 *
			 * @paaram string $url The URL used.
			 */
			$matches = apply_filters( 'efml_http_directory_regex', $matches, $content, $url );

			// bail if no matches where found.
			if ( empty( $matches ) || empty( $matches[1] ) ) {
				Log::get_instance()->create( __( 'The presumed directory URL does not contain any linked files.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );
				return array();
			}

			// show progress.
			/* translators: %1$s is replaced by a URL. */
			$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( __( 'Check files from presumed directory URL %1$s', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), count( $matches[1] ) ) : '';

			// show deprecated hint for old hook.
			do_action_deprecated( 'eml_http_directory_import_files', array( $this->get_url(), $matches[1] ), '5.0.0', 'efml_http_directory_import_files' );

			/**
			 * Run action if we have files to check via HTTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 * @param array<string> $matches List of matches (the URLs).
			 */
			do_action( 'efml_http_directory_import_files', $this->get_url(), $matches[1] );

			// loop through the matches.
			foreach ( $matches[1] as $url ) {
				// bail if URL is empty or just "/".
				if ( empty( $url ) || '/' === $url ) {
					// show progress.
					$progress ? $progress->tick() : '';

					// bail this file.
					continue;
				}

				// concat the URL if $url to not start with http.
				$file_url = $url;
				if ( false === str_starts_with( $url, 'http' ) ) {
					$file_url_parts = wp_parse_url( $this->get_url() . $url );
					if ( is_array( $file_url_parts ) && isset( $file_url_parts['host'], $file_url_parts['scheme'], $file_url_parts['path'] ) ) {
						$file_url = $file_url_parts['scheme'] . '://' . $file_url_parts['host'] . str_replace( '//', '/', $file_url_parts['path'] );
					}
				}

				// check if given file is a local file which exist in media library.
				if ( $this->is_local_file( $file_url ) ) {
					Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 2, Import::get_instance()->get_identifier() );

					// show progress.
					$progress ? $progress->tick() : '';

					// bail this file.
					continue;
				}

				// check for duplicate.
				if ( $this->check_for_duplicate( $file_url ) ) {
					Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $file_url ), 'error', 0, Import::get_instance()->get_identifier() );

					// show progress.
					$progress ? $progress->tick() : '';

					// bail this file.
					continue;
				}

				// show deprecated hint for old hook.
				do_action_deprecated( 'eml_http_directory_import_file_check', array( $file_url ), '5.0.0', 'efml_http_directory_import_file_check' );

				/**
				 * Run action just before the file check via HTTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 */
				do_action( 'efml_http_directory_import_file_check', $file_url );

				// get file data.
				$file = $this->get_url_info( $file_url );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail if no data resulted.
				if ( empty( $file ) ) {
					continue;
				}

				// show deprecated hint for old hook.
				do_action_deprecated( 'eml_http_directory_import_file_before_to_list', array( $file_url, $matches[1] ), '5.0.0', 'efml_http_directory_import_file_before_to_list' );

				/**
				 * Run action just before the file is added to the list via HTTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 * @param array $files List of files.
				 */
				do_action( 'efml_http_directory_import_file_before_to_list', $file_url, $matches[1] );

				// add the file with its data to the list.
				$files[] = $file;
			}

			// finish progress.
			$progress ? $progress->finish() : '';
		} else {
			// check if given file is a local file which exist in media library.
			if ( $this->is_local_file( $this->get_url() ) ) {
				Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 2, Import::get_instance()->get_identifier() );
				return array();
			}

			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() ) ) {
				Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), 'error', 0, Import::get_instance()->get_identifier() );
				return array();
			}

			// get file data.
			$file = $this->get_url_info( $this->get_url() );

			// bail if no data resulted.
			if ( empty( $file ) ) {
				return array();
			}

			// add the file with its data to the list.
			$files[] = $file;
		}

		$instance = $this;

		// show deprecated hint for old hook.
		$files = apply_filters_deprecated( 'eml_external_files_infos', array( $files, $instance ), '5.0.0', 'efml_external_files_infos' );

		/**
		 * Filter list of files during this import.
		 *
		 * @since 3.0.0 Available since 3.0.0
		 * @param array<int,array<string,mixed>> $files List of files.
		 * @param Protocol_Base $instance The import object.
		 */
		return apply_filters( 'efml_external_files_infos', $files, $instance );
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array<string,mixed>
	 */
	public function get_url_info( string $url ): array {
		if ( false === $this->check_url( $url ) ) {
			return array();
		}

		// bail if availability-check is not successful.
		if ( false === $this->check_availability( $url ) ) {
			return array();
		}

		// get http head response for URL.
		$response = $this->get_http_header( $url );

		// bail if response is empty.
		if ( empty( $response ) ) {
			return array();
		}

		// get header from response.
		$response_headers_obj = $response['http_response']->get_headers();
		$response_headers     = $response_headers_obj->getAll();

		// initialize basic array for file data.
		$results = array(
			'title'         => basename( $url ),
			'filesize'      => 0,
			'mime-type'     => '',
			'local'         => false,
			'url'           => $url,
			'last-modified' => '',
		);

		// set file size in result-array.
		if ( ! empty( $response_headers['content-length'] ) ) {
			$results['filesize'] = $response_headers['content-length'];
		}

		// set title from content-disposition.
		if ( ! empty( $response_headers['content-disposition'] ) ) {
			$results['title'] = $this->get_filename_from_disposition( (array) $response_headers['content-disposition'] );
		}

		// set content-type as mime-type in result-array.
		if ( ! empty( $response_headers['content-type'] ) ) {
			$results['mime-type'] = Helper::get_content_type_from_string( $response_headers['content-type'] );

			// set local to true, if requirements match.
			$results['local'] = $this->url_should_be_saved_local( $url, $results['mime-type'] );
		}

		// set last modified, if given.
		if ( ! empty( $response_headers['last-modified'] ) ) {
			$last_modified = strtotime( $response_headers['last-modified'] );
			if ( $last_modified ) {
				$results['last-modified'] = $last_modified;
			}
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// if file should be saved local, load the temp file.
		if ( $results['local'] ) {
			$results['tmp-file'] = $this->get_temp_file( $url, $wp_filesystem );
		}

		// show deprecated hint for old hook.
		$results = apply_filters_deprecated( 'eml_external_file_infos', array( $results, $url, $response_headers ), '5.0.0', 'efml_external_file_infos' );

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since        1.1.0 Available since 1.1.0
		 *
		 * @param array<string,mixed>  $results List of detected file settings.
		 * @param string $url     The requested external URL.
		 * @param array<string,mixed> $response_headers The response header.
		 */
		return apply_filters( 'efml_external_file_infos', $results, $url, $response_headers );
	}

	/**
	 * Get the filename from content-disposition-header.
	 *
	 * @source WordPress-Importer
	 *
	 * @param array<string> $disposition_header The disposition header-list.
	 *
	 * @return ?string
	 * @noinspection DuplicatedCode
	 * @noinspection PhpUnusedLocalVariableInspection
	 */
	private function get_filename_from_disposition( array $disposition_header ): ?string {
		// Get the filename.
		$filename = null;

		foreach ( $disposition_header as $value ) {
			$value = trim( $value );

			if ( ! str_contains( $value, ';' ) ) {
				continue;
			}

			list( $type, $attr_parts ) = explode( ';', $value, 2 );

			$attr_parts = explode( ';', $attr_parts );
			$attributes = array();

			foreach ( $attr_parts as $part ) {
				if ( ! str_contains( $part, '=' ) ) {
					continue;
				}

				list( $key, $value ) = explode( '=', $part, 2 );

				$attributes[ trim( $key ) ] = trim( $value );
			}

			if ( empty( $attributes['filename'] ) ) {
				continue;
			}

			$filename = trim( $attributes['filename'] );

			// Unquote quoted filename, but after trimming.
			if ( str_starts_with( $filename, '"' ) && str_ends_with( $filename, '"' ) ) {
				$filename = substr( $filename, 1, -1 );
			}
		}

		// return resulting filename.
		return $filename;
	}

	/**
	 * Return whether the file should be saved local (true) or not (false).
	 *
	 * Files from HTTP could be saved local if setting for it is enabled.
	 * If external file is not available via SSL but actual page is, save it local.
	 * If credentials are set, all files should be saved local.
	 *
	 * This should be used if external object does exist for the used URL.
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		// if credentials are set, file should be saved local.
		if ( $this->has_fields_with_credentials() ) {
			return true;
		}

		$url = $this->get_url();

		// show deprecated hint for old hook.
		$true = apply_filters_deprecated( 'eml_http_ssl', array( true, $url ), '5.0.0', 'efml_http_ssl' );

		/**
		 * Filter whether files should be forced to save local
		 * if URL is using SSL but the website not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $true Use false to disable this.
		 * @param string $url The URL to check.
		 */
		if ( is_ssl() && ! str_starts_with( $url, 'https://' ) && apply_filters( 'efml_http_ssl', $true, $url ) ) {
			return true;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// bail if object could not be loaded.
		if ( ! $external_file_obj instanceof \ExternalFilesInMediaLibrary\ExternalFiles\File ) {
			return false;
		}

		// if setting enables local, file should be saved local.
		$result = ! $external_file_obj->is_locally_saved() && $external_file_obj->get_file_type_obj()->is_proxy_enabled();

		// show deprecated hint for old hook.
		$result = apply_filters_deprecated( 'eml_http_save_local', array( $result, $url ), '5.0.0', 'efml_http_save_local' );

		/**
		 * Filter if an HTTP-file should be saved local or not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $result True if file should be saved local.
		 * @param string $url The used URL.
		 */
		return apply_filters( 'efml_http_save_local', $result, $url );
	}

	/**
	 * Check if given URL should be saved local.
	 *
	 * This should be used if external object does NOT exist for a URL.
	 *
	 * @param string $url       The URL.
	 * @param string $mime_type The mime-type.
	 *
	 * @return bool
	 */
	public function url_should_be_saved_local( string $url, string $mime_type ): bool {
		// if credentials are set, file should be saved local.
		if ( $this->has_fields_with_credentials() ) {
			return true;
		}

		// show deprecated hint for old hook.
		$true = apply_filters_deprecated( 'eml_http_ssl', array( true, $url ), '5.0.0', 'efml_http_ssl' );

		/**
		 * Filter whether files should be forced to save local
		 * if URL is using SSL but the website not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool   $true Use false to disable this.
		 * @param string $url  The URL to check.
		 */
		if ( is_ssl() && ! str_starts_with( $url, 'https://' ) && apply_filters( 'efml_http_ssl', $true, $url ) ) {
			return true;
		}

		// get file type object for this URL by its mime type.
		$file_type_obj = File_Types::get_instance()->get_type_object_by_mime_type( $mime_type, $url );

		// if setting enables local saving, file should be saved local.
		$result = $file_type_obj->is_local();

		// show deprecated hint for old hook.
		$result = apply_filters_deprecated( 'eml_http_save_local', array( $result, $url ), '5.0.0', 'efml_http_save_local' );

		/**
		 * Filter whether the HTTP-file should be saved local or not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool   $result True if file should be saved local.
		 * @param string $url    The used URL.
		 */
		return apply_filters( 'efml_http_save_local', $result, $url );
	}

	/**
	 * Create http header for each request.
	 *
	 * @return array<string,mixed>
	 */
	public function get_header_args(): array {
		// define basic header.
		$args = array(
			'timeout'     => get_option( 'eml_timeout' ),
			'httpversion' => '1.1',
			'redirection' => 0,
		);

		// add credentials if set.
		if ( $this->has_fields_with_credentials() ) {
			// get the fields.
			$fields = $this->get_fields();

			// set the header with the credentials for AuthBasic.
			$args['headers'] = array(
				'Authorization' => 'Basic ' . base64_encode( $fields['login']['value'] . ':' . $fields['password']['value'] ),
			);
		}

		$instance = $this;

		// show deprecated hint for old hook.
		$args = apply_filters_deprecated( 'eml_http_header_args', array( $args, $instance ), '5.0.0', 'efml_http_header_args' );

		/**
		 * Filter the resulting header.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,mixed> $args List of headers.
		 * @param HTTP $instance The protocol object.
		 */
		return apply_filters( 'efml_http_header_args', $args, $instance );
	}

	/**
	 * Set our own headers for download_url-usage.
	 *
	 * Custom solution for https://core.trac.wordpress.org/ticket/40153
	 *
	 * @param array<string,array<string,mixed>> $parsed_args The header arguments.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function set_download_url_header( array $parsed_args ): array {
		return array_merge( $parsed_args, $this->get_header_args() );
	}

	/**
	 * Return list of allowed http states.
	 *
	 * @param string $url The used URL.
	 *
	 * @return array<int,int>
	 */
	private function get_allowed_http_states( string $url ): array {
		$list = array( 200 );

		// show deprecated hint for old hook.
		$list = apply_filters_deprecated( 'eml_http_states', array( $list, $url ), '5.0.0', 'efml_http_states' );

		/**
		 * Filter the list of allowed http states.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<int,int> $list List of http states.
		 * @param string $url The requested URL.
		 */
		return apply_filters( 'efml_http_states', $list, $url );
	}

	/**
	 * Return whether the file using this protocol is available.
	 *
	 * This depends on the hosting, e.g. if necessary libraries are available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return function_exists( 'wp_remote_head' );
	}

	/**
	 * Check if given URL exist in local media library.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	private function is_local_file( string $url ): bool {
		// show deprecated hint for old hook.
		$false = apply_filters_deprecated( 'eml_locale_file_check', array( false, $url ), '5.0.0', 'efml_locale_file_check' );

		/**
		 * Filter to prevent locale file check.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param bool $false Must be true to prevent check.
		 * @param string $url The used URL.
		 */
		if ( apply_filters( 'efml_locale_file_check', $false, $url ) ) {
			return false;
		}

		$attachment_id = attachment_url_to_postid( $url );
		return 0 !== $attachment_id;
	}

	/**
	 * Return temp file for the given URL.
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
			// temp file could not be saved.
			Log::get_instance()->create( __( 'Creation of a temporary file for the requested URL was prevented by a setting.', 'external-files-in-media-library' ), $url, 'info', 2, Import::get_instance()->get_identifier() );

			// do nothing more.
			return false;
		}

		// download file as temporary file.
		add_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );
		$tmp_file = download_url( $url );
		remove_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );

		// bail if error occurred.
		if ( is_wp_error( $tmp_file ) ) {
			// show 404 hint.
			if ( ! empty( $tmp_file->errors['http_404'] ) ) {
				// temp file could not be saved.
				Log::get_instance()->create( __( 'Specified URL does not exist or is not accessible. Error occurred:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $tmp_file->errors ) . '</code>', $url, 'error', 0, Import::get_instance()->get_identifier() );

				// return empty array as we got not the file.
				return false;
			}

			// temp file could not be saved.
			/* translators: %1$s by the error in JSON-format. */
			Log::get_instance()->create( sprintf( __( 'Temp file could not be created because of the following error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_strip_all_tags( Helper::get_json( $tmp_file ) ) . '</code>' ), $url, 'error', 0, Import::get_instance()->get_identifier() );

			// return empty array as we got not the file.
			return false;
		}

		// return the temp file.
		return $tmp_file;
	}

	/**
	 * Return whether this object has credentials set on its fields.
	 *
	 * @return bool
	 */
	protected function has_fields_with_credentials(): bool {
		// get the fields.
		$fields = $this->get_fields();

		// return true if login and password for AuthBasic are set.
		return ! empty( $fields['login']['value'] ) && ! empty( $fields['password']['value'] );
	}
}
