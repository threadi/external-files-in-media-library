<?php
/**
 * File to handle support for the Google Drive platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Number;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\GoogleDrive\Client;
use ExternalFilesInMediaLibrary\Services\GoogleDrive\Export;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception;
use JsonException;
use WP_Error;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class GoogleDrive extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'google-drive';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Google Drive';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_googledrive';

	/**
	 * Instance of actual object.
	 *
	 * @var ?GoogleDrive
	 */
	private static ?GoogleDrive $instance = null;

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
	 * @return GoogleDrive
	 */
	public static function get_instance(): GoogleDrive {
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
		add_action( 'init', array( $this, 'init_google_drive' ), 30 );

		// bail if user has no capability for this service.
		if ( ! defined( 'EFML_SYNC_RUNNING' ) && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your Google Drive', 'external-files-in-media-library' );

		// use hooks.
		add_filter( 'query_vars', array( $this, 'set_query_vars' ) );
		add_action( 'admin_action_eml_google_drive_init', array( $this, 'initiate_connection' ) );
		add_action( 'admin_action_eml_google_drive_disconnect', array( $this, 'disconnect_via_request' ) );
		add_filter( 'template_include', array( $this, 'check_for_oauth_return_url' ), 10, 1 );
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ) );

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_prevent_import', array( $this, 'check_url' ), 10, 2 );
		add_filter( 'efml_google_drive_query_params', array( $this, 'set_query_params' ) );
		add_filter( 'efml_google_drive_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 3 );
		add_filter( 'efml_directory_listing_before_tree_building', array( $this, 'filter_on_directory_request' ), 10, 3 );
	}

	/**
	 * Add settings for Google Drive support.
	 *
	 * @return void
	 */
	public function init_google_drive(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// add the endpoint for Google OAuth.
		add_rewrite_rule( $this->get_oauth_slug() . '?$', 'index.php?' . $this->get_oauth_slug() . '=1', 'top' );

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

		// add setting for button to connect.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || $this->is_mode( 'global' ) ) {
			$setting = $settings_obj->add_setting( 'eml_google_drive_connector' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );

			// get the access token of the actual user.
			$access_token = $this->get_access_token();

			// show connect button if no token is set.
			if ( empty( $access_token ) ) {
				// create dialog.
				$dialog = $this->get_connect_dialog();

				$field = new Button();
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Connect now', 'external-files-in-media-library' ) );
			} else {
				// create dialog.
				$dialog = $this->get_disconnect_dialog();

				$field = new Button();
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Disconnect', 'external-files-in-media-library' ) );

				// get the creation date from token.
				if ( ! empty( $access_token['created'] ) ) {
					/* translators: %1$s will be replaced by a date and time. */
					$field->set_description( sprintf( __( 'Created at %1$s', 'external-files-in-media-library' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i', absint( $access_token['created'] ) ) ) ) . '<br><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . __( 'View and import files', 'external-files-in-media-library' ) . '</a>' );
				}
			}
			$field->add_class( 'easy-dialog-for-wordpress' );
			$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
			$setting->set_field( $field );
		}
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || in_array( $this->get_mode(), array( 'global', 'manually' ), true ) ) {
			// add setting to show also shared files.
			$setting = $settings_obj->add_setting( 'eml_google_drive_show_shared' );
			$setting->set_section( $section );
			$setting->set_type( 'integer' );
			$setting->set_default( 0 );
			$field = new Checkbox();
			$field->set_title( __( 'Show shared files', 'external-files-in-media-library' ) );
			$field->set_setting( $setting );
			$field->set_readonly( $this->is_disabled() );
			$setting->set_field( $field );

			// add setting to show also trashed files.
			$setting = $settings_obj->add_setting( 'eml_google_drive_show_trashed' );
			$setting->set_section( $section );
			$setting->set_type( 'integer' );
			$setting->set_default( 0 );
			$field = new Checkbox();
			$field->set_title( __( 'Show trashed files', 'external-files-in-media-library' ) );
			$field->set_setting( $setting );
			$field->set_readonly( $this->is_disabled() );
			$setting->set_field( $field );
		}

		if ( $this->is_mode( 'user' ) ) {
			$setting = $settings_obj->add_setting( 'eml_google_drive_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo();
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}

		// add setting to show also trashed files.
		$setting = $settings_obj->add_setting( 'eml_google_drive_limit' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 10 );
		$field = new Number();
		$field->set_title( __( 'Max. files to load per iteration', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This value specifies how many files should be loaded during a directory import. The higher the value, the greater the likelihood of timeouts during import.', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add invisible setting for global access token.
		$setting = $settings_obj->add_setting( 'eml_google_drive_access_tokens' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$setting->prevent_export( true );
		$setting->set_save_callback( array( $this, 'preserve_tokens_value' ) );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// only add the protocol if access_token for actual user is set.
		if ( empty( $this->get_access_token() ) ) {
			return $protocols;
		}

		// add the Google Drive protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\GoogleDrive\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Set a pseudo-directory to force the directory listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return 'Google Drive Directory Listing';
	}

	/**
	 * Return access token depending on actual mode.
	 *
	 * @return array<string,mixed>
	 */
	public function get_access_token(): array {
		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			return get_option( 'eml_google_drive_access_tokens', array() );
		}

		// save it user-specific, if this is enabled.
		if ( in_array( $this->get_mode(), array( 'user', 'manually' ), true ) ) {
			// get the user set on object.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) {
				return array();
			}

			// get the value.
			$access_token_json = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_google_drive_access_tokens', true ) );

			// bail if string is empty.
			if ( empty( $access_token_json ) ) {
				return array();
			}

			// convert JSON to array.
			$access_token = json_decode( $access_token_json, true );

			// bail if token is not an array.
			if ( ! is_array( $access_token ) ) {
				return array();
			}

			// return the access token.
			return $access_token;
		}

		// return nothing.
		return array();
	}

	/**
	 * Set the access token for the actual WordPress user.
	 *
	 * @param array<string,string> $access_token The access token.
	 * @param int                  $user_id The user id (optional).
	 *
	 * @return void
	 */
	public function set_access_token( array $access_token, int $user_id = 0 ): void {
		// save it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// log event.
			Log::get_instance()->create( __( 'New Google Drive access token saved for global usage.', 'external-files-in-media-library' ), '', 'info', 2 );

			// save the updated token.
			update_option( 'eml_google_drive_access_tokens', $access_token );
		}

		// save it user-specific, if this is enabled.
		if ( in_array( $this->get_mode(), array( 'user', 'manually' ), true ) ) {
			// get the user_id from session if it is not set.
			if ( 0 === $user_id ) {
				// get the user.
				$user = $this->get_user();
				if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
					return;
				}
				$user_id = $user->ID;
			} else {
				// get the user object.
				$user = get_user_by( 'id', $user_id );
				if ( ! $user instanceof WP_User ) {  // @phpstan-ignore instanceof.alwaysTrue
					return;
				}
			}

			// log event.
			/* translators: %1$s will be replaced by the username. */
			Log::get_instance()->create( sprintf( __( 'New Google Drive access token saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . $user->display_name . '</em>' ), '', 'info', 2 );

			// save the token.
			update_user_meta( $user_id, 'efml_google_drive_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $access_token ) ) );
		}
	}

	/**
	 * Delete the access token for the actual WordPress user.
	 *
	 * @return bool
	 */
	public function delete_access_token(): bool {
		// delete it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// clear the global list.
			update_option( 'eml_google_drive_access_tokens', array() );
		}

		// delete it user-specific, if this or manually mode is enabled.
		if ( in_array( $this->get_mode(), array( 'user', 'manually' ), true ) ) {
			// get the user.
			$user = $this->get_user();
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return false;
			}
			$user_id = $user->ID;

			// clear the user meta.
			delete_user_meta( $user_id, 'efml_google_drive_access_tokens' );
		}

		// return true as token has been removed.
		return true;
	}

	/**
	 * Add our OAuth slug to the allowed vars.
	 *
	 * @param array<int,string> $query_vars List of vars.
	 *
	 * @return array<int,string>
	 */
	public function set_query_vars( array $query_vars ): array {
		$query_vars[] = $this->get_oauth_slug();
		return $query_vars;
	}

	/**
	 * Check if given URL is using a not possible Google Drive-URL.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function check_url( bool $results, string $url ): bool {
		// bail if this is not a Google-URL.
		if ( ! str_contains( $url, 'google.com' ) ) {
			return $results;
		}

		// list of Google Drive-URLs which cannot be used for <img>-elements.
		$blacklist = array(
			'https://drive.google.com/file/',
		);

		// check the URL against the blacklist.
		$match = false;
		foreach ( $blacklist as $blacklist_url ) {
			if ( str_contains( $url, $blacklist_url ) ) {
				$match = true;
			}
		}

		// bail on no match => GoogleDrive URL could be used.
		if ( ! $match ) {
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'Given GoogleDrive-URL could not be used as external file in websites.', 'external-files-in-media-library' ), esc_url( $url ), 'error' );

		// return result to prevent any further import.
		return true;
	}

	/**
	 * Return the Google OAuth Client ID.
	 *
	 * @return string
	 */
	public function get_client_id(): string {
		// set our client ID.
		$client_id = EFML_GOOGLE_OAUTH_CLIENT_ID;

		// show deprecated warning for old hook name.
		$client_id = apply_filters_deprecated( 'eml_google_drive_client_id', array( $client_id ), '5.0.0', 'efml_google_drive_client_id' );

		/**
		 * Filter the Google OAuth Client ID for the app used to connect Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $client_id The client ID.
		 */
		return apply_filters( 'efml_google_drive_client_id', $client_id );
	}

	/**
	 * Return the real return URL where the user will land after successfully connection via OAuth.
	 *
	 * @return string
	 */
	private function get_real_redirect_uri(): string {
		// set the token.
		$real_redirect_uri = get_option( 'siteurl' ) . '/' . $this->get_oauth_slug() . '/';

		// show deprecated warning for old hook name.
		$real_redirect_uri = apply_filters_deprecated( 'eml_google_drive_real_redirect_uri', array( $real_redirect_uri ), '5.0.0', 'efml_google_drive_real_redirect_uri' );

		/**
		 * Filter the real redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $real_redirect_uri The real redirect URI.
		 */
		return apply_filters( 'efml_google_drive_real_redirect_uri', $real_redirect_uri );
	}

	/**
	 * Return the state used for the connection to Google OAuth.
	 *
	 * The string is made up of:
	 * - The plugin slug.
	 * - The base64 encoded string with the installation hash.
	 * - The base64 encoded return URL.
	 *
	 * @return string
	 */
	private function get_state(): string {
		// get the crypt method.
		$crypt_method = Crypt::get_instance()->get_method();

		// bail if crypt method could not be read.
		if ( ! $crypt_method ) {
			return '';
		}

		// set the state.
		$state = Helper::get_plugin_slug() . ':' . base64_encode( $crypt_method->get_hash() ) . ':' . base64_encode( $this->get_real_redirect_uri() );

		// show deprecated warning for old hook name.
		$state = apply_filters_deprecated( 'eml_google_drive_state', array( $state ), '5.0.0', 'efml_google_drive_state' );

		/**
		 * Filter the token to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $state The token.
		 */
		return apply_filters( 'efml_google_drive_state', $state );
	}

	/**
	 * Return the redirect URI used for the connection to Google OAuth by our app.
	 *
	 * The URL must be explicitly released in the API settings for the above client ID in Google.
	 *
	 * @return string
	 */
	private function get_redirect_uri(): string {
		// set the redirect URI.
		$redirect_uri = EFML_GOOGLE_OAUTH_SERVICE_URL;

		// show deprecated warning for old hook name.
		$redirect_uri = apply_filters_deprecated( 'eml_google_drive_redirect_uri', array( $redirect_uri ), '5.0.0', 'efml_google_drive_redirect_uri' );

		/**
		 * Filter the redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $redirect_uri The redirect URI.
		 */
		return apply_filters( 'efml_google_drive_redirect_uri', $redirect_uri );
	}

	/**
	 * Initiate the Google Drive connection.
	 *
	 * @return void
	 */
	public function initiate_connection(): void {
		// check nonce.
		check_admin_referer( 'eml-google-drive-initiate', 'nonce' );

		// set params for OAuth2-Request to get access to Google Drive.
		$params = array(
			'response_type'           => 'code',
			'client_id'               => $this->get_client_id(), // the client ID used by our Google App.
			'redirect_uri'            => $this->get_redirect_uri(), // the return URL for the Google App.
			'scope'                   => 'https://www.googleapis.com/auth/drive',
			'state'                   => $this->get_state(), // configuration for our return URL for the Google App.
			'access_type'             => 'offline', // allows usage in PHP-apps.
			'include_granted_scopes'  => 'false',
			'enable_granular_consent' => 'true', // forces usage of explicit configured field to use for each query.
			'prompt'                  => 'select_account consent', // forces consent dialog for each user.
		);

		// show deprecated warning for old hook name.
		$params = apply_filters_deprecated( 'eml_google_drive_connector_params', array( $params ), '5.0.0', 'efml_google_drive_connector_params' );

		/**
		 * Filter the params for Google OAuth request.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $params The list of params.
		 */
		$params = apply_filters( 'efml_google_drive_connector_params', $params );

		// bail if params are empty.
		if ( empty( $params ) ) {
			// redirect user.
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// forward the user to initiate the connection via OAuth.
		header( 'Location: https://accounts.google.com/o/oauth2/auth?' . http_build_query( $params, '', '&' ) );
	}

	/**
	 * Disconnect the API connection.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function disconnect_via_request(): void {
		// check nonce.
		check_admin_referer( 'eml-google-drive-disconnect', 'nonce' );

		// delete the token.
		$this->delete_access_token();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Return the OAuth slug for the real return URL.
	 *
	 * @return string
	 */
	private function get_oauth_slug(): string {
		return 'emlgoogledrive';
	}

	/**
	 * Check for requested OAuth return URL.
	 *
	 * @param string $template The requested template.
	 *
	 * @return string
	 * @throws JsonException Could throw exception.
	 */
	public function check_for_oauth_return_url( string $template ): string {
		// bail if slug is not used.
		if ( empty( get_query_var( $this->get_oauth_slug() ) ) ) {
			return $template;
		}

		// get access token from request.
		$access_token = filter_input( INPUT_GET, 'access_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no access token is set.
		if ( empty( $access_token ) ) {
			return Helper::get_404_template();
		}

		// get the decoded access token.
		$access_token_string = base64_decode( $access_token );
		if ( ! $access_token_string ) {
			return Helper::get_404_template();
		}
		$access_token = json_decode( $access_token_string, true, 512, JSON_THROW_ON_ERROR );

		// bail if access token is not an array.
		if ( ! is_array( $access_token ) ) {
			return Helper::get_404_template();
		}

		// bail if return value contains any errors.
		if ( ! empty( $access_token['error'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got error from Google OAuth:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $access_token ) . '</code>', '', 'error' );

			// return 404-page.
			return Helper::get_404_template();
		}

		// save the token.
		$this->set_access_token( $access_token );

		// forward user.
		wp_safe_redirect( $this->get_config_url() );
		exit;
	}

	/**
	 * Enable WP CLI for Google Drive tasks.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\Services\GoogleDrive\Cli' );
	}

	/**
	 * Return directory listing from Google Drive.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the client.
		$client_obj = new Client( $this->get_access_token() );
		$client     = $client_obj->get_client();

		// bail if client is not a Client object.
		if ( ! $client instanceof \Google\Client ) {
			return array();
		}

		// connect to Google Drive.
		$service = new Drive( $client );

		// collect the request query.
		$query = array(
			'fields'   => 'files(id,name,mimeType,parents,fileExtension,hasThumbnail,iconLink,thumbnailLink,createdTime,modifiedTime,imageMediaMetadata(height,width,rotation,time),size),nextPageToken',
			'pageSize' => 1000,
			'orderBy'  => 'name_natural',
		);

		// show deprecated warning for old hook name.
		$query = apply_filters_deprecated( 'eml_google_drive_query_params', array( $query, $directory ), '5.0.0', 'efml_google_drive_query_params' );

		/**
		 * Filter the query to get files from Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $query The list of params.
		 * @param string $directory The requested directory.
		 */
		$query = apply_filters( 'efml_google_drive_query_params', $query, $directory );

		// get the files.
		try {
			$results = $service->files->listFiles( $query );
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'List of files could not be loaded from Google Drive. Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', '', 'error' );

			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'List of files could not be loaded from Google Drive. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url() ) );
			$error_obj->set_url( $directory );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// return an empty list as we could not analyse the file.
			return array();
		}

		/**
		 * Get the list of files from Google Drive.
		 *
		 * This will be all files depending on the query.
		 */
		$files = $results->getFiles();

		// bail if list is empty.
		if ( empty( $files ) ) {
			return array();
		}

		// collect the list of files.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// show deprecated warning for old hook name.
		$files = apply_filters_deprecated( 'eml_google_drive_files', array( $files ), '5.0.0', 'efml_google_drive_files' );

		/**
		 * Filter the list of files we got from Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array<DriveFile> $files List of files.
		 */
		$files = apply_filters( 'efml_google_drive_files', $files );

		// collect list of folders.
		$folders = array();

		// loop through the files and add them to the list.
		foreach ( $files as $file_obj ) {
			$false = false;

			/**
			 * Filter whether given GoogleDrive file should be hidden.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param DriveFile $file_obj The object with the file data.
			 * @param string $directory The requested directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'efml_google_drive_hide_file', $false, $file_obj, $directory ) ) {
				continue;
			}

			// collect the entry.
			$entry = array(
				'title' => $file_obj->getName(),
			);

			// get content type of this file.
			$mime_type = wp_check_filetype( $file_obj->getName() );

			// bail if file type is not allowed.
			if ( empty( $mime_type['type'] ) ) {
				continue;
			}

			// get thumbnail, if set and enabled.
			$thumbnail = '';
			if ( $file_obj->getHasThumbnail() && Init::get_instance()->is_preview_enabled() ) {
				$thumbnail = '<img src="' . esc_url( $file_obj->getThumbnailLink() ) . '" alt="" class="filepreview">';
			}

			// add settings for entry.
			$entry['file']          = $file_obj->getId();
			$entry['filesize']      = absint( $file_obj->getSize() );
			$entry['mime-type']     = $mime_type['type'];
			$entry['icon']          = '<img src="' . esc_url( $file_obj->getIconLink() ) . '" alt="">';
			$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( strtotime( $file_obj->getCreatedTime() ) ) ) );
			$entry['preview']       = $thumbnail;

			// get directory-data for this file and add file in the given directories.
			$parent_dirs = $file_obj->getParents();
			if ( $parent_dirs ) {
				// loop through all parent folders, add the directory if it does not exist in the list
				// and add the file to each of them.
				foreach ( $parent_dirs as $parent_folder_id ) {
					// add the directory if it does not exist atm in the list.
					if ( ! isset( $folders[ trailingslashit( $parent_folder_id ) ] ) ) {
						// get directory data.
						try {
							$parent_folder_obj = $service->files->get( $parent_folder_id );
						} catch ( Exception $e ) {
							// log event.
							Log::get_instance()->create( __( 'Loading folder data results in an error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', '', 'error' );

							// do nothing more.
							continue;
						}

						// add the directory to the list.
						$folders[ trailingslashit( $parent_folder_id ) ] = array(
							'title' => $parent_folder_obj->getName(),
							'files' => array(),
							'dirs'  => array(),
						);

						$listing['dirs'][ trailingslashit( $parent_folder_id ) ] = array(
							'title' => $parent_folder_obj->getName(),
							'files' => array(),
							'dirs'  => array(),
						);
					}

					// add the file to this directory.
					$folders[ trailingslashit( $parent_folder_id ) ]['files'][] = $entry;
				}
			} else {
				// simply add the entry to the list if no directory data exist.
				$listing['files'][] = $entry;
			}
		}

		// return the resulting file list.
		return array_merge(
			array(
				$directory => $listing,
			),
			$folders
		);
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
		// get the config URL.
		$config_url = $this->get_config_url();

		// create our custom global actions.
		$actions = array(
			array(
				'action' => 'location.href="https://drive.google.com/drive/my-drive";',
				'label'  => __( 'Go to Google Drive', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'location.href="' . esc_url( $config_url ) . '";',
				'label'  => __( 'Settings', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": "' . $this->get_url_mark() . '" + actualDirectoryPath, "fields": config.fields, "term": config.term } );',
				'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
			),
			array(
				'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
				'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
			),
		);

		// if config URL is empty, remove this option from the list.
		if ( empty( $config_url ) ) {
			unset( $actions[1] );
		}

		// return the resulting actions.
		return array_merge(
			parent::get_global_actions(),
			$actions
		);
	}

	/**
	 * Return the URL mark which identifies Google Drive URLs within this plugin.
	 *
	 * @return string
	 */
	public function get_url_mark(): string {
		return 'https://drive.google.com/drive/';
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		// not disabled if mode is set 'manually'.
		if ( $this->is_mode( 'manually' ) ) {
			return false;
		}

		// otherwise check the access token.
		return empty( $this->get_access_token() );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		// show no description on manual connection mode.
		if ( $this->is_mode( 'manually' ) ) {
			return '';
		}

		// get the config URL.
		$config_url = $this->get_config_url();

		// bail if URL is empty.
		if ( empty( $config_url ) ) {
			return '';
		}

		// return the description with link to settings.
		return '<a class="connect button button-secondary" href="' . esc_url( $config_url ) . '">' . __( 'Connect', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Set query params depending on plugin settings.
	 *
	 * @param array<string,mixed> $query The list of query params.
	 *
	 * @return array<string,mixed>
	 */
	public function set_query_params( array $query ): array {
		// collect settings for q.
		$q = array();

		// if directory is set, use it.
		if ( ! empty( $this->directory ) ) {
			$q[]                                = "'" . str_replace( '/', '', $this->directory ) . "' in parents";
			$query['includeItemsFromAllDrives'] = true;
			$query['supportsAllDrives']         = true;
		}

		// get it global, if this is enabled.
		if ( in_array( $this->get_mode(), array( 'global', 'manually' ), true ) ) {
			// do not query for trashed files.
			if ( 1 !== absint( get_option( 'eml_google_drive_show_trashed' ) ) ) {
				$q[] = 'trashed = false';
			}

			// do only query for my own files, not shared files.
			if ( 1 !== absint( get_option( 'eml_google_drive_show_shared' ) ) ) {
				$q[] = "'me' in owners";
			}
		}

		// get the user settings, if this is enabled.
		if ( $this->is_mode( 'user' ) ) {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return $query;
			}

			// do not query for trashed files.
			if ( 1 !== absint( get_user_meta( $user->ID, 'eml_google_drive_show_trashed', true ) ) ) {
				$q[] = 'trashed = false';
			}

			// do only query for my own files, not shared files.
			if ( 1 !== absint( get_user_meta( $user->ID, 'eml_google_drive_show_shared', true ) ) ) {
				$q[] = "'me' in owners";
			}
		}

		// set q.
		$query['q'] = implode( ' and ', $q );

		// return the resulting query param list.
		return $query;
	}

	/**
	 * Preserve the tokens value.
	 *
	 * @param array<string,mixed>|null $new_value The new value.
	 * @param array<string,mixed>      $old_value The old value.
	 *
	 * @return array<string,mixed>
	 */
	public function preserve_tokens_value( array|null $new_value, array $old_value ): array {
		// if new value is null use the old value.
		if ( is_null( $new_value ) ) {
			return $old_value;
		}

		// otherwise return the new value.
		return $new_value;
	}

	/**
	 * Return the URL of our own service to refresh a token.
	 *
	 * @return string
	 */
	private function get_refresh_token_url(): string {
		// set the refresh URI.
		$refresh_uri = EFML_GOOGLE_OAUTH_REFRESH_URL;

		// show deprecated warning for old hook name.
		$refresh_uri = apply_filters_deprecated( 'eml_google_drive_refresh_uri', array( $refresh_uri ), '5.0.0', 'efml_google_drive_refresh_uri' );

		/**
		 * Filter the redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $refresh_uri The redirect URI.
		 */
		return apply_filters( 'efml_google_drive_refresh_uri', $refresh_uri );
	}

	/**
	 * Refresh token by requesting our own endpoint.
	 *
	 * @param \Google\Client $client The Google client object.
	 *
	 * @return array<string,mixed>
	 */
	public function get_refreshed_token( \Google\Client $client ): array {
		// create the URL.
		$url = add_query_arg(
			array(
				'refresh_token' => $client->getRefreshToken(),
			),
			$this->get_refresh_token_url()
		);

		// request the new token.
		$response = wp_safe_remote_get( $url );

		// check the response.
		if ( is_wp_error( $response ) ) {
			// log possible error.
			Log::get_instance()->create( __( 'Error on request to get refreshed token.', 'external-files-in-media-library' ), '', 'error' );
		} else {
			// get the http status.
			$http_status = $response['http_response']->get_status();

			// bail if http status is not 200.
			if ( 200 !== $http_status ) {
				return array();
			}

			// get the body if the response.
			$body = wp_remote_retrieve_body( $response );

			// bail if body is empty.
			if ( empty( $body ) ) {
				return array();
			}

			// decode the response.
			$access_token = json_decode( $body, true );

			// bail if access token is empty.
			if ( empty( $access_token ) ) {
				return array();
			}

			// return the access token.
			return $access_token;
		}

		// return empty array of no setting is valid.
		return array();
	}

	/**
	 * Check if access token for GoogleDrive is set and valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// get the access token.
		$access_token = $this->get_access_token();

		// get config URL.
		$config_url = $this->get_config_url();

		// bail if no access token is set.
		if ( empty( $access_token ) ) {
			// log this event.
			Log::get_instance()->create( __( 'GoogleDrive is not connected!', 'external-files-in-media-library' ), '', 'error', 1 );

			// create error.
			$error = new WP_Error();
			if ( empty( $config_url ) ) {
				/* translators: %1$s will be replaced with a URL. */
				$error->add( 'efml_service_googledrive', __( 'GoogleDrive is not connected.', 'external-files-in-media-library' ) );
			} else {
				/* translators: %1$s will be replaced with a URL. */
				$error->add( 'efml_service_googledrive', sprintf( __( 'GoogleDrive is not connected. Please create a connection to Google Drive <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( $config_url ) ) );
			}

			// add error.
			$this->add_error( $error );

			// return false as login was not successfully.
			return false;
		}

		// get the client.
		$client_obj = new Client( $access_token );
		try {
			$client = $client_obj->get_client();

			// bail if client is not a Client object.
			if ( ! $client instanceof \Google\Client ) {
				// log this event.
				Log::get_instance()->create( __( 'GoogleDrive access token is not valid.', 'external-files-in-media-library' ), '', 'error', 1 );

				// create error.
				$error = new WP_Error();
				if ( empty( $config_url ) ) {
					$error->add( 'efml_service_googledrive', __( 'GoogleDrive access token appears to be no longer valid.', 'external-files-in-media-library' ), esc_url( $config_url ) );
				} else {
					/* translators: %1$s will be replaced with a URL. */
					$error->add( 'efml_service_googledrive', sprintf( __( 'GoogleDrive access token appears to be no longer valid. Please create a new connection to Google Drive <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( $config_url ) ) );
				}

				// add error.
				$this->add_error( $error );

				// return false as login was not successfully.
				return false;
			}
		} catch ( JsonException $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during check of GoogleDrive access token:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );

			// create error.
			$error = new WP_Error();
			if ( empty( $config_url ) ) {
				$error->add( 'efml_service_googledrive', __( 'GoogleDrive access token appears to be no longer valid.', 'external-files-in-media-library' ), esc_url( $config_url ) );
			} else {
				/* translators: %1$s will be replaced with a URL. */
				$error->add( 'efml_service_googledrive', sprintf( __( 'GoogleDrive access token appears to be no longer valid. Please create a new connection to Google Drive <a href="%1$s">here</a>.', 'external-files-in-media-library' ), esc_url( $config_url ) ) );
			}

			// add error.
			$this->add_error( $error );

			// return false as login was not successfully.
			return false;
		}

		// return true as all is ok.
		return true;
	}

	/**
	 * Prevent visibility of not allowed mime types.
	 *
	 * @param bool      $result The result - should be true to prevent the usage.
	 * @param DriveFile $file_obj   The file object.
	 * @param string    $url The used URL.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, DriveFile $file_obj, string $url ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_directory_listing_hide_not_supported_file_types' ) ) ) {
			return $result;
		}

		// get content type of this file.
		$mime_type = wp_check_filetype( $file_obj->getName() );

		// return whether this file type is allowed (false) or not (true).
		return ! in_array( $mime_type['type'], Helper::get_allowed_mime_types(), true );
	}

	/**
	 * Return only the requested directory.
	 *
	 * @param array<string,mixed> $listing The resulting list.
	 * @param string              $url The called URL.
	 * @param string              $service The used service.
	 *
	 * @return array<string,mixed>
	 */
	public function filter_on_directory_request( array $listing, string $url, string $service ): array {
		// bail if this is not our service.
		if ( $this->get_name() !== $service ) {
			return $listing;
		}

		// bail if this is not a specific directory.
		if ( false !== stripos( $url, $this->get_directory() ) ) {
			return $listing;
		}

		// bail if requested directory is not in list.
		if ( empty( $listing[ $url ] ) ) {
			return array();
		}

		// only return the list of dirs and files for the requested URL.
		return array(
			$url => array(
				'title' => $url,
				'files' => $listing[ $url ]['files'],
				'dirs'  => $listing[ $url ]['dirs'],
			),
		);
	}

	/**
	 * Return the Google Drive specific URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function get_url( string $url ): string {
		return $this->get_url_mark() . $url;
	}

	/**
	 * Show option to connect to Google Drive on the user profile.
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

		?><h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'GoogleDrive', 'external-files-in-media-library' ); ?></h3>
		<div class="efml-user-settings">
		<?php

		// get the actual access token.
		$access_token = $this->get_access_token();

		// if no token is set, show hint.
		if ( empty( $access_token ) ) {
			?>
			<a href="#" class="easy-dialog-for-wordpress button button-secondary" data-dialog="<?php echo esc_attr( Helper::get_json( $this->get_connect_dialog() ) ); ?>"><?php echo esc_html__( 'Connect now', 'external-files-in-media-library' ); ?></a>
			<?php
		} else {
			?>
			<a href="#" class="easy-dialog-for-wordpress button button-secondary" data-dialog="<?php echo esc_attr( Helper::get_json( $this->get_disconnect_dialog() ) ); ?>"><?php echo esc_html__( 'Disconnect', 'external-files-in-media-library' ); ?></a><br><br>
			<?php
			/* translators: %1$s will be replaced by a date. */
			echo wp_kses_post( sprintf( __( 'Created at %1$s', 'external-files-in-media-library' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i', absint( $access_token['created'] ) ) ) ) . '<br><br><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . __( 'View and import files', 'external-files-in-media-library' ) . '</a>' );
		}

		// show settings table.
		$this->get_user_settings_table( absint( $user->ID ) );

		?>
		</div>
		<?php
	}

	/**
	 * Return connect dialog.
	 *
	 * @return array<string,mixed>
	 */
	private function get_connect_dialog(): array {
		// get URL to initiate the connection.
		$url = add_query_arg(
			array(
				'action' => 'eml_google_drive_init',
				'nonce'  => wp_create_nonce( 'eml-google-drive-initiate' ),
			),
			get_admin_url() . 'admin.php'
		);

		return array(
			'title'   => __( 'Connect Google Drive', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'You will be directed to a Google dialog. Follow this and confirm the approvals.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'You will also be directed to the website of the plugin developer. This is necessary to allow you to easily share your Google Drive account. No data about you will be stored in this context.', 'external-files-in-media-library' ) . '</p>',
				'<p><strong>' . __( 'Click on the button below to connect your Google Drive with your website.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '"',
					'variant' => 'primary',
					'text'    => __( 'Connect now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Return disconnect dialog.
	 *
	 * @return array<string,mixed>
	 */
	private function get_disconnect_dialog(): array {
		// get URL to disconnect the connection.
		$url = add_query_arg(
			array(
				'action' => 'eml_google_drive_disconnect',
				'nonce'  => wp_create_nonce( 'eml-google-drive-disconnect' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		return array(
			'title'   => __( 'Disconnect Google Drive', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Click on the button below to disconnect your Google Drive from your website.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'Files you downloaded in the media library will still be there and usable.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '"',
					'variant' => 'primary',
					'text'    => __( 'Disconnect now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array {
		$list = array(
			'google_drive_show_shared'  => array(
				'label'    => __( 'Show shared files', 'external-files-in-media-library' ),
				'field'    => 'checkbox',
				'readonly' => $this->is_disabled(),
			),
			'google_drive_show_trashed' => array(
				'label'    => __( 'Show trashed files', 'external-files-in-media-library' ),
				'field'    => 'checkbox',
				'readonly' => $this->is_disabled(),
			),
		);

		/**
		 * Filter the list of possible user settings for Google Drive.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $list The list of settings.
		 */
		return apply_filters( 'efml_service_google_drive_user_settings', $list );
	}

	/**
	 * Check if a file is public.
	 *
	 * @param DriveFile $file         The DriveFile object.
	 * @param Drive     $drive_service_obj The service object used.
	 *
	 * @return bool
	 */
	public function is_file_public( DriveFile $file, Drive $drive_service_obj ): bool {
		try {
			// get the permissions.
			$permissions = $drive_service_obj->permissions->listPermissions( $file->getId() );

			// check each permission.
			foreach ( $permissions->getPermissions() as $permission ) {
				if ( $permission->getType() === 'anyone' && $permission->getRole() === 'reader' ) {
					return true;
				}
			}

			return false;
		} catch ( Exception $e ) {
			Log::get_instance()->create( __( 'Error during loading of file permissions from Google Drive. Error:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $file->getName(), 'error', 1 );
			return false;
		}
	}

	/**
	 * Return the public URL for a given file ID.
	 *
	 * @param string $file_id The file ID.
	 *
	 * @return string
	 */
	public function get_public_url_for_file_id( string $file_id ): string {
		$url = 'https://drive.usercontent.google.com/download?id=' . $file_id . '&export=download';

		/**
		 * Filter the public URL for a given file ID.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 * @param string $file_id The file ID.
		 */
		return apply_filters( 'efml_service_google_drive_public_url', $url, $file_id );
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// get the token.
			$token = $this->get_access_token();

			// set the fields.
			$this->fields = array(
				'access_token' => array(
					'name'        => 'access_token',
					'type'        => ! empty( $token ) && ! $this->is_mode( 'manually' ) ? 'hidden' : 'textarea',
					'label'       => __( 'Access Token', 'external-files-in-media-library' ),
					'placeholder' => __( 'Enter your access token', 'external-files-in-media-library' ),
					'value'       => empty( $token ) ? '' : Helper::get_json( $token ),
					'credential'  => true,
				),
				'folder_id'    => array(
					'name'         => 'folder_id',
					'type'         => 'text',
					'label'        => __( 'Folder Id', 'external-files-in-media-library' ),
					'description'  => __( 'You can find the folder Id in the URL when you show it in your browser.', 'external-files-in-media-library' ),
					'placeholder'  => __( 'Enter a folder Id', 'external-files-in-media-library' ),
					'not_required' => true,
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// TODO bei allen Nutzern löschen.
		$this->delete_access_token();
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		// get the token.
		$token = $this->get_access_token();

		// show other title if access token is set.
		if ( ! empty( $token ) && ! $this->is_mode( 'manually' ) ) {
			return __( 'Connect to your Google Drive', 'external-files-in-media-library' );
		}

		// return default hint.
		return __( 'Enter your access token', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the token.
		$token = $this->get_access_token();

		// bail if token is set.
		if ( ! empty( $token ) && ! $this->is_mode( 'manually' ) ) {
			// if access token is set in plugin settings.
			if ( $this->is_mode( 'global' ) ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return __( 'An access token has already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
				}

				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Your access token is already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			// if access token is set per user.
			if ( $this->is_mode( 'user' ) ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Your access token is already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
			}
		}

		// get URL to initiate the connection.
		$url = add_query_arg(
			array(
				'action' => 'eml_google_drive_init',
				'nonce'  => wp_create_nonce( 'eml-google-drive-initiate' ),
			),
			get_admin_url() . 'admin.php'
		);

		/* translators: %1$s will be replaced by a URL. */
		return sprintf( __( 'Get your access token <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $url );
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
