<?php
/**
 * File which handles the http support.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller\Protocols;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Controller\Protocol_Base;
use threadi\eml\Helper;
use threadi\eml\Model\Log;

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
		'http',
		'https'
	);

	/**
	 * List of file infos.
	 *
	 * @var array
	 */
	private array $file_infos = array();

	/**
	 * Check the given file-url regarding its string.
	 *
	 * Return true if file-url is ok.
	 * Return false if file-url is not ok
	 *
	 * @return bool
	 */
	public function check_url(): bool {
		// given url starts not with http.
		if ( ! str_starts_with( $this->get_url(), 'http' ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given string %s is not a valid url starting with http.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

		// given string is not an url.
		if ( false === filter_var( $this->get_url(), FILTER_VALIDATE_URL ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given string %s is not a valid url.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

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
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'eml_check_url', $return, esc_url( $this->get_url() ) );
	}

	/**
	 * Check the availability of a given file-url via http(s) incl. check of its content-type.
	 *
	 * @return bool true if file is available, false if not.
	 */
	public function check_availability(): bool {
		// check if url is available.
		$response = wp_remote_head( $this->get_url(), $this->get_header_args() );

		// request resulted in error.
		if ( is_wp_error( $response ) || empty( $response ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %s is not available.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

		// file-url returns not with http-status 200.
		if ( $response['http_response']->get_status() !== 200 ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s response with http-status %2$d.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), $response['http_response']->get_status() ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

		// request does not have a content-type header.
		$response_headers_obj = $response['http_response']->get_headers();
		if ( false === $response_headers_obj->offsetExists( 'content-type' ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			Log::get_instance()->create( sprintf( __( 'Given URL %s response without Content-type.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'error', 0 );
			return false;
		}

		// request does not have a valid content-type.
		$response_headers = $response_headers_obj->getAll();
		if ( ! empty( $response_headers['content-type'] ) ) {
			if ( false === in_array( Helper::get_content_type_from_string( $response_headers['content-type'] ), Helper::get_allowed_mime_types(), true ) ) {
				/* translators: %1$s will be replaced by the file-URL, %2$s will be replaced by its Mime-Type */
				Log::get_instance()->create( sprintf( __( 'Given URL %1$s response with a not allowed mime-type %2$s.', 'external-files-in-media-library' ), esc_url( $this->get_url() ), $response_headers['content-type'] ), esc_url( $this->get_url() ), 'error', 0 );
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
		if ( apply_filters( 'eml_check_url_availability', $return, $this->get_url() ) ) {
			// file is available.
			/* translators: %1$s will be replaced by the url of the file. */
			Log::get_instance()->create( sprintf( __( 'Given URL %1$s is available.', 'external-files-in-media-library' ), esc_url( $this->get_url() ) ), esc_url( $this->get_url() ), 'success', 2 );

			return true;
		}

		return false;
	}

	/**
	 * Check the availability of a given file-url via http(s) incl. check of its content-type.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_file_infos(): array {
		// initialize return array.
		$results = array(
			'title' => '',
			'filesize'  => 0,
			'mime-type' => '',
			'local'     => false,
		);

		// get the header-data of this url.
		$response = wp_remote_head( $this->get_url(), $this->get_header_args() );

		// get header from response.
		$response_headers_obj = $response['http_response']->get_headers();
		$response_headers     = $response_headers_obj->getAll();

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
		}

		// download file as temporary file for further analyses.
		add_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );
		$results['tmp-file'] = download_url( $this->get_url() );
		remove_filter( 'http_request_args', array( $this, 'set_download_url_header' ) );

		// save results in object.
		$this->file_infos = $results;

		/**
		 * Filter the data of a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param array $results List of detected file settings.
		 * @param string $url The requested external URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		return apply_filters( 'eml_external_file_infos', $results, $this->get_url() );
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
	 * If credentials are set, all files should be saved local
	 *
	 * @return bool
	 */
	public function should_be_saved_local(): bool {
		// if credentials are set, file should be saved local.
		if( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			return true;
		}

		// otherwise use the settings.
		return 'local' === get_option( 'eml_images_mode', 'external' ) && ! empty( $this->file_infos ) && Helper::is_image_by_mime_type( $this->file_infos['mime-type'] );
	}

	/**
	 * Create http header for each request.
	 *
	 * @return array
	 */
	private function get_header_args(): array {
		// define basic header.
		$args     = array(
			'timeout'     => 30,
			'httpversion' => '1.1',
			'redirection' => 0,
		);

		// add credentials if set.
		if( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			if( ! empty( $args['headers']['Authorization'] ) ) {
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
}
