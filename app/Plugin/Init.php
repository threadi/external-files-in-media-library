<?php
/**
 * This file contains the main init-object for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Elementor\Element_Base;
use Elementor\Widget_Video;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
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
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'add_elementor_video' ) );
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

	public function add_elementor_video( Element_Base $element ): void {
		// bail if this is not the video widget.
		if( ! $element instanceof Widget_Video ) {
			return;
		}

		// get the settings.
		$settings = $element->get_settings();

		// bail if hostet URL is not set.
		if( empty( $settings['hosted_url'] ) ) {
			return;
		}

		// bail if not ID is given.
		if( empty( $settings['hosted_url']['id'] ) ) {
			return;
		}

		// get the attachment ID.
		$attachment_id = $settings['hosted_url']['id'];

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if external file obj could not be loaded.
		if( ! $external_file_obj ) {
			return;
		}

		// remove the local hosted marker.
		$element->set_settings( 'video_type', 'youtube' );
		$element->delete_setting( 'hosted_url' );
		$element->delete_setting( '__dynamic__' );

		// set the YouTube URL.
		$element->set_settings( 'youtube_url', $external_file_obj->get_url( true ) );
	}
}
