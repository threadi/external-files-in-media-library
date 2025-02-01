<?php
/**
 * File which holds the interface for each service.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface for each supported ThirdParty.
 */
interface ThirdParty {
	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void;

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void;
}
