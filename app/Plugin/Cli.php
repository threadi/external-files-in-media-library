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
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use WP_Query;
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
				'value' => ! empty( $arguments['login'] ) ? sanitize_text_field( wp_unslash( $arguments['password'] ) ) : '',
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
				$results[] = sprintf( __( '%1$s could not be saved in media library. Take a look in the log (Settings > External files in Media Library) for details.', 'external-files-in-media-library' ), $url );
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
		$logs->create( __( 'Plugin has been reset via cli.', 'external-files-in-media-library' ), '', 'success', 2 );

		// return ok-message.
		\WP_CLI::success( __( 'Plugin has been reset.', 'external-files-in-media-library' ) );
	}

	/**
	 * Switch hosting of files to external.
	 *
	 * Except for those with credentials or un-supported protocols.
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
		Queue::get_instance()->process_queue();

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
				'meta_query' => array(
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
				'meta_query' => array(
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
				'meta_query' => array(
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
			$sync_obj->delete_synced_files( $term_id, $taxonomy_obj->get_name() );
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
				'meta_query' => array(
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
	 * Export files from media library to an external source.
	 *
	 * @return void
	 */
	public function export(): void {
		// bail if export is not enabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			// show hint.
			\WP_CLI::error( __( 'Export is not enabled!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// bail if option is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			// show hint.
			\WP_CLI::error( __( 'Export for local files is not enabled!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// get all not external files in media library.
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'NOT EXISTS',
				),
			),
		);
		$files = new WP_Query( $query );

		// bail if no files could be found.
		if ( 0 === $files->found_posts ) {
			// show hint.
			\WP_CLI::error( __( 'No files to export could be found!', 'external-files-in-media-library' ) );

			// do nothing more.
			return;
		}

		// collect the results.
		$result_counter = 0;

		// export each file.
		foreach ( $files->posts as $attachment_id ) {
			if ( Export::get_instance()->export_file( absint( $attachment_id ) ) ) {
				++$result_counter;
			}
		}

		// show ok message.
		/* translators: %1$s will be replaced by a number. */
		\WP_CLI::success( sprintf( _n( '%1$d file have been exported.', '%1$d files have been exported.', $result_counter, 'external-files-in-media-library' ), $result_counter ) );
	}
}
