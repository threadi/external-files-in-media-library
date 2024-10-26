<?php
/**
 * This file contains an object which handles the admin tasks of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Forms;
use ExternalFilesInMediaLibrary\ExternalFiles\Tables;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Logs;
use ExternalFilesInMediaLibrary\Plugin\Transients;

/**
 * Initialize the admin tasks for this plugin.
 */
class Admin {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Admin
	 */
	private static ?Admin $instance = null;

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
	 * @return Admin
	 */
	public static function get_instance(): Admin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the backend forms for external files.
		Forms::get_instance()->init();

		// initialize the table extensions.
		Tables::get_instance()->init();

		// initialize the files object.
		Files::get_instance()->init();

		// add admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dialog_scripts' ) );
		add_action( 'admin_init', array( $this, 'trigger_mime_warning' ) );
		add_action( 'admin_init', array( $this, 'check_php' ) );

		// misc.
		add_filter( 'plugin_action_links_' . plugin_basename( EML_PLUGIN ), array( $this, 'add_setting_link' ) );
	}

	/**
	 * Checks on each admin-initialization.
	 *
	 * @return void
	 */
	public function trigger_mime_warning(): void {
		// bail if mime types are allowed.
		if ( ! empty( Helper::get_allowed_mime_types() ) ) {
			return;
		}

		// trigger warning as no mime types are allowed.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_dismissible_days( 14 );
		$transient_obj->set_name( 'eml_missing_mime_types' );
		$transient_obj->set_message( __( 'External files could not be used as no mime-types are allowed.', 'external-files-in-media-library' ) );
		$transient_obj->set_type( 'error' );
		$transient_obj->save();
	}

	/**
	 * Add WP Dialog Easy scripts in wp-admin.
	 */
	public function add_dialog_scripts(): void {
		// define paths: adjust if necessary.
		$path = trailingslashit( plugin_dir_path( EML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';
		$url  = trailingslashit( plugin_dir_url( EML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';

		// bail if path does not exist.
		if ( ! file_exists( $path ) ) {
			return;
		}

		// embed the dialog-components JS-script.
		$script_asset_path = $path . 'build/index.asset.php';

		// bail if file does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		$script_asset = require $script_asset_path;
		wp_enqueue_script(
			'easy-dialog-for-wordpress',
			$url . 'build/index.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		// embed the dialog-components CSS-script.
		$admin_css      = $url . 'build/style-index.css';
		$admin_css_path = $path . 'build/style-index.css';
		wp_enqueue_style(
			'easy-dialog-for-wordpress',
			$admin_css,
			array( 'wp-components' ),
			filemtime( $admin_css_path )
		);
	}

	/**
	 * Check if website is using a valid SSL and show warning if not.
	 *
	 * @return void
	 */
	public function check_php(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if WordPress is in developer mode.
		if ( function_exists( 'wp_is_development_mode' ) && wp_is_development_mode( 'plugin' ) ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_php_hint' ) );
			return;
		}

		// bail if PHP >= 8.1 is used.
		if ( version_compare( PHP_VERSION, '8.1', '>' ) ) {
			return;
		}

		// show hint for necessary configuration to restrict access to application files.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_php_hint' );
		$transient_obj->set_dismissible_days( 90 );
		$transient_obj->set_message( '<strong>' . __( 'Your website is using an outdated PHP-version!', 'external-files-in-media-library' ) . '</strong><br>' . __( 'Future versions of <i>External Files in Media Library</i> will no longer be compatible with PHP 8.0 or older. These versions <a href="https://www.php.net/supported-versions.php" target="_blank">are outdated</a> since December 2023. To continue using the plugins new features, please update your PHP version.', 'external-files-in-media-library' ) . '<br>' . __( 'Talk to your hosting support team about this.', 'external-files-in-media-library' ) );
		$transient_obj->save();
	}

	/**
	 * Add link to settings in plugin list.
	 *
	 * @param array $links List of links.
	 *
	 * @return array
	 */
	public function add_setting_link( array $links ): array {
		// add link to settings.
		$links[] = "<a href='" . esc_url( Helper::get_config_url() ) . "'>" . __( 'Settings', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of links.
		return $links;
	}
}
