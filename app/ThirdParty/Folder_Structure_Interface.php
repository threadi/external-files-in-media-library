<?php
/**
 * File with the interface for folder plugins, which could take over the structure of subdirectories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface for the integrations of folder plugins, which could rebuild the structure of the subdirectories of an
 * external source during its synchronization (see \ExternalFilesInMediaLibrary\ExternalFiles\Folder_Structure).
 *
 * @since 6.1.0 Available since 6.1.0.
 */
interface Folder_Structure_Interface {
	/**
	 * Return the internal name of this integration (used for the saved folders).
	 *
	 * @return string
	 */
	public function get_folder_structure_name(): string;

	/**
	 * Return the title of the folder plugin.
	 *
	 * @return string
	 */
	public function get_folder_structure_title(): string;

	/**
	 * Return whether the folder plugin is active and its functions to create folders are available.
	 *
	 * @return bool
	 */
	public function is_folder_structure_available(): bool;

	/**
	 * Return the folder of the given external source, under which the structure is created.
	 *
	 * Returns 0 for the top level and -1, if the structure could not be used for this external source (e.g. if
	 * more than one folder is chosen).
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return int
	 */
	public function get_target_folder( int $term_id ): int;

	/**
	 * Return the ID of the folder with the given name in the given parent folder; the folder is created, if it does not
	 * exist.
	 *
	 * @param int    $parent_id The ID of the parent folder (0 for the top level).
	 * @param string $name      The name of the folder.
	 *
	 * @return int The ID of the folder, 0 on errors.
	 */
	public function get_or_create_folder( int $parent_id, string $name ): int;

	/**
	 * Return whether the folder with the given ID exists.
	 *
	 * @param int $folder_id The ID of the folder.
	 *
	 * @return bool
	 */
	public function folder_exists( int $folder_id ): bool;

	/**
	 * Assign the given file to the given folder (only to this folder of the plugin).
	 *
	 * @param int $attachment_id The ID of the attachment.
	 * @param int $folder_id     The ID of the folder.
	 *
	 * @return void
	 */
	public function assign_file( int $attachment_id, int $folder_id ): void;
}
