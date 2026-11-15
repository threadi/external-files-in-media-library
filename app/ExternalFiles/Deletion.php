<?php
/**
 * File to delete exported files everywhere: on each external source and their local copies.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object to delete exported files everywhere.
 *
 * - On each external source the file has been exported to, independent of whether the export is enabled for it.
 * - With all its sizes and former files (e.g. before it has been edited).
 * - Files, which could not be deleted (e.g. the service is not available or answers with an error), are added to the
 *   queue and deleted later, also after the attachment has been deleted.
 * - Local copies of exported files are deleted together with the attachment.
 */
class Deletion {
	/**
	 * Prefixes of the post meta, which the services need to delete a file (e.g. its path or ID on the external source).
	 *
	 * @var array<int,string>
	 */
	private const META_PREFIXES = array( 'efml_', 'eml_' );

	/**
	 * Instance of actual object.
	 *
	 * @var ?Deletion
	 */
	private static ?Deletion $instance = null;

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
	 * @return Deletion
	 */
	public static function get_instance(): Deletion {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// delete the local copies (first, as the data of the export is removed afterward) and the files on the external
		// sources, also if the export is not enabled (anymore).
		add_action( 'delete_attachment', array( Export::get_instance(), 'delete_exported_file' ) );
		add_action( 'delete_attachment', array( $this, 'delete_local_files' ), 5 );
	}

