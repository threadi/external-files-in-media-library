<?php
/**
 * Tests for ZIP files as external source with a real HTTP server.
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
use ZipArchive;

/**
 * Object to test ZIP files as external source: opening, listing and synchronization.
 */
class Zip extends externalFilesTests {
	/**
	 * The data of the used server.
	 *
	 * @var array<string,mixed>
	 */
	private array $server = array();

	/**
	 * The directory on the server.
	 *
	 * @var string
	 */
	private string $directory = '';

	/**
	 * Prepare each test: ZIP files must be allowed in the settings, like for every import of ZIP files.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'efml_get_mime_types', array( $this, 'allow_zip' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_get_mime_types', array( $this, 'allow_zip' ) );
		remove_filter( 'wp_doing_cron', '__return_true' );
		update_option( 'eml_sync_delete_unused_files_after_sync', 0 );
		parent::tear_down();
	}

	/**
	 * Allow ZIP files.
	 *
	 * @param array<int,string> $mime_types List of mime types.
	 *
	 * @return array<int,string>
	 */
	public function allow_zip( array $mime_types ): array {
		$mime_types[] = 'application/zip';
		return $mime_types;
	}

	/**
	 * Create the ZIP file on the HTTP server with the given test files and return its URL.
	 *
	 * @param array<string,string> $files List of test files (name in ZIP => test file).
	 *
	 * @return string
	 */
	private function create_zip( array $files ): string {
		if ( empty( $this->directory ) ) {
			$this->server    = TestServers::get( 'http', $this );
			$this->directory = TestServers::create_directory( $this->server );
		}

		$path = $this->server['root'] . $this->directory . 'archive.zip';
		if ( file_exists( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		$zip = new ZipArchive();
		$zip->open( $path, ZipArchive::CREATE );
		foreach ( $files as $name => $file ) {
			$zip->addFile( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $file, $name );
		}

		// a file with a type unknown to WordPress, which must be ignored.
		$zip->addFromString( 'data.unknown', 'not allowed' );
		$zip->close();

		// the URL of the ZIP like it is entered for the external source.
		return TestServers::get_url( $this->server, '/files' . $this->directory . 'archive.zip' );
	}

	/**
	 * Synchronize the given external source.
	 *
	 * @param string $url The URL.
	 * @param int    $term_id The term ID.
	 *
	 * @return void
	 */
	private function sync( string $url, int $term_id ): void {
		@Synchronization::get_instance()->sync( $url, Taxonomy::get_instance()->get_entry( $term_id ), $term_id ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Return the URLs of the files assigned to the given external source with their attachment IDs.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return array<string,int>
	 */
	private function get_files_of_source( int $term_id ): array {
		$ids = get_posts(
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
		);

		$files = array();
		foreach ( $ids as $id ) {
			$external_file_obj = Files::get_instance()->get_file( absint( $id ) );
			$this->assertInstanceOf( File::class, $external_file_obj );
			$files[ $external_file_obj->get_url( true ) ] = absint( $id );
		}
		ksort( $files );
		return $files;
	}

	/**
	 * Test the synchronization of a ZIP file via cron, without a user.
	 *
	 * The files in it are imported and saved locally, removed files are deleted with the next synchronization.
	 *
	 * @return void
	 */
	public function test_synchronization_via_cron(): void {
		$url = $this->create_zip(
			array(
				'example-red.jpg'         => 'example-red.jpg',
				'images/example-blue.png' => 'example-blue.png',
			)
		);
		update_option( 'eml_sync_delete_unused_files_after_sync', 1 );

		// run like a scheduled synchronization: via cron, without a user.
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		\ExternalFilesInMediaLibrary\Services\Zip::get_instance()->init();

		$term_id = Taxonomy::get_instance()->add( 'zip', $url, array() );
		$this->assertGreaterThan( 0, $term_id );
		$this->sync( $url, $term_id );

		// the image files of the ZIP (also from its subdirectory) are imported, not the ZIP itself or the unknown file.
		$files = $this->get_files_of_source( $term_id );
		$this->assertSame( array( $url . '/example-red.jpg', $url . '/images/example-blue.png' ), array_keys( $files ) );
		foreach ( $files as $id ) {
			$external_file_obj = Files::get_instance()->get_file( $id );
			$this->assertTrue( $external_file_obj->is_locally_saved() );
			$this->assertSame( 'zip', $external_file_obj->get_service_name() );
		}
		$this->assertFileEquals( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg', (string) get_attached_file( $files[ $url . '/example-red.jpg' ], true ) );

		// a second synchronization keeps the files.
		$this->sync( $url, $term_id );
		$this->assertSame( $files, $this->get_files_of_source( $term_id ) );

		// remove a file from the ZIP and add another: the next synchronization follows.
		$this->create_zip(
			array(
				'example-red.jpg'   => 'example-red.jpg',
				'example-green.jpg' => 'example-green.jpg',
			)
		);
		$this->sync( $url, $term_id );
		$new_files = $this->get_files_of_source( $term_id );
		$this->assertSame( array( $url . '/example-green.jpg', $url . '/example-red.jpg' ), array_keys( $new_files ) );
		$this->assertSame( $files[ $url . '/example-red.jpg' ], $new_files[ $url . '/example-red.jpg' ] );
		$this->assertNull( get_post( $files[ $url . '/images/example-blue.png' ] ) );
	}

	/**
	 * Test that other imports of ZIP files are not changed: without the ZIP service, the ZIP is a single file.
	 *
	 * @return void
	 */
	public function test_import_of_zip_without_zip_service(): void {
		$url = $this->create_zip( array( 'example-red.jpg' => 'example-red.jpg' ) );

		$this->assertTrue( @\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertSame( 'application/zip', $external_file_obj->get_mime_type() );
		$this->assertFalse( Files::get_instance()->get_file_by_url( $url . '/example-red.jpg' ) );
	}

	/**
	 * Return the errors of the ZIP service as text and reset them.
	 *
	 * @return string
	 */
	private function get_errors(): string {
		$service  = \ExternalFilesInMediaLibrary\Services\Zip::get_instance();
		$messages = array();
		foreach ( $service->get_errors() as $error ) {
			$messages = array_merge( $messages, $error->get_error_messages() );
		}
		$errors = new \ReflectionProperty( $service, 'errors' );
		$errors->setValue( $service, array() );
		return implode( ' ', $messages );
	}

	/**
	 * Test the check of a ZIP file before it is opened as external source.
	 *
	 * @return void
	 */
	public function test_open_zip(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Zip::get_instance();
		$this->get_errors();

		// a valid ZIP.
		$url = $this->create_zip( array( 'example-red.jpg' => 'example-red.jpg' ) );
		$this->assertTrue( @$service->do_login( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( '', $this->get_errors() );

		// without a URL.
		$this->assertFalse( @$service->do_login( '' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertStringContainsString( 'No ZIP-file given', $this->get_errors() );

		// a ZIP, which does not exist on the server.
		$this->assertFalse( @$service->do_login( str_replace( 'archive.zip', 'missing.zip', $url ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertNotSame( '', $this->get_errors() );

		// a file, which is not a ZIP.
		file_put_contents( $this->server['root'] . $this->directory . 'broken.zip', 'no zip' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$this->assertFalse( @$service->do_login( str_replace( 'archive.zip', 'broken.zip', $url ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Test the listing of a ZIP file on a server.
	 *
	 * @return void
	 */
	public function test_listing_of_zip(): void {
		$url = $this->create_zip(
			array(
				'example-red.jpg'         => 'example-red.jpg',
				'images/example-blue.png' => 'example-blue.png',
			)
		);

		$listing = @\ExternalFilesInMediaLibrary\Services\Zip::get_instance()->get_directory_listing( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertSame( array( 'example-red.jpg' ), array_column( $listing[ $url ]['files'], 'title' ) );
		$this->assertSame( array( $url . '/images/' ), array_keys( $listing[ $url ]['dirs'] ) );
		$this->assertSame( array( $url . '/images/example-blue.png' ), array_column( $listing[ $url . '/images/' ]['files'], 'file' ) );
		$this->assertSame( filesize( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/example-red.jpg' ), $listing[ $url ]['files'][0]['filesize'] );

		// a file, which is not an archive.
		$this->assertSame( array(), @\ExternalFilesInMediaLibrary\Services\Zip::get_instance()->get_directory_listing( str_replace( 'archive.zip', 'example-red.jpg', $url ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
