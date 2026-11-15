<?php
/**
 * File to handle support for the plugin "Smush".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this plugin.
 *
 * It optimizes the local files of uploads after the upload, so their export waits for it. Its backup of the image
 * before the optimization (e.g. "image.bak.jpg") is exported together with the image (see ExternalFiles\Variants).
 */
class Smush extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Smush
	 */
	private static ?Smush $instance = null;

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
	 * @return Smush
	 */
	public static function get_instance(): Smush {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the main files of the plugin, relative to the plugin directory.
	 *
	 * @return array<int,string>
	 */
	protected function get_plugin_files(): array {
		return array( 'wp-smushit/wp-smush.php', 'wp-smush-pro/wp-smush.php' );
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * Smush (free) creates no files in other formats: only the export waits for its optimization.
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_variant_paths( string $path ): array {
		return array();
	}

	/**
	 * Add the backup of the image, which Smush saves before its optimization (e.g. "image.bak.jpg"), so it is
	 * exported, deleted and restored together with the image.
	 *
	 * @param array<int,array<string,string>> $variants      The variants.
	 * @param int                             $attachment_id The attachment ID.
	 * @param array<string,string>            $paths         The absolute paths of the files (size name => path).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_variants( array $variants, int $attachment_id, array $paths ): array {
		// bail if the image itself is not given.
		if ( empty( $paths['full'] ) ) {
			return $variants;
		}

		// get the directory and the possible names of the backups.
		$info      = pathinfo( $paths['full'] );
		$directory = trailingslashit( $info['dirname'] );
		$names     = array( $info['filename'] . '.bak.' . ( $info['extension'] ?? '' ) );

		// add the backups, which Smush saves in the backup sizes of WordPress (e.g. "smush-full").
		$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );
		if ( is_array( $backup_sizes ) ) {
			foreach ( $backup_sizes as $key => $backup ) {
				if ( str_starts_with( (string) $key, 'smush' ) && is_array( $backup ) && ! empty( $backup['file'] ) && is_string( $backup['file'] ) ) {
					$names[] = wp_basename( $backup['file'] );
				}
			}
		}

		// add each existing backup.
		foreach ( array_unique( $names ) as $name ) {
			// get the path.
			$path = $directory . $name;

			// bail if it does not exist.
			if ( ! file_exists( $path ) ) {
				continue;
			}

			// get its mime type.
			$type = wp_check_filetype( $name );

			// add it.
			$variants[] = array(
				'size'      => 'full',
				'path'      => $path,
				'mime-type' => (string) $type['type'],
				'kind'      => 'backup',
			);
		}

		// return the list.
		return $variants;
	}

}
