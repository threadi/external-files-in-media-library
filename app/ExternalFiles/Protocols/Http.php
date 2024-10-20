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
	 * Check the given file-url regarding its string.
	 *
	 * Return true if file-url is ok.
	 * Return false if file-url is not ok
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
	 * Check the availability of a given file-url via http(s) incl. check of its content-type.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability( string $url ): bool {
		// check if url is available.
		$response = wp_remote_head( $url, $this->get_header_args() );

		// request resulted in an error.
		if ( is_wp_error( $response ) || empty( $response ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %s is not available.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'error', 0 );
			return false;
		}

		// file-url returns not compatible http-state.
		if ( ! in_array( $response['http_response']->get_status(), $this->get_allowed_http_states( $url ), true ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s response with http-status %2$d.', 'external-files-in-media-library' ), esc_url( $url ), $response['http_response']->get_status() ), esc_url( $url ), 'error', 0 );
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
			/* translators: %1$s will be replaced by the url of the file. */
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
	 * Check the availability of a given file-url via http(s) incl. check of its content-type.
	 *
	 * @return array List of files from the given URL with its infos.
	 */
	public function get_external_infos(): array {
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

		// if content-type is "text/html" it must be a directory listing.
		if ( ! empty( $response_headers['content-type'] ) && Helper::get_content_type_from_string( $response_headers['content-type'] ) === 'text/html' ) {
			// get WP Filesystem-handler.
			require_once ABSPATH . '/wp-admin/includes/file.php';
			\WP_Filesystem();
			global $wp_filesystem;

			// get all files from response and get info to each one.
			add_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );
			$tmp_content_file = download_url( $this->get_url() );
			remove_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );

			// get the content.
			$content = $wp_filesystem->get_contents( $tmp_content_file );

			// bail if saving failed.
			if ( ! $content ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'Given directory url %s could not be loaded.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			// parse all links to get their URLs.
			preg_match_all( "<a href=\x22(.+?)\x22>", $content, $matches );

			// bail if no matches where found.
			if ( empty( $matches ) || empty( $matches[1] ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'Given directory url %s does not contain any linked files.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
				return array();
			}

			// loop through the matches.
			foreach ( $matches[1] as $url ) {
				// bail if url is empty or just "/".
				if ( empty( $url ) || '/' === $url ) {
					continue;
				}

				// concat the URL.
				$file_url = path_join( $this->get_url(), $url );

				// check for duplicate.
				if ( $this->check_for_duplicate( $file_url ) ) {
					/* translators: %1$s will be replaced by the file-URL */
					Log::get_instance()->create( sprintf( __( 'Given file %1$s already exist in media library.', 'external-files-in-media-library' ), esc_url( $file_url ) ), esc_url( $file_url ), 'error', 0 );
					continue;
				}

				// get file data.
				$file = $this->get_url_info( $file_url );

				// bail if no data resulted.
				if ( empty( $file ) ) {
					continue;
				}

				// add the file with its data to the list.
				$files[] = $file;
			}
		} else {
			// check for duplicate.
			if ( $this->check_for_duplicate( $this->get_url() ) ) {
				/* translators: %1$s will be replaced by the file-URL */
				Log::get_instance()->create( sprintf( __( 'Given url %s already exist in media library.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
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
			/* translators: %1$s will be replaced by the url of the file. */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s could not be downloaded.', 'external-files-in-media-library' ), esc_url( $url ) ), esc_url( $url ), 'success', 2 );
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

		// if URL is not SSL but project is, file should be saved local.
		if ( is_ssl() && ! str_starts_with( $this->get_url(), 'https://' ) ) {
			return true;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file_by_url( $this->get_url() );

		// if setting enables local saving for images, file should be saved local.
		$result = 'local' === get_option( 'eml_images_mode', 'external' ) && $external_file_obj->is_image();
		/**
		 * Filter if a http-file should be saved local or not.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $result True if file should be saved local.
		 * @param string $url The used URL.
		 */
		return apply_filters( 'eml_http_save_local', $result, $this->get_url() );
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

		// if URL is not SSL but project is, file should be saved local.
		if ( is_ssl() && ! str_starts_with( $url, 'https://' ) ) {
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
			'timeout'     => 30,
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
}
