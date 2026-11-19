<?php
/**
 * This file contains WP CLI options for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Availability;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Csv;
use ExternalFilesInMediaLibrary\Services\Xml;
use WP_Term_Query;

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
	 * [--real_import]
	 * : Import files from URLs as real files, not linked to external URL.
	 *
	 * [--use_dates]
	 * : Use the dates of the external files.
	 *
	 * [--use_specific_date=<value>]
	 * : Use specific date for each file.
	 *
	 * @param array<int,string>    $urls Array with URL, which might be given as parameter on CLI-command.
	 * @param array<string,string> $arguments List of parameter to use for the given URLs.
	 *
	 * @return void
	 */
	public function import( array $urls = array(), array $arguments = array() ): void {
		// array for the list of results.
		$results = array();

		// get the import object.
		$import = Import::get_instance();

		// create the fields-array, we assume it is an HTTP- or FTP-connection.
		$fields = array(
			'login'    => array(
				'value' => ! empty( $arguments['login'] ) ? sanitize_text_field( wp_unslash( $arguments['login'] ) ) : '',
			),
			'password' => array(
				'value' => ! empty( $arguments['login'] ) && ! empty( $arguments['password'] ) ? sanitize_text_field( wp_unslash( $arguments['password'] ) ) : '',
			),
		);

		// set the fields.
		$import->set_fields( $fields );

		/**
		 * Run additional tasks from extensions.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $arguments List of CLI arguments.
		 */
		do_action( 'efml_cli_arguments', $arguments );

		// show progress.
		$progress = \WP_CLI\Utils\make_progress_bar( _n( 'Import files from a specific URL', 'Import files from given URLs', count( $urls ), 'external-files-in-media-library' ), count( $urls ) );

		// check each single given url.
		foreach ( $urls as $url ) {
			// bail if URL is empty.
			if ( empty( $url ) ) {
				// show progress.
				$progress->tick();

				// bail this URL.
				continue;
			}

			// convert spaces in URL to HTML-encoded spaces.
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
			if ( $import->add_url( $url ) ) {
				/* translators: %1$s will be replaced by URL. */
				$results[] = sprintf( __( '%1$s has been saved in media library.', 'external-files-in-media-library' ), $url );
			} else {
				/* translators: %1$s will be replaced by URL. */
				$results[] = sprintf( __( '%1$s could not be saved in media library. Take a look in the log (Settings > External files in Media Library > Logs) for details.', 'external-files-in-media-library' ), $url );
			}

			// show progress.
			$progress->tick();
		}

		// finish progress.
		$progress->finish();

		// show results.
		\WP_CLI::success( __( 'Import results:', 'external-files-in-media-library' ) . "\n" . implode( "\n", $results ) );
	}

	/**
	 * Delete all URLs in media library, which are imported by this plugin.
	 *
	 * [<URLs>]
	 * : List of URLs to delete from in media library. If nothing is given all external files are deleted.
	 *
	 * @param array<int,string> $urls List of URLs.
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
			\WP_CLI::error( __( 'No files found to delete.', 'external-files-in-media-library' ) );
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

		// return ok-message.
		\WP_CLI::success( __( 'Log has been cleared.', 'external-files-in-media-library' ) );
	}

	/**
	 * Check the availability of URLs in the media library.
	 *
	 * [<URLs>]
	 * : List of URLs to check. They must exist in media library.
	 *
	 * @param array<int,string> $urls List of URLs.
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
			Availability::get_instance()->check_files();
		}

		// return ok-message.
		\WP_CLI::success( __( 'URL-check has been run. Results could be checked under Settings > External Files in Media Library > Logs.', 'external-files-in-media-library' ) );
	}

	/**
	 * Reset the plugin. It will be de- and re-installed.
	 *
	 * Exported files are restored as local files at their original path before. If one of them could not be
	 * restored, the plugin is not reset.
	 *
	 * [--force]
	 * : Reset the plugin, even if exported files could not be restored. They stay exported files then.
	 *
	 * @param array<int,string>    $args      List of arguments (not used).
	 * @param array<string,string> $arguments List of parameters.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function reset_plugin( array $args = array(), array $arguments = array() ): void {
		// restore the exported files.
		$remaining = \ExternalFilesInMediaLibrary\ExternalFiles\Restore::get_instance()->restore_until( PHP_INT_MAX );

		// bail if files could not be restored.
		if ( $remaining > 0 && ! isset( $arguments['force'] ) ) {
			/* translators: %1$d will be replaced by a number. */
			\WP_CLI::error( sprintf( _n( 'The plugin has not been reset, as %1$d exported file could not be restored. The reason is listed in the log. Use --force to reset the plugin nevertheless.', 'The plugin has not been reset, as %1$d exported files could not be restored. The reasons are listed in the log. Use --force to reset the plugin nevertheless.', $remaining, 'external-files-in-media-library' ), $remaining ) );

			// do nothing more.
			return;
		}

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
		$logs->create( __( 'Plugin has been reset via cli.', 'external-files-in-media-library' ), '', 'success', 2 );

		// return ok-message.
		\WP_CLI::success( __( 'Plugin has been reset.', 'external-files-in-media-library' ) );
	}

	/**
	 * Switch hosting of files to external.
	 *
	 * Except for un-supported protocols.
	 * If no URLs given all external files will be switched.
	 *
	 * [<URLs>]
	 * : List of URLs to switch.
	 *
	 * @param array<int,string> $urls List of URLs.
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
		\WP_CLI::success( _n( 'File has been switched to external hosting.', 'Files have been switched to external hosting.', count( $files ), 'external-files-in-media-library' ) );
	}

	/**
	 * Switch hosting of files to local.
	 *
	 * Except for those with credentials or un-supported protocols.
	 *  If no URLs given all external files will be switched.
	 *
	 * [<URLs>]
	 * : List of URLs to switch.
	 *
	 * @param array<int,string> $urls List of URLs.
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
		\WP_CLI::success( _n( 'File has been switched to local hosting.', 'Files have been switched to local hosting.', count( $files ), 'external-files-in-media-library' ) );
	}

	/**
	 * Process the queue.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function process_queue(): void {
		Queue::get_instance()->process_queue( true );

		// return ok-message.
		\WP_CLI::success( __( 'The queue has been processed.', 'external-files-in-media-library' ) );
	}

	/**
	 * Clear the queue (will delete every entry in the queue).
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
		Queue::get_instance()->clear_error_entries();

		// show ok message.
		\WP_CLI::success( __( 'Error-URLs has been removed from the queue.', 'external-files-in-media-library' ) );
	}

	/**
	 * Delete the lists with URLs (CSV or XML), which have been used via the directory listing and are no longer in use.
	 *
	 * The last downloaded version of a URL, which is saved as external source, is always kept.
	 *
	 * [--service=<value>]
	 * : Only cleanup the lists of this service (csv or xml).
	 *
	 * [--days=<value>]
	 * : Delete lists, which have not been used for this amount of days (default: the value from the settings).
	 *
	 * [--force]
	 * : Run the cleanup also if it is disabled in the settings.
	 *
	 * [--dry-run]
	 * : Only show the files, which would be deleted.
	 *
	 * @param array<int,string>    $names The names.
	 * @param array<string,string> $arguments List of parameter to use.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function cleanup_lists( array $names = array(), array $arguments = array() ): void {
		// get the services.
		$services = array(
			Csv::get_instance()->get_name() => Csv::get_instance(),
			Xml::get_instance()->get_name() => Xml::get_instance(),
		);

		// filter for a single service.
		if ( ! empty( $arguments['service'] ) ) {
			$service = strtolower( $arguments['service'] );

			// bail if service is unknown.
			if ( ! isset( $services[ $service ] ) ) {
				/* translators: %1$s will be replaced by a list of names. */
				\WP_CLI::error( sprintf( __( 'Unknown service. Please use one of: %1$s', 'external-files-in-media-library' ), implode( ', ', array_keys( $services ) ) ) );
				return;
			}
			$services = array( $service => $services[ $service ] );
		}

		// get the other options.
		$days    = isset( $arguments['days'] ) ? absint( $arguments['days'] ) : 0;
		$force   = isset( $arguments['force'] );
		$dry_run = isset( $arguments['dry-run'] );

		// run the cleanup for each service.
		$count = 0;
		foreach ( $services as $service_obj ) {
			// show hint if cleanup is disabled for this service.
			if ( ! $force && ! $service_obj->is_cleanup_enabled() ) {
				/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
				\WP_CLI::log( sprintf( __( 'The cleanup for %1$s is disabled in the settings. Use --force to run it anyway.', 'external-files-in-media-library' ), $service_obj->get_label() ) );
				continue;
			}

			// run the cleanup and show the files.
			foreach ( $service_obj->cleanup( $days, $force, $dry_run ) as $attachment_id ) {
				\WP_CLI::log( '#' . $attachment_id . ( $dry_run ? ' ' . get_the_title( $attachment_id ) : '' ) );
				++$count;
			}
		}

		// show the result.
		if ( $dry_run ) {
			/* translators: %1$d will be replaced by a number. */
			\WP_CLI::success( sprintf( _n( '%1$d file would be deleted.', '%1$d files would be deleted.', $count, 'external-files-in-media-library' ), $count ) );
			return;
		}
		/* translators: %1$d will be replaced by a number. */
		\WP_CLI::success( sprintf( _n( '%1$d file has been deleted.', '%1$d files have been deleted.', $count, 'external-files-in-media-library' ), $count ) );
	}

	/**
	 * Add an external source.
	 *
	 * <Name>
	 * : The name to use to this entry.
	 *
	 * --type=<value>
	 * : Set the type for this source. This must be the internal name of the used service, e.g., local or ftp.
	 *
	 * --fields=<value>
	 * : Fields to configure this source as JSON.
	 *
	 * @param array<int,string>    $names Array with names, which might be given as parameter on CLI-command.
	 * @param array<string,string> $arguments List of parameter to use for the given URLs.
	 *
	 * @return void
	 */
	public function add_external_source( array $names = array(), array $arguments = array() ): void {
		// get the fields.
		$fields = json_decode( $arguments['fields'], true );

		// bail if fields is not an array.
		if ( ! is_array( $fields ) ) {
			\WP_CLI::error( __( 'Wrong fields value given!', 'external-files-in-media-library' ) );
		}

		// add the external source.
		$term_id = Taxonomy::get_instance()->add( $arguments['type'], $names[0], $fields );

		// bail if no entry could be saved.
		if ( 0 === $term_id ) {
			\WP_CLI::error( __( 'Wrong fields value given!', 'external-files-in-media-library' ) );
		}

		// show ok message.
		\WP_CLI::success( __( 'The external source has been added.', 'external-files-in-media-library' ) );
	}

	/**
	 * Delete external sources by their names.
	 *
	 * <Names>
	 * : List of names of external sources to delete.
	 *
	 * @param array<int,string> $names List of names.
	 *
	 * @return void
	 */
	public function delete_external_source( array $names = array() ): void {
		// get the taxonomy object.
		$taxonomy_obj = Taxonomy::get_instance();

		// check each given name.
		foreach ( $names as $name ) {
			// get the term by the given name.
			$query  = array(
				'taxonomy'   => $taxonomy_obj->get_name(),
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for meta.
					array(
						'key'     => 'path',
						'value'   => $name,
						'compare' => '=',
					),
				),
			);
			$result = new WP_Term_Query( $query );

			// bail on no results.
			if ( empty( $result->terms ) ) {
				continue;
			}

			// delete the entry.
			$taxonomy_obj->delete( $result->terms[0]->term_id );
		}

		// show ok message.
		\WP_CLI::success( __( 'Given external sources has been deleted.', 'external-files-in-media-library' ) );
	}

	/**
	 * Run sync for given external sources.
	 *
	 * <Names>
	 * : Names of external sources to sync.
	 *
	 * @param array<int,string> $names List of names.
	 *
	 * @return void
	 */
	public function sync( array $names = array() ): void {
		// bail if sync is enabled.
		if ( 0 === absint( get_option( 'eml_sync' ) ) ) {
			// show hint.
			\WP_CLI::success( __( 'Synchronisation is not enabled in the plugin settings.', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// bail if no names are given.
		if ( empty( $names ) ) {
			// show hint.
			\WP_CLI::error( __( 'No external sources given!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// get the taxonomy object.
		$taxonomy_obj = Taxonomy::get_instance();

		// get the synchronisation object.
		$sync_obj = Synchronization::get_instance();

		// sync each given name.
		foreach ( $names as $name ) {
			// get the term by the given name.
			$query  = array(
				'taxonomy'   => $taxonomy_obj->get_name(),
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
					array(
						'key'     => 'path',
						'value'   => $name,
						'compare' => '=',
					),
				),
			);
			$result = new WP_Term_Query( $query );

			// bail on no results.
			if ( empty( $result->terms ) ) {
				continue;
			}

			// get the term ID.
			$term_id = $result->terms[0]->term_id;

			// get the term data.
			$term_data = $taxonomy_obj->get_entry( $term_id );

			// run the synchronisation for this external source.
			$sync_obj->sync( $name, $term_data, $term_id );
		}

		// show ok message.
		\WP_CLI::success( __( 'Synchronisation for given external sources have been run.', 'external-files-in-media-library' ) );
	}

	/**
	 * Delete synced files for given external sources.
	 *
	 * <Names>
	 * : Names of external sources to sync.
	 *
	 * @param array<int,string> $names List of names.
	 *
	 * @return void
	 */
	public function delete_synced_files( array $names = array() ): void {
		// bail if sync is enabled.
		if ( 0 === absint( get_option( 'eml_sync' ) ) ) {
			// show hint.
			\WP_CLI::success( __( 'Synchronisation is not enabled in the plugin settings.', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// bail if no names are given.
		if ( empty( $names ) ) {
			// show hint.
			\WP_CLI::error( __( 'No external sources given!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// get the taxonomy object.
		$taxonomy_obj = Taxonomy::get_instance();

		// get the synchronisation object.
		$sync_obj = Synchronization::get_instance();

		// delete files for each given name.
		foreach ( $names as $name ) {
			// get the term by the given name.
			$query  = array(
				'taxonomy'   => $taxonomy_obj->get_name(),
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
					array(
						'key'     => 'path',
						'value'   => $name,
						'compare' => '=',
					),
				),
			);
			$result = new WP_Term_Query( $query );

			// bail on no results.
			if ( empty( $result->terms ) ) {
				continue;
			}

			// get the term ID.
			$term_id = $result->terms[0]->term_id;

			// delete the synced files of this external source.
			$sync_obj->delete_synced_files( $term_id );
		}

		// show ok message.
		\WP_CLI::success( __( 'Synchronised files have been deleted.', 'external-files-in-media-library' ) );
	}

	/**
	 * Change the export state for given external sources.
	 *
	 * <Names>
	 * : Names of external sources.
	 *
	 * [--enable]
	 * : Enable the export for the given external sources.
	 *
	 * [--disable]
	 * : Disable the export for the given external sources.
	 *
	 * Hint: enabling the export to an external source can only be successful
	 * if it has already been configured for export.
	 *
	 * @param array<int,string>    $names List of names.
	 * @param array<string,string> $arguments The arguments.
	 *
	 * @return void
	 */
	public function change_export_state( array $names = array(), array $arguments = array() ): void {
		// bail if sync is enabled.
		if ( 0 === absint( get_option( 'eml_export' ) ) ) {
			// show hint.
			\WP_CLI::success( __( 'Export is not enabled in the plugin settings.', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// bail if no names are given.
		if ( empty( $names ) ) {
			// show hint.
			\WP_CLI::error( __( 'No external sources given!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// bail if no arguments are set.
		if ( empty( $arguments ) ) {
			// show hint.
			\WP_CLI::error( __( 'No setting is given!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// get the taxonomy object.
		$taxonomy_obj = Taxonomy::get_instance();

		// get the export object.
		$export_obj = Export::get_instance();

		// enable export for each given name.
		foreach ( $names as $name ) {
			// get the term by the given name.
			$query  = array(
				'taxonomy'   => $taxonomy_obj->get_name(),
				'hide_empty' => false,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
					array(
						'key'     => 'path',
						'value'   => $name,
						'compare' => '=',
					),
				),
			);
			$result = new WP_Term_Query( $query );

			// bail on no results.
			if ( empty( $result->terms ) ) {
				continue;
			}

			// get the term ID.
			$term_id = $result->terms[0]->term_id;

			// enable the export to this external source.
			$export_obj->set_state_for_term( $term_id, isset( $arguments['enable'] ) ? 1 : 0 );
		}

		// show ok message.
		\WP_CLI::success( __( 'Settings for export to external sources has been changed.', 'external-files-in-media-library' ) );
	}

	/**
	 * Export the local files in the media library to the external sources with enabled export.
	 *
	 * Exports all local files, which are not exported yet and whose mime type is allowed in the plugin settings.
	 * This is the same export as via the button "Export now" in the export settings or via the quickstart, but all
	 * files are exported directly.
	 *
	 * @return void
	 */
	public function export(): void {
		// export the files.
		$result = Import_Runs::get_instance()->start_export();

		// bail if the export could not be started.
		if ( 0 === $result['run_id'] ) {
			\WP_CLI::error( $result['message'] );

			// do nothing more.
			return;
		}

		// show a warning, if files could not be exported.
		if ( ! $result['success'] ) {
			\WP_CLI::warning( $result['message'] );

			// do nothing more.
			return;
		}

		// show ok message.
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Export the sizes of exported images, which have been exported without them.
	 *
	 * The sizes are created from the local copy of an image or from the image downloaded from its external source
	 * and exported to its external sources, if their services support it. This is the same as via the button
	 * "Export sizes now" in the export settings, but all images are processed directly.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp eml export_sizes
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function export_sizes(): void {
		// export the sizes.
		$result = Import_Runs::get_instance()->start_sizes();

		// bail if it could not be started.
		if ( 0 === $result['run_id'] ) {
			\WP_CLI::error( $result['message'] );

			// do nothing more.
			return;
		}

		// show a warning, if images failed.
		if ( ! $result['success'] ) {
			\WP_CLI::warning( $result['message'] );

			// do nothing more.
			return;
		}

		// show ok message.
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Restore all exported files as local files at their original path in the media library.
	 *
	 * The files are downloaded from their external sources, their sizes are created again and they are normal
	 * local files afterward. The export is disabled for this. This is the same restore as via the button
	 * "Restore now" in the export settings, but all files are restored directly.
	 *
	 * [--delete-on-target]
	 * : Delete the copies on the external sources after the restore. Without it they are kept.
	 *
	 * ## EXAMPLES
	 *
	 *     # Restore all exported files, e.g. before you deactivate the plugin.
	 *     $ wp eml localize
	 *
	 * @param array<int,string>    $args      List of arguments (not used).
	 * @param array<string,string> $arguments List of parameters.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function localize( array $args = array(), array $arguments = array() ): void {
		// restore the files.
		$result = Import_Runs::get_instance()->start_localize( isset( $arguments['delete-on-target'] ) );

		// bail if the restore could not be started.
		if ( 0 === $result['run_id'] ) {
			\WP_CLI::error( $result['message'] );

			// do nothing more.
			return;
		}

		// show a warning, if files could not be restored.
		if ( ! $result['success'] ) {
			\WP_CLI::warning( $result['message'] );

			// do nothing more.
			return;
		}

		// show ok message.
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Delete the local copies of exported files, whose local files have been kept during their export.
	 *
	 * This applies to files exported with the setting "Do not delete local files". Each file is loaded from its external
	 * source before, its local copy is only deleted, if this works. Sizes, original images and variants (e.g. WebP) are
	 * only deleted, if they exist on the external source. Files taken over from other offload plugins keep their local
	 * files. This is the same as via the button "Delete local copies now" in the export settings, but all files are
	 * processed directly.
	 *
	 * ## EXAMPLES
	 *
	 *     # Free the storage space of the local copies of exported files.
	 *     $ wp eml delete_local_copies
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function delete_local_copies(): void {
		// delete the local copies.
		$result = Import_Runs::get_instance()->start_delete_local();

		// bail if it could not be started.
		if ( 0 === $result['run_id'] ) {
			\WP_CLI::error( $result['message'] );

			// do nothing more.
			return;
		}

		// show a warning, if files kept their local copies.
		if ( ! $result['success'] ) {
			\WP_CLI::warning( $result['message'] );

			// do nothing more.
			return;
		}

		// show ok message.
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Save or finish local working copies of exported images, e.g. for image optimizers.
	 *
	 * Image optimizers and other plugins need the local files of images. "create" saves working copies of all exported
	 * images, which are still delivered from their external sources. "finish" exports the changed and new files of all
	 * working copies (e.g. optimized files, WebP files and backups) and removes the working copies. "status" shows the
	 * number of working copies. Working copies are finished automatically, as soon as an image optimizer reports the
	 * end of its work for an image.
	 *
	 * <action>
	 * : "create", "finish" or "status".
	 *
	 * ## EXAMPLES
	 *
	 *     # Prepare the exported images for the bulk optimization of an image optimizer.
	 *     $ wp eml working_copies create
	 *
	 *     # Export the optimized images afterward.
	 *     $ wp eml working_copies finish
	 *
	 * @param array<int,string> $actions List of actions (only the first one is used).
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function working_copies( array $actions = array() ): void {
		// get the action.
		$action = $actions[0] ?? 'status';

		// show the status.
		if ( 'status' === $action ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			\WP_CLI::line( sprintf( __( 'Images with working copy: %1$d, exported images without working copy: %2$d', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies::get_instance()->count_active(), \ExternalFilesInMediaLibrary\ExternalFiles\Working_Copies::get_instance()->count_candidates() ) );
			return;
		}

		// bail on unknown actions.
		if ( ! in_array( $action, array( 'create', 'finish' ), true ) ) {
			\WP_CLI::error( __( 'Unknown action. Use "create", "finish" or "status".', 'external-files-in-media-library' ) );
			return;
		}

		// run it.
		$result = 'create' === $action ? Import_Runs::get_instance()->start_working_copies() : Import_Runs::get_instance()->start_finish_working_copies();

		// bail if it could not be started.
		if ( 0 === $result['run_id'] ) {
			\WP_CLI::error( $result['message'] );
			return;
		}

		// show a warning, if images failed.
		if ( ! $result['success'] ) {
			\WP_CLI::warning( $result['message'] );
			return;
		}

		// show ok message.
		\WP_CLI::success( $result['message'] );
	}

	/**
	 * Show or set whether this website is the main website or a copy (e.g. a staging site).
	 *
	 * On a copy, files are not exported to or deleted on external sources, and scheduled synchronizations are paused.
	 *
	 * <state>
	 * : "main" to set this website as main website, "copy" to confirm it as copy, "status" to show the actual state.
	 *
	 * ## EXAMPLES
	 *
	 *     # Use this website as main website, e.g. after the go-live of a new domain.
	 *     $ wp eml site main
	 *
	 * @param array<int,string> $states List of states (only the first one is used).
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function site( array $states = array() ): void {
		// get the object.
		$site_copy_obj = Site_Copy::get_instance();

		// get the requested state.
		$state = ! empty( $states[0] ) ? $states[0] : 'status';

		// set this website as main website.
		if ( 'main' === $state ) {
			$site_copy_obj->set_as_main();
			\WP_CLI::success( __( 'This website is now the main website.', 'external-files-in-media-library' ) );
			return;
		}

		// confirm this website as copy.
		if ( 'copy' === $state ) {
			if ( ! $site_copy_obj->is_copy() ) {
				\WP_CLI::error( __( 'This website is the main website. Change its address or its environment type to use it as copy.', 'external-files-in-media-library' ) );
			}
			$site_copy_obj->confirm_copy();
			\WP_CLI::success( __( 'This website has been confirmed as copy.', 'external-files-in-media-library' ) );
			return;
		}

		// bail on unknown state.
		if ( 'status' !== $state ) {
			\WP_CLI::error( __( 'Use "main", "copy" or "status".', 'external-files-in-media-library' ) );
		}

		// show the actual state.
		if ( $site_copy_obj->is_copy() ) {
			\WP_CLI::warning( __( 'This website is a copy of another website: files are not exported and not deleted on external sources, scheduled synchronizations are paused.', 'external-files-in-media-library' ) );
			return;
		}
		\WP_CLI::success( __( 'This website is the main website.', 'external-files-in-media-library' ) );
	}

	/**
	 * Show the state of the migration of files from other offload plugins, continue it or revert it.
	 *
	 * Reverting a migration uses the local files and the data of the other plugin again. Activate the other plugin
	 * afterwards.
	 *
	 * <action>
	 * : "status" to show the state, "run" to continue a running migration, "revert" to revert the migration.
	 *
	 * [--attachment=<id>]
	 * : Revert only the given attachment.
	 *
	 * ## EXAMPLES
	 *
	 *     # Revert the migration of all files.
	 *     $ wp eml migration revert
	 *
	 * @param array<int,string>    $actions List of actions (only the first one is used).
	 * @param array<string,string> $arguments List of arguments.
	 *
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function migration( array $actions = array(), array $arguments = array() ): void {
		// get the object.
		$migrations_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Migrations::get_instance();

		// get the requested action.
		$action = ! empty( $actions[0] ) ? $actions[0] : 'status';

		// continue the running migration.
		if ( 'run' === $action ) {
			// bail if no migration is running.
			if ( empty( $migrations_obj->get_run() ) ) {
				\WP_CLI::success( __( 'No migration is running.', 'external-files-in-media-library' ) );
				return;
			}

			// run it without time limit.
			$migrations_obj->work( PHP_FLOAT_MAX );
			$result = $migrations_obj->get_last_result();
			/* translators: %1$d, %2$d and %3$d will be replaced by numbers. */
			\WP_CLI::success( sprintf( __( 'The migration has been completed: %1$d files taken over, %2$d skipped, %3$d failed.', 'external-files-in-media-library' ), absint( $result['done'] ?? 0 ), absint( $result['skipped'] ?? 0 ), absint( $result['failed'] ?? 0 ) ) );
			return;
		}

		// revert the migration.
		if ( 'revert' === $action ) {
			// get the attachments.
			$attachment_ids = ! empty( $arguments['attachment'] ) ? array( absint( $arguments['attachment'] ) ) : $migrations_obj->get_migrated_attachments();

			// revert them.
			$count = 0;
			foreach ( $attachment_ids as $attachment_id ) {
				if ( $migrations_obj->revert( $attachment_id ) ) {
					++$count;
				}
			}

			/* translators: %1$d will be replaced by a number. */
			\WP_CLI::success( sprintf( __( 'The migration of %1$d files has been reverted. Activate the other offload plugin again to use them.', 'external-files-in-media-library' ), $count ) );
			return;
		}

		// bail on unknown action.
		if ( 'status' !== $action ) {
			\WP_CLI::error( __( 'Use "status", "run" or "revert".', 'external-files-in-media-library' ) );
		}

		// show the running migration.
		$run = $migrations_obj->get_run();
		if ( ! empty( $run ) ) {
			/* translators: %1$d and %2$d will be replaced by numbers. */
			\WP_CLI::log( sprintf( __( 'A migration is running: %1$d of %2$d files processed.', 'external-files-in-media-library' ), absint( $run['done'] ?? 0 ) + absint( $run['skipped'] ?? 0 ) + absint( $run['failed'] ?? 0 ), absint( $run['total'] ?? 0 ) ) );
		}

		// show the amount of migrated files.
		/* translators: %1$d will be replaced by a number. */
		\WP_CLI::success( sprintf( __( '%1$d files have been taken over from other offload plugins.', 'external-files-in-media-library' ), count( $migrations_obj->get_migrated_attachments() ) ) );
	}
}
