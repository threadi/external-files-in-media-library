<?php
/**
 * File to handle the support of files from ZIP as directory listing.
 *
 * Handling of ZIPs per request:
 * - URL/path ending with "/" is a ZIP that should be extracted and its files should be imported in media library
 * - URL/path ending with ".zip" is a file that should bei imported
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use Error;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Error;
use WP_Post;
use ZipArchive;

/**
 * Object to handle support of files from ZIP as directory listing.
 */
class Zip extends Service_Base implements Service {

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'zip';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'ZIP';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_zip';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Zip
	 */
	private static ?Zip $instance = null;

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
	 * @return Zip
	 */
	public static function get_instance(): Zip {
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

		// use our own hooks.
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// add settings.
		add_action( 'init', array( $this, 'init_zip' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Extract file(s) from a ZIP-File', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_file_check_existence', array( $this, 'is_file_in_zip_file' ), 10, 2 );
		add_filter( 'efml_external_file_infos', array( $this, 'get_file' ), 10, 2 );
		add_filter( 'efml_filter_url_response', array( $this, 'get_files_from_zip' ), 10, 2 );
		add_filter( 'efml_filter_file_response', array( $this, 'get_files_from_zip' ), 10, 2 );
		add_filter( 'efml_add_dialog', array( $this, 'change_import_dialog' ), 10, 2 );
		add_filter( 'efml_duplicate_check', array( $this, 'prevent_duplicate_check_for_unzip' ) );
		add_filter( 'efml_locale_file_check', array( $this, 'prevent_duplicate_check_for_unzip' ) );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
	}

	/**
	 * Add settings for AWS S3 support.
	 *
	 * @return void
	 */
	public function init_zip(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add new tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add section for file statistics.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add setting to show also trashed files.
		$setting = $settings_obj->add_setting( 'eml_zip_import_limit' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 6 );
		$field = new Number();
		$field->set_title( __( 'Max. files to load during import per iteration', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This value specifies how many files should be loaded during a directory import. The higher the value, the greater the likelihood of timeouts during import.', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$field->set_readonly( $this->is_disabled() );
		$setting->set_field( $field );
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
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// bail if "ZipArchive" is not available.
		if ( ! $this->is_zip_archive_available( $directory ) ) {
			return array();
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if handler is not a known protocol.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return array();
		}

		// get the starting directory.
		$parse_url = wp_parse_url( $directory );

		// bail if given string is not a valid URL.
		if ( empty( $parse_url ) ) {
			return array();
		}

		// bail if no path could be excluded.
		if ( empty( $parse_url['path'] ) ) {
			return array();
		}

		// bail if no scheme could be excluded.
		if ( empty( $parse_url['scheme'] ) ) {
			return array();
		}

		// open zip file using ZipArchive as readonly.
		$zip = $this->get_zip_object_by_file( $directory );

		// bail if ZIP could not be opened.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// get count of files.
		$file_count = $zip->count();

		// collect the list of files.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// collect folders.
		$folders = array();

		// loop through the files and create the list.
		for ( $i = 0; $i < $file_count; $i++ ) {
			// get the name.
			$name = $zip->getNameIndex( $i );

			// bail if name could not be read.
			if ( ! is_string( $name ) ) {
				continue;
			}

			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $name );

			// get entry data.
			$file_stat = $zip->statIndex( $i );

			// bail if file_stat could not be read.
			if ( ! is_array( $file_stat ) ) {
				continue;
			}

			// collect the entry.
			$entry = array(
				'title' => basename( $file_stat['name'] ),
			);

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $file_stat['name'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $file_stat['name'];
				$entry['filesize']      = absint( $file_stat['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( ( $file_stat['mtime'] ) ) ) );
				$entry['preview']       = '';
			}

			// if array contains more than 1 entry this file is in a directory.
			if ( count( $parts ) > 1 ) {
				$the_keys = array_keys( $parts );
				$last_key = end( $the_keys );
				$last_dir = '';
				$dir_path = '';
				foreach ( $parts as $key => $dir ) {
					// bail if dir is empty.
					if ( empty( $dir ) ) {
						continue;
					}

					// bail for last entry (which is a file).
					if ( $key === $last_key ) {
						// add the file to the last iterated directory.
						$folders[ $last_dir ]['files'][] = $entry;
						continue;
					}

					// add the path.
					$dir_path .= DIRECTORY_SEPARATOR . $dir;

					// add the directory if it does not exist atm in the list.
					$index = $directory . '/' . trailingslashit( $dir_path );
					if ( ! isset( $folders[ $index ] ) ) {
						// add the directory to the list.
						$folders[ $index ] = array(
							'title' => $dir,
							'files' => array(),
							'dirs'  => array(),
						);
					}

					// add the directory if it does not exist atm in the main folder list.
					if ( ! empty( $last_dir ) && ! isset( $folders[ $last_dir ]['dirs'][ $index ] ) ) {
						// add the directory to the list.
						$folders[ $last_dir ]['dirs'][ $index ] = array(
							'title' => $dir,
							'files' => array(),
							'dirs'  => array(),
						);
					}

					// mark this dir as last dir for file path.
					$last_dir = $index;
				}
			} else {
				// simply add the entry to the list if no directory data exist.
				$listing['files'][] = $entry;
			}
		}

		// close the zip handle.
		$zip->close();

		if ( ! empty( $folders ) ) {
			$listing['dirs'][ array_key_first( $folders ) ] = array(
				'title'   => array_key_first( $folders ),
				'folders' => array(),
				'dirs'    => array(),
			);
		}

		// return the resulting list.
		return array_merge( array( 'completed' => true ), array( $directory => $listing ), $folders );
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": config.directory + file.file, "fields": config.fields, "term": config.term } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
			),
		);
	}

	/**
	 * Check if given path is a file in a ZIP-file.
	 *
	 * @param bool   $return_value The result (true, if file existence check should be run).
	 * @param string $file_path The path to the file (should contain and not end with '.zip').
	 *
	 * @return bool
	 */
	public function is_file_in_zip_file( bool $return_value, string $file_path ): bool {
		// bail if file path does not contain '.zip'.
		if ( ! $this->is_zip( $file_path ) ) {
			return $return_value;
		}

		// bail if file path does end with '.zip' (if it is the ZIP itself).
		if ( str_ends_with( $file_path, '.zip' ) ) {
			return $return_value;
		}

		// return false to prevent file check as this seems to be a file in a ZIP-file.
		return false;
	}

	/**
	 * Return info about requested single file from a given ZIP.
	 *
	 * We save the unzipped file in tmp directory to get all data of this file. This is necessary for the import
	 * of them.
	 *
	 * @param array<string,int|string> $results The result.
	 * @param string                   $file_path The path to the file (should contain and not end with '.zip').
	 *
	 * @return array<string,int|string>
	 */
	public function get_file( array $results, string $file_path ): array {
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return $results;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return $results;
		}

		// bail if file path does not contain '.zip'.
		if ( ! $this->is_zip( $file_path ) ) {
			return array();
		}

		// bail if PHP module "ZipArchive" is not available.
		if ( ! $this->is_zip_archive_available( $file_path ) ) {
			return array();
		}

		// get the path to the file in the ZIP (+4 for .zip and +1 for the starting "/") we want to extract.
		$file = substr( $file_path, strpos( $file_path, '.zip' ) + 5 );

		// bail if no file could be found. This is not an error, but used for direct ZIP-upload.
		if ( empty( $file ) ) {
			return array();
		}

		// get the zip object for the given file.
		$zip = $this->get_zip_object_by_file( $file_path );

		// bail if zip could not be opened.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// get the fields JSON from request.
		$fields_json = filter_input( INPUT_POST, 'fields', FILTER_UNSAFE_RAW );

		// decode the fields-JSON to an array.
		$fields = array();
		if ( is_string( $fields_json ) ) {
			$fields = json_decode( $fields_json, true );
		}

		// if password is set, set if on the zip object.
		if ( is_array( $fields ) && ! empty( $fields['zip_password']['value'] ) ) {
			$zip->setPassword( $fields['zip_password']['value'] );
		}

		// get content of the file to extract.
		$file_content = $zip->getFromName( $file );

		// bail if no file data could be loaded.
		if ( ! $file_content ) {
			// log event.
			Log::get_instance()->create( __( 'No data of the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $file_path, 'error' );

			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'No data of the file to extract from ZIP could not be loaded. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $file ) ) );
			$error_obj->set_url( $file );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get entry data.
		$file_stat = $zip->statName( $file );

		// bail if no stats could be loaded.
		if ( ! is_array( $file_stat ) ) {
			// log event.
			Log::get_instance()->create( __( 'No stats for the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $file_path, 'error' );

			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'No stats for the file to extract from ZIP could not be loaded. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $file ) ) );
			$error_obj->set_url( $file );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// return empty array as we can not get infos about a file which does not exist.
			return array();
		}

		// get file date from zip.
		$results['last-modified'] = absint( $file_stat['mtime'] );

		// get the file size.
		$results['filesize'] = absint( $file_stat['size'] );

		// get file infos.
		$file_info = pathinfo( $file );

		// bail if extension could not be read.
		if ( ! isset( $file_info['extension'] ) ) {
			return array();
		}

		// get tmp file name.
		$tmp_file_name = wp_tempnam();

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $file_info['extension'] );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// and save the file there.
		try {
			$wp_filesystem->put_contents( $tmp_file, $file_content );
			$wp_filesystem->delete( $tmp_file_name );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $file ) ) );
			$error_obj->set_url( $file );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $file, 'error' );

			// do nothing more.
			return array();
		}

		// add the path to the tmp file to the file infos.
		$results['tmp-file'] = $tmp_file;

		// return resulting file infos.
		return $results;
	}

	/**
	 * Return list of files in zip to import.
	 *
	 * @param array<int|string,array<string,mixed>|bool> $results The resulting list.
	 * @param string                                     $file_path The file path to check and import.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_files_from_zip( array $results, string $file_path ): array {
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return $results;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return $results;
		}

		// bail if PHP module "ZipArchive" is not available.
		if ( ! $this->is_zip_archive_available( $file_path ) ) {
			return array();
		}

		// if given file is a single file in a ZIP, get its file infos.
		if ( ! str_ends_with( $file_path, '/' ) ) {
			// get the tmp file for this single file from the zip.
			$tmp_file = $this->get_file( array(), $file_path );

			// bail if no file could be loaded.
			if ( empty( $tmp_file ) ) {
				return array();
			}

			// set the mime type.
			$mime_type = wp_check_filetype( basename( $file_path ) );

			// bail if no mime type could be found.
			if ( empty( $mime_type['ext'] ) ) {
				return array();
			}

			// complete the entry and return it.
			return array(
				array(
					'title'         => basename( $file_path ),
					'local'         => true,
					'url'           => $file_path,
					'last-modified' => absint( $tmp_file['last-modified'] ),
					'tmp-file'      => $tmp_file['tmp-file'],
					'mime-type'     => $mime_type['type'],
					'filesize'      => absint( $tmp_file['filesize'] ),
				),
			);
		}

		// get the zip file as object.
		$zip = $this->get_zip_object_by_file( $file_path );

		// bail if zip could not be opened.
		if ( ! $zip instanceof ZipArchive ) {
			return array();
		}

		// get count of files.
		$file_count = $zip->count();

		// set counter for files which has been loaded from Google Drive.
		$loaded_files = 0;

		// loop through the files and create the list.
		for ( $i = 0; $i < $file_count; $i++ ) {
			// get the name.
			$name = $zip->getNameIndex( $i );

			// create a pseudo URL for this file.
			$url = trailingslashit( $file_path ) . $name;

			// get entry data.
			$file_stat = $zip->statIndex( $i );

			// bail if file_stat could not be read.
			if ( ! is_array( $file_stat ) ) {
				continue;
			}

			// bail if this an AJAX-request and the file already exist in media library.
			if ( wp_doing_ajax() && Files::get_instance()->get_file_by_title( basename( $file_stat['name'] ) ) ) {
				continue;
			}

			// bail if limit for loaded files has been reached and this is an AJAX-request.
			if ( wp_doing_ajax() && $loaded_files > absint( get_option( 'eml_zip_import_limit', 10 ) ) ) {
				// set marker to load more.
				$results['load_more'] = true;
				continue;
			}

			// bail if name could not be read.
			if ( ! is_string( $name ) ) {
				continue;
			}

			// get parts of the path.
			$parts = explode( DIRECTORY_SEPARATOR, $name );

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $file_stat['name'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// get tmp file name.
				$tmp_file_name = wp_tempnam();

				// set the file as tmp-file for import.
				$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.' . $mime_type['ext'] );

				// get WP Filesystem-handler.
				$wp_filesystem = Helper::get_wp_filesystem();

				// get info about the file to extract.
				$file_content = $zip->getFromName( $name );

				// bail if no file data could be loaded.
				if ( ! $file_content ) {
					// log event.
					Log::get_instance()->create( __( 'No data of the file to extract from ZIP could not be loaded.', 'external-files-in-media-library' ), $file_path, 'error' );

					// return empty array as we can not get infos about a file which does not exist.
					continue;
				}

				// and save the file there.
				try {
					$wp_filesystem->put_contents( $tmp_file, $file_content );
					$wp_filesystem->delete( $tmp_file_name );
				} catch ( Error $e ) {
					// create the error entry.
					$error_obj = new Url_Result();
					/* translators: %1$s will be replaced by a URL. */
					$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $file_path ) ) );
					$error_obj->set_url( $file_path );
					$error_obj->set_error( true );

					// add the error object to the list of errors.
					Results::get_instance()->add( $error_obj );

					// add log entry.
					Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $file_path, 'error' );

					// do nothing more.
					continue;
				}

				// collect the entry.
				$entry = array(
					'title'         => basename( $file_stat['name'] ),
					'local'         => true,
					'last-modified' => absint( $file_stat['mtime'] ),
					'tmp-file'      => $tmp_file,
					'mime-type'     => $mime_type['type'],
					'url'           => $url,
					'filesize'      => absint( $file_stat['size'] ),
				);

				// update the counter.
				++$loaded_files;

				// add the entry to the list.
				$results[] = $entry;
			}
		}

		// close the zip handle.
		$zip->close();

		// return the resulting list of files.
		return $results;
	}

	/**
	 * Check if given directory is a valid ZIP-file.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// bail if "ZipArchive" is not available.
		if ( ! $this->is_zip_archive_available( $directory ) ) {
			return false;
		}

		// bail if directory is not set.
		if ( empty( $directory ) ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_zip', __( 'No ZIP-file given!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// log this event.
			Log::get_instance()->create( __( 'No ZIP-file given!', 'external-files-in-media-library' ), $directory, 'error' );

			// return false to prevent further processing.
			return false;
		}

		// bail if file path does end with '.zip'.
		if ( ! str_ends_with( $directory, '.zip' ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'The given path <code>%1$s</code> does not end with ".zip"!', 'external-files-in-media-library' ), $directory ) );

			// add it to the list.
			$this->add_error( $error );

			// log this event.
			/* translators: %1$s will be replaced by a file path. */
			Log::get_instance()->create( sprintf( __( 'The given path <code>%1$s</code> does not end with ".zip"!', 'external-files-in-media-library' ), $directory ), $directory, 'error' );

			// return false to prevent further processing.
			return false;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the used protocol.
		$protocol_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if no protocol could be loaded.
		if ( ! $protocol_obj instanceof Protocol_Base ) {
			return false;
		}

		// download the ZIP-file to test it.
		$zip_file = $protocol_obj->get_temp_file( $directory, $wp_filesystem );

		// bail if temp file could not be loaded.
		if ( ! is_string( $zip_file ) ) {
			return false;
		}

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $zip_file ) ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'The given URL <code>%1$s</code> does not exist.', 'external-files-in-media-library' ), $directory ) );

			// add it to the list.
			$this->add_error( $error );

			// log this event.
			/* translators: %1$s will be replaced by a file path. */
			Log::get_instance()->create( sprintf( __( 'The given URL <code>%1$s</code> does not exist.', 'external-files-in-media-library' ), $directory ), $directory, 'error' );

			// return false to prevent further processing.
			return false;
		}

		// get the zip object and open it to test if it is valid.
		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_file, ZipArchive::RDONLY );

		// bail if file could not be opened.
		if ( true !== $opened ) {
			// create error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a file path. */
			$error->add( 'efml_service_zip', sprintf( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file ) );

			// add it to the list.
			$this->add_error( $error );

			// log this event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened with following error code:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $opened ) . '</code>', $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return true as given directory is a valid ZIP-file.
		return true;
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return ! class_exists( 'ZipArchive' );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<span>' . __( 'PHP-Module zip is missing!', 'external-files-in-media-library' ) . '</span>';
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		// add our own actions.
		$actions = array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "fields": config.fields, "term": config.term } );',
				'label'  => __( 'Extract actual directory in media library', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
				'label'  => __( 'Save this file as your external source', 'external-files-in-media-library' ),
			),
		);

		// return the resulting actions.
		return array_merge(
			parent::get_global_actions(),
			$actions
		);
	}

	/**
	 * Return whether the necessary PHP module ZipArchive is available.
	 *
	 * @param string $url The requested URL.
	 *
	 * @return bool
	 */
	private function is_zip_archive_available( string $url ): bool {
		// bail if it is available.
		if ( class_exists( 'ZipArchive' ) ) {
			return true;
		}

		// create error object.
		$error = new WP_Error();
		$error->add( 'efml_service_zip', __( 'PHP-Module zip is missing! Please contact your hosting support about this problem.', 'external-files-in-media-library' ) );

		// add it to the list.
		$this->add_error( $error );

		// log this event.
		Log::get_instance()->create( __( 'PHP-Module zip is missing! Please contact your hosting support about this problem.', 'external-files-in-media-library' ), $url, 'error' );

		// return false to prevent further processing.
		return false;
	}

	/**
	 * Return an open zip object for a given file.
	 *
	 * @param string $file_path The file.
	 *
	 * @return bool|ZipArchive
	 */
	private function get_zip_object_by_file( string $file_path ): bool|ZipArchive {
		// get the path to the ZIP from path string.
		$zip_file = substr( $file_path, 0, absint( strpos( $file_path, '.zip' ) ) ) . '.zip';

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $zip_file );

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return false;
		}

		// get the local tmp file of this zip.
		$tmp_zip_file = $protocol_handler_obj->get_temp_file( $zip_file, $wp_filesystem );

		// bail if no temp zip could be returned.
		if ( ! is_string( $tmp_zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be saved as temp file.', 'external-files-in-media-library' ), $zip_file, 'error' );

			return false;
		}

		// bail if file does not exist.
		if ( ! $wp_filesystem->exists( $tmp_zip_file ) ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file to use for extracting a file does not exist.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// get the zip object.
		$zip    = new ZipArchive();
		$opened = $zip->open( $tmp_zip_file, ZipArchive::RDONLY );

		// bail if file could not be opened.
		if ( ! $opened ) {
			// log event.
			Log::get_instance()->create( __( 'ZIP-file could not be opened for extracting a file from it.', 'external-files-in-media-library' ), $zip_file, 'error' );

			// return empty array as we can not get infos about a file which does not exist.
			return false;
		}

		// return the opened zip as object.
		return $zip;
	}

	/**
	 * Change media row actions for URL-files.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// bail if this is not a zip file.
		if ( 'ZIP' !== $external_file_obj->get_file_type_obj()->get_name() ) {
			return $actions;
		}

		// bail if file is not hosted locally.
		if ( ! $external_file_obj->is_locally_saved() ) {
			return $actions;
		}

		// get the local path of this file.
		$path = wp_get_attachment_url( $external_file_obj->get_id() );

		// bail if path could not be loaded.
		if ( ! is_string( $path ) ) {
			return $actions;
		}

		// define settings for the import dialog.
		$settings = array(
			'service' => $this->get_name(),
			'urls'    => trailingslashit( $path ),
			'unzip'   => true,
		);

		// add action to extract this file in media library.
		$actions['eml-extract-zip'] = '<a href="#" class="efml-import-dialog" data-settings="' . esc_attr( Helper::get_json( $settings ) ) . '">' . __( 'Extract file', 'external-files-in-media-library' ) . '</a>';

		// create link to open this file.
		$url = add_query_arg(
			array(
				'page'   => 'efml_local_directories',
				'method' => $this->get_name(),
				'url'    => $path,
				'nonce'  => wp_create_nonce( 'efml-open-zip-nonce' ),
			),
			get_admin_url() . 'upload.php'
		);

		// add action to open this file.
		$actions['eml-open-zip'] = '<a href="' . esc_url( $url ) . '">' . __( 'Open file', 'external-files-in-media-library' ) . '</a>';

		// return the resulting list of action.
		return $actions;
	}

	/**
	 * Change the import dialog if we request the unzipping of a single file in media library.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings for the dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function change_import_dialog( array $dialog, array $settings ): array {
		// bail if "unzip" is not set.
		if ( ! isset( $settings['unzip'] ) ) {
			return $dialog;
		}

		// change the title.
		$dialog['title'] = __( 'Unzip this file in your media library', 'external-files-in-media-library' );

		// add marker for unzip task (this allows the magic).
		$dialog['texts'][] = '<input type="hidden" name="unzip" value="1" />';

		// return resulting dialog.
		return $dialog;
	}

	/**
	 * Prevent duplicate check if zip should be unzipped in media library.
	 *
	 * @param bool $return_value The return value.
	 *
	 * @return bool
	 */
	public function prevent_duplicate_check_for_unzip( bool $return_value ): bool {
		// get unzip value from request.
		$unzip = filter_input( INPUT_POST, 'unzip', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no unzip value is set.
		if ( is_null( $unzip ) ) {
			return $return_value;
		}

		// return true to prevent the duplicate check.
		return true;
	}

	/**
	 * Return whether a given URL is a zip file based on its extension.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	private function is_zip( string $url ): bool {
		return str_contains( $url, '.zip' );
	}

	/**
	 * Change the directory listing object if a zip is requested.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		// get the base config.
		$config = parent::get_config();

		// get the URL from request.
		$url = filter_input( INPUT_GET, 'url', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no url is set.
		if ( is_null( $url ) ) {
			return $config;
		}

		// bail if nonce does not match.
		if ( ! check_admin_referer( 'efml-open-zip-nonce', 'nonce' ) ) {
			return $config;
		}

		// bail if the requested URL is not a zip.
		if ( ! $this->is_zip( $url ) ) {
			return $config;
		}

		// add the URL from the request.
		$config['directory'] = $url;

		// return the resulting config.
		return $config;
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			$this->fields = array(
				'server'       => array(
					'name'        => 'server',
					'type'        => 'url',
					'label'       => __( 'URL of the ZIP-file', 'external-files-in-media-library' ),
					'placeholder' => __( 'https://example.com', 'external-files-in-media-library' ),
				),
				'login'        => array(
					'name'         => 'login',
					'type'         => 'text',
					'label'        => __( 'Auth Basic Login (optional)', 'external-files-in-media-library' ),
					'placeholder'  => __( 'Your login', 'external-files-in-media-library' ),
					'not_required' => true,
					'credential'   => true,
				),
				'password'     => array(
					'name'         => 'password',
					'type'         => 'password',
					'label'        => __( 'Auth Basic Password (optional)', 'external-files-in-media-library' ),
					'placeholder'  => __( 'Your password', 'external-files-in-media-library' ),
					'not_required' => true,
					'credential'   => true,
				),
				'zip_password' => array(
					'name'         => 'zip_password',
					'type'         => 'password',
					'label'        => __( 'Password for the ZIP-file (optional)', 'external-files-in-media-library' ),
					'placeholder'  => __( 'The ZIP password', 'external-files-in-media-library' ),
					'not_required' => true,
					'credential'   => true,
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return the directory to load from fields.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// bail if no directory is set.
		if ( empty( $this->fields['server']['value'] ) ) {
			return '';
		}

		// return the directory.
		return $this->fields['server']['value'];
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		return __( 'Enter the URL', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		return __( 'Enter the URL of the ZIP file your want to open. This can also be a local file in your hosting starting with <em>file://</em>.', 'external-files-in-media-library' );
	}
}
