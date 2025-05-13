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
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Queue;

/**
 * Handle external files via WP CLI.
 *
 * @noinspection PhpUnused
 */
class Cli {

	/**
	 * Import given URL(s) in the media library.
	 *
	 * <URL>
	 * : URL to import in media library.
	 *
	 * [--login=<value>]
	 * : Set authentication login to use for any added URL.
	 *
	 * [--password=<value>]
	 * : Set authentication password to use for any added URL.
	 *
	 * [--queue]
	 * : Adds the given URL(s) to the queue.
	 *
	 * @param array<string,string> $urls Array with URL which might be given as parameter on CLI-command.
	 * @param array<string,string> $arguments List of parameter to use for the given URLs.
	 *
	 * @return void
	 */
	public function import( array $urls = array(), array $arguments = array() ): void {
		// array for the list of results.
		$results = array();

		// get the import object.
		$import = Import::get_instance();
		$import->set_login( ! empty( $arguments['login'] ) ? sanitize_text_field( wp_unslash( $arguments['login'] ) ) : '' );
		$import->set_password( ! empty( $arguments['password'] ) ? sanitize_text_field( wp_unslash( $arguments['password'] ) ) : '' );

		// get the queue settings.
		$add_to_queue = ! empty( $arguments['queue'] );

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( _n( 'Import files from given URL', 'Import files from given URLs', count( $urls ), 'external-files-in-media-library' ), count( $urls ) );

		// check each single given url.
		foreach ( $urls as $url ) {
			// bail if URL is empty.
			if ( empty( $url ) ) {
				// show progress.
				$progress->tick();

				// bail this URL.
				continue;
			}

			// convert spaces in URL to HTML-coded spaces.
			$url = str_replace( ' ', '%20', $url );

			// bail if given string is not a URL.
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				/* translators: %1$s will be replaced by URL. */
				$results[] = sprintf( __( '%1$s is not a valid URL and will not be saved.', 'external-files-in-media-library' ), $url );

				// show progress.
				$progress->tick();

				// bail this URL.
				continue;
			}

			// try to add this URL as single file.
			if ( $import->add_url( $url, $add_to_queue ) ) {
				/* translators: %1$s will be replaced by URL. */
				$results[] = sprintf( __( '%1$s has been saved in media library.', 'external-files-in-media-library' ), $url );
			} elseif ( $add_to_queue ) {
					/* translators: %1$s will be replaced by URL. */
					$results[] = sprintf( __( '%1$s has been added to the queue. It will be imported automatically in your media library.', 'external-files-in-media-library' ), $url );
			} else {
				/* translators: %1$s will be replaced by URL. */
				$results[] = sprintf( __( '%1$s could not be saved in media library. Take a look in the log for details.', 'external-files-in-media-library' ), $url );
			}

			// show progress.
			$progress->tick();
		}

		// finish progress.
		$progress->finish();

		// show results.
		\WP_CLI::success( __( 'Results of the import:', 'external-files-in-media-library' ) . "\n" . implode( "\n", $results ) );
	}

	/**
	 * Delete all URLs in media library which are imported by this plugin.
	 *
	 * [<URLs>]
	 * : List of URLs to delete from in media library. If nothing is given all external files are deleted.
	 *
	 * @param array<string> $urls List of URLs.
	 *
	 * @return void
	 */
	public function delete( array $urls = array() ): void {
		// get log-object to log this action.
		$logs = Log::get_instance();
		$logs->create( __( 'External files will be deleted via WP CLI.', 'external-files-in-media-library' ), '', 'success', 2 );

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
			$files_to_delete = $external_files_obj->get_files();
		}

		// bail if no files found.
		if ( empty( $files_to_delete ) ) {
			$logs->create( __( 'No files found to delete.', 'external-files-in-media-library' ), '', 'success', 2 );
			\WP_CLI::error( __( 'There are no external URLs to delete.', 'external-files-in-media-library' ) );
		}

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( __( 'Deleting external files from media library', 'external-files-in-media-library' ), count( $files_to_delete ) );

		// loop through the files and delete them.
		foreach ( $files_to_delete as $external_file_obj ) {
			// delete the file.
			$external_file_obj->delete();

			// show progress.
			$progress->tick();
		}

		// finish progress.
		$progress->finish();

		// show resulting message.
		/* translators: %1$d is replaced by a number. */
		$logs->create( sprintf( __( '%1$d URLs have been deleted.', 'external-files-in-media-library' ), count( $files_to_delete ) ), '', 'success', 2 );
		/* translators: %1$d is replaced by a number. */
		\WP_CLI::success( sprintf( __( '%1$d URLs have been deleted.', 'external-files-in-media-library' ), count( $files_to_delete ) ) );
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
		$logs->create( __( 'Log has been cleared via cli.', 'external-files-in-media-library' ), '', 'success', 2 );
	}

	/**
	 * Check all URLs in the media library.
	 *
	 * [<URLs>]
	 * : List of URLs to check. They must exist in media library.
	 *
	 * @param array<string> $urls List of URLs.
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
				$protocol_handler = $external_file_obj->get_protocol_handler_obj();

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
		\WP_CLI::success( __( 'URL-check has been run.', 'external-files-in-media-library' ) );
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
		$logs->create( __( 'Plugin have been reset via cli.', 'external-files-in-media-library' ), '', 'success', 2 );

		// return ok-message.
		\WP_CLI::success( __( 'Plugin have been reset.', 'external-files-in-media-library' ) );
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
	 * @param array<string> $urls List of URLs.
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
			$files = $external_files_obj->get_files();
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
		\WP_CLI::success( __( 'Files have been switches to external hosting.', 'external-files-in-media-library' ) );
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
	 * @param array<string> $urls List of URLs.
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
			$files = $external_files_obj->get_files();
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
		\WP_CLI::success( __( 'Files have been switches to local hosting.', 'external-files-in-media-library' ) );
	}

	/**
	 * Process the queue.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function process_queue(): void {
		Queue::get_instance()->process_queue();

		// return ok-message.
		\WP_CLI::success( __( 'The queue has been processed.', 'external-files-in-media-library' ) );
	}

	/**
	 * Clear the queue (delete every entry).
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function clear_queue(): void {
		Queue::get_instance()->clear();

		// return ok-message.
		\WP_CLI::success( __( 'The queue has been cleared.', 'external-files-in-media-library' ) );
	}

	/**
	 * Remove error URLs from queue.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function cleanup_queue(): void {
		Queue::get_instance()->clear();

		// return ok-message.
		\WP_CLI::success( __( 'Error-URLs has been removed from the queue.', 'external-files-in-media-library' ) );
	}
}
