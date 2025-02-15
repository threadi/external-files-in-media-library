<?php
/**
 * File to handle support for the Google Drive platform.
 *
 * TODO:
 * - API-Schlüssel für öffentliche Verwendung einrichten
 * - Google Auth Service als Plugin fertigstellen & bei thomaszwirner.de unterbringen
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Button;
use ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Plugin\Settings\Settings;
use ExternalFilesInMediaLibrary\Services\GoogleDrive\Client;
use Google\Service\Exception;

/**
 * Object to handle support for this platform.
 */
class GoogleDrive extends Directory_Listing_Base implements Service {
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
	private string $settings_tab = 'eml_googledrive';

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
		$this->title = __( 'Choose file from your Google Drive', 'external-files-in-media-library' );

		// use hooks.
		add_action( 'init', array( $this, 'init_google_drive' ), 20 );
		add_filter( 'query_vars', array( $this, 'set_query_vars' ), 10, 1 );
		add_action( 'admin_action_eml_google_drive_init', array( $this, 'initiate_connection' ) );
		add_action( 'admin_action_eml_google_drive_disconnect', array( $this, 'disconnect' ) );
		add_filter( 'template_include', array( $this, 'check_for_oauth_return_url' ), 10, 1 );
		add_action( 'cli_init', array( $this, 'cli' ) );

