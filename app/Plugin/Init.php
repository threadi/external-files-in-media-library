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
		// update handling.
		Update::get_instance()->init();

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

		// plugin-actions.
		register_activation_hook( EFML_PLUGIN, array( Install::get_instance(), 'activation' ) );
		register_deactivation_hook( EFML_PLUGIN, array( Install::get_instance(), 'deactivation' ) );

		// misc.
		add_action( 'cli_init', array( $this, 'cli' ) );
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
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
	 * Add some custom cron-intervals.
	 *
	 * @param array<string, array<string, int|string>> $intervals List of intervals.
	 *
	 * @return array<string, array<string, int|string>>
	 */
	public function add_cron_intervals( array $intervals ): array {
		$intervals['efml_15minutely'] = array(
			'interval' => 60 * 15,
			'display'  => __( 'every 15 Minutes', 'external-files-in-media-library' ),
		);
		$intervals['efml_20minutely'] = array(
			'interval' => 60 * 20,
			'display'  => __( 'every 20 Minutes', 'external-files-in-media-library' ),
		);
		$intervals['efml_30minutely'] = array(
			'interval' => 60 * 30,
			'display'  => __( 'every 30 Minutes', 'external-files-in-media-library' ),
		);
		$intervals['efml_hourly']     = array(
			'interval' => 60 * 60,
			'display'  => __( 'every hour', 'external-files-in-media-library' ),
		);
		$intervals['efml_2hourly']    = array(
			'interval' => 60 * 60 * 2,
			'display'  => __( 'every 2 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_3hourly']    = array(
			'interval' => 60 * 60 * 3,
			'display'  => __( 'every 3 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_4hourly']    = array(
			'interval' => 60 * 60 * 4,
			'display'  => __( 'every 4 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_6hourly']    = array(
			'interval' => 60 * 60 * 6,
			'display'  => __( 'every 6 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_12hourly']   = array(
			'interval' => 60 * 60 * 12,
			'display'  => __( 'every 12 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_24hourly']   = array(
			'interval' => 60 * 60 * 24,
			'display'  => __( 'every 24 hours', 'external-files-in-media-library' ),
		);
		$intervals['efml_weekly']     = array(
			'interval' => 60 * 60 * 24 * 7,
			'display'  => __( 'every week', 'external-files-in-media-library' ),
		);

		// return resulting list of additional intervals.
		return $intervals;
	}
}
