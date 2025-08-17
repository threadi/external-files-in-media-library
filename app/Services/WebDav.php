<?php
/**
 * File to handle support for the WebDAV.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Text;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use Sabre\DAV\Client;
use Sabre\HTTP\ClientHttpException;
use Error;
use WP_Error;

/**
 * Object to handle support for this platform.
 */
class WebDav extends Directory_Listing_Base implements Service {
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
	private string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_sub_tab = 'eml_webdav';

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
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your WebDAV', 'external-files-in-media-library' );

		// use hooks.
		add_action( 'init', array( $this, 'init_webdav' ), 20 );

		// use our own hooks.
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_service_webdav_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'efml_service_webdav_client', array( $this, 'ignore_self_signed_ssl' ) );
		add_filter( 'efml_service_webdav_path', array( $this, 'set_path' ), 10, 2 );
	}

	/**
	 * Add settings for Google Drive support.
	 *
	 * @return void
	 */
	public function init_webdav(): void {
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
		$tab->set_title( __( 'WebDAV', 'external-files-in-media-library' ) );

		// add section for file statistics.
		$section = $tab->add_section( 'section_webdav_main', 20 );
		$section->set_title( __( 'Settings for WebDAV', 'external-files-in-media-library' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_webdav_path' );
		$setting->set_section( $section );
		$setting->set_type( 'string' );
		$setting->set_default( '/remote.php/dav/files/' );
		$field = new Text();
		$field->set_title( __( 'Path', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Define the path added after the WebDAV-domain to load files. For Nextcloud-based WebDAV this is "/remote.php/dav/files/".', 'external-files-in-media-library' ) );
		$setting->set_field( $field );

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
	 * Enable WP CLI for Google Drive tasks.
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
			$error->add( 'efml_service_webdav', __( 'Given URL is not a HTTP-URL. The URL:', 'external-files-in-media-library' ) . ' ' . esc_html( $directory ) );
			$this->add_error( $error );

			// do nothing more.
			return array();
		}

		// get the staring directory.
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

		// set the requested domain.
		$domain = $parse_url['scheme'] . '://' . $parse_url['host'];

		// create settings array for request.
		$settings = array(
			'baseUri'  => $domain,
			'userName' => $this->get_login(),
			'password' => $this->get_password(),
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
		 * @param string $login The login to use.
		 * @param string $domain The domain to use.
		 * @param string $directory The requested URL.
		 */
		$path = apply_filters( 'efml_service_webdav_path', $path, $this->get_login(), $domain, $directory );

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
		} catch ( ClientHttpException | Error $e ) {
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
			// get directory-data for this file and add file in the given directories.
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

				// get content type of this file.
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
				// and add the file to each of them.
				foreach ( $parts as $key => $dir ) {
					// bail if dir is empty.
					if ( empty( $dir ) ) {
						continue;
					}

					// bail for last entry (which is a file).
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
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, login, password, "", config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Return list of translations.
	 *
	 * @param array<string,mixed> $translations List of translations.
	 *
	 * @return array<string,mixed>
	 */
	public function get_translations( array $translations ): array {
		// get the method.
		$method = filter_input( INPUT_GET, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no method is called.
		if ( is_null( $method ) ) {
			return $translations;
		}

		// bail if called method is not ours.
		if ( $this->get_name() !== $method ) {
			return $translations;
		}

		// add our custom translation.
		$translations['form_login'] = array(
			'title'       => __( 'Enter the connection details for your WebDAV', 'external-files-in-media-library' ),
			'description' => '',
			'url'         => array(
				'label' => __( 'Enter the URL of your WebDAV (starting with https://).', 'external-files-in-media-library' ),
			),
			'login'       => array(
				'label' => __( 'WebDAV-Username', 'external-files-in-media-library' ),
			),
			'password'    => array(
				'label' => __( 'Password', 'external-files-in-media-library' ),
			),
			'button'      => array(
				'label' => __( 'Use this URL', 'external-files-in-media-library' ),
			),
		);

		// return the resulting translations.
		return $translations;
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
	 * @param string $path The path to use.
	 * @param string $login The used login.
	 *
	 * @return string
	 */
	public function set_path( string $path, string $login ): string {
		// replace double slashes in path.
		$path = str_replace( '//', '/', $path );

		// if path is only "/" remove it.
		if ( '/' === $path ) {
			$path = '';
		}

		// bail if path is not empty.
		if ( ! empty( $path ) ) {
			return $path;
		}

		// return path from settings.
		return get_option( 'eml_webdav_path' ) . $login . '/';
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
		 * E.g. to add proxy or other additional settings to reach the WebDAV.
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

		// get content type of this file.
		$mime_type = wp_check_filetype( $file );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}
}
