<?php
/**
 * File which provide the base functions for each ThirdParty-plugin we support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle different ThirdParty-plugin-tasks.
 */
class ThirdParty_Base {
	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Tasks to run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {}
}
