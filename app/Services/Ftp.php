<?php
/**
 * File to handle the FTP support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Error;
use WP_Filesystem_FTPext;
use WP_Image_Editor_Imagick;

/**
 * Object to handle support for FTP-based directory listing.
 */
class Ftp extends Directory_Listing_Base implements Service {
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
		$this->title = __( 'Choose file(s) from a FTP server', 'external-files-in-media-library' );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_filter( 'eml_import_fields', array( $this, 'add_option_for_local_import' ) );
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
	 * Add option to import from local directory.
	 *
	 * @param array<int,string> $fields List of import options.
	 *
	 * @return array<int,string>
	 */
	public function add_option_for_local_import( array $fields ): array {
		$fields[] = '<details><summary>' . __( 'Or add from FTP-server directory', 'external-files-in-media-library' ) . '</summary><div><label for="eml_ftp"><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . esc_html__( 'Add from FTP server directory', 'external-files-in-media-library' ) . '</a></label></div></details>';
		return $fields;
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

		// bail if connection failed.
		if ( ! $ftp_connection ) {
			return array();
		}

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			return array();
		}

		// get the staring directory.
		$parse_url = wp_parse_url( $directory );

		// set parent dir.
		$parent_dir = '/';

		// bail if path could not be read.
		if ( isset( $parse_url['path'] ) ) {
			// get parent_dir path.
			$parent_dir = trailingslashit( $parse_url['path'] );
		}

		// get list of directory.
		$directory_list = $ftp_connection->dirlist( $parent_dir );

		// bail if list is empty.
		if ( empty( $directory_list ) ) {
			return array();
		}

		// collect the content of this directory.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// loop through the list, add each file to the list and loop through each subdirectory.
		foreach ( $directory_list as $item_name => $item_settings ) {
			// get path for item.
			$path = $parse_url['scheme'] . '://' . $parse_url['host'] . $parent_dir . $item_name;

			// collect the entry.
			$entry = array(
				'title' => $item_name,
			);

			// if item is a directory, check its files.
			if ( $ftp_connection->is_dir( $path ) ) {
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

				if ( Init::get_instance()->is_preview_enabled() ) {
					// get protocol handler for this external file.
					$protocol_handler = Protocols::get_instance()->get_protocol_object_for_url( trailingslashit( $directory ) . $item_name );
					if ( $protocol_handler instanceof Protocols\Ftp ) {
						// get the tmp file for this file.
						$filename = $protocol_handler->get_temp_file( $protocol_handler->get_url(), $ftp_connection );

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
							$results = $editor->save( $upload_dir . '/' . basename( $item_name ) );

							// add thumb to output if it does not result in an error.
							if ( ! is_wp_error( $results ) ) {
								$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
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
					'action' => 'efml_import_url( actualDirectoryPath, login, password, [], config.term );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "ftp", actualDirectoryPath, login, password, "", config.term );',
					'label'  => __( 'Save active directory as directory archive', 'external-files-in-media-library' ),
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
			$error->add( 'efml_service_ftp', __( 'Given URL is not a FTP-path! Should be one of sftp:// or ftps://.', 'external-files-in-media-library' ) );

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
			$error->add( 'efml_service_ftp', __( 'Connection to FTP failed! Check the log for details.', 'external-files-in-media-library' ) );

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
}
