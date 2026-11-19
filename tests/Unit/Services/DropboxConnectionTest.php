<?php
/**
 * Tests for the complete connection test of DropBox.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Spatie\Dropbox\Client;

/**
 * Object to simulate the DropBox client for the connection test.
 */
class DropboxConnectionTestClient extends Client {
	/**
	 * The files with their path and content.
	 *
	 * @var array<string,string>
	 */
	public array $files = array();

	/**
	 * List of called methods with their path.
	 *
	 * @var array<int,string>
	 */
	public array $calls = array();

	/**
	 * The methods, which should fail.
	 *
	 * @var array<int,string>
	 */
	public array $failing = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'test-token' );
	}

	/**
	 * Throw an error like DropBox, if the given method should fail.
	 *
	 * @param string $method The method.
	 *
	 * @return void
	 * @throws ClientException The error.
	 */
	private function maybe_fail( string $method ): void {
		if ( in_array( $method, $this->failing, true ) ) {
			throw new ClientException( 'missing_scope', new Request( 'POST', 'https://api.dropboxapi.com/2/' . $method ), new Response( 409 ) );
		}
	}

	/**
	 * Return the content of a folder.
	 *
	 * @param string $path The path.
	 * @param bool   $recursive Whether to list recursive.
	 *
	 * @return array<string,mixed>
	 */
	public function listFolder( string $path = '', bool $recursive = false ): array {
		$this->calls[] = 'listFolder ' . $path;
		$this->maybe_fail( 'listFolder' );
		return array(
			'entries'  => array(),
			'has_more' => false,
		);
	}

	/**
	 * Save a file.
	 *
	 * @param string $path The path.
	 * @param mixed  $contents The content.
	 * @param string $mode The mode.
	 * @param bool   $autorename Whether to rename the file.
	 *
	 * @return array<string,mixed>
	 */
	public function upload( string $path, mixed $contents, string $mode = 'add', bool $autorename = false ): array {
		$this->calls[] = 'upload ' . $path;
		$this->maybe_fail( 'upload' );
		$this->files[ $path ] = (string) $contents;
		return array( 'path_display' => $path );
	}

	/**
	 * Create a shared link.
	 *
	 * @param string              $path The path.
	 * @param array<string,mixed> $settings The settings.
	 *
	 * @return array<string,mixed>
	 */
	public function createSharedLinkWithSettings( string $path, array $settings = array() ): array {
		$this->calls[] = 'createSharedLinkWithSettings ' . $path;
		$this->maybe_fail( 'createSharedLinkWithSettings' );
		return array( 'url' => 'https://www.dropbox.com/scl/fi/test' . $path );
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path The path.
	 *
	 * @return array<string,mixed>
	 */
	public function delete( string $path ): array {
		$this->calls[] = 'delete ' . $path;
		$this->maybe_fail( 'delete' );
		unset( $this->files[ $path ] );
		return array();
	}
}

/**
 * Object to test the connection test of DropBox.
 */
class DropboxConnectionTest extends externalFilesTests {
	/**
	 * The simulated client.
	 *
	 * @var DropboxConnectionTestClient|null
	 */
	private ?DropboxConnectionTestClient $client = null;

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->client = new DropboxConnectionTestClient();
		add_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_dropbox_client', array( $this, 'get_client' ) );
		DropBox::get_instance()->set_connection_test_directory( '' );
		parent::tear_down();
	}

	/**
	 * Return the simulated client.
	 *
	 * @return DropboxConnectionTestClient|null
	 */
	public function get_client(): ?DropboxConnectionTestClient {
		return $this->client;
	}

	/**
	 * Test the connection test in a folder: all steps are run in this folder.
	 *
	 * @return void
	 */
	public function test_connection_test(): void {
		$this->assertTrue( DropBox::get_instance()->supports_connection_test() );
		DropBox::get_instance()->set_connection_test_directory( 'DropBox/Photos/' );

		$steps = DropBox::get_instance()->run_connection_test();

		foreach ( $steps as $step ) {
			$this->assertTrue( $step['success'], $step['title'] . ': ' . $step['message'] );
		}
		$this->assertCount( 4, $this->client->calls );
		$this->assertEquals( 'listFolder /Photos/', $this->client->calls[0] );
		$this->assertStringStartsWith( 'upload /Photos/efml-connection-test-', $this->client->calls[1] );
		$this->assertStringStartsWith( 'createSharedLinkWithSettings /Photos/efml-connection-test-', $this->client->calls[2] );
		$this->assertStringStartsWith( 'delete /Photos/efml-connection-test-', $this->client->calls[3] );
		$this->assertEmpty( $this->client->files );
	}

	/**
	 * Test the connection test in the main folder.
	 *
	 * @return void
	 */
	public function test_connection_test_in_main_folder(): void {
		DropBox::get_instance()->set_connection_test_directory( 'DropBox' );

		DropBox::get_instance()->run_connection_test();

		$this->assertEquals( 'listFolder ', $this->client->calls[0] );
		$this->assertStringStartsWith( 'upload /efml-connection-test-', $this->client->calls[1] );
	}

	/**
	 * Test the connection test, if no public link could be created: the file is still deleted.
	 *
	 * @return void
	 */
	public function test_connection_test_without_public_link(): void {
		$this->client->failing = array( 'createSharedLinkWithSettings' );

		$steps = DropBox::get_instance()->run_connection_test();

		$this->assertTrue( $steps[1]['success'] );
		$this->assertFalse( $steps[2]['success'] );
		$this->assertStringContainsString( 'Save files privately', $steps[2]['message'] );
		$this->assertTrue( $steps[3]['success'] );
	}

	/**
	 * Test the connection test with errors during the upload and the deletion.
	 *
	 * @return void
	 */
	public function test_connection_test_with_errors(): void {
		$this->client->failing = array( 'upload' );
		$steps                 = DropBox::get_instance()->run_connection_test();
		$this->assertFalse( $steps[1]['success'] );
		$this->assertTrue( $steps[2]['skipped'] );
		$this->assertTrue( $steps[3]['skipped'] );

		$this->client->failing = array( 'delete' );
		$steps                 = DropBox::get_instance()->run_connection_test();
		$this->assertFalse( $steps[3]['success'] );
		$this->assertStringContainsString( 'manually', $steps[3]['message'] );
	}

	/**
	 * Test the connection test without connection to DropBox.
	 *
	 * @return void
	 */
	public function test_connection_test_without_client(): void {
		$this->client = null;
		$steps        = DropBox::get_instance()->run_connection_test();
		$this->assertFalse( $steps[0]['success'] );
		$this->assertStringContainsString( 'not connected', $steps[0]['message'] );
	}
}
