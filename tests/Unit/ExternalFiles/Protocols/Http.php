<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Protocols;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_Error;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http.
 */
class Http extends externalFilesTests {
	/**
	 * The content-disposition header the mock should return.
	 *
	 * @var string
	 */
	private string $disposition = '';

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->replace_http_mock( array( $this, 'mock_http' ) );
		add_filter( 'efml_save_temp_file', '__return_false' );
	}

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->restore_http_mock( array( $this, 'mock_http' ) );
		remove_filter( 'efml_save_temp_file', '__return_false' );
		parent::tear_down();
	}

	/**
	 * Return the response for the HEAD request.
	 *
	 * @param false|array|WP_Error $false The return value.
	 * @param array                $parsed_args The used parameters.
	 * @param string               $url The requested URL.
	 *
	 * @return array|WP_Error
	 */
	public function mock_http( false|array|WP_Error $false, array $parsed_args, string $url ): array|WP_Error {
		if ( 'HEAD' !== $parsed_args['method'] ) {
			return new WP_Error( 'test', 'Only HEAD requests are expected.' );
		}

		$headers = array(
			'content-type'   => 'application/pdf',
			'content-length' => 100,
		);
		if ( ! empty( $this->disposition ) ) {
			$headers['content-disposition'] = $this->disposition;
		}
		return self::get_http_response( $url, 200, $headers );
	}

	/**
	 * Return the test cases for the content-disposition header.
	 *
	 * @return array<string,array<int,string>>
	 */
	public function provide_dispositions(): array {
		return array(
			'without header'           => array( '', 'disposition.pdf' ),
			'with file name'           => array( 'attachment; filename="other.pdf"', 'other.pdf' ),
			'without file name'        => array( 'inline', 'disposition.pdf' ),
			'with empty file name'     => array( 'attachment; filename=""', 'disposition.pdf' ),
			'with path in file name'   => array( 'attachment; filename="../../evil.pdf"', 'evil.pdf' ),
		);
	}

	/**
	 * Test the title of the file with different content-disposition headers.
	 *
	 * @dataProvider provide_dispositions
	 *
	 * @param string $disposition The header value.
	 * @param string $expected The expected title.
	 *
	 * @return void
	 */
	public function test_title_from_content_disposition( string $disposition, string $expected ): void {
		$this->disposition = $disposition;

		$url  = 'https://example.com/files/disposition.pdf';
		$obj  = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( $url );
		$info = $obj->get_url_info( $url );
		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'title', $info );
		$this->assertSame( $expected, $info['title'] );
	}

	/**
	 * Test if the Authorization header is only set if login and password are given.
	 *
	 * @return void
	 */
	public function test_header_args_with_credentials(): void {
		$obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http( 'https://example.com/example.pdf' );
		$this->assertArrayNotHasKey( 'headers', $obj->get_header_args() );
		$this->assertSame( 0, $obj->get_header_args()['redirection'] );

		$obj->set_fields( array( 'login' => array( 'value' => 'my-login' ) ) );
		$this->assertArrayNotHasKey( 'headers', $obj->get_header_args() );

		$obj->set_fields(
			array(
				'login'    => array( 'value' => 'my-login' ),
				'password' => array( 'value' => 'my-password' ),
			)
		);
		$this->assertSame( 'Basic ' . base64_encode( 'my-login:my-password' ), $obj->get_header_args()['headers']['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
