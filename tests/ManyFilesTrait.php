<?php
/**
 * Trait with the tests for directories with many files on the test servers.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;

/**
 * Tests for directories with many files: the file list and the import via import run.
 *
 * The count of files could be changed with the environment variables EFML_TEST_LIST_COUNT (default 10000) and
 * EFML_TEST_IMPORT_COUNT (default 300).
 */
trait ManyFilesTrait {
	/**
	 * Create a directory with the given count of files on the given test server and return its path.
	 *
	 * @param array<string,mixed> $server The test server.
	 * @param int                 $count The count of files.
	 *
	 * @return string
	 */
	protected function create_directory_with_many_files( array $server, int $count ): string {
		$name = 'many-' . strtolower( wp_generate_password( 8, false, false ) );
		$path = trailingslashit( (string) $server['root'] ) . $name;
		mkdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		chmod( $path, 0777 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
		$source = trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg';
		for ( $i = 1; $i <= $count; $i++ ) {
			copy( $source, $path . '/file-' . $i . '.jpg' );
		}
		TestServers::add_directory_for_cleanup( $path );
		return '/' . $name . '/';
	}

	/**
	 * Test the file list of a directory with many files: all files are listed page by page.
	 *
	 * @return void
	 */
	public function test_file_list_with_many_files(): void {
		$server    = $this->get_server();
		$count     = getenv( 'EFML_TEST_LIST_COUNT' ) ? absint( getenv( 'EFML_TEST_LIST_COUNT' ) ) : 10000;
		$directory = $this->create_directory_with_many_files( $server, $count );
		$url       = TestServers::get_url( $server, $directory );
		$protocol  = Protocols::get_instance()->get_protocol_object_for_url( $url, TestServers::get_fields( $server ) );
		$this->assertNotFalse( $protocol );
		$protocol->set_fields( TestServers::get_fields( $server ) );
		$this->assertTrue( $protocol->supports_file_list() );
		$this->assertTrue( $protocol->is_directory() );

		// load all pages.
		$urls   = array();
		$pages  = 0;
		$cursor = '';
		do {
			$page = $protocol->get_file_list( $cursor );
			$this->assertIsArray( $page );
			foreach ( $page['files'] as $file ) {
				$urls[ $file['url'] ] = true;
			}
			$cursor = $page['cursor'];
			++$pages;
		} while ( '' !== $cursor );

		$this->assertCount( $count, $urls );
		$this->assertEquals( (int) ceil( $count / 500 ), $pages );
		$this->assertArrayHasKey( TestServers::get_url( $server, $directory . 'file-1.jpg' ), $urls );
	}

	/**
	 * Test the import of a directory with many files via import run: all files are imported, also in the
	 * background, and a second import skips them.
	 *
	 * @return void
	 */
	public function test_import_of_directory_with_many_files(): void {
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'efml_import_runs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'eml_queue' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$server    = $this->get_server();
		$count     = getenv( 'EFML_TEST_IMPORT_COUNT' ) ? absint( getenv( 'EFML_TEST_IMPORT_COUNT' ) ) : 300;
		$directory = $this->create_directory_with_many_files( $server, $count );
		$url       = TestServers::get_url( $server, $directory );
		$before    = $this->get_media_file_count();

		// import the directory and process the run in the background until it is finished.
		$import = Import::get_instance();
		$import->set_fields( TestServers::get_fields( $server ) );
		$this->assertTrue( $import->add_url( $url ) );
		$runs = Import_Runs::get_instance();
		for ( $i = 0; $i < 100 && $runs->get_progress()['runs'] > 0; $i++ ) {
			$runs->process();
		}

		$this->assertEquals( 0, $runs->get_progress()['runs'] );
		$this->assertEquals( $before + $count, $this->get_media_file_count() );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( TestServers::get_url( $server, $directory . 'file-' . $count . '.jpg' ) ) );

		// a second import does not import them again.
		$this->assertFalse( $import->add_url( $url ) );
		$this->assertEquals( $before + $count, $this->get_media_file_count() );
		$import->set_fields( array() );
	}
}
