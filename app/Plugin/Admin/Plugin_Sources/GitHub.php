<?php
/**
 * File to handle GitHub as source for a service plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Admin\Plugin_Sources_Base;

/**
 * Object to handle GitHub as source for a service plugin.
 */
class GitHub extends Plugin_Sources_Base {
	/**
	 * Name of this source.
	 *
	 * @var string
	 */
	protected string $name = 'github';

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
		// bail if required fields are missing.
		if ( empty( $config ) || empty( $config['github_user'] ) || empty( $config['github_slug'] ) ) {
			return '';
		}

		// create URL for request.
		$url = 'https://api.github.com/repos/' . $config['github_user'] . '/' . $config['github_slug'] . '/releases/latest';

		// create HTTP header.
		$args     = array(
			'method'      => 'GET',
			'httpversion' => '1.1',
			'timeout'     => 30,
			'redirection' => 0,
			'body'        => array(),
		);
		$response = wp_safe_remote_get( $url, $args );

		// bail on error.
		if ( is_wp_error( $response ) ) {
			return '';
		}

		// bail if http status is not 200.
		if ( 200 !== absint( wp_remote_retrieve_response_code( $response ) ) ) {
			return '';
		}

		// get the contents from the response.
		$response_data = wp_remote_retrieve_body( $response );

		// get contents as array.
		$plugin_data = json_decode( $response_data );

		// bail if plugin data could not be loaded.
		if ( ! is_object( $plugin_data ) ) {
			return '';
		}

		// bail if no assets are set.
		if ( empty( $plugin_data->assets ) ) {
			return '';
		}

		// get the release ZIP from these assets.
		$release_zip_url = '';
		foreach ( $plugin_data->assets as $asset ) {
			// bail if this is no a zip.
			if ( 'application/zip' !== $asset->content_type ) {
				continue;
			}

			// bail if it has no size.
			if ( 0 === $asset->size ) {
				continue;
			}

			// use the release ZIP URL.
			$release_zip_url = $asset->browser_download_url;
		}

		// bail if no URL could be found.
		if ( empty( $release_zip_url ) ) {
			return '';
		}

		// save this file in local tmp.
		$release_zip_path = download_url( $release_zip_url );

		// bail if no URL could be downloaded.
		if ( is_wp_error( $release_zip_url ) ) {
			return '';
		}

		// bail if it is not a string.
		if ( ! is_string( $release_zip_path ) ) {
			return '';
		}

		// return the resulting absolute local path to the file to install.
		return $release_zip_path;
	}

	/**
	 * Return the description for the given source.
	 *
	 * @param array<string,mixed> $config The configuration of a service.
	 *
	 * @return string
	 */
	public function get_description( array $config ): string {
		/* translators: %1$s will be replaced by a URL. */
		return '<p>' . sprintf( __( 'The newest release from <a href="%1$s" target="_blank">this GitHub repository (opens new windows)</a> will be loaded.', 'external-files-in-media-library' ), 'https://github.com/' . $config['github_user'] . '/' . $config['github_slug'] ) . '</p>';
	}
}
