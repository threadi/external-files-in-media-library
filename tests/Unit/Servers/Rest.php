<?php
/**
 * Tests with a simulated WordPress REST API on a real HTTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the REST API service with a simulated WordPress REST API (see tests/Servers/http_server.py).
 */
class Rest extends externalFilesTests {
	/**
	 * Prepare each test: 2 files per page, so the pagination is used with the 5 test files.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'eml_rest_limit', 2 );
		update_option( 'eml_rest_max_requests', 100 );
	}

	/**
	 * Return the data of the test server or skip the test.
	 *
	 * @return array<string,mixed>
	 */
	private function get_server(): array {
		return TestServers::get( 'http', $this );
	}

	/**
	 * Return the listing of the REST API service for the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return array<string,mixed>
	 */
	private function get_listing( string $url ): array {
		$service = \ExternalFilesInMediaLibrary\Services\Rest::get_instance();
		$errors  = new \ReflectionProperty( $service, 'errors' );
		$errors->setValue( $service, array() );
		return @$service->get_directory_listing( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Return the error messages of the REST API service.
	 *
	 * @return string
	 */
	private function get_listing_errors(): string {
		$messages = array();
		foreach ( \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_errors() as $error ) {
			$messages = array_merge( $messages, $error->get_error_messages() );
		}
		return implode( ' ', $messages );
	}

	/**
	 * Test the listing of a website: the first page with its files and all pages as directories.
	 *
	 * @return void
	 */
	public function test_listing(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$listing   = $this->get_listing( TestServers::get_url( $server, '/sites' . $directory ) );
		$this->assertSame( '', $this->get_listing_errors() );

		// 5 files with 2 per page result in 3 pages.
		$this->assertCount( 3, $listing['dirs'] );

		// the first page contains the first 2 files (sorted by name) with their URL on the server.
		$this->assertSame( array( 'example-blue.png', 'example-broken.gif' ), array_column( $listing['files'], 'title' ) );
		$this->assertSame( TestServers::get_url( $server, '/files' . $directory . 'example-blue.png' ), $listing['files'][0]['file'] );
		$this->assertSame( 155, $listing['files'][0]['filesize'] );
	}

	/**
	 * Test the listing of a further page.
	 *
	 * @return void
	 */
	public function test_listing_of_page(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$listing   = $this->get_listing( TestServers::get_url( $server, '/sites' . $directory ) );

		// load the second page.
		$pages   = array_keys( $listing['dirs'] );
		$listing = $this->get_listing( $pages[1] );
		$this->assertSame( '', $this->get_listing_errors() );
		$this->assertSame( array( 'example-green.jpg', 'example-red.jpg' ), array_column( $listing['files'], 'title' ) );
	}

	/**
	 * Test the listing of a website without pretty permalinks, which only has "?rest_route=".
	 *
	 * @return void
	 */
	public function test_listing_without_pretty_permalinks(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$listing   = $this->get_listing( TestServers::get_url( $server, '/plain-sites' . $directory ) );
		$this->assertSame( '', $this->get_listing_errors() );
		$this->assertCount( 3, $listing['dirs'] );
		$this->assertSame( array( 'example-blue.png', 'example-broken.gif' ), array_column( $listing['files'], 'title' ) );
	}

	/**
	 * Test the listing of a URL without WordPress REST API.
	 *
	 * @return void
	 */
	public function test_listing_without_rest_api(): void {
		$this->assertSame( array(), $this->get_listing( TestServers::get_url( $this->get_server(), '/files' . TestServers::create_directory( $this->get_server() ) ) ) );
		$this->assertStringContainsString( 'No WordPress REST API', $this->get_listing_errors() );
	}

	/**
	 * Test that the own website can not be used.
	 *
	 * @return void
	 */
	public function test_listing_of_own_website(): void {
		$this->get_server();
		$this->assertSame( array(), $this->get_listing( home_url() ) );
		$this->assertStringContainsString( 'Your own website', $this->get_listing_errors() );
	}

	/**
	 * Test the synchronization of a website via cron (without user): files are imported, deleted files removed.
	 *
	 * @return void
	 */
	public function test_synchronization_via_cron(): void {
		$server    = $this->get_server();
		$directory = TestServers::create_directory( $server );
		$url       = TestServers::get_url( $server, '/sites' . $directory );
		$files_url = TestServers::get_url( $server, '/files' . $directory );

		// remove synchronized files, which are no longer on the server.
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// run like a scheduled synchronization: via cron, without a user.
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		\ExternalFilesInMediaLibrary\Services\Rest::get_instance()->init();

		// create the external source and synchronize it.
		$term_id = Taxonomy::get_instance()->add( 'rest', $url, array() );
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// all files with an allowed file type from all pages are imported.
		$red = Files::get_instance()->get_file_by_url( $files_url . 'example-red.jpg' );
		$this->assertInstanceOf( File::class, $red );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $files_url . 'example-green.jpg' ) );
		$this->assertInstanceOf( File::class, Files::get_instance()->get_file_by_url( $files_url . 'example-blue.png' ) );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $files_url . 'example.txt' ) );

		// the files are assigned to the external source.
		$this->assertTrue( has_term( $term_id, Taxonomy::get_instance()->get_name(), $red->get_id() ) );

		// delete a file on the server and synchronize again.
		unlink( $server['root'] . $directory . 'example-green.jpg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( $red->get_id(), Files::get_instance()->get_file_by_url( $files_url . 'example-red.jpg' )->get_id() );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $files_url . 'example-green.jpg' ) );

		remove_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
	}
}
