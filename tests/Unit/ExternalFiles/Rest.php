<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Rest.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFiles;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Rest.
 */
class Rest extends WP_UnitTestCase {

	/**
	 * The URL of the file to use for testings.
	 *
	 * @var string
	 */
	private string $url = 'https://plugins.svn.wordpress.org/external-files-in-media-library/assets/example_en.pdf';

	/**
	 * Set up the preparations for the tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Initiating the REST API.
		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );
	}

	/**
	 * Set the authorization.
	 *
	 * @return void
	 */
	private function set_authorization(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Test response for a not authorized request to our endpoint to add a URL.
	 *
	 * @return void
	 */
	public function test_add_url_unauthorized(): void {
		$request = new WP_REST_Request( 'POST', '/efml/v1/file' );
		$request->set_body_params(
			array(
				'url'   => $this->url,
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test response for an authorized request to our endpoint to add a URL but without a URL.
	 *
	 * @return void
	 */
	public function test_add_url_authorized_with_missing_url(): void {
		$this->set_authorization();
		$request = new WP_REST_Request( 'POST', '/efml/v1/file' );
		$request->set_body_params(
			array(
				'url'   => '',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'No file URL given!', $response->get_data() );
	}

	/**
	 * Test response for an authorized request to our endpoint to add a URL.
	 *
	 * @return void
	 */
	public function test_add_url_authorized_with_url(): void {
		$this->set_authorization();
		$request = new WP_REST_Request( 'POST', '/efml/v1/file' );
		$request->set_body_params(
			array(
				'url'   => $this->url,
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test response for a not authorized request to get info about a file.
	 *
	 * @return void
	 */
	public function test_get_url_unauthorized(): void {
		$request = new WP_REST_Request( 'GET', '/efml/v1/file' );
		$request->set_query_params(
			array(
				'url'   => '',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test response for a not authorized request to get info about a file.
	 *
	 * @return void
	 */
	public function test_get_url_authorized_but_without_url(): void {
		$this->set_authorization();
		$request = new WP_REST_Request( 'GET', '/efml/v1/file' );
		$request->set_query_params(
			array(
				'url'   => '',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test response for a not authorized request to get info about a file.
	 *
	 * @return void
	 */
	public function test_get_url_authorized_with_url(): void {
		// set the authorization.
		$this->set_authorization();

		// first upload the file.
		$request = new WP_REST_Request( 'POST', '/efml/v1/file' );
		$request->set_body_params(
			array(
				'url'   => $this->url,
			)
		);
		$this->server->dispatch( $request );

		// then get the file.
		$request = new WP_REST_Request( 'GET', '/efml/v1/file' );
		$request->set_query_params(
			array(
				'url'   => $this->url,
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test response for a not authorized request to get info about a file.
	 *
	 * @return void
	 */
	public function test_delete_url_authorized_with_url(): void {
		// set the authorization.
		$this->set_authorization();

		// first upload the file.
		$request = new WP_REST_Request( 'POST', '/efml/v1/file' );
		$request->set_body_params(
			array(
				'url'   => $this->url,
			)
		);
		$this->server->dispatch( $request );

		// then delete the file.
		$request = new WP_REST_Request( 'DELETE', '/efml/v1/file' );
		$request->set_query_params(
			array(
				'url'   => $this->url,
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}
}
