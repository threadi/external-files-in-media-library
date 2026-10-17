<?php
/**
 * Tests for the checks of the proxy cache directory.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;

/**
 * Object to test the checks of the proxy cache directory, e.g. whether it is publicly reachable.
 */
class ProxyChecks extends externalFilesTests {
	/**
	 * How the simulated web server answers requests to the cache directory.
	 *
	 * "deny" (403), "serve" (200 with the file), "soft404" (200 with another content) or "fail" (no connection).
	 *
	 * @var string
	 */
	private string $web_server = 'deny';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'answer_request' ), 5, 3 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'answer_request' ), 5 );
		remove_all_filters( 'efml_proxy_path' );
		parent::tear_down();
	}

	/**
	 * Answer requests to the cache directory like the web server.
	 *
	 * @param false|array<string,mixed>|WP_Error $response The response.
	 * @param array<string,mixed>                $parsed_args The arguments.
	 * @param string                             $url The URL.
	 *
	 * @return false|array<string,mixed>|WP_Error
	 */
	public function answer_request( false|array|WP_Error $response, array $parsed_args, string $url ): false|array|WP_Error {
		if ( ! str_starts_with( $url, content_url() ) ) {
			return $response;
		}

		if ( 'fail' === $this->web_server ) {
			return new WP_Error( 'http_request_failed', 'Loopback requests are blocked.' );
		}

		$body = 'Not found';
		$code = 403;
		if ( 'serve' === $this->web_server ) {
			$code = 200;
			$body = (string) file_get_contents( str_replace( content_url(), WP_CONTENT_DIR, $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( 'soft404' === $this->web_server ) {
			$code = 200;
			$body = '<html><body>Page not found</body></html>';
		}

		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Test the URL of the cache directory.
	 *
	 * @return void
	 */
	public function test_get_cache_url(): void {
		// in the content directory it has a URL.
		$this->assertSame( trailingslashit( content_url() ) . 'cache/eml/', Proxy::get_instance()->get_cache_url() );

		// outside the content directory it has no URL.
		$directory = trailingslashit( get_temp_dir() ) . 'efml-proxy-' . wp_generate_password( 8, false, false ) . '/';
		add_filter(
			'efml_proxy_path',
			function () use ( $directory ) {
				return $directory;
			}
		);
		$this->assertSame( '', Proxy::get_instance()->get_cache_url() );
		Proxy::get_instance()->delete_cache_directory();
	}

	/**
	 * Test the check whether the cache directory is publicly reachable.
	 *
	 * @return void
	 */
	public function test_is_cache_directory_public(): void {
		// the web server denies the access, like the .htaccess of the directory does it on Apache.
		$this->web_server = 'deny';
		$this->assertFalse( Proxy::get_instance()->is_cache_directory_public() );

		// the web server delivers the files, like nginx without a rule for the directory.
		$this->web_server = 'serve';
		$this->assertTrue( Proxy::get_instance()->is_cache_directory_public() );

		// the web server answers with 200 but another content (e.g. a page for not found files).
		$this->web_server = 'soft404';
		$this->assertFalse( Proxy::get_instance()->is_cache_directory_public() );

		// the request does not reach the web server.
		$this->web_server = 'fail';
		$this->assertNull( Proxy::get_instance()->is_cache_directory_public() );
	}

	/**
	 * Test the check for the site health.
	 *
	 * @return void
	 */
	public function test_check_proxy(): void {
		$this->web_server = 'deny';
		$this->assertSame( 'good', Proxy::get_instance()->check_proxy()['status'] );

		// a public directory results in a hint with the rule for nginx.
		$this->web_server = 'serve';
		$result           = Proxy::get_instance()->check_proxy();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'location ^~ ' . wp_parse_url( Proxy::get_instance()->get_cache_url(), PHP_URL_PATH ), $result['description'] );

		// a check, which could not be run, results in a hint.
		$this->web_server = 'fail';
		$result           = Proxy::get_instance()->check_proxy();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'could not be checked', $result['label'] );
	}

	/**
	 * Test whether any proxy is enabled and whether it is used for a file.
	 *
	 * @return void
	 */
	public function test_proxy_settings(): void {
		$proxy = Proxy::get_instance();

		// the proxy for images is enabled by default.
		$this->assertTrue( $proxy->is_any_proxy_enabled() );

		// a disabled proxy for a file is not enabled again.
		$attachment_id = self::factory()->attachment->create_object( array( 'file' => 'image.jpg', 'post_mime_type' => 'image/jpeg' ) );
		$file          = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file( $attachment_id );
		$file->set_url( 'https://example.com/image.jpg' );
		$this->assertFalse( $proxy->prevent_proxied_url( false, $file ) );
		$this->assertTrue( $proxy->prevent_proxied_url( true, $file ) );
	}
}
