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
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the admin-support.
		Admin::get_instance()->init();

		// initialize the settings.
		Settings::get_instance()->init();

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
}
