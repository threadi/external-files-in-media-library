<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Rest.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Rest.
 */
class Rest extends externalFilesTests {
	/**
	 * The URL of the external REST API used in the tests.
	 *
	 * @var string
	 */
	private string $api_url = 'https://api.example.com/wp-json/wp/v2/media?per_page=10&page=1';

	/**
	 * The response the HTTP mock should return.
	 *
	 * @var array
	 */
	private array $response = array();

	/**
	 * List of requested URLs.
	 *
	 * @var array<int,string>
	 */
	private array $requested_urls = array();

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->restore_http_mock( array( $this, 'mock_http' ) );
		$this->requested_urls = array();
		parent::tear_down();
	}

	/**
	 * Return the configured response for every request.
	 *
	 * @param false|array|WP_Error $false The return value.
	 * @param array                $parsed_args The used parameters.
	 * @param string               $url The requested URL.
	 *
	 * @return array|WP_Error
	 */
	public function mock_http( false|array|WP_Error $false, array $parsed_args, string $url ): array|WP_Error {
		$this->requested_urls[] = $url;
		if ( empty( $this->response ) ) {
			return new WP_Error( 'test', 'No response configured.' );
		}
		return $this->response;
	}

	/**
	 * Return whether the filters for the requests of the REST API service are still active.
	 *
	 * @return bool
	 */
	private function has_request_filters(): bool {
		$obj = \ExternalFilesInMediaLibrary\Services\Rest::get_instance();
		return false !== has_filter( 'efml_http_header_args', array( $obj, 'disable_check_for_unsafe_urls' ) ) || false !== has_filter( 'http_headers_useragent', array( $obj, 'add_user_agent' ) );
	}

	/**
	 * Return the test cases for the check of own URLs.
	 *
	 * @return array<string,array<int,string|bool>>
	 */
	public function provide_own_urls(): array {
		return array(
			'same URL'                   => array( 'http://example.org', true ),
			'path on own website'        => array( 'http://example.org/wp-json/wp/v2/media', true ),
			'other protocol'             => array( 'https://example.org/', true ),
			'upper case'                 => array( 'HTTPS://EXAMPLE.ORG/wp-json', true ),
			'without protocol'           => array( 'example.org', true ),
			'without protocol with path' => array( 'example.org/wp-json', true ),
			'other domain'               => array( 'https://example.com', false ),
			'domain with same start'     => array( 'https://example.org.example.com', false ),
			'subdomain'                  => array( 'https://www.example.org', false ),
		);
	}

	/**
	 * Test the check for URLs of the own website.
	 *
	 * @dataProvider provide_own_urls
	 *
	 * @param string $url The URL.
	 * @param bool   $expected The expected result.
	 *
	 * @return void
	 */
	public function test_is_own_url( string $url, bool $expected ): void {
		$this->assertEquals( 'http://example.org', get_option( 'home' ) );
		$this->assertSame( $expected, \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->is_own_url( $url ) );
	}

	/**
	 * Test if the own website could not be requested, also if the URL is given without protocol.
	 *
	 * @return void
	 */
	public function test_own_website_is_not_requested(): void {
		$this->replace_http_mock( array( $this, 'mock_http' ) );
		$this->assertSame( array(), \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_directory_listing( 'example.org' ) );
		$this->assertSame( array(), \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_directory_listing( 'HTTPS://example.org/wp-json/' ) );
		$this->assertEmpty( $this->requested_urls );
	}

	/**
	 * Return the test cases for responses, which ends the listing early.
	 *
	 * @return array<string,array<int,int|array|string>>
	 */
	public function provide_early_exits(): array {
		return array(
			'unexpected status'        => array( 500, array( 'content-type' => 'application/json' ), '[]' ),
			'no more files'            => array( 400, array( 'content-type' => 'application/json' ), '[]' ),
			'missing content type'     => array( 200, array(), '[]' ),
			'not JSON content type'    => array( 200, array( 'content-type' => 'text/html' ), '<html></html>' ),
			'JSON which is no list'    => array( 200, array( 'content-type' => 'application/json' ), '1' ),
		);
	}

	/**
	 * Test if the filters for the requests (which disable the check for unsafe URLs) are removed on each exit.
	 *
	 * @dataProvider provide_early_exits
	 *
	 * @param int    $status_code The HTTP status code.
	 * @param array  $headers The headers.
	 * @param string $body The body.
	 *
	 * @return void
	 */
	public function test_request_filters_are_removed( int $status_code, array $headers, string $body ): void {
		$this->response = self::get_http_response( $this->api_url, $status_code, $headers, $body );
		$this->replace_http_mock( array( $this, 'mock_http' ) );

		$listing = \ExternalFilesInMediaLibrary\Services\Rest::get_instance()->get_directory_listing( $this->api_url );
		$this->assertIsArray( $listing );
		$this->assertNotEmpty( $this->requested_urls );
		$this->assertFalse( $this->has_request_filters() );
	}

	/**
	 * Test if the credentials of the external source are only used for files on the same host.
	 *
	 * @return void
	 */
	public function test_credentials_are_only_used_for_same_host(): void {
		$http_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( 'https://api.example.com/wp-json/wp/v2/media' );
		$http_obj->set_fields(
			array(
				'login'    => array( 'value' => 'my-login' ),
				'password' => array( 'value' => 'my-password' ),
			)
		);
		$this->assertArrayHasKey( 'Authorization', $http_obj->get_header_args()['headers'] );

		$obj = \ExternalFilesInMediaLibrary\Services\Rest::get_instance();

		// a file on the same host uses the object with the credentials.
		$this->assertSame( $http_obj, $obj->get_http_object_for_file( 'https://API.example.com/wp-content/uploads/a.pdf', 'https://api.example.com/', $http_obj ) );

		// a file on another host must not get the credentials.
		$other_obj = $obj->get_http_object_for_file( 'https://cdn.example.net/a.pdf', 'https://api.example.com/', $http_obj );
		$this->assertNotSame( $http_obj, $other_obj );
		$this->assertArrayNotHasKey( 'headers', $other_obj->get_header_args() );
	}
}
