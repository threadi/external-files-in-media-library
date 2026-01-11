<?php
/**
 * File to handle the FTP support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Password;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Ftp\Export;
use WP_Error;
use WP_Filesystem_FTPext;
use WP_Image_Editor;
use WP_User;

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

		// add settings.
		add_action( 'init', array( $this, 'init_ftp' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from a FTP server', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_service_ftp_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 4 );

		// misc.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
	}

	/**
	 * Add settings for Google Drive support.
	 *
	 * @return void
	 */
	public function init_ftp(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = \ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

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

		// add a new tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add a section for file statistics.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add setting for the button to connect.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || $this->is_mode( 'global' ) ) {
			// add setting to show also shared files.
			$setting = $settings_obj->add_setting( 'efml_ftp_server' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$field = new Text();
			$field->set_title( __( 'Server', 'external-files-in-media-library' ) );
			$field->set_setting( $setting );
			$field->set_placeholder( __( 'ftps://example.com', 'external-files-in-media-library' ) );
			$field->set_readonly( $this->is_disabled() );
			$setting->set_field( $field );

			// add setting to show also shared files.
			$setting = $settings_obj->add_setting( 'efml_ftp_login' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Text();
			$field->set_title( __( 'Login', 'external-files-in-media-library' ) );
			$field->set_setting( $setting );
			$field->set_placeholder( __( 'Your login', 'external-files-in-media-library' ) );
			$field->set_readonly( $this->is_disabled() );
			$setting->set_field( $field );

			// add setting to show also shared files.
			$setting = $settings_obj->add_setting( 'efml_ftp_password' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( '' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Password();
			$field->set_title( __( 'Password', 'external-files-in-media-library' ) );
			$field->set_setting( $setting );
			$field->set_placeholder( __( 'Your password', 'external-files-in-media-library' ) );
			$field->set_readonly( $this->is_disabled() );
			$setting->set_field( $field );
		}

		if ( $this->is_mode( 'user' ) ) {
			$setting = $settings_obj->add_setting( 'eml_ftp_credential_location_hint' );
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
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// prepend a directory with ftp:// if that is not given.
		if ( ! ( absint( stripos( $directory, 'ftp://' ) ) >= 0 || absint( stripos( $directory, 'ftps://' ) ) > 0 ) ) {
			$directory = 'ftp://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if the detected protocol handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Given path is not an FTP-URL.', 'external-files-in-media-library' ) );
			$this->add_error( $error );

			// log this event.
			Log::get_instance()->create( __( 'Given path is not an FTP-URL.', 'external-files-in-media-library' ), $directory, 'error' );

			// do nothing more.
			return array();
		}

		// set the login.
		$protocol_handler_obj->set_fields( $this->get_fields() );

		// get the FTP-connection.
		$ftp_connection = $protocol_handler_obj->get_connection( $directory );

		// bail if connection is not an FTP-object.
		if ( ! $ftp_connection instanceof WP_Filesystem_FTPext ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Got wrong object to load FTP-data.', 'external-files-in-media-library' ) );
			$this->add_error( $error );

			// log this event.
			Log::get_instance()->create( __( 'Got wrong object to load FTP-data.', 'external-files-in-media-library' ), $directory, 'error' );

			// do nothing more.
			return array();
		}

		// get the starting directory.
		$parse_url = wp_parse_url( $directory );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Could not get scheme and host from given URL.', 'external-files-in-media-library' ) );
			$this->add_error( $error );

			// log this event.
			Log::get_instance()->create( __( 'Could not get scheme and host from given URL.', 'external-files-in-media-library' ), $directory, 'error' );

			// do nothing more.
			return array();
		}

		// set parent dir.
		$parent_dir = '/';

		// bail if path could not be read.
		if ( isset( $parse_url['path'] ) ) {
			// get "parent_dir" path.
			$parent_dir = trailingslashit( $parse_url['path'] );
		}

		// get list of directory.
		$directory_list = $ftp_connection->dirlist( $parent_dir, true, true );

		// collect the content of this directory.
		$listing = array(
			'title' => $directory,
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

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// loop through the list, add each file to the list and loop through each subdirectory.
		foreach ( $directory_list as $item_name => $item_settings ) {
			// get path for item.
			$path      = trailingslashit( $listing['title'] ) . $item_name;
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
				// get content-type of this file.
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

									// add the thumb to output if it does not result in an error.
									if ( ! is_wp_error( $results ) ) {
										$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="" class="filepreview">';
									}
								}
							}

							// delete the tmp file.
							$wp_filesystem->delete( $filename );
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "fields": config.fields, "term": term } );',
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
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "fields": config.fields, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
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
		// check if all fields are set.
		$counter = 0;
		$fields  = 0;
		foreach ( $this->fields as $field ) {
			++$fields;
			if ( ! empty( $field['value'] ) ) {
				++$counter;
			}
		}

		// bail if credentials are missing.
		if ( $fields !== $counter ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'No credentials set for this FTP connection!', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// override the directory.
		$directory = $this->fields['server']['value'];

		// prepend the directory with ftp:// if that is not given.
		if ( ! ( absint( stripos( $directory, 'ftp://' ) ) >= 0 || absint( stripos( $directory, 'ftps://' ) ) > 0 ) ) {
			$directory = 'ftp://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if handler is not FTP.
		if ( ! $protocol_handler_obj instanceof Protocols\Ftp ) {
			// create error object.
			$error = new WP_Error();
			$error->add( 'efml_service_ftp', __( 'Specified URL is not an FTP-path! Should be one of sftp:// or ftps://.', 'external-files-in-media-library' ) );

			// add it to the list.
			$this->add_error( $error );

			// return false to login check.
			return false;
		}

		// set the login.
		$protocol_handler_obj->set_fields( $this->get_fields() );

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

		// get content-type of this file.
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

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// get the prepared values for the fields.
			$values = $this->get_field_values();

			// set the fields.
			$this->fields = array(
				'server'   => array(
					'name'        => 'server',
					'type'        => 'url',
					'label'       => __( 'Server', 'external-files-in-media-library' ),
					'placeholder' => __( 'ftps://example.com', 'external-files-in-media-library' ),
					'value'       => $values['server'],
					'readonly'    => ! empty( $values['server'] ),
					'credential'  => true,
				),
				'login'    => array(
					'name'        => 'login',
					'type'        => 'text',
					'label'       => __( 'Login', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your login', 'external-files-in-media-library' ),
					'value'       => $values['login'],
					'readonly'    => ! empty( $values['login'] ),
					'credential'  => true,
				),
				'password' => array(
					'name'        => 'password',
					'type'        => 'password',
					'label'       => __( 'Password', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your password', 'external-files-in-media-library' ),
					'value'       => $values['password'],
					'readonly'    => ! empty( $values['password'] ),
					'credential'  => true,
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
		// bail if directory is set on the object.
		if ( ! empty( $this->directory ) ) {
			return $this->directory;
		}

		// bail if no directory is set.
		if ( empty( $this->fields['server']['value'] ) ) {
			return '';
		}

		// return the directory.
		return $this->fields['server']['value'];
	}

	/**
	 * Show option to connect to FTP on the user profile.
	 *
	 * @param WP_User $user The "WP_User" object for the actual user.
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

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'FTP', 'external-files-in-media-library' ); ?></h3>
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
			'ftp_server'   => array(
				'label'       => __( 'Server', 'external-files-in-media-library' ),
				'field'       => 'text',
				'placeholder' => __( 'ftps://example.com', 'external-files-in-media-library' ),
			),
			'ftp_login'    => array(
				'label'       => __( 'Login', 'external-files-in-media-library' ),
				'field'       => 'text',
				'placeholder' => __( 'Your login', 'external-files-in-media-library' ),
			),
			'ftp_password' => array(
				'label'       => __( 'Password', 'external-files-in-media-library' ),
				'field'       => 'password',
				'placeholder' => __( 'Your password', 'external-files-in-media-library' ),
			),
		);

		/**
		 * Filter the list of possible user settings for the FTP.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'efml_service_ftp_user_settings', $list );
	}

	/**
	 * Return the values depending on the actual mode.
	 *
	 * @return array<string,mixed>
	 */
	private function get_field_values(): array {
		// prepare the return array.
		$values = array(
			'server'   => '',
			'login'    => '',
			'password' => '',
		);

		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$values['server']   = get_option( 'efml_ftp_server', '' );
			$values['login']    = Crypt::get_instance()->decrypt( get_option( 'efml_ftp_login', '' ) );
			$values['password'] = Crypt::get_instance()->decrypt( get_option( 'efml_ftp_password', '' ) );
		}

		// save it user-specific, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get the user set on the object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) {
				return array();
			}

			// get the values.
			$values['server']   = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_ftp_server', true ) );
			$values['login']    = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_ftp_login', true ) );
			$values['password'] = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_ftp_password', true ) );
		}

		// return the resulting list of values.
		return $values;
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		// get the values.
		$values = $this->get_field_values();

		// show other title if credentials are already set.
		if ( ! empty( $values['server'] ) && ! $this->is_mode( 'manually' ) ) {
			return __( 'Connect to your FTP directory', 'external-files-in-media-library' );
		}

		return __( 'Enter your FTP connection details', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the values.
		$values = $this->get_field_values();

		// bail if token is set.
		if ( ! empty( $values['server'] ) && ! $this->is_mode( 'manually' ) ) {
			// if access token is set in plugin settings.
			if ( $this->is_mode( 'global' ) ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return __( 'The FTP credentials have already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
				}

				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'The FTP credentials are already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			// if access token is set per user.
			if ( $this->is_mode( 'user' ) ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'The FTP credentials are already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
			}
		}

		// return the hint.
		return __( 'Enter your FTP credentials in the following fields.', 'external-files-in-media-library' );
	}

	/**
	 * Return the export object for this service.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		return Export::get_instance();
	}
}
