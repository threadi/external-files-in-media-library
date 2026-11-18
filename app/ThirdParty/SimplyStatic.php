<?php
/**
 * File to handle support for the plugin "Simply Static".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 *
 * The plugin creates a static copy of the website via its URLs. It copies files from the own domain, so files
 * delivered via the proxy are copied, too, if their URL has the form of a file ("/emlproxy/image.jpg"). Without
 * pretty permalinks, the URLs of the proxy have a query string ("?emlproxy=image.jpg"), which the plugin does not
 * copy as file. The Site Health shows a hint in this case.
 */
class SimplyStatic extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?SimplyStatic
	 */
	private static ?SimplyStatic $instance = null;

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
	 * @return SimplyStatic
	 */
	public static function get_instance(): SimplyStatic {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the plugin files of the supported plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'simply-static/simply-static.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if plugin is not active.
		if ( ! $this->is_supported_plugin_active() ) {
			return;
		}

		// use hooks.
		add_filter( 'site_status_tests', array( $this, 'add_site_health_test' ) );
	}

	/**
	 * Add the check of the permalinks to the Site Health.
	 *
	 * @param array<string,array<string,mixed>> $tests List of tests.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_site_health_test( array $tests ): array {
		$tests['direct']['efml_simply_static'] = array(
			'label' => __( 'Files of the proxy in static copies of Simply Static', 'external-files-in-media-library' ),
			'test'  => array( $this, 'check_permalinks' ),
		);
		return $tests;
	}

	/**
	 * Return the result of the check, whether files of the proxy could be copied by Simply Static.
	 *
	 * @return array<string,mixed>
	 */
	public function check_permalinks(): array {
		// define the result.
		$result = array(
			'label'       => __( 'Simply Static copies the files of the proxy', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => Helper::get_plugin_name(),
				'color' => 'gray',
			),
			'description' => '<p>' . __( 'Files delivered via the proxy of External Files in Media Library have URLs in the form of files, so Simply Static copies them into the static website. Files, which are delivered directly from their external source, stay linked there.', 'external-files-in-media-library' ) . '</p>',
			'actions'     => '',
			'test'        => 'efml_simply_static',
		);

		// return the good result, if pretty permalinks are used or the proxy is not used.
		if ( '' !== (string) get_option( 'permalink_structure' ) || ! Proxy::get_instance()->is_any_proxy_enabled() ) {
			return $result;
		}

		// return the hint.
		$result['label']       = __( 'Simply Static does not copy the files of the proxy', 'external-files-in-media-library' );
		$result['status']      = 'recommended';
		$result['description'] = '<p>' . __( 'Without pretty permalinks, the URLs of files delivered via the proxy of External Files in Media Library contain a query string (e.g. "?emlproxy=image.jpg"). Simply Static does not copy such URLs as files, so these files are missing in the static website.', 'external-files-in-media-library' ) . '</p>';
		$result['actions']     = '<p><a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . __( 'Choose a structure for the permalinks other than "Plain"', 'external-files-in-media-library' ) . '</a></p>';

		// return the result.
		return $result;
	}
}
