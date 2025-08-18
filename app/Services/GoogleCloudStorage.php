<?php
/**
 * File to handle support for the Google Cloud Storage platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use Error;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Textarea;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use Google\Cloud\Storage\StorageClient;
use WP_Error;

/**
 * Object to handle support for this platform.
 */
class GoogleCloudStorage extends Directory_Listing_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'google-cloud-storage';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Google Cloud Storage';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_sub_tab = 'eml_googlecloudstorage';

	/**
	 * Instance of actual object.
	 *
	 * @var ?GoogleCloudStorage
	 */
	private static ?GoogleCloudStorage $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return GoogleCloudStorage
	 */
	public static function get_instance(): GoogleCloudStorage {
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
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your Google Cloud Storage', 'external-files-in-media-library' );

		// use hooks.
		add_action( 'init', array( $this, 'init_google_cloud_storage' ), 20 );

		// use our own hooks.
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_service_googlecloudstorage_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
	}

	/**
	 * Add settings for Google Drive support.
	 *
	 * @return void
	 */
	public function init_google_cloud_storage(): void {
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
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 100 );
		$tab->set_title( __( 'Google Cloud Storage', 'external-files-in-media-library' ) );

		// add section for file statistics.
		$section = $tab->add_section( 'section_googlecloudstorage_main', 20 );
		$section->set_title( __( 'Settings for Google Cloud Storage', 'external-files-in-media-library' ) );

		// add setting for insert-field.
		$setting = $settings_obj->add_setting( 'eml_google_cloud_storage_json' );
		$setting->set_section( $section );
		$setting->set_autoload( false );
		$setting->set_type( 'string' );
		$setting->set_read_callback( array( $this, 'decrypt_value' ) );
		$setting->set_save_callback( array( $this, 'encrypt_value' ) );
		$field = new Textarea();
		$field->set_title( __( 'Authentication JSON', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by a URL. */
		$field->set_description( sprintf( __( 'Get the authentication JSON by editing your service account <a href="%1$s" target="_blank">here</a>.', 'external-files-in-media-library' ), 'https://console.cloud.google.com/apis/credentials' ) );
		$field->set_sanitize_callback( array( $this, 'validate_json' ) );
		$setting->set_field( $field );

		// add setting for bucket.
		$setting = $settings_obj->add_setting( 'eml_google_cloud_storage_bucket' );
		$setting->set_section( $section );
		$setting->set_autoload( false );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$field = new Text();
		$field->set_title( __( 'Bucket', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Set the name of the bucket to use for external files.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );

		// show button to go to the files if auth JSON is set.
		if ( ! $this->is_disabled() ) {
			$setting = $settings_obj->add_setting( 'eml_google_cloud_storage_goto' );
			$setting->set_section( $section );
			$setting->prevent_export( true );
			$field = new Button();
			$field->set_title( __( 'Files in storage', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'View and import files', 'external-files-in-media-library' ) );
			$field->set_button_url( Directory_Listing::get_instance()->get_view_directory_url( $this ) );
			$setting->set_field( $field );
		}
	}

	/**
	 * Enable WP CLI for Google Cloud Storage tasks.
	 *
	 * @return void
	 */
	public function cli(): void {}

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
	 * Return the settings slug.
	 *
	 * @return string
	 */
	private function get_settings_tab_slug(): string {
		return $this->settings_tab;
	}

	/**
	 * Return the settings sub tab slug.
	 *
	 * @return string
	 */
	private function get_settings_subtab_slug(): string {
		return $this->settings_sub_tab;
	}

	/**
	 * Validate the given JSON-string.
	 *
	 * @param string|null $json The string to validate as JSON.
	 *
	 * @return string
	 */
	public function validate_json( ?string $json ): string {
		// convert to string.
		if ( ! is_string( $json ) ) {
			$json = '';
		}

		// bail if empty string is given.
		if ( empty( $json ) ) {
			return '';
		}

		// check for encrypted value.
		$decrypted_value = $this->decrypt_value( $json );
		if ( ! empty( $decrypted_value ) ) {
			$json = $decrypted_value;
		}

		// decode the string.
		json_decode( $json, true );

		// bail on error.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// show error.
			add_settings_error( 'eml_google_cloud_storage_json', 'eml_google_cloud_storage_json', __( '<strong>Given JSON is not valid!</strong> Please use the JSON given to you by Google Cloud.', 'external-files-in-media-library' ) );

			// return empty string.
			return '';
		}

		// return the valid JSON.
		return $json;
	}

	/**
	 * Return the authentication JSON string.
	 *
	 * @return string
	 */
	private function get_authentication_json(): string {
		return (string) get_option( 'eml_google_cloud_storage_json' );
	}

	/**
	 * Return the bucket name.
	 *
	 * @return string
	 */
	public function get_bucket_name(): string {
		return (string) get_option( 'eml_google_cloud_storage_bucket' );
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return empty( $this->get_authentication_json() ) || empty( $this->get_bucket_name() );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<a class="connect button button-secondary" href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '">' . __( 'Connect', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Set a pseudo-directory to force the directory listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return 'Google Storage Cloud Listing';
	}

	/**
	 * Return directory listing from Google Storage Cloud.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// bail if it is disabled.
		if ( $this->is_disabled() ) {
			return array();
		}

		// load necessary classes.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		}

		// get the storage object.
		$storage = $this->get_storage_object();

		// bail if storage could not be loaded.
		if ( ! $storage instanceof StorageClient ) {
			// create an error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a URL. */
			$error->add( 'efml_service_googlecloudstorage', sprintf( __( 'Google Cloud Storage object could not be loaded. Take a look at the <a href="%1$s" target="_blank">log</a> for more information.', 'external-files-in-media-library' ), Helper::get_log_url() ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get our bucket as object.
		$bucket = $storage->bucket( $this->get_bucket_name() );

		// bail if bucket does not exist.
		if ( ! $bucket->exists() ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_googlecloudstorage', __( 'Given bucket does not exist.', 'external-files-in-media-library' ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// collect the list of files.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// collect list of folders.
		$folders = array();

		// get the file list from bucket.
		foreach ( $bucket->objects() as $file_obj ) {
			// get the file data.
			$file_data = $file_obj->info();

			$false = false;
			/**
			 * Filter whether given Google Cloud Storage file should be hidden.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param array<string,mixed> $file_data The object with the file data.
			 * @param string $directory The requested directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'efml_service_googlecloudstorage_hide_file', $false, $file_data, $directory ) ) {
				continue;
			}

			// get directory-data for this file and add file in the given directories.
			$parts = explode( '/', $file_data['name'] );

			// collect the entry.
			$entry = array(
				'title' => basename( $file_data['name'] ),
			);

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				// get content type of this file.
				$mime_type = wp_check_filetype( $file_data['name'] );

				// bail if file type is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $file_data['name'];
				$entry['filesize']      = absint( $file_data['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( strtotime( $file_data['updated'] ) ) ) );
				$entry['preview']       = '';
			}

			if ( count( $parts ) > 1 ) {
				$the_keys = array_keys( $parts );
				$last_key = end( $the_keys );
				$last_dir = '';
				$dir_path = '';

				// loop through all parent folders, add the directory if it does not exist in the list
				// and add the file to each of them.
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

					// create the full path.
					$index = $this->get_api_key() . trailingslashit( $dir_path );

					// add the directory if it does not exist atm in the main folder list.
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

		// return the resulting listing.
		return array_merge( array( 'completed' => true ), array( $this->get_api_key() => $listing ), $folders );
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "' . $this->get_url_mark() . '" + file.file, "login": login, "password": password, "term": term } );',
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
					'action' => 'location.href="https://console.cloud.google.com/storage/browser/";',
					'label'  => __( 'Go to your bucket', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'location.href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '";',
					'label'  => __( 'Settings', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, "", "", "" );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Return the URL mark which identifies Google Drive URLs within this plugin.
	 *
	 * @return string
	 */
	public function get_url_mark(): string {
		return 'https://console.cloud.google.com/storage/browser/';
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool                $result The result - should be true to prevent the usage.
	 * @param array<string,mixed> $file_data   The file object.
	 * @param string              $url The used URL.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, array $file_data, string $url ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $file_data['name'] );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// only add the protocol if it is not disabled.
		if ( $this->is_disabled() ) {
			return $protocols;
		}

		// add the Google Drive protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\GoogleCloudStorage\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Return the StorageClient object for configured JSON.
	 *
	 * @return StorageClient|false
	 */
	public function get_storage_object(): StorageClient|false {
		// create tmp file for the credential JSON.
		$credential_file_path = wp_tempnam();

		// get the file system object.
		$wp_filesystem = Helper::get_wp_filesystem();

		try {
			// save the tmp-file.
			// TODO auch wieder löschen!
			$wp_filesystem->put_contents( $credential_file_path, $this->get_authentication_json() );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			$error_obj->set_result_text( __( 'Error occurred during requesting the credential file for Google Cloud Storage.', 'external-files-in-media-library' ) );
			$error_obj->set_url( $this->get_url( '' ) );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred during requesting the credential file for Google Cloud Storage:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url( '' ), 'error' );

			// do nothing more.
			return false;
		}

		// set the tmp file for authentication.
		putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . $credential_file_path );

		// get the storage as object.
		return new StorageClient( array() );
	}

	/**
	 * Encrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function encrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty.
		if ( empty( $value ) ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->encrypt( $value );
	}

	/**
	 * Decrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function decrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty.
		if ( empty( $value ) ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}
}
