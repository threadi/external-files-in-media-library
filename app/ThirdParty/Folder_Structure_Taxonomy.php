<?php
/**
 * File with the functions for folder plugins, whose folders are terms of a hierarchical taxonomy.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Functions of Folder_Structure_Interface for folder plugins, whose folders are terms of a hierarchical taxonomy for
 * attachments. The folders are created with wp_insert_term() and assigned with wp_set_object_terms().
 *
 * @since 6.1.0 Available since 6.1.0.
 */
trait Folder_Structure_Taxonomy {
	/**
	 * Return the name of the taxonomy of the folders.
	 *
	 * @return string
	 */
	abstract protected function get_folder_taxonomy(): string;

	/**
	 * Return whether the taxonomy of the folders exists and is hierarchical.
	 *
	 * @return bool
	 */
	protected function is_folder_taxonomy_usable(): bool {
		return taxonomy_exists( $this->get_folder_taxonomy() ) && is_taxonomy_hierarchical( $this->get_folder_taxonomy() );
	}

	/**
	 * Return the ID of the folder with the given name in the given parent folder; the folder is created, if it does not
	 * exist.
	 *
	 * @param int    $parent_id The ID of the parent folder (0 for the top level).
	 * @param string $name      The name of the folder.
	 *
	 * @return int The ID of the folder, 0 on errors.
	 */
	public function get_or_create_folder( int $parent_id, string $name ): int {
		// bail if the taxonomy is not usable.
		if ( ! $this->is_folder_taxonomy_usable() ) {
			return 0;
		}

		// use an existing folder with this name in the parent folder.
		$existing = get_terms(
			array(
				'taxonomy'   => $this->get_folder_taxonomy(),
				'parent'     => $parent_id,
				'name'       => $name,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
			)
		);
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return absint( $existing[0] );
		}

		// create the folder.
		$result = wp_insert_term( $name, $this->get_folder_taxonomy(), array( 'parent' => absint( $parent_id ) ) );

		// use the existing folder, if WordPress reports it as existing.
		if ( $result instanceof WP_Error ) {
			return 'term_exists' === $result->get_error_code() ? absint( $result->get_error_data() ) : 0;
		}

		// get the ID of the new folder.
		$folder_id = absint( $result['term_id'] );

		// run tasks of the plugin for new folders.
		$this->after_folder_created( $folder_id, $parent_id );

		// return the ID of the new folder.
		return $folder_id;
	}

	/**
	 * Run tasks after a folder has been created (e.g. to save data, which the plugin saves for its folders).
	 *
	 * @param int $folder_id The ID of the new folder.
	 * @param int $parent_id The ID of its parent folder.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	protected function after_folder_created( int $folder_id, int $parent_id ): void {}

	/**
	 * Return whether the folder with the given ID exists.
	 *
	 * @param int $folder_id The ID of the folder.
	 *
	 * @return bool
	 */
	public function folder_exists( int $folder_id ): bool {
		return $folder_id > 0 && $this->is_folder_taxonomy_usable() && ! empty( term_exists( $folder_id, $this->get_folder_taxonomy() ) );
	}

	/**
	 * Assign the given file to the given folder (only to this folder of the taxonomy).
	 *
	 * @param int $attachment_id The ID of the attachment.
	 * @param int $folder_id     The ID of the folder.
	 *
	 * @return void
	 */
	public function assign_file( int $attachment_id, int $folder_id ): void {
		wp_set_object_terms( $attachment_id, array( $folder_id ), $this->get_folder_taxonomy() );
	}

	/**
	 * Return the target folder from the given list of chosen folders: only a single folder could be used.
	 *
	 * @param mixed $folders The chosen folders (IDs as keys or as values).
	 * @param bool  $as_keys True if the IDs are the keys of the list.
	 *
	 * @return int 0 if no folder is chosen, -1 if more than one folder is chosen.
	 */
	protected function get_single_folder( mixed $folders, bool $as_keys ): int {
		// no folder is chosen.
		if ( empty( $folders ) || ! is_array( $folders ) ) {
			return 0;
		}

		// get the IDs.
		$ids = array_values( array_filter( array_map( 'absint', $as_keys ? array_keys( $folders ) : $folders ) ) );

		// return the folder, if exactly one is chosen.
		if ( 1 === count( $ids ) ) {
			return $ids[0];
		}

		// return 0 for no and -1 for multiple folders.
		return empty( $ids ) ? 0 : -1;
	}
}
