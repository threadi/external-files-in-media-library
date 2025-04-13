<?php
/**
 * This file extends the CLI commands with the migration task for Exmage-files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty\Cli;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for cli-commands.
 *
 * @noinspection PhpUnused
 */
class Exmage {
	/**
	 * Migrate files from Exmage to our plugin.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function migrate_exmage(): void {
		\ExternalFilesInMediaLibrary\ThirdParty\Exmage::get_instance()->migrate();
	}
}
