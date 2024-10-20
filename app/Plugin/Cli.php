<?php
/**
 * This file contains WP CLI options for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Handler for cli-commands.
 *
 * @noinspection PhpUnused
 */
class Cli {

	/**
	 * Import as parameter given external urls in the media library.
	 *
	 * @param array $urls Array of URLs which might be given as parameter on CLI-command.
	 * @param array $arguments List of parameter to use for the given URLs.
	 *
	 * @return void
	 */
	public function import( array $urls = array(), array $arguments = array() ): void {
		// bail if no urls are given.
		if ( empty( $urls ) ) {
			\WP_CLI::error( 'Please add one or more URL as parameters.' );
			return;
		}

		// array for the list of results.
		$results = array();

		// get the files object.
		$external_files_obj = Files::get_instance();
		$external_files_obj->set_login( ! empty( $arguments['login'] ) ? sanitize_text_field( wp_unslash( $arguments['login'] ) ) : '' );
		$external_files_obj->set_password( ! empty( $arguments['password'] ) ? sanitize_text_field( wp_unslash( $arguments['password'] ) ) : '' );

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Import files from given URL', count( $urls ) );

		// check each single given url.
		foreach ( $urls as $url ) {
			// bail if URL is empty.
			if ( empty( $url ) ) {
				continue;
			}

			// bail if given string is not a URL.
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$results[] = $url . ' is not a valid URL and will not be saved.';
				continue;
			}

			// try to add this URL as single file.
			if ( $external_files_obj->add_from_url( $url ) ) {
				$results[] = $url . ' has been saved in media library.';
			} else {
				$results[] = $url . ' could not be saved in media library. Take a look in the log for details.';
			}

			// show progress.
			$progress->tick();
		}

		// finish progress.
		$progress->finish();

		// show results.
		\WP_CLI::success( "Results of the import:\n" . implode( "\n", $results ) );
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
		$external_files_obj = Files::get_instance();

		// get all files created by this plugin in media library.
		$files = $external_files_obj->get_files_in_media_library();

		// bail if no files found.
		if ( empty( $files ) ) {
			\WP_CLI::error( 'There are no external urls to delete.' );
			return;
		}

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Delete external files from media library', count( $files ) );

		// loop through the files and delete them.
		foreach ( $files as $external_file_obj ) {
			// bail if this is not an external file object.
			if ( ! $external_file_obj instanceof External_File ) {
				continue;
			}

			// delete the file.
			$external_files_obj->delete_file( $external_file_obj );

			// show progress.
			$progress->tick();
		}

		// finish progress.
		$progress->finish();

		// show resulting message.
		\WP_CLI::success( count( $files ) . ' URLs has been deleted.' );
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
		$external_files_obj = Files::get_instance();

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

	/**
	 * Switch hosting of all files to external (except for those with credentials or un-supported protocols).
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function switch_to_external(): void {
		// get external files object.
		$external_files_obj = Files::get_instance();

		// switch hosting of files to local if option is enabled for it.
		foreach ( $external_files_obj->get_files_in_media_library() as $external_file_obj ) {
			// bail if this is not an external file object.
			if ( ! $external_file_obj instanceof File ) {
				continue;
			}

			// bail if file is already external hosted.
			if ( ! $external_file_obj->is_locally_saved() ) {
				continue;
			}

			// switch the hosting of this file to external.
			$external_file_obj->switch_to_external();
		}

		// return ok-message.
		\WP_CLI::success( 'Files has been switches to external hosting.' );
	}

	/**
	 * Switch hosting of all files to local.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function switch_to_local(): void {
		// get external files object.
		$external_files_obj = Files::get_instance();

		// switch hosting of files to local if option is enabled for it.
		foreach ( $external_files_obj->get_files_in_media_library() as $external_file_obj ) {
			// bail if this is not an external file object.
			if ( ! $external_file_obj instanceof File ) {
				continue;
			}

			// bail if file is already local hosted.
			if ( $external_file_obj->is_locally_saved() ) {
				continue;
			}

			// switch the hosting of this file to local.
			$external_file_obj->switch_to_local();
		}

		// return ok-message.
		\WP_CLI::success( 'Files has been switches to local hosting.' );
	}
}
