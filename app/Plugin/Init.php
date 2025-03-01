<?php
/**
 * This file contains the main init-object for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\Plugin\Admin\Admin;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\ThirdParty\ThirdPartySupport;

/**
 * Initialize the plugin, connect all together.
 */
class Init {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

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
	 * @return Init
	 */
	public static function get_instance(): Init {
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
		// update handling.
		Update::get_instance()->init();

		// initialize the admin-support.
		Admin::get_instance()->init();

		// initialize the settings.
		Settings::get_instance()->init();

		// enable services we support.
		Services::get_instance()->init();

		// enable third party support.
		ThirdPartySupport::get_instance()->init();

		// initialize proxy.
		Proxy::get_instance()->init();

		// initialize schedules.
		Schedules::get_instance()->init();

		// initialize statistics.
		Statistics::get_instance()->init();

		// plugin-actions.
		register_activation_hook( EFML_PLUGIN, array( Install::get_instance(), 'activation' ) );
		register_deactivation_hook( EFML_PLUGIN, array( Install::get_instance(), 'deactivation' ) );

		// misc.
		add_action( 'cli_init', array( $this, 'cli' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'add_scripts' ) );
	}

	/**
	 * Enable WP CLI.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\Plugin\Cli' );
	}

	/**
	 * Add Block Editor script.
	 *
	 * @return void
	 */
	public function add_scripts(): void {
		// get the script path.
		$script_path = Helper::get_plugin_url() . 'blocks/build/index.js';

		// get the asset path.
		$script_asset_path = Helper::get_plugin_dir() . 'blocks/build/index.asset.php';

		// get the assets.
		$script_asset = require $script_asset_path;

		// enqueue the script.
		wp_enqueue_script(
			'efml-script',
			$script_path,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}
}
