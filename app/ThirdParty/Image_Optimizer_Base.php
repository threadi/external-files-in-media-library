<?php
/**
 * File with the base for the support of image optimizers.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Base for the support of plugins, which work with the local files of uploads after the upload (e.g. image optimizers).
 *
 * If such a plugin is active, the export of uploads waits, so the plugin finds the local files and its optimized
 * files are exported. The variants of the images in other formats (e.g. WebP), which it creates, are exported
 * together with them (see ExternalFiles\Variants).
 */
abstract class Image_Optimizer_Base extends ThirdParty_Base implements ThirdParty {
	/**
	 * Return the main files of the plugin (also of its variants, e.g. a pro version), relative to the plugin directory.
	 *
	 * @return array<int,string>
	 */
	abstract protected function get_plugin_files(): array;

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not active.
		if ( ! $this->is_active() ) {
			return;
		}

		// use the hooks.
		$this->add_hooks();
	}

	/**
	 * Return whether the plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		foreach ( $this->get_plugin_files() as $plugin_file ) {
			if ( Helper::is_plugin_active( $plugin_file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		add_filter( 'efml_export_upload_delay', array( $this, 'set_delay' ), 10, 2 );
		add_filter( 'efml_image_variants', array( $this, 'add_variants' ), 10, 3 );
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * Default: the name of the file with an added extension (e.g. "image.jpg.webp" beside "image.jpg").
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_variant_paths( string $path ): array {
		return array(
			$path . '.avif' => 'image/avif',
			$path . '.webp' => 'image/webp',
		);
	}

	/**
	 * Add the existing variants of the files of an image, which this plugin creates (e.g. WebP files).
	 *
	 * @param array<int,array<string,string>> $variants      The variants.
	 * @param int                             $attachment_id The attachment ID.
	 * @param array<string,string>            $paths         The absolute paths of the files (size name => path).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_variants( array $variants, int $attachment_id, array $paths ): array {
		foreach ( $paths as $size_name => $path ) {
			foreach ( $this->get_variant_paths( $path ) as $variant_path => $mime_type ) {
				// bail if it does not exist.
				if ( ! file_exists( $variant_path ) ) {
					continue;
				}

				// add it.
				$variants[] = array(
					'size'      => (string) $size_name,
					'path'      => $variant_path,
					'mime-type' => $mime_type,
				);
			}
		}

		// return the list.
		return $variants;
	}

	/**
	 * Return the delay in seconds for the export of an upload, so the plugin could work with its local files.
	 *
	 * @param int $delay         The delay in seconds.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function set_delay( int $delay, int $attachment_id ): int {
		// only for images.
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return $delay;
		}

		// wait at least 5 minutes.
		return max( $delay, 5 * MINUTE_IN_SECONDS );
	}
}
