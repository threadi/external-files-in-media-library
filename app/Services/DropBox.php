<?php
/**
 * File to handle support for the DropBox platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Crypt;
use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Taxonomy;
use easySettingsForWordPress\Fields\Button;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use Error;
use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Plugin\Setup;
use ExternalFilesInMediaLibrary\Services\DropBox\Export;
use ExternalFilesInMediaLibrary\Services\DropBox\Protocol;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;
use Throwable;
use WP_Error;
use WP_Image_Editor;
use WP_User;
use WP_User_Query;

/**
 * Object to handle support for this platform.
 */
class DropBox extends Service_Base implements Service {
	/**
	 * The context for the encryption of the global access tokens.
	 */
	public const ACCESS_TOKENS_OPTION_CONTEXT = 'option:efml_dropbox_access_tokens';

	/**
	 * The context for the encryption of the access tokens of a user.
	 */
	public const ACCESS_TOKENS_USER_CONTEXT = 'usermeta:efml_dropbox_access_tokens';

	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'dropbox';

	/**
	 * The user, whose connection to DropBox is used at the moment (e.g. the owner of the external source of a file).
	 *
	 * @var WP_User|false
	 */
	private WP_User|false $context_user = false;

	/**
	 * Marker whether the values of the constants are values of the fields of this service.
	 *
	 * Disabled, as only the API credentials of the app could be defined via constants, which are no fields.
	 *
	 * @var bool
	 */
	protected bool $constants_in_fields = false;

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'DropBox';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	protected string $settings_sub_tab = 'eml_dropbox';

	/**
	 * Instance of actual object.
	 *
	 * @var ?DropBox
	 */
	private static ?DropBox $instance = null;

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
	 * @return DropBox
	 */
	public static function get_instance(): DropBox {
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
		add_action( 'init', array( $this, 'add_settings' ), 30 );

		// hide files with a not allowed type in the directory listing, if enabled.
		add_filter( 'efml_service_dropbox_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 4 );

		// download exported files via the API for their restore (also without a user, e.g. via cron).
		add_filter( 'efml_restore_download_urls', array( $this, 'add_restore_download_url' ), 10, 2 );

		// follow the forwarding of shared links to the file content, also for visitors (e.g. for the proxy).
		add_filter( 'http_request_args', array( $this, 'follow_forwarding_of_shared_links' ), 20, 2 );

		// load exported files with an expiring URL via the API, also for visitors (e.g. for the proxy).
		add_filter( 'efml_http_temp_file', array( $this, 'load_exported_file_via_api' ), 10, 2 );

		// use the protocol in each request, e.g. for private files via the proxy and for exports of uploads by users
		// without the capability for DropBox (imports check the capability via the protocol).
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );

		// handle shared links of DropBox for each user, who is allowed to add external files: they are public URLs,
		// which do not use the connection to DropBox (e.g. their conversion to the file itself and the content type).
		add_filter( 'efml_http_check_content_type', array( $this, 'allow_wrong_content_type' ), 10, 2 );
		add_filter( 'efml_files_check_content_type', array( $this, 'allow_wrong_content_type' ), 10, 2 );
		add_filter( 'efml_http_header_response', array( $this, 'get_real_request_headers' ), 10, 3 );
		add_filter( 'efml_import_url', array( $this, 'convert_dropbox_urls' ) );

		// bail if user has no capability for this service.
		if ( ! $this->is_import_allowed() ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from your DropBox', 'external-files-in-media-library' );

		// use hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_js_admin' ) );
		add_action( 'show_user_profile', array( $this, 'add_user_settings' ), 10, 0 );
		add_filter( 'query_vars', array( $this, 'set_query_vars' ) );
		add_filter( 'template_include', array( $this, 'check_for_oauth_return_url' ), 10, 1 );

		// use AJAX hook.
		add_action( 'wp_ajax_efml_dropbox_setup_connection', array( $this, 'add_connection_by_ajax' ), 10, 0 );
		add_action( 'wp_ajax_efml_remove_access_token', array( $this, 'remove_access_token_by_ajax' ), 10, 0 );

		// use our own hooks.
		add_filter( 'efml_directory_listing', array( $this, 'resort_for_subdirectories' ), 10, 3 );
	}

	/**
	 * Add settings for DropBox support.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// add the endpoint for DropBox OAuth.
		add_rewrite_rule( $this->get_oauth_slug() . '?$', 'index.php?' . $this->get_oauth_slug() . '=1', 'top' );

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

		// get tab for settings.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// add a section for file statistics.
		$section = $tab->get_section( 'section_dropbox_main' );

		// bail if tab does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add invisible setting for access token global.
		$setting = $settings_obj->add_setting( 'efml_dropbox_access_tokens' );
		$setting->set_section( $section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->prevent_export( true );
		$setting->set_save_callback( array( $this, 'preserve_tokens_value' ) );

		// add setting for a button to connect.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) || $this->is_mode( 'global' ) ) {
			$setting = $settings_obj->add_setting( 'eml_dropbox_connector' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );

			// get the access token of the actual user.
			$access_token = $this->get_access_token();

			// show connect button if no token is set.
			if ( empty( $access_token ) ) {
				$field = new Button( $settings_obj );
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Connect', 'external-files-in-media-library' ) );
				$field->set_description( $this->get_help( false ) );
				$field->add_class( 'easy-dialog-for-wordpress' );
				$field->add_data( 'dialog', Helper::get_json( $this->get_connect_dialog() ) );
			} else {
				// create the dialog.
				$dialog = $this->get_disconnect_dialog();

				$field = new Button( $settings_obj );
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Disconnect', 'external-files-in-media-library' ) );
				$field->set_description( $this->get_connect_info() );
				$field->add_class( 'easy-dialog-for-wordpress' );
				$field->add_data( 'dialog', Helper::get_json( $dialog ) );
			}
			$setting->set_field( $field );
		}

		if ( $this->is_mode( 'user' ) ) {
			$setting = $settings_obj->add_setting( 'eml_dropbox_credential_location_hint' );
			$setting->set_section( $section );
			$setting->prevent_export( true );
			$field = new TextInfo( $settings_obj );
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in their own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
			$setting->set_field( $field );
		}
	}

	/**
	 * Set a pseudo-directory to force the directory listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// bail if directory is set on the object.
		if ( ! empty( $this->directory ) ) {
			return $this->directory;
		}

		// just return the name.
		return 'DropBox';
	}

	/**
	 * Return whether this listing object is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		// bail if no SSL is used by this project (necessary for OAuth).
		if ( ! is_ssl() ) {
			return true;
		}

		// not disabled if mode is set to "manually".
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
		// show hint if no SSL is used (necessary for OAuth).
		if ( ! is_ssl() ) {
			return '<p>' . __( 'SSL is required in your hosting', 'external-files-in-media-library' ) . '</p>';
		}

		// show no description on manual connection mode.
		if ( $this->is_mode( 'manually' ) ) {
			return '';
		}

		// get the URL where the setting can be found.
		$url = get_admin_url() . 'profile.php#efml-' . $this->get_name();
		if ( $this->is_mode( 'global' ) ) {
			// bail if user has no capability to load the global settings.
			if ( ! current_user_can( 'manage_options' ) ) {
				return '';
			}

			// get the URL for the global settings.
			$url = Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
		}

		// return the description with link to settings.
		return '<a class="connect button button-secondary" href="' . esc_url( $url ) . '">' . __( 'Connect', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Preserve the tokens value.
	 *
	 * @param string|null $new_value The new value.
	 * @param string      $old_value The old value.
	 *
	 * @return string
	 */
	public function preserve_tokens_value( string|null $new_value, string $old_value ): string {
		// if new value is null use the old value.
		if ( is_null( $new_value ) ) {
			return $old_value;
		}

		// otherwise return the new value.
		return $new_value;
	}

	/**
	 * Add our dropbox JS.
	 *
	 * @return void
	 */
	public function add_js_admin(): void {
		global $pagenow;

		// load these files if:
		// - we are on a user profile and location is set to "user"
		// - we are on plugin settings and location is set to "global".
		$use_it = false;
		$page   = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'eml_settings' === $page && 'global' === $this->get_mode() ) {
			$use_it = true;
		}
		if ( 'profile.php' === $pagenow ) {
			$use_it = true;
		}
		if ( Setup::get_instance()->is_setup_page() ) {
			$use_it = true;
		}

		// bail if marker is not set.
		if ( ! $use_it ) {
			return;
		}

		// backend-JS.
		wp_enqueue_script(
			'eml-admin-dropbox',
			Helper::get_plugin_url() . 'admin/dropbox.js',
			array( 'jquery', 'eml-admin' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/dropbox.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-admin-dropbox',
			'efmlJsVarsDropBox',
			array(
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'dropbox_connect_nonce'         => wp_create_nonce( 'efml-dropbox-save-access-token' ),
				'access_token_disconnect_nonce' => wp_create_nonce( 'efml-dropbox-remove-access-token' ),
			)
		);
	}

	/**
	 * Setup connection to DropBox API via AJAX request.
	 *
	 * @return void
	 */
	public function add_connection_by_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-dropbox-save-access-token', 'nonce' );

		// check capability.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// the connection for the whole website must only be changed by users who are allowed to manage the settings.
		if ( $this->is_mode( 'global' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'detail' => $this->get_not_allowed_dialog() ) );
		}

