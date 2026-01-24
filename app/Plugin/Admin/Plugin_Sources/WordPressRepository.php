<?php
/**
 * File to handle the WordPress Repository as source for a service plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources_Base;

/**
 * Object to handle the WordPress Repository as source for a service plugin.
 */
class WordPressRepository extends Plugin_Sources_Base {
	/**
	 * Name of this source.
	 *
	 * @var string
	 */
	protected string $name = 'wordpress-repository';

	/**
	 * Return the absolute path of the given file from the external source.
	 *
	 * Returns an empty string on any failure.
	 *
	 * @param array<string,mixed> $config The specific configuration.
	 *
	 * @return string
	 */
	public function get_file( array $config ): string {
		// embed necessary files.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		// request the WordPress Repository to get the download URL.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $config['plugin_slug'],
				'fields' => array(
					'sections' => false,
				),
			)
		);

		// bail if this is not an object.
		if ( ! is_object( $api ) ) {
			return '';
		}

		// bail if "download_link" does not exist as property.
		if ( ! property_exists( $api, 'download_url' ) ) {
			return '';
		}

		// return the download URL.
		return $api->download_link; // @phpstan-ignore property.notFound
	}

	/**
	 * Return the description for the given source.
	 *
	 * @param array<string,mixed> $config The configuration of a service.
	 *
	 * @return string
	 */
	public function get_description( array $config ): string {
		return '<p>' . sprintf( __( 'The newest release from <a href="%1$s" target="_blank">this WordPress plugin (opens new windows)</a> will be loaded.', 'external-files-in-media-library' ), 'https://wordpress.org/plugins/' . $config['plugin_slug'] . '/' ) . '</p>';
	}
}
