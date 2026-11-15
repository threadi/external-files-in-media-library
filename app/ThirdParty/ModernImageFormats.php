<?php
/**
 * File to handle support for the plugin "Modern Image Formats" of the WordPress Performance Team.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Variants;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 *
 * The plugin creates the sizes of uploaded images in modern formats (WebP or AVIF) during the upload. Their files are
 * saved as sizes and exported like them. Optionally, it creates the image and its sizes additionally in these
 * formats ("sources" in the metadata): these variants are exported together with the image (see
 * ExternalFiles\Variants) and delivered via the proxy. The plugin does not change the HTML of exported images, as their
 * local files do not exist anymore.
 */
class ModernImageFormats extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?ModernImageFormats
	 */
	private static ?ModernImageFormats $instance = null;

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
	 * @return ModernImageFormats
	 */
	public static function get_instance(): ModernImageFormats {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not active.
		if ( ! Helper::is_plugin_active( 'webp-uploads/load.php' ) ) {
			return;
		}

		// use the hooks.
		$this->add_hooks();
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		add_filter( 'efml_image_variants', array( $this, 'add_variants' ), 10, 3 );
		add_filter( 'webp_uploads_content_image_mimes', array( $this, 'prevent_replacement_in_content' ), 10, 2 );
		add_filter( 'webp_uploads_picture_element_mime_types', array( $this, 'prevent_picture_element' ), 10, 2 );
	}

	/**
	 * Add the variants of the files of an image from the "sources" in its metadata.
	 *
	 * @param array<int,array<string,string>> $variants      The variants.
	 * @param int                             $attachment_id The attachment ID.
	 * @param array<string,string>            $paths         The absolute paths of the files (size name => path).
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_variants( array $variants, int $attachment_id, array $paths ): array {
		// get the metadata (unfiltered).
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );

		// bail if it is not usable.
		if ( ! is_array( $metadata ) ) {
			return $variants;
		}

		// check each file.
		foreach ( $paths as $size_name => $path ) {
			// get the sources of this file: of the image itself or of its size.
			if ( Variants::FULL === $size_name ) {
				$sources = $metadata['sources'] ?? array();
			} else {
				$sources = $metadata['sizes'][ $size_name ]['sources'] ?? array();
			}

			// bail if no sources exist.
			if ( ! is_array( $sources ) ) {
				continue;
			}

			// add each source in another file.
			foreach ( $sources as $mime_type => $source ) {
				// bail if it is not usable or the file itself.
				if ( ! is_array( $source ) || empty( $source['file'] ) || ! is_string( $source['file'] ) || wp_basename( $source['file'] ) === wp_basename( $path ) ) {
					continue;
				}

				// add it.
				$variants[] = array(
					'size'      => (string) $size_name,
					'path'      => trailingslashit( dirname( $path ) ) . wp_basename( $source['file'] ),
					'mime-type' => (string) $mime_type,
				);
			}
		}

		// return the list.
		return $variants;
	}

	/**
	 * Prevent the replacement of exported images in contents with their files in other formats, as these do not exist
	 * locally anymore. The proxy delivers the format the browser supports instead.
	 *
	 * @param mixed $mime_types    The mime types for the replacement.
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function prevent_replacement_in_content( mixed $mime_types, mixed $attachment_id ): mixed {
		// bail if this is not an exported image.
		if ( ! $this->is_exported( $attachment_id ) ) {
			return $mime_types;
		}

		// use only its own format.
		return array( (string) get_post_mime_type( absint( $attachment_id ) ) );
	}

	/**
	 * Prevent the picture element for exported images, as their files in other formats do not exist locally anymore.
	 *
	 * @param mixed $mime_types    The mime types for the picture element.
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function prevent_picture_element( mixed $mime_types, mixed $attachment_id ): mixed {
		// bail if this is not an exported image.
		if ( ! $this->is_exported( $attachment_id ) ) {
			return $mime_types;
		}

		// do not use the picture element.
		return array();
	}

	/**
	 * Return whether the given attachment is an exported file, whose files are not local.
	 *
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_exported( mixed $attachment_id ): bool {
		// bail if no ID is given.
		if ( ! is_numeric( $attachment_id ) || absint( $attachment_id ) <= 0 ) {
			return false;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( (int) $attachment_id );

		// return whether it is an external file without local files.
		return $external_file_obj->is_valid() && ! $external_file_obj->is_locally_saved();
	}
}
