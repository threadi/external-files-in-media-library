<?php
/**
 * Tests for the synchronization of subdirectories, used for each protocol with own listing of directories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Tests for the synchronization of subdirectories.
 *
 * The using class creates the external source with the files of "tests/Data/ftp" (with the file
 * "subdirectory/example-grey.jpg") and returns the URL of its directory, the ID of the external source and
 * the path of the directory in the local file system (to change the files).
 */
trait SubdirectoriesSyncTrait {
	use SynchronizationTrait;

	/**
	 * Create the external source with the test files and their subdirectory.
	 *
	 * The URL is the start of the URLs of the files, the URL for the synchronization could differ ("sync_url").
	 *
	 * @return array{url: string, term_id: int, path: string, sync_url?: string}
	 */
	abstract protected function create_source_with_subdirectories(): array;

	/**
	 * Synchronize the given external source.
	 *
	 * @param string                                                         $request The request: "ajax" or "cron".
	 * @param array{url: string, term_id: int, path: string, sync_url?: string} $source The external source.
	 *
	 * @return void
	 */
	protected function sync_source( string $request, array $source ): void {
		$this->run_sync( $request, $source['sync_url'] ?? $source['url'], $source['term_id'] );
	}

	/**
	 * Enable the synchronization with the extension for subdirectories.
	 *
	 * @return void
	 */
	protected function enable_subdirectories_extension(): void {
		update_option( 'eml_sync_extensions', array( 'sync_subdirectories' ) );
		$this->enable_synchronization();
		Sync_Subdirectories::get_instance()->init();
	}

	/**
	 * Return whether a file with the given URL is in the media library.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	protected function has_file( string $url ): bool {
		return Files::get_instance()->get_file_by_url( $url ) instanceof File;
	}

	/**
	 * Without a setting, only the files directly in the directory are synchronized (the default of the service).
	 *
	 * @return void
	 */
	public function test_subdirectories_are_not_synchronized_by_default(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();

		$this->sync_source( 'cron', $source );

		$this->assertTrue( $this->has_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
	}

	/**
	 * With the setting on the external source, the files of the subdirectory are synchronized.
	 *
	 * @return void
	 */
	public function test_subdirectories_are_synchronized_with_setting_on_source(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );

		$this->sync_source( 'ajax', $source );

		$this->assertTrue( $this->has_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
	}

	/**
	 * The global setting is used for external sources without own setting, "No" on the external source wins.
	 *
	 * @return void
	 */
	public function test_global_setting_and_setting_on_source(): void {
		$this->enable_subdirectories_extension();
		update_option( Sync_Subdirectories::SETTING, 1 );

		// without own setting: the global setting is used.
		$source = $this->create_source_with_subdirectories();
		$this->sync_source( 'cron', $source );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );

		// with "No" on the external source: the files of the subdirectory are deleted.
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::NO );
		$this->sync_source( 'cron', $source );
		$this->assertTrue( $this->has_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
	}

	/**
	 * Without the enabled extension, the setting of the external source is not used.
	 *
	 * @return void
	 */
	public function test_setting_is_not_used_without_extension(): void {
		$this->enable_synchronization();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );

		$this->sync_source( 'cron', $source );

		$this->assertTrue( $this->has_file( $source['url'] . 'example-red.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
	}

	/**
	 * The maximum depth of subdirectories is used: deeper files are not synchronized and deleted if the depth is lowered.
	 *
	 * @return void
	 */
	public function test_maximum_depth(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );

		// add a file in a deeper directory.
		$this->add_test_file( $source['path'] . 'subdirectory/deeper/', 'example-blue.png' );

		// with the default depth, all files are synchronized.
		$this->sync_source( 'cron', $source );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/deeper/example-blue.png' ) );

		// with the depth 1, the file in the deeper directory is deleted.
		add_filter( 'efml_subdirectories_max_depth', static fn() => 1 );
		$this->sync_source( 'cron', $source );
		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'subdirectory/deeper/example-blue.png' ) );
	}

	/**
	 * Excluded directories (e.g. "node_modules" or ".git") are not synchronized.
	 *
	 * @return void
	 */
	public function test_excluded_directories(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );
		$this->add_test_file( $source['path'] . 'node_modules/', 'example-blue.png' );
		$this->add_test_file( $source['path'] . '.git/', 'example-blue.png' );

		$this->sync_source( 'cron', $source );

		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'node_modules/example-blue.png' ) );
		$this->assertFalse( $this->has_file( $source['url'] . '.git/example-blue.png' ) );
	}

	/**
	 * Return the IDs of the files of the given external source.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return array<int,int>
	 */
	protected function get_ids_of_source( int $term_id ): array {
		return array_map(
			'absint',
			get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'any',
					'fields'      => 'ids',
					'numberposts' => -1,
					'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => Taxonomy::get_instance()->get_name(),
							'terms'    => $term_id,
						),
					),
				)
			)
		);
	}

	/**
	 * Copy a test file into the given directory, which is created if necessary.
	 *
	 * @param string $directory The directory with trailing slash.
	 * @param string $file The name of the file in "tests/Data/ftp".
	 *
	 * @return void
	 */
	protected function add_test_file( string $directory, string $file ): void {
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			chmod( $directory, 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		}
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $directory . $file );
	}
}
