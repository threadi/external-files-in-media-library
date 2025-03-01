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
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			exit;
		}

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
	 * Set meta-data for the file if it is hosted extern and with proxy.
	 *
	 * @return void
	 */
	public function set_metadata(): void {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			return;
		}

		// get the file object.
		$external_file_obj = $this->get_file();

		// bail if file should be saved locally (then WP will handle this for us).
		if ( $external_file_obj->is_locally_saved() ) {
			return;
		}

		// bail if proxy is not enabled for images.
		if ( ! $this->is_proxy_enabled() ) {
			return;
		}

		// get the protocol handler for this file.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// bail if no handler found.
		if ( ! $protocol_handler ) {
			return;
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// get temporary file.
		$tmp_file = $protocol_handler->get_temp_file( $external_file_obj->get_url( true ), $wp_filesystem );

		// bail if no tmp file returned.
		if ( ! $tmp_file ) {
			return;
		}

		// create the image meta data.
		$image_meta = wp_create_image_subsizes( $tmp_file, $external_file_obj->get_id() );

		// set file to our url.
		$image_meta['file'] = $external_file_obj->get_url( true );

		// change file name for each size, if given.
		if ( ! empty( $image_meta['sizes'] ) ) {
			foreach ( $image_meta['sizes'] as $size_name => $size_data ) {
				$image_meta['sizes'][ $size_name ]['file'] = Helper::generate_sizes_filename( $external_file_obj->get_title(), $size_data['width'], $size_data['height'], $external_file_obj->get_file_extension() );
			}
		}

		// save the resulting image-data.
		wp_update_attachment_metadata( $external_file_obj->get_id(), $image_meta );

		// if caption is set in image meta, use it.
		if ( ! empty( trim( $image_meta["image_meta"]['caption'] ) ) ) {
			$query = array(
				'ID' => $external_file_obj->get_id(),
				'post_excerpt' => $image_meta["image_meta"]['caption']
			);
			wp_update_post( $query );
		}

		/**
		 * Run additional tasks to add custom meta data on external hostet files.
		 *
		 * @since 3.1.0 Available since 3.1.0.
		 * @param File $external_file_obj The external files object.
		 * @param array $image_meta The image meta data.
		 */
		do_action( 'eml_image_meta_data', $external_file_obj, $image_meta );
	}

	/**
	 * Return whether this file should be saved locally.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return 'local' === get_option( 'eml_images_mode' );
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
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			return false;
		}

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
