<?php
/**
 * File, which provide the base functions for each file extension.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle different file extensions.
 */
class Extension_Base extends Tools_Base {
	/**
	 * The extension type.
	 *
	 * @var string
	 */
	protected string $extension_type = '';

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

	/**
	 * Return the type from this extension.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->extension_type;
	}
}
