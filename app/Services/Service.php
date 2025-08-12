<?php
/**
 * File which holds the interface for each service.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Interface for each supported service.
 */
interface Service {
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

	/**
	 * Run WP CLI initialisation.
	 *
	 * @return void
	 */
	public function cli(): void;
}
