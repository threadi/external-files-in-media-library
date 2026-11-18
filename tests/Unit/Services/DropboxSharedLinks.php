<?php
/**
 * Tests for the shared links of Dropbox: exports, imports and exported files with a temporary URL.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use Exception;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\DropBox\Export;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionMethod;
use Spatie\Dropbox\Client;

/**
 * Simulated Dropbox client for shared links and downloads.
 */
class DropboxSharedLinksClient extends Client {
	/**
	 * The files with their path (in lower case) and content.
	 *
	 * @var array<string,string>
	 */
	public array $files = array();

	/**
	 * The existing shared links per path (in lower case).
	 *
	 * @var array<string,string>
	 */
	public array $links = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'test-token' );
	}

	/**
	 * Create a shared link, like Dropbox fail if the file has already one.
	 *
	 * @param string              $path The path.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 * @throws Exception If the file has already a shared link.
	 */
	public function createSharedLinkWithSettings( string $path, array $settings = array() ): array {
		if ( isset( $this->links[ $path ] ) ) {
			throw new Exception( 'shared_link_already_exists' );
		}
		$this->links[ $path ] = 'https://www.dropbox.com/scl/fi/new' . $path . '?rlkey=abc&dl=0';
		return array( 'url' => $this->links[ $path ] );
	}

	/**
	 * Return the shared links of a path.
	 *
	 * @param string|null $path The path.
	 * @param bool        $direct_only Only links to the path itself.
	 * @param string|null $cursor The cursor.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function listSharedLinks( ?string $path = null, bool $direct_only = false, ?string $cursor = null ): array {
		return isset( $this->links[ (string) $path ] ) ? array( array( 'url' => $this->links[ (string) $path ] ) ) : array();
	}

	/**
	 * Return the content of a file as stream.
	 *
	 * @param string $path The path.
	 *
	 * @return resource
	 * @throws Exception If the file does not exist.
	 */
	public function download( string $path ) {
		if ( ! isset( $this->files[ strtolower( $path ) ] ) ) {
			throw new Exception( 'path/not_found' );
		}
		$stream = fopen( 'php://memory', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $stream, $this->files[ strtolower( $path ) ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $stream );
		return $stream;
	}
}

/**
 * Object to test the shared links of Dropbox.
 */
class DropboxSharedLinks extends externalFilesTests {
	/**
	 * The simulated client.
	 *
	 * @var DropboxSharedLinksClient|null
	 */
	private ?DropboxSharedLinksClient $client = null;

	/**
	 * The requested URLs of HTTP requests.
	 *
	 * @var array<int,string>
	 */
	private array $requests = array();

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->client   = new DropboxSharedLinksClient();
		$this->requests = array();
		add_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		add_filter( 'pre_http_request', array( $this, 'prevent_http_requests' ), 1, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		remove_filter( 'pre_http_request', array( $this, 'prevent_http_requests' ), 1 );
		parent::tear_down();
	}

	/**
	 * Return the simulated client.
	 *
	 * @return DropboxSharedLinksClient|null
	 */
	public function get_client(): ?DropboxSharedLinksClient {
		return $this->client;
	}

	/**
	 * Prevent HTTP requests and remember their URL.
	 *
	 * @param mixed               $response The response.
	 * @param array<string,mixed> $args The arguments.
	 * @param string              $url The URL.
	 *
	 * @return \WP_Error
	 */
	public function prevent_http_requests( mixed $response, array $args, string $url ): \WP_Error {
		$this->requests[] = $url;
		return new \WP_Error( 'http_request_failed', 'No HTTP requests in tests.' );
	}

	/**
	 * Return the shared link of an uploaded file via the export object.
	 *
	 * @param string $path The path in Dropbox.
	 *
	 * @return string|false
	 */
	private function get_shared_link( string $path ): string|false {
		$method = new ReflectionMethod( Export::class, 'get_shared_link' );
		$method->setAccessible( true );
		return $method->invoke( Export::get_instance(), array( 'path_lower' => $path ), 'DropBox' . $path );
	}

	/**
	 * Test if the export uses the shared link itself (with "raw=1") and not the temporary URL it forwards to.
	 *
	 * @return void
	 */
	public function test_export_uses_shared_link(): void {
		$url = $this->get_shared_link( '/photos/red.jpg' );
		$this->assertEquals( 'https://www.dropbox.com/scl/fi/new/photos/red.jpg?rlkey=abc&raw=1', $url );

		// no HTTP request for the target of the forwarding.
		$this->assertEmpty( $this->requests );
	}

	/**
	 * Test if the export uses the existing shared link of a file (before, the export failed then).
	 *
	 * @return void
	 */
	public function test_export_uses_existing_shared_link(): void {
		$this->client->links['/photos/red.jpg'] = 'https://www.dropbox.com/scl/fi/old/photos/red.jpg?rlkey=xyz&dl=0';
		$this->assertEquals( 'https://www.dropbox.com/scl/fi/old/photos/red.jpg?rlkey=xyz&raw=1', $this->get_shared_link( '/photos/red.jpg' ) );
	}

