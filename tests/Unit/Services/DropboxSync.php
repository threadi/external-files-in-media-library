<?php
/**
 * Tests for the synchronization of DropBox folders with a simulated DropBox client.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\SynchronizationTrait;

require_once __DIR__ . '/DropboxProtocol.php';

/**
 * Object to test the synchronization of DropBox folders.
 */
class DropboxSync extends externalFilesTests {
	use SynchronizationTrait;

	/**
	 * The simulated client.
	 *
	 * @var DropboxFakeClient|null
	 */
	private ?DropboxFakeClient $client = null;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->use_files( array( '/Photos/Red.jpg', '/Photos/Green.jpg', '/Photos/Sub/Blue.png', '/Photos/Sub/Black.jpg', '/Photos/Sub/White.jpg' ) );
		add_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		$this->enable_synchronization();
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		parent::tear_down();
	}

	/**
	 * Use a simulated client with the given files, which contain real images (WordPress checks their content).
	 *
	 * @param array<int,string> $paths The paths of the files.
	 *
	 * @return void
	 */
	private function use_files( array $paths ): void {
		$files = array();
		foreach ( $paths as $path ) {
			$image          = str_ends_with( $path, '.png' ) ? 'example-blue.png' : 'example-red.jpg';
			$files[ $path ] = (string) file_get_contents( trailingslashit( TESTS_PLUGIN_DIR ) . 'tests/Data/ftp/' . $image ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$this->client = new DropboxFakeClient( $files );
	}

	/**
	 * Return the simulated client.
	 *
	 * @return DropboxFakeClient|null
	 */
	public function get_client(): ?DropboxFakeClient {
		return $this->client;
	}

	/**
	 * Return the names of the synchronized files.
	 *
	 * @return array<int,string>
	 */
	private function get_names(): array {
		$names = array();
		foreach ( array( '/Photos/Red.jpg', '/Photos/Green.jpg', '/Photos/Sub/Blue.png', '/Photos/Sub/Black.jpg', '/Photos/Sub/White.jpg' ) as $path ) {
			if ( Files::get_instance()->get_file_by_url( 'dropbox' . $path ) ) {
				$names[] = $path;
			}
		}
		return $names;
	}

	/**
	 * Test that only the deleted file of a subfolder is deleted with the next synchronization.
	 *
	 * @dataProvider provide_sync_requests
	 *
	 * @param string $request The request, in which the synchronization runs.
	 *
	 * @return void
	 */
	public function test_deleted_file_in_subfolder( string $request ): void {
		$term_id = Taxonomy::get_instance()->add( 'dropbox', 'DropBox/Photos', array() );
		$this->run_sync( $request, 'DropBox/Photos', $term_id );
		$this->assertSame( array( '/Photos/Red.jpg', '/Photos/Green.jpg', '/Photos/Sub/Blue.png', '/Photos/Sub/Black.jpg', '/Photos/Sub/White.jpg' ), $this->get_names() );

		// delete a file of the subfolder in DropBox.
		$this->use_files( array( '/Photos/Red.jpg', '/Photos/Green.jpg', '/Photos/Sub/Blue.png', '/Photos/Sub/White.jpg' ) );
		$this->run_sync( $request, 'DropBox/Photos', $term_id );
		$this->assertSame( array( '/Photos/Red.jpg', '/Photos/Green.jpg', '/Photos/Sub/Blue.png', '/Photos/Sub/White.jpg' ), $this->get_names() );
	}
}
