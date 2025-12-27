<?php
/**
 * File to handle the support of files from ZIP as directory listing.
 *
 * Handling of ZIPs per request:
 * - URL/path ending with "/" is a ZIP that should be extracted and its files should be imported in media library
 * - URL/path ending with allowed ending is a file that should bei imported
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Zip\Zip_Base;
use WP_Error;
use WP_Post;

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
		add_filter( 'efml_directory_translations', array( $this, 'change_translations' ) );
		add_action( 'efml_show_file_info', array( $this, 'add_option_to_show_zip' ) );
		add_filter( 'efml_supported_mime_types', array( $this, 'add_supported_mime_types' ) );
		add_filter( 'efml_get_mime_types', array( $this, 'change_enabled_mime_types' ) );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'allow_tar_gz_uploads' ), 10, 4 );
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

		// add setting to show also trashed files.
		$setting = $settings_obj->add_setting( 'eml_zip_allow_gz' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Allow upload of .tar.gz', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled you will be able to import .tar.gz and .gz files as external files.', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$field->set_readonly( $this->is_disabled() );
		$setting->set_field( $field );
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the zip object for this file.
		$zip_obj = $this->get_zip_object_by_file( $directory );

		// bail if no object could be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return array();
		}

		// return the directory listing of this object.
		return $zip_obj->get_directory_listing();
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
		// get the zip object for this file.
		$zip_obj = $this->get_zip_object_by_file( $file_path );

		// bail if zip object could not be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return $return_value;
		}

		// return check through the zip object.
		return $zip_obj->is_file_in_zip();
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

		// get the zip object for this file.
		$zip_obj = $this->get_zip_object_by_file( $file_path );

		// bail if zip object could not be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return array();
		}

		// return the file info by zip object.
		return $zip_obj->get_file_info_from_zip( $file_path );
	}

	/**
	 * Return list of files in zip to import in media library.
	 *
	 * The file must be extracted in tmp directory to import them as usual URLs.
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

		// get the zip object for this file.
		$zip_obj = $this->get_zip_object_by_file( $file_path );

		// bail if zip object could not be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return array();
		}

		// return the list of files from zip object.
		return $zip_obj->get_files_from_zip();
	}

	/**
	 * Check if given directory is a valid ZIP-file.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
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

		// get the used protocol.
		$protocol_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if no protocol could be loaded.
		if ( ! $protocol_obj instanceof Protocol_Base ) {
			return false;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

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
			$error->add( 'efml_service_zip', sprintf( __( 'The given URL %1$s does not exist.', 'external-files-in-media-library' ), '<code>' . $directory . '</code>' ) );

			// add it to the list.
			$this->add_error( $error );

			// log this event.
			/* translators: %1$s will be replaced by a file path. */
			Log::get_instance()->create( sprintf( __( 'The given URL %1$s does not exist.', 'external-files-in-media-library' ), '<code>' . $directory . '</code>' ), $directory, 'error' );

			// return false to prevent further processing.
			return false;
		}

		// get the zip object for this file.
		$zip_obj = $this->get_zip_object_by_file( $directory );

		// bail if zip object could not be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return false;
		}

		// return result of trying to open this file.
		return $zip_obj->can_file_be_opened();
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		// check if any of our zip object can be used.
		$disabled = true;

		// check each zip object.
		foreach ( $this->get_zip_objects() as $zip_obj ) {
			// bail if it is not usable.
			if ( ! $zip_obj->is_usable() ) {
				continue;
			}

			// mark as enabled.
			$disabled = false;
		}

		// return the result.
		return $disabled;
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<span>' . __( 'Requirements not matched!', 'external-files-in-media-library' ) . '</span>';
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
	 * Change media row actions for URL-files:
	 * - add extract option.
	 * - add open option.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// bail if cap is not set.
		if ( ! current_user_can( 'efml_cap_tools_zip' ) ) {
			return $actions;
		}

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
		// bail if cap is not set.
		if ( ! current_user_can( 'efml_cap_tools_zip' ) ) {
			return $dialog;
		}

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

		// get the zip handler for this URL.
		$zip_obj = $this->get_zip_object_by_file( $url );

		// bail if no zip object could be loaded.
		if ( ! $zip_obj instanceof Zip_Base ) {
			return $config;
		}

		// add the URL from the request.
		$config['fields']['server']['value'] = $url;

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
		return __( 'Enter the URL of the ZIP file you want to open. This can also be a local file on your hosting that starts with <em>file://</em>.', 'external-files-in-media-library' );
	}

	/**
	 * Change some translations on directory listing.
	 *
	 * @param array<string,mixed> $translations List of translations.
	 *
	 * @return array<string,mixed>
	 */
	public function change_translations( array $translations ): array {
		// get requested method.
		$method = filter_input( INPUT_GET, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if method is not "zip".
		if ( $this->get_name() !== $method ) {
			return $translations;
		}

		// change translations.
		$translations['form_login']['button']['label'] = __( 'Open ZIP', 'external-files-in-media-library' );

		// return the resulting list of translations.
		return $translations;
	}

	/**
	 * Return the zip object which can be used for this zip file.
	 *
	 * @param string $url The file URL.
	 *
	 * @return Zip_Base|false
	 */
	private function get_zip_object_by_file( string $url ): Zip_Base|false {
		// loop through the available zip objects.
		foreach ( $this->get_zip_objects() as $zip_object ) {
			// bail if URL is not compatible.
			if ( ! $zip_object->is_compatible( $url ) ) {
				// log this event.
				/* translators: %1$s will be replaced by a title. */
				Log::get_instance()->create( sprintf( __( 'The given URL is not compatible with the ZIP object %1$s. Further tests for other ZIP objects will follow.', 'external-files-in-media-library' ), '<code>' . get_class( $zip_object ) . '</code>' ), $url, 'info', 2 );

				// do nothing more.
				continue;
			}

			/* translators: %1$s will be replaced by a title. */
			Log::get_instance()->create( sprintf( __( 'Using zip object %1$s.', 'external-files-in-media-library' ), '<code>' . get_class( $zip_object ) . '</code>' ), $url, 'info', 2 );

			// set the file.
			$zip_object->set_zip_file( $url );

			// return the object as it is compatible.
			return $zip_object;
		}

		// return false as no compatible object could be found.
		return false;
	}

	/**
	 * Return list of zip objects as objects.
	 *
	 * @return array<int,Zip_Base>
	 */
	private function get_zip_objects(): array {
		// create the list of objects.
		$zip_objects = array();

		// loop through the names and create their objects.
		foreach ( $this->get_zip_object_names() as $zip_object_name ) {
			// bail if class does not exist.
			if ( ! class_exists( $zip_object_name ) ) {
				continue;
			}

			// get class name with method.
			$class_name = $zip_object_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// initiate object.
			$obj = $class_name();

			// bail if object is not a service object.
			if ( ! $obj instanceof Zip_Base ) {
				continue;
			}

			// add object to the list.
			$zip_objects[] = $obj;
		}

		// return the resulting list.
		return $zip_objects;
	}

	/**
	 * Return list of zip object names.
	 *
	 * @return array<int,string>
	 */
	private function get_zip_object_names(): array {
		// create list of zip objects we support.
		$zip_objects = array(
			'ExternalFilesInMediaLibrary\Services\Zip\Gzip',
			'ExternalFilesInMediaLibrary\Services\Zip\TarGzip',
			'ExternalFilesInMediaLibrary\Services\Zip\Zip',
		);

		/**
		 * Filter the list of available zip object names.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $zip_objects List of object names.
		 */
		return apply_filters( 'efml_zip_objects', $zip_objects );
	}

	/**
	 * Add option to show zip.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_option_to_show_zip( File $external_file_obj ): void {
		// bail if capability is not set.
		if ( ! current_user_can( 'efml_cap_tools_zip' ) ) {
			return;
		}

		// bail if file is not a ZIP.
		if ( 'ZIP' !== $external_file_obj->get_file_type_obj()->get_name() ) {
			return;
		}

		// get the local path of this file.
		$path = wp_get_attachment_url( $external_file_obj->get_id() );

		// bail if path could not be loaded.
		if ( ! is_string( $path ) ) {
			return;
		}

		// define settings for the import dialog.
		$settings = array(
			'service' => $this->get_name(),
			'urls'    => trailingslashit( $path ),
			'unzip'   => true,
		);

		?>
		<li>
			<span id="eml_url_zip"><span class="dashicons dashicons-database-view"></span> <a href="#" class="efml-import-dialog" data-settings="<?php echo esc_attr( Helper::get_json( $settings ) ); ?>"><?php echo esc_html__( 'Extract file', 'external-files-in-media-library' ); ?></a></span>
		</li>
		<?php

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

		?>
		<li>
			<span id="eml_url_zip"><span class="dashicons dashicons-database-view"></span> <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html__( 'Open file', 'external-files-in-media-library' ); ?></a></span>
		</li>
		<?php
	}

	/**
	 * Allow the upload of .tar.gz and .gz files.
	 *
	 * @param array<string,mixed> $data The upload data.
	 * @param string $file The file path.
	 * @param string $filename The file name.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function allow_tar_gz_uploads( array $data, string $file, string $filename ): array {
		// bail if setting is disabled.
		if( 1 !== absint( get_option( 'eml_zip_allow_gz' ) ) ) {
			return $data;
		}

		// get the path infos for this file.
		$ext = pathinfo($filename, PATHINFO_EXTENSION);

		// allow the file if the extension is .gz or .tar.gz.
		if ( $ext === 'gz' || str_ends_with( $filename, '.tar.gz' ) ) {
			$data['ext']  = 'gz';
			$data['type'] = 'application/gzip';
			$data['proper_filename'] = $filename;
		}

		// return resulting data array.
		return $data;
	}

	/**
	 * Add .gz files to the list of possible mime types we support.
	 *
	 * @param array<string,array<string,string>> $mime_types List of mime types we support.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function add_supported_mime_types( array $mime_types ): array {
		// bail if setting is disabled.
		if( 1 !== absint( get_option( 'eml_zip_allow_gz' ) ) ) {
			return $mime_types;
		}

		// add .gz files to the list.
		$mime_types['application/x-gzip'] = array(
			'label' => __( 'GZIP', 'external-files-in-media-library' ),
			'ext'   => 'tar.gz',
		);

		// return the list of allowed mime types.
		return $mime_types;
	}

	/**
	 * Remove the .gz mime type from the list of allowed mime types if the setting is disabled.
	 *
	 * @param array<int,string> $mime_types List of mime types.
	 *
	 * @return array<int,string>
	 */
	public function change_enabled_mime_types( array $mime_types ): array {
		// bail if setting is enabled.
		if( 1 === absint( get_option( 'eml_zip_allow_gz' ) ) ) {
			return $mime_types;
		}

		// get the entry for "application/x-gzip".
		$key = array_search( 'application/x-gzip', $mime_types, true );

		// bail if no key could be found.
		if( ! $key ) {
			return $mime_types;
		}

		// remove .gz from this list.
		unset( $mime_types[ $key ] );

		// return the resulting list of enabled mimes.
		return $mime_types;
	}
}
