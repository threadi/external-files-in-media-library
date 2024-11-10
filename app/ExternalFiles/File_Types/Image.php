<?php
/**
 * File to handle Image files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\File_Types;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle images.
 */
class Image extends File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = 'Image';

	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array(
		'image/avif',
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Output of proxied file.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_proxied_file(): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// get the cached file.
		$cached_file = $external_file_obj->get_cache_file( $this->get_size() );

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// return header.
		header( 'Content-Type: ' . $external_file_obj->get_mime_type() );
		header( 'Content-Disposition: inline; filename="' . basename( $external_file_obj->get_url() ) . '"' );
		header( 'Content-Length: ' . wp_filesize( $cached_file ) );

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $cached_file ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Set meta-data for the file by given file data.
	 *
	 * @param array $file_data The file data.
	 *
	 * @return void
	 */
	public function set_metadata( array $file_data ): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// create the image meta data.
		$image_meta = wp_create_image_subsizes( $file_data['tmp-file'], $external_file_obj->get_id() );

		// set file to our url.
		$image_meta['file'] = $file_data['url'];

		// change file name for each size, if given.
		if ( ! empty( $image_meta['sizes'] ) ) {
			foreach ( $image_meta['sizes'] as $size_name => $size_data ) {
				$image_meta['sizes'][ $size_name ]['file'] = Helper::generate_sizes_filename( $file_data['title'], $size_data['width'], $size_data['height'], $external_file_obj->get_file_extension() );
			}
		}

		// save the resulting image-data.
		wp_update_attachment_metadata( $external_file_obj->get_id(), $image_meta );
	}

	/**
	 * Return whether this file should be proxied.
	 *
	 * @return bool
	 */
	public function is_proxy_enabled(): bool {
		return 1 === absint( get_option( 'eml_proxy' ) );
	}

	/**
	 * Return true if cache age has been reached its expiration.
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		// bail if no proxy age is set.
		if ( absint( get_option( 'eml_proxy_max_age' ) ) <= 0 ) {
			return false;
		}

		// compare cache file date with max proxy age.
		return filemtime( $this->get_file()->get_cache_file() ) < ( time() - absint( get_option( 'eml_proxy_max_age' ) ) * 60 * 60 );
	}

	/**
	 * Return whether this file type has thumbs.
	 *
	 * @return bool
	 */
	public function has_thumbs(): bool {
		return true;
	}
}
