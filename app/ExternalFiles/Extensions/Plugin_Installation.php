<?php
/**
 * This file controls the option to install a plugin from an external source.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Section;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Admin\Plugins;
use ExternalFilesInMediaLibrary\Plugin\Admin\Upgrader_Skin;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Zip\Zip;
use WP_Post;
use WP_Upgrader;
use ZipArchive;

/**
 * Handler controls how to check the availability of external files.
 */
class Plugin_Installation extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'plugin_installation';

	/**
	 * Instance of actual object.
	 *
	 * @var Plugin_Installation|null
	 */
	private static ?Plugin_Installation $instance = null;

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
	 * @return Plugin_Installation
	 */
	public static function get_instance(): Plugin_Installation {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use hooks.
		add_action( 'init', array( $this, 'add_settings' ), 20 );
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );

		// add actions.
		add_action( 'admin_action_efml_install_plugin', array( $this, 'install_plugin_by_request' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Plugin Installation', 'external-files-in-media-library' );
	}

	/**
	 * Add our custom settings for this plugin.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// get the settings object.
		$settings_obj = Settings::get_instance();

		// get the main settings page.
		$main_settings_page = $settings_obj->get_page( 'eml_settings' );

		// bail if page could not be loaded.
		if ( ! $main_settings_page instanceof Page ) {
			return;
		}

		// get the advanced tab.
		$advanced_tab = $main_settings_page->get_tab( 'eml_advanced' );

		// bail if page could not be loaded.
		if ( ! $advanced_tab instanceof Tab ) {
			return;
		}

		// get the advanced section.
		$advanced_section = $advanced_tab->get_section( 'settings_section_advanced' );

		// bail if section could not be loaded.
		if ( ! $advanced_section instanceof Section ) {
			return;
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_plugin_installation' );
		$setting->set_section( $advanced_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Allow installation of plugin from external sources', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled any user with the capability for it will be able to install WordPress plugins from external sources. These plugins must just be saved as ZIP.', 'external-files-in-media-library' ) );
		$setting->set_field( $field );
	}

	/**
	 * Change media row actions for URL-files: add export option for local files.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// bail if cap is missing.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $actions;
		}

		// bail if the installation of plugins is not enabled.
		if ( 1 !== absint( get_option( 'eml_plugin_installation' ) ) ) {
			return $actions;
		}

		// bail if file is NOT an external file.
		if ( 0 === absint( get_post_meta( $post->ID, 'eml_exported_file', true ) ) ) {
			return $actions;
		}

		// bail if mime type is not a ZIP.
		if ( 'application/zip' !== get_post_mime_type( $post->ID ) ) {
			return $actions;
		}

		// create URL to export this file.
		$url = add_query_arg(
			array(
				'action' => 'efml_install_plugin',
				'post'   => $post->ID,
				'nonce'  => wp_create_nonce( 'efml-install-plugin' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Install WordPress plugin', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Are you sure you want to install this ZIP as WordPress plugin?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'It will only be installed. You can activate it after the installation yourself.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		// add the option.
		$actions['eml-install-plugin'] = '<a href="' . esc_url( $url ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Install plugin', 'external-files-in-media-library' ) . '</a>';

		// return the resulting list of actions.
		return $actions;
	}

	/**
	 * Install the plugin by request.
	 *
	 * @return void
	 */
	public function install_plugin_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-install-plugin', 'nonce' );

		// bail if cap is missing.
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// get the attachment ID from request.
		$attachment_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no attachment ID is given.
		if ( 0 === $attachment_id ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// bail if file is NOT an external file.
		if ( 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// get the external file object for this file.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// unzip the file to check a) its slug and b) if it is a valid WordPress plugin.
		$zip_obj = Zip::get_instance();
		$zip_obj->set_zip_file( $external_file_obj->get_url( true ) . '/' );
		$files = $zip_obj->get_files_from_zip();

		// prepare the slug.
		$slug = '';

		// check the files.
		foreach ( $files as $file ) {
			// bail if this is not the readme.txt.
			if ( 'readme.txt' !== $file['title'] ) { // @phpstan-ignore offsetAccess.nonOffsetAccessible
				continue;
			}

			// get the slug.
			$slug = dirname( str_replace( $external_file_obj->get_url( true ) . '/', '', $file['url'] ) );
		}

		// bail if slug could not be loaded.
		if ( empty( $slug ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// bail if this plugin is already installed.
		if ( Helper::is_plugin_installed( $slug ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// get the protocol handler of this file.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// get the file for local installation.
		$file = $protocol_handler_obj->get_temp_file( $external_file_obj->get_url( true ), Helper::get_wp_filesystem() );

		// bail if file could not be loaded.
		if ( ! is_string( $file ) ) {
			wp_safe_redirect( wp_get_referer() );
			return;
		}

		// unpack this package in the plugin directory.
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$upgrader       = new WP_Upgrader( new Upgrader_Skin() );
		$install_result = $upgrader->run(
			array(
				'package'           => $file,
				'destination'       => WP_PLUGIN_DIR . '/' . $slug,
				'clear_destination' => true,
			)
		);

		// bail if the unpacking was not successfully.
		if ( is_wp_error( $install_result ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// forward user to the plugin list and filter for the slug.
		$url = add_query_arg(
			array(
				's'             => $slug,
				'plugin_status' => 'all',
			),
			get_admin_url() . 'plugins.php'
		);
		wp_safe_redirect( $url );
	}
}
