<?php
/**
 * File with the base for the support of image optimizers.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads;
use ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Base for the support of plugins, which work with the local files of uploads after the upload (e.g. image optimizers).
 *
 * If such a plugin is active, the export of uploads waits, so the plugin finds the local files and its optimized
 * files are exported. The variants of the images in other formats (e.g. WebP) and the backups, which it creates,
 * are exported together with them (see ExternalFiles\Variants).
 *
 * Exported images could be optimized via local working copies (see ExternalFiles\Working_Copies): as soon as the
 * plugin reports the end of its work for an image, the working copy is finished at the end of the request.
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
	 * Return the possible backups of the given local file, which this plugin saves (path => name on the external
	 * source). Default: none.
	 *
	 * The name on the external source must differ from the names of the files of the image, as the backups are
	 * exported beside them.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_backup_paths( string $path ): array {
		return array();
	}

	/**
	 * Return the name of a backup on the external source: the name of the file with the given suffix before its
	 * extension (e.g. "image-300x200.ewww-backup.jpg").
	 *
	 * @param string $path   The absolute path of the file.
	 * @param string $suffix The suffix.
	 *
	 * @return string
	 */
	protected function get_backup_name( string $path, string $suffix ): string {
		$info = pathinfo( $path );
		return $info['filename'] . '.' . $suffix . ( ! empty( $info['extension'] ) ? '.' . $info['extension'] : '' );
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

			// add the existing backups of this file.
			foreach ( $this->get_backup_paths( $path ) as $backup_path => $name ) {
				// bail if it does not exist.
				if ( ! file_exists( $backup_path ) ) {
					continue;
				}

				// add it.
				$type       = wp_check_filetype( $backup_path );
				$variants[] = array(
					'size'      => (string) $size_name,
					'path'      => $backup_path,
					'mime-type' => (string) $type['type'],
					'kind'      => 'backup',
					'name'      => $name,
				);
			}
		}

		// return the list.
		return $variants;
	}

	/**
	 * Mark the end of the work of this plugin with an image: a waiting upload is exported, and a working copy of an
	 * exported image is finished at the end of the request.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	protected function done( int $attachment_id ): void {
		// bail if no ID is given.
		if ( $attachment_id <= 0 ) {
			return;
		}

		// export a waiting upload.
		Delayed_Uploads::get_instance()->done( $attachment_id );

		// finish a working copy.
		Working_Copies::get_instance()->finish_later( $attachment_id );
	}

	/**
	 * Create the working copy of an exported image, before this plugin works with it by request of the user (e.g.
	 * to optimize or restore a single image in the media library). It is finished at the end of the request, or, if
	 * the plugin works in the background, as soon as it reports the end of its work.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $finish_later  Whether the working copy is finished at the end of this request (false, if the plugin
	 *                            works with it in the background and reports the end of its work).
	 *
	 * @return bool True if the image has a working copy now.
	 */
	protected function provide_working_copy( int $attachment_id, bool $finish_later = true ): bool {
		// get the object.
		$working_copies_obj = Working_Copies::get_instance();

		// bail if the user is not allowed to change the image or no working copy is possible.
		if ( $attachment_id <= 0 || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) || ! $working_copies_obj->is_possible( $attachment_id ) ) {
			return false;
		}

		// use an existing working copy (e.g. saved for a bulk optimization) and finish it at the end of this request,
		// if requested (e.g. as the plugin does not report the end of a restore).
		if ( $working_copies_obj->is_active( $attachment_id ) ) {
			if ( $finish_later ) {
				$working_copies_obj->finish_later( $attachment_id );
			}
			return true;
		}

		// create it.
		if ( ! $working_copies_obj->create( $attachment_id ) ) {
			return false;
		}

		// finish it at the end of this request, if the plugin does not work in the background.
		if ( $finish_later ) {
			$working_copies_obj->finish_later( $attachment_id );
		}

		// return true as the image has a working copy now.
		return true;
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
