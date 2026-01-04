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
 * Object to handle different file extensions.
 */
class Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = '';

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
	 * Return the object name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Hide this extension in settings.
	 *
	 * @return bool
	 */
	public function hide(): bool {
		return false;
	}
}
