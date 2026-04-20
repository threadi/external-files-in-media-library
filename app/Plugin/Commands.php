<?php
/**
 * File to handle the command palette for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * The object, which handles the command palette for this plugin.
 */
class Commands {
	/**
	 * Instance of this object.
	 *
	 * @var ?Commands
	 */
	private static ?Commands $instance = null;

	/**
	 * Constructor for Schedules-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Commands {
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
		// add action to enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add the command palette script to the admin area.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// bail if the user does not have capabilities to use services.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// get the path for the asset script.
		$script_asset_path = Helper::get_plugin_path() . 'js/commands/commands.asset.php';

		// bail if the asset script does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		// embed script.
		$script_asset = require $script_asset_path;

		wp_enqueue_script(
			'external-files-in-media-library-commands',
			Helper::get_plugin_url() . 'js/commands/commands.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}
}
