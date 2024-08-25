<?php
/**
 * This file contains WP CLI options for this plugin.
 *
 * @package thread\eml
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Model\log;

/**
 * Handler for cli-commands.
 *
 * @noinspection PhpUnused
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
class Cli {

	/**
	 * Import as parameter given external urls in the media library.
	 *
	 * @param array $urls   Array of urls which might be given as parameter on CLI-command.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function import( array $urls = array() ): void {
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$file_obj = External_Files::get_instance();
				if ( $file_obj->add_file( $url ) ) {
					\WP_CLI::success( $url . ' has been saved in media library.' );
				} else {
					\WP_CLI::error( $url . ' could not be saved in media library.' );
				}
			}
		} else {
			\WP_CLI::error( 'No urls given.' );
		}
	}

	/**
	 * Delete all urls in media library which are imported by this plugin.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function delete(): void {
		// get log-object to log this action.
		$logs = Log::get_instance();
		$logs->create( 'All external files will be deleted via cli.', '', 'success', 2 );

		// get external files object.
		$external_files_obj = External_Files::get_instance();

		// get all files created by this plugin in media library.
		$files = $external_files_obj->get_files_in_media_library();
		if ( ! empty( $files ) ) {
			// loop through the files and delete them.
			foreach ( $files as $external_file_obj ) {
				$external_files_obj->delete_file( $external_file_obj );
			}
			\WP_CLI::success( count( $files ) . ' URLs has been deleted.' );
		} else {
			\WP_CLI::error( 'There are no external urls to delete.' );
		}
	}

	/**
	 * Cleanup complete log.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function clear_log(): void {
		// get log-object.
		$logs = Log::get_instance();

		// truncate all log-entries.
		$logs->truncate_log();

		// log what we have done.
		$logs->create( 'Log has been cleared via cli.', '', 'success', 2 );
	}

	/**
	 * Check all URLs in the media library.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 * @noinspection PhpUndefinedClassInspection
	 */
	public function check(): void {
		// get object for external files.
		$external_files_obj = External_Files::get_instance();

		// run check for all files.
		$external_files_obj->check_files();

		// return ok-message.
		\WP_CLI::success( 'URL-check has been run.' );
	}

	/**
	 * Reset the plugin as it will be de- and reinstalled.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function reset_plugin(): void {
		// run actions as if the plugin is deactivated.
		$init = Install::get_instance();
		$init->deactivation();

		// uninstall everything from the plugin.
		$uninstaller = Uninstall::get_instance();
		$uninstaller->run();

		// run actions as if the plugin is activated.
		$init->activation();

		// log resetting via cli.
		$logs = Log::get_instance();
		$logs->create( 'Plugin has been reset via cli.', '', 'success', 2 );

		// return ok-message.
		\WP_CLI::success( 'Plugin has been reset.' );
	}
}
