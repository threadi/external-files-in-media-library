<?php
/**
 * This file contains the linking of local files of a website with files of the same content in its central media
 * library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Multisite;

/**
 * Object to link local files of a website with the files of the same content in its central media library during the
 * synchronization, instead of importing these files again.
 *
 * Happens e.g. if a website joins a group with files, which the central media library has already (like a former
 * central media library after a move). The local attachment keeps its ID, so its usage in contents is kept.
 */
class Linking {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Linking
	 */
	private static ?Linking $instance = null;

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
	 * @return Linking
	 */
	public static function get_instance(): Linking {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Link the local files of the actual website with the given files of its central media library, which have the
	 * same content, and return the files, which have not been linked.
	 *
	 * @param array<int,array<string,mixed>> $listing The files of the central media library to import.
	 * @param int                            $central The website, from which the files come.
	 * @param int                            $term_id The external source of the central media library.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function link_local_files( array $listing, int $central, int $term_id ): array {
		// bail if the list is empty, if the files do not come from the central media library of this website or if
		// files with the same content should not be used.
		if ( empty( $listing ) || Media_Library::get_instance()->get_central_site_id() !== $central || ! Media_Library::get_instance()->is_using_central() || 1 !== (int) Media_Library::get_instance()->get_setting( 'dedupe' ) ) {
			return $listing;
		}

		// get the local files of this website by their content.
		$local_files = $this->get_local_files();

		// bail if there are none.
		if ( empty( $local_files ) ) {
			return $listing;
		}

		// link each file of the central media library with a local file of the same content.
		$remaining = array();
		foreach ( $listing as $entry ) {
			$hash = (string) ( $entry['efml-central-hash'] ?? '' );
			if ( '' !== $hash && ! empty( $local_files[ $hash ] ) ) {
				$local_id = array_shift( $local_files[ $hash ] );
				if ( $this->link( $local_id, $entry, $term_id ) ) {
					continue;
				}
			}
			$remaining[] = $entry;
		}

		// return the files, which should be imported.
		return $remaining;
	}

	/**
	 * Return the local files of the actual website as lists of attachment IDs per hash of their content.
	 *
	 * The hash of files without it is saved first.
	 *
	 * @return array<string,array<int,int>>
	 */
	private function get_local_files(): array {
		global $wpdb;

		// get the local files: attachments, which are not external files.
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i AS p LEFT JOIN %i AS u ON u.`post_id` = p.`ID` AND u.`meta_key` = %s WHERE p.`post_type` = %s AND u.`meta_id` IS NULL ORDER BY p.`ID` ASC', array( $wpdb->posts, $wpdb->postmeta, EFML_POST_META_URL, 'attachment' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// collect them by their hash.
		$files = array();
		foreach ( $ids as $attachment_id ) {
			// save the hash, if it does not exist (a missing file gets an empty hash).
			if ( ! metadata_exists( 'post', $attachment_id, Media_Library::META_HASH ) && ! Media_Library::get_instance()->update_file_info( $attachment_id ) ) {
				update_post_meta( $attachment_id, Media_Library::META_HASH, '' );
			}

			// add it.
			$hash = (string) get_post_meta( $attachment_id, Media_Library::META_HASH, true );
			if ( '' !== $hash ) {
				$files[ $hash ][] = $attachment_id;
			}
		}

		// return the list.
		return $files;
	}

	/**
	 * Link the given local file of the actual website with the given file of its central media library.
	 *
	 * The attachment keeps its ID. Its caption, description and alternative text are kept as own values of this
	 * website, if they differ.
	 *
	 * @param int                 $local_id The attachment ID of the local file.
	 * @param array<string,mixed> $entry    The data of the file of the central media library.
	 * @param int                 $term_id  The external source of the central media library.
	 *
	 * @return bool
	 */
	public function link( int $local_id, array $entry, int $term_id ): bool {
		// get the data of the central file.
		$url        = (string) ( $entry['url'] ?? '' );
		$central_id = absint( $entry['efml-central-id'] ?? 0 );
		if ( '' === $url || 0 === $central_id ) {
			return false;
		}

		// keep the own values of caption, description and alternative text, which differ.
		$central_values = isset( $entry['efml-central-values'] ) && is_array( $entry['efml-central-values'] ) ? $entry['efml-central-values'] : array();
		$overrides      = array();
		foreach ( Metadata::get_instance()->get_values( $local_id ) as $field => $value ) {
			$central_value = (string) ( $central_values[ $field ] ?? '' );
			if ( '' !== $value && $central_value !== $value ) {
				$overrides[ $field ] = $value;
			}
		}

		// it is not an own upload of this website, so its deletion here does not delete the central file.
		update_post_meta( $local_id, Media_Library::META_LINKED, 1 );
		update_post_meta( $local_id, Media_Library::META_CENTRAL_ID, $central_id );
		update_post_meta( $local_id, 'efml_multisite_attachment_id', $central_id );
		if ( ! empty( $overrides ) ) {
			update_post_meta( $local_id, Media_Library::META_OVERRIDES, $overrides );
		}

		// use the file of the central media library (like an exported file).
		Export::get_instance()->save_as_exported( Files::get_instance()->get_file( $local_id ), $url, Multisite::get_instance()->get_name(), basename( $url ), false, array(), $term_id );
		wp_set_object_terms( $local_id, $term_id, Taxonomy::get_instance()->get_name() );

		// the next synchronization skips it, if it is unchanged.
		if ( ! empty( $entry['efml-stamp'] ) ) {
			update_post_meta( $local_id, Multisite::META_STAMP, (string) $entry['efml-stamp'] );
		}

		// mark it as synchronized, so the synchronization does not delete it.
		$external_file_obj = Files::get_instance()->get_file( $local_id );
		if ( $external_file_obj->is_valid() ) {
			Synchronization::get_instance()->mark_as_synced( $external_file_obj );
		}

		// log this event.
		Log::get_instance()->create( __( 'Linked the local file with the file of the same content in the central media library.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// return success.
		return true;
	}
}
