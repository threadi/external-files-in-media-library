<?php
/**
 * File to handle support for the DropBox platform.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Init;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Button;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use GuzzleHttp\Exception\ClientException;
use Spatie\Dropbox\Client;
use WP_Error;
use WP_Image_Editor;
use WP_User;

/**
 * Object to handle support for this platform.
 */
class DropBox extends Directory_Listing_Base implements Service {
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
	private string $settings_tab = 'services';

	/**
	 * Slug of settings tab.
	 *
	 * @var string
	 */
	private string $settings_sub_tab = 'eml_dropbox';

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
		$this->title = __( 'Choose file(s) from your DropBox', 'external-files-in-media-library' );

		// use hooks.
		add_action( 'init', array( $this, 'init_drop_box' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_js_admin' ) );
		add_action( 'wp_ajax_efml_add_access_token', array( $this, 'add_access_token' ), 10, 0 );
		add_action( 'wp_ajax_efml_remove_access_token', array( $this, 'remove_access_token' ), 10, 0 );

		// use our own hooks.
		add_filter( 'eml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
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
	 * Add settings for DropBox support.
	 *
	 * @return void
	 */
	public function init_drop_box(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

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

		// add new tab for settings.
		$tab = $services_tab->add_tab( $this->get_settings_subtab_slug(), 90 );
		$tab->set_title( __( 'DropBox', 'external-files-in-media-library' ) );
		$tab->set_hide_save( true );

		// add section for file statistics.
		$section = $tab->add_section( 'section_dropbox_main', 10 );
		$section->set_title( __( 'Settings for DropBox', 'external-files-in-media-library' ) );

		// add invisible setting for access token.
		$setting = $settings_obj->add_setting( 'eml_dropbox_access_tokens' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( array() );
		$setting->prevent_export( true );
		$setting->set_show_in_rest( false );
		$setting->set_save_callback( array( $this, 'preserve_tokens_value' ) );

		// add setting for button to connect.
		$setting = $settings_obj->add_setting( 'eml_dropbox_connector' );
		$setting->set_section( $section );
		$setting->set_autoload( false );
		$setting->prevent_export( true );

		// get the access token of the actual user.
		$access_token = $this->get_access_token();

		// show connect button if no token is set.
		if ( empty( $access_token ) ) {
			// create dialog.
			$dialog = array(
				'title'   => __( 'Connect DropBox', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Please enter your access token below:', 'external-files-in-media-library' ) . '</strong></p>',
					'<div><label for="efml_dropbox_access_token">' . esc_html__( 'Access Token', 'external-files-in-media-library' ) . '</label><input type="text" id="efml_dropbox_access_token" name="access_token" value=""></div>',
				),
				'buttons' => array(
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

			$field = new Button();
			$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Connect now', 'external-files-in-media-library' ) );
			$field->set_description( $this->get_help() );
		} else {
			// create dialog.
			$dialog = array(
				'title'   => __( 'Disconnect DropBox', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Click on the button below to disconnect your DropBox from your website.', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'Files you downloaded in the media library will still be there and usable.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
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

			$field = new Button();
			$field->set_title( __( 'API connection', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Disconnect', 'external-files-in-media-library' ) );
			$field->set_description( $this->get_connect_info() );
		}
		$field->add_class( 'easy-dialog-for-wordpress' );
		$field->set_custom_attributes( array( 'data-dialog' => wp_json_encode( $dialog ) ) );
		$setting->set_field( $field );
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
	 * Set a pseudo-directory to force the directory listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		return 'DropBox';
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
		return '<a class="connect button button-secondary" href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '">' . __( 'Connect', 'external-files-in-media-library' ) . '</a>';
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
	 * Add our dropbox JS.
	 *
	 * @return void
	 */
	public function add_js_admin(): void {
		// backend-JS.
		wp_enqueue_script(
			'eml-admin-dropbox',
			plugins_url( '/admin/dropbox.js', EFML_PLUGIN ),
			array( 'jquery' ),
			(string) filemtime( Helper::get_plugin_dir() . '/admin/dropbox.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-admin-dropbox',
			'efmlJsVarsDropBox',
			array(
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'access_token_connect_nonce'    => wp_create_nonce( 'efml-dropbox-save-access-token' ),
				'access_token_disconnect_nonce' => wp_create_nonce( 'efml-dropbox-remove-access-token' ),
			)
		);
	}

	/**
	 * Add access token via AJAX request.
	 *
	 * @return void
	 */
	public function add_access_token(): void {
		// check nonce.
		check_ajax_referer( 'efml-dropbox-save-access-token', 'nonce' );

		// get the token.
		$access_token = filter_input( INPUT_POST, 'access_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if token is empty.
		if ( empty( $access_token ) ) {
			// create dialog.
			$dialog = array(
				'title'   => __( 'Error', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p>' . __( 'Please enter an access token!', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
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

		// add the token.
		$this->set_access_token( $access_token );

		// create dialog.
		$dialog = array(
			'title'   => __( 'DropBox access token saved', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'You will now be able to use DropBox as your external source.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
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
	 * Return access token for actual WordPress user.
	 *
	 * @return string
	 */
	public function get_access_token(): string {
		// get all access tokens.
		$access_tokens = get_option( 'eml_dropbox_access_tokens', array() );

		// bail if no token are set.
		if ( empty( $access_tokens ) ) {
			return '';
		}

		// get current user.
		$user = wp_get_current_user();

		// bail if user is not available.
		if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
			return '';
		}

		// bail if no token for actual user is set.
		if ( empty( $access_tokens[ $user->ID ] ) ) {
			return '';
		}

		// return the access token for this user.
		return $access_tokens[ $user->ID ];
	}

	/**
	 * Set the access token for the actual WordPress user.
	 *
	 * @param string $access_token The access token.
	 * @param int    $user_id The user id (optional).
	 *
	 * @return void
	 */
	public function set_access_token( string $access_token, int $user_id = 0 ): void {
		// get actual access token list.
		$access_tokens = get_option( 'eml_dropbox_access_tokens', array() );

		// if list is not an array, create one.
		if ( ! is_array( $access_tokens ) ) {
			$access_tokens = array();
		}

		// get the user_id from session if it is not set.
		if ( 0 === $user_id ) {
			// get the user.
			$user = wp_get_current_user();
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

		// add this token.
		$access_tokens[ $user_id ] = $access_token;

		// log event.
		/* translators: %1$s will be replaced by the username. */
		Log::get_instance()->create( sprintf( __( 'New DropBox access token saved for user %1$s.', 'external-files-in-media-library' ), '<em>' . $user->display_name . '</em>' ), '', 'info', 2 );

		// save the updated token list.
		update_option( 'eml_dropbox_access_tokens', $access_tokens );
	}

	/**
	 * Delete the access token for the actual WordPress user.
	 *
	 * @return bool
	 */
	public function delete_access_token(): bool {
		// get actual access token list.
		$access_tokens = get_option( 'eml_dropbox_access_tokens' );

		// get current user.
		$user = wp_get_current_user();

		// bail if user is not available.
		if ( ! $user instanceof WP_User ) { // @phpstan-ignore instanceof.alwaysTrue
			return false;
		}

		// bail if user does not have a token.
		if ( empty( $access_tokens[ $user->ID ] ) ) {
			return false;
		}

		// remove the token.
		unset( $access_tokens[ $user->ID ] );

		// save the updated token list.
		update_option( 'eml_dropbox_access_tokens', $access_tokens );

		// return true as token has been removed.
		return true;
	}

	/**
	 * Remove access token via AJAX request.
	 *
	 * @return void
	 */
	public function remove_access_token(): void {
		// check nonce.
		check_ajax_referer( 'efml-dropbox-remove-access-token', 'nonce' );

		// remove the token.
		if ( ! $this->delete_access_token() ) {
			// create dialog.
			$dialog = array(
				'title'   => __( 'Error', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p>' . __( 'An error occurred during removing the access token!', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
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

		// create dialog.
		$dialog = array(
			'title'   => __( 'DropBox access token removed', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'You will now not be able to use DropBox in your WordPress backend.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
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
	 * Create the help to create app and get access token.
	 *
	 * @return string
	 */
	private function get_help(): string {
		$help = esc_html__( 'Follow these steps:', 'external-files-in-media-library' ) . '</p><ol>';
		/* translators: %1$s will be replaced by a URL. */
		$help .= '<li>' . sprintf( __( 'Create your own app <a href="$1%s" target="_blank">here</a>. ', 'external-files-in-media-library' ), 'https://www.dropbox.com/developers/apps' ) . '</li>';
		$help .= '<li>' . esc_html__( 'Enter the following as OAuth2 Redirect URL:', 'external-files-in-media-library' ) . ' <code>' . get_option( 'home' ) . '</code></li>';
		$help .= '<li>' . esc_html__( 'Copy the access token.', 'external-files-in-media-library' ) . '</li>';
		$help .= '<li>' . esc_html__( 'Click on the button "Connect" above here.', 'external-files-in-media-library' ) . '</li>';
		$help .= '</ol>';
		return $help;
	}

	/**
	 * Return info about the active connection.
	 *
	 * @return string
	 */
	private function get_connect_info(): string {
		// get the client with the given token.
		$client = new Client( $this->get_access_token() );

		// get the account infos.
		$account_infos = array();
		try {
			$account_infos = $client->getAccountInfo();
		} catch ( ClientException $e ) {
			Log::get_instance()->create( __( 'Error during request of DropBox account infos:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );
		}

		// bail if account infos are empty.
		if ( empty( $account_infos ) ) {
			return '<strong>' . esc_html__( 'Could not load the DropBox account infos.', 'external-files-in-media-library' ) . '</strong> ' . esc_html__( 'This usually means that the access token is no longer valid. Please try to reconnect with a new access token.', 'external-files-in-media-library' ) . '<br>';

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
	 * Return directory listing from Google Drive.
	 *
	 * @param string $directory The given directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the client with the given token.
		$client = new Client( $this->get_access_token() );

		// collect the list of files.
		$listing = array(
			'title' => basename( $directory ),
			'files' => array(),
			'dirs'  => array(),
		);

		// get the entries (files and folders).
		$entries = array();
		try {
			$entries = $client->listFolder( '/', true );
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

				// get content type of this file.
				$mime_type = wp_check_filetype( $dropbox_entry['path_lower'] );

				// bail if file is not allowed.
				if ( empty( $mime_type['type'] ) ) {
					continue;
				}

				// get binary stream of the file for preview.
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

									// add thumb to output if it does not result in an error.
									if ( ! is_wp_error( $results ) ) {
										$thumbnail = '<img src="' . esc_url( $upload_url . $results['file'] ) . '" alt="">';
									}
								}
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
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file } );',
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

		// set the file as tmp-file for import.
		$tmp_file = str_replace( '.tmp', '', wp_tempnam() . '.jpg' );

		// and save the file there.
		$wp_filesystem->put_contents( $tmp_file, $content );

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
		// only add the protocol if access_token for actual user is set.
		if ( empty( $this->get_access_token() ) ) {
			return $protocols;
		}

		// add the Google Drive protocol before the HTTPS-protocol and return resulting list of protocols.
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
		// bail if access token is empty.
		if ( empty( $this->get_access_token() ) ) {
			// log this event.
			Log::get_instance()->create( __( 'No access token for DropBox given!', 'external-files-in-media-library' ), '', 'error', 1 );

			// create error.
			$error = new WP_Error();
			/* translators: %1$s will be replaced with a URL. */
			$error->add( 'efml_service_dropbox', sprintf( __( 'DropBox access token is not configured. Please create a new one and <a href="%1$s">add it here</a>.', 'external-files-in-media-library' ), esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) ) );

			// add error.
			$this->add_error( $error );

			return false;
		}

		// get the client with the given token.
		$client = new Client( $this->get_access_token() );

		// start a simple request to check if access token could be used.
		try {
			$client->getAccountInfo();
		} catch ( ClientException $e ) {
			// log this event.
			Log::get_instance()->create( __( 'Error during check of DropBox access token:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', '', 'error', 1 );

			// create error.
			$error = new WP_Error();
			/* translators: %1$s will be replaced with a URL. */
			$error->add( 'efml_service_dropbox', sprintf( __( 'DropBox access token appears to be no longer valid. Please create a new one and <a href="%1$s">add it here</a>.', 'external-files-in-media-library' ), esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) ) );

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
}
