<?php
/**
 * Live tests of the services with real accounts: import, synchronization, export, private files and permissions.
 *
 * Each test runs for each service, whose credentials are complete in ".env.live", the others are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Live;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Error;

/**
 * Object to test the services with real accounts.
 */
class ServicesTest extends Live_Test_Base {
	/**
	 * All configured services.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function services(): array {
		return self::get_services();
	}

	/**
	 * The configured services with directory listing.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function services_with_listing(): array {
		return self::get_services( 'listing' );
	}

	/**
	 * The configured services with export.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function services_with_export(): array {
		return self::get_services( 'export' );
	}

	/**
	 * The configured services with private files.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function services_with_private_files(): array {
		return self::get_services( 'private' );
	}

	/**
	 * Test the connection: the external source is saved (its connection is tested before) and its directory is listed.
	 *
	 * @dataProvider services_with_listing
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_connection_and_listing( string $name ): void {
		$this->get_service( $name );
		$term_id = $this->add_source( $name );

		$listing = $this->run_ability( 'list-directory', array( 'source_id' => $term_id ) );
		$this->assertArrayHasKey( 'files', $listing );
		$this->assertArrayHasKey( 'directories', $listing );
	}

	/**
	 * Test the import of a single file of the external source and its delivery for visitors.
	 *
	 * @dataProvider services_with_listing
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_import_of_single_file( string $name ): void {
		$this->get_service( $name );
		$term_id = $this->add_source( $name );

		// get the first file of the directory.
		$files = $this->get_files( $term_id );
		if ( empty( $files ) ) {
			$this->markTestSkipped( 'The directory of the external source contains no files to import.' );
		}
		$url = (string) $files[0]['url'];

		// import it.
		$result = $this->run_ability(
			'import',
			array(
				'urls'      => array( $url ),
				'source_id' => $term_id,
			)
		);
		$this->assertNotEmpty( $result['imported'] ?? array(), 'The file ' . $url . ' has not been imported: ' . wp_json_encode( $result['errors'] ?? array() ) . $this->get_log_errors() );
		$attachment_id = absint( $result['imported'][0]['attachment_id'] ?? 0 );
		$this->assertGreaterThan( 0, $attachment_id );
		$this->remember_attachment( $attachment_id );

		// it is an external file of this source.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		$this->assertTrue( $external_file_obj->is_valid() );

		// visitors get its content (e.g. via the proxy), if it is not saved locally.
		if ( ! $external_file_obj->is_locally_saved() ) {
			$content = $this->load_as_visitor( $attachment_id );
			$this->assertNotFalse( $content, 'The imported file could not be loaded for a visitor.' . $this->get_log_errors() );
			$this->assertNotSame( '', $content );
		}
	}

	/**
	 * Test the synchronization of the external source, if its directory is small enough.
	 *
	 * @dataProvider services_with_listing
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_synchronization( string $name ): void {
		$this->get_service( $name );

		// bail if the service does not support the synchronization (like the local directory).
		$listing_obj = Services::get_instance()->get_service_by_name( $name );
		if ( ! $listing_obj instanceof Service_Base || $listing_obj->is_sync_disabled() ) {
			$this->markTestSkipped( 'The service does not support the synchronization.' );
		}

		$term_id = $this->add_source( $name );

		// synchronize only the files of the directory itself, also for services, which use subdirectories by default.
		$this->set_subdirectories( $term_id, Sync_Subdirectories::NO );

		// bail if the directory is empty or too large.
		$listing = $this->run_ability( 'list-directory', array( 'source_id' => $term_id ) );
		$count   = count( (array) ( $listing['files'] ?? array() ) );
		$max     = absint( Env::get_instance()->get( 'EFML_LIVE_MAX_SYNC_FILES', '20' ) );
		if ( 0 === $count || ! empty( $listing['truncated'] ) || $count > $max ) {
			$this->markTestSkipped( 'The directory of the external source is empty or contains more than ' . $max . ' files (EFML_LIVE_MAX_SYNC_FILES).' );
		}

		// synchronize it.
		$this->synchronize( $term_id );

		// its files are in the media library.
		$files = $this->run_ability(
			'list-files',
			array(
				'source_id' => $term_id,
				'per_page'  => 100,
			)
		);
		$ids   = array_map( fn( $file ) => absint( is_array( $file ) ? ( $file['attachment_id'] ?? 0 ) : 0 ), (array) ( $files['files'] ?? array() ) );
		foreach ( $ids as $attachment_id ) {
			$this->remember_attachment( $attachment_id );
		}
		$this->assertNotEmpty( array_filter( $ids ), 'The synchronization has not added any file.' . $this->get_log_errors() );

		// a second synchronization does not add them again.
		$this->synchronize( $term_id );
		$again = $this->run_ability(
			'list-files',
			array(
				'source_id' => $term_id,
				'per_page'  => 100,
			)
		);
		$this->assertCount( count( $ids ), (array) ( $again['files'] ?? array() ), 'The second synchronization has changed the number of files.' );

		// delete the synchronized files.
		$this->run_ability( 'delete-synced-files', array( 'source_id' => $term_id ) );
	}

	/**
	 * Enable the option for subdirectories and set it for the given external source.
	 *
	 * @param int    $term_id The ID of the external source.
	 * @param string $value   The setting: Sync_Subdirectories::YES or Sync_Subdirectories::NO.
	 *
	 * @return void
	 */
	private function set_subdirectories( int $term_id, string $value ): void {
		update_option( 'eml_sync_extensions', array( 'sync_subdirectories' ) );
		Sync_Subdirectories::get_instance()->init();
		update_term_meta( $term_id, Sync_Subdirectories::SETTING, $value );
	}