	/**
	 * Delete the files of the given attachment on the given external source.
	 *
	 * Files, which could not be deleted, are added to the queue.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param int    $term_id       The ID of the external source.
	 * @param string $file_name     The name of the file on the external source.
	 *
	 * @return bool True if all files have been deleted.
	 */
	public function delete_on_source( int $attachment_id, int $term_id, string $file_name ): bool {
		// get the files of the attachment on this external source.
		$files = $this->get_files_on_source( $attachment_id, $term_id, $file_name );

		// delete them.
		$remaining = $this->delete_files( $attachment_id, $term_id, $files );

		// forget the files of the sizes on this external source: they are deleted or in the queue now.
		Variants::get_instance()->delete_cache( $attachment_id );
		Sizes::get_instance()->forget_source( $attachment_id, $term_id );

		// bail if all files have been deleted.
		if ( empty( $remaining ) ) {
			return true;
		}

		// add the remaining files to the queue, so they are deleted later.
		Queue::get_instance()->add_deletion( $attachment_id, $term_id, $remaining, $this->get_metas( $attachment_id ) );

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( '%1$d file could not be deleted on the external source now. It has been added to the queue and will be deleted later.', '%1$d files could not be deleted on the external source now. They have been added to the queue and will be deleted later.', count( $remaining ), 'external-files-in-media-library' ), count( $remaining ) ), (string) get_term_meta( $term_id, 'path', true ), 'info', 1 );

		// return false as not all files have been deleted.
		return false;
	}

	/**
	 * Return the files of the given attachment on the given external source: the file itself, its sizes and its
	 * former files.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param int    $term_id       The ID of the external source.
	 * @param string $file_name     The name of the file on the external source.
	 *
	 * @return array<int,array<string,string>> List of files (kind, file, url, target).
	 */
	private function get_files_on_source( int $attachment_id, int $term_id, string $file_name ): array {
		// get the folder, which has been used for this file on this external source.
		$folders = get_post_meta( $attachment_id, 'efml_export_folders', true );
		$folder  = is_array( $folders ) && ! empty( $folders[ $term_id ] ) && is_string( $folders[ $term_id ] ) ? trailingslashit( $folders[ $term_id ] ) : '';

		// add the file itself (with the URL the export objects expect for its deletion).
		$url   = trailingslashit( (string) get_term_meta( $term_id, 'path', true ) ) . $folder . $file_name;
		$files = array(
			array(
				'kind'   => 'file',
				'file'   => $file_name,
				'url'    => $url,
				'target' => $url,
			),
		);

		// add the sizes and the former files (each file once, as several sizes could use the same file).
		$data  = Sizes::get_instance()->get_data( $attachment_id );
		$known = array( $file_name );
		foreach ( array_merge( array_values( $data['terms'][ $term_id ] ?? array() ), array_values( $data['former'][ $term_id ] ?? array() ) ) as $size ) {
			// get the file name.
			$name = (string) ( $size['file'] ?? '' );

			// bail if it is known already.
			if ( '' === $name || in_array( $name, $known, true ) ) {
				continue;
			}
			$known[] = $name;

			// add it.
			$files[] = array(
				'kind'   => 'size',
				'file'   => $name,
				'url'    => (string) ( $size['url'] ?? '' ),
				'target' => (string) ( $size['target'] ?? '' ),
			);
		}

		// add the variants in other formats (e.g. WebP).
		foreach ( $data['variants'][ $term_id ] ?? array() as $variant ) {
			// get the file name.
			$name = (string) ( $variant['file'] ?? '' );

			// bail if it is known already.
			if ( '' === $name || in_array( $name, $known, true ) ) {
				continue;
			}
			$known[] = $name;

			// add it.
			$files[] = array(
				'kind'   => 'size',
				'file'   => $name,
				'url'    => (string) ( $variant['url'] ?? '' ),
				'target' => (string) ( $variant['target'] ?? '' ),
			);
		}

		// return the list.
		return $files;
	}

	/**
	 * Delete the given files of the given attachment on the given external source and return the remaining files.
	 *
	 * @param int                             $attachment_id The attachment ID.
	 * @param int                             $term_id       The ID of the external source.
	 * @param array<int,array<string,string>> $files         List of files (kind, file, url, target).
	 *
	 * @return array<int,array<string,string>> The files, which could not be deleted.
	 */
	public function delete_files( int $attachment_id, int $term_id, array $files ): array {
		// get the export object of the external source.
		$export_obj = $this->get_export_object( $term_id );

		// bail if it could not be used: all files remain.
		if ( ! $export_obj instanceof Export_Base ) {
			return $files;
		}

		// get the credentials.
		$credentials            = Taxonomy::get_instance()->get_entry( $term_id );
		$credentials['private'] = ! empty( get_post_meta( $attachment_id, 'efml_private_file', true ) );

		// delete each file.
		$remaining = array();
		foreach ( $files as $file ) {
			try {
				$deleted = 'file' === ( $file['kind'] ?? '' ) ? $export_obj->delete_exported_file( (string) $file['url'], $credentials, $attachment_id ) : $export_obj->delete_size( $attachment_id, (string) ( $file['url'] ?? '' ), (string) ( $file['target'] ?? '' ), $credentials );
			} catch ( \Throwable $e ) {
				/* translators: %1$s will be replaced by an error message. */
				Log::get_instance()->create( sprintf( __( 'Error during the deletion of an exported file: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $e->getMessage() ) . '</code>' ), (string) ( $file['url'] ?? '' ), 'error' );
				$deleted = false;
			}

			// remember it, if it could not be deleted.
			if ( ! $deleted ) {
				$remaining[] = $file;
				continue;
			}

			// log this event.
			Log::get_instance()->create( __( 'The exported file has been deleted.', 'external-files-in-media-library' ), (string) ( $file['url'] ?? '' ), 'info', 2 );
		}

		// return the remaining files.
		return $remaining;
	}

	/**
	 * Return the export object of the given external source, if it could be used to delete files.
	 *
	 * Unlike the export, it is not required that the export is enabled for the external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object( int $term_id ): Export_Base|false {
		// bail if the external source does not exist anymore.
		$term = get_term( $term_id, Taxonomy::get_instance()->get_name() );
		if ( ! $term instanceof \WP_Term ) {
			return false;
		}

		// bail if the credentials of its service are defined via constants for another server.
		if ( Services::get_instance()->is_source_blocked_by_constants( $term_id ) ) {
			return false;
		}

		// get the service object.
		$listing_obj = Export::get_instance()->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if the service is not available or does not support exports.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
			return false;
		}

		// return the export object.
		return $listing_obj->get_export_object();
	}

	/**
	 * Return the post meta of the given attachment, which the services need to delete its files later.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string>
	 */
	private function get_metas( int $attachment_id ): array {
		$metas = array();
		foreach ( (array) get_post_meta( $attachment_id ) as $key => $values ) {
			// bail if this is not a meta of this plugin or its services, or it contains credentials.
			if ( ! is_string( $key ) || ! $this->is_service_meta( $key ) || 'eml_fields' === $key || ! is_array( $values ) || ! isset( $values[0] ) || ! is_string( $values[0] ) ) {
				continue;
			}

			// bail on large values (e.g. the data of the sizes, which are saved in the entry itself).
			if ( strlen( $values[0] ) > 1024 ) {
				continue;
			}

			// add it.
			$metas[ $key ] = $values[0];
		}
		return $metas;
	}

	/**
	 * Return whether the given meta key belongs to this plugin or its services.
	 *
	 * @param string $key The meta key.
	 *
	 * @return bool
	 */
	private function is_service_meta( string $key ): bool {
		foreach ( self::META_PREFIXES as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Delete the given files of a deleted attachment from the queue and return the remaining files.
	 *
	 * The services read data of the attachment (e.g. the path of the file on the external source) from its post meta,
	 * which has been deleted together with the attachment. Missing post meta is added for this moment.
	 *
	 * @param int                             $attachment_id The ID of the deleted attachment.
	 * @param int                             $term_id       The ID of the external source.
	 * @param array<int,array<string,string>> $files         List of files (kind, file, url, target).
	 * @param array<string,string>            $metas         The post meta of the attachment.
	 *
	 * @return array<int,array<string,string>>|false The files, which could not be deleted, or false, if the external
	 *                                               source could not be used at the moment.
	 */
	public function delete_files_of_deleted_attachment( int $attachment_id, int $term_id, array $files, array $metas ): array|false {
		// bail if the external source could not be used at the moment.
		if ( ! $this->get_export_object( $term_id ) instanceof Export_Base ) {
			return false;
		}

		// add the missing post meta for this moment (e.g. the attachment has been deleted or restored as local file).
		$added = array();
		foreach ( $metas as $key => $value ) {
			if ( $this->is_service_meta( $key ) && '' === get_post_meta( $attachment_id, $key, true ) && add_post_meta( $attachment_id, $key, $value ) ) {
				$added[] = $key;
			}
		}

		// delete the files.
		$remaining = $this->delete_files( $attachment_id, $term_id, $files );

		// remove the post meta again.
		foreach ( $added as $key ) {
			delete_post_meta( $attachment_id, $key );
		}

		// return the remaining files.
		return $remaining;
	}

	/**
	 * Delete the local copies of an exported file, if it is deleted.
	 *
	 * WordPress does not know them anymore, as the attached file is the external URL. These are the copies kept after
	 * the export ("Do not delete local files"), their sizes, the original image and the backups of the image editor.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function delete_local_files( int $attachment_id ): void {
		// bail during the uninstallation or the reset of this plugin.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an exported file or it is saved locally (WordPress deletes its files then).
		if ( ! $external_file_obj->is_valid() || $external_file_obj->is_locally_saved() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			return;
		}

		// get the local paths of the file (the file itself, its original image and former files).
		$paths = Local_Urls::get_instance()->get_local_paths( $attachment_id );

		// bail if they are unknown.
		if ( empty( $paths ) ) {
			return;
		}

		// get the local directory.
		$base_dir  = trailingslashit( wp_get_upload_dir()['basedir'] );
		$directory = dirname( $paths[0] );
		$directory = '.' === $directory ? '' : trailingslashit( $directory );

		// collect the names of the files in this directory.
		$names = array_map( 'wp_basename', $paths );
		$names = array_merge( $names, array_values( Local_Urls::get_instance()->get_local_sizes( $attachment_id ) ) );
		foreach ( array( '_wp_attachment_metadata', '_wp_attachment_backup_sizes' ) as $meta_key ) {
			$meta = get_post_meta( $attachment_id, $meta_key, true );
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$entries = '_wp_attachment_metadata' === $meta_key ? ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() ) : $meta;
			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['file'] ) && is_string( $entry['file'] ) ) {
					$names[] = wp_basename( $entry['file'] );
				}
			}
			if ( '_wp_attachment_metadata' === $meta_key && ! empty( $meta['original_image'] ) && is_string( $meta['original_image'] ) ) {
				$names[] = wp_basename( $meta['original_image'] );
			}
		}
		$data = Sizes::get_instance()->get_data( $attachment_id );
		foreach ( array_merge( $data['terms'], $data['former'] ) as $files ) {
			$names = array_merge( $names, wp_list_pluck( (array) $files, 'file' ) );
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// delete each existing file, which is not used by another file in the media library.
		$deleted = 0;
		foreach ( array_unique( array_filter( array_map( 'strval', $names ) ) ) as $name ) {
			// get the path.
			$path = $directory . $name;

			// bail if the path is not valid or the file does not exist.
			if ( str_contains( $path, '..' ) || 0 !== validate_file( $path ) || ! $wp_filesystem->exists( $base_dir . $path ) ) {
				continue;
			}

			// bail if it is the file of another attachment.
			if ( $this->is_used_by_other_attachment( $path, $attachment_id ) ) {
				continue;
			}

			// delete it.
			wp_delete_file( $base_dir . $path );
			++$deleted;
		}

		// delete the local copies of the variants in other formats (e.g. WebP, also outside the uploads directory).
		foreach ( Variants::get_instance()->get_local_paths( $attachment_id ) as $path ) {
			if ( $wp_filesystem->is_file( $path ) && ! $this->is_used_by_other_attachment( _wp_relative_upload_path( $path ), $attachment_id ) ) {
				wp_delete_file( $path );
				++$deleted;
			}
		}

		// log this event.
		if ( $deleted > 0 ) {
			/* translators: %1$d will be replaced by a number. */
			Log::get_instance()->create( sprintf( _n( '%1$d local copy of the exported file has been deleted.', '%1$d local copies of the exported file have been deleted.', $deleted, 'external-files-in-media-library' ), $deleted ), $external_file_obj->get_url( true ), 'info', 2 );
		}
	}

	/**
	 * Return whether the given path relative to the uploads directory is the attached file of another attachment.
	 *
	 * @param string $path          The path.
	 * @param int    $attachment_id The ID of the attachment, which is deleted.
	 *
	 * @return bool
	 */
	private function is_used_by_other_attachment( string $path, int $attachment_id ): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s AND `post_id` != %d LIMIT 1', $wpdb->postmeta, '_wp_attached_file', $path, $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}
}
