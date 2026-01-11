<?php
/**
 * File to handle support for the WebDAV.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Password;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\WebDav\Export;
use Sabre\DAV\Client;
use Error;
use WP_Error;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class WebDav extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'webdav';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'WebDAV';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_webdav';

	/**
	 * Instance of actual object.
	 *
	 * @var ?WebDav
	 */
	private static ?WebDav $instance = null;

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
	 * @return WebDav
	 */
	public static function get_instance(): WebDav {
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
		add_action( 'init', array( $this, 'init_webdav' ), 30 );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your WebDAV', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_service_webdav_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'efml_service_webdav_client', array( $this, 'ignore_self_signed_ssl' ) );
		add_filter( 'efml_service_webdav_path', array( $this, 'set_path' ), 10, 2 );
		add_action( 'efml_export_before_on_service', array( $this, 'enable_unsafe_urls_for_export' ) );
		add_action( 'efml_proxy_before', array( $this, 'enable_unsafe_urls_for_proxy' ) );

		// use hooks.
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );
	}

	/**
	 * Add settings for WebDAV support.
	 *
	 * @return void
	 */
	public function init_webdav(): void {
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

		// add setting.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || 'global' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			// add setting.
			$setting = $settings_obj->add_setting( 'eml_webdav_server' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$field = new Text();
			$field->set_title( __( 'WebDAV Server', 'external-files-in-media-library' ) );
			$field->set_placeholder( __( 'https://nextcloud.local', 'external-files-in-media-library' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_webdav_login' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Text();
			$field->set_title( __( 'Login', 'external-files-in-media-library' ) );
			$field->set_placeholder( __( 'Your login', 'external-files-in-media-library' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_webdav_password' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->set_type( 'string' );
			$setting->set_read_callback( array( $this, 'decrypt_value' ) );
			$setting->set_save_callback( array( $this, 'encrypt_value' ) );
			$field = new Password();
			$field->set_title( __( 'Password', 'external-files-in-media-library' ) );
			$field->set_placeholder( __( 'Your password', 'external-files-in-media-library' ) );
			$setting->set_field( $field );

			// add setting.
			$setting = $settings_obj->add_setting( 'eml_webdav_path' );
			$setting->set_section( $section );
			$setting->set_type( 'string' );
			$setting->set_default( '/remote.php/dav/files/' );
			$field = new Text();
			$field->set_title( __( 'Path', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Define the path added after the WebDAV-domain to load files. For Nextcloud-based WebDAV this is <code>/remote.php/dav/files/</code>.', 'external-files-in-media-library' ) );
			$setting->set_field( $field );
		}

		// show hint for user settings.
		if ( 'user' === get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			$setting = $settings_obj->add_setting( 'eml_webdav_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_webdav_ignore_ssl' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Ignore self-signed SSL-certificates', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled self-signed certificates will be acknowledged.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// add the WebDAV protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\WebDav\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Enable WP CLI for WebDAV tasks.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// use subdirectory, if this is requested (has a longer path than the default one).
		if ( $directory !== $this->directory && strlen( $directory ) < strlen( $this->directory ) ) {
			$directory = $this->directory;
		}

		// add https:// before the directory if it is not set.
		if ( ! str_starts_with( $directory, 'https://' ) ) {
			$directory = 'https://' . $directory;
		}

		// get the protocol handler for this URL.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $directory );

		// bail if the detected protocol handler is not Http.
		if ( ! $protocol_handler_obj instanceof Protocols\Http ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_webdav', __( 'Given URL is not an HTTP-URL. The URL:', 'external-files-in-media-library' ) . ' ' . esc_html( $directory ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get the starting directory.
		$parse_url = wp_parse_url( $directory );

		// bail if scheme or host is not found in directory URL.
		if ( ! isset( $parse_url['scheme'], $parse_url['host'] ) ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_webdav', __( 'Given URL could not be analysed.', 'external-files-in-media-library' ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get the fields.
		$fields = $this->get_fields();

		// set the requested domain.
		$domain = $parse_url['scheme'] . '://' . $parse_url['host'];

		// create settings array for request.
		$settings = array(
			'baseUri'  => $domain,
			'userName' => $fields['login']['value'],
			'password' => $fields['password']['value'],
		);

		// get the path.
		$path = isset( $parse_url['path'] ) ? $parse_url['path'] : '';

		// empty path if it is just a slash.
		if ( '/' === $path ) {
			$path = '';
		}

		/**
		 * Filter the WebDAV path.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param string $path The path to use after the given domain.
		 * @param array $fields The fields to use.
		 * @param string $domain The domain to use.
		 * @param string $directory The requested URL.
		 */
		$path = apply_filters( 'efml_service_webdav_path', $path, $fields, $domain, $directory );

		/**
		 * Filter the WebDAV settings.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param array<string,string> $settings The settings to use.
		 * @param string $domain The domain to use.
		 * @param string $directory The requested URL.
		 */
		$settings = apply_filters( 'efml_service_webdav_settings', $settings, $domain, $directory );

		// get a new client.
		$client = $this->get_client( $settings, $domain, $directory );

		// get the directory listing for the given path from the external WebDAV.
		try {
			$directory_list = $client->propFind( $path, array(), 1 );
		} catch ( \Sabre\HTTP\ClientHttpException | \Sabre\HTTP\ClientException | Error $e ) {
			// create an error object.
			$error = new WP_Error();
			$error->add( 'efml_service_webdav', __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>' );
			$this->add_error( $error );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code><br><br>' . __( 'Domain:', 'external-files-in-media-library' ) . ' <code>' . $domain . '</code><br><br>' . __( 'Path:', 'external-files-in-media-library' ) . ' <code>' . $path . '</code><br><br>' . __( 'Settings:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $settings ) . '</code>', $directory, 'error' );

			// do nothing more.
			return array();
		}

		// collect the content of this directory.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// loop through the list, add each file to the list and loop through each subdirectory.
		foreach ( $directory_list as $file_name => $settings ) {
			// get directory-data for this file and add the file in the given directories.
			$parts = explode( '/', str_replace( $path, '', $file_name ) );

			// collect the entry.
			$entry = array(
				'title' => basename( $file_name ),
			);

			// if array contains more than 1 entry this file is in a directory.
			if ( end( $parts ) ) {
				$false = false;
				/**
				 * Filter whether given WebDAV file should be hidden.
				 *
				 * @since 5.0.0 Available since 5.0.0.
				 *
				 * @param bool $false True if it should be hidden.
				 * @param array<string,mixed> $file The array with the file data.
				 * @param string $file_name The requested file.
				 *
				 * @noinspection PhpConditionAlreadyCheckedInspection
				 */
				if ( apply_filters( 'efml_service_webdav_hide_file', $false, $settings, $file_name ) ) {
					continue;
				}

				// get content-type of this file.
				$mime_type = wp_check_filetype( basename( $file_name ) );

				// bail if file type is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// add settings for entry.
				$entry['file']          = $domain . '/' . $file_name;
				$entry['filesize']      = absint( $settings['{DAV:}getcontentlength'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( strtotime( $settings['{DAV:}getlastmodified'] ) ) ) );
				$entry['preview']       = '';

				// simply add the entry to the list if no directory data exist.
				$listing['files'][] = $entry;
			}

			if ( count( $parts ) > 1 ) {
				$the_keys = array_keys( $parts );
				$last_key = end( $the_keys );
				$dir_path = '';

				// loop through all parent folders, add the directory if it does not exist in the list
				// and add the file to each.
				foreach ( $parts as $key => $dir ) {
					// bail if dir is empty.
					if ( empty( $dir ) ) {
						continue;
					}

					// bail for last entry (a file).
					if ( $key === $last_key ) {
						continue;
					}

					// combine the path.
					$dir_path .= $dir . '/';

					// get index.
					$index = $domain . trailingslashit( $path ) . $dir_path;

					// add it to the directory list, if it does not already exist.
					if ( ! isset( $listing['dirs'][ $index ] ) ) {
						$listing['dirs'][ $index ] = array(
							'title' => basename( $dir ),
							'files' => array(),
							'dirs'  => array(),
						);
					}
				}
			}
		}

		// return the resulting file list.
		return $listing;
	}

	/**
	 * Ignore self-signed SSL-certificates for a WebDAV-connection.
	 *
	 * @param Client $client The client object.
	 *
	 * @return Client
	 */
	public function ignore_self_signed_ssl( Client $client ): Client {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_webdav_ignore_ssl' ) ) ) {
			return $client;
		}

		// set the curl settings for ignore the SSL certificate.
		$client->addCurlSetting( CURLOPT_SSL_VERIFYPEER, false );
		$client->addCurlSetting( CURLOPT_SSL_VERIFYHOST, false );

		// return the resulting client object.
		return $client;
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
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Return the URL. Possibility to complete it depending on listing method.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function get_url( string $url ): string {
		// bail if URL already starts with https or http.
		if ( str_starts_with( $url, 'https://' ) || str_starts_with( $url, 'http://' ) ) {
			return $url;
		}

		// add https:// before the URL.
		return 'https://' . $url;
	}

	/**
	 * Set the path, if string for it is empty, with value from settings.
	 *
	 * @param string                            $path The path to use.
	 * @param array<string,array<string,mixed>> $fields The used fields.
	 *
	 * @return string
	 */
	public function set_path( string $path, array $fields ): string {
		// replace double slashes in the path.
		$path = str_replace( '//', '/', $path );

		// if path is only "/" remove it.
		if ( '/' === $path ) {
			$path = '';
		}

		// bail if path is not empty.
		if ( ! empty( $path ) ) {
			return $path;
		}

		// get from global setting, if enabled.
		if ( $this->is_mode( 'global' ) ) {
			// return path from settings.
			return get_option( 'eml_webdav_path' ) . $fields['login']['value'] . '/';
		}

		// get from user setting, if enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}

			// get the setting.
			$path = get_user_meta( $user->ID, 'efml_webdav_path', true );

			// bail if value is not a string.
			if ( ! is_string( $path ) ) {
				return '';
			}

			// return the region from settings.
			return Crypt::get_instance()->decrypt( $path ) . $fields['login']['value'] . '/';
		}

		// use the field setting.
		if ( ! empty( $fields['path']['value'] ) ) {
			return $fields['path']['value'] . $fields['login']['value'] . '/';
		}

		// return nothing in other cases.
		return '';
	}

	/**
	 * Return a new sabre client object for given settings.
	 *
	 * @param array<string,string> $settings The settings for the client.
	 * @param string               $domain The used domain.
	 * @param string               $directory The used URL/directory/file.
	 *
	 * @return Client
	 */
	public function get_client( array $settings, string $domain, string $directory ): Client {
		// get the file contents.
		$client = new Client( $settings );
		$client->setThrowExceptions( true );

		/**
		 * Filter the WebDAV client connection object.
		 *
		 * E.g., to add proxy or other additional settings to reach the WebDAV.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 *
		 * @param Client $client The WebDAV client object.
		 * @param string $domain    The domain to use.
		 * @param string $directory The requested URL.
		 */
		return apply_filters( 'efml_service_webdav_client', $client, $domain, $directory );
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool                $result The result - should be true to prevent the usage.
	 * @param array<string,mixed> $settings The settings for the WebDAV connection.
	 * @param string              $file The requested file.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, array $settings, string $file ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// get content-type of this file.
		$mime_type = wp_check_filetype( $file );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Show option to connect to WebDav on the user profile.
	 *
	 * @param WP_User $user The "WP_User" object for the actual user.
	 *
	 * @return void
	 */
	public function add_user_settings( WP_User $user ): void {
		// bail if settings are not user-specific.
		if ( 'user' !== get_option( 'eml_' . $this->get_name() . '_credentials_vault' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'WebDav', 'external-files-in-media-library' ); ?></h3>
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
			'webdav_server'   => array(
				'label'       => __( 'WebDAV Server', 'external-files-in-media-library' ),
				'field'       => 'text',
				'placeholder' => __( 'https://nextcloud.local', 'external-files-in-media-library' ),
			),
			'webdav_login'    => array(
				'label'       => __( 'Login', 'external-files-in-media-library' ),
				'field'       => 'text',
				'placeholder' => __( 'Your login', 'external-files-in-media-library' ),
			),
			'webdav_password' => array(
				'label'       => __( 'Password', 'external-files-in-media-library' ),
				'field'       => 'password',
				'placeholder' => __( 'Your password', 'external-files-in-media-library' ),
			),
			'webdav_path'     => array(
				'label'       => __( 'Path', 'external-files-in-media-library' ),
				'field'       => 'text',
				'description' => __( 'Define the path added after the WebDAV-domain to load files. For Nextcloud-based WebDAV this is <code>/remote.php/dav/files/</code>.', 'external-files-in-media-library' ),
				'default'     => '/remote.php/dav/files/',
			),
		);

		/**
		 * Filter the list of possible user settings for WebDAV.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'efml_service_webdav_user_settings', $list );
	}

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
					'placeholder' => __( 'https://example.com', 'external-files-in-media-library' ),
					'value'       => $values['server'],
					'readonly'    => ! empty( $values['server'] ),
				),
				'login'    => array(
					'name'        => 'login',
					'type'        => 'text',
					'label'       => __( 'Login', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your login', 'external-files-in-media-library' ),
					'credential'  => true,
					'value'       => $values['login'],
					'readonly'    => ! empty( $values['login'] ),
				),
				'password' => array(
					'name'        => 'password',
					'type'        => 'password',
					'label'       => __( 'Password', 'external-files-in-media-library' ),
					'placeholder' => __( 'Your password', 'external-files-in-media-library' ),
					'credential'  => true,
					'value'       => $values['password'],
					'readonly'    => ! empty( $values['password'] ),
				),
				'path'     => array(
					'name'        => 'path',
					'type'        => 'text',
					'label'       => __( 'Path', 'external-files-in-media-library' ),
					'placeholder' => __( '/remote.php/dav/files/', 'external-files-in-media-library' ),
					'value'       => ! empty( $values['path'] ) ? $values['path'] : '/remote.php/dav/files/',
					'readonly'    => ! empty( $values['path'] ),
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
		// bail if credentials are set.
		if ( $this->has_credentials_set() ) {
			return __( 'Connect to your WebDAV', 'external-files-in-media-library' );
		}

		// return the default title.
		return __( 'Enter your credentials', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the fields.
		$has_credentials_set = $this->has_credentials_set();

		// if access token is set in plugin settings.
		if ( $this->is_mode( 'global' ) ) {
			if ( $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'The credentials have already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
			}

			if ( ! $has_credentials_set && ! current_user_can( 'manage_options' ) ) {
				return __( 'The credentials must be set by an administrator in the plugin settings.', 'external-files-in-media-library' );
			}

			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your credentials <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your credentials are already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		// if authentication JSON is set per user.
		if ( $this->is_mode( 'user' ) ) {
			if ( ! $has_credentials_set ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your credentials <a href="%1$s">in your profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your credentials are already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		return __( 'Enter your WebDAV credentials in this form.', 'external-files-in-media-library' );
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
			'path'     => '',
		);

		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$values['server']   = get_option( 'eml_webdav_server', '' );
			$values['login']    = Crypt::get_instance()->decrypt( get_option( 'eml_webdav_login', '' ) );
			$values['password'] = Crypt::get_instance()->decrypt( get_option( 'eml_webdav_password', '' ) );
			$values['path']     = get_option( 'eml_webdav_path', '' );
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
			$values['server']   = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_webdav_server', true ) );
			$values['login']    = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_webdav_login', true ) );
			$values['password'] = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_webdav_password', true ) );
			$values['path']     = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_webdav_path', true ) );
		}

		// return the resulting list of values.
		return $values;
	}

	/**
	 * Return whether credentials are set in the fields.
	 *
	 * @return bool
	 */
	private function has_credentials_set(): bool {
		// get the fields.
		$fields = $this->get_fields();

		// return whether both credentials are set.
		return ! empty( $fields['server'] ) && ! empty( $fields['login'] ) && ! empty( $fields['password'] );
	}

	/**
	 * Return the export object for this service.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		return Export::get_instance();
	}

	/**
	 * Enable the usage of unsafe URLs for export.
	 *
	 * @param Service_Base $service The used service.
	 *
	 * @return void
	 */
	public function enable_unsafe_urls_for_export( Service_Base $service ): void {
		// bail if the given service is not ours.
		if ( $this->get_name() !== $service->get_name() ) {
			return;
		}

		// add a filter.
		add_filter( 'efml_http_header_args', array( $this, 'disable_check_for_unsafe_urls' ) );
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
	 * Add a filter to use unsafe URLs in the proxy if WebDav-URLs are used.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function enable_unsafe_urls_for_proxy( File $external_file_obj ): void {
		// get the fields.
		$fields = $external_file_obj->get_fields();

		// bail if fields does not contain a path.
		if ( empty( $fields['path'] ) ) {
			return;
		}

		// bail if path is not in file URL.
		if ( ! str_contains( $external_file_obj->get_url( true ), $fields['path']['value'] ) ) {
			return;
		}

		// set filter to enabled unsafe URL.
		add_filter( 'http_request_args', array( $this, 'disable_check_for_unsafe_urls' ) );
	}
}
