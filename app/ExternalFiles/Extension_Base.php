<?php
/**
 * File which provide the base functions for each file extension.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle different file types.
 */
class Extension_Base {
	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Run additional tasks during plugin installation.
	 *
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Run additional tasks during plugin uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {}
}
