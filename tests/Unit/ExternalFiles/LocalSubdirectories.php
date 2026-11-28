<?php
/**
 * Tests for the synchronization of subdirectories of local directories.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Sync_Subdirectories;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\File;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SubdirectoriesSyncTrait;

/**
 * Object to test the synchronization of subdirectories of local directories.
 */
class LocalSubdirectories extends externalFilesTests {
	use SubdirectoriesSyncTrait;

	/**
	 * The created directories.
	 *
	 * @var array<int,string>
	 */
	private array $directories = array();

	/**
	 * Remove the created directories.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->directories as $directory ) {
			$this->remove_directory( $directory );
		}
		$this->directories = array();
		parent::tear_down();
	}

	/**
	 * Remove the given directory with its contents (links are removed, not followed).
	 *
	 * @param string $directory The directory.
	 *
	 * @return void
	 */
	private function remove_directory( string $directory ): void {
		foreach ( (array) scandir( $directory ) as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$path = $directory . '/' . $name;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$this->remove_directory( $path );
				continue;
			}
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Create the external source with the test files and their subdirectory.
	 *
	 * @return array{url: string, term_id: int, path: string}
	 */
	protected function create_source_with_subdirectories(): array {
		// create the directory with the files.
		$path                = (string) realpath( get_temp_dir() ) . '/efml-subdirectories-' . uniqid();
		$this->directories[] = $path;
		$path                = trailingslashit( $path );
		foreach ( array( 'example-red.jpg', 'example-green.jpg', 'example-blue.png' ) as $file ) {
			$this->add_test_file( $path, $file );
		}
		mkdir( $path . 'subdirectory' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		copy( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/subdirectory/example-grey.jpg', $path . 'subdirectory/example-grey.jpg' );

		// create the external source.
		$term_id = Taxonomy::get_instance()->add( 'local', 'file://' . $path, array( 'path' => array( 'value' => $path ) ) );
		$this->assertGreaterThan( 0, $term_id );

		return array(
			'url'     => 'file://' . $path,
			'term_id' => $term_id,
			'path'    => $path,
		);
	}

	/**
	 * Links to directories are not followed, links to files are used like before.
	 *
	 * @return void
	 */
	public function test_links_to_directories_are_not_followed(): void {
		$this->enable_subdirectories_extension();
		$source = $this->create_source_with_subdirectories();
		update_term_meta( $source['term_id'], Sync_Subdirectories::SETTING, Sync_Subdirectories::YES );

		// add a link to another directory with a file.
		$other               = (string) realpath( get_temp_dir() ) . '/efml-subdirectories-other-' . uniqid();
		$this->directories[] = $other;
		$this->add_test_file( trailingslashit( $other ), 'example-blue.png' );
		symlink( $other, $source['path'] . 'linked' );

		$this->sync_source( 'cron', $source );

		$this->assertTrue( $this->has_file( $source['url'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertFalse( $this->has_file( $source['url'] . 'linked/example-blue.png' ) );
	}

	/**
	 * The directory of a local file relative to the external source is returned, also for the URL with "file://".
	 *
	 * @return void
	 */
	public function test_relative_directory(): void {
		$source       = $this->create_source_with_subdirectories();
		$protocol_obj = new File( $source['url'] );
		$this->assertSame( '', $protocol_obj->get_relative_directory( $source['path'] . 'example-red.jpg' ) );
		$this->assertSame( 'subdirectory', $protocol_obj->get_relative_directory( $source['path'] . 'subdirectory/example-grey.jpg' ) );
		$this->assertSame( 'subdirectory', $protocol_obj->get_relative_directory( $source['url'] . 'subdirectory/example-grey.jpg' ) );
	}

	/**
	 * The excluded directories are also hidden in the directory listing of local directories.
	 *
	 * @return void
	 */
	public function test_excluded_directories_in_listing(): void {
		$local = \ExternalFilesInMediaLibrary\Services\Local::get_instance();
		$path  = (string) realpath( get_temp_dir() ) . '/efml-subdirectories-git-' . uniqid();
		$this->directories[] = $path;
		mkdir( $path . '/.svn', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		mkdir( $path . '/images', 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		$this->assertTrue( $local->hide_generic_directories( false, $path . '/.svn', $path ) );
		$this->assertFalse( $local->hide_generic_directories( false, $path . '/images', $path ) );
	}
}
