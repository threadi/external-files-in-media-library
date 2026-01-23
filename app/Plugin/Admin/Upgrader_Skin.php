<?php
/**
 * Git Updater
 *
 * @author   Andy Fragen
 * @license  GPL-3.0-or-later
 * @link     https://github.com/afragen/git-updater
 * @package  git-updater
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Upgrader_Skin;

// import required files.
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Object for custom upgrader skin to prevent output of any messages and log them instead.
 */
class Upgrader_Skin extends WP_Upgrader_Skin {
	/**
	 * Do nothing.
	 *
	 * @param string $feedback The message.
	 * @param mixed  ...$args Array of arguments.
	 */
	public function feedback( $feedback, ...$args ): void {}

	/**
	 * Do nothing.
	 *
	 * @param mixed $errors Error messages.
	 */
	public function error( $errors ): void {}

	/**
	 * Do nothing.
	 *
	 * @param mixed $type The update type with should be decremented. E.g., 'plugin', 'theme', 'translation'.
	 */
	protected function decrement_update_count( $type ): void {}

	/**
	 * Do nothing.
	 */
	public function header(): void {}

	/**
	 * Do nothing.
	 */
	public function footer(): void {}
}
