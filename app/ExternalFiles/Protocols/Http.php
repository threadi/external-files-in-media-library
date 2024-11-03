<?php
/**
 * File which handles the http support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Queue;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle different protocols.
 */
class Http extends Protocol_Base {
	/**
	 * List of supported tcp protocols.
	 *
	 * @var array
	 */
	protected array $tcp_protocols = array(
		'http'  => 80,
		'https' => 443,
	);

	/**
	 * List of http head requests.
	 *
	 * @var array
	 */
	private array $http_heads = array();

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
		// given string is not an url.
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given string %s is not a valid url.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'error', 0 );
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
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'eml_check_url', $return, $url );
	}

	/**
	 * Check the availability of a given URL via http(s) incl. check of its content-type.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability( string $url ): bool {
		// check if URL is available.
		$response = wp_remote_head( $url, $this->get_header_args() );

		// request resulted in an error.
		if ( is_wp_error( $response ) || empty( $response ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %s is not available.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'error', 0 );
			return false;
		}

		// URL returns not compatible HTTP-state.
		if ( ! in_array( $response['http_response']->get_status(), $this->get_allowed_http_states( $url ), true ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s response with HTTP-status %2$d.', 'external-files-in-media-library' ), esc_url( $url ), $response['http_response']->get_status() ), esc_url( $url ), 'error', 0 );
			return false;
		}

		// request does not have a content-type header.
		$response_headers_obj = $response['http_response']->get_headers();
		$true                 = true;
		/**
		 * Filter for check if file has content-type given.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $true True if content type check should be run.
		 * @param string $url The used URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( false === $response_headers_obj->offsetExists( 'content-type' ) && apply_filters( 'eml_http_check_content_type_existence', $true, $url ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %s response without Content-type.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'error', 0 );
			return false;
		}

		// request does not have a valid content-type.
		$response_headers = $response_headers_obj->getAll();
		$true             = true;
		/**
		 * Filter for check of file content type during availability check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $true True if content type check should be run.
		 * @param string $url The used URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( ! empty( $response_headers['content-type'] && apply_filters( 'eml_http_check_content_type', $true, $url ) ) ) {
			if ( false === in_array( Helper::get_content_type_from_string( $response_headers['content-type'] ), Helper::get_allowed_mime_types(), true ) ) {
				/* translators: %1$s will be replaced by the file-URL, %2$s will be replaced by its Mime-Type */
				Log::get_instance()->create( sprintf( __( 'Given URL %1$s response with the not allowed mime-type %2$s.', 'external-files-in-media-library' ), esc_url( $url ), $response_headers['content-type'] ), esc_url( $url ), 'error', 0 );
				return false;
			}
		}

		$return = true;
		/**
		 * Filter the resulting for checking an external URL.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param bool $return The result of this check.
		 * @param string $url The requested external URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'eml_check_url_availability', $return, $url ) ) {
			// file is available.
			/* translators: %1$s will be replaced by the URL of the file. */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s is available.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'success', 2 );

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
	 * @return array
	 */
	private function get_http_head( string $url ): array {
		// bail if result is known.
		if ( ! empty( $this->http_heads[ $url ] ) ) {
			return $this->http_heads[ $url ];
		}

		// get the header-data of this url.
		$response = wp_remote_head( $url, $this->get_header_args() );

		// bail if response results in error.
		if ( is_wp_error( $response ) ) {
			return array();
		}

		// save in cache.
		$this->http_heads[ $url ] = $response;

		// return results.
		return $this->http_heads[ $url ];
	}

	/**
	 * Check the availability of a given URL via http(s) incl. check of its content-type.
	 *
	 * @return array List of files from the given URL with its infos.
	 */
	public function get_url_infos(): array {
		// initialize list of files.
		$files = array();

		// get the header-data of this url.
		$response = $this->get_http_head( $this->get_url() );

		// bail if response is empty.
		if ( empty( $response ) ) {
			return array();
		}

		// get header from response.
		$response_headers_obj = $response['http_response']->get_headers();
		$response_headers     = $response_headers_obj->getAll();

		// if content-type is "text/html" it could be a directory listing.
		if ( ! empty( $response_headers['content-type'] ) && Helper::get_content_type_from_string( $response_headers['content-type'] ) === 'text/html' ) {
			/**
			 * Filter the URL with custom import methods.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array $array Result list with infos.
			 * @param string $url The URL to import.
			 */
			$results = apply_filters( 'eml_filter_url_response', array(), $this->get_url() );
			if ( ! empty( $results ) ) {
				return array( $results );
			}

			/**
			 * Run action on beginning of presumed directory import.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 */
			do_action( 'eml_http_directory_import_start', $this->get_url() );

			// get WP Filesystem-handler.
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
			global $wp_filesystem;

			// save the content of the URL in a temporary file.
			add_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );
			$tmp_content_file = download_url( $this->get_url() );
			remove_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );

			// bail if temp file resulted in error.
			if ( is_wp_error( $tmp_content_file ) ) {
				/* translators: %1$s will be replaced by the file-URL, %2$s by the error in JSON-format */
				Log::get_instance()->create( sprintf( __( 'Temp file for %1$s could not be created because of the following error: <code>%2$s</code>', 'external-files-in-media-library' ), esc_url( $this->get_url() ), wp_strip_all_tags( wp_json_encode( $tmp_content_file ) ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			// get the content.
			$content = $wp_filesystem->get_contents( $tmp_content_file );

			// delete the temporary file.
			Files::get_instance()->cleanup_temp_file( $tmp_content_file );

			// bail if saving has been failed.
			if ( ! $content ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'The presumed directory URL %s could not be loaded.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			/**
			 * Filter the content with regex via HTTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param array $results The results.
			 * @param string $content The content to parse.
			 *
			 * @paaram string $url The URL used.
			 */
			$matches = apply_filters( 'eml_http_directory_regex', array(), $content, $this->get_url() );

			// bail if no matches where found.
			if ( empty( $matches ) || empty( $matches[1] ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'The presumed directory URL %s does not contain any linked files.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			// add files to list in queue mode.
			if ( $this->is_queue_mode() ) {
				Queue::get_instance()->add_urls( $matches[1], $this->get_login(), $this->get_password() );
				return array();
			}

			// show progress.
			/* translators: %1$s is replaced by a URL. */
			$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( sprintf( __( 'Check files from presumed directory URL %1$s', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), count( $matches[1] ) ) : '';

			/**
			 * Run action if we have files to check via HTTP-protocol.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $url   The URL to import.
			 * @param array $matches List of matches (the URLs).
			 */
			do_action( 'eml_http_directory_import_files', $this->get_url(), $matches[1] );

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
					if ( is_array( $file_url_parts ) ) {
						$file_url = $file_url_parts['scheme'] . '://' . $file_url_parts['host'] . str_replace( '//', '/', $file_url_parts['path'] );
					}
				}

				// check if given file is a local file which exist in media library.
				if ( $this->is_local_file( $file_url ) ) {
					/* translators: %1$s will be replaced by the file-URL */
					Log::get_instance()->create( sprintf( __( 'Given URL %s already exist in media library as local file.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 2 );

					// show progress.
					$progress ? $progress->tick() : '';

					// bail this file.
					continue;
				}

				// check for duplicate.
				if ( $this->check_for_duplicate( $file_url ) ) {
					/* translators: %1$s will be replaced by the file-URL */
					Log::get_instance()->create( sprintf( __( 'Given file %1$s already exist in media library.', 'external-files-in-media-library' ), esc_url( $file_url ) ), esc_url( $file_url ), 'error', 0 );

					// show progress.
					$progress ? $progress->tick() : '';

					// bail this file.
					continue;
				}

				/**
				 * Run action just before the file check via HTTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 */
				do_action( 'eml_http_directory_import_file_check', $file_url );

				// get file data.
				$file = $this->get_url_info( $file_url );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail if no data resulted.
				if ( empty( $file ) ) {
					continue;
				}

				/**
				 * Run action just before the file is added to the list via HTTP-protocol.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 *
				 * @param string $file_url   The URL to import.
				 * @param array $files List of files.
				 */
				do_action( 'eml_http_directory_import_file_before_to_list', $file_url, $matches[1] );

				// add the file with its data to the list.
				$files[] = $file;
			}

			// finish progress.
			$progress ? $progress->finish() : '';
		} else {
			// check if given file is a local file which exist in media library.
			if ( $this->is_local_file( $this->get_url() ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'Given URL %1$s already exist in media library as normal file.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 2 );
				return array();
			}

			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'Given URL %1$s already exist in media library as external file.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			// add file to list in queue mode.
			if ( $this->is_queue_mode() ) {
				Queue::get_instance()->add_urls( array( $this->get_url() ), $this->get_login(), $this->get_password() );
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

		// return resulting list of files.
		return $files;
	}

	/**
	 * Get infos from single given URL.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array
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
		$response = $this->get_http_head( $url );

		// bail if response is empty.
		if ( empty( $response ) ) {
			return array();
		}

		// get header from response.
		$response_headers_obj = $response['http_response']->get_headers();
		$response_headers     = $response_headers_obj->getAll();

		// initialize basic array for file data.
		$results = array(
			'title'     => basename( $url ),
			'filesize'  => 0,
			'mime-type' => '',
			'local'     => false,
			'url'       => $url,
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

		// download file as temporary file for further analyses.
		add_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );
		$results['tmp-file'] = download_url( $url );
		remove_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );

		// bail if error occurred.
		if ( is_wp_error( $results['tmp-file'] ) ) {
			// file is available.
			/* translators: %1$s will be replaced by the file-URL, %2$s by the error in JSON-format */
			Log::get_instance()->create( sprintf( __( 'Temp file for %1$s could not be created because of the following error: <code>%2$s</code>', 'external-files-in-media-library' ), esc_url( $this->get_url() ), wp_strip_all_tags( wp_json_encode( $results['tmp-file'] ) ) ), esc_url( $this->get_url() ), 'error', 0 );

			// return empty array as we got not the file.
			return array();
		}

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since        1.1.0 Available since 1.1.0
		 *
		 * @param array  $results List of detected file settings.
		 * @param string $url     The requested external URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'eml_external_file_infos', $results, $url );
	}

	/**
	 * Get the filename from content-disposition-header.
	 *
	 * @source WordPress-Importer
	 *
	 * @param array $disposition_header The disposition header-list.
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
		if ( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			return true;
		}

		$url  = $this->get_url();
		$true = true;

		/**
		 * Filter whether files should be forced to save local
		 * if URL is using SSL but the website not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $true Use false to disable this.
		 * @param string $url The URL to check.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( is_ssl() && ! str_starts_with( $url, 'https://' ) && apply_filters( 'eml_http_ssl', $true, $url ) ) {
			return true;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// bail if object could not be loaded.
		if ( ! $external_file_obj ) {
			return false;
		}

		// if setting enables local saving for images or videos, file should be saved local.
		$result = (
			( 'local' === get_option( 'eml_images_mode', 'external' ) && $external_file_obj->is_image() )
			|| ( 'local' === get_option( 'eml_video_mode', 'external' ) && $external_file_obj->is_video() )
		);
		/**
		 * Filter if a http-file should be saved local or not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $result True if file should be saved local.
		 * @param string $url The used URL.
		 */
		return apply_filters( 'eml_http_save_local', $result, $url );
	}

	/**
	 * Check if given URL should be saved local.
	 *
	 * This should be used if external object does NOT exist for a URL.
	 *
	 * @param string $url The URL.
	 * @param string $mime_type The mime-type.
	 *
	 * @return bool
	 */
	private function url_should_be_saved_local( string $url, string $mime_type ): bool {
		// if credentials are set, file should be saved local.
		if ( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			return true;
		}

		$true = true;
		/**
		 * Filter whether files should be forced to save local
		 * if URL is using SSL but the website not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $true Use false to disable this.
		 * @param string $url The URL to check.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( is_ssl() && ! str_starts_with( $url, 'https://' ) && apply_filters( 'eml_http_ssl', $true, $url ) ) {
			return true;
		}

		// if setting enables local saving for images, file should be saved local.
		$result = 'local' === get_option( 'eml_images_mode', 'external' ) && Helper::is_image_by_mime_type( $mime_type );
		/**
		 * Filter if a http-file should be saved local or not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $result True if file should be saved local.
		 * @param string $url The used URL.
		 */
		return apply_filters( 'eml_http_save_local', $result, $url );
	}

	/**
	 * Create http header for each request.
	 *
	 * @return array
	 */
	private function get_header_args(): array {
		// define basic header.
		$args = array(
			'timeout'     => get_option( 'eml_timeout' ),
			'httpversion' => '1.1',
			'redirection' => 0,
		);

		// add credentials if set.
		if ( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			if ( ! empty( $args['headers']['Authorization'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->get_login() . ':' . $this->get_password() );
		}

		/**
		 * Filter the resulting header.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $args List of headers.
		 */
		return apply_filters( 'eml_http_header_args', $args );
	}

	/**
	 * Set our own headers for download_url-usage.
	 *
	 * Custom solution for https://core.trac.wordpress.org/ticket/40153
	 *
	 * @param array $parsed_args The header arguments.
	 *
	 * @return array
	 */
	public function set_download_url_header( array $parsed_args ): array {
		return array_merge( $parsed_args, $this->get_header_args() );
	}

	/**
	 * Return list of allowed http states.
	 *
	 * @param string $url The used URL.
	 *
	 * @return array
	 */
	private function get_allowed_http_states( string $url ): array {
		$list = array( 200 );

		/**
		 * Filter the list of allowed http states.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of http states.
		 */
		return apply_filters( 'eml_http_states', $list, $url );
	}

	/**
	 * Return whether this protocol could be used.
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
		$attachment_id = attachment_url_to_postid( $url );
		return 0 !== $attachment_id;
	}
}