		// check whether the API credentials are defined via constants.
		$api_via_constants = $this->is_api_defined_via_constants();

		// get the API key.
		$api_key = $api_via_constants ? $this->get_api_key() : filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get the API secret.
		$api_secret = $api_via_constants ? $this->get_api_secret() : filter_input( INPUT_POST, 'api_secret', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if any value is missing.
		if ( empty( $api_key ) || empty( $api_secret ) ) {
			// create the dialog.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Error', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p>' . __( 'Please enter the API credentials!', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);

			// return this message.
			wp_send_json_error( array( 'detail' => $dialog ) );
			exit; // @phpstan-ignore deadCode.unreachable
		}

		// save the values, if they are not defined via constants.
		if ( ! $api_via_constants ) {
			$this->set_api_key( $api_key );
			$this->set_api_secret( $api_secret );
		}

		// create a random state for this request and save it for the actual user.
		// It is checked on return from DropBox to prevent that a foreign authorization is saved here.
		$state = wp_generate_password( 32, false );
		update_user_meta( get_current_user_id(), 'efml_dropbox_oauth_state', $state );

		// run connection.
		$params = array(
			'client_id'         => $api_key,
			'response_type'     => 'code',
			'redirect_uri'      => $this->get_real_redirect_uri(),
			'token_access_type' => 'offline',
			'state'             => $state,
		);

		// redirect the user to dropbox for authorization.
		wp_send_json_success( array( 'url' => $this->get_oauth_url_step_2() . http_build_query( $params ) ) );
	}

	/**
	 * Return the user for the connection to DropBox: the user set for the actual task (e.g. the owner of the external
	 * source of a file), otherwise the configured or the actual user.
	 *
	 * @return WP_User|false
	 */
	protected function get_user(): WP_User|false {
		// use the user of the actual task, if set.
		if ( $this->context_user instanceof WP_User ) {
			return $this->context_user;
		}

		// use the configured or the actual user.
		return parent::get_user();
	}

	/**
	 * Return the user, whose connection to DropBox is used for the given external source: its owner.
	 *
	 * Only if each user has its own connection to DropBox. Returns false, if the connection of the website is used
	 * or the owner is unknown.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return WP_User|false
	 */
	public function get_owner_of_source( int $term_id ): WP_User|false {
		// bail if the connection of the website is used.
		if ( 0 === $term_id || $this->is_mode( 'global' ) ) {
			return false;
		}

		// bail if this is not an external source of DropBox.
		if ( $this->get_name() !== get_term_meta( $term_id, 'type', true ) ) {
			return false;
		}

		// get the owner of the external source.
		$user = get_user_by( 'id', absint( get_term_meta( $term_id, 'user_id', true ) ) );

		// return the user, if it exists.
		return $user instanceof WP_User ? $user : false;
	}

