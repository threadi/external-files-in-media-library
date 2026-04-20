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
use easySettingsForWordPress\Fields\Button;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\ImportDialog;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\DropBox\Export;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;
use WP_Error;
use WP_Image_Editor;
use WP_User;
use WP_User_Query;
use WpOrg\Requests\Utility\CaseInsensitiveDictionary;

/**
 * Object to handle support for this platform.
 */
class DropBox extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'dropbox';

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

		// bail if user has no capability for this service.
		if ( ! defined( 'EFML_SYNC_RUNNING' ) && ! Helper::is_cli() && ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
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
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_http_check_content_type', array( $this, 'allow_wrong_content_type' ), 10, 2 );
		add_filter( 'efml_files_check_content_type', array( $this, 'allow_wrong_content_type' ), 10, 2 );
		add_filter( 'efml_http_header_response', array( $this, 'get_real_request_headers' ), 10, 3 );
		add_filter( 'efml_directory_listing', array( $this, 'resort_for_subdirectories' ), 10, 3 );
		add_filter( 'efml_import_url', array( $this, 'convert_dropbox_urls' ) );
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
				$field = new TextInfo( $settings_obj );
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_description( $this->get_help() );
			} else {
				// create the dialog.
				$dialog = $this->get_disconnect_dialog();

				$field = new Button( $settings_obj );
				$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Disconnect', 'external-files-in-media-library' ) );
				$field->set_description( $this->get_connect_info() );
				$field->add_class( 'easy-dialog-for-wordpress' );
				$field->set_custom_attributes( array( 'data-dialog' => Helper::get_json( $dialog ) ) );
			}
			$setting->set_field( $field );
		}

		if ( $this->is_mode( 'user' ) ) {
			$setting = $settings_obj->add_setting( 'eml_dropbox_credential_location_hint' );
			$setting->set_section( $section );
			$setting->set_show_in_rest( false );
			$setting->prevent_export( true );
			$field = new TextInfo( $settings_obj );
			$field->set_title( __( 'Hint', 'external-files-in-media-library' ) );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Each user will find its settings in his own <a href="%1$s">user profile</a>.', 'external-files-in-media-library' ), $this->get_config_url() ) );
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
			$url = \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
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
		$subtab = filter_input( INPUT_GET, 'subtab', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'eml_settings' === $page && 'global' === $this->get_mode() && $this->get_settings_subtab_slug() === $subtab ) {
			$use_it = true;
		}
		if ( 'profile.php' === $pagenow ) {
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

		// get the API key.
		$api_key = filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get the API secret.
		$api_secret = filter_input( INPUT_POST, 'api_secret', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

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

		// save the values.
		$this->set_api_key( $api_key );
		$this->set_api_secret( $api_secret );

		// run connection.
		$params = array(
			'client_id'         => $api_key,
			'response_type'     => 'code',
			'redirect_uri'      => $this->get_real_redirect_uri(),
			'token_access_type' => 'offline',
		);

		// redirect the user to dropbox for authorization.
		wp_send_json_success( array( 'url' => $this->get_oauth_url_step_2() . http_build_query( $params ) ) );
	}

	/**
	 * Return access token data.
	 *
	 * @return array<string,mixed>
	 */
	public function get_access_token(): array {
		// get it global, if this is enabled.
		if ( $this->is_mode( 'global' ) ) {
			$data = get_option( 'efml_dropbox_access_tokens', array() );
		} else {
			// get current user.
			$user = $this->get_user();

			// bail if user is not available.
			if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
				return array();
			}

			// get and return the value.
			$data = Crypt::get_instance()->decrypt( get_user_meta( $user->ID, 'efml_dropbox_access_tokens', true ) );
		}

		// convert JSON to array.
		if ( ! is_array( $data ) ) {
			$data = json_decode( $data, true );
			if ( ! is_array( $data ) ) {
				return array();
			}
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
			update_option( 'efml_dropbox_access_tokens', $data );
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
		Log::get_instance()->create( sprintf( __( 'New DropBox API key saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . ( $user instanceof WP_User ? $user->display_name : '' ) . '</em>' ), '', 'info', 2 );

		// save the token.
		update_user_meta( $user_id, 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $data ) ) );
	}

	/**
	 * Return the used API key.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return string
	 */
	public function get_api_key( int $user_id = 0 ): string {
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
		return Crypt::get_instance()->decrypt( (string) get_user_meta( $user_id, 'efml_dropbox_api_key', true ) );
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
		update_user_meta( $user_id, 'efml_dropbox_api_key', Crypt::get_instance()->encrypt( $key ) );
	}

	/**
	 * Return the used API key.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return string
	 */
	public function get_api_secret( int $user_id = 0 ): string {
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
		return Crypt::get_instance()->decrypt( (string) get_user_meta( $user_id, 'efml_dropbox_api_secret', true ) );
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
		update_user_meta( $user_id, 'efml_dropbox_api_secret', Crypt::get_instance()->encrypt( $secret ) );
	}

	/**
	 * Delete the access token.
	 *
	 * @return bool
	 */
	public function delete_access_token(): bool {
		// delete it global.
		update_option( 'efml_dropbox_access_tokens', '' );

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
			exit; // @phpstan-ignore deadCode.unreachable
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
	 * @return string
	 */
	private function get_help(): string {
		$help = esc_html__( 'Follow these steps:', 'external-files-in-media-library' ) . '</p><ol>';
		/* translators: %1$s will be replaced by a URL. */
		$help .= '<li>' . sprintf( __( 'Create your own app <a href="$1%s" target="_blank">here</a>.', 'external-files-in-media-library' ), $this->get_token_url() ) . '</li>';
		$help .= '<li>' . esc_html__( 'Enter the following as OAuth2 Redirect URL for this app:', 'external-files-in-media-library' ) . ' <code>' . $this->get_real_redirect_uri() . '</code></li>';
		$help .= '<li>' . esc_html__( 'Click on the following button.', 'external-files-in-media-library' ) . '</li>';
		$help .= '</ol><p><a href="#" class="easy-dialog-for-wordpress button button-secondary" data-dialog="' . esc_attr( Helper::get_json( $this->get_connect_dialog() ) ) . '">' . esc_html__( 'Connect now', 'external-files-in-media-library' ) . '</a>';
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
		} catch ( ClientException $e ) {
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
					'action' => 'location.href="https://www.dropbox.com";',
					'label'  => __( 'Go to DropBox', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'location.href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '";',
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
	 * Return the connect dialog.
	 *
	 * @return array<string,mixed>
	 */
	private function get_connect_dialog(): array {
		return array(
			'className' => 'efml efml-dropbox-dialog',
			'title'     => __( 'Connect your DropBox', 'external-files-in-media-library' ),
			'texts'     => array(
				/* translators: %1$s will be replaced by a URL. */
				'<p><strong>' . sprintf( __( 'Please fill our the form. Get your API credentials for your DropBox app <a href="%1$s" target="_blank">here (opens in a new window)</a>.', 'external-files-in-media-library' ), $this->get_token_url() ) . '</strong></p>',
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
			'meta_query' => array(
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
		if ( ! str_contains( $url, 'dropboxusercontent.com' ) ) {
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
		if ( ! str_contains( $url, 'dropboxusercontent.com' ) ) {
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
	 * Convert a given Dropbox URL to its download URL.
	 *
	 * @param string $url The given URL.
	 *
	 * @return string
	 */
	public function convert_dropbox_urls( string $url ): string {
		// bail if URL is not a Dropbox URL.
		if ( ! str_starts_with( $url, 'https://www.dropbox.com/' ) ) {
			return $url;
		}

		// log this event.
		Log::get_instance()->create( __( 'Start check for converting the DropBox URL.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// create the direct download URL.
		$dropbox_url = add_query_arg(
			array(
				'raw' => 1,
			),
			$url
		);

		// send header request to get the forwarding URL for direct access.
		$response = wp_get_http_headers( $dropbox_url );

		// bail if result is false.
		if ( ! $response instanceof CaseInsensitiveDictionary ) {
			// log this event.
			Log::get_instance()->create( __( 'Request to DropBox URL results in error.', 'external-files-in-media-library' ), $url, 'error' );

			// return the given URL.
			return $url;
		}

		// get the response data.
		$response_array = $response->getAll();

		// bail if no location is set.
		if ( empty( $response_array['location'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'No forward URL returned from DropBox.', 'external-files-in-media-library' ), $url, 'error' );

			// return the given URL.
			return $url;
		}

		// log this event.
		/* translators: %1$s and %2$s will be replaced by URLs. */
		Log::get_instance()->create( sprintf( __( 'Converting %1$s to %2$s.', 'external-files-in-media-library' ), '<em>' . $url . '</em>', '<em>' . $response_array['location'] . '</em>' ), $response_array['location'], 'info', 2 );

		// return the forwarding URL to use.
		return $response_array['location'];
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
			return \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() );
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
		$real_redirect_uri = get_option( 'siteurl' ) . '/' . $this->get_oauth_slug() . '/';

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
		$data['expires'] = time() + $data['expires_in'];

		// save the response data.
		$this->set_access_token( $data );

		// forward user.
		wp_safe_redirect( $this->get_config_url() );
		exit;
	}

	/**
	 * Return the Client object for DropBox connections with actual access token.
	 *
	 * @return Client|false
	 */
	public function get_client(): Client|false {
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
}
