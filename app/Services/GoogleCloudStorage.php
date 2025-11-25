<?php
/**
 * File to handle support for the Google Cloud Storage platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Exception;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Textarea;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use Google\Cloud\Storage\StorageClient;
use Error;
use WP_Error;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class GoogleCloudStorage extends Service_Base implements Service {
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
	protected string $settings_sub_tab = 'eml_googlecloudstorage';

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
		// use parent initialization.
		parent::init();

		// add settings.
		add_action( 'init', array( $this, 'init_google_cloud_storage' ), 30 );

		// bail if user has no capability for this service.
		if ( ! defined( 'EFML_SYNC_RUNNING' ) && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your Google Cloud Storage', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_service_googlecloudstorage_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'efml_directory_listing', array( $this, 'cleanup_on_rest' ), 10, 3 );
		add_action( 'eml_before_file_list', array( $this, 'cleanup_after_import' ) );
		add_filter( 'efml_directory_listing', array( $this, 'prepare_tree_building' ), 10, 3 );

		// use hooks.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_settings' ) );
	}

	/**
	 * Add settings for Google Cloud Storage support.
	 *
	 * @return void
	 */
	public function init_google_cloud_storage(): void {
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

		// add setting for insert-field.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || $this->is_mode( 'global' ) ) {
			$setting = $settings_obj->add_setting( 'eml_google_cloud_storage_json' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Textarea();
			$field->set_title( __( 'Authentication JSON', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Get the authentication JSON by editing your service account <a href="%1$s" target="_blank">here (opens new window)</a>.', 'external-files-in-media-library' ), $this->get_console_url() ) );
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
			$field->set_description( __( 'Set the name of the bucket to use for external files. Leave this field empty if you want to connect to different buckets.', 'external-files-in-media-library' ) );
			$setting->set_field( $field );

			// show button to go to the files.
			$setting = $settings_obj->add_setting( 'eml_google_cloud_storage_goto' );
			$setting->set_section( $section );
			$setting->prevent_export( true );
			$field = new Button();
			$field->set_title( __( 'Files in storage', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'View and import files', 'external-files-in-media-library' ) );
			$field->set_button_url( Directory_Listing::get_instance()->get_view_directory_url( $this ) );
			$setting->set_field( $field );
		}

		if ( $this->is_mode( 'user' ) ) {
			$setting = $settings_obj->add_setting( 'eml_google_cloud_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
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
	 * Validate the given JSON-string.
	 *
	 * @param string|null $json The string to validate as JSON.
	 *
	 * @return string
	 */
	public function validate_json( ?string $json ): string {
		// convert to string.
		if ( ! is_string( $json ) ) {
			return '';
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
		$array = json_decode( $json, true );

		// bail on error.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// show error.
			add_settings_error( 'eml_google_cloud_storage_json', 'eml_google_cloud_storage_json', __( '<strong>Given JSON is not valid!</strong> Please use the JSON given to you by Google Cloud.', 'external-files-in-media-library' ) );

			// return empty string.
			return '';
		}

		// bail if necessary data for Google Cloud Storage are not included in the array.
		if ( ! isset( $array['type'] ) ) {
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
		// get from global setting, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			return (string) get_option( 'eml_google_cloud_storage_json' );
		}

		// get user-specific setting, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}

			// get the value.
			return Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_google_cloud_storage_json', true ) );
		}

		// return nothing.
		return '';
	}

	/**
	 * Return the bucket name.
	 *
	 * @return string
	 */
	public function get_bucket_name(): string {
		// get from global setting, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			return (string) get_option( 'eml_google_cloud_storage_bucket' );
		}

		// get user-specific setting, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get the user set on object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}

			// return the value.
			return Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_google_cloud_storage_bucket', true ) );
		}

		// return nothing.
		return '';
	}

	/**
	 * Set a pseudo-directory to force the directory listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return 'Google Cloud Storage Listing';
	}

	/**
	 * Return directory listing from Google Cloud Storage.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// load necessary classes.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		}

		// get fields.
		$fields = $this->get_fields();

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
		$bucket = $storage->bucket( $fields['bucket']['value'] );

		// bail if bucket does not exist.
		try {
			if ( ! $bucket->exists() ) {
				// log this event.
				Log::get_instance()->create( __( 'Google Cloud Storage bucket does not exist.', 'external-files-in-media-library' ), '', 'info' );

				// create an error object.
				$error = new WP_Error();
				$error->add( 'efml_service_googlecloudstorage', __( 'Given bucket does not exist.', 'external-files-in-media-library' ) );
				$this->add_error( $error );

				// do nothing more.
				return array();
			}
		} catch ( Exception $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Google Cloud Storage bucket could not be loaded:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// create an error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a URL. */
			$error->add( 'efml_service_googlecloudstorage', sprintf( __( 'Google Cloud Storage bucket could not be loaded. Take a look at the <a href="%1$s" target="_blank">log</a> for more information.', 'external-files-in-media-library' ), Helper::get_log_url() ) );
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
		try {
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
						$index = $fields['bucket']['value'] . trailingslashit( $dir_path );

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
		} catch ( Exception $e ) {
			Log::get_instance()->create( __( 'Google Cloud Storage bucket could not be loaded:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );

			// create an error object.
			$error = new WP_Error();
			/* translators: %1$s will be replaced by a URL. */
			$error->add( 'efml_service_googlecloudstorage', sprintf( __( 'Google Cloud Storage bucket could not be loaded. Take a look at the <a href="%1$s" target="_blank">log</a> for more information.', 'external-files-in-media-library' ), Helper::get_log_url() ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// return the resulting listing.
		return array_merge( array( 'completed' => true ), array( $this->get_directory() => $listing ), $folders );
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "' . $this->get_url_mark() . '" + file.file, "fields": config.fields, "term": term } );',
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
		// get the settings URL depending on actual settings.
		$settings_url = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
		if ( $this->is_mode( 'user' ) ) {
			$settings_url = $this->get_config_url();
		}

		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'location.href="https://console.cloud.google.com/storage/browser/";',
					'label'  => __( 'Go to your bucket', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'location.href="' . esc_url( $settings_url ) . '";',
					'label'  => __( 'Settings', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", "' . $this->get_url_mark() . '" + actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Return the URL mark which identifies Google Cloud Storage URLs within this plugin.
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
		// add the Google Cloud Storage protocol before the HTTPS-protocol and return resulting list of protocols.
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

		// get the authentification JSON.
		$authentication_json = $this->get_fields()['authentication_json']['value'];

		try {
			// save the tmp-file.
			$wp_filesystem->put_contents( $credential_file_path, $authentication_json );
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
	 * Show option to connect to Google Cloud Storage on the user profile.
	 *
	 * @param WP_User $user The WP_User object for the actual user.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if settings are not user-specific.
		if ( ! $this->is_mode( 'user' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'Google Cloud Storage', 'external-files-in-media-library' ); ?></h3>
		<div class="efml-user-settings">
		<?php

		// show settings table.
		$this->get_user_settings_table( absint( $user->ID ) );

		?>
		</div>
		<?php
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		$list = array(
			'google_cloud_storage_json'   => array(
				'label'       => __( 'Authentication JSON', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced by a URL. */
				'description' => sprintf( __( 'Get the authentication JSON by editing your service account <a href="%1$s" target="_blank">here (opens new window)</a>.', 'external-files-in-media-library' ), $this->get_console_url() ),
				'field'       => 'textarea',
			),
			'google_cloud_storage_bucket' => array(
				'label'       => __( 'Bucket', 'external-files-in-media-library' ),
				'description' => __( 'Set the name of the bucket to use for external files. Leave this field empty if you want to connect to different buckets.', 'external-files-in-media-library' ),
				'field'       => 'text',
			),
		);

		/**
		 * Filter the list of possible user settings for Google Cloud Storage.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'eml_service_google_cloud_user_settings', $list );
	}

	/**
	 * Cleanup after tree has been build.
	 *
	 * @param array<string,mixed> $tree The tree.
	 * @param string              $directory The requested directory.
	 * @param string              $name The used service name.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function cleanup_on_rest( array $tree, string $directory, string $name ): array {
		// bail if this is not our service.
		if ( $name !== $this->get_name() ) {
			return $tree;
		}

		// get the env var.
		$path = getenv( 'GOOGLE_APPLICATION_CREDENTIALS' );

		// bail if path is not set.
		if ( empty( $path ) ) {
			return $tree;
		}

		// delete the file.
		$wp_filesystem = Helper::get_wp_filesystem();
		$wp_filesystem->delete( $path );

		// return tree.
		return $tree;
	}

	/**
	 * Cleanup after import of file from Google Cloud Storage.
	 *
	 * @return void
	 */
	public function cleanup_after_import(): void {
		// get the env var.
		$path = getenv( 'GOOGLE_APPLICATION_CREDENTIALS' );

		// bail if path is not set.
		if ( empty( $path ) ) {
			return;
		}

		// delete the file.
		$wp_filesystem = Helper::get_wp_filesystem();
		$wp_filesystem->delete( $path );
	}

	/**
	 * Rebuild the resulting list to remove the pagination folders for clean view of the files.
	 *
	 * @param array<string,mixed> $listing The resulting list.
	 * @param string              $url The called URL.
	 * @param string              $service The used service.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prepare_tree_building( array $listing, string $url, string $service ): array {
		// bail if this is not our service.
		if ( $this->get_name() !== $service ) {
			return $listing;
		}

		// create the key of the index we want to remove.
		$index = trailingslashit( $this->get_url_mark() . $this->get_directory() );

		// bail if the entry with url_marker is not set.
		if ( ! isset( $listing[ $index ] ) ) {
			return $listing;
		}

		// remove this entry.
		unset( $listing[ $index ] );

		// return resulting list.
		return $listing;
	}

	/**
	 * Return the public URL for a given file ID.
	 *
	 * @param string $bucket_name The bucket name.
	 * @param string $file_name The file name.
	 *
	 * @return string
	 */
	public function get_public_url_for_file( string $bucket_name, string $file_name ): string {
		$url = 'https://storage.googleapis.com/' . $bucket_name . '/' . $file_name;

		/**
		 * Filter the public URL for a given bucket and file name.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 * @param string $bucket_name The bucket name.
		 * @param string $file_name The file name.
		 */
		return apply_filters( 'eml_service_google_cloud_storage_public_url', $url, $bucket_name, $file_name );
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// get the JSON.
			$authentication_json = $this->get_authentication_json();

			// get the bucket name.
			$bucket = $this->get_bucket_name();

			$this->fields = array(
				'authentication_json' => array(
					'name'        => 'authentication_json',
					'type'        => 'textarea',
					'label'       => __( 'Authentication JSON', 'external-files-in-media-library' ),
					'placeholder' => __( 'Enter your authentication JSON here', 'external-files-in-media-library' ),
					'readonly'    => $this->is_mode( 'user' ) || $this->is_mode( 'global' ),
					'value'       => $authentication_json,
					'credential'  => true,
				),
				'bucket'              => array(
					'name'        => 'bucket',
					'type'        => 'text',
					'label'       => __( 'Bucket', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your bucket', 'external-files-in-media-library' ),
					'readonly'    => ! empty( $bucket ),
					'value'       => $bucket,
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		// get the JSON.
		$authentication_json = $this->get_authentication_json();

		// show other title if access token is set.
		if ( ! empty( $authentication_json ) && ! $this->is_mode( 'manually' ) ) {
			return __( 'Connect to your Google Cloud Storage', 'external-files-in-media-library' );
		}

		// return default title.
		return __( 'Enter your access credentials', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the JSON.
		$authentication_json = $this->get_authentication_json();

		// if access token is set in plugin settings.
		if ( $this->is_mode( 'global' ) ) {
			if ( ! empty( $authentication_json ) && ! current_user_can( 'manage_options' ) ) {
				return __( 'An authentication JSON has already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
			}

			if ( empty( $authentication_json ) && ! current_user_can( 'manage_options' ) ) {
				return __( 'An authentication JSON must be set by an administrator in the plugin settings.', 'external-files-in-media-library' );
			}

			if ( empty( $authentication_json ) ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your authentication JSON <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your access token is already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		// if authentication JSON is set per user.
		if ( $this->is_mode( 'user' ) ) {
			if ( empty( $authentication_json ) ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your authentication JSON <a href="%1$s">in your profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your authentication JSON is already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		/* translators: %1$s will be replaced by a URL. */
		return sprintf( __( 'Get the authentication JSON by editing your service account <a href="%1$s" target="_blank">here</a> and enter the name of the bucket you want to connect to.', 'external-files-in-media-library' ), $this->get_console_url() );
	}

	/**
	 * Return the URL for the Google Cloud Console where credentials can be managed.
	 *
	 * @return string
	 */
	private function get_console_url(): string {
		$url = 'https://console.cloud.google.com/apis/credentials';

		/**
		 * Filter the URL for the Google Cloud Console where credentials can be managed.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 */
		return apply_filters( 'eml_service_google_cloud_storage_console_url', $url );
	}
}