	/**
	 * Test the detection and conversion of shared links, also for imports.
	 *
	 * @return void
	 */
	public function test_shared_links(): void {
		$dropbox = DropBox::get_instance();

		$this->assertTrue( $dropbox->is_shared_link( 'https://www.dropbox.com/scl/fi/abc/file.jpg?rlkey=x&dl=0' ) );
		$this->assertTrue( $dropbox->is_shared_link( 'https://www.dropbox.com/s/abc/file.jpg?dl=0' ) );
		$this->assertFalse( $dropbox->is_shared_link( 'https://www.dropbox.com/home' ) );
		$this->assertFalse( $dropbox->is_shared_link( 'https://uc123.dl.dropboxusercontent.com/cd/0/inline/abc/file' ) );
		$this->assertFalse( $dropbox->is_shared_link( 'https://www.dropbox.com.example.com/s/abc/file.jpg' ) );

		// the import saves the shared link itself, without a request for the target of its forwarding.
		$this->assertEquals( 'https://www.dropbox.com/scl/fi/abc/file.jpg?rlkey=x&raw=1', $dropbox->convert_dropbox_urls( 'https://www.dropbox.com/scl/fi/abc/file.jpg?rlkey=x&dl=0' ) );
		$this->assertEquals( 'https://example.com/file.jpg', $dropbox->convert_dropbox_urls( 'https://example.com/file.jpg' ) );
		$this->assertEmpty( $this->requests );
	}

	/**
	 * Test if HTTP requests follow the forwarding of shared links, but not of other URLs.
	 *
	 * @return void
	 */
	public function test_forwarding_of_shared_links(): void {
		$dropbox = DropBox::get_instance();
		$http    = new Http( 'https://www.dropbox.com/scl/fi/abc/file.jpg?rlkey=x&raw=1' );

		// the arguments of the plugin do not follow forwardings.
		$args = $http->get_header_args();
		$this->assertEquals( 0, $args['redirection'] );

		// for shared links they do (the filter is used for all requests).
		$this->assertEquals( 3, apply_filters( 'http_request_args', $args, 'https://www.dropbox.com/scl/fi/abc/file.jpg?rlkey=x&raw=1' )['redirection'] );
		$this->assertEquals( 0, $dropbox->follow_forwarding_of_shared_links( $args, 'https://example.com/file.jpg' )['redirection'] );
		$this->assertEquals( 0, $dropbox->follow_forwarding_of_shared_links( $args, 'https://uc1.dl.dropboxusercontent.com/cd/0/inline/abc/file' )['redirection'] );
	}

	/**
	 * Test if an exported file with a temporary URL of Dropbox (saved before 6.0.0) is loaded via the API.
	 *
	 * @return void
	 */
	public function test_exported_file_with_temporary_url_is_loaded_via_api(): void {
		// an exported file with a temporary URL, like the export saved it before 6.0.0.
		$url           = 'https://uc1.dl.dropboxusercontent.com/cd/0/inline/abc/file';
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		Files::get_instance()->get_file( $attachment_id )->set_url( $url );
		update_post_meta( $attachment_id, 'efml_dropbox_path', '/photos/red.jpg' );
		$this->client->files['/photos/red.jpg'] = 'red-content';

		// the HTTP protocol loads it via the API, without HTTP request.
		$tmp_file = ( new Http( $url ) )->get_temp_file( $url, Helper::get_wp_filesystem() );
		$this->assertIsString( $tmp_file );
		$this->assertEquals( 'red-content', file_get_contents( $tmp_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertEmpty( $this->requests );
		wp_delete_file( $tmp_file );

		// without the path in Dropbox (e.g. an imported file) it is downloaded via HTTP as before.
		delete_post_meta( $attachment_id, 'efml_dropbox_path' );
		$this->assertFalse( ( new Http( $url ) )->get_temp_file( $url, Helper::get_wp_filesystem() ) );
		$this->assertEquals( array( $url ), $this->requests );

		// without a connection to Dropbox (e.g. for visitors, if each user has its own) too.
		update_post_meta( $attachment_id, 'efml_dropbox_path', '/photos/red.jpg' );
		$this->client   = null;
		$this->requests = array();
		add_filter( 'efml_dropbox_client', '__return_null', 20 );
		$this->assertNull( DropBox::get_instance()->load_exported_file_via_api( null, $url ) );
		remove_filter( 'efml_dropbox_client', '__return_null', 20 );
	}

	/**
	 * Test if other URLs are not loaded via the API.
	 *
	 * @return void
	 */
	public function test_other_urls_are_not_loaded_via_api(): void {
		$this->assertNull( DropBox::get_instance()->load_exported_file_via_api( null, 'https://example.com/file.jpg' ) );
		$this->assertNull( DropBox::get_instance()->load_exported_file_via_api( null, 'https://uc1.dl.dropboxusercontent.com/cd/0/inline/unknown/file' ) );
		$this->assertEquals( '/tmp/other', DropBox::get_instance()->load_exported_file_via_api( '/tmp/other', 'https://uc1.dl.dropboxusercontent.com/cd/0/inline/abc/file' ) );
	}
}
