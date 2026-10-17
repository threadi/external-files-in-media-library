<?php
/**
 * Tests for the proxy with files from a real HTTP server.
 *
 * Start the servers with "bin/test-servers.sh start", otherwise these tests are skipped.
 *
 * The delivery of a file ends with "exit", so these tests check everything before the delivery.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Servers;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ExternalFilesInMediaLibrary\Tests\TestServers;

/**
 * Object to test the proxy cache of external files.
 */
class Proxy extends externalFilesTests {
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
	 * Import the given file of a new directory on the server as external file.
	 *
	 * @param string $filename The file name.
	 *
	 * @return File
	 */
	private function import( string $filename ): File {
		$this->server    = TestServers::get( 'http', $this );
		$this->directory = TestServers::create_directory( $this->server );
		$url             = TestServers::get_url( $this->server, '/files' . $this->directory . $filename );
		$this->assertTrue( @Import::get_instance()->add_url( $url ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		$this->assertInstanceOf( File::class, $external_file_obj );
		$this->assertFalse( $external_file_obj->is_locally_saved() );
		return $external_file_obj;
	}

	/**
	 * Return the path of a file on the server.
	 *
	 * @param string $filename The file name.
	 *
	 * @return string
	 */
	private function get_server_path( string $filename ): string {
		return $this->server['root'] . $this->directory . $filename;
	}

	/**
	 * Test that the import of an external image adds it to the proxy cache with the original content.
	 *
	 * @return void
	 */
	public function test_import_adds_file_to_cache(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );

		$this->assertTrue( $external_file_obj->is_cached() );
		$this->assertFileEquals( $this->get_server_path( 'example-red.jpg' ), $external_file_obj->get_cache_file() );

		// the cache directory is protected against direct access and directory listings.
		$cache_directory = \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_cache_directory();
		$this->assertFileExists( $cache_directory . 'index.php' );
		$this->assertFileExists( $cache_directory . '.htaccess' );
	}

	/**
	 * Test that the URL of the external image in the media library is the proxy URL.
	 *
	 * @return void
	 */
	public function test_attachment_url_is_proxy_url(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );

		$url = (string) wp_get_attachment_url( $external_file_obj->get_id() );
		$this->assertStringContainsString( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), $url );
		$this->assertStringNotContainsString( '127.0.0.1', $url );
	}

	/**
	 * Test that sized images are created in the cache and deleted with the file.
	 *
	 * @return void
	 */
	public function test_sized_images_are_cached_and_deleted(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$attachment_id     = $external_file_obj->get_id();
		$cache_file        = $external_file_obj->get_cache_file();

		// request a smaller size.
		$this->assertIsArray( @wp_get_attachment_image_src( $attachment_id, array( 32, 32 ) ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$sized_files = glob( str_replace( '.jpg', '-*x*.jpg', $cache_file ) );
		$this->assertNotEmpty( $sized_files );

		// deleting the file deletes its cache files.
		$this->assertInstanceOf( \WP_Post::class, wp_delete_attachment( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $cache_file );
		$this->assertSame( array(), glob( str_replace( '.jpg', '-*x*.jpg', $cache_file ) ) );

		// the file on the server is not touched.
		$this->assertFileExists( $this->get_server_path( 'example-red.jpg' ) );
	}

	/**
	 * Test that an expired cache is not used and is replaced with the actual file from the server.
	 *
	 * @return void
	 */
	public function test_expired_cache_is_renewed(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$cache_file        = $external_file_obj->get_cache_file();

		// change the file on the server and let the cache expire (max age is 1 hour).
		update_option( 'eml_images_proxy_max_age', 1 );
		copy( $this->get_server_path( 'example-green.jpg' ), $this->get_server_path( 'example-red.jpg' ) );
		file_put_contents( $this->get_server_path( 'example-red.jpg' ), 'x', FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		touch( $cache_file, time() - 2 * HOUR_IN_SECONDS );
		$this->assertFalse( $external_file_obj->is_cached() );

		// the proxy loads it again (like it does before the delivery).
		@$external_file_obj->add_to_proxy(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertTrue( $external_file_obj->is_cached() );
		$this->assertFileEquals( $this->get_server_path( 'example-red.jpg' ), $cache_file );

		update_option( 'eml_images_proxy_max_age', 0 );
	}

	/**
	 * Test that a file, which is not available anymore, is not cached.
	 *
	 * @return void
	 */
	public function test_unavailable_file_is_not_cached(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$external_file_obj->delete_cache();
		$this->assertFalse( $external_file_obj->is_cached() );

		unlink( $this->get_server_path( 'example-red.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@$external_file_obj->add_to_proxy(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$this->assertFalse( $external_file_obj->is_cached() );
		$this->assertFileDoesNotExist( $external_file_obj->get_cache_file() );
	}

	/**
	 * Test that a file, whose content does not match its file type, is not cached and therefore not delivered.
	 *
	 * @return void
	 */
	public function test_file_with_wrong_content_is_not_cached(): void {
		$this->server    = TestServers::get( 'http', $this );
		$this->directory = TestServers::create_directory( $this->server );

		// a file with the extension .jpg, but with HTML in it.
		file_put_contents( $this->get_server_path( 'fake.jpg' ), '<html><script>alert(1)</script></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$url = TestServers::get_url( $this->server, '/files' . $this->directory . 'fake.jpg' );
		@Import::get_instance()->add_url( $url ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// if it has been imported, it must not be in the cache.
		$external_file_obj = Files::get_instance()->get_file_by_url( $url );
		if ( $external_file_obj instanceof File ) {
			$this->assertFalse( $external_file_obj->is_cached() );
			$this->assertFileDoesNotExist( $external_file_obj->get_cache_file() );
		} else {
			$this->assertFalse( $external_file_obj );
		}
	}

	/**
	 * Test that the proxy does not deliver a file, which could not be cached, and shows the normal template (404).
	 *
	 * @return void
	 */
	public function test_run_with_not_cacheable_file(): void {
		$external_file_obj = $this->import( 'example-red.jpg' );
		$external_file_obj->delete_cache();
		unlink( $this->get_server_path( 'example-red.jpg' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), 'example-red.jpg' );
		$this->assertSame( 'my-template.php', @\ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), '' );
	}
}
