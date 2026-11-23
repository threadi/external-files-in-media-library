<?php
/**
 * File with the helpers for tests of synchronizations.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;

/**
 * Helpers for tests of synchronizations.
 *
 * Synchronizations are tested like they run on a website:
 * - with the settings of a new installation (synchronized files are protected against deletion, unused files are
 *   deleted after the synchronization) and with the hooks, which the synchronization registers in each request,
 * - started in the backend (via AJAX) and as scheduled synchronization (via cron),
 * - at least twice, with a change of the external source between them, as many problems only occur with files,
 *   which already exist in the media library.
 */
trait SynchronizationTrait {
	/**
	 * Enable the synchronization with the settings of a new installation and register its hooks like each request
	 * does it.
	 *
	 * The hooks are only registered, if the synchronization is enabled during the initialization of the plugin, which
	 * is not the case in the tests. WP_UnitTestCase restores the hooks after each test.
	 *
	 * @return void
	 */
	protected function enable_synchronization(): void {
		update_option( 'eml_sync', 1 );
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		update_option( 'eml_sync_delete_file_on_archive_deletion', 1 );
		Synchronization::get_instance()->init();
	}

	/**
	 * Data provider: the requests, in which a synchronization runs.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_sync_requests(): array {
		return array(
			'started in the backend (AJAX)' => array( 'ajax' ),
			'scheduled (cron)'              => array( 'cron' ),
		);
	}

	/**
	 * Synchronize the given external source like in the given request.
	 *
	 * In the backend an administrator starts it, via cron it runs without user.
	 *
	 * @param string $request The request: "ajax" or "cron".
	 * @param string $url     The URL of the external source.
	 * @param int    $term_id The ID of the external source.
	 *
	 * @return void
	 */
	protected function run_sync( string $request, string $url, int $term_id ): void {
		// get the actual user to restore it afterward.
		$user_id = get_current_user_id();

		// simulate the request.
		if ( 'ajax' === $request ) {
			$filter = 'wp_doing_ajax';
			if ( 0 === $user_id ) {
				wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			}
		} else {
			$filter = 'wp_doing_cron';
			wp_set_current_user( 0 );
		}
		add_filter( $filter, '__return_true' );

		// run the synchronization.
		try {
			@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			remove_filter( $filter, '__return_true' );
			wp_set_current_user( $user_id );
		}
	}
}
