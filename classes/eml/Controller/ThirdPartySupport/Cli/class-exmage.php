<?php
/**
 * This file extends the CLI commands with the migration task for Exmage-files.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller\ThirdPartySupport\Cli;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Handler for cli-commands.
 *
 * @noinspection PhpUnused
 */
class Exmage extends \threadi\eml\Controller\Cli {
	/**
	 * Migrate files from Exmage to our plugin.
	 *
	 * @return void
	 */
	public function migrate_exmage(): void {
		\threadi\eml\Controller\ThirdPartySupport\Exmage::get_instance()->migrate();
	}
}
