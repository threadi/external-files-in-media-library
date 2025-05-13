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
use ExternalFilesInMediaLibrary\Plugin\Languages;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
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
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
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

		// initialize transients.
		Transients::get_instance()->init();

		// initialize the help system.
		Help_System::get_instance()->init();

		// initialize the directory listing support.
		Directory_Listing::get_instance()->init();

		// add admin hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_dialog_scripts' ) );
		add_action( 'admin_init', array( $this, 'trigger_mime_warning' ) );
		add_action( 'admin_init', array( $this, 'check_php' ) );
		add_action( 'admin_init', array( $this, 'check_gprd' ) );
		add_action( 'admin_action_eml_empty_log', array( $this, 'empty_log' ) );
		add_action( 'admin_action_eml_log_delete_entry', array( $this, 'delete_log_entry' ) );

		// misc.
		add_filter( 'plugin_action_links_' . plugin_basename( EFML_PLUGIN ), array( $this, 'add_setting_link' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );
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
		/* translators: %1$s will be replaced by the URL for the plugin configuration. */
		$transient_obj->set_message( sprintf( __( '<strong>External files could not be used as no mime-types are allowed.</strong> Go to <a href="%1$s">Settings</a> to choose mime-types you want to use.', 'external-files-in-media-library' ), esc_url( Helper::get_config_url() ) ) );
		$transient_obj->set_type( 'error' );
		$transient_obj->save();
	}

	/**
	 * Add WP Dialog Easy scripts in wp-admin.
	 */
	public function add_dialog_scripts(): void {
		// define paths: adjust if necessary.
		$path = trailingslashit( plugin_dir_path( EFML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';
		$url  = trailingslashit( plugin_dir_url( EFML_PLUGIN ) ) . 'vendor/threadi/easy-dialog-for-wordpress/';

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
			(string) filemtime( $admin_css_path )
		);
	}

	/**
	 * Check if website is using PHP 8.1 or older and warn about it.
	 *
	 * @return void
	 */
	public function check_php(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// use this after 2025-10-01.
		if( time() < 1759269600 ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_php_hint' ) );
			return;
		}

		// bail if PHP >= 8.2 is used.
		if ( PHP_VERSION_ID > 80200 ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_php_hint' ) );
			return;
		}

		// bail if WordPress is in developer mode.
		if ( function_exists( 'wp_is_development_mode' ) && wp_is_development_mode( 'plugin' ) ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_php_hint' ) );
			//return;
		}

		// show hint for old PHP-version.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_php_hint' );
		$transient_obj->set_dismissible_days( 90 );
		$transient_obj->set_message( '<strong>' . __( 'Your website is using an old PHP-version!', 'external-files-in-media-library' ) . '</strong><br>' . __( 'Future versions of <i>External Files in Media Library</i> will no longer be compatible with PHP 8.1 or older. These versions <a href="https://www.php.net/supported-versions.php" target="_blank">will be outdated</a> after December 2025. To continue using the plugins new features, please update your PHP version.', 'external-files-in-media-library' ) . '<br>' . __( 'Talk to your hosting support team about this.', 'external-files-in-media-library' ) );
		$transient_obj->save();
	}

	/**
	 * Add link to settings in plugin list.
	 *
	 * @param array<string> $links List of links.
	 *
	 * @return array<string>
	 */
	public function add_setting_link( array $links ): array {
		// add link to settings.
		$links[] = "<a href='" . esc_url( Helper::get_config_url() ) . "'>" . __( 'Settings', 'external-files-in-media-library' ) . '</a>';

		// add link to add media.
		$links[] = "<a href='" . esc_url( Helper::get_add_media_url() ) . "' style='font-weight: bold'>" . __( 'Add external files', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of links.
		return $links;
	}

	/**
	 * Add links in row meta.
	 *
	 * @param array<string> $links List of links.
	 * @param string        $file The requested plugin file name.
	 *
	 * @return array<string>
	 */
	public function add_row_meta_links( array $links, string $file ): array {
		// bail if this is not our plugin.
		if ( EFML_PLUGIN !== WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $file ) {
			return $links;
		}

		// add our custom links.
		$row_meta = array(
			'support' => '<a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank" title="' . esc_attr__( 'Support Forum', 'external-files-in-media-library' ) . '">' . esc_html__( 'Support Forum', 'external-files-in-media-library' ) . '</a>',
		);

		/**
		 * Filter the links in row meta of our plugin in plugin list.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 * @param array $row_meta List of links.
		 */
		$row_meta = apply_filters( 'eml_plugin_row_meta', $row_meta );

		// return the resulting list of links.
		return array_merge( $links, $row_meta );
	}

	/**
	 * Empty the log per request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function empty_log(): void {
		// check the nonce.
		check_admin_referer( 'eml-empty-log', 'nonce' );

		// empty the table.
		Log::get_instance()->truncate_log();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Delete single log entry.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_log_entry(): void {
		// check the nonce.
		check_admin_referer( 'eml-log-delete-entry', 'nonce' );

		// get the ID from request.
		$id = absint( filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// empty the table.
		Log::get_instance()->delete_log( $id );

		// show ok message.
		$transients_obj = Transients::get_instance();
		$transient_obj  = $transients_obj->add();
		$transient_obj->set_name( 'eml_log_entry_deleted' );
		$transient_obj->set_message( '<strong>' . __( 'The log entry has been deleted.', 'external-files-in-media-library' ) . '</strong>' );
		$transient_obj->set_type( 'success' );
		$transient_obj->save();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Check if website is using a language which _might_ be underlying the GPRS.
	 *
	 * Show a warning hint if it is the case.
	 *
	 * @return void
	 */
	public function check_gprd(): void {
		// get transients object.
		$transients_obj = Transients::get_instance();

		// bail if setting to hide this hint is enabled.
		if ( 1 === absint( get_option( 'eml_disable_gprd_warning' ) ) ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_gprd_hint' ) );
			return;
		}

		// bail if language is not german.
		if ( ! Languages::get_instance()->is_german_language() ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_gprd_hint' ) );
			return;
		}

		// bail if WordPress is in developer mode.
		if ( function_exists( 'wp_is_development_mode' ) && wp_is_development_mode( 'plugin' ) ) {
			$transients_obj->delete_transient( $transients_obj->get_transient_by_name( 'eml_gprd_hint' ) );
			return;
		}

		// show hint for GPRD.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_type( 'error' );
		$transient_obj->set_name( 'eml_gprd_hint' );
		$transient_obj->set_dismissible_days( 180 );
		/* translators: %1$s will be replaced by a URL. */
		$transient_obj->set_message( '<strong>' . sprintf( __( 'Your website seems to be subject to the European Union rules of the <a href="%1$s" target="_blank">GPRD (opens new window)</a>!', 'external-files-in-media-library' ), esc_url( Helper::get_gprd_url() ) ) . '</strong><br><br>' . __( 'Please note that according to these rules, the use of external, directly loaded files (such as images or videos) in a website requires active information to the visitor before these files are loaded. We recommend that you use the proxy mode offered when using <i>External Files for Media Library</i>. This means that the files are not loaded directly from an external source but are cached locally. If you have any further questions about these rules, please contact your legal advisor.', 'external-files-in-media-library' ) . '<br><br>' . sprintf( __( 'The above-mentioned detection is based on the language you use in WordPress. If you are not affected by the GPRD-rules, we apologize for this information. You can hide it at any time <a href="%1$s">by click on this link</a>.', 'external-files-in-media-library' ), esc_url( Settings::get_instance()->disable_gprd_hint_url() ) ) );
		$transient_obj->save();
	}
}
