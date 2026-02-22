<?php
/**
 * File, which provides the PHP-interface for each file extension object.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object for the PHP-interface for each file extension object.
 */
interface Extension_Interface {
	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Run additional tasks during plugin installation.
	 *
	 * @return void
	 */
	public function install(): void;

	/**
	 * Run additional tasks during plugin uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void;

	/**
	 * Return the types from this extension.
	 *
	 * @return array<int,string>
	 */
	public function get_types(): array;
}
