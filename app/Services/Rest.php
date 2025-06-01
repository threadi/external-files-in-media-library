<?php
/**
 * File to handle the REST support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use JsonException;
use WP_Image_Editor_Imagick;

/**
 * Object to handle support for REST-based directory listing.
 */
class Rest extends Directory_Listing_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'rest';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'WordPress REST API';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Rest
	 */
	private static ?Rest $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {    }

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {    }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Rest
	 */
	public static function get_instance(): Rest {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->title = __( 'Get files from WordPress REST API', 'external-files-in-media-library' );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// use our own hooks to allow import of REST API media files.
		add_filter( 'eml_mime_type_for_multiple_files', array( $this, 'allow_json_response' ), 10, 3 );
		add_filter( 'eml_filter_url_response', array( $this, 'get_rest_api_files' ), 10, 3 );
	}

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		$directory_listing_objects[] = $this;
		return $directory_listing_objects;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * Hint: we have no directory in media library.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 * @throws \JsonException Could throw exception.
	 */
	public function get_directory_listing( string $directory ): array {
		// prepend directory with https:// if that is not given.
		if ( ! ( absint( stripos( $directory, 'http://' ) ) >= 0 || absint( stripos( $directory, 'https://' ) ) > 0 ) ) {
			$directory = 'https://' . $directory;
		}

		// append the directory with the default REST API URL, if not set.
		if ( ! str_ends_with( $directory, '/wp-json/wp/v2/media' ) ) {
			$directory = trailingslashit( $directory ) . '/wp-json/wp/v2/media';
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if the detected protocol handler is not HTTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Http ) {
			return array();
		}

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// disable the check for unsafe URLs.
		add_filter( 'eml_http_header_args', array( $this, 'disable_check_for_unsafe_urls' ) );

		// we use pagination to get really all files from the external media library.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);
		for ( $p = 1;$p < 100;$p++ ) {
			// extend the given URL.
			$url = add_query_arg(
				array(
					'page'     => $p,
					'per_page' => 100,
				),
				$directory
			);

			// request the external WordPress REST-API.
			$response = wp_remote_get( $url );

			// bail general if error occurred.
			if ( is_wp_error( $response ) ) {
				return array();
			}

			// get the HTTP-status.
			$http_status = wp_remote_retrieve_response_code( $response );

			// bail general if HTTP status is not 200 and not 400.
			if ( ! in_array( $http_status, array( 200, 400 ), true ) ) {
				return array();
			}

			// bail if response is 400 (means there a no more files => return the list).
			if ( 400 === $http_status ) {
				return $listing;
			}

			// get the content.
			$body = wp_remote_retrieve_body( $response );

			// decode the JSON.
			try {
				$files = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

				// bail if list is not an array.
				if ( ! is_array( $files ) ) {
					continue;
				}

				// add each file from response to the list of all files.
				foreach ( $files as $file ) {
					// bail if source_url is not set.
					if ( ! isset( $file['source_url'] ) ) {
						continue;
					}

					// define the thumb.
					$thumbnail = '';

					if ( Init::get_instance()->is_preview_enabled() ) {
						// get protocol handler for this external file.
						$protocol_handler = Protocols::get_instance()->get_protocol_object_for_url( $file['source_url'] );
						if ( $protocol_handler instanceof Protocols\Http ) {
							// get the tmp file for this file.
							$filename = $protocol_handler->get_temp_file( $protocol_handler->get_url(), Helper::get_wp_filesystem() );

							// bail if filename could not be read.
							if ( ! is_string( $filename ) ) {
								continue;
							}

							// get image editor object of the file to get a thumb of it.
							$editor = wp_get_image_editor( $filename );

							// get the thumb via image editor object.
							if ( $editor instanceof WP_Image_Editor_Imagick ) {
								// set size for the preview.
								$editor->resize( 32, 32 );

								// save the thumb.
								$results = $editor->save( $upload_dir . '/' . basename( $file['source_url'] ) );

								// add thumb to output if it does not result in an error.
								if ( ! is_wp_error( $results ) ) {
									$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
								}
							}
						}
					}

					// collect the entry.
					$entry                  = array(
						'title' => basename( $file['source_url'] ),
					);
					$entry['file']          = $file['source_url'];
					$entry['filesize']      = isset( $file['media_details']['filesize'] ) ? absint( $file['media_details']['filesize'] ) : 0;
					$entry['mime-type']     = $file['mime_type'];
					$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $file['type'] ) . '"></span>';
					$entry['last-modified'] = absint( strtotime( $file['modified'] ) );
					$entry['preview']       = $thumbnail;

					// add the entry to the list.
					$listing['files'][] = $entry;
				}
			} catch ( JsonException $e ) {
				continue;
			}
		}

		// set completed marker.
		$listing['complete'] = true;

		// return the resulting list.
		return $listing;
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_import_url( file.file, login, password, [], term );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'efml_import_url( actualDirectoryPath + "/wp-json/wp/v2/media/", login, password, [], config.term );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "rest", actualDirectoryPath, login, password, "" );',
					'label'  => __( 'Save active directory as directory archive', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Disable the check for unsafe URLs.
	 *
	 * @param array<string,mixed> $parsed_args List of args for URL request.
	 *
	 * @return array<string,mixed>
	 */
	public function disable_check_for_unsafe_urls( array $parsed_args ): array {
		$parsed_args['reject_unsafe_urls'] = false;
		return $parsed_args;
	}

	/**
	 * Return list of translations.
	 *
	 * @param array<string,mixed> $translations List of translations.
	 *
	 * @return array<string,mixed>
	 */
	public function get_translations( array $translations ): array {
		$translations['form_file'] = array(
			'title'       => __( 'Enter the WordPress-URL', 'external-files-in-media-library' ),
			'description' => __( 'Enter the URL of the WordPress project from which you want to integrate media files into your project via REST API.', 'external-files-in-media-library' ),
			'url'         => array(
				'label' => __( 'WordPress-URL', 'external-files-in-media-library' ),
			),
			'button'      => array(
				'label' => __( 'Use this URL', 'external-files-in-media-library' ),
			),
		);
		return $translations;
	}

	/**
	 * Return whether a given URL is a WordPress REST API-URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return bool
	 */
	private function is_rest_api_url( string $url ): bool {
		foreach ( array( '/wp-json/wp/v2/media' ) as $path ) {
			if ( ! str_contains( $url, $path ) ) {
				continue;
			}

			// return true on match.
			return true;
		}

		// return false on not match.
		return false;
	}

	/**
	 * Allow JSON response for REST API-URLs.
	 *
	 * @param bool   $response The result.
	 * @param string $mime_type The used mime type.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function allow_json_response( bool $response, string $mime_type, string $url ): bool {
		// bail if this is not a REST API-URL.
		if ( ! $this->is_rest_api_url( $url ) ) {
			return $response;
		}

		// bail if mime type is not JSON.
		if ( 'application/json' !== $mime_type ) {
			return false;
		}

		// return true to allow JSON.
		return true;
	}

	/**
	 * Return the list of files from given REST API-URL for import.
	 *
	 * @param array<int,array<string,mixed>> $results The result as array for file import.
	 * @param string                         $url     The used URL.
	 * @param Protocols\Http                 $http_obj The HTTP object.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_rest_api_files( array $results, string $url, Protocols\Http $http_obj ): array {
		// bail if this is not a REST API-URL.
		if ( ! $this->is_rest_api_url( $url ) ) {
			return $results;
		}

		// return the data of the files under this URL.
		return $this->get_rest_api_data( $url, $http_obj );
	}

	/**
	 * Return the list of files from given REST API-URL.
	 *
	 * @param string $directory The given URL.
	 * @param Http   $http_obj The HTTP object.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_rest_api_data( string $directory, Protocols\Http $http_obj ): array {
		// collect the files.
		$file_list = array();

		// loop through the directory until no more files come back.
		for ( $p = 1; $p < 100; $p++ ) {
			// extend the given URL.
			$url = add_query_arg(
				array(
					'page'     => $p,
					'per_page' => 100,
				),
				$directory
			);

			// request the external WordPress REST-API.
			$response = wp_remote_get( $url );

			// bail general if error occurred.
			if ( is_wp_error( $response ) ) {
				return array();
			}

			// get the HTTP-status.
			$http_status = wp_remote_retrieve_response_code( $response );

			// bail general if HTTP status is not 200 and not 400.
			if ( ! in_array( $http_status, array( 200, 400 ), true ) ) {
				return array();
			}

			// bail if response is 400 (means there a no more files => return the list).
			if ( 400 === $http_status ) {
				return $file_list;
			}

			// get the content.
			$body = wp_remote_retrieve_body( $response );

			// decode the JSON.
			try {
				$files = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );

				// bail if list is not an array.
				if ( ! is_array( $files ) ) {
					continue;
				}

				// add each file from response to the list of all files.
				foreach ( $files as $file ) {
					// bail if source_url is not set.
					if ( ! isset( $file['source_url'] ) ) {
						continue;
					}

					// get content type of this file.
					$mime_type = wp_check_filetype( $file['source_url'] );

					// bail if file is not allowed.
					if ( empty( $mime_type['type'] ) ) {
						continue;
					}

					// check whether to save this file local or let it extern.
					$local = $http_obj->url_should_be_saved_local( $url, $mime_type['type'] );

					// add this file to the list.
					$file_list[] = array(
						'title'     => basename( $file['source_url'] ),
						'filesize'  => isset( $file['media_details']['filesize'] ) ? absint( $file['media_details']['filesize'] ) : 0,
						'mime-type' => $mime_type['type'],
						'local'     => $local,
						'url'       => $file['source_url'],
						'tmp-file'  => $local ? $http_obj->get_temp_file( $url, Helper::get_wp_filesystem() ) : '',
						'last-modified' => absint( strtotime( $file['modified'] ) )
					);
				}
			} catch ( JsonException $e ) {
				continue;
			}
		}

		// return the resulting list of files.
		return $file_list;
	}
}
