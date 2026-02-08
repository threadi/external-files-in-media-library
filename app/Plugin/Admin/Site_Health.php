<?php
/**
 * File for handling site health options of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_REST_Server;

/**
 * Helper-function for Site Health options of this plugin.
 */
class Site_Health {
	/**
	 * Instance of this object.
	 *
	 * @var ?Site_Health
	 */
	private static ?Site_Health $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Site_Health {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the site health support.
	 *
	 * @return void
	 */
	public function init(): void {
		// register REST API.
		add_action( 'rest_api_init', array( $this, 'add_rest_api' ) );

		// add checks.
		add_filter( 'site_status_tests', array( $this, 'add_checks' ) );

		// add debug information.
		add_filter( 'debug_information', array( $this, 'add_debug_info' ) );
	}

	/**
	 * Return list of endpoints the site health should use for our plugin.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_endpoints(): array {
		$list = array();

		/**
		 * Filter the endpoints for Site Health this plugin is using.
		 *
		 * Hint: these are just arrays, which define the endpoints.
		 *
		 * @param array<int,array<string,mixed>> $list List of endpoints.
		 */
		return apply_filters( 'efml_site_health_endpoints', $list );
	}

	/**
	 * Register each rest api endpoints for site health checks.
	 *
	 * @return void
	 */
	public function add_rest_api(): void {
		foreach ( $this->get_endpoints() as $endpoint ) {
			// bail if no callback is set.
			if ( empty( $endpoint['callback'] ) ) {
				continue;
			}

			// check if args are set.
			if ( ! isset( $endpoint['args'] ) ) {
				$endpoint['args'] = array();
			}

			// register the route.
			register_rest_route(
				$endpoint['namespace'],
				$endpoint['route'],
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => $endpoint['callback'],
					'args'                => $endpoint['args'],
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				)
			);
		}
	}

	/**
	 * Add custom status-check for running cronjobs of our own plugin.
	 *
	 * @param array<string,mixed> $statuses List of tests to run.
	 * @return array<string,mixed>
	 */
	public function add_checks( array $statuses ): array {
		foreach ( $this->get_endpoints() as $check ) {
			$statuses['async'][ sanitize_title( $check['label'] ) ] = array(
				'label'    => $check['label'],
				'test'     => rest_url( $check['namespace'] . $check['route'] ),
				'has_rest' => true,
			);
		}

		// return the statuses.
		return $statuses;
	}

	/**
	 * Add our own debug information to site health.
	 *
	 * @param array<string,mixed> $debug_information List of debug information for the actual project.
	 *
	 * @return array<string,mixed>
	 */
	public function add_debug_info( array $debug_information ): array {
		$debug_information[ Helper::get_plugin_slug() ] = array(
			'label' => Helper::get_plugin_name(),
			'fields' => array()
		);

		// loop through all settings and add them as fields if their export is allowed.
		foreach( Settings::get_instance()->get_settings() as $setting ) {
			// create the entry.
			$entry = array(
				'label' => $setting->get_name(),
				'value' => $setting->get_value(),
				'private' => $setting->is_export_prevented()
			);

			// add it to the list.
			$debug_information[ Helper::get_plugin_slug() ]['fields'][$setting->get_name()] = $entry;
		}

		// return the resulting list of debug information.
		return $debug_information;
	}
}