		// use our own hooks.
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'eml_blacklist', array( $this, 'check_url' ), 10, 2 );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_filter( 'eml_import_fields', array( $this, 'add_option_for_local_import' ) );
		add_filter( 'eml_google_drive_query_params', array( $this, 'set_query_params' ) );
	}

	/**
	 * Add settings for Google Drive support.
	 *
	 * @return void
	 */
	public function init_google_drive(): void {
		// add the endpoint for Google OAuth.
		add_rewrite_rule( $this->get_oauth_slug() . '?$', 'index.php?' . $this->get_oauth_slug() . '=1', 'top' );

		// get the settings object.
		$settings_obj = Settings::get_instance();

		// add new tab for settings.
		$tab = $settings_obj->add_tab( $this->get_settings_tab_slug() );
		$tab->set_title( __( 'Google Drive', 'external-files-in-media-library' ) );

		// add section for file statistics.
		$section = $tab->add_section( 'section_googledrive_main' );
		$section->set_title( __( 'Google Drive', 'external-files-in-media-library' ) );

		// add setting for button to connect.
		$setting = $settings_obj->add_setting( 'eml_google_drive_connector' );
		$setting->set_section( $section );
		$setting->set_autoload( false );
		$setting->prevent_export( true );

		// get the access token of the actual user.
		$access_token = $this->get_access_token();

		// show connect button if no token is set.
		if ( empty( $access_token ) ) {
			// get URL to initiate the connection.
			$url = add_query_arg(
				array(
					'action' => 'eml_google_drive_init',
					'nonce'  => wp_create_nonce( 'eml-google-drive-initiate' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create dialog.
			$dialog = array(
				'title'   => __( 'Connect Google Drive', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p>' . __( 'You will be directed to a Google dialog. Follow this and confirm the approvals.', 'external-files-in-media-library' ) . '</p>',
					'<p>' . __( 'You will also be directed to the website of the plugin developer. This is necessary to allow you to easily share your Google Drive account. No data about you will be stored in this context.', 'external-files-in-media-library' ) . '</p>',
					'<p><strong>' . __( 'Click on the button below to connect your GoogleDrive with your website.', 'external-files-in-media-library' ) . '</strong></p>',
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

			$field = new Button();
			$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Connect now', 'external-files-in-media-library' ) );
		} else {
			// get URL to disconnect the connection.
			$url = add_query_arg(
				array(
					'action' => 'eml_google_drive_disconnect',
					'nonce'  => wp_create_nonce( 'eml-google-drive-disconnect' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create dialog.
			$dialog = array(
				'title'   => __( 'Disconnect Google Drive', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Click on the button below to disconnect your GoogleDrive from your website.', 'external-files-in-media-library' ) . '</strong></p>',
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

			$field = new Button();
			$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Disconnect', 'external-files-in-media-library' ) );

			// get the creation date from token.
			if ( ! empty( $access_token['created'] ) ) {
				/* translators: %1$s will be replaced by a date and time. */
				$field->set_description( sprintf( __( 'Created at %1$s', 'external-files-in-media-library' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i', $access_token['created'] ) ) ) . '<br><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . __( 'View and import files', 'external-files-in-media-library' ) . '</a>' );
			}
		}
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$setting->set_field( $field );

		// add invisible setting for access token.
		$setting = $settings_obj->add_setting( 'eml_google_drive_access_tokens' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$setting->prevent_export( true );
		$setting->set_show_in_rest( false );
		$setting->set_save_callback( array( $this, 'preserve_tokens_value' ) );

		// add setting to show also shared files.
		$setting = $settings_obj->add_setting( 'eml_google_drive_show_shared' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Show shared files', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting to show also trashed files.
		$setting = $settings_obj->add_setting( 'eml_google_drive_show_trashed' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Show trashed files', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array $protocols List of protocols.
	 *
	 * @return array
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
	 * Return access token for actual WordPress user.
	 *
	 * @return array
	 */
	public function get_access_token(): array {
		// get all access tokens.
		$access_tokens = get_option( 'eml_google_drive_access_tokens', array() );

		// bail if no token are set.
		if ( empty( $access_tokens ) ) {
			return array();
		}

		// bail if no token for actual user is set.
		if ( empty( $access_tokens[ wp_get_current_user()->ID ] ) ) {
			return array();
		}

		// bail if access token is not an array.
		if ( ! is_array( $access_tokens[ wp_get_current_user()->ID ] ) ) {
			return array();
		}

		// return the access token for this user.
		return $access_tokens[ wp_get_current_user()->ID ];
	}

	/**
	 * Set the access token for the actual WordPress user.
	 *
	 * @param array $access_token The access token.
	 * @param int   $user_id
	 *
	 * @return void
	 */
	public function set_access_token( array $access_token, int $user_id = 0 ): void {
		// get actual access token list.
		$access_tokens = get_option( 'eml_google_drive_access_tokens', array() );

		// if list is not an array, create one.
		if ( ! is_array( $access_tokens ) ) {
			$access_tokens = array();
		}

		// get the user_id from session if it is not set.
		if( 0 === $user_id ) {
			$user_id = wp_get_current_user()->ID;
		}

		// get the user object.
		$user = get_user_by( 'id', $user_id );

		// add this token.
		$access_tokens[ $user_id ] = $access_token;

		// log event.
		/* translators: %1$s will be replaced by the username. */
		Log::get_instance()->create( sprintf( __( 'New Google OAuth token saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . $user->display_name . '</em>' ), '', 'info', 2 );

		// save the updated token list.
		update_option( 'eml_google_drive_access_tokens', $access_tokens );
	}

	/**
	 * Delete the access token for the actual WordPress user.
	 *
	 * @return void
	 */
	public function delete_access_token(): void {
		// get actual access token list.
		$access_tokens = get_option( 'eml_google_drive_access_tokens' );

		// bail if user does not have a token.
		if ( empty( $access_tokens[ wp_get_current_user()->ID ] ) ) {
			return;
		}

		// remove the token.
		unset( $access_tokens[ wp_get_current_user()->ID ] );

		// save the updated token list.
		update_option( 'eml_google_drive_access_tokens', $access_tokens );
	}

	/**
	 * Add our OAuth slug to the allowed vars.
	 *
	 * @param array $query_vars List of vars.
	 *
	 * @return array
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
		Log::get_instance()->create( __( 'Given GoogleDrive-URL could not be used as external file in websites.', 'external-files-in-media-library' ), esc_url( $url ), 'error', 0 );

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

		/**
		 * Filter the Google OAuth Client ID for the app used to connect Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $client_id The client ID.
		 */
		return apply_filters( 'eml_google_drive_client_id', $client_id );
	}

	/**
	 * Return the real return URL where the user will land after successfully connection via OAuth.
	 *
	 * @return string
	 */
	private function get_real_redirect_uri(): string {
		// set the token.
		$real_redirect_uri = get_option( 'siteurl' ) . '/' . $this->get_oauth_slug() . '/';

		/**
		 * Filter the real redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $real_redirect_uri The real redirect URI.
		 */
		return apply_filters( 'eml_google_drive_real_redirect_uri', $real_redirect_uri );
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
		// set the state.
		$state = Helper::get_plugin_slug() . ':' . base64_encode( Crypt::get_instance()->get_method()->get_hash() ) . ':' . base64_encode( $this->get_real_redirect_uri() );

		/**
		 * Filter the token to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $state The token.
		 */
		return apply_filters( 'eml_google_drive_state', $state );
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

		/**
		 * Filter the redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $redirect_uri The redirect URI.
		 */
		return apply_filters( 'eml_google_drive_redirect_uri', $redirect_uri );
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

		/**
		 * Filter the params for Google OAuth request.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $params The list of params.
		 */
		$params = apply_filters( 'eml_google_drive_connector_params', $params );

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
	public function disconnect(): void {
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
		$access_token = json_decode( $access_token_string, ARRAY_A );

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

		// forward user to settings page.
		wp_safe_redirect( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug() ) );
		exit;
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
	 * Enable WP CLI for Google Drive tasks.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\Services\GoogleDrive\Cli' );
	}

	/**
	 * Add this object to the list of listing objects.
	 *
	 * @param array $directory_listing_objects List of directory listing objects.
	 *
	 * @return array
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		$directory_listing_objects[] = $this;
		return $directory_listing_objects;
	}

	/**
	 * Add option to import from local directory.
	 *
	 * @param array $fields List of import options.
	 *
	 * @return array
	 */
	public function add_option_for_local_import( array $fields ): array {
		// bail if no token is set.
		if ( empty( $this->get_access_token() ) ) {
			return $fields;
		}

		// add the entry.
		$fields[] = '<details><summary>' . __( 'Or add from your Google Drive', 'external-files-in-media-library' ) . '</summary><div><label for="eml_googledrive"><a href="' . Directory_Listing::get_instance()->get_view_directory_url( $this ) . '" class="button button-secondary">' . esc_html__( 'Add from your Google Drive', 'external-files-in-media-library' ) . '</a></label></div></details>';

		// return the resulting list.
		return $fields;
	}

	/**
	 * Return directory listing from Google Drive.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array
	 * @throws Exception Could be thrown an exception.
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
		$service = new \Google\Service\Drive( $client );

		// collect the request query.
		$query = array(
			'fields'   => 'files(capabilities(canEdit,canRename,canDelete,canShare,canTrash,canMoveItemWithinDrive),description,fileExtension,iconLink,id,driveId,imageMediaMetadata(height,rotation,width,time),mimeType,createdTime,modifiedTime,name,ownedByMe,parents,size,hasThumbnail,thumbnailLink,trashed,videoMediaMetadata(height,width,durationMillis),webContentLink,webViewLink,exportLinks,permissions(id,type,role,domain),copyRequiresWriterPermission,shortcutDetails,resourceKey),nextPageToken',
			'pageSize' => 1000,
			'orderBy'  => 'name_natural',
		);

		/**
		 * Filter the query to get files from Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $query The list of params.
		 */
		$query = apply_filters( 'eml_google_drive_query_params', $query );

		// get the files.
		try {
			$results = $service->files->listFiles( $query );
		} catch ( Exception $e ) {
			// log event.
			Log::get_instance()->create( __( 'List of files could not be loaded from Google Drive. Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $e->getErrors() ) . '</code>', '', 'error' );

			// return an empty list.
			return array();
		}

		// get list of files.
		$files = $results->getFiles();

		// bail if list is empty.
		if ( empty( $files ) ) {
			return array();
		}

		// collect the list of folders.
		$folders = array();

		// collect the list of files.
		$list = array();

		/**
		 * Filter the list of files we got from Google Drive.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param array $files List of files.
		 */
		$files = apply_filters( 'eml_google_drive_files', $files );

		// loop through the files and add them to the list.
		foreach ( $files as $file_obj ) {
			// bail if this is not a file object.
			if ( ! $file_obj instanceof \Google\Service\Drive\DriveFile ) {
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

			// get thumbnail, if set.
			$thumbnail = '';
			if ( $file_obj->getHasThumbnail() ) {
				$thumbnail = '<img src="' . esc_url( $file_obj->getThumbnailLink() ) . '" alt="" class="filepreview">';
			}

			// add settings for entry.
			$entry['file']          = $file_obj->getId();
			$entry['filesize']      = absint( $file_obj->getSize() );
			$entry['mime-type']     = $mime_type;
			$entry['icon']          = '<img src="' . esc_url( $file_obj->getIconLink() ) . '" alt="">';
			$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', strtotime( $file_obj->getCreatedTime() ) ) );
			$entry['preview']       = $thumbnail;

			// get directory-data for this file and add file in the directory.
			if ( $file_obj->getParents() ) {
				// loop through all parent folders and add the file to each of them.
				foreach ( $file_obj->getParents() as $parent_folder_id ) {
					// add file to already existing folder.
					if ( ! empty( $folders[ $parent_folder_id ] ) ) {
						$folders[ $parent_folder_id ]['sub'][] = $entry;
						++$folders[ $parent_folder_id ]['count'];
						continue;
					}

					// get folder data.
					$parent_folder_obj = $service->files->get( $parent_folder_id );

					// add file to this folder and define the folder for this.
					$folders[ $parent_folder_id ] = array(
						'title' => $parent_folder_obj->getName(),
						'dir'   => $parent_folder_obj->getId(),
						'sub'   => array(
							$entry,
						),
						'count' => 1,
					);
				}
			} else {
				// add entry to the list.
				$list[] = $entry;
			}
		}

		// return the resulting file list.
		return array_merge( array_values( $folders ), $list );
	}

	/**
	 * Return the actions.
	 *
	 * @return array
	 */
	public function get_actions(): array {
		return array(
			array(
				'action' => 'efml_import_file( "' . $this->get_url_mark() . '" + file.file, login, password, term );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'location.href="https://drive.google.com/drive/my-drive";',
					'label'  => __( 'Go to Google Drive', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'location.href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug() ) ) . '";',
					'label'  => __( 'Settings', 'external-files-in-media-library' ),
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
		return 'https://drive.google.com/drive/';
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return empty( $this->get_access_token() );
	}

	/**
	 * Return the description for this listing object.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return '<a class="connect button button-secondary" href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug() ) ) . '">' . __( 'Connect', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Set query params depending on plugin settings.
	 *
	 * @param array $query The list of query params.
	 *
	 * @return array
	 */
	public function set_query_params( array $query ): array {
		// collect settings for q.
		$q = array();

		// do not query for trashed files.
		if ( 1 !== absint( get_option( 'eml_google_drive_show_trashed' ) ) ) {
			$q[] = 'trashed = false';
		}

		// do only query for my own files, not shared files.
		if ( 1 !== absint( get_option( 'eml_google_drive_show_shared' ) ) ) {
			$q[] = "'me' in owners";
		}

		// set q.
		$query['q'] = implode( ' and ', $q );

		// return the resulting query param list.
		return $query;
	}

	/**
	 * Preserve the tokens value.
	 *
	 * @param array|null $new_value The new value.
	 * @param array      $old_value The old value.
	 *
	 * @return array
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

		/**
		 * Filter the redirect URI to connect the Google OAuth Client.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param string $refresh_uri The redirect URI.
		 */
		return apply_filters( 'eml_google_drive_refresh_uri', $refresh_uri );
	}

	/**
	 * Refresh token by requesting our own endpoint.
	 *
	 * @param \Google\Client $client The Google client object.
	 *
	 * @return array
	 */
	public function get_refreshed_token( \Google\Client $client ): array {
		// create the URL.
		$url = add_query_arg(
			array(
				'refresh_token' => $client->getRefreshToken()
			),
			$this->get_refresh_token_url()
		);

		// request the new token.
		$response = wp_safe_remote_get( $url );

		// check the response.
		if ( is_wp_error( $response ) ) {
			// log possible error.
			Log::get_instance()->create( __( 'Error on request to get refreshed token.', 'external-files-in-media-library' ), '' , 'error' );
		} elseif ( empty( $response ) ) {
			// log im result is empty.
			Log::get_instance()->create( __( 'Got empty response for refreshing the token.', 'external-files-in-media-library' ), '', 'error' );
		} else {
			// get the http status.
			$http_status = $response['http_response']->get_status();

			// bail if http status is not 200.
			if( 200 !== $http_status ) {
				return array();
			}

			// get the body if the response.
			$body = wp_remote_retrieve_body( $response );

			// bail if body is empty.
			if( empty( $body ) ) {
				return array();
			}

			// decode the response.
			$access_token = json_decode( $body, ARRAY_A );

			// bail if access token is empty.
			if( empty( $access_token ) ) {
				return array();
			}

			// return the access token.
			return $access_token;
		}

		return array();
	}
}
