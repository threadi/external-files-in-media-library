<?php
/**
 * File to handle variants of images in other formats (e.g. WebP and AVIF), which image optimizers create.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle variants of images in other formats.
 *
 * Image optimizers (e.g. Imagify, EWWW, ShortPixel, Converter for Media, Modern Image Formats) create files of the
 * image and its sizes in other formats beside them. Their integrations (see ThirdParty) name them via the hook
 * "efml_image_variants". They are exported together with the image, deleted with it, restored with it and delivered
 * via the proxy instead of the image, if the browser supports their format (same URL, "Vary: Accept").
 *
 * They are saved in the post meta of the sizes (Sizes::META) under "variants": term ID => file name => data (file,
 * of, size, mime-type, url, target, local).
 */
class Variants {
	/**
	 * Key of the image itself in the list of base files.
	 *
	 * @var string
	 */
	public const FULL = 'full';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Variants
	 */
	private static ?Variants $instance = null;

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
	 * @return Variants
	 */
	public static function get_instance(): Variants {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the formats, which could be used as variants (mime type => file extension).
	 *
	 * The order is the order of the delivery: the first format the browser supports is used.
	 *
	 * @return array<string,string>
	 */
	public function get_formats(): array {
		$formats = array(
			'image/avif' => 'avif',
			'image/webp' => 'webp',
		);

		/**
		 * Filter the formats, which could be used as variants of images (mime type => file extension).
		 *
		 * The order is the order of the delivery via the proxy: the first format the browser supports is used.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,string> $formats The formats.
		 */
		return (array) apply_filters( 'efml_image_variant_formats', $formats );
	}

	/**
	 * Return the local variants of the given local files of an image.
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param string               $directory     The local directory of the files, with trailing slash.
	 * @param array<string,string> $files         The files (size name or "full" for the image => file name).
	 *
	 * @return array<string,array<string,string>> File name of the variant => data (file, of, size, mime-type, kind, path).
	 */
	public function get_local_variants( int $attachment_id, string $directory, array $files ): array {
		// get the absolute paths of the files.
		$paths = array();
		foreach ( $files as $size_name => $file ) {
			if ( '' !== $file ) {
				$paths[ (string) $size_name ] = $directory . $file;
			}
		}

		// bail if no files are given.
		if ( empty( $paths ) ) {
			return array();
		}

		/**
		 * Filter the variants of an image in other formats (e.g. WebP or AVIF), which exist locally.
		 *
		 * Each entry names the size (or "full" for the image itself), the absolute local path of the variant and its
		 * mime type. Only existing files in the content directory of WordPress in the formats of the hook
		 * "efml_image_variant_formats" are used. Entries with "kind" => "backup" are backups of the plugin (e.g. of
		 * the image before its optimization) in any image format: they are exported, deleted and restored together
		 * with the image, but never delivered. The optional "name" sets the name on the external source, e.g. for
		 * backups with the same name as the file of the image in another directory.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,array{size: string, path: string, mime-type: string, kind?: string, name?: string}> $variants      The variants.
		 * @param int                                                             $attachment_id The attachment ID.
		 * @param array<string,string>                                            $paths         The absolute paths of the files of the image (size name => path).
		 */
		$variants = (array) apply_filters( 'efml_image_variants', array(), $attachment_id, $paths );

		// bail if no variants are given.
		if ( empty( $variants ) ) {
			return array();
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the allowed formats and the content directory.
		$formats     = $this->get_formats();
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// check each variant.
		$result = array();
		foreach ( $variants as $variant ) {
			// bail if the data is not usable.
			$variant = (array) $variant;
			if ( empty( $variant['size'] ) || empty( $variant['path'] ) || empty( $variant['mime-type'] ) ) {
				continue;
			}

			// get the data.
			$size_name = (string) $variant['size'];
			$path      = wp_normalize_path( (string) $variant['path'] );
			$mime_type = (string) $variant['mime-type'];
			$kind      = 'backup' === ( $variant['kind'] ?? '' ) ? 'backup' : 'format';

			// bail if its image is unknown or it is the file of the image itself.
			if ( ! isset( $paths[ $size_name ] ) || in_array( $path, array_map( 'wp_normalize_path', $paths ), true ) ) {
				continue;
			}

			// bail if its format is not allowed: formats for the delivery, backups as images.
			if ( 'format' === $kind ? ! isset( $formats[ $mime_type ] ) : ! str_starts_with( $mime_type, 'image/' ) ) {
				continue;
			}

			// bail if it is not located in the content directory or contains relative parts.
			if ( ! str_starts_with( $path, $content_dir ) || str_contains( $path, '../' ) || 0 !== validate_file( substr( $path, strlen( $content_dir ) ) ) ) {
				continue;
			}

			// bail if it does not exist.
			if ( ! $wp_filesystem->is_file( $path ) ) {
				continue;
			}

			// bail if it is the file of another attachment in the media library.
			if ( $this->is_file_of_other_attachment( $path, $attachment_id ) ) {
				continue;
			}

			// get its name on the external source: its own name or the given name.
			$name = ! empty( $variant['name'] ) ? sanitize_file_name( wp_basename( (string) $variant['name'] ) ) : wp_basename( $path );

			// bail if this name is the name of a file of the image (e.g. a backup with the same name in another directory).
			if ( in_array( $name, array_map( 'wp_basename', $paths ), true ) ) {
				continue;
			}

			// add it (each file once).
			$result[ $name ] = array(
				'file'      => $name,
				'of'        => wp_basename( $paths[ $size_name ] ),
				'size'      => $size_name,
				'mime-type' => $mime_type,
				'kind'      => $kind,
				'path'      => $path,
			);
		}

		// return the list.
		return $result;
	}

	/**
	 * Return whether the given local file is the file of another attachment in the media library.
	 *
	 * @param string $path          The absolute path.
	 * @param int    $attachment_id The attachment ID of the image.
	 *
	 * @return bool
	 */
	private function is_file_of_other_attachment( string $path, int $attachment_id ): bool {
		global $wpdb;

		// get the path relative to the uploads directory.
		$relative = _wp_relative_upload_path( $path );

		// bail if it is not located in the uploads directory.
		if ( $relative === $path ) {
			return false;
		}

		// search for it.
		$post_id = $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s AND `post_id` != %d LIMIT 1', $wpdb->postmeta, '_wp_attached_file', $relative, $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// return the result.
		return ! empty( $post_id );
	}

	/**
	 * Export the local variants of the given files of an image to the given external source.
	 *
	 * Variants, which could not be exported, stay local and are exported again later together with the sizes.
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param Export_Base          $export_obj    The export object of the service.
	 * @param int                  $term_id       The ID of the external source.
	 * @param string               $directory     The local directory of the files, with trailing slash.
	 * @param string               $target_dir    The target directory, with trailing slash.
	 * @param array<string,mixed>  $credentials   The credentials of the external source.
	 * @param array<string,string> $files         The files (size name or "full" for the image => file name).
	 * @param string               $target_name   The name of the image on the external source, if it differs from its local name.
	 *
	 * @return int The number of exported variants.
	 */
	public function export( int $attachment_id, Export_Base $export_obj, int $term_id, string $directory, string $target_dir, array $credentials, array $files, string $target_name = '' ): int {
		// get the local variants.
		$variants = $this->get_local_variants( $attachment_id, $directory, $files );

		// bail if none exist.
		if ( empty( $variants ) ) {
			return 0;
		}

		// export each of them.
		$exported = array();
		foreach ( $variants as $variant ) {
			// get the name on the external source: like the image, if it got another name there.
			$name = $variant['file'];
			if ( self::FULL === $variant['size'] && '' !== $target_name && $target_name !== $variant['of'] ) {
				$name = $this->get_renamed_file( $name, $variant['of'], $target_name );
			}
			$target = $target_dir . $name;

			// export it.
			try {
				$url = $export_obj->export_size( $attachment_id, $variant['path'], $target, $credentials );
			} catch ( \Throwable $e ) {
				/* translators: %1$s will be replaced by an error message. */
				Log::get_instance()->create( sprintf( __( 'Error during the export of a variant of the image: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $e->getMessage() ) . '</code>' ), $target, 'error' );
				$url = false;
			}

			// bail if it could not be exported: it stays local and is exported again later.
			if ( ! is_string( $url ) || '' === $url ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The variant %1$s of the image could not be exported. It stays local and is exported again later.', 'external-files-in-media-library' ), '<code>' . esc_html( $variant['file'] ) . '</code>' ), $target, 'error' );
				Sizes::get_instance()->retry_later( $attachment_id );
				continue;
			}

			// add it to the list.
			$exported[ $name ] = array(
				'file'      => $name,
				'of'        => self::FULL === $variant['size'] && '' !== $target_name ? $target_name : $variant['of'],
				'size'      => $variant['size'],
				'mime-type' => $variant['mime-type'],
				'kind'      => $variant['kind'],
				'url'       => $url,
				'target'    => $target,
				'local'     => $this->get_relative_path( $variant['path'] ),
			);
		}

		// bail if none has been exported.
		if ( empty( $exported ) ) {
			return 0;
		}

		// save them.
		$this->save( $attachment_id, $term_id, $exported );

		// let local URLs of the variants in contents use the image (the proxy delivers the variant to the browser).
		foreach ( $exported as $variant ) {
			if ( 'backup' === $variant['kind'] ) {
				continue;
			}
			$relative = _wp_relative_upload_path( WP_CONTENT_DIR . '/' . $variant['local'] );
			if ( ! str_starts_with( $relative, '/' ) && $relative !== $variant['local'] ) {
				Local_Urls::get_instance()->add_local_path( $attachment_id, $relative );
			}
		}

		// log this event.
		/* translators: %1$d will be replaced by a number, %2$s by a list of file names. */
		Log::get_instance()->create( sprintf( __( '%1$d variants of the image in other formats have been exported: %2$s', 'external-files-in-media-library' ), count( $exported ), '<code>' . esc_html( implode( ', ', array_keys( $exported ) ) ) . '</code>' ), $target_dir, 'info', 2 );

		// return the number of exported variants.
		return count( $exported );
	}

	/**
	 * Update the variants of an image on the given external source after its files have been changed (e.g. its
	 * sizes have been created again, it has been edited or renamed).
	 *
	 * New local variants are exported. Variants of files, which are not used anymore, are kept on the external source
	 * (like the former sizes), or, if they are not kept (e.g. after a renaming), they are renamed like their file.
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param Export_Base          $export_obj    The export object of the service.
	 * @param int                  $term_id       The ID of the external source.
	 * @param string               $directory     The local directory of the files, with trailing slash.
	 * @param string               $target_dir    The target directory, with trailing slash.
	 * @param array<string,mixed>  $credentials   The credentials of the external source.
	 * @param array<string,string> $files         The new files (size name or "full" for the image => file name).
	 * @param bool                 $keep_replaced True to keep variants of files, which are not used anymore.
	 *
	 * @return int The number of uploaded variants.
	 */
	public function update( int $attachment_id, Export_Base $export_obj, int $term_id, string $directory, string $target_dir, array $credentials, array $files, bool $keep_replaced ): int {
		// export the new local variants.
		$uploaded = $this->export( $attachment_id, $export_obj, $term_id, $directory, $target_dir, $credentials, $files );

		// bail if variants of replaced files are kept.
		if ( $keep_replaced ) {
			return $uploaded;
		}

		// get the actual variants on this external source.
		$data     = Sizes::get_instance()->get_data( $attachment_id );
		$variants = $data['variants'][ $term_id ] ?? array();

		// check each of them.
		foreach ( $variants as $name => $variant ) {
			// bail if its file is still used.
			if ( in_array( (string) ( $variant['of'] ?? '' ), $files, true ) ) {
				continue;
			}

			// get the size of the variant.
			$size_name = (string) ( $variant['size'] ?? '' );

			// bail if it is a variant of the image itself, which has not been changed.
			if ( self::FULL === $size_name && ! isset( $files[ self::FULL ] ) ) {
				continue;
			}

			// get the new file of its size.
			$new_of = $files[ $size_name ] ?? '';

			// rename it like its file, if a variant with the new name does not exist.
			$new_name = '' !== $new_of ? $this->get_renamed_file( (string) $name, (string) ( $variant['of'] ?? '' ), $new_of ) : '';
			if ( '' !== $new_name && ! isset( $variants[ $new_name ] ) && $this->copy_on_source( $attachment_id, $export_obj, $term_id, $credentials, $variant, $new_name, $new_of, $target_dir ) ) {
				++$uploaded;
			}

			// delete the variant of the former file.
			try {
				$deleted = $export_obj->delete_size( $attachment_id, (string) ( $variant['url'] ?? '' ), (string) ( $variant['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				$deleted = false;
			}
			if ( ! $deleted ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The exported variant %1$s could not be deleted (maybe it does not exist).', 'external-files-in-media-library' ), '<code>' . esc_html( (string) $name ) . '</code>' ), (string) ( $variant['target'] ?? '' ), 'error' );
			}

			// remove it from the list.
			$this->forget( $attachment_id, $term_id, (string) $name );
		}

		// return the number of uploaded variants.
		return $uploaded;
	}

	/**
	 * Copy the given exported variant under the given new name on the external source (e.g. after its image has
	 * been renamed).
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param Export_Base         $export_obj    The export object of the service.
	 * @param int                 $term_id       The ID of the external source.
	 * @param array<string,mixed> $credentials   The credentials of the external source.
	 * @param array<string,mixed> $variant       The exported variant.
	 * @param string              $new_name      The new name of the variant.
	 * @param string              $new_of        The new name of its file.
	 * @param string              $target_dir    The target directory, with trailing slash.
	 *
	 * @return bool
	 */
	private function copy_on_source( int $attachment_id, Export_Base $export_obj, int $term_id, array $credentials, array $variant, string $new_name, string $new_of, string $target_dir ): bool {
		// download it.
		$tmp_file = $this->download( $attachment_id, (string) ( $variant['url'] ?? '' ) );

		// bail if it could not be downloaded.
		if ( '' === $tmp_file ) {
			return false;
		}

		// upload it with its new name.
		$target = $target_dir . $new_name;
		try {
			$url = $export_obj->export_size( $attachment_id, $tmp_file, $target, $credentials );
		} catch ( \Throwable $e ) {
			$url = false;
		}

		// remove the temporary file.
		wp_delete_file( $tmp_file );

		// bail if it could not be uploaded.
		if ( ! is_string( $url ) || '' === $url ) {
			/* translators: %1$s will be replaced by a file name. */
			Log::get_instance()->create( sprintf( __( 'The variant %1$s of the image could not be exported with its new name.', 'external-files-in-media-library' ), '<code>' . esc_html( $new_name ) . '</code>' ), $target, 'error' );
			return false;
		}

		// get its new local path.
		$local = (string) ( $variant['local'] ?? '' );
		$local = '' !== $local ? trailingslashit( dirname( $local ) ) . $new_name : '';

		// save it.
		$this->save(
			$attachment_id,
			$term_id,
			array(
				$new_name => array(
					'file'      => $new_name,
					'of'        => $new_of,
					'size'      => (string) ( $variant['size'] ?? '' ),
					'mime-type' => (string) ( $variant['mime-type'] ?? '' ),
					'kind'      => (string) ( $variant['kind'] ?? 'format' ),
					'url'       => $url,
					'target'    => $target,
					'local'     => $local,
				),
			)
		);

		// return true as it has been copied.
		return true;
	}

	/**
	 * Return the name of the given variant for a renamed file (e.g. "new.jpg.webp" for "old.jpg.webp").
	 *
	 * Returns an empty string, if the name of the variant does not follow the name of its file.
	 *
	 * @param string $name     The name of the variant.
	 * @param string $old_file The former name of its file.
	 * @param string $new_file The new name of its file.
	 *
	 * @return string
	 */
	public function get_renamed_file( string $name, string $old_file, string $new_file ): string {
		// the name of the file with an added extension (e.g. "image.jpg.webp").
		if ( '' !== $old_file && str_starts_with( $name, $old_file ) ) {
			return $new_file . substr( $name, strlen( $old_file ) );
		}

		// the name of the file without its extension (e.g. "image.webp" or "image-jpg.webp").
		$old_base = pathinfo( $old_file, PATHINFO_FILENAME );
		if ( '' !== $old_base && str_starts_with( $name, $old_base ) ) {
			return pathinfo( $new_file, PATHINFO_FILENAME ) . substr( $name, strlen( $old_base ) );
		}

		// return an empty string, as the name does not follow the file.
		return '';
	}

	/**
	 * Save the given exported variants of an image for the given external source.
	 *
	 * @param int                               $attachment_id The attachment ID.
	 * @param int                               $term_id       The ID of the external source.
	 * @param array<string,array<string,mixed>> $variants      The variants (file name => data).
	 *
	 * @return void
	 */
	private function save( int $attachment_id, int $term_id, array $variants ): void {
		// get the actual data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// add the variants.
		$data['variants'][ $term_id ] = array_merge( $data['variants'][ $term_id ] ?? array(), $variants );

		// save them.
		Sizes::get_instance()->save_data( $attachment_id, $data );
	}

	/**
	 * Remove the given variant of an image on the given external source from its data.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param int    $term_id       The ID of the external source.
	 * @param string $name          The name of the variant.
	 *
	 * @return void
	 */
	private function forget( int $attachment_id, int $term_id, string $name ): void {
		// get the actual data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// remove the variant.
		unset( $data['variants'][ $term_id ][ $name ] );
		if ( empty( $data['variants'][ $term_id ] ) ) {
			unset( $data['variants'][ $term_id ] );
		}

		// save the data.
		Sizes::get_instance()->save_data( $attachment_id, $data );
	}

	/**
	 * Return the exported variants of the given image on its main external source (file name => data).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_variants( int $attachment_id ): array {
		// get the data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// return the variants of the main external source.
		return $data['variants'][ $data['main'] ] ?? array();
	}

	/**
	 * Return whether the given image has exported variants.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function has_variants( int $attachment_id ): bool {
		return ! empty( $this->get_variants( $attachment_id ) );
	}

	/**
	 * Return the variant of the given file of an image, which should be delivered to the actual browser.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $size_name     The size name or "full" for the image itself.
	 * @param string $file          The name of the file of this size on the external source (used for sizes).
	 *
	 * @return array<string,mixed> The variant or an empty array, if the browser should get the file itself.
	 */
	public function get_variant_for_browser( int $attachment_id, string $size_name, string $file = '' ): array {
		// get the formats the browser supports.
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) ) : '';

		// bail if the browser does not name any format.
		if ( '' === $accept ) {
			return array();
		}

		// get the variants of this file by their format.
		$by_format = array();
		foreach ( $this->get_variants( $attachment_id ) as $variant ) {
			// bail if it is a backup (e.g. of the original before its optimization), which is never delivered.
			if ( 'backup' === ( $variant['kind'] ?? '' ) ) {
				continue;
			}

			// bail if it is the variant of another file.
			if ( self::FULL === $size_name ? self::FULL !== ( $variant['size'] ?? '' ) : ( (string) ( $variant['of'] ?? '' ) !== $file || '' === $file ) ) {
				continue;
			}

			// add it.
			$by_format[ (string) ( $variant['mime-type'] ?? '' ) ] = $variant;
		}

		// use the first format, which the browser supports.
		foreach ( array_keys( $this->get_formats() ) as $mime_type ) {
			if ( isset( $by_format[ $mime_type ] ) && $this->is_accepted( $accept, $mime_type ) ) {
				return $by_format[ $mime_type ];
			}
		}

		// return an empty array as no variant could be used.
		return array();
	}

	/**
	 * Deliver the variant of the requested file of an image via the proxy, if the browser supports its format.
	 *
	 * Ends the request, if a variant has been delivered. Otherwise, the proxy delivers the file itself, with the
	 * header "Vary: Accept", as the same URL could deliver another format to other browsers.
	 *
	 * @param File                  $external_file_obj The external file object of the image.
	 * @param array<int,int|string> $dimensions        The requested dimensions of a size (empty for the image itself).
	 *
	 * @return void
	 */
	public function deliver( File $external_file_obj, array $dimensions ): void {
		// bail if the image has no variants.
		if ( ! $this->has_variants( $external_file_obj->get_id() ) ) {
			return;
		}

		// the URL could deliver other formats, which caches must respect.
		if ( ! headers_sent() ) {
			header( 'Vary: Accept' );
		}

		// get the requested file: the image itself or a size.
		$size_name = self::FULL;
		$file      = '';
		if ( ! empty( $dimensions ) ) {
			$size = Sizes::get_instance()->find_by_dimensions( $external_file_obj->get_id(), $dimensions );
			if ( empty( $size['file'] ) ) {
				return;
			}
			$size_name = 'size';
			$file      = (string) $size['file'];
		}

		// get the variant for this browser.
		$variant = $this->get_variant_for_browser( $external_file_obj->get_id(), $size_name, $file );

		// bail if the browser should get the file itself.
		if ( empty( $variant ) ) {
			return;
		}

		// load it into the cache.
		$path = $this->cache( $external_file_obj, $variant );

		// bail if it could not be loaded: the file itself is delivered.
		if ( '' === $path ) {
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'Proxy delivers the variant of the image in the format supported by the browser.', 'external-files-in-media-library' ), (string) ( $variant['url'] ?? '' ), 'info', 2 );

		// output it.
		$this->output( $path, (string) ( $variant['mime-type'] ?? '' ), (string) ( $variant['file'] ?? '' ) );
	}

	/**
	 * Return whether the given Accept header allows the given mime type.
	 *
	 * @param string $accept    The Accept header in lower case.
	 * @param string $mime_type The mime type.
	 *
	 * @return bool
	 */
	private function is_accepted( string $accept, string $mime_type ): bool {
		foreach ( explode( ',', $accept ) as $part ) {
			// get the type and its parameters.
			$params = array_map( 'trim', explode( ';', $part ) );
			if ( array_shift( $params ) !== $mime_type ) {
				continue;
			}

			// bail if it is excluded via "q=0".
			foreach ( $params as $param ) {
				if ( preg_match( '/^q=0(\.0*)?$/', $param ) ) {
					return false;
				}
			}

			// return true as it is accepted.
			return true;
		}

		// return false as it is not named.
		return false;
	}

	/**
	 * Return the path of the given variant in the cache of the proxy.
	 *
	 * @param array<string,mixed> $variant The variant.
	 *
	 * @return string
	 */
	public function get_cache_file( array $variant ): string {
		// get the extension of its format.
		$formats   = $this->get_formats();
		$extension = $formats[ (string) ( $variant['mime-type'] ?? '' ) ] ?? (string) pathinfo( (string) ( $variant['file'] ?? '' ), PATHINFO_EXTENSION );

		// return the path.
		return Proxy::get_instance()->get_cache_directory() . md5( (string) ( $variant['url'] ?? '' ) ) . '.' . $extension;
	}

	/**
	 * Load the given variant into the cache of the proxy.
	 *
	 * @param File                $external_file_obj The external file object of the image.
	 * @param array<string,mixed> $variant           The variant.
	 *
	 * @return string The path in the cache or an empty string on error.
	 */
	public function cache( File $external_file_obj, array $variant ): string {
		// get the cache file.
		$cache_file = $this->get_cache_file( $variant );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// bail if it is already cached.
		if ( $wp_filesystem->exists( $cache_file ) ) {
			return $cache_file;
		}

		// download it.
		$tmp_file = $this->download( $external_file_obj->get_id(), (string) ( $variant['url'] ?? '' ) );

		// bail if it could not be downloaded.
		if ( '' === $tmp_file ) {
			Log::get_instance()->create( __( 'The exported variant of the image could not be loaded for the proxy. The image is delivered in its own format.', 'external-files-in-media-library' ), (string) ( $variant['url'] ?? '' ), 'error' );
			return '';
		}

		// move it into the cache.
		wp_mkdir_p( dirname( $cache_file ) );
		if ( ! $wp_filesystem->move( $tmp_file, $cache_file, true ) ) {
			$wp_filesystem->delete( $tmp_file );
			return '';
		}

		// return the path.
		return $cache_file;
	}

	/**
	 * Download the given exported file of the given image into a temporary file.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $url           The URL of the exported file.
	 *
	 * @return string The path of the temporary file or an empty string on error.
	 */
	private function download( int $attachment_id, string $url ): string {
		// bail if no URL is given.
		if ( '' === $url ) {
			return '';
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get the protocol handler of the file.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if it could not be loaded.
		if ( ! $protocol_handler_obj ) {
			return '';
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// download the file.
		add_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );
		try {
			$tmp_file = $protocol_handler_obj->get_temp_file( $url, $wp_filesystem );
		} catch ( \Throwable $e ) {
			$tmp_file = false;
		}
		remove_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );

		// bail if it could not be downloaded or is no image.
		if ( ! is_string( $tmp_file ) || ! $wp_filesystem->exists( $tmp_file ) || 0 === absint( wp_filesize( $tmp_file ) ) || ! $this->is_image( $tmp_file ) ) {
			if ( is_string( $tmp_file ) ) {
				$wp_filesystem->delete( $tmp_file );
			}
			return '';
		}

		// return the path.
		return $tmp_file;
	}

	/**
	 * Return whether the given file is an image (not e.g. the error page of an expired link).
	 *
	 * @param string $file The absolute path of the file.
	 *
	 * @return bool
	 */
	private function is_image( string $file ): bool {
		// use the detection of WordPress.
		if ( false !== wp_get_image_mime( $file ) ) {
			return true;
		}

		// check the signature of AVIF files, which older versions of PHP do not detect.
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return false;
		}
		$header = (string) fread( $handle, 12 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// return whether it is an AVIF file.
		return 'ftyp' === substr( $header, 4, 4 ) && in_array( substr( $header, 8, 4 ), array( 'avif', 'avis' ), true );
	}

	/**
	 * Output the given file of the proxy cache and end the request.
	 *
	 * @param string $path      The absolute path of the file.
	 * @param string $mime_type The mime type of the file.
	 * @param string $file_name The name of the file for the browser.
	 *
	 * @return void
	 */
	public function output( string $path, string $mime_type, string $file_name ): void {
		// get the file name for the header.
		$file_name = sanitize_file_name( $file_name );
		if ( '' === $file_name ) {
			$file_name = 'image';
		}
		$file_name_ascii = str_replace( array( '"', '\\' ), '_', (string) preg_replace( '/[^\x20-\x7E]/', '_', $file_name ) );

		// send the headers.
		header( 'Content-Type: ' . $mime_type );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Vary: Accept' );
		header( 'Content-Disposition: inline; filename="' . $file_name_ascii . '"; filename*=UTF-8\'\'' . rawurlencode( $file_name ) );
		header( 'Content-Length: ' . (string) wp_filesize( $path ) );

		// output the file, if this is not a HEAD request.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'HEAD' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		// end the request.
		exit;
	}

	/**
	 * Delete the local files of the exported variants of the given image (after its export).
	 *
	 * Variants, which could not be exported, stay local, so they could be exported later.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function delete_local_files( int $attachment_id ): void {
		// bail if local files should be kept.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			return;
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// delete each local file of the exported variants.
		foreach ( $this->get_local_paths( $attachment_id ) as $path ) {
			if ( $wp_filesystem->is_file( $path ) && ! $this->is_file_of_other_attachment( $path, $attachment_id ) ) {
				$wp_filesystem->delete( $path );
			}
		}
	}

	/**
	 * Return the absolute local paths of the exported variants of the given image on all its external sources.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	public function get_local_paths( int $attachment_id ): array {
		// get the data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// collect the paths.
		$paths       = array();
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		foreach ( $data['variants'] as $variants ) {
			foreach ( (array) $variants as $variant ) {
				$local = (string) ( $variant['local'] ?? '' );
				if ( '' !== $local && 0 === validate_file( $local ) ) {
					$paths[] = $content_dir . $local;
				}
			}
		}

		// return the list.
		return array_values( array_unique( $paths ) );
	}

	/**
	 * Return the given absolute path relative to the content directory of WordPress.
	 *
	 * @param string $path The absolute path.
	 *
	 * @return string
	 */
	private function get_relative_path( string $path ): string {
		// get the content directory.
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		// return the relative path.
		$path = wp_normalize_path( $path );
		return str_starts_with( $path, $content_dir ) ? substr( $path, strlen( $content_dir ) ) : '';
	}

	/**
	 * Save the exported backups of the given image at their local paths and return these paths (e.g. for the work with
	 * the local files of the image, so other plugins could restore the image from them).
	 *
	 * Existing local files are not overwritten and not returned.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string> The absolute paths of the saved backups (name of the variant => path).
	 */
	public function fetch_backups( int $attachment_id ): array {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// save each backup of the main external source.
		$paths       = array();
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		foreach ( $this->get_variants( $attachment_id ) as $name => $variant ) {
			// bail if this is not a backup.
			if ( 'backup' !== ( $variant['kind'] ?? '' ) ) {
				continue;
			}

			// get the local path.
			$local = (string) ( $variant['local'] ?? '' );
			if ( '' === $local || 0 !== validate_file( $local ) ) {
				continue;
			}
			$path = $content_dir . $local;

			// bail if it exists.
			if ( $wp_filesystem->exists( $path ) ) {
				continue;
			}

			// download it.
			$tmp_file = $this->download( $attachment_id, (string) ( $variant['url'] ?? '' ) );
			if ( '' === $tmp_file ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The variant %1$s of the image could not be restored.', 'external-files-in-media-library' ), '<code>' . esc_html( (string) $name ) . '</code>' ), (string) ( $variant['url'] ?? '' ), 'error' );
				continue;
			}

			// move it to its local path.
			wp_mkdir_p( dirname( $path ) );
			if ( ! $wp_filesystem->move( $tmp_file, $path, true ) ) {
				$wp_filesystem->delete( $tmp_file );
				continue;
			}
			$paths[ (string) $name ] = $path;
		}

		// return the list.
		return $paths;
	}

	/**
	 * Delete the given exported variant of an image on each external source and remove it from its data (e.g. a
	 * backup, which another plugin has removed after it restored the image from it).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $name          The name of the variant.
	 *
	 * @return void
	 */
	public function remove( int $attachment_id, string $name ): void {
		// get the data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// delete it on each external source.
		foreach ( $data['variants'] as $term_id => $variants ) {
			// bail if it does not exist there.
			if ( ! isset( $variants[ $name ] ) ) {
				continue;
			}
			$variant = (array) $variants[ $name ];

			// get the export object.
			$export_obj = Deletion::get_instance()->get_export_object( absint( $term_id ) );

			// delete it.
			$deleted = false;
			if ( $export_obj instanceof Export_Base ) {
				$credentials            = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( absint( $term_id ) );
				$credentials['private'] = Files::get_instance()->get_file( $attachment_id )->is_private();
				$credentials['term_id'] = absint( $term_id );
				try {
					$deleted = $export_obj->delete_size( $attachment_id, (string) ( $variant['url'] ?? '' ), (string) ( $variant['target'] ?? '' ), $credentials );
				} catch ( \Throwable $e ) {
					$deleted = false;
				}
			}

			// keep it in the data, if it could not be deleted (it is deleted together with the image then).
			if ( ! $deleted ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The exported variant %1$s could not be deleted (maybe it does not exist).', 'external-files-in-media-library' ), '<code>' . esc_html( $name ) . '</code>' ), (string) ( $variant['target'] ?? '' ), 'error' );
				continue;
			}

			// remove it from the data.
			$this->forget( $attachment_id, absint( $term_id ), $name );
		}
	}

	/**
	 * Save the exported variants of the given image at their original local paths (e.g. during its restore).
	 *
	 * Existing local files are not overwritten.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return int The number of saved variants.
	 */
	public function restore( File $external_file_obj ): int {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// save each variant of the main external source.
		$restored    = 0;
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		foreach ( $this->get_variants( $external_file_obj->get_id() ) as $variant ) {
			// get the local path.
			$local = (string) ( $variant['local'] ?? '' );
			if ( '' === $local || 0 !== validate_file( $local ) ) {
				continue;
			}
			$path = $content_dir . $local;

			// bail if it exists.
			if ( $wp_filesystem->exists( $path ) ) {
				continue;
			}

			// download it.
			$tmp_file = $this->download( $external_file_obj->get_id(), (string) ( $variant['url'] ?? '' ) );
			if ( '' === $tmp_file ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The variant %1$s of the image could not be restored.', 'external-files-in-media-library' ), '<code>' . esc_html( (string) ( $variant['file'] ?? '' ) ) . '</code>' ), (string) ( $variant['url'] ?? '' ), 'error' );
				continue;
			}

			// move it to its local path.
			wp_mkdir_p( dirname( $path ) );
			if ( ! $wp_filesystem->move( $tmp_file, $path, true ) ) {
				$wp_filesystem->delete( $tmp_file );
				continue;
			}
			++$restored;
		}

		// return the number of saved variants.
		return $restored;
	}

	/**
	 * Remove the variants of the given image from the cache of the proxy.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function delete_cache( int $attachment_id ): void {
		// get the data.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// delete the cache file of each variant.
		foreach ( $data['variants'] as $variants ) {
			foreach ( (array) $variants as $variant ) {
				$cache_file = $this->get_cache_file( (array) $variant );
				if ( file_exists( $cache_file ) ) {
					wp_delete_file( $cache_file );
				}
			}
		}
	}
}
