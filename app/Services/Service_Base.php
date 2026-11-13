<?php
/**
 * File to handle service objects.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use easySettingsForWordPress\Fields\Select;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use Exception;
use Throwable;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class Service_Base extends Directory_Listing_Base {
	/**
	 * Name of the field to choose the connection, if several connections are defined via constants.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 */
	public const CONNECTION_FIELD = 'efml_connection';

	/**
	 * Name of the connection, which is defined via constants without a connection name (e.g. "EFML_FTP_SERVER").
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 */
	public const DEFAULT_CONNECTION = 'default';

	/**
	 * The directory, in which the connection test saves its test file.
	 *
	 * @var string
	 */
	private string $connection_test_directory = '';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = '';

	/**
	 * Marker for sync support (false to enable it).
	 *
	 * @var bool
	 */
	protected bool $sync_disabled = false;

	/**
	 * The user.
	 *
	 * @var WP_User|false
	 */
	private WP_User|false $user = false;

	/**
	 * Marker whether values of fields could be defined via constants (true) or not (false).
	 *
	 * The constants are used for the fields of the service object and for the fields of each protocol,
	 * which is using this service (see @Protocol_Base::get_fields()).
	 *
	 * @var bool
	 */
	protected bool $fields_via_constants = true;

	/**
	 * Name of the field, which contains the URL of the server this service connects to.
	 *
	 * If set, the values of the constants are only used for connections to the host defined
	 * by the constant for this field.
	 *
	 * @var string
	 */
	protected string $constants_host_field = '';

	/**
	 * List of fields, which identify the target of this service besides the server (e.g. a bucket).
	 *
	 * If set, the values of the constants are only used for fields, whose values for these fields are empty or
	 * equal to the values of the constants. Saved external sources with other values are not usable.
	 *
	 * @var array<int,string>
	 */
	protected array $constants_target_fields = array();

	/**
	 * Marker whether the values of the constants are values of the fields of this service (true) or not (false).
	 *
	 * Services, which use other credentials than their fields (e.g. API credentials for OAuth), set this to false.
	 * The values of their constants are then never added to any fields.
	 *
	 * @var bool
	 */
	protected bool $constants_in_fields = true;

	/**
	 * Marker whether the fields have been set from outside (e.g. from a request or a saved external source).
	 *
	 * @var bool
	 */
	private bool $fields_set_externally = false;

	/**
	 * Marker whether constants should not be used for the fields (used to load the field definitions).
	 *
	 * @var bool
	 */
	private bool $constants_disabled = false;

	/**
	 * The definitions of the fields without values from constants (cache).
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private ?array $field_definitions = null;

	/**
	 * The parsed names of the connections defined via constants (cache).
	 *
	 * @var array<string,array{valid: array<string,string>, invalid: array<int,string>}>
	 */
	private array $connection_names = array();

	/**
	 * Set the configuration for the external source of this service plugin.
	 *
	 * @var array<string,string>
	 */
	protected array $source_config = array(
		'type'        => '',
		'github_user' => '',
		'github_slug' => '',
		'plugin_slug' => '',
	);

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add settings.
		add_action( 'init', array( $this, 'add_base_settings' ), 20 );
		add_action( 'personal_options_update', array( $this, 'save_user_settings' ) );

		// use our own hooks.
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// use the mode "global" if the credentials are defined via constants.
		add_filter( 'option_eml_' . $this->get_name() . '_credentials_vault', array( $this, 'force_global_mode_via_constants' ) );
		add_filter( 'pre_update_option_eml_' . $this->get_name() . '_credentials_vault', array( $this, 'prevent_mode_change_via_constants' ) );
	}

	/**
	 * Return "global" as mode, if the credentials of this service are defined via constants.
	 *
	 * @param mixed $value The saved mode.
	 *
	 * @return mixed
	 */
	public function force_global_mode_via_constants( mixed $value ): mixed {
		// bail if mode is not forced by constants.
		if ( ! $this->is_mode_forced_by_constants() ) {
			return $value;
		}

		// return the mode used with constants.
		return $this->get_mode_for_constants();
	}

	/**
	 * Return the mode, which is used if the credentials of this service are defined via constants.
	 *
	 * @return string
	 */
	protected function get_mode_for_constants(): string {
		return 'global';
	}

	/**
	 * Prevent saving of the mode, if it is forced by constants, so the saved mode is used again after removing them.
	 *
	 * @param mixed $value The new value.
	 *
	 * @return mixed
	 */
	public function prevent_mode_change_via_constants( mixed $value ): mixed {
		// bail if mode is not forced by constants.
		if ( ! $this->is_mode_forced_by_constants() ) {
			return $value;
		}

		// return the saved mode without our filter.
		return $this->get_saved_mode();
	}

	/**
	 * Return whether the mode is forced to "global", as the credentials are defined via constants.
	 *
	 * @return bool
	 */
	public function is_mode_forced_by_constants(): bool {
		// bail if constants are not used at the moment (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return false;
		}

		// return whether all required values of any connection are defined via constants.
		return ! empty( $this->get_connections() );
	}

	/**
	 * Return the mode saved in the database, without the mode forced by constants.
	 *
	 * @return string
	 */
	private function get_saved_mode(): string {
		// get the option name.
		$option_name = 'eml_' . $this->get_name() . '_credentials_vault';

		// remove our filter for the mode temporarily, if it is set.
		$has_filter = false !== has_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		if ( $has_filter ) {
			remove_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		}

		// get the saved mode.
		$mode = get_option( $option_name, 'manually' );

		// add our filter again.
		if ( $has_filter ) {
			add_filter( 'option_' . $option_name, array( $this, 'force_global_mode_via_constants' ) );
		}

		// return the saved mode.
		return is_string( $mode ) ? $mode : 'manually';
	}

	/**
	 * Add this object to the list of directory listing objects.
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
	 * Add main settings for each service.
	 *
	 * @return void
	 */
	public function add_base_settings(): void {
		// bail if not subtab slug is given.
		if ( empty( $this->get_settings_subtab_slug() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( $settings_obj->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( 'services' );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// add a new tab for settings.
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 90 );
		$tab->set_title( $this->get_label() );

		// add a section for file statistics.
		$section = $tab->add_section( 'section_' . $this->get_name() . '_main', 10 );
		/* translators: %1$s will be replaced by the service title. */
		$section->set_title( sprintf( __( 'Settings for %1$s', 'external-files-in-media-library' ), $this->get_label() ) );
		$section->set_callback( array( $this, 'show_hint_for_permissions' ) );

		// show mode selection if this service is using credentials.
		if ( $this->has_credentials() ) {
			// add setting where to save the credentials for this service, if enabled.
			$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_credentials_vault' );
			$setting->set_type( 'string' );
			$setting->set_default( 'manually' );
			$setting->set_section( $section );
			$field = new Select( $settings_obj );
			$field->set_title( __( 'Location where credentials are stored', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by the service title (e.g. DropBox). */
			$field->set_description( sprintf( __( 'This setting determines where %1$s access data is stored. Depending on this setting, the access data must be entered each time a connection is made, or it can be stored for each user or all users in this project. This only affects the import of files. This does not affect files in the media library.', 'external-files-in-media-library' ), $this->get_label() ) );
			$field->set_options( $this->get_modes() );

			// fix the mode to "global", if the credentials are defined via constants.
			if ( $this->is_mode_forced_by_constants() ) {
				$field->set_readonly( true );
				/* translators: %1$s will be replaced by the service title (e.g. DropBox). */
				$field->set_description( sprintf( __( 'The access data for %1$s are defined as constants, e.g. in your wp-config.php. Therefore, they are used for the whole website and this setting could not be changed.', 'external-files-in-media-library' ), $this->get_label() ) );
			}
			if ( method_exists( $setting, 'set_reload_on_save' ) ) { // @phpstan-ignore function.alreadyNarrowedType
				$setting->set_reload_on_save( true );
			}
			$setting->set_field( $field );
		}

		// show hint if settings of this service are defined via constants.
		$description = $this->get_constants_settings_hint();
		if ( '' !== $description ) {
			$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_constants_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo( $settings_obj );
			$field->set_title( __( 'Defined in wp-config.php', 'external-files-in-media-library' ) );
			$field->set_description( $description );
			$setting->set_field( $field );
		}

		$instance = $this;
		/**
		 * Run additional tasks after the main settings of a service have been added, e.g. to add further settings.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param Section $section The main settings section of this service.
		 * @param Service_Base $instance The service object.
		 */
		do_action( 'efml_service_main_settings', $section, $instance );
	}

	/**
	 * Return whether files with the given mime type should be hidden in the directory listing.
	 *
	 * This is the case if the setting to hide not supported file types is enabled and the mime type is
	 * not allowed in the settings.
	 *
	 * @param string $mime_type The mime type.
	 *
	 * @return bool
	 */
	protected function is_mime_type_hidden( string $mime_type ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return false;
		}

		// return whether this file type is not allowed.
		return ! in_array( $mime_type, Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Return list of possible modes this service can be run.
	 *
	 * @return array<string,string>
	 */
	private function get_modes(): array {
		$modes = array(
			'manually' => __( 'Enter on each connection', 'external-files-in-media-library' ),
			'user'     => __( 'User-specific', 'external-files-in-media-library' ),
			'global'   => __( 'One for this website', 'external-files-in-media-library' ),
		);

		$instance = $this;

		/**
		 * Filter the list of possible modes of this service.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,string> $modes List of modes.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_modes', $modes, $instance );
	}

	/**
	 * Return the forward URL after activating this as plugin.
	 *
	 * @return string
	 */
	public function get_forward_url(): string {
		return Directory_Listing::get_instance()->get_view_directory_url( $this );
	}

	/**
	 * Return the URL of the web interface of the platform of this service (e.g. the website of DropBox).
	 *
	 * Used for the link in the directory listing and in the info box of external files of this service. Return an
	 * empty string if the service has no such platform.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return '';
	}

	/**
	 * Return whether this service supports the complete connection test.
	 *
	 * Services, which could be used to export files, should support it.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_connection_test(): bool {
		return false;
	}

	/**
	 * Run the complete connection test with the actual fields.
	 *
	 * Services return the list of their steps (see get_connection_test_steps()) after running them.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<int,array<string,mixed>> List of steps with "title", "success", "skipped" and "message".
	 */
	public function run_connection_test(): array {
		// get the URL for the log entries.
		$log_url = $this->get_directory();

		// log the start.
		$this->log_connection_test_start( $log_url );

		// get the steps.
		$steps = $this->get_connection_test_steps();

		// step 1: connect and list the directory.
		$steps['list']['skipped'] = false;
		try {
			$steps['list']['message'] = $this->test_connection_list();
			$steps['list']['success'] = true;
		} catch ( Throwable $e ) {
			$steps['list']['message'] = $e->getMessage();
			return $this->finish_connection_test( $steps, $log_url );
		}

		// step 2: upload a test file.
		$file_name                  = $this->get_connection_test_file_name();
		$local_file                 = $this->create_connection_test_file( $file_name );
		$steps['upload']['skipped'] = false;
		try {
			$url                        = $this->test_connection_upload( $local_file, $file_name );
			$steps['upload']['success'] = true;
			/* translators: %1$s will be replaced by a file name. */
			$steps['upload']['message'] = sprintf( __( 'The test file %1$s has been uploaded.', 'external-files-in-media-library' ), $url );
		} catch ( Throwable $e ) {
			$this->delete_connection_test_file( $local_file );
			$steps['upload']['message'] = $e->getMessage();
			return $this->finish_connection_test( $steps, $log_url );
		}

		// remove the local test file.
		$this->delete_connection_test_file( $local_file );

		// step 3: check the public availability.
		try {
			$public = $this->test_connection_public( $url );
		} catch ( Throwable $e ) {
			$public = false;
		}
		if ( is_null( $public ) ) {
			$steps['public']['message'] = __( 'Not necessary, as files of this service are delivered via your website.', 'external-files-in-media-library' );
		} else {
			$steps['public']['skipped'] = false;
			$steps['public']['success'] = $public;
			/* translators: %1$s will be replaced by a URL. */
			$steps['public']['message'] = $public ? sprintf( __( 'The test file is public available under %1$s.', 'external-files-in-media-library' ), $url ) : __( 'The test file is not public available. Files could be imported and exported with the option "Save files privately", but not exported as public files.', 'external-files-in-media-library' );
		}

		// step 4: delete the test file.
		$steps['delete']['skipped'] = false;
		try {
			$this->test_connection_delete( $url, $file_name );
			$steps['delete']['success'] = true;
			$steps['delete']['message'] = __( 'The test file has been deleted.', 'external-files-in-media-library' );
		} catch ( Throwable $e ) {
			/* translators: %1$s will be replaced by a file name. */
			$steps['delete']['message'] = $e->getMessage() . ' ' . sprintf( __( 'Please delete the test file %1$s manually.', 'external-files-in-media-library' ), $file_name );
		}

		// return the result.
		return $this->finish_connection_test( $steps, $log_url );
	}

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
	/**
	 * Connect and list the directory for the connection test.
	 *
	 * Services, which support the connection test, must override this.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string The message for this step.
	 * @throws Exception If the step failed (its message is shown).
	 */
	protected function test_connection_list(): string {
		throw new Exception( esc_html__( 'This service does not support the connection test.', 'external-files-in-media-library' ) );
	}
	// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn

	// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn
	/**
	 * Upload the given local test file for the connection test and return its URL.
	 *
	 * Services, which support the connection test, must override this.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $local_file The absolute path to the local test file.
	 * @param string $file_name  The name of the test file.
	 *
	 * @return string The URL (or path) of the uploaded file.
	 * @throws Exception If the step failed (its message is shown).
	 */
	protected function test_connection_upload( string $local_file, string $file_name ): string {
		throw new Exception( esc_html__( 'This service does not support the connection test.', 'external-files-in-media-library' ) );
	}
	// phpcs:enable Squiz.Commenting.FunctionComment.InvalidNoReturn

	/**
	 * Return whether the uploaded test file is public available.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url The URL of the uploaded file.
	 *
	 * @return bool|null Null, if the check is not necessary for this service (e.g. as its files are always delivered via the proxy).
	 */
	protected function test_connection_public( string $url ): ?bool {
		return null;
	}

	/**
	 * Delete the uploaded test file of the connection test.
	 *
	 * Services, which support the connection test, must override this.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url       The URL of the uploaded file.
	 * @param string $file_name The name of the test file.
	 *
	 * @return void
	 * @throws Exception If the step failed (its message is shown).
	 */
	protected function test_connection_delete( string $url, string $file_name ): void {
		throw new Exception( esc_html__( 'This service does not support the connection test.', 'external-files-in-media-library' ) );
	}

	/**
	 * Set the directory, in which the connection test should save its test file (e.g. the opened directory).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $directory The directory (as used in the directory listing of this service).
	 *
	 * @return void
	 */
	public function set_connection_test_directory( string $directory ): void {
		$this->connection_test_directory = $directory;
	}

	/**
	 * Return the directory, in which the connection test should save its test file.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string Empty string, if no directory is set (then the main directory of the connection is used).
	 */
	protected function get_connection_test_directory(): string {
		return $this->connection_test_directory;
	}

	/**
	 * Return the default steps of a connection test, all marked as skipped.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_connection_test_steps(): array {
		return array(
			'list'   => array(
				'title'   => __( 'Connect and list the directory', 'external-files-in-media-library' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'upload' => array(
				'title'   => __( 'Upload a test file', 'external-files-in-media-library' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'public' => array(
				'title'   => __( 'Check the public availability of the test file', 'external-files-in-media-library' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
			'delete' => array(
				'title'   => __( 'Delete the test file', 'external-files-in-media-library' ),
				'success' => false,
				'skipped' => true,
				'message' => '',
			),
		);
	}

	/**
	 * Return the name for a new test file of the connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	protected function get_connection_test_file_name(): string {
		// create the name.
		$file_name = 'efml-connection-test-' . wp_generate_password( 12, false ) . '.txt';

		$instance = $this;
		/**
		 * Filter the name of the test file of the connection test.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $file_name The file name.
		 * @param Service_Base $instance The service object.
		 */
		return (string) apply_filters( 'efml_connection_test_file_name', $file_name, $instance );
	}

	/**
	 * Return the content of the test file of the connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	protected function get_connection_test_file_content(): string {
		return __( 'This is a test file of the plugin "External Files in Media Library". It could be deleted.', 'external-files-in-media-library' );
	}

	/**
	 * Create a local test file for the connection test and return its path.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $file_name The name of the file.
	 *
	 * @return string
	 */
	protected function create_connection_test_file( string $file_name ): string {
		// create the file in a new temporary directory, so it has the given name.
		$directory = trailingslashit( get_temp_dir() ) . 'efml-connection-test-' . wp_generate_password( 8, false ) . '/';
		wp_mkdir_p( $directory );
		$file = $directory . $file_name;

		// save the content.
		Helper::get_wp_filesystem()->put_contents( $file, $this->get_connection_test_file_content() );

		// return the path.
		return $file;
	}

	/**
	 * Delete the local test file of the connection test with its temporary directory.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $file The path of the file.
	 *
	 * @return void
	 */
	protected function delete_connection_test_file( string $file ): void {
		$wp_filesystem = Helper::get_wp_filesystem();
		$wp_filesystem->delete( $file );
		$wp_filesystem->rmdir( dirname( $file ) );
	}

	/**
	 * Log the start of the connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $log_url The URL for the log entries.
	 *
	 * @return void
	 */
	protected function log_connection_test_start( string $log_url ): void {
		/* translators: %1$s will be replaced by the name of the service. */
		Log::get_instance()->create( sprintf( __( 'Starting the connection test for %1$s.', 'external-files-in-media-library' ), '<em>' . esc_html( $this->get_label() ) . '</em>' ), $log_url, 'info' );
	}

	/**
	 * Log the results of the connection test and return the steps as list.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param array<string,array<string,mixed>> $steps   The steps.
	 * @param string                            $log_url The URL for the log entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function finish_connection_test( array $steps, string $log_url ): array {
		foreach ( $steps as $step ) {
			// bail if step has been skipped.
			if ( $step['skipped'] ) {
				continue;
			}

			// log the result.
			Log::get_instance()->create( esc_html( $step['title'] ) . ': ' . esc_html( $step['message'] ), $log_url, $step['success'] ? 'success' : 'error' );
		}

		// return the steps as list.
		return array_values( $steps );
	}

	/**
	 * Return global actions.
	 *
	 * Adds the complete connection test, if the service supports it.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		// get the actions of the directory listing.
		$actions = parent::get_global_actions();

		// add the connection test, if supported.
		if ( $this->supports_connection_test() ) {
			$actions[] = array(
				'action' => 'efml_connection_test( "' . $this->get_name() . '", config.fields, config.term, actualDirectoryPath );',
				'label'  => __( 'Run connection test', 'external-files-in-media-library' ),
			);
		}

		// return the resulting list.
		return $actions;
	}

	/**
	 * Return whether a specific service is used.
	 *
	 * @param string $mode The name of the service.
	 *
	 * @return bool
	 */
	protected function is_mode( string $mode ): bool {
		return $mode === $this->get_mode();
	}

	/**
	 * Return the actual mode this service is using.
	 *
	 * @return string
	 */
	protected function get_mode(): string {
		// use the mode for constants, if the credentials are defined via constants.
		if ( $this->is_mode_forced_by_constants() ) {
			return $this->get_mode_for_constants();
		}

		// return the saved mode.
		return $this->get_saved_mode();
	}

	/**
	 * Show hint where to edit permissions to use this service.
	 *
	 * @return void
	 */
	public function show_hint_for_permissions(): void {
		/* translators: %1$s will be replaced by the service name, %2$s by a URL. */
		echo wp_kses_post( sprintf( __( 'Set permission who could use the %1$s service <a href="%2$s">here</a>.', 'external-files-in-media-library' ), $this->get_label(), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_permissions' ) ) );
	}

	/**
	 * Return the settings slug.
	 *
	 * @return string
	 */
	protected function get_settings_tab_slug(): string {
		return $this->settings_tab;
	}

	/**
	 * Return the settings sub tab slug.
	 *
	 * @return string
	 */
	protected function get_settings_subtab_slug(): string {
		return $this->settings_sub_tab;
	}

	/**
	 * Show user settings table, if settings are defined for this object.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return void
	 */
	protected function get_user_settings_table( int $user_id ): void {
		// get the settings.
		$settings = $this->get_user_settings();

		// bail if no settings are defined.
		if ( empty( $settings ) ) {
			return;
		}

		// show settings as table.
		?>
		<table class="form-table" role="presentation">
			<?php
			foreach ( $settings as $name => $setting ) {
				// show only a hint, if the value of this setting is defined via constant.
				$constant_hint = $this->get_constant_hint_for_field( $this->get_field_name_of_user_setting( (string) $name ) );
				if ( '' !== $constant_hint ) {
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $setting['label'] ); ?></th>
						<td><p><?php echo wp_kses_post( $constant_hint ); ?></p></td>
					</tr>
					<?php
					continue;
				}

				// get actual value.
				$value = Crypt::get_instance()->decrypt( get_user_meta( $user_id, 'efml_' . $name, true ) );

				// if no value is set, use the default value, if set.
				if ( empty( $value ) && ! empty( $setting['default'] ) ) {
					$value = $setting['default'];
				}

				// get the placeholder for this field.
				$placeholder = '';
				if ( ! empty( $setting['placeholder'] ) ) {
					$placeholder = $setting['placeholder'];
				}

				// output.
				?>
				<tr>
					<th scope="row"><label for="efml_<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $setting['label'] ); ?></label></th>
					<td>
						<?php
						switch ( $setting['field'] ) {
							case 'checkbox':
								echo '<input type="checkbox" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" value="1"' . ( 1 === absint( $value ) ? ' checked="checked"' : '' ) . ( ! empty( $setting['readonly'] ) ? ' disabled="disabled"' : '' ) . '>';
								break;
							case 'password':
								echo '<input type="password" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $value ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								break;
							case 'textarea':
								echo '<textarea id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . ' placeholder="' . esc_attr( $placeholder ) . '">' . esc_html( $value ) . '</textarea>';
								break;
							case 'text':
								echo '<input type="text" id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								break;
							case 'select':
								echo '<select id="efml_' . esc_attr( $name ) . '" name="efml_' . esc_attr( $name ) . '"' . ( ! empty( $setting['readonly'] ) ? ' readonly="readonly"' : '' ) . '>';
								foreach ( $setting['options'] as $key => $label ) {
									echo '<option value="' . esc_attr( $key ) . '"' . ( $key === $value ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
								}
								echo '</select>';
								break;
							default:
								break;
						}

						// show description for this field, if given.
						if ( isset( $setting['description'] ) ) {
							?>
							<p><?php echo wp_kses_post( $setting['description'] ); ?></p>
							<?php
						}
						?>
					</td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		return array();
	}

	/**
	 * Save user-specific options.
	 *
	 * @param int $user_id The ID of the user.
	 *
	 * @return void
	 */
	public function save_user_settings( int $user_id ): void {
		// check for nonce.
		if ( isset( $_GET['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'eml-nonce' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		// loop through the settings and save them.
		foreach ( $this->get_user_settings() as $name => $setting ) {
			// bail if the value of this setting is defined via constant.
			if ( $this->is_field_defined_via_constants( $this->get_field_name_of_user_setting( (string) $name ) ) ) {
				continue;
			}

			// get the settings full name.
			$full_name = 'efml_' . $name;

			// get the value from request depending on field type.
			switch ( $setting['field'] ) {
				case 'checkbox':
					$value = isset( $_POST[ $full_name ] ) ? absint( $_POST[ $full_name ] ) : 0;
					break;
				case 'textarea':
					$value = isset( $_POST[ $full_name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $full_name ] ) ) : '';
					break;
				default:
					$value = isset( $_POST[ $full_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $full_name ] ) ) : '';
			}

			// save the value in the database.
			update_user_meta( $user_id, 'efml_' . $name, Crypt::get_instance()->encrypt( (string) $value ) );
		}
	}

	/**
	 * Return the config URL.
	 *
	 * @return string
	 */
	protected function get_config_url(): string {
		// use the directory URL in the manual mode.
		if ( $this->is_mode( 'manually' ) ) {
			return Directory_Listing::get_instance()->get_view_directory_url( $this );
		}

		// use the global settings in the global mode.
		if ( $this->is_mode( 'global' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}
			return Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
		}

		// use the profile in user mode.
		return get_admin_url() . 'profile.php#efml-' . $this->get_name();
	}

	/**
	 * Return the tab with the settings of this service. Returns false, if this service has no settings.
	 *
	 * @return Tab|false
	 */
	private function get_settings_tab(): Tab|false {
		// bail if this service has no own settings tab.
		if ( empty( $this->get_settings_subtab_slug() ) ) {
			return false;
		}

		// get the settings page.
		$settings_page = Settings::get_instance()->get_settings_obj()->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return false;
		}

		// get the tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return false;
		}

		// return the tab of this service, if it exists.
		return $services_tab->get_tab( $this->get_settings_subtab_slug() );
	}

	/**
	 * Return the URL of the settings of this service. Returns an empty string, if this service has no settings.
	 *
	 * @return string
	 */
	public function get_settings_url(): string {
		// bail if the tab of this service does not exist.
		if ( ! $this->get_settings_tab() instanceof Tab ) {
			return '';
		}

		// return the URL.
		return Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
	}

	/**
	 * Return the title of this service in the settings: the title of its tab or its label.
	 *
	 * @return string
	 */
	public function get_settings_title(): string {
		// get the tab.
		$tab = $this->get_settings_tab();

		// use the title of the tab, if set.
		if ( $tab instanceof Tab && '' !== $tab->get_title() ) {
			return $tab->get_title();
		}

		// return the label.
		return $this->get_label();
	}

	/**
	 * Return the user to use.
	 *
	 * @return WP_User|false
	 */
	protected function get_user(): WP_User|false {
		// if no user is set, use the current one.
		if ( empty( $this->user ) ) {
			return wp_get_current_user();
		}

		// return the configured user.
		return $this->user;
	}

	/**
	 * Set user this object should use.
	 *
	 * @param WP_User $user The user object.
	 *
	 * @return void
	 */
	public function set_user( WP_User $user ): void {
		$this->user = $user;
	}

	/**
	 * Return whether this object does not allow sync.
	 *
	 * @return bool
	 */
	public function is_sync_disabled(): bool {
		return $this->sync_disabled;
	}

	/**
	 * Initiate the WP CLI support for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {}

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

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
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

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * Values of fields, which are defined as constants (e.g. in wp-config.php), replace the configured values.
	 * The name of the constant is "EFML_" + the service name + "_" + the field name in uppercase,
	 * e.g. "EFML_FTP_PASSWORD" for the field "password" of the service "ftp".
	 *
	 * The constants are only used if the constants for all required fields are defined.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// get the fields.
		$fields = parent::get_fields();

		// bail if constants should not be used (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return $fields;
		}

		// add the field to choose the connection, if several connections are defined via constants.
		$fields = $this->add_connection_field( $fields );

		// replace the values with the values of constants, if they are defined. The values of the target fields
		// are only checked, if the fields have been set from outside (e.g. by a saved external source).
		$fields = $this->apply_constants_to_fields( $fields, $this->directory, false, $this->fields_set_externally );

		$instance = $this;
		/**
		 * Filter the fields of a service incl. their values before they are used.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,array<string,mixed>> $fields List of fields.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_fields', $fields, $instance );
	}

	/**
	 * Set the fields.
	 *
	 * @param array<string,array<string,mixed>> $fields List of fields.
	 *
	 * @return void
	 */
	public function set_fields( array $fields ): void {
		parent::set_fields( $fields );
		$this->fields_set_externally = ! empty( $fields );
	}

	/**
	 * Add the field to choose the connection to the given fields, if several connections are defined via constants.
	 *
	 * The field is removed, if not several connections are defined.
	 *
	 * @param array<string,array<string,mixed>> $fields List of fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function add_connection_field( array $fields ): array {
		// get the connections.
		$connections = $this->get_connections();

		// get the chosen connection.
		$chosen = $this->get_chosen_connection( $fields );

		// remove the field, it is added again with the actual options.
		unset( $fields[ self::CONNECTION_FIELD ] );

		// bail if not several connections are defined.
		if ( count( $connections ) < 2 ) {
			return $fields;
		}

		// use the connection matching the fields or the first connection, if the chosen one does not exist.
		if ( ! isset( $connections[ $chosen ] ) ) {
			$chosen = $this->get_connection_for_fields( $fields, $this->directory, $this->fields_set_externally ) ?? (string) array_key_first( $connections );
		}

		// collect the options.
		$options = array();
		foreach ( array_keys( $connections ) as $connection ) {
			$options[] = array(
				'value' => (string) $connection,
				'label' => $this->get_connection_label( (string) $connection ),
			);
		}

		// add the field as first field.
		return array(
			self::CONNECTION_FIELD => array(
				'name'        => self::CONNECTION_FIELD,
				'type'        => 'select',
				'label'       => __( 'Connection', 'external-files-in-media-library' ),
				'description' => __( 'The access data are defined as constants for several connections, e.g. in your wp-config.php. Choose the connection to use.', 'external-files-in-media-library' ),
				'options'     => $options,
				'value'       => $chosen,
			),
		) + $fields;
	}

	/**
	 * Return the label of the given connection: its name and, if set, its server or target (e.g. a bucket).
	 *
	 * @param string $connection The name of the connection.
	 *
	 * @return string
	 */
	public function get_connection_label( string $connection ): string {
		// get the name.
		$label = __( 'Default', 'external-files-in-media-library' );
		if ( self::DEFAULT_CONNECTION !== $connection ) {
			$names = $this->get_connection_names();
			$label = isset( $names[ $connection ] ) ? $names[ $connection ] : $connection;
		}

		// get the values of this connection.
		$values = $this->get_constant_values( $connection );

		// get the server and the targets (e.g. a bucket) as details.
		$details = array();
		if ( ! empty( $this->constants_host_field ) && isset( $values[ $this->constants_host_field ] ) && is_scalar( $values[ $this->constants_host_field ] ) ) {
			$details[] = $this->get_host_from_url( (string) $values[ $this->constants_host_field ] );
		}
		foreach ( $this->constants_target_fields as $field_name ) {
			// bail if the field is not set.
			if ( ! isset( $values[ $field_name ] ) || ! is_scalar( $values[ $field_name ] ) || '' === (string) $values[ $field_name ] ) {
				continue;
			}

			// add its value.
			$details[] = (string) $values[ $field_name ];
		}

		// get the details as text.
		$detail = implode( ', ', array_filter( $details ) );

		// bail if no detail is set.
		if ( '' === $detail ) {
			return $label;
		}

		/* translators: %1$s will be replaced by the name of a connection, %2$s by its server or e.g. its bucket and region. */
		return sprintf( __( '%1$s (%2$s)', 'external-files-in-media-library' ), $label, $detail );
	}

	/**
	 * Return the hint for the settings of this service, which shows the settings defined via constants.
	 *
	 * Returns an empty string, if no constant of this service is defined.
	 *
	 * @return string
	 */
	private function get_constants_settings_hint(): string {
		// get the constants of the default connection.
		$constants = $this->get_defined_constants_for_fields();

		// get the named connections.
		$connections_constant = $this->get_connections_constant_name();
		$names                = $this->get_connection_names();
		$invalid_names        = $this->get_invalid_connection_names();

		// show only the default connection, if no named connections are listed.
		if ( empty( $names ) && empty( $invalid_names ) ) {
			// bail if no constant is defined.
			if ( empty( $constants ) ) {
				return '';
			}

			// get the missing constants.
			$missing_constants = $this->get_missing_constants_for_fields();

			// show hint for missing constants.
			if ( ! empty( $missing_constants ) ) {
				/* translators: %1$s will be replaced by a list of constant names, %2$s by a list of constant names. */
				return '<strong>' . sprintf( __( 'The constants %1$s are defined, e.g. in your wp-config.php, but they are not used, because the constants for all required settings must be defined. The following constants are missing: %2$s', 'external-files-in-media-library' ), $this->get_constant_list_for_output( $constants ), $this->get_constant_list_for_output( $missing_constants ) ) . '</strong>';
			}

			/* translators: %1$s will be replaced by a list of constant names. */
			$description = sprintf( __( 'The following settings are defined as constants, e.g. in your wp-config.php. They are used for every connection to this service and replace the values entered here, in the user profiles or in your external sources: %1$s', 'external-files-in-media-library' ), $this->get_constant_list_for_output( $constants ) );

			// add hint for the host binding, if used.
			if ( ! empty( $this->constants_host_field ) ) {
				$description .= ' ' . __( 'The values are only used for connections to the server defined by the constants.', 'external-files-in-media-library' );
			}

			// return the hint.
			return $description;
		}

		// collect the connections with defined constants.
		$list = array();
		foreach ( array_merge( array( self::DEFAULT_CONNECTION ), array_keys( $names ) ) as $connection ) {
			// get the defined constants of this connection.
			$connection_constants = $this->get_defined_constants_for_fields( (string) $connection );

			// get its missing constants.
			$missing_constants = $this->get_missing_constants_for_fields( false, (string) $connection );

			// bail if the default connection is not used.
			if ( self::DEFAULT_CONNECTION === $connection && empty( $connection_constants ) ) {
				continue;
			}

			// show the missing constants.
			if ( ! empty( $missing_constants ) ) {
				/* translators: %1$s will be replaced by a list of constant names. */
				$list[] = '<li><strong>' . esc_html( $this->get_connection_label( (string) $connection ) ) . ':</strong> <strong>' . sprintf( __( 'Not used, because the following constants are missing: %1$s', 'external-files-in-media-library' ), $this->get_constant_list_for_output( $missing_constants ) ) . '</strong></li>';
				continue;
			}

			// show the defined constants.
			$list[] = '<li><strong>' . esc_html( $this->get_connection_label( (string) $connection ) ) . ':</strong> ' . $this->get_constant_list_for_output( $connection_constants ) . '</li>';
		}

		/* translators: %1$s will be replaced by the name of a constant. */
		$description = sprintf( __( 'The access data are defined as constants for the connections listed in %1$s, e.g. in your wp-config.php. They replace the values entered here, in the user profiles or in your external sources.', 'external-files-in-media-library' ), '<code>' . esc_html( $connections_constant ) . '</code>' );

		// add the list.
		if ( ! empty( $list ) ) {
			$description .= '<ul>' . implode( '', $list ) . '</ul>';
		}

		// add hint for the host binding, if used.
		if ( ! empty( $this->constants_host_field ) ) {
			$description .= ' ' . __( 'The values of each connection are only used for connections to its server.', 'external-files-in-media-library' );
		}

		// add hint for the choice of the connection, if several connections are usable.
		if ( count( $this->get_connections() ) > 1 ) {
			$description .= ' ' . __( 'When you add an external source, choose the connection to use.', 'external-files-in-media-library' );
		}

		// add hint for invalid names.
		if ( ! empty( $invalid_names ) ) {
			/* translators: %1$s will be replaced by the name of a constant, %2$s by a list of names. */
			$description .= '<p><strong>' . sprintf( __( 'The following names in %1$s could not be used as connection names: %2$s. Use only letters, numbers, hyphens and underscores. The name "default", duplicates and names, which result in the names of other constants, are not possible.', 'external-files-in-media-library' ), '<code>' . esc_html( $connections_constant ) . '</code>', '<em>' . implode( '</em>, <em>', array_map( 'esc_html', $invalid_names ) ) . '</em>' ) . '</strong></p>';
		}

		// return the hint.
		return $description;
	}

	/**
	 * Return the configuration for the directory listing in the backend.
	 *
	 * Values of credential fields, which are defined as constants, are never sent to the browser.
	 * They are set again on the server via @get_fields() for each request.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		// get the configuration.
		$config = parent::get_config();

		// bail if no fields are set.
		if ( empty( $config['fields'] ) || ! is_array( $config['fields'] ) ) {
			return $config;
		}

		// get whether several connections are defined via constants.
		$several_connections = count( $this->get_connections() ) > 1;

		// check each field.
		foreach ( $config['fields'] as $name => $field ) {
			// bail if field is not an array or is the field to choose the connection.
			if ( ! is_array( $field ) || self::CONNECTION_FIELD === $name ) {
				continue;
			}

			// hide the values of all fields defined via constants, if several connections are defined, as the
			// values depend on the chosen connection.
			if ( $several_connections && $this->is_field_defined_via_constants( (string) $name ) ) {
				$config['fields'][ $name ]['readonly']     = true;
				$config['fields'][ $name ]['not_required'] = true;
				$config['fields'][ $name ]['value']        = '';
				$config['fields'][ $name ]['placeholder']  = __( 'Defined via the chosen connection', 'external-files-in-media-library' );
				continue;
			}

			// bail if field is not defined via constant.
			if ( empty( $field['constant'] ) ) {
				continue;
			}

			// mark field as not editable and not required in the form.
			$config['fields'][ $name ]['readonly']     = true;
			$config['fields'][ $name ]['not_required'] = true;

			// bail if this is the field with the server, which is visible to show which server is used.
			if ( $name === $this->constants_host_field && ( empty( $field['type'] ) || 'password' !== $field['type'] ) ) {
				continue;
			}

			// bail if this is not a credential field.
			if ( empty( $field['credential'] ) && ( empty( $field['type'] ) || 'password' !== $field['type'] ) ) {
				continue;
			}

			// remove the value and show a hint instead.
			$config['fields'][ $name ]['value'] = '';
			/* translators: %1$s will be replaced by the name of a constant. */
			$config['fields'][ $name ]['placeholder'] = sprintf( __( 'Defined via %1$s', 'external-files-in-media-library' ), $field['constant'] );
		}

		// return the resulting configuration.
		return $config;
	}

	/**
	 * Return whether values of fields of this service could be defined via constants.
	 *
	 * @return bool
	 */
	public function is_fields_via_constants_enabled(): bool {
		return $this->fields_via_constants;
	}

	/**
	 * Return the name of the constant, which could be used to define the value of the given field.
	 *
	 * The constants of the default connection are named "EFML_" + the service name + "_" + the field name,
	 * e.g. "EFML_FTP_PASSWORD". The constants of a named connection contain the name of the connection
	 * after the service name, e.g. "EFML_FTP_SHOP_PASSWORD" for the connection "shop".
	 *
	 * @param string $field_name The name of the field.
	 * @param string $connection The name of the connection (optional, the default connection is used if not set).
	 *
	 * @return string
	 */
	public function get_constant_name_for_field( string $field_name, string $connection = self::DEFAULT_CONNECTION ): string {
		// get the name parts.
		$parts = array( $this->get_name(), $field_name );
		if ( self::DEFAULT_CONNECTION !== $connection ) {
			$parts = array( $this->get_name(), $connection, $field_name );
		}

		// create the name.
		$constant_name = 'EFML_' . $this->get_normalized_constant_part( implode( '_', $parts ) );

		$instance = $this;
		/**
		 * Filter the name of the constant for a field of a service.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $constant_name The name of the constant.
		 * @param string $field_name The name of the field.
		 * @param Service_Base $instance The service object.
		 * @param string $connection The name of the connection ("default" for the constants without connection name).
		 */
		return apply_filters( 'efml_service_field_constant_name', $constant_name, $field_name, $instance, $connection );
	}

	/**
	 * Return the name of the constant, which contains the comma-separated list of named connections of this service,
	 * e.g. "EFML_FTP_CONNECTIONS".
	 *
	 * @return string
	 */
	public function get_connections_constant_name(): string {
		// create the name.
		$constant_name = 'EFML_' . $this->get_normalized_constant_part( $this->get_name() ) . '_CONNECTIONS';

		$instance = $this;
		/**
		 * Filter the name of the constant, which contains the list of named connections of a service.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $constant_name The name of the constant.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_connections_constant_name', $constant_name, $instance );
	}

	/**
	 * Return the given text in the form used in constant names: uppercase, other characters than letters and
	 * numbers replaced by an underscore.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function get_normalized_constant_part( string $text ): string {
		return strtoupper( (string) preg_replace( '/[^a-zA-Z0-9]+/', '_', $text ) );
	}

	/**
	 * Return whether this service supports several connections defined via constants.
	 *
	 * Services, whose constants are not values of their fields (e.g. API credentials for OAuth), only use
	 * the default connection.
	 *
	 * @return bool
	 */
	public function supports_constant_connections(): bool {
		return $this->constants_in_fields && $this->is_fields_via_constants_enabled();
	}

	/**
	 * Return the names of the named connections of this service, which are listed in the constant for them.
	 *
	 * The key is the name used internally and in the constant names (in lowercase), the value the name as written
	 * in the constant. Names, which could not be used, are returned by @get_invalid_connection_names().
	 *
	 * @return array<string,string>
	 */
	public function get_connection_names(): array {
		return $this->get_parsed_connection_names()['valid'];
	}

	/**
	 * Return the names of the connections, which are listed in the constant, but could not be used.
	 *
	 * This is the case for names with other characters than letters, numbers, hyphens and underscores, for duplicates,
	 * for the reserved name "default" and for names, whose constant names would be equal to the names of other
	 * constants (e.g. a connection "cloudfront" and a field "domain" for a service with the field "cloudfront_domain").
	 *
	 * @return array<int,string>
	 */
	public function get_invalid_connection_names(): array {
		return $this->get_parsed_connection_names()['invalid'];
	}

	/**
	 * Return the valid and invalid names of the connections, which are listed in the constant for them.
	 *
	 * @return array{valid: array<string,string>, invalid: array<int,string>}
	 */
	private function get_parsed_connection_names(): array {
		// set the return value.
		$result = array(
			'valid'   => array(),
			'invalid' => array(),
		);

		// bail if this service does not support named connections.
		if ( ! $this->supports_constant_connections() ) {
			return $result;
		}

		// get the name of the constant.
		$constant_name = $this->get_connections_constant_name();

		// bail if the constant is not defined.
		if ( ! defined( $constant_name ) ) {
			return $result;
		}

		// get its value.
		$value = constant( $constant_name );

		// bail if the value is not a string.
		if ( ! is_string( $value ) ) {
			return $result;
		}

		// return the result from the cache, if it is set.
		$cache_key = $constant_name . '|' . $value;
		if ( isset( $this->connection_names[ $cache_key ] ) ) {
			return $this->connection_names[ $cache_key ];
		}

		// get the field names.
		$field_names = array_map( 'strval', array_keys( $this->get_constant_field_definitions() ) );

		// collect the names of the constants of the default connection and of other services, whose constants
		// could be equal to the constants of a named connection of this service.
		$used_constant_names = array();
		foreach ( $field_names as $field_name ) {
			$used_constant_names[ $this->get_normalized_constant_part( $this->get_name() . '_' . $field_name ) ] = true;
		}
		foreach ( $this->get_constant_names_of_similar_services() as $name ) {
			$used_constant_names[ $name ] = true;
		}

		// check each name.
		foreach ( explode( ',', $value ) as $name ) {
			// remove spaces.
			$name = trim( $name );

			// bail if the name is empty.
			if ( '' === $name ) {
				continue;
			}

			// get the key.
			$key = strtolower( $name );

			// check the characters, the reserved name and duplicates.
			$is_valid = 1 === preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $key ) && self::DEFAULT_CONNECTION !== $key && ! isset( $result['valid'][ $key ] );

			// get the names of the constants of this connection and check whether they are already used.
			$constant_names = array();
			foreach ( $field_names as $field_name ) {
				$constant_names[] = $this->get_normalized_constant_part( $this->get_name() . '_' . $key . '_' . $field_name );
			}
			if ( $is_valid && ! empty( array_intersect_key( array_flip( $constant_names ), $used_constant_names ) ) ) {
				$is_valid = false;
			}

			// add invalid names to the list.
			if ( ! $is_valid ) {
				$result['invalid'][] = $name;
				continue;
			}

			// add the name to the list.
			$result['valid'][ $key ] = $name;

			// mark its constant names as used.
			foreach ( $constant_names as $constant_name_of_connection ) {
				$used_constant_names[ $constant_name_of_connection ] = true;
			}
		}

		// save the result in the cache.
		$this->connection_names[ $cache_key ] = $result;

		// return the result.
		return $result;
	}

	/**
	 * Return the normalized names (without prefix "EFML_") of the constants of the default connections of other
	 * services, whose names start with the name of this service (e.g. "s3-compatible" for a service "s3").
	 *
	 * Their constants could otherwise be equal to the constants of a named connection of this service.
	 *
	 * @return array<int,string>
	 */
	private function get_constant_names_of_similar_services(): array {
		// get the normalized name of this service.
		$prefix = $this->get_normalized_constant_part( $this->get_name() ) . '_';

		// check each service.
		$list = array();
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $service_obj ) {
			// bail if this is not a service object or this service.
			if ( ! $service_obj instanceof Service_Base || $service_obj->get_name() === $this->get_name() ) {
				continue;
			}

			// bail if the name of the service does not start with the name of this service.
			if ( ! str_starts_with( $this->get_normalized_constant_part( $service_obj->get_name() ) . '_', $prefix ) ) {
				continue;
			}

			// add the names of its constants.
			foreach ( array_keys( $service_obj->get_constant_field_definitions() ) as $field_name ) {
				$list[] = $this->get_normalized_constant_part( $service_obj->get_name() . '_' . $field_name );
			}
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the definitions of the fields, which values could be defined via constants.
	 *
	 * The definitions are loaded without any values from constants. Services, which do not use
	 * fields for their credentials (e.g. via OAuth), could override this to define their own list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_constant_field_definitions(): array {
		// bail if this service does not support constants.
		if ( ! $this->is_fields_via_constants_enabled() ) {
			return array();
		}

		// return the cached definitions, if set.
		if ( is_array( $this->field_definitions ) ) {
			return $this->field_definitions;
		}

		// load the definitions of the fields without the actual values of this object
		// (services are singletons, so the actual fields are saved and restored afterward).
		$actual_fields            = $this->fields;
		$this->fields             = array();
		$this->constants_disabled = true;
		try {
			$fields = $this->get_fields();
		} finally {
			$this->fields             = $actual_fields;
			$this->constants_disabled = false;
		}

		// collect the definitions.
		$definitions = array();
		foreach ( $fields as $name => $field ) {
			// bail if field is not an array.
			if ( ! is_array( $field ) ) { // @phpstan-ignore function.alreadyNarrowedType
				continue;
			}

			// bail if field could not be set via constant (e.g., a hidden field or a file upload).
			if ( isset( $field['type'] ) && in_array( $field['type'], array( 'hidden', 'file' ), true ) ) {
				continue;
			}

			// bail if this is the field to choose the connection.
			if ( self::CONNECTION_FIELD === $name ) {
				continue;
			}

			// add the field without any value.
			unset( $field['value'], $field['readonly'] );
			$definitions[ (string) $name ] = $field;
		}

		// save them in the cache.
		$this->field_definitions = $definitions;

		// return the resulting list.
		return $definitions;
	}

	/**
	 * Return the list of required fields, which must all be defined via constants to use any constant.
	 *
	 * @return array<int,string>
	 */
	public function get_required_fields_for_constants(): array {
		// collect the required fields.
		$required_fields = array();
		foreach ( $this->get_constant_field_definitions() as $name => $field ) {
			// bail if field is not required.
			if ( ! empty( $field['not_required'] ) ) {
				continue;
			}

			// add it to the list.
			$required_fields[] = $name;
		}

		$instance = $this;
		/**
		 * Filter the list of fields of a service, which must all be defined via constants,
		 * before any constant of this service is used.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $required_fields List of field names.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_required_fields_for_constants', $required_fields, $instance );
	}

	/**
	 * Return all connections of this service, whose constants for all required fields are defined.
	 *
	 * The key is the name of the connection ("default" for the constants without connection name), the value
	 * the list of values of the fields defined via constants. The default connection is always the first entry.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_connections(): array {
		// bail if constants are not used at the moment (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return array();
		}

		// collect the connections.
		$connections = array();
		foreach ( array_merge( array( self::DEFAULT_CONNECTION ), array_keys( $this->get_connection_names() ) ) as $connection ) {
			// get the values of this connection.
			$values = $this->get_constant_values_of_connection( (string) $connection );

			// bail if not all required values are defined.
			if ( empty( $values ) ) {
				continue;
			}

			// add the connection.
			$connections[ (string) $connection ] = $values;
		}

		// return the resulting list.
		return $connections;
	}

	/**
	 * Return the values of all fields of the given connection, which are defined via constants.
	 *
	 * Returns an empty list if the constant for any required field of this connection is missing.
	 *
	 * @param string $connection The name of the connection.
	 *
	 * @return array<string,mixed>
	 */
	private function get_constant_values_of_connection( string $connection ): array {
		// get the definitions.
		$definitions = $this->get_constant_field_definitions();

		// bail if no definitions are given.
		if ( empty( $definitions ) ) {
			return array();
		}

		// bail if any required field is not defined via constant.
		if ( ! empty( $this->get_missing_constants_for_fields( false, $connection ) ) ) {
			return array();
		}

		// collect the values.
		$values = array();
		foreach ( array_keys( $definitions ) as $name ) {
			// get the constant name for this field.
			$constant_name = $this->get_constant_name_for_field( $name, $connection );

			// bail if constant is not defined.
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			// add the value.
			$values[ $name ] = constant( $constant_name );
		}

		// return the resulting list.
		return $values;
	}

	/**
	 * Return the values of all fields, which are defined via constants for the given connection.
	 *
	 * Without a connection, the connection for the actual fields of this object is used (see @get_actual_connection()).
	 * Returns an empty list if the constant for any required field of the connection is missing.
	 *
	 * @param string|null $connection The name of the connection (optional).
	 *
	 * @return array<string,mixed>
	 */
	public function get_constant_values( ?string $connection = null ): array {
		// bail if constants are not used at the moment (e.g., while the field definitions are loaded).
		if ( $this->constants_disabled ) {
			return array();
		}

		// get the connection for the actual fields, if none is given.
		if ( is_null( $connection ) ) {
			$connection = $this->get_actual_connection();
		}

		// bail if no connection could be used.
		if ( is_null( $connection ) ) {
			return array();
		}

		// get the connections.
		$connections = $this->get_connections();

		// return the values of the connection, if it exists.
		return isset( $connections[ $connection ] ) ? $connections[ $connection ] : array();
	}

	/**
	 * Return the value of a single field, if it is defined via constant and all required constants are defined.
	 *
	 * Without a connection, the connection for the actual fields of this object is used.
	 *
	 * @param string      $field_name The name of the field.
	 * @param string|null $connection The name of the connection (optional).
	 *
	 * @return string|null
	 */
	public function get_constant_value( string $field_name, ?string $connection = null ): ?string {
		// get the values.
		$values = $this->get_constant_values( $connection );

		// bail if field is not set.
		if ( ! isset( $values[ $field_name ] ) || ! is_scalar( $values[ $field_name ] ) ) {
			return null;
		}

		// return the value.
		return (string) $values[ $field_name ];
	}

	/**
	 * Return whether the value of the given field is defined via constant in any connection of this service.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return bool
	 */
	public function is_field_defined_via_constants( string $field_name ): bool {
		return ! empty( $this->get_connections_with_field( $field_name ) );
	}

	/**
	 * Return the connections, which define the given field via constant, with their values for this field.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return array<string,string>
	 */
	private function get_connections_with_field( string $field_name ): array {
		$list = array();
		foreach ( $this->get_connections() as $connection => $values ) {
			// bail if field is not set.
			if ( ! isset( $values[ $field_name ] ) || ! is_scalar( $values[ $field_name ] ) ) {
				continue;
			}

			// add the connection.
			$list[ $connection ] = (string) $values[ $field_name ];
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the name of the connection, which is used for the actual fields and directory of this object.
	 *
	 * Returns null if constants are not used for these fields.
	 *
	 * @return string|null
	 */
	protected function get_actual_connection(): ?string {
		return $this->get_connection_for_fields( $this->fields, $this->directory, $this->fields_set_externally );
	}

	/**
	 * Return the name of the connection, whose constants should be used for the given fields and URL.
	 *
	 * The connection chosen in the fields is used, if it exists. Otherwise, the first connection, whose server
	 * and target (e.g. a bucket) are matching the URL and the fields, is used, beginning with the default connection.
	 * Returns null if no connection could be used. Then no constant is used for these fields.
	 *
	 * @param array<string,mixed> $fields              List of fields.
	 * @param string              $url                 The URL, which should be used with these fields (optional).
	 * @param bool                $check_target_fields Whether the values of the target fields should be checked.
	 *
	 * @return string|null
	 */
	public function get_connection_for_fields( array $fields, string $url = '', bool $check_target_fields = true ): ?string {
		// get the connections.
		$connections = $this->get_connections();

		// bail if no connection is defined.
		if ( empty( $connections ) ) {
			return null;
		}

		// use the chosen connection, if it exists, otherwise check all connections.
		$candidates = array_keys( $connections );
		$chosen     = $this->get_chosen_connection( $fields );
		if ( isset( $connections[ $chosen ] ) ) {
			$candidates = array( $chosen );
		}

		// check the candidates.
		$result = null;
		foreach ( $candidates as $connection ) {
			// bail if the URL is not using the server of this connection.
			if ( ! $this->is_url_matching_constants( $url, (string) $connection ) ) {
				continue;
			}

			// bail if the fields are for another target (e.g. another bucket).
			if ( $check_target_fields && ! $this->is_fields_matching_connection( $fields, (string) $connection ) ) {
				continue;
			}

			// use this connection.
			$result = (string) $connection;
			break;
		}

		$instance = $this;
		/**
		 * Filter the connection, whose constants should be used for the given fields and URL.
		 * Return null to use no constants.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string|null $result The name of the connection or null.
		 * @param array<string,mixed> $fields List of fields.
		 * @param string $url The URL.
		 * @param Service_Base $instance The service object.
		 */
		$result = apply_filters( 'efml_service_constants_connection', $result, $fields, $url, $instance );

		// return the connection, if it exists.
		return is_string( $result ) && isset( $connections[ $result ] ) ? $result : null;
	}

	/**
	 * Return the name of the connection, which is chosen in the given fields. Returns an empty string if none is chosen.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return string
	 */
	private function get_chosen_connection( array $fields ): string {
		// bail if the field is not set.
		if ( ! isset( $fields[ self::CONNECTION_FIELD ] ) || ! is_array( $fields[ self::CONNECTION_FIELD ] ) || ! isset( $fields[ self::CONNECTION_FIELD ]['value'] ) || ! is_string( $fields[ self::CONNECTION_FIELD ]['value'] ) ) {
			return '';
		}

		// return the name.
		return strtolower( trim( $fields[ self::CONNECTION_FIELD ]['value'] ) );
	}

	/**
	 * Replace the values of the given fields with the values of the constants of this service.
	 *
	 * The constants of the connection for these fields are used (see @get_connection_for_fields()). If no connection
	 * could be used, values from constants are removed from the fields, so they are never sent to other servers.
	 *
	 * @param array<string,mixed> $fields              List of fields.
	 * @param string              $url                 The URL, which should be used with these fields (optional).
	 * @param bool                $add_missing         Whether fields, which are not in the list, should be added.
	 * @param bool                $check_target_fields Whether the values of the target fields should be checked.
	 *
	 * @return array<string,mixed>
	 */
	public function apply_constants_to_fields( array $fields, string $url = '', bool $add_missing = true, bool $check_target_fields = true ): array {
		// bail if the constants of this service are not values of its fields.
		if ( ! $this->constants_in_fields ) {
			return $fields;
		}

		// get the connections.
		$connections = $this->get_connections();

		// bail if no values are defined.
		if ( empty( $connections ) ) {
			return $fields;
		}

		// get the connection for these fields.
		$connection = $this->get_connection_for_fields( $fields, $url, $check_target_fields );

		// remove values of other connections, which might be set (e.g. after choosing another connection).
		$fields = $this->remove_constant_values_from_fields( $fields );

		// bail if no connection could be used.
		if ( is_null( $connection ) ) {
			return $fields;
		}

		// get the definitions.
		$definitions = $this->get_constant_field_definitions();

		// set the values.
		foreach ( $connections[ $connection ] as $name => $value ) {
			// add the field, if it is missing.
			if ( ! isset( $fields[ $name ] ) || ! is_array( $fields[ $name ] ) ) {
				// bail if missing fields should not be added.
				if ( ! $add_missing ) {
					continue;
				}

				// add the definition of this field.
				$fields[ $name ] = isset( $definitions[ $name ] ) ? $definitions[ $name ] : array( 'name' => $name );
			}

			// use the value of the constant.
			$fields[ $name ]['value']    = $value;
			$fields[ $name ]['readonly'] = true;
			$fields[ $name ]['constant'] = $this->get_constant_name_for_field( $name, $connection );
		}

		// return the resulting fields.
		return $fields;
	}

	/**
	 * Return whether the given URL is using the host, which is defined via constant for the given connection.
	 *
	 * Without a connection, the connection for the actual fields of this object is used.
	 *
	 * @param string      $url        The URL to check.
	 * @param string|null $connection The name of the connection (optional).
	 *
	 * @return bool
	 */
	public function is_url_matching_constants( string $url, ?string $connection = null ): bool {
		// bail if no URL is given.
		if ( '' === $url ) {
			return true;
		}

		// bail if this service does not use a host field.
		if ( empty( $this->constants_host_field ) ) {
			return true;
		}

		// get the value of the host field.
		$server = $this->get_constant_value( $this->constants_host_field, $connection );

		// bail if no server is defined via constant (should not happen as it is a required field).
		if ( is_null( $server ) ) {
			return true;
		}

		// compare the hosts.
		$server_host = $this->get_host_from_url( $server );
		$result      = '' !== $server_host && $server_host === $this->get_host_from_url( $url );

		$instance = $this;
		/**
		 * Filter whether the given URL is using the server defined via constants for this service.
		 * Only if true is returned, the values of the constants are used for this URL.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result True if the URL is matching.
		 * @param string $url The URL.
		 * @param string $server The server defined via constant.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_url_matching_constants', $result, $url, $server, $instance );
	}

	/**
	 * Return whether the given URL could not be used, because the credentials of this service are defined
	 * via constants for other servers.
	 *
	 * Used for saved external sources: sources on other servers are not usable as long as the constants are defined.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool
	 */
	public function is_url_blocked_by_constants( string $url ): bool {
		return $this->is_target_blocked_by_constants( $url, array() );
	}

	/**
	 * Return whether the given URL and fields could not be used, because the credentials of this service are
	 * defined via constants for other targets (other servers or e.g. other buckets).
	 *
	 * The URL and fields are usable, if they are matching any connection (or the connection chosen in the fields).
	 *
	 * @param string              $url    The URL to check.
	 * @param array<string,mixed> $fields The fields to check (e.g. of a saved external source).
	 *
	 * @return bool
	 */
	public function is_target_blocked_by_constants( string $url, array $fields ): bool {
		// bail if this service does not use a host field or target fields.
		if ( empty( $this->constants_host_field ) && empty( $this->constants_target_fields ) ) {
			return false;
		}

		// bail if no connection is defined via constants.
		if ( empty( $this->get_connections() ) ) {
			return false;
		}

		// return true if no connection could be used for this URL and fields.
		return is_null( $this->get_connection_for_fields( $fields, $url ) );
	}

	/**
	 * Return whether the values of the target fields (e.g. a bucket) in the given fields are empty or equal to
	 * the values of any connection defined via constants (or the connection chosen in the fields).
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return bool
	 */
	public function is_fields_matching_constants( array $fields ): bool {
		// bail if no connection is defined via constants.
		if ( empty( $this->get_connections() ) ) {
			return true;
		}

		// return whether a connection could be used for these fields.
		return ! is_null( $this->get_connection_for_fields( $fields ) );
	}

	/**
	 * Return whether the values of the target fields (e.g. a bucket) in the given fields are empty or equal to
	 * the values defined via constants for the given connection.
	 *
	 * @param array<string,mixed> $fields     List of fields.
	 * @param string              $connection The name of the connection.
	 *
	 * @return bool
	 */
	public function is_fields_matching_connection( array $fields, string $connection ): bool {
		// check each target field.
		$result = true;
		foreach ( $this->constants_target_fields as $field_name ) {
			// get the value of the constant.
			$constant_value = $this->get_constant_value( $field_name, $connection );

			// bail if this field is not defined via constant.
			if ( is_null( $constant_value ) ) {
				continue;
			}

			// bail if the field is not set or is set via constant.
			if ( ! isset( $fields[ $field_name ] ) || ! is_array( $fields[ $field_name ] ) || ! empty( $fields[ $field_name ]['constant'] ) ) {
				continue;
			}

			// get the value.
			$value = isset( $fields[ $field_name ]['value'] ) && is_scalar( $fields[ $field_name ]['value'] ) ? trim( (string) $fields[ $field_name ]['value'] ) : '';

			// bail if no value is set.
			if ( '' === $value ) {
				continue;
			}

			// compare the values.
			if ( strtolower( $value ) !== strtolower( trim( $constant_value ) ) ) {
				$result = false;
			}
		}

		$instance = $this;
		/**
		 * Filter whether the given fields are for the target (e.g. a bucket) defined via constants.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result True if the fields are matching.
		 * @param array<string,mixed> $fields List of fields.
		 * @param Service_Base $instance The service object.
		 * @param string $connection The name of the connection ("default" for the constants without connection name).
		 */
		return apply_filters( 'efml_service_fields_matching_constants', $result, $fields, $instance, $connection );
	}

	/**
	 * Return the host of the given URL in lowercase.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function get_host_from_url( string $url ): string {
		// add a scheme, if it is missing, so the host could be detected.
		if ( ! str_contains( $url, '://' ) ) {
			$url = 'http://' . $url;
		}

		// get the host.
		$host = wp_parse_url( $url, PHP_URL_HOST );

		// bail if no host could be detected.
		if ( ! is_string( $host ) ) {
			return '';
		}

		// return the host.
		return strtolower( $host );
	}

	/**
	 * Remove all values, which are set via constants, from the given fields.
	 *
	 * @param array<string,mixed> $fields List of fields.
	 *
	 * @return array<string,mixed>
	 */
	public function remove_constant_values_from_fields( array $fields ): array {
		foreach ( $fields as $name => $field ) {
			// bail if field is not set via constant.
			if ( ! is_array( $field ) || empty( $field['constant'] ) ) {
				continue;
			}

			// remove the value.
			$fields[ $name ]['value'] = '';
			unset( $fields[ $name ]['constant'], $fields[ $name ]['readonly'] );
		}

		// return the resulting fields.
		return $fields;
	}

	/**
	 * Return the list of defined constants for fields of this service for the given connection.
	 *
	 * @param string $connection The name of the connection (optional, the default connection is used if not set).
	 *
	 * @return array<int,string>
	 */
	public function get_defined_constants_for_fields( string $connection = self::DEFAULT_CONNECTION ): array {
		// collect the defined constants of this service.
		$list = array();
		foreach ( array_keys( $this->get_constant_field_definitions() ) as $name ) {
			// get the constant name.
			$constant_name = $this->get_constant_name_for_field( $name, $connection );

			// bail if constant is not defined.
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			// add it to the list.
			$list[] = $constant_name;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of constants for required fields of the given connection, which are not defined.
	 *
	 * @param bool   $only_if_any_defined True to return an empty list, if no constant of this connection is defined.
	 * @param string $connection          The name of the connection (optional, the default connection is used if not set).
	 *
	 * @return array<int,string>
	 */
	public function get_missing_constants_for_fields( bool $only_if_any_defined = true, string $connection = self::DEFAULT_CONNECTION ): array {
		// bail if no constant is defined and this is requested.
		if ( $only_if_any_defined && empty( $this->get_defined_constants_for_fields( $connection ) ) ) {
			return array();
		}

		// collect the missing constants.
		$list = array();
		foreach ( $this->get_required_fields_for_constants() as $name ) {
			// get the constant name.
			$constant_name = $this->get_constant_name_for_field( $name, $connection );

			// bail if constant is defined.
			if ( defined( $constant_name ) ) {
				continue;
			}

			// add it to the list.
			$list[] = $constant_name;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the name of the field for the given name of a user setting (e.g. "login" for "ftp_login").
	 *
	 * @param string $setting_name The name of the user setting.
	 *
	 * @return string
	 */
	private function get_field_name_of_user_setting( string $setting_name ): string {
		// get the prefix of the user settings of this service.
		$prefix = $this->get_name() . '_';

		// bail if the name does not start with the prefix.
		if ( ! str_starts_with( $setting_name, $prefix ) ) {
			return $setting_name;
		}

		// return the name without the prefix.
		return substr( $setting_name, strlen( $prefix ) );
	}

	/**
	 * Return the hint for a field, whose value is defined via constant. Returns an empty string, if it is not.
	 *
	 * Only the value of the field with the server is shown, other values are never shown.
	 *
	 * @param string $field_name The name of the field.
	 *
	 * @return string
	 */
	protected function get_constant_hint_for_field( string $field_name ): string {
		// get the connections, which define this field.
		$connections = $this->get_connections_with_field( $field_name );

		// bail if the value is not defined via constant.
		if ( empty( $connections ) ) {
			return '';
		}

		// show the connections, if the field is defined for several connections.
		if ( count( $connections ) > 1 ) {
			// get the labels of the connections.
			$labels = array();
			foreach ( array_keys( $connections ) as $connection ) {
				$labels[] = '<em>' . esc_html( $this->get_connection_label( $connection ) ) . '</em>';
			}

			/* translators: %1$s will be replaced by a list of connection names. */
			return sprintf( __( 'Defined via constants for the connections %1$s', 'external-files-in-media-library' ), implode( ', ', $labels ) );
		}

		// get the connection and the value.
		$connection = (string) array_key_first( $connections );
		$value      = $connections[ $connection ];

		// get the name of the constant.
		$constant_name = $this->get_constant_name_for_field( $field_name, $connection );

		// show the value for the server.
		if ( $field_name === $this->constants_host_field ) {
			/* translators: %1$s will be replaced by a value, %2$s by the name of a constant. */
			return sprintf( __( '%1$s (defined via %2$s)', 'external-files-in-media-library' ), '<code>' . esc_html( $value ) . '</code>', '<code>' . esc_html( $constant_name ) . '</code>' );
		}

		/* translators: %1$s will be replaced by the name of a constant. */
		return sprintf( __( 'Defined via %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $constant_name ) . '</code>' );
	}

	/**
	 * Add a non-editable hint as setting, if the value of the given field is defined via constant.
	 *
	 * Services use this for their global settings, so the values saved in the database are not changed.
	 *
	 * @param Section $section    The section for the setting.
	 * @param string  $field_name The name of the field.
	 * @param string  $title      The title of the setting.
	 *
	 * @return bool True if the value is defined via constant and the hint has been added.
	 */
	protected function add_constant_hint_setting( Section $section, string $field_name, string $title ): bool {
		// get the hint.
		$hint = $this->get_constant_hint_for_field( $field_name );

		// bail if the value is not defined via constant.
		if ( '' === $hint ) {
			return false;
		}

		// add the hint as setting.
		$settings_obj = Settings::get_instance()->get_settings_obj();
		$setting      = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_' . $field_name . '_constant_hint' );
		$setting->set_section( $section );
		$setting->set_show_in_rest( false );
		$setting->prevent_export( true );
		$field = new TextInfo( $settings_obj );
		$field->set_title( $title );
		$field->set_description( $hint );
		$setting->set_field( $field );

		// return true as the hint has been added.
		return true;
	}

	/**
	 * Return a list of constant names for output.
	 *
	 * @param array<int,string> $constants List of constant names.
	 *
	 * @return string
	 */
	private function get_constant_list_for_output( array $constants ): string {
		return '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $constants ) ) . '</code>';
	}

	/**
	 * Return whether this service is using credentials by checking its field configuration.
	 *
	 * @return bool
	 */
	protected function has_credentials(): bool {
		// set initial value.
		$has_credentials = false;

		// check fields for credentials fields.
		foreach ( $this->get_fields() as $setting ) {
			// bail if field has not the credential marker.
			if ( empty( $setting['credential'] ) ) {
				continue;
			}

			// bail if it is a credential field but not required.
			if ( ! empty( $setting['not_required'] ) ) {
				continue;
			}

			// mark that this service is using credentials.
			$has_credentials = true;
		}

		// return the result.
		return $has_credentials;
	}

	/**
	 * Return whether this listing could also be used to export files.
	 *
	 * @return Export_Base|bool
	 */
	public function get_export_object(): Export_Base|bool {
		return false;
	}

	/**
	 * Return the search terms of this service, e.g. for the command palette of WordPress.
	 *
	 * Contains its name, its label and further terms (see @get_keywords()).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<int,string>
	 */
	public function get_search_keywords(): array {
		// collect the terms.
		$keywords = array( str_replace( array( '-', '_' ), ' ', $this->get_name() ), wp_strip_all_tags( $this->get_label() ) );
		foreach ( explode( ',', $this->get_keywords() ) as $keyword ) {
			$keyword = trim( $keyword );
			if ( '' !== $keyword ) {
				$keywords[] = $keyword;
			}
		}

		$instance = $this;
		/**
		 * Filter the search terms of a service, e.g. for the command palette of WordPress.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $keywords List of search terms.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_search_keywords', array_values( array_unique( $keywords ) ), $instance );
	}

	/**
	 * Return further search terms of this service, separated by commas (e.g. other names of the service).
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return '';
	}

	/**
	 * Return the features of this service, which are shown e.g. on the tiles to choose a service.
	 *
	 * Possible features are "sync", "export", "private" (private files) and "constants" (access data via constants).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,bool>
	 */
	public function get_features(): array {
		// get the export object.
		$export_obj = $this->get_export_object();

		// collect the features.
		$features = array(
			'sync'      => ! $this->is_sync_disabled(),
			'export'    => $export_obj instanceof Export_Base,
			'private'   => $export_obj instanceof Export_Base && $export_obj->supports_private_files(),
			'constants' => $this->is_fields_via_constants_enabled() && ! empty( $this->get_constant_field_definitions() ),
		);

		$instance = $this;
		/**
		 * Filter the features of a service, which are shown e.g. on the tiles to choose a service.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,bool> $features List of features.
		 * @param Service_Base $instance The service object.
		 */
		return apply_filters( 'efml_service_features', $features, $instance );
	}

	/**
	 * Return the plugin slug for this service.
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string {
		return $this->source_config['plugin_slug'];
	}

	/**
	 * Return whether this service is based on a plugin.
	 *
	 * @return bool
	 */
	public function is_plugin(): bool {
		return ! empty( $this->get_plugin_slug() );
	}

	/**
	 * Return the source configuration if this service is based on an external plugin.
	 *
	 * @return array<string,string>
	 */
	public function get_source_config(): array {
		return $this->source_config;
	}

	/**
	 * Return the plugins main file.
	 *
	 * @return string
	 */
	public function get_plugin_main_file(): string {
		return $this->source_config['plugin_main_file'];
	}

	/**
	 * Return false to mark these service provides editable permissions.
	 *
	 * @return bool
	 */
	public function has_no_editable_permissions(): bool {
		return false;
	}

	/**
	 * Return the permission name to use this listing.
	 *
	 * @return string
	 */
	public function get_permission_name(): string {
		return 'efml_cap_' . $this->get_name();
	}

	/**
	 * Return the default roles, which should be allowed to use this service after install.
	 *
	 * @return array<int,string>
	 */
	public function get_default_roles(): array {
		return array( 'administrator', 'editor' );
	}

	/**
	 * Return whether the hooks for importing files with this service should be used in this request.
	 *
	 * This is the case for users with the capability for this service and for scheduled synchronizations
	 * via cron and WP CLI, which run without a user.
	 *
	 * @return bool
	 */
	protected function is_import_allowed(): bool {
		return defined( 'EFML_SYNC_RUNNING' ) || wp_doing_cron() || Helper::is_cli() || current_user_can( 'efml_cap_' . $this->get_name() );
	}

	/**
	 * Return whether the actual import or synchronization is for this service.
	 *
	 * The import dialog sends the service with the request. A synchronization (also via cron or WP CLI)
	 * sets the external source on the import object instead, which has the service as type.
	 *
	 * @param string $field The name of the field in the request, which contains the service.
	 *
	 * @return bool
	 */
	protected function is_actual_service( string $field = 'service' ): bool {
		// use the service from the request, if given.
		$service = filter_input( INPUT_POST, $field, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_null( $service ) ) {
			return $this->get_name() === $service;
		}

		// bail if no external source is used for the actual import.
		$term_id = Import::get_instance()->get_term_id();
		if ( 0 === $term_id ) {
			return false;
		}

		// return whether the external source is from this service.
		return $this->get_name() === get_term_meta( $term_id, 'type', true );
	}
}
