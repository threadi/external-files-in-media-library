<?php
/**
 * This file contains a controller-object to handle external files operations.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Helper;
use threadi\eml\Model\external_file;
use threadi\eml\Model\log;
use WP_Query;

/**
 * Controller for external file-urls-tasks.
 *
 * @noinspection PhpUnused
 */
class External_Files {
	/**
	 * Instance of actual object.
	 *
	 * @var External_Files|null
	 */
	private static ?External_Files $instance = null;

	/**
	 * Log-object
	 *
	 * @var log
	 */
	private log $log;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
		// get log-object.
		$this->log = Log::get_instance();
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return External_Files
	 */
	public static function get_instance(): External_Files {
		if ( is_null( self::$instance ) ) {
			self::$instance = new External_Files();
		}

		return self::$instance;
	}

	/**
	 * Get all external files in media library as external_file-object-array.
	 *
	 * @return array
	 */
	public function get_files_in_media_library(): array {
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );
		if ( $result->post_count > 0 ) {
			$results = array();
			foreach ( $result->posts as $attachment_id ) {
				$external_file_obj = $this->get_file( $attachment_id );
				if ( $external_file_obj ) {
					$results[] = $external_file_obj;
				}
			}
			return $results;
		}
		return array();
	}

	/**
	 * Return allowed content types.
	 *
	 * @return array
	 */
	public function get_allowed_mime_types(): array {
		$list = get_option( 'eml_allowed_mime_types', array() );
		if ( ! is_array( $list ) ) {
			return array();
		}
		return $list;
	}

	/**
	 * Add the given file-url in media library as external-file-object.
	 *
	 * @param string $url   The URL to add.
	 *
	 * @return bool true if URL is added successfully.
	 */
	public function add_file( string $url ): bool {
		/**
		 * Do nothing if file-check is not successful.
		 */
		if ( false === $this->check_url( $url ) ) {
			return false;
		}

		/**
		 * Do nothing if availability-check is not successful.
		 */
		if ( false === $this->check_availability( $url ) ) {
			return false;
		}

		/**
		 * Get user the attachment would be assigned to.
		 */
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			// get user from setting.
			$user_id = absint( get_option( 'eml_user_assign', 0 ) );

			// check if user exists.
			$user_obj = get_user_by( 'ID', $user_id );

			// Fallback: search for an administrator.
			if ( false === $user_obj ) {
				$user_id = helper::get_first_administrator_user();
			}
		}

		/**
		 * Get file information via http-header.
		 */
		$file_data = $this->get_external_file_infos( $url );

		/**
		 * Get file-title.
		 */
		$title = ! empty( $file_data['title'] ) ? $file_data['title'] : '';
		if ( empty( $title ) ) {
			$url_info = wp_parse_url( $url );
			if ( ! empty( $url_info ) ) {
				// get all possible mime-types our plugin supports.
				$mime_types = $this->get_possible_mime_types();

				// set title as filename.
				$title = str_replace( '/', '', $url_info['path'] );

				// add file extension if we support the mime-type.
				if ( ! empty( $mime_types[ $file_data['mime-type'] ] ) ) {
					$title .= '.' . $mime_types[ $file_data['mime-type'] ]['ext'];
				}
			}
		}

		/**
		 * Prepare attachment-post-settings.
		 */
		$post_array = array(
			'post_author' => $user_id,
		);

		/**
		 * If mime-type is an image, import it via sideload if this modus is enabled in plugin-settings.
		 */
		if ( 'local' === get_option( 'eml_images_mode', 'external' ) && $this->is_image_by_mime_type( $file_data['mime-type'] ) ) {

			// import file as image via WP-own functions.
			$array = array(
				'name'     => $title,
				'type'     => $file_data['mime-type'],
				'tmp_name' => $file_data['tmp-file'],
				'error'    => 0,
				'size'     => $file_data['filesize'],
			);

			$attachment_id = media_handle_sideload( $array, 0, null, $post_array );
			if ( ! is_wp_error( $attachment_id ) ) {
				$file_data['local'] = true;
			}
		} else {
			/**
			 * For all other files: simply create the attachment.
			 */
			$attachment_id = wp_insert_attachment( $post_array, $url );
		}

		if ( ! is_wp_error( $attachment_id ) && absint( $attachment_id ) > 0 ) {
			// get external file object. to update its settings.
			$external_file_obj = $this->get_file( $attachment_id );

			if ( $external_file_obj ) {
				// mark this attachment as one of our own plugin.
				$external_file_obj->set_url( $url );

				// set title.
				$external_file_obj->set_title( $title );

				// set mime-type.
				$external_file_obj->set_mime_type( $file_data['mime-type'] );

				// set availability-status (true for 'is available', false if not).
				$external_file_obj->set_availability( true );

				// set filesize.
				$external_file_obj->set_filesize( $file_data['filesize'] );

				// mark if this file is an external file locally saved.
				$external_file_obj->set_is_local_saved( $file_data['local'] );

				// set meta-data for images if modus is enabled for this.
				if ( 'external' === get_option( 'eml_images_mode', 'external' ) && $this->is_image_by_mime_type( $file_data['mime-type'] ) ) {
					if ( ! empty( $file_data['tmp-file'] ) ) {
						$image_meta = wp_create_image_subsizes( $file_data['tmp-file'], $attachment_id );

						// set file to our url.
						$image_meta['file'] = $url;

						// save the resulting image-data.
						wp_update_attachment_metadata( $attachment_id, $image_meta );
					}
				}

				// return true as the file has been created successfully.
				/* translators: %1$s will be replaced by the file-URL */
				$this->log->create( sprintf( __( 'URL %1$s successfully added in media library.', 'external-files-in-media-library' ), $url ), $url, 'success', 0 );

				return true;
			}
		}

		if ( is_wp_error( $attachment_id ) ) {
			/* translators: %1$s will be replaced by the file-URL, %2$s will be replaced by a WP-error-message */
			$this->log->create( sprintf( __( 'URL %1$s could not be saved because of this error: %2$s', 'external-files-in-media-library' ), $url, $attachment_id->errors['upload_error'][0] ), $url, 'error', 0 );        }

		// return false in case of errors.
		return false;
	}

	/**
	 * Check the given file-url regarding its string.
	 *
	 * Return true if file-url is ok.
	 * Return false if file-url is not ok
	 *
	 * @param string $url   The URL to check.
	 *
	 * @return bool
	 */
	private function check_url( string $url ): bool {
		// given url starts not with http.
		if ( ! str_starts_with( $url, 'http' ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given string %s is not a valid url starting with http.', 'external-files-in-media-library' ), $url ), $url, 'error', 0 );
			return false;
		}

		// given string is not an url.
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given string %s is not a valid url.', 'external-files-in-media-library' ), $url ), $url, 'error', 0 );
			return false;
		}

		// check for duplicate.
		$query   = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EML_POST_META_URL,
					'value'   => $url,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$results = new WP_Query( $query );
		if ( $results->post_count > 0 ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given url %s already exist in media library.', 'external-files-in-media-library' ), $url ), $url, 'error', 0 );
			return false;
		}

		// all ok with the url.
		return true;
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
		$args     = array(
			'timeout'     => 30,
			'httpversion' => '1.1',
			'redirection' => 0,
		);
		$response = wp_remote_head( $url, $args );

		// request resulted in error.
		if ( is_wp_error( $response ) || empty( $response ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given URL %s is not available.', 'external-files-in-media-library' ), $url ), $url, 'error', 0 );
			return false;
		}

		// file-url returns not with http-status 200.
		if ( $response['http_response']->get_status() !== 200 ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given URL %1$s response with http-status %2$d.', 'external-files-in-media-library' ), $url, $response['http_response']->get_status() ), $url, 'error', 0 );
			return false;
		}

		// request does not have a content-type header.
		$response_headers_obj = $response['http_response']->get_headers();
		if ( false === $response_headers_obj->offsetExists( 'content-type' ) ) {
			/* translators: %1$s will be replaced by the file-URL */
			$this->log->create( sprintf( __( 'Given URL %s response without Content-type.', 'external-files-in-media-library' ), $url ), $url, 'error', 0 );
			return false;
		}

		// request does not have a valid content-type.
		$response_headers = $response_headers_obj->getAll();
		if ( false === in_array( $response_headers['content-type'], $this->get_allowed_mime_types(), true ) ) {
			/* translators: %1$s will be replaced by the file-URL, %2$s will be replaced by its Mime-Type */
			$this->log->create( sprintf( __( 'Given URL %1$s response with a not allowed mime-type %2$s.', 'external-files-in-media-library' ), $url, $response_headers['content-type'] ), $url, 'error', 0 );
			return false;
		}

		// file is available.
		/* translators: %1$s will be replaced by the url of the file. */
		$this->log->create( sprintf( __( 'Given URL %1$s is available.', 'external-files-in-media-library' ), $url ), $url, 'success', 2 );
		return true;
	}

	/**
	 * Log deletion of external urls in media library.
	 *
	 * @param int $attachment_id  The attachment_id which will be deleted.
	 *
	 * @return void
	 */
	public function log_url_deletion( int $attachment_id ): void {
		// get the external file object.
		$external_file = $this->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file || false === $external_file->is_valid() ) {
			return;
		}

		// log deletion.
		/* translators: %1$s will be replaced by the file-URL */
		Log::get_instance()->create( sprintf( __( 'URL %1$s has been deleted from media library.', 'external-files-in-media-library' ), $external_file->get_url() ), $external_file->get_url(), 'success', 1 );
	}

	/**
	 * Return external_file object of single attachment by given ID without checking its availability.
	 *
	 * @param int $attachment_id    The attachment_id where we want to call the External_File-object.
	 * @return false|External_File
	 */
	public function get_file( int $attachment_id ): false|External_File {
		if ( false !== is_attachment( $attachment_id ) ) {
			return false;
		}
		return new External_File( $attachment_id );
	}

	/**
	 * Delete the given external-file-object with all its data from media library.
	 *
	 * @param External_File $external_file_obj  The External_File which will be deleted.
	 *
	 * @return void
	 */
	public function delete_file( External_File $external_file_obj ): void {
		wp_delete_attachment( $external_file_obj->get_id(), true );
	}

	/**
	 * Get possible mime-types.
	 *
	 * These are the mime-types this plugin supports. Not the enabled mime-types!
	 *
	 * We do not use @get_allowed_mime_types() as there might be much more mime-types as our plugin
	 * could support.
	 *
	 * @return array
	 */
	public function get_possible_mime_types(): array {
		return apply_filters(
			'eml_supported_mime_types',
			array(
				'image/gif'       => array(
					'label' => __( 'GIF', 'external-files-in-media-library' ),
					'ext'   => 'gif',
				),
				'image/jpeg'      => array(
					'label' => __( 'JPG/JPEG', 'external-files-in-media-library' ),
					'ext'   => 'jpg',
				),
				'image/png'       => array(
					'label' => __( 'PNG', 'external-files-in-media-library' ),
					'ext'   => 'png',
				),
				'image/webp'      => array(
					'label' => __( 'WEBP', 'external-files-in-media-library' ),
					'ext'   => 'webp',
				),
				'image/svg+xml'   => array(
					'label' => __( 'SVG', 'external-files-in-media-library' ),
					'ext'   => 'svg',
				),
				'application/pdf' => array(
					'label' => __( 'PDF', 'external-files-in-media-library' ),
					'ext'   => 'pdf',
				),
				'application/zip' => array(
					'label' => __( 'ZIP', 'external-files-in-media-library' ),
					'ext'   => 'zip',
				),
				'video/mp4'       => array(
					'label' => __( 'MP4 Video', 'external-files-in-media-library' ),
					'ext'   => 'mp4',
				),
			)
		);
	}

	/**
	 * Check all external files regarding their availability.
	 *
	 * @return void
	 */
	public function check_files(): void {
		$files = $this->get_files_in_media_library();
		foreach ( $files as $external_file_obj ) {
			$external_file_obj->set_availability( $this->check_availability( $external_file_obj->get_url() ) );
		}
	}

	/**
	 * Check the availability of a given file-url via http(s) incl. check of its content-type.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array List of file-infos.
	 */
	public function get_external_file_infos( string $url ): array {
		// initialize return array.
		$results = array(
			'filesize'  => 0,
			'mime-type' => '',
			'local'     => false,
		);

		// get the header-data of this url.
		$args     = array(
			'timeout'     => 30,
			'httpversion' => '1.1',
			'redirection' => 0,
		);
		$response = wp_remote_head( $url, $args );

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
			$results['mime-type'] = $response_headers['content-type'];
		}

		// download file as temporary file for further analyses.
		$results['tmp-file'] = download_url( $url );

		// return resulting array.
		return $results;
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
	 * Return true if the given mime_type is an image-mime-type.
	 *
	 * @param string $mime_type The mime-type to check.
	 *
	 * @return bool
	 */
	public function is_image_by_mime_type( $mime_type ): bool {
		return str_starts_with( $mime_type, 'image/' );
	}

	/**
	 * Get all imported external files.
	 *
	 * @return array
	 */
	public function get_imported_external_files(): array {
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => EML_POST_META_URL,
					'compare' => 'EXISTS',
				),
				array(
					'key'   => EML_POST_IMPORT_MARKER,
					'value' => 1,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );
		if ( $result->post_count > 0 ) {
			$results = array();
			foreach ( $result->posts as $attachment_id ) {
				$external_file_obj = $this->get_file( $attachment_id );
				if ( $external_file_obj ) {
					$results[] = $external_file_obj;
				}
			}
			return $results;
		}
		return array();
	}

	/**
	 * Get file-object by URL.
	 *
	 * @param string $url The file-url we search.
	 *
	 * @return bool|External_File
	 */
	public function get_file_by_url( string $url ): ?External_File {
		if ( ! empty( $url ) ) {
			$query  = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_query'     => array(
					array(
						'key'     => EML_POST_META_URL,
						'value'   => $url,
						'compare' => '=',
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);
			$result = new WP_Query( $query );
			if ( 1 === $result->post_count ) {
				$external_file_obj = $this->get_file( $result->posts[0] );
				if ( $external_file_obj->is_valid() ) {
					return $external_file_obj;
				}
			}
		}
		return false;
	}

	/**
	 * Get file-object by its title.
	 *
	 * @param string $title The file-url we search.
	 *
	 * @return bool|External_File
	 */
	public function get_file_by_title( string $title ): bool|External_File {
		if ( ! empty( $title ) ) {
			$query  = array(
				'post_title'     => $title,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_query'     => array(
					array(
						'key'     => EML_POST_META_URL,
						'compare' => 'EXISTS',
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);
			$result = new WP_Query( $query );
			if ( 1 === $result->post_count ) {
				$external_file_obj = $this->get_file( $result->posts[0] );
				if ( $external_file_obj->is_valid() ) {
					return $external_file_obj;
				}
			}
		}
		return false;
	}

	/**
	 * Return the cache-directory for proxied external files.
	 * Handles also the existence of the directory.
	 *
	 * @return string
	 */
	public function get_cache_directory(): string {
		// create string with path for directory.
		$path = trailingslashit( WP_CONTENT_DIR ) . 'cache/eml/';

		// create it if necessary.
		$this->create_cache_directory( $path );

		// return path.
		return $path;
	}

	/**
	 * Create cache directory.
	 *
	 * @param string $path The path to the cache directory.
	 * @return void
	 */
	private function create_cache_directory( string $path ): void {
		if ( ! file_exists( $path ) ) {
			if ( false === wp_mkdir_p( $path ) ) {
				$this->log->create( __( 'Error creating cache directory.', 'external-files-in-media-library' ), '', 'error', 0 );
			}
		}
	}

	/**
	 * Delete the cache directory.
	 *
	 * @return void
	 */
	public function delete_cache_directory(): void {
		Helper::delete_directory_recursively( $this->get_cache_directory() );
	}

	/**
	 * If file is deleted, delete also its proxy-cache, if set.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 * @return void
	 */
	public function delete_file_from_cache( int $attachment_id ): void {
		// get the external file object.
		$external_file = $this->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file || false === $external_file->is_valid() ) {
			return;
		}

		// call cache file deletion.
		$external_file->delete_cache();
	}
}
