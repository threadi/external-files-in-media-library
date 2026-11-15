<?php
/**
 * File to handle support for the plugin "Converter for Media".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle support for this plugin.
 *
 * It creates variants of the local files of uploads in other formats after the upload, so their export waits for it.
 */
class ConverterForMedia extends Image_Optimizer_Base {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ConverterForMedia
	 */
	private static ?ConverterForMedia $instance = null;

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
	 * @return ConverterForMedia
	 */
	public static function get_instance(): ConverterForMedia {
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
		return array( 'webp-converter-for-media/webp-converter-for-media.php' );
	}

	/**
	 * Return the possible paths of the variants of the given local file in other formats (path => mime type).
	 *
	 * Converter for Media saves them in its own directory "uploads-webpc" beside the uploads directory, with the
	 * structure below the content directory (e.g. "wp-content/uploads-webpc/uploads/2026/11/image.jpg.webp").
	 *
	 * @param string $path The absolute path of the file.
	 *
	 * @return array<string,string>
	 */
	protected function get_variant_paths( string $path ): array {
		// get the directories via the hooks of the plugin (relative to the root directory of WordPress).
		$root       = trailingslashit( wp_normalize_path( (string) apply_filters( 'webpc_site_root', ABSPATH ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$uploads    = trim( (string) apply_filters( 'webpc_dir_name', 'wp-content/uploads', 'uploads' ), '/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$output_dir = trim( (string) apply_filters( 'webpc_dir_name', 'wp-content/uploads-webpc', 'webp' ), '/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		// get the path relative to the parent directory of the uploads directory (e.g. "uploads/2026/11/image.jpg").
		$path   = wp_normalize_path( $path );
		$parent = $root . trailingslashit( dirname( $uploads ) );
		if ( ! str_starts_with( $path, $parent ) ) {
			return array();
		}
		$relative = substr( $path, strlen( $parent ) );

		// get the path in the directory of the plugin, also without the name of the uploads directory.
		$paths = array();
		foreach ( array( $relative, substr( $relative, strlen( wp_basename( $uploads ) ) + 1 ) ) as $part ) {
			$paths[ $root . $output_dir . '/' . $part . '.avif' ] = 'image/avif';
			$paths[ $root . $output_dir . '/' . $part . '.webp' ] = 'image/webp';
		}

		// return the possible paths.
		return $paths;
	}

}