	/**
	 * Return the user, whose connection to DropBox is used for the given file: the owner of its external source.
	 *
	 * So the file could be loaded with the connection it has been exported with, independent of the actual user
	 * (e.g. for visitors via the proxy, in cron or for other users). Returns false, if the connection of the website
	 * is used or the external source is unknown.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID of the file.
	 *
	 * @return WP_User|false
	 */
	public function get_owner_of_file( int $attachment_id ): WP_User|false {
		// bail if the connection of the website is used.
		if ( 0 === $attachment_id || $this->is_mode( 'global' ) ) {
			return false;
		}

		// get the external sources of this file: the export sources and the assigned external sources.
		$term_ids = get_post_meta( $attachment_id, 'efml_export_sources', true );
		if ( ! is_array( $term_ids ) ) {
			$term_ids = array();
		}
		$assigned = wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) );
		if ( is_array( $assigned ) ) {
			$term_ids = array_merge( $term_ids, $assigned );
		}

		// use the owner of the first external source of DropBox.
		foreach ( array_unique( array_map( 'absint', $term_ids ) ) as $term_id ) {
			$user = $this->get_owner_of_source( $term_id );
			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		// return false as no owner is known.
		return false;
	}

	/**
	 * Return the DropBox client with the connection of the given user (without user: of the actual user).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param WP_User|false $user The user.
	 *
	 * @return Client|false
	 */
	public function get_client_for_user( WP_User|false $user ): Client|false {
		$previous           = $this->context_user;
		$this->context_user = $user;
		try {
			return $this->get_client();
		} finally {
			$this->context_user = $previous;
		}
	}

	/**
	 * Return the access token data of the given user (without user: of the actual user).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param WP_User|false $user The user.
	 *
	 * @return array<string,mixed>
	 */
	public function get_access_token_for_user( WP_User|false $user ): array {
		$previous           = $this->context_user;
		$this->context_user = $user;
		try {
			return $this->get_access_token();
		} finally {
			$this->context_user = $previous;
		}
	}

	/**
	 * Return access token data.
	 *
	 * @return array<string,mixed>
	 */
	public function get_access_token(): array {
		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$data = get_option( 'efml_dropbox_access_tokens', '' );
			$data = Crypt::get_instance()->decrypt( is_string( $data ) ? $data : '', self::ACCESS_TOKENS_OPTION_CONTEXT );
		} else {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return array();
			}

			// get and return the value.
			$data = Crypt::get_instance()->decrypt( (string) get_user_meta( $user->ID, 'efml_dropbox_access_tokens', true ), self::ACCESS_TOKENS_USER_CONTEXT );
		}

		// convert JSON to array.
		$data = json_decode( $data, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		// check the expires time.
		$expires = $data['expires'];

		// refresh token if it is expiring.
		if ( time() >= ( $expires - 60 ) ) {
			$data = $this->refresh_token( $data );
		}

		// return the resulting access_token data.
		return $data;
	}

	/**
	 * Set the access token data array from DropBox.
	 *
	 * @param array<string,mixed> $data The data for the access token.
	 *
	 * @return void
	 */
	private function set_access_token( array $data ): void {
		// save it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// log event.
			Log::get_instance()->create( __( 'New DropBox API access token has been saved for global usage.', 'external-files-in-media-library' ), '', 'info', 2 );

			// save the updated token.
			update_option( 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $data ), self::ACCESS_TOKENS_OPTION_CONTEXT ) );
		}

		// get the user_id from the session if it is not set.
		$user    = $this->get_user();
		$user_id = 0;
		if ( $user instanceof WP_User ) {
			$user_id = $user->ID;
		}

		// bail if no user could be found.
		if ( 0 === $user_id ) {
			return;
		}

		// log event.
		/* translators: %1$s will be replaced by the username. */
		Log::get_instance()->create( sprintf( __( 'New DropBox API key saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . ( $user instanceof WP_User ? $user->display_name : '' ) . '</em>' ), '', 'info', 2 ); // @phpstan-ignore instanceof.alwaysTrue

		// save the token.
		update_user_meta( $user_id, 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $data ), self::ACCESS_TOKENS_USER_CONTEXT ) );
	}

	/**
	 * Return the used API key.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return string
	 */
	public function get_api_key( int $user_id = 0 ): string {
		// use the value of the constant, if set.
		$constant_value = $this->get_constant_value( 'api_key' );
		if ( ! is_null( $constant_value ) ) {
			return $constant_value;
		}

		// get the global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			return (string) get_option( 'efml_dropbox_api_key', '' );
		}

		// get the user_id from the session if it is not set.
		if ( 0 === $user_id ) {
			// get the user.
			$user = $this->get_user();
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}
			$user_id = $user->ID;
		} else {
			// get the user object.
			$user = get_user_by( 'id', $user_id );
			if ( ! $user instanceof WP_User ) {  // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}
		}

		// return the decrypted API key.
		return Crypt::get_instance()->decrypt( (string) get_user_meta( $user_id, 'efml_dropbox_api_key', true ), 'usermeta:efml_dropbox_api_key' );
	}

	/**
	 * Set the API key depending on actual setting.
	 *
	 * @param string $key The API key.
	 * @param int    $user_id The user ID (optional).
	 *
	 * @return void
	 */
	public function set_api_key( string $key, int $user_id = 0 ): void {
		// save it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// log event.
			Log::get_instance()->create( __( 'New DropBox API key saved for global usage.', 'external-files-in-media-library' ), '', 'info', 2 );

			// save the updated token.
			update_option( 'efml_dropbox_api_key', $key );
		}

		// get the user_id from the session if it is not set.
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
		Log::get_instance()->create( sprintf( __( 'New DropBox API key saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . $user->display_name . '</em>' ), '', 'info', 2 );

		// save the token.
		update_user_meta( $user_id, 'efml_dropbox_api_key', Crypt::get_instance()->encrypt( $key, 'usermeta:efml_dropbox_api_key' ) );
	}

	/**
	 * Return the used API key.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return string
	 */
	public function get_api_secret( int $user_id = 0 ): string {
		// use the value of the constant, if set.
		$constant_value = $this->get_constant_value( 'api_secret' );
		if ( ! is_null( $constant_value ) ) {
			return $constant_value;
		}

		// get the global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			return (string) get_option( 'efml_dropbox_api_secret', '' );
		}

		// get the user_id from the session if it is not set.
		if ( 0 === $user_id ) {
			// get the user.
			$user = $this->get_user();
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}
			$user_id = $user->ID;
		} else {
			// get the user object.
			$user = get_user_by( 'id', $user_id );
			if ( ! $user instanceof WP_User ) {  // @phpstan-ignore instanceof.alwaysTrue
				return '';
			}
		}

		// return the decrypted API key.
		return Crypt::get_instance()->decrypt( (string) get_user_meta( $user_id, 'efml_dropbox_api_secret', true ), 'usermeta:efml_dropbox_api_secret' );
	}

	/**
	 * Set the API key depending on actual setting.
	 *
	 * @param string $secret The API secret.
	 * @param int    $user_id The user ID (optional).
	 *
	 * @return void
	 */
	public function set_api_secret( string $secret, int $user_id = 0 ): void {
		// save it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// log event.
			Log::get_instance()->create( __( 'New DropBox API secret saved for global usage.', 'external-files-in-media-library' ), '', 'info', 2 );

			// save the updated token.
			update_option( 'efml_dropbox_api_secret', $secret );
		}

		// get the user_id from the session if it is not set.
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
		Log::get_instance()->create( sprintf( __( 'New DropBox API secret saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . $user->display_name . '</em>' ), '', 'info', 2 );

		// save the token.
		update_user_meta( $user_id, 'efml_dropbox_api_secret', Crypt::get_instance()->encrypt( $secret, 'usermeta:efml_dropbox_api_secret' ) );
	}

	/**
	 * Delete the access token.
	 *
	 * @return bool
	 */
	public function delete_access_token(): bool {
		// save it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			// delete it global.
			update_option( 'efml_dropbox_access_tokens', '' );

			// we are done, do nothing more.
			return true;
		}

		// get the user.
		$user = $this->get_user();
		if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
			return false;
		}
		$user_id = $user->ID;

		// clear the user meta.
		delete_user_meta( $user_id, 'efml_dropbox_access_tokens' );

		// return true as token has been removed.
		return true;
	}

	/**
	 * Remove access token via AJAX request.
	 *
	 * @return void
	 */
	public function remove_access_token_by_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-dropbox-remove-access-token', 'nonce' );

		// check capability.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// the connection for the whole website must only be changed by users who are allowed to manage the settings.
		if ( $this->is_mode( 'global' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'detail' => $this->get_not_allowed_dialog() ) );
		}

		// remove the token.
		if ( ! $this->delete_access_token() ) {
			// create the dialog.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Error', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p>' . __( 'An error occurred during removing the access token!', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'location.reload();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);

			// return this message.
			wp_send_json_error( array( 'detail' => $dialog ) );
		}

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'DropBox access token removed', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p>' . __( 'You will now not be able to use DropBox in your WordPress backend.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.reload();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// return ok.
		wp_send_json_success( array( 'detail' => $dialog ) );
	}

	/**
	 * Create the help to create an app and get access token.
	 *
	 * @param bool $with_button Show button or not.
	 *
	 * @return string
	 */
	private function get_help( bool $with_button = true ): string {
		$help = esc_html__( 'Follow these steps:', 'external-files-in-media-library' ) . '</p><ol>';
		/* translators: %1$s will be replaced by a URL. */
		$help .= '<li>' . sprintf( __( 'Create your own app <a href="%1$s" target="_blank">here%2$s</a>.', 'external-files-in-media-library' ), $this->get_token_url(), Helper::get_a11n_window_hint() ) . '</li>';
		$help .= '<li>' . esc_html__( 'Enter the following as OAuth2 Redirect URL for this app:', 'external-files-in-media-library' ) . ' <code>' . $this->get_real_redirect_uri() . '</code></li>';
		$help .= '<li>' . esc_html__( 'Click on the following button.', 'external-files-in-media-library' ) . '</li></ol>';
		if ( $with_button ) {
			$help .= '<p><a href="#" class="easy-dialog-for-wordpress button button-secondary" data-dialog="' . esc_attr( Helper::get_json( $this->get_connect_dialog() ) ) . '">' . esc_html__( 'Connect now', 'external-files-in-media-library' ) . '</a>';
		}
		return $help;
	}

	/**
	 * Return info about the active connection.
	 *
	 * @return string
	 */
	private function get_connect_info(): string {
		// get the client.
		$client = $this->get_client();

		// bail if client could not be loaded.
		if ( ! $client instanceof Client ) {
			return '';
		}

		// get the account infos.
		$account_infos = array();
		try {
			$account_infos = $client->getAccountInfo();
		} catch ( \Exception $e ) {
			// also network errors (e.g. DropBox is not reachable) must not break the request.
			Log::get_instance()->create( __( 'Error during request of DropBox account infos:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error' );
		}

		// bail if account infos are empty.
		if ( empty( $account_infos ) ) {
			return '<strong>' . esc_html__( 'Could not load the DropBox account infos.', 'external-files-in-media-library' ) . '</strong> ' . esc_html__( 'This usually means that the access token is no longer valid. Please reconnect with a new access token.', 'external-files-in-media-library' ) . '<br>';
		}

		// collect the text for return.
		$infos  = '<strong>' . esc_html__( 'You are connected to this DropBox account:', 'external-files-in-media-library' ) . '</strong><br>';
		$infos .= '<strong>' . esc_html__( 'Name:', 'external-files-in-media-library' ) . '</strong> ' . esc_html( $account_infos['name']['display_name'] ) . '<br>';
		$infos .= '<strong>' . esc_html__( 'Email:', 'external-files-in-media-library' ) . '</strong> ' . esc_html( $account_infos['email'] ) . '<br>';
		$infos .= '<strong>' . esc_html__( 'Account ID:', 'external-files-in-media-library' ) . '</strong> ' . esc_html( $account_infos['account_id'] );
		$infos .= '<br><br><a href="' . esc_url( Directory_Listing::get_instance()->get_view_directory_url( $this ) ) . '" class="button button-secondary">' . esc_html__( 'View and import your files', 'external-files-in-media-library' ) . '</a>';

		// return the infos.
		return $infos;
	}

	/**
	 * Return directory listing from Dropbox.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the client with the given token.
		$client = $this->get_client();

		// bail if client could not be loaded.
		if ( ! $client instanceof Client ) {
			return array();
		}

		// collect the list of files.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// get the requested subdirectory.
		$subdirectory = '/';
		if ( str_contains( $directory, '/' ) ) {
			$subdirectory = str_replace( 'DropBox', '', $directory );
			$directory    = 'DropBox';
		}

		// get the entries (files and folders).
		$entries = array();
		try {
			$entries = $client->listFolder( $subdirectory, true );
		} catch ( ClientException $e ) {
			Log::get_instance()->create( __( 'Error during request of DropBox entries:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );
		}

		// bail if list is empty.
		if ( empty( $entries ) ) {
			return $listing;
		}

		// bail if entries does not exist.
		if ( empty( $entries['entries'] ) ) {
			return $listing;
		}

		// create list of folders.
		$folders = array();

		// get upload directory.
		$upload_dir_data = wp_get_upload_dir();
		$upload_dir      = trailingslashit( $upload_dir_data['basedir'] ) . 'edlfw/';
		$upload_url      = trailingslashit( $upload_dir_data['baseurl'] ) . 'edlfw/';

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// add the entries to the list.
		foreach ( $entries['entries'] as $dropbox_entry ) {
			$false = false;
			/**
			 * Filter whether the given DropBox entry should be hidden.
			 *
			 * @since 5.6.0 Available since 5.6.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param string $path The path of the entry in the DropBox.
			 * @param string $directory The requested directory.
			 * @param bool $is_dir True if this entry is a directory.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'efml_service_dropbox_hide_file', $false, $dropbox_entry['path_lower'], $directory, 'folder' === $dropbox_entry['.tag'] ) ) {
				continue;
			}

			// collect the entry.
			$entry = array(
				'title' => $dropbox_entry['name'],
			);

			// if this is a directory, add it there.
			if ( 'folder' === $dropbox_entry['.tag'] ) {
				$listing['dirs'][ trailingslashit( $directory . $dropbox_entry['path_lower'] ) ] = array(
					'title' => $dropbox_entry['name'],
					'files' => array(),
					'dirs'  => array(),
				);
				// add the directory to the list.
				$folders[ trailingslashit( $directory . $dropbox_entry['path_lower'] ) ] = array(
					'title' => $dropbox_entry['name'],
					'files' => array(),
					'dirs'  => array(),
				);
			} else {
				// get parts of the path.
				$parts = explode( '/', $dropbox_entry['path_lower'] );

				// get the content-type of this file.
				$mime_type = wp_check_filetype( $dropbox_entry['path_lower'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// get the binary stream of the file for the preview.
				$thumbnail = '';
				if ( str_contains( $mime_type['type'], 'image/' ) && Init::get_instance()->is_preview_enabled() ) {
					try {
						$dropbox_thumbnail = $client->getThumbnail( $dropbox_entry['path_lower'], 'jpeg', 'w32h32' );
						if ( ! empty( $dropbox_thumbnail ) ) {
							// save this file as temp file.
							$filename = $this->save_temp_file( $dropbox_thumbnail );

							// if temp path is given, save it via image editor.
							if ( is_string( $filename ) ) {
								// get image editor object of the file to get a thumb of it.
								$editor = wp_get_image_editor( $filename );

								// get the thumb via image editor object.
								if ( $editor instanceof WP_Image_Editor ) {
									// set size for the preview.
									$editor->resize( 32, 32 );

									// save the thumb.
									$results = $editor->save( $upload_dir . '/' . basename( $dropbox_entry['path_lower'] ) );

									// add the thumb to output if it does not result in an error.
									if ( ! is_wp_error( $results ) ) {
										$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
									}
								}

								// delete the temp file.
								$wp_filesystem->delete( $filename );
							}
						}
					} catch ( ClientException $e ) {
						Log::get_instance()->create( __( 'Error during request for thumbnail from DropBox:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );
					}
				}

				// add settings for entry.
				$entry['file']          = $directory . $dropbox_entry['path_lower'];
				$entry['filesize']      = absint( $dropbox_entry['size'] );
				$entry['mime-type']     = $mime_type['type'];
				$entry['icon']          = '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type['type'] ) . '"></span>';
				$entry['last-modified'] = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( strtotime( $dropbox_entry['client_modified'] ) ) ) );
				$entry['preview']       = $thumbnail;

				// if parts contains more than 2 entries, this file is in a subdirectory.
				if ( count( $parts ) > 2 ) {
					// get the path.
					$path = dirname( $dropbox_entry['path_lower'] );

					// add it to this list.
					$folders[ trailingslashit( $directory . $path ) ]['files'][] = $entry;
				} else {
					// add the entry to the list.
					$listing['files'][] = $entry;
				}
			}
		}

		// return the resulting list.
		return array_merge( array( 'completed' => true ), array( $directory => $listing ), $folders );
	}

	/**
	 * Prevent the visibility of files with a not allowed mime type, if this is enabled in the settings.
	 *
	 * @param bool   $result The result - should be true to hide the entry.
	 * @param string $path The path of the entry.
	 * @param string $directory The requested directory.
	 * @param bool   $is_dir True if this entry is a directory.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_not_allowed_files( bool $result, string $path, string $directory, bool $is_dir ): bool {
		// bail if this is a directory.
		if ( $is_dir ) {
			return $result;
		}

		// get the content-type of this file.
		$mime_type = wp_check_filetype( $path );

		// return whether this file should be hidden.
		return $result || $this->is_mime_type_hidden( (string) $mime_type['type'] );
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
	 * Return the URL of the web interface of DropBox.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	public function get_platform_url(): string {
		return 'https://www.dropbox.com';
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
					'action' => 'location.href="' . esc_url( $this->get_platform_url() ) . '";',
					'label'  => __( 'Go to DropBox', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'location.href="' . esc_url( Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '";',
					'label'  => __( 'Settings', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save this DropBox as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * Save a temp file from DropBox and return its path.
	 *
	 * @param string $content The content of the file.
	 *
	 * @return string|false
	 */
	private function save_temp_file( string $content ): string|false {
		// bail if no content could be loaded.
		if ( ! $content ) {
			return false;
		}

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the temp file name.
		$tmp_file_name = wp_tempnam();

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', $tmp_file_name . '.jpg' );

		// and save the file there.
		try {
			$wp_filesystem->put_contents( $tmp_file, $content );
			$wp_filesystem->delete( $tmp_file_name );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			/* translators: %1$s will be replaced by a URL. */
			$error_obj->set_result_text( sprintf( __( 'Error occurred during requesting this file. Check the <a href="%1$s" target="_blank">log</a> for detailed information.', 'external-files-in-media-library' ), Helper::get_log_url( $this->get_url( '' ) ) ) );
			$error_obj->set_url( $this->get_url( '' ) );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url( '' ), 'error' );

			// do nothing more.
			return false;
		}

		// return the path to the tmp file.
		return $tmp_file;
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// add the DropBox protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\DropBox\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Check if access token for DropBox is set and valid.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// get the client with the given token.
		$client = $this->get_client();

		// client could not be loaded.
		if ( ! $client instanceof Client ) {
			// create error.
			$error = new WP_Error();
			/* translators: %1$s will be replaced with a URL. */
			$error->add( 'efml_service_dropbox', sprintf( __( 'DropBox access token is not configured. Please create a new one and <a href="%1$s">add it here</a>.', 'external-files-in-media-library' ), esc_url( $this->get_config_url() ) ) );

			// add error.
			$this->add_error( $error );

			// do nothing more.
			return false;
		}

		// start a simple request to check if access token could be used.
		try {
			$client->getAccountInfo();
		} catch ( ClientException $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during check of DropBox access token:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );

			// create error.
			$error = new WP_Error();
			/* translators: %1$s will be replaced with a URL. */
			$error->add( 'efml_service_dropbox', sprintf( __( 'DropBox access token appears to be no longer valid. Please create a new one and <a href="%1$s">add it here</a>.', 'external-files-in-media-library' ), esc_url( $this->get_config_url() ) ) );

			// add error.
			$this->add_error( $error );

			// return false as login was not successfully.
			return false;
		}

		// return true as login is possible.
		return true;
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Show option to connect to DropBox on user profile.
	 *
	 * @return void
	 */
	public function add_user_settings(): void {
		// bail if settings are global.
		if ( $this->is_mode( 'global' ) ) {
			return;
		}

		// bail if customization for this user is not allowed.
		if ( ! ImportDialog::get_instance()->is_customization_allowed() ) {
			return;
		}

		?>
		<h3 id="efml-<?php echo esc_attr( $this->get_name() ); ?>"><?php echo esc_html__( 'DropBox', 'external-files-in-media-library' ); ?></h3>
		<div class="efml-user-settings">
		<?php

		// get the actual access token.
		$access_token = $this->get_access_token();

		// if no token is set, show hint.
		if ( empty( $access_token ) ) {
			?>
				<p><?php echo wp_kses_post( $this->get_help() ); ?></p>
			<?php
		} else {
			?>
			<a href="#" class="easy-dialog-for-wordpress button button-secondary" data-dialog="<?php echo esc_attr( Helper::get_json( $this->get_disconnect_dialog() ) ); ?>"><?php echo esc_html__( 'Disconnect', 'external-files-in-media-library' ); ?></a>
			<p><?php echo wp_kses_post( $this->get_connect_info() ); ?></p>
			<?php
		}
		?>
		</div>
		<?php
	}

	/**
	 * Return the dialog to connect DropBox, if it must be connected before it could be used in the setup.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<string,mixed>
	 */
	public function get_setup_connect_dialog(): array {
		// bail if OAuth is not possible on this website.
		if ( ! is_ssl() ) {
			return array();
		}

		// bail if DropBox is not connected via OAuth.
		if ( $this->is_mode( 'manually' ) ) {
			return array();
		}

		// bail if DropBox is already connected.
		if ( ! empty( $this->get_access_token() ) ) {
			return array();
		}

		// bail if the connection for the whole website must not be changed by the actual user.
		if ( $this->is_mode( 'global' ) && ! current_user_can( 'manage_options' ) ) {
			return array();
		}

		// return the dialog to connect.
		return $this->get_connect_dialog();
	}

	/**
	 * Return whether DropBox is already connected with this website via OAuth.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function is_connected_via_oauth(): bool {
		return ! $this->is_mode( 'manually' ) && ! empty( $this->get_access_token() );
	}

	/**
	 * Return the connect dialog.
	 *
	 * @return array<string,mixed>
	 */
	private function get_connect_dialog(): array {
		// show only a hint, if the API credentials are defined via constants.
		if ( $this->is_api_defined_via_constants() ) {
			return array(
				'className' => 'efml efml-dropbox-dialog',
				'title'     => __( 'Connect your DropBox', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p><strong>' . __( 'The API credentials for your DropBox app are defined as constants, e.g. in your wp-config.php.', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'Click on the button below to authorize the access to your DropBox.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'efml_dropbox_connect();',
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

		return array(
			'className' => 'efml efml-dropbox-dialog',
			'title'     => __( 'Connect your DropBox', 'external-files-in-media-library' ),
			'texts'     => array(
				/* translators: %1$s will be replaced by a URL. */
				'<p><strong>' . sprintf( __( 'Please fill our the form. Get your API credentials for your DropBox app <a href="%1$s" target="_blank">here%2$s</a>.', 'external-files-in-media-library' ), $this->get_token_url(), Helper::get_a11n_window_hint() ) . '</strong></p>',
				/* translators: %1$s will be replaced by a URL. */
				'<div><label for="efml_dropbox_api_key">' . esc_html__( 'App key', 'external-files-in-media-library' ) . '</label><input type="text" id="efml_dropbox_api_key" name="api_key" value="" placeholder="' . __( 'Enter your API key', 'external-files-in-media-library' ) . '"></div>',
				'<div><label for="efml_dropbox_api_secret">' . esc_html__( 'App secret', 'external-files-in-media-library' ) . '</label><input type="password" id="efml_dropbox_api_secret" name="api_secret" value="" placeholder="' . __( 'Enter your API secret', 'external-files-in-media-library' ) . '"></div>',
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_dropbox_connect();',
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
		return array(
			'className' => 'efml',
			'title'     => __( 'Disconnect DropBox', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Click on the button below to disconnect your DropBox from your website.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'Files you downloaded in the media library will still be there and usable.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_dropbox_disconnect();',
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
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// remove the global token.
		$this->delete_access_token();

		// get all users with a token and delete their tokens.
		$query = array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
				array(
					'key'     => 'efml_dropbox_api_key',
					'compare' => 'EXISTS',
				),
			),
		);
		$users = new WP_User_Query( $query );
		foreach ( $users->get_results() as $user ) {
			delete_user_meta( $user->ID, 'efml_dropbox_api_key' );
			delete_user_meta( $user->ID, 'efml_dropbox_api_secret' );
			delete_user_meta( $user->ID, 'efml_dropbox_access_tokens' );
		}
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		return __( 'Enter your DropBox access token', 'external-files-in-media-library' );
	}

	/**
	 * Return the help for the setup of this service.
	 *
	 * @return array{intro?: string, steps?: array<int,string>, hints?: array<int,string>, code?: array{label: string, content: string, hint?: string}, links?: array<int,array{label: string, url: string}>}
	 */
	public function get_setup_guide(): array {
		return array(
			'intro' => __( 'You do not need to enter access data for Dropbox: connect it via the button, sign in to Dropbox and allow the access. The connection is saved and renewed automatically.', 'external-files-in-media-library' ),
			'hints' => array(
				__( 'Use the Dropbox account, whose files you would like to use. To change the account, disconnect it in the settings and connect it again.', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced by a URL. */
				sprintf( __( 'To use your own Dropbox app instead, create it in the Dropbox App Console and define its app key and app secret as constants, see <a href="%1$s" target="_blank">the documentation</a>.', 'external-files-in-media-library' ), esc_url( Helper::get_documentation_url( 'constants' ) ) ),
			),
			'links' => array(
				array(
					'label' => __( 'Dropbox App Console', 'external-files-in-media-library' ),
					'url'   => 'https://www.dropbox.com/developers/apps',
				),
			),
		);
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		// get the token.
		$token = $this->get_access_token();

		// if access token is set in plugin settings.
		if ( $this->is_mode( 'global' ) ) {
			if ( ! empty( $token ) && ! current_user_can( 'manage_options' ) ) {
				return __( 'An access token has already been set by an administrator in the plugin settings. Just connect for show the files.', 'external-files-in-media-library' );
			}

			if ( empty( $token ) && ! current_user_can( 'manage_options' ) ) {
				return __( 'An access token must be set by an administrator in the plugin settings.', 'external-files-in-media-library' );
			}

			if ( empty( $token ) ) {
				/* translators: %1$s will be replaced by a URL. */
				return sprintf( __( 'Set your access token <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
			}

			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Your access token is already set <a href="%1$s">here</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		// if access token is set per user or individuell.
		if ( empty( $token ) ) {
			/* translators: %1$s will be replaced by a URL. */
			return sprintf( __( 'Configure your connection <a href="%1$s">in your profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() );
		}

		/* translators: %1$s will be replaced by a URL. */
		return sprintf( __( 'Your access token is already set <a href="%1$s">in your profile</a>. Just connect for show the files.', 'external-files-in-media-library' ), $this->get_config_url() );
	}

	/**
	 * Do not check for content type if a Dropbox-URL is given.
	 *
	 * Reason: a bug in Dropbox API regarding header requests, which results in "application/json" instead the correct
	 * content type.
	 *
	 * @param bool   $results The result.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function allow_wrong_content_type( bool $results, string $url ): bool {
		// bail if this is not a Dropbox URL.
		if ( ! $this->is_content_url( $url ) ) {
			return $results;
		}

		// do not check for content type.
		return false;
	}

	/**
	 * Return the export object for this service.
	 *
	 * Dropbox is only usable in development mode.
	 * Reason: bugs in Dropbox API regarding HTTP header requests.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		return Export::get_instance();
	}

	/**
	 * Return the real HTTP request headers for an DropBox content URL.
	 *
	 * Reason: Dropbox does return "application/json" for each HTTP header request.
	 *
	 * @param array<string,mixed>|WP_Error $response The response.
	 * @param Http                         $http_object The HTTP-object.
	 * @param string                       $url The requested URL.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_real_request_headers( array|WP_Error $response, Http $http_object, string $url ): array|WP_Error {
		// bail if URL is not a Dropbox content URL.
		if ( ! $this->is_content_url( $url ) ) {
			return $response;
		}

		// send a second request for all data to get the real HTTP answer.
		return wp_safe_remote_get( $url, $http_object->get_header_args() );
	}

	/**
	 * Cleanup after tree has been build for a subdirectory.
	 *
	 * @param array<string,mixed> $tree The tree.
	 * @param string              $directory The requested directory.
	 * @param string              $name The used service name.
	 *
	 * @return array<string,mixed>
	 */
	public function resort_for_subdirectories( array $tree, string $directory, string $name ): array {
		// bail if this is not our service.
		if ( $name !== $this->get_name() ) {
			return $tree;
		}

		// bail if no subdirectory was requested.
		if ( ! str_contains( $directory, '/' ) ) {
			return $tree;
		}

		// bail if requested directory is not in list.
		if ( empty( $tree['DropBox/']['dirs'][ $directory ] ) ) {
			return $tree;
		}

		// return only the subdirectory.
		return array( $directory => $tree['DropBox/']['dirs'][ $directory ] );
	}

	/**
	 * Convert a given shared link of Dropbox to the link of the file itself ("raw=1").
	 *
	 * Dropbox forwards this link to a temporary URL of the file content, which expires after 4 hours. Therefore,
	 * the link itself is used and not the target of its forwarding (as before 6.0.0).
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function convert_dropbox_urls( string $url ): string {
		// bail if URL is not a shared link of Dropbox.
		if ( ! $this->is_shared_link( $url ) ) {
			return $url;
		}

		// get the link to the file itself.
		$raw_url = $this->get_raw_shared_link( $url );

		// bail if nothing has been changed.
		if ( $raw_url === $url ) {
			return $url;
		}

		// log this event.
		/* translators: %1$s and %2$s will be replaced by URLs. */
		Log::get_instance()->create( sprintf( __( 'Converting %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . esc_html( $url ) . '</em>', '<em>' . esc_html( $raw_url ) . '</em>' ), $raw_url, 'info', 2 );

		// return the link to the file itself.
		return $raw_url;
	}

	/**
	 * Return whether the given URL is a shared link of Dropbox (e.g. "https://www.dropbox.com/scl/fi/…").
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	public function is_shared_link( string $url ): bool {
		// bail if the URL is not on the domain of Dropbox.
		if ( ! Helper::is_url_on_domain( $url, 'dropbox.com' ) ) {
			return false;
		}

		// get the path of the URL.
		$path = wp_parse_url( $url, PHP_URL_PATH );

		// return whether the path is the path of a shared link.
		return is_string( $path ) && 1 === preg_match( '#^/(s|sh|scl)/#i', $path );
	}

	/**
	 * Return the link to the file itself for the given shared link of Dropbox.
	 *
	 * It uses "raw=1" instead of "dl=0" or "dl=1", so Dropbox delivers the file itself (and not a page about it).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url The shared link.
	 *
	 * @return string
	 */
	public function get_raw_shared_link( string $url ): string {
		return add_query_arg( array( 'raw' => 1 ), remove_query_arg( 'dl', $url ) );
	}

	/**
	 * Return whether the given URL delivers the content of a file in Dropbox: a shared link or the temporary URL
	 * it forwards to.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	private function is_content_url( string $url ): bool {
		return Helper::is_url_on_domain( $url, 'dropboxusercontent.com' ) || $this->is_shared_link( $url );
	}

	/**
	 * Allow the forwarding of shared links of Dropbox to the content of the file.
	 *
	 * The HTTP requests of this plugin do not follow forwardings. Shared links of Dropbox always forward to a
	 * temporary URL of the file content, so they could not be loaded without it (e.g. by the proxy).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param array<string,mixed> $parsed_args The arguments of the request.
	 * @param string              $url         The requested URL.
	 *
	 * @return array<string,mixed>
	 */
	public function follow_forwarding_of_shared_links( array $parsed_args, string $url ): array {
		// bail if this is not a shared link of Dropbox.
		if ( ! $this->is_shared_link( $url ) ) {
			return $parsed_args;
		}

		// bail if forwardings are already allowed.
		if ( ! empty( $parsed_args['redirection'] ) ) {
			return $parsed_args;
		}

		// allow the forwardings.
		$parsed_args['redirection'] = 3;

		// return the resulting arguments.
		return $parsed_args;
	}

	/**
	 * Load an exported file via the Dropbox API, if its saved URL is a temporary URL of Dropbox.
	 *
	 * Before 6.0.0 the export saved the temporary URL of the file content, which expires after 4 hours. The API
	 * loads the file via its path in Dropbox, which is saved during the export.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string|null $tmp_file The path of the temporary file, null to download it via HTTP.
	 * @param string      $url      The requested URL.
	 *
	 * @return string|null
	 */
	public function load_exported_file_via_api( string|null $tmp_file, string $url ): string|null {
		// bail if another temporary file is already set.
		if ( is_string( $tmp_file ) && '' !== $tmp_file ) {
			return $tmp_file;
		}

		// bail if this is not a temporary URL of Dropbox.
		if ( ! Helper::is_url_on_domain( $url, 'dropboxusercontent.com' ) ) {
			return $tmp_file;
		}

		// get the external file with this URL.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );

		// bail if no file uses this URL.
		if ( ! $external_file_obj instanceof File ) {
			return $tmp_file;
		}

		// get the path of the file in Dropbox.
		$path = get_post_meta( $external_file_obj->get_id(), 'efml_dropbox_path', true );

		// bail if it is unknown (e.g. for imported files).
		if ( empty( $path ) || ! is_string( $path ) ) {
			return $tmp_file;
		}

		// bail if no client is available (e.g. if the owner of its external source has removed the connection).
		if ( ! $this->get_client_for_user( $this->get_owner_of_file( $external_file_obj->get_id() ) ) instanceof Client ) {
			return $tmp_file;
		}

		// load the file via the API (with the connection of the owner of its external source).
		$protocol_obj = new Protocol( $this->get_name() . $path );
		$protocol_obj->set_fields( $external_file_obj->get_fields() );
		$protocol_obj->set_external_file( $external_file_obj );
		try {
			$file = $protocol_obj->get_temp_file( $this->get_name() . $path, Helper::get_wp_filesystem() );
		} catch ( Throwable $e ) {
			// log this event.
			Log::get_instance()->create( __( 'The exported file could not be loaded via the Dropbox API:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $url, 'error' );

			// use the HTTP download.
			return $tmp_file;
		}

		// bail if the file could not be loaded.
		if ( ! is_string( $file ) || '' === $file ) {
			return $tmp_file;
		}

		// log this event.
		Log::get_instance()->create( __( 'The exported file has been loaded via the Dropbox API, as its saved URL is a temporary URL of Dropbox.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// return the temporary file.
		return $file;
	}

	/**
	 * Return the URL where user find their access token.
	 *
	 * @return string
	 */
	private function get_token_url(): string {
		$url = 'https://www.dropbox.com/developers/apps';

		/**
		 * Filter the URL where Dropbox user will find their access token.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 */
		return apply_filters( 'efml_dropbox_access_token_url', $url );
	}

	/**
	 * Return the config URL.
	 *
	 * @return string
	 */
	protected function get_config_url(): string {
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
	 * Return the real return URL where the user will land after successfully connection via OAuth.
	 *
	 * @return string
	 */
	private function get_real_redirect_uri(): string {
		// set the token.
		$real_redirect_uri = trailingslashit( get_home_url() ) . $this->get_oauth_slug() . '/';

		// if no permalink structure is set, generate a parameterized URL.
		if ( empty( get_option( 'permalink_structure', '' ) ) ) {
			// return link for simple permalinks.
			$real_redirect_uri = trailingslashit( get_home_url() ) . '?' . $this->get_oauth_slug() . '=1';
		}

		/**
		 * Filter the real redirect URI to connect the Google OAuth Client.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $real_redirect_uri The real redirect URI.
		 */
		return apply_filters( 'efmlgd_dropbox_real_redirect_uri', $real_redirect_uri );
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
	 * Return the OAuth slug for the real return URL.
	 *
	 * @return string
	 */
	private function get_oauth_slug(): string {
		return 'efml-' . $this->get_name() . '-oauth';
	}

	/**
	 * Return the DropBox OAuth URL for step 1.
	 *
	 * @return string
	 */
	private function get_oauth_url(): string {
		$url = 'https://api.dropbox.com/oauth2/token';

		/**
		 * Filter the DropBox OAuth URL.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 */
		return apply_filters( 'efml_dropbox_oauth_url', $url );
	}

	/**
	 * Check for requested OAuth return URL.
	 *
	 * @param string $template The requested template.
	 *
	 * @return string
	 */
	public function check_for_oauth_return_url( string $template ): string {
		// bail if the slug is unused.
		if ( empty( get_query_var( $this->get_oauth_slug() ) ) ) {
			return $template;
		}

		// get the code.
		$code = filter_input( INPUT_GET, 'code', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no code is set.
		if ( empty( $code ) ) {
			return Helper::get_404_template();
		}

		// the connection for the whole website must only be changed by users who are allowed to manage the settings.
		if ( $this->is_mode( 'global' ) && ! current_user_can( 'manage_options' ) ) {
			return Helper::get_404_template();
		}

		// get the state from the request and the state we saved for the actual user as we started the connection.
		$state          = filter_input( INPUT_GET, 'state', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$expected_state = get_user_meta( get_current_user_id(), 'efml_dropbox_oauth_state', true );

		// bail if the state does not match: this request has not been started by this user.
		if ( ! is_string( $state ) || ! is_string( $expected_state ) || '' === $expected_state || ! hash_equals( $expected_state, $state ) ) {
			// log this event.
			Log::get_instance()->create( __( 'The OAuth-response from DropBox does not match the request of this user and has been ignored.', 'external-files-in-media-library' ), '', 'error' );

			// return 404-page.
			return Helper::get_404_template();
		}

		// the state must only be usable once.
		delete_user_meta( get_current_user_id(), 'efml_dropbox_oauth_state' );

		// create the query for the next request.
		$query = array(
			'body' => array(
				'code'          => $code,
				'grant_type'    => 'authorization_code',
				'client_id'     => $this->get_api_key(),
				'client_secret' => $this->get_api_secret(),
				'redirect_uri'  => $this->get_real_redirect_uri(),
			),
		);

		// request the credentials.
		$response = wp_remote_post( $this->get_oauth_url(), $query );

		// bail if response results in failure.
		if ( is_wp_error( $response ) ) {
			// log this event.
			Log::get_instance()->create( __( 'OAuth-request to DropBox results in the following error:', 'external-files-in-media-library' ) . ' <code>' . Helper::get_json( $response ) . '</code>', '', 'error' );

			// return 404-page.
			return Helper::get_404_template();
		}

		// get the response data.
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// bail if data is empty.
		if ( empty( $data ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got empty data from DropBox OAuth response.', 'external-files-in-media-library' ), '', 'error' );

			// return 404-page.
			return Helper::get_404_template();
		}

		// bail if the response contains an "error"-entry.
		if ( ! empty( $data['error'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got error from DropBox OAuth response:', 'external-files-in-media-library' ) . ' <code>' . Helper::get_json( $data ) . '</code>', '', 'error' );

			// return 404-page.
			return Helper::get_404_template();
		}

		// update the expires time.
		$data['expires'] = time() + ( isset( $data['expires_in'] ) ? absint( $data['expires_in'] ) : 0 );

		// save the response data.
		$this->set_access_token( $data );

		// forward user.
		wp_safe_redirect( $this->get_oauth_return_url() );
		exit;
	}

	/**
	 * Return the dialog to show if the actual user is not allowed to change the DropBox connection.
	 *
	 * @return array<string,mixed>
	 */
	private function get_not_allowed_dialog(): array {
		return array(
			'className' => 'efml',
			'title'     => __( 'Error', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p>' . __( 'The DropBox connection of this website can only be changed by an administrator in the plugin settings.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Return the Client object for DropBox connections with actual access token.
	 *
	 * @return Client|false
	 */
	public function get_client(): Client|false {
		/**
		 * Filter the DropBox client to use, e.g. for tests. Return a client to use it instead of the default one.
		 *
		 * @since 5.5.2 Available since 5.5.2.
		 * @param Client|null $client The client.
		 */
		$client = apply_filters( 'efml_dropbox_client', null );
		if ( $client instanceof Client ) {
			return $client;
		}

		// get the access token data.
		$data = $this->get_access_token();

		// bail if data is empty or access token is missing.
		if ( empty( $data ) || empty( $data['access_token'] ) ) {
			return false;
		}

		// get the client with the given token.
		return new Client( $data['access_token'] );
	}

	/**
	 * Return the OAuth URL for step 2.
	 *
	 * @return string
	 */
	private function get_oauth_url_step_2(): string {
		$url = 'https://www.dropbox.com/oauth2/authorize?';

		/**
		 * Filter the DropBox OAuth URL for step 2
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The URL.
		 */
		return apply_filters( 'efml_dropbox_oauth_url_step_2', $url );
	}

	/**
	 * Refresh the token.
	 *
	 * @param array<string,mixed> $data The token data.
	 *
	 * @return array<string,mixed>
	 */
	private function refresh_token( array $data ): array {
		// create the query for the next request.
		$query = array(
			'body' => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $data['refresh_token'],
				'client_id'     => $this->get_api_key(),
				'client_secret' => $this->get_api_secret(),
			),
		);

		// request the credentials.
		$response = wp_remote_post( $this->get_oauth_url(), $query );

		// bail on error.
		if ( is_wp_error( $response ) ) {
			return array();
		}

		// get the new data.
		$new_data = json_decode( wp_remote_retrieve_body( $response ), true );

		// bail if data is empty.
		if ( empty( $new_data ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got empty data from DropBox OAuth response to refresh the token.', 'external-files-in-media-library' ), '', 'error' );

			// return 404-page.
			return array();
		}

		// bail if the response contains an "error"-entry.
		if ( ! empty( $new_data['error'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Got error from DropBox OAuth response to refresh the token', 'external-files-in-media-library' ) . ' <code>' . Helper::get_json( $new_data ) . '</code>', '', 'error' );

			// return 404-page.
			return array();
		}

		// get the updated data.
		$data['access_token'] = $new_data['access_token'];
		$data['expires']      = time() + $new_data['expires_in'];

		// save the updated access token data.
		$this->set_access_token( $data );

		// and return them.
		return $data;
	}

	/**
	 * Return whether this service is using credentials by checking its field configuration.
	 *
	 * @return bool
	 */
	protected function has_credentials(): bool {
		return true;
	}

	/**
	 * Return the definitions of the values, which could be defined via constants.
	 *
	 * DropBox is using OAuth, so only the API credentials of the app could be defined via constants.
	 * The tokens are saved in the database, as they will be refreshed regularly.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_constant_field_definitions(): array {
		// bail if this service does not support constants.
		if ( ! $this->is_fields_via_constants_enabled() ) {
			return array();
		}

		// return the definitions.
		return array(
			'api_key'    => array(
				'name'       => 'api_key',
				'type'       => 'text',
				'label'      => __( 'App key', 'external-files-in-media-library' ),
				'credential' => true,
			),
			'api_secret' => array(
				'name'       => 'api_secret',
				'type'       => 'password',
				'label'      => __( 'App secret', 'external-files-in-media-library' ),
				'credential' => true,
			),
		);
	}

	/**
	 * Return whether the API credentials are defined via constants.
	 *
	 * @return bool
	 */
	private function is_api_defined_via_constants(): bool {
		return ! empty( $this->get_constant_values() );
	}

	/**
	 * Return whether this service supports the complete connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function supports_connection_test(): bool {
		return true;
	}

	/**
	 * Return the client for the connection test.
	 *
	 * @return Client
	 * @throws Exception If no client could be loaded (e.g. without access token).
	 */
	private function get_connection_test_client(): Client {
		// get the client.
		$client = $this->get_client();

		// bail if no client could be loaded.
		if ( ! $client instanceof Client ) {
			throw new Exception( esc_html__( 'Your website is not connected with DropBox. Connect it in the settings first.', 'external-files-in-media-library' ) );
		}

		// return the client.
		return $client;
	}

	/**
	 * Return the path of the directory for the connection test in DropBox.
	 *
	 * @return string
	 */
	private function get_connection_test_path(): string {
		// get the directory and remove the mark of this service like the export does it (e.g. "DropBox/Photos/" results in "/Photos/").
		$directory = (string) preg_replace( '#^DropBox#i', '', $this->get_connection_test_directory() );

		// return the path with leading and trailing slash.
		return '/' . trim( $directory, '/' ) . ( '' !== trim( $directory, '/' ) ? '/' : '' );
	}

	/**
	 * List the directory in DropBox for the connection test.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 * @throws Exception If the step failed.
	 */
	protected function test_connection_list(): string {
		// get the client.
		$client = $this->get_connection_test_client();

		// list the directory.
		try {
			$client->listFolder( '/' === $this->get_connection_test_path() ? '' : $this->get_connection_test_path() );
		} catch ( \Exception $e ) {
			Log::get_instance()->create( __( 'The directory could not be read in DropBox. Error:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $this->get_directory(), 'error' );
			throw new Exception( esc_html__( 'The directory could not be read in DropBox. Check the log for details.', 'external-files-in-media-library' ) );
		}

		// return the message.
		return __( 'The directory could be read.', 'external-files-in-media-library' );
	}

	/**
	 * Upload the test file to DropBox.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $local_file The absolute path to the local test file.
	 * @param string $file_name  The name of the test file.
	 *
	 * @return string
	 * @throws Exception If the step failed.
	 */
	protected function test_connection_upload( string $local_file, string $file_name ): string {
		// get the client.
		$client = $this->get_connection_test_client();

		// upload the file.
		try {
			$client->upload( $this->get_connection_test_path() . $file_name, (string) Helper::get_wp_filesystem()->get_contents( $local_file ) );
		} catch ( \Exception $e ) {
			Log::get_instance()->create( __( 'The test file could not be uploaded to DropBox. Error:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $this->get_directory(), 'error' );
			throw new Exception( esc_html__( 'The test file could not be uploaded to DropBox. Check the permissions of the app in DropBox and the log for details.', 'external-files-in-media-library' ) );
		}

		// return the path of the file like the export does it.
		return 'DropBox' . $this->get_connection_test_path() . $file_name;
	}

	/**
	 * Check whether a public link could be created for the test file, like the export does it for public files.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url The URL of the uploaded file.
	 *
	 * @return bool|null
	 */
	protected function test_connection_public( string $url ): ?bool {
		try {
			$shared_file_data = $this->get_connection_test_client()->createSharedLinkWithSettings( (string) preg_replace( '#^DropBox#i', '', $url ) );
		} catch ( \Exception $e ) {
			Log::get_instance()->create( __( 'No public link could be created for the test file in DropBox. Error:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $this->get_directory(), 'error' );
			return false;
		}

		// return whether a link has been created.
		return ! empty( $shared_file_data['url'] );
	}

	/**
	 * Delete the test file in DropBox.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $url       The URL of the uploaded file.
	 * @param string $file_name The name of the test file.
	 *
	 * @return void
	 * @throws Exception If the step failed.
	 */
	protected function test_connection_delete( string $url, string $file_name ): void {
		try {
			$this->get_connection_test_client()->delete( $this->get_connection_test_path() . $file_name );
		} catch ( \Exception $e ) {
			Log::get_instance()->create( __( 'The test file could not be deleted in DropBox. Error:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $this->get_directory(), 'error' );
			throw new Exception( esc_html__( 'The test file could not be deleted in DropBox.', 'external-files-in-media-library' ) );
		}
	}

	/**
	 * Return further search terms of this service, separated by commas.
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return _x( 'Dropbox, cloud', 'search terms for the Dropbox service, separated by commas', 'external-files-in-media-library' );
	}

	/**
	 * Add the path of an exported file for the Dropbox API as first URL for its restore.
	 *
	 * The public URL of an exported file is the target of its shared link, which could expire. The API
	 * downloads the file via its path, which is saved during the export.
	 *
	 * @param array<int,string> $urls              List of URLs.
	 * @param File              $external_file_obj The external file object.
	 *
	 * @return array<int,string>
	 */
	public function add_restore_download_url( array $urls, File $external_file_obj ): array {
		// bail if this is not a file of this service.
		if ( $this->get_name() !== get_post_meta( $external_file_obj->get_id(), 'eml_service', true ) ) {
			return $urls;
		}

		// get the path of the file in Dropbox.
		$path = get_post_meta( $external_file_obj->get_id(), 'efml_dropbox_path', true );

		// bail if it is unknown.
		if ( empty( $path ) || ! is_string( $path ) ) {
			return $urls;
		}

		// add it as first URL.
		array_unshift( $urls, $this->get_name() . $path );

		// return the list.
		return array_values( array_unique( $urls ) );
	}
}
