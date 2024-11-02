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
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

/**
 * Handle external files via WP CLI.
 *
 * @noinspection PhpUnused
 */
class Cli {

	/**
	 * Import given URL(s) in the media library.
	 *
	 * <URLs>
	 * : List of URLs to import in media library.
	 *
	 * [--login=<value>]
	 * : Set authentication login to use for any added URL.
	 *
	 * [--password=<value>]
	 * : Set authentication password to use for any added URL.
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
	 * [<URLs>]
	 * : List of URLs to delete from in media library. If nothing is given all external files are deleted.
	 *
	 * @param array $urls List of URLs.
	 *
	 * @return void
	 */
	public function delete( array $urls = array() ): void {
		// get log-object to log this action.
		$logs = Log::get_instance();
		$logs->create( 'All external files will be deleted via WP CLI.', '', 'success', 2 );

		// get external files object.
		$external_files_obj = Files::get_instance();

		$files_to_delete = array();
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$external_file_obj = $external_files_obj->get_file_by_url( $url );

				// bail if object could not be loaded.
				if ( ! $external_file_obj ) {
					continue;
				}

				// add to list.
				$files_to_delete[] = $external_file_obj;
			}
		} else {
			// get all files created by this plugin in media library.
			$files_to_delete = $external_files_obj->get_files_in_media_library();
		}

		// bail if no files found.
		if ( empty( $files_to_delete ) ) {
			$logs->create( 'No files found to delete.', '', 'success', 2 );
			\WP_CLI::error( 'There are no external URLs to delete.' );
			return;
		}

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( 'Delete external files from media library', count( $files_to_delete ) );

		// loop through the files and delete them.
		foreach ( $files_to_delete as $external_file_obj ) {
			// bail if this is not an external file object.
			if ( ! $external_file_obj instanceof File ) {
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
		$logs->create( count( $files_to_delete ) . ' URLs has been deleted.', '', 'success', 2 );
		\WP_CLI::success( count( $files_to_delete ) . ' URLs has been deleted.' );
	}

	/**
	 * Cleanup the plugin-own log.
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
	 * [<URLs>]
	 * : List of URLs to check. They must exist in media library.
	 *
	 * @param array $urls List of URLs.
	 *
	 * @return void
	 */
	public function check( array $urls = array() ): void {
		// get object for external files.
		$external_files_obj = Files::get_instance();

		// check the given files.
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				// get the file object for this URL.
				$external_file_obj = $external_files_obj->get_file_by_url( $url );

				// bail if it is not existent.
				if ( ! $external_file_obj ) {
					continue;
				}

				// bail if it is not valid.
				if ( ! $external_file_obj->is_valid() ) {
					continue;
				}

				// get the protocol handler for this URL.
				$protocol_handler = Protocols::get_instance()->get_protocol_object_for_external_file( $external_file_obj );

				// bail if handler is false.
				if ( ! $protocol_handler ) {
					continue;
				}

				// run the check for this file.
				$external_file_obj->set_availability( $protocol_handler->check_availability( $external_file_obj->get_url() ) );
			}
		} else {
			// run check for all files.
			$external_files_obj->check_files();
		}

		// return ok-message.
		\WP_CLI::success( 'URL-check has been run.' );
	}

	/**
	 * Reset the plugin. It will be de- and re-installed.
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
	 * Switch hosting of files to external.
	 *
	 * Except for those with credentials or un-supported protocols.
	 * If no URLs given all external files will be switched.
	 *
	 * [<URLs>]
	 * : List of URLs for switch.
	 *
	 * @param array $urls List of URLs.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function switch_to_external( array $urls = array() ): void {
		// get external files object.
		$external_files_obj = Files::get_instance();

		// get files depending on arguments.
		$files = array();
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$files[] = $external_files_obj->get_file_by_url( $url );
			}
		} else {
			// get all files.
			$files = $external_files_obj->get_files_in_media_library();
		}

		// switch hosting of files to local if option is enabled for it.
		foreach ( $files as $external_file_obj ) {
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
	 * Switch hosting of files to local.
	 *
	 * Except for those with credentials or un-supported protocols.
	 *  If no URLs given all external files will be switched.
	 *
	 * [<URLs>]
	 * : List of URLs for switch.
	 *
	 * @param array $urls List of URLs.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function switch_to_local( array $urls = array() ): void {
		// get external files object.
		$external_files_obj = Files::get_instance();

		// get files depending on arguments.
		$files = array();
		if ( ! empty( $urls ) ) {
			foreach ( $urls as $url ) {
				$files[] = $external_files_obj->get_file_by_url( $url );
			}
		} else {
			// get all files.
			$files = $external_files_obj->get_files_in_media_library();
		}

		// switch hosting of files to local if option is enabled for it.
		foreach ( $files as $external_file_obj ) {
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