	/**
	 * Return the IDs of the files of the given external source in the media library.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return array<int,int>
	 */
	private function get_synced_ids( int $term_id ): array {
		$files = $this->run_ability(
			'list-files',
			array(
				'source_id' => $term_id,
				'per_page'  => 100,
			)
		);
		return array_values( array_filter( array_map( fn( $file ) => absint( is_array( $file ) ? ( $file['attachment_id'] ?? 0 ) : 0 ), (array) ( $files['files'] ?? array() ) ) ) );
	}

	/**
	 * Test the synchronization with the files of subdirectories and their deletion, if this is disabled afterward.
	 *
	 * The test runs only with EFML_LIVE_SYNC_SUBDIRECTORIES=1, as the subdirectories of an external source could
	 * contain many files. The directory itself must contain at most EFML_LIVE_MAX_SYNC_FILES files.
	 *
	 * @dataProvider services_with_listing
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_synchronization_of_subdirectories( string $name ): void {
		// bail if this test is not enabled.
		if ( '1' !== Env::get_instance()->get( 'EFML_LIVE_SYNC_SUBDIRECTORIES', '' ) ) {
			$this->markTestSkipped( 'The synchronization of subdirectories is only tested with EFML_LIVE_SYNC_SUBDIRECTORIES=1.' );
		}

		$this->get_service( $name );

		// bail if the service does not support the synchronization.
		$listing_obj = Services::get_instance()->get_service_by_name( $name );
		if ( ! $listing_obj instanceof Service_Base || $listing_obj->is_sync_disabled() ) {
			$this->markTestSkipped( 'The service does not support the synchronization.' );
		}

		$term_id = $this->add_source( $name );

		// bail if the service does not support subdirectories (e.g. an older version of an extension).
		if ( ! Synchronization::get_instance()->supports_subdirectories( $term_id ) ) {
			$this->markTestSkipped( 'The service does not support subdirectories.' );
		}

		// bail if the directory has no subdirectories with files or is too large.
		$listing = $this->run_ability( 'list-directory', array( 'source_id' => $term_id ) );
		$count   = count( (array) ( $listing['files'] ?? array() ) );
		$max     = absint( Env::get_instance()->get( 'EFML_LIVE_MAX_SYNC_FILES', '20' ) );
		if ( empty( $listing['directories'] ) || ! empty( $listing['truncated'] ) || $count > $max ) {
			$this->markTestSkipped( 'The directory of the external source has no subdirectories or contains more than ' . $max . ' files (EFML_LIVE_MAX_SYNC_FILES).' );
		}

		// synchronize with subdirectories: more files than in the directory itself are in the media library.
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );
		$this->set_subdirectories( $term_id, Sync_Subdirectories::YES );
		$this->synchronize( $term_id );
		$ids = $this->get_synced_ids( $term_id );
		foreach ( $ids as $attachment_id ) {
			$this->remember_attachment( $attachment_id );
		}
		$this->assertGreaterThan( $count, count( $ids ), 'The synchronization has not added the files of the subdirectories.' . $this->get_log_errors() );

		// synchronize without subdirectories: only the files of the directory itself are kept.
		$this->set_subdirectories( $term_id, Sync_Subdirectories::NO );
		$this->synchronize( $term_id );
		$this->assertCount( $count, $this->get_synced_ids( $term_id ), 'The files of the subdirectories have not been deleted.' . $this->get_log_errors() );

		// delete the synchronized files.
		$this->run_ability( 'delete-synced-files', array( 'source_id' => $term_id ) );
	}

	/**
	 * Synchronize the given external source completely, also the part which the plugin continues in the background.
	 *
	 * A synchronization works on its files until the time limit of the request is reached and continues via WP Cron
	 * afterward. In the tests the request is running for a long time already, so the remaining part is processed here
	 * like WP Cron does it.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return void
	 */
	private function synchronize( int $term_id ): void {
		// give the synchronization time, counted from now.
		$time_limit   = static fn() => 300;
		$request_time = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		add_filter( 'efml_import_run_time_limit', $time_limit );
		$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );

