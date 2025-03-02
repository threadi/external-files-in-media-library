<?php
/**
 * File to handle support for Block Editor.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class BlockEditor extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?BlockEditor
	 */
	private static ?BlockEditor $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return BlockEditor
	 */
	public static function get_instance(): BlockEditor {
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
		add_action( 'enqueue_block_editor_assets', array( $this, 'add_scripts' ) );
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
