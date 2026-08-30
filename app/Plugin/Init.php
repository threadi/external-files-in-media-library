<?php
/**
 * This file contains the main initialization object for this plugin.
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
		$this->deprecated();

		// update handling.
		Update::get_instance()->init();

		// initialize our intervals.
		Intervals::get_instance()->init();

		// enable services we support.
		Services::get_instance()->init();

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

		// initialize the roles.
		Roles::get_instance()->init();

		// initialize the user management.
		Users::get_instance()->init();

		// initialize the command palette.
		Commands::get_instance()->init();

		// initialize the network settings.
		\ExternalFilesInMediaLibrary\Plugin\Network\Settings::get_instance()->init();

		// plugin-actions.
		register_activation_hook( EFML_PLUGIN, array( Install::get_instance(), 'activation' ) );
		register_deactivation_hook( EFML_PLUGIN, array( Install::get_instance(), 'deactivation' ) );

		// misc.
		add_action( 'cli_init', array( $this, 'cli' ) );
		add_filter( 'external-files-in-media-library_crypt_constant', array( $this, 'set_crypt_constant_name' ), 10, 0 );
	}

	/**
	 * Enable WP CLI.
	 *
	 * @return void
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 * @noinspection ClassConstantCanBeUsedInspection
	 */
	public function cli(): void {
		\WP_CLI::add_command( 'eml', 'ExternalFilesInMediaLibrary\Plugin\Cli' );
	}

	/**
	 * Set deprecated for backwarts compatibility.
	 *
	 * @return void
	 */
	private function deprecated(): void {
		if ( ! class_exists( '\ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings' ) ) {
			class_alias( '\ExternalFilesInMediaLibrary\Plugin\DeprecatedSettings', 'ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings' );
		}
	}

	/**
	 * Set the cryptographic constant name.
	 *
	 * @return string
	 */
	public function set_crypt_constant_name(): string {
		return 'EDLFW_HASH';
	}
}
