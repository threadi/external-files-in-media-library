<?php
/**
 * File to handle ZIP files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\File_Types;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle ZIP files.
 */
class Zip extends File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = 'ZIP';

	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array(
		'application/zip',
	);

	/**
	 * Return the file type title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'ZIPs', 'external-files-in-media-library' );
	}

	/**
	 * Output of proxied file.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_proxied_file(): void {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			exit;
		}

		// get the file object.
		$external_file_obj = $this->get_file();

		// get the cached file.
		$cached_file = $external_file_obj->get_cache_file( $this->get_size() );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// return header.
		header( 'Content-Type: ' . $external_file_obj->get_mime_type() );
		header( 'Content-Disposition: inline; filename="' . basename( $external_file_obj->get_url() ) . '"' );
		header( 'Content-Length: ' . wp_filesize( $cached_file ) );

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $cached_file ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Return whether this file should be saved locally.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return 'local' === get_option( 'eml_zip_mode' );
	}

	/**
	 * Return whether this file should be proxied.
	 *
	 * @return bool
	 */
	public function is_proxy_enabled(): bool {
		return 'external' === get_option( 'eml_zip_mode' ) && 1 === absint( get_option( 'eml_zip_proxy' ) );
	}

	/**
	 * Return true if cache age has been reached its expiration.
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			return true;
		}

		// bail if no proxy age is set.
		if ( absint( get_option( 'eml_zip_proxy_max_age' ) ) <= 0 ) {
			return false;
		}

		// compare cache file date with max proxy age.
		return filemtime( $this->get_file()->get_cache_file() ) < ( time() - absint( get_option( 'eml_zip_proxy_max_age', 168 ) ) * 60 * 60 );
	}
}
