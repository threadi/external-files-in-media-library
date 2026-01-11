<?php
/**
 * File, which holds the PHP-interface for each service.
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
	 * Run during uninstallation of the plugin.
	 *
	 * @return void
	 */
	public function uninstall(): void;

	/**
	 * Run WP CLI initialisation.
	 *
	 * @return void
	 */
	public function cli(): void;

	/**
	 * Return list of user settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get_user_settings(): array;
}
