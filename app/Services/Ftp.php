<?php
/**
 * File to handle the FTP support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use WP_Error;
use WP_Filesystem_FTPext;
use WP_Image_Editor;

/**
 * Object to handle support for FTP-based directory listing.
 */
class Ftp extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'ftp';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'FTP';

	/**
	 * Marker if login is required.
	 *
	 * @var bool
	 */
	protected bool $requires_login = true;

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_ftp';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Ftp
	 */
	private static ?Ftp $instance = null;

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
	 * @return Ftp
	 */
	public static function get_instance(): Ftp {
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
		// use parent initialization.
		parent::init();

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from a FTP server', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_service_ftp_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 4 );
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// prepend directory with ftp:// if that is not given.
		if ( ! ( absint( stripos( $directory, 'ftp://' ) ) >= 0 || absint( stripos( $directory, 'ftps://' ) ) > 0 ) ) {
			$directory = 'ftp://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if the detected protocol handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			return array();
		}

		// set the login.
		$protocol_handler_obj->set_login( $this->get_login() );
		$protocol_handler_obj->set_password( $this->get_password() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $directory );

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			return array();
		}

		// get the staring directory.
		$parse_url = wp_parse_url( $directory );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			return array();
		}

		// set parent dir.
		$parent_dir = '/';

		// bail if path could not be read.
		if ( isset( $parse_url['path'] ) ) {
			// get parent_dir path.
			$parent_dir = trailingslashit( $parse_url['path'] );
		}

		// get list of directory.
		$directory_list = $ftp_connection->dirlist( $parent_dir );

		// collect the content of this directory.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// bail if list is empty.
		if ( empty( $directory_list ) ) {
			return $listing;
		}

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// loop through the list, add each file to the list and loop through each subdirectory.
		foreach ( $directory_list as $item_name => $item_settings ) {
			// get path for item.
			$path      = $parse_url['scheme'] . '://' . $parse_url['host'] . $parent_dir . $item_name;
			$path_only = $parent_dir . $item_name;

			$false  = false;
			$is_dir = $ftp_connection->is_dir( $path_only );
			/**
			 * Filter whether given FTP file should be hidden.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param string $path Absolute path to the given file.
			 * @param string $directory The requested directory.
			 * @param bool $is_dir True if this entry is a directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'efml_service_ftp_hide_file', $false, $path, $directory, $is_dir ) ) {
				continue;
			}

			// collect the entry.
			$entry = array(
				'title' => $item_name,
			);

			// if item is a directory, add it to the list.
			if ( $is_dir ) {
				$listing['dirs'][ trailingslashit( trailingslashit( $directory ) . $item_name ) ] = $entry;
			} else {
				// get content type of this file.
				$mime_type = wp_check_filetype( $path );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// define the thumb.
				$thumbnail = '';

				// get thumbnail, if set and enabled.
				if ( str_contains( $mime_type['type'], 'image/' ) && Init::get_instance()->is_preview_enabled() ) {
					// get protocol handler for this external file.
					$protocol_handler = Protocols::get_instance()->get_protocol_object_for_url( trailingslashit( $directory ) . $item_name );
					if ( $protocol_handler instanceof Protocols\Ftp ) {
						// get the tmp file for this file.
						$filename = $protocol_handler->get_temp_file( $protocol_handler->get_url(), $ftp_connection );

						// check mime if file could be saved.
						if ( is_string( $filename ) ) {
							// get the real image mime.
							$image_mime = wp_get_image_mime( $filename );

							// bail if filename could not be read and if real mime type is not an image.
							if ( is_string( $image_mime ) && str_contains( $image_mime, 'image/' ) ) {
								// get image editor object of the file to get a thumb of it.
								$editor = wp_get_image_editor( $filename );

								// get the thumb via image editor object.
								if ( $editor instanceof WP_Image_Editor ) {
									// set size for the preview.
									$editor->resize( 32, 32 );

									// save the thumb.
									$results = $editor->save( $upload_dir . '/' . basename( $item_name ) );

									// add thumb to output if it does not result in an error.
									if ( ! is_wp_error( $results ) ) {
										$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="" class="filepreview">';
									}
								}
							}
						}
					}
				}

				// add settings for entry.
				$entry['file']          = $path;
				$entry['filesize']      = absint( $item_settings['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $item_settings['time'] ) ) );
				$entry['preview']       = $thumbnail;

				// add the entry to the list.
				$listing['files'][] = $entry;
			}
		}

		// return resulting list.
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "login": login, "password": password, "term": term } );',
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
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "login": login, "password": password, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, login, password, "", config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Check if login with given credentials is valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if credentials are missing.
		if ( empty( $this->get_login() ) || empty( $this->get_password() ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'No credentials set for this FTP connection!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// prepend directory with ftp:// if that is not given.
		if ( ! ( absint( stripos( $directory, 'ftp://' ) ) >= 0 || absint( stripos( $directory, 'ftps://' ) ) > 0 ) ) {
			$directory = 'ftp://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Specified URL is not a FTP-path! Should be one of sftp:// or ftps://.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// set the login.
		$protocol_handler_obj->set_login( $this->get_login() );
		$protocol_handler_obj->set_password( $this->get_password() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $directory );

		// bail if connection failed.
		if ( ! $ftp_connection ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a URL. */
			$error->add( 'efml_service_ftp', sprintf( __( 'Connection to FTP failed! <a href="%1$s">Check the log</a> for details.', 'external-files-in-media-library' ), esc_url( Settings::get_instance()->get_url( 'eml_logs' ) ) ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Connection to FTP failed! Reason:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $ftp_connection->errors ) . '</code>' );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// return true if connection was successfully.
		return true;
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool   $result The result - should be true to prevent the usage.
	 * @param string $path   The file path.
	 * @param string $url The used URL.
	 * @param bool   $is_dir Is this is a directory.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, string $path, string $url, bool $is_dir ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// bail if this is a directory.
		if ( $is_dir ) {
			return $result;
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $path );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}
}