		try {
			$this->run_ability( 'sync-source', array( 'source_id' => $term_id ) );

			// process the part, which would be continued in the background.
			for ( $i = 0; $i < 20; $i++ ) {
				if ( ! wp_next_scheduled( Import_Runs::CRON_HOOK ) ) {
					break;
				}
				wp_clear_scheduled_hook( Import_Runs::CRON_HOOK );
				$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
				Import_Runs::get_instance()->process();
			}
			$this->assertFalse( wp_next_scheduled( Import_Runs::CRON_HOOK ), 'The synchronization has not been finished.' . $this->get_log_errors() );
		} finally {
			remove_filter( 'efml_import_run_time_limit', $time_limit );
			$_SERVER['REQUEST_TIME_FLOAT'] = $request_time;
		}
	}

	/**
	 * Test the public export of an uploaded image with its sizes, its delivery and its deletion on the external source.
	 *
	 * @dataProvider services_with_export
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_export( string $name ): void {
		if ( $this->is_private_only( $name ) ) {
			$this->markTestSkipped( 'The external source allows only private files (EFML_LIVE_PRIVATE_ONLY in .env.live).' );
		}
		$this->export_and_check( $name, false );
	}

	/**
	 * Return whether the external source of the given service allows only private files, e.g. a bucket without public
	 * access (setting EFML_LIVE_PRIVATE_ONLY in ".env.live").
	 *
	 * @param string $name The name of the service.
	 *
	 * @return bool
	 */
	private function is_private_only( string $name ): bool {
		return in_array( $name, array_map( 'trim', explode( ',', Env::get_instance()->get( 'EFML_LIVE_PRIVATE_ONLY' ) ) ), true );
	}

	/**
	 * Test the private export of an uploaded image, its delivery for visitors and its deletion on the external source.
	 *
	 * @dataProvider services_with_private_files
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_private_export( string $name ): void {
		$this->export_and_check( $name, true );
	}

	/**
	 * Test the export of an upload of a user without the capability for the service: it is exported, but the user
	 * could not import files of the service.
	 *
	 * @dataProvider services_with_export
	 *
	 * @param string $name The name of the service.
	 *
	 * @return void
	 */
	public function test_upload_of_user_without_capability( string $name ): void {
		$this->get_service( $name );
		$term_id = $this->enable_export( $name, $this->is_private_only( $name ) );

		// the upload of an editor without the capability for this service is exported.
		wp_set_current_user( $this->create_user_without_service( 'editor', $name ) );
		$this->assertFalse( current_user_can( 'efml_cap_' . $name ) );
		$upload = $this->upload_image( $name . '-editor' );
		if ( ! Export::get_instance()->export_file( $upload['id'] ) ) {
			$this->fail( 'The upload of the editor has not been exported.' . $this->get_log_errors() . $this->get_failed_export_details( $name, $term_id, $upload['id'] ) );
		}
		$this->assertSame( $upload['md5'], md5( (string) $this->load_as_visitor( $upload['id'] ) ), 'The exported file could not be loaded.' );

		// the editor could not import files of this service. Public URLs of files (e.g. of a bucket with its own
		// domain or shared links) could be imported by everyone who may add external files, like any other URL.
		wp_set_current_user( 1 );
		$files = $this->get_files( $term_id );
		wp_set_current_user( $this->create_user_without_service( 'editor', $name ) );
		$definition = $this->get_definition( $name );
		$file_url   = empty( $files ) ? '' : (string) $files[0]['url'];
		if ( '' !== $file_url && ! preg_match( '#^https?://#i', $file_url ) && ( 'dropbox' === $name || ! empty( $definition['plugin'] ) ) ) {
			$this->assertFalse( Import::get_instance()->add_url( $file_url ), 'The editor without capability could import a file of the service: ' . $file_url );
		}
	}

	/**
	 * Test the import of the single URLs of "EFML_LIVE_URLS" via "Add URL".
	 *
	 * @return void
	 */
	public function test_single_urls(): void {
		$urls = array_filter( array_map( 'trim', explode( ',', Env::get_instance()->get( 'EFML_LIVE_URLS' ) ) ) );
		if ( empty( $urls ) ) {
			$this->markTestSkipped( 'No single URLs in EFML_LIVE_URLS.' );
		}

		foreach ( $urls as $url ) {
			$result = $this->run_ability_raw( 'add', array( 'url' => $url ) );
			$this->assertNotInstanceOf( WP_Error::class, $result, 'The URL ' . $url . ' could not be added: ' . ( $result instanceof WP_Error ? $result->get_error_message() : '' ) . $this->get_log_errors() );
			$attachment_id = is_numeric( $result ) ? absint( $result ) : 0;
			$this->assertGreaterThan( 0, $attachment_id, 'The URL ' . $url . ' has not been added.' . $this->get_log_errors() );
			$this->remember_attachment( $attachment_id );
		}
	}

	/**
	 * Enable the export to a new external source of the given service and return its ID.
	 *
	 * @param string $name    The name of the service.
	 * @param bool   $is_private Whether the files should be saved privately.
	 *
	 * @return int
	 */
	private function enable_export( string $name, bool $is_private ): int {
		$term_id = $this->add_source( $name );

		// set the public URL, if the service requires it.
		$service_obj = $this->get_service( $name );
		$export_obj  = $service_obj->get_export_object();
		$this->assertInstanceOf( Export_Base::class, $export_obj, 'The service does not support the export.' );
		if ( $export_obj->is_url_required() ) {
			$key = (string) ( $this->get_definition( $name )['export_url'] ?? '' );
			if ( '' === $key || ! Env::get_instance()->has( $key ) ) {
				$this->markTestSkipped( 'The export to this service requires the public URL of its directory in ' . $key . '.' );
			}
			update_term_meta( $term_id, 'efml_export_url', Env::get_instance()->get( $key ) );
		}

		// bail if the private files are requested, but not supported by the installed version of the service.
		if ( $is_private && ! $export_obj->supports_private_files() ) {
			$this->markTestSkipped( 'The installed version of the service does not support private files' . ( empty( $this->get_definition( $name )['plugin'] ) ? '.' : ' (an older version of its extension, e.g. from GitHub? See EFML_ADDONS_SOURCE in .env.live).' ) );
		}

		// save the files privately, if requested.
		if ( $is_private ) {
			update_term_meta( $term_id, 'efml_export_private', 1 );
		}

		// enable the export.
		update_option( 'eml_export', 1 );
		update_option( 'eml_export_local_files', 1 );
		$this->run_ability(
			'set-export-state',
			array(
				'source_id' => $term_id,
				'enabled'   => true,
			)
		);
		update_option( 'eml_export_main_source', $term_id );

		return $term_id;
	}

	/**
	 * Export an uploaded image and check it on the external source, its delivery and its deletion there.
	 *
	 * @param string $name       The name of the service.
	 * @param bool   $is_private Whether the file should be saved privately.
	 *
	 * @return void
	 */
	private function export_and_check( string $name, bool $is_private ): void {
		$this->get_service( $name );
		$term_id = $this->enable_export( $name, $is_private );

		// upload and export an image.
		$upload = $this->upload_image( $name . ( $is_private ? '-private' : '' ) );
		$result = $this->run_ability_raw( 'export-file', array( 'attachment_id' => $upload['id'] ) );
		if ( $result instanceof WP_Error ) {
			$this->fail( 'The ability export-file failed: ' . $result->get_error_message() . $this->get_log_errors() . $this->get_failed_export_details( $name, $term_id, $upload['id'] ) );
		}

		// it is an exported file now.
		$external_file_obj = Files::get_instance()->get_file( $upload['id'] );
		$this->assertTrue( $external_file_obj->is_valid(), 'The file is not an external file.' . $this->get_log_errors() );
		$this->assertNotEmpty( get_post_meta( $upload['id'], 'eml_exported_file', true ) );
		$this->assertSame( $is_private, $external_file_obj->is_private() );
		$url = $external_file_obj->get_url( true );

		// its sizes are exported, too, if the service supports it.
		$export_obj = $this->get_service( $name )->get_export_object();
		if ( $export_obj instanceof Export_Base && $export_obj->supports_sizes() ) {
			$this->assertNotEmpty( Sizes::get_instance()->get_sizes( $upload['id'] ), 'The sizes of the image have not been exported.' . $this->get_log_errors() );
		}

		// visitors get the exported file (e.g. via the proxy).
		$this->assertSame( $upload['md5'], md5( (string) $this->load_as_visitor( $upload['id'] ) ), 'The exported file could not be loaded for a visitor.' . $this->get_log_errors() );

		// after the deletion of the file, it is deleted on the external source, too.
		wp_delete_attachment( $upload['id'], true );
		$this->assert_deleted( $name, $term_id, $url, $upload['md5'] );
	}

	/**
	 * Return the URLs of the files in the directory of the external source, whose name contains the given text.
	 *
	 * @param int    $term_id The ID of the external source.
	 * @param string $name    The text, e.g. the name of a file without extension.
	 *
	 * @return array<int,string>
	 */
	private function find_files_on_source( int $term_id, string $name ): array {
		$found = array();
		foreach ( $this->get_files( $term_id ) as $file ) {
			$file_url = (string) ( $file['url'] ?? '' );
			if ( str_contains( rawurldecode( $file_url ), $name ) ) {
				$found[] = $file_url;
			}
		}
		return $found;
	}

	/**
	 * Return the message for a failed export: whether the uploaded file remained on the external source, which the
	 * plugin must prevent.
	 *
	 * @param string $name          The name of the service.
	 * @param int    $term_id       The ID of the external source.
	 * @param int    $attachment_id The attachment ID of the upload.
	 *
	 * @return string
	 */
	private function get_failed_export_details( string $name, int $term_id, int $attachment_id ): string {
		// bail if the service has no directory listing.
		if ( empty( $this->get_definition( $name )['listing'] ) ) {
			return '';
		}

		// search as administrator, as the actual user might not be allowed to use the service.
		$user_id = get_current_user_id();
		wp_set_current_user( 1 );
		try {
			$found = $this->find_files_on_source( $term_id, pathinfo( (string) get_attached_file( $attachment_id, true ), PATHINFO_FILENAME ) );
		} finally {
			wp_set_current_user( $user_id );
		}
		return empty( $found )
			? PHP_EOL . 'No uploaded file remained on the external source.'
			: PHP_EOL . 'ERROR: the uploaded file remained on the external source: ' . implode( ', ', $found );
	}

	/**
	 * Check that the given exported file has been deleted on its external source.
	 *
	 * With directory listing the check uses the API of the service: its public URL could be delivered by the cache of
	 * a CDN for a while (e.g. Cloudflare R2 with own domain), which is reported as warning. Without directory listing
	 * the file must not be delivered anymore via its URL (services may deliver an error page instead, e.g. Dropbox
	 * "File deleted", so only the same content counts).
	 *
	 * @param string $name    The name of the service.
	 * @param int    $term_id The ID of the external source.
	 * @param string $url     The URL of the exported file.
	 * @param string $md5     The MD5 hash of the file.
	 *
	 * @return void
	 */
	private function assert_deleted( string $name, int $term_id, string $url, string $md5 ): void {
		$still_delivered = md5( (string) $this->load_url( $url, $term_id ) ) === $md5;

		// bail if the service has no directory listing: the URL is the only possible check.
		if ( empty( $this->get_definition( $name )['listing'] ) ) {
			$this->assertFalse( $still_delivered, 'The exported file still exists on the external source after its deletion: ' . $url );
			return;
		}

		// search the file in the directory of the external source.
		$found = $this->find_files_on_source( $term_id, pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_FILENAME ) );
		$this->assertEmpty( $found, 'The exported file (or one of its sizes) still exists on the external source after its deletion: ' . implode( ', ', $found ) );

		// report a still delivered public URL (e.g. by the cache of a CDN).
		if ( $still_delivered ) {
			$this->addWarning( 'The file has been deleted on the external source, but its URL still delivers it (cache of a CDN?): ' . $url );
		}
	}
}
