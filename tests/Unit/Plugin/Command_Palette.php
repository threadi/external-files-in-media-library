<?php
/**
 * Tests for the search of the command palette.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Commands;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;

/**
 * Object to test the search of the command palette.
 */
class Command_Palette extends externalFilesTests {
	/**
	 * Return a request with the given search.
	 *
	 * @param string $search The search.
	 *
	 * @return WP_REST_Request
	 */
	private function get_request( string $search ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/external-files-in-media-library/v1/services' );
		$request->set_param( 'search', $search );
		return $request;
	}

	/**
	 * Return the values of the given result.
	 *
	 * @param array<int,array<string,mixed>> $result The result.
	 *
	 * @return array<int,mixed>
	 */
	private function get_values( array $result ): array {
		return array_column( $result, 'value' );
	}

	/**
	 * Test the search for words.
	 *
	 * @return void
	 */
	public function test_search_matching(): void {
		$this->assertTrue( Helper::is_search_matching( 'ftp', array( 'FTP', 'server' ) ) );
		$this->assertTrue( Helper::is_search_matching( 'Ser FT', array( 'FTP', 'server' ) ) );
		$this->assertFalse( Helper::is_search_matching( 'ftp cloud', array( 'FTP', 'server' ) ) );
		$this->assertFalse( Helper::is_search_matching( '  ', array( 'FTP' ) ) );
	}

	/**
	 * Test the search for services, also by other terms and for service plugins, which are not installed.
	 *
	 * @return void
	 */
	public function test_services(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$services = Services::get_instance();

		// the FTP service by its name in any case and by other terms.
		$this->assertContains( 'ftp', $this->get_values( $services->get_services_via_rest( $this->get_request( 'ftp' ) ) ) );
		$this->assertContains( 'ftp', $this->get_values( $services->get_services_via_rest( $this->get_request( 'SFTP' ) ) ) );
		$this->assertContains( 'local', $this->get_values( $services->get_services_via_rest( $this->get_request( 'uploads' ) ) ) );

		// service plugins, which are not installed, with the URL of the page to choose a service.
		$result = $services->get_services_via_rest( $this->get_request( 'hetzner' ) );
		$this->assertEquals( array( 'aws-s3' ), $this->get_values( $result ) );
		$this->assertTrue( $result[0]['extension'] );
		$this->assertStringContainsString( 'page=efml_local_directories', $result[0]['url'] );
		$this->assertStringNotContainsString( 'method=', $result[0]['url'] );
		$this->assertContains( 'webdav', $this->get_values( $services->get_services_via_rest( $this->get_request( 'nextcloud' ) ) ) );

		// the URL of an installed service.
		$result = $services->get_services_via_rest( $this->get_request( 'dropbox' ) );
		$this->assertStringContainsString( 'method=dropbox', $result[0]['url'] );

		// nothing without search.
		$this->assertEmpty( $services->get_services_via_rest( $this->get_request( '' ) ) );
	}

	/**
	 * Test that only services are found, which the actual user is allowed to use.
	 *
	 * @return void
	 */
	public function test_services_with_permissions(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertNotContains( 'ftp', $this->get_values( Services::get_instance()->get_services_via_rest( $this->get_request( 'ftp' ) ) ) );
	}

	/**
	 * Test the search for external sources: only sources of the actual user are found, administrators find all.
	 *
	 * @return void
	 */
	public function test_sources(): void {
		// create a source for an editor and for another editor.
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$own_id = Taxonomy::get_instance()->add( 'ftp', 'ftp://own.example.com/', array() );
		wp_update_term( $own_id, Taxonomy::get_instance()->get_name(), array( 'name' => 'Own shop files' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$other_id = Taxonomy::get_instance()->add( 'ftp', 'ftp://other.example.com/', array() );
		wp_update_term( $other_id, Taxonomy::get_instance()->get_name(), array( 'name' => 'Other shop files' ) );

		// the editor finds only the own source.
		wp_set_current_user( $editor );
		$result = Services::get_instance()->get_sources_via_rest( $this->get_request( 'shop' ) );
		$this->assertEquals( array( $own_id ), $this->get_values( $result ) );
		$this->assertStringContainsString( 'term=' . $own_id, $result[0]['url'] );
		$this->assertStringContainsString( 'method=ftp', $result[0]['url'] );

		// the search also matches the service.
		$this->assertEquals( array( $own_id ), $this->get_values( Services::get_instance()->get_sources_via_rest( $this->get_request( 'own ftp' ) ) ) );
		$this->assertEmpty( Services::get_instance()->get_sources_via_rest( $this->get_request( 'dropbox' ) ) );

		// an administrator finds both.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$values = $this->get_values( Services::get_instance()->get_sources_via_rest( $this->get_request( 'shop' ) ) );
		$this->assertContains( $own_id, $values );
		$this->assertContains( $other_id, $values );
	}

	/**
	 * Test the search for settings, pages and documentations.
	 *
	 * @return void
	 */
	public function test_pages(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$commands = Commands::get_instance();

		// the export settings by other terms.
		$result = $commands->get_pages_via_rest( $this->get_request( 'offloading' ) );
		$this->assertContains( 'settings-eml_export', $this->get_values( $result ) );

		// the logs as sub-tab of the status tab.
		$result = $commands->get_pages_via_rest( $this->get_request( 'logs' ) );
		$this->assertContains( 'settings-eml_status-eml_logs', $this->get_values( $result ) );
		$logs = $result[ array_search( 'settings-eml_status-eml_logs', $this->get_values( $result ), true ) ];
		$this->assertStringContainsString( 'subtab=eml_logs', $logs['url'] );

		// the settings of a service.
		$this->assertContains( 'settings-services-eml_ftp', $this->get_values( $commands->get_pages_via_rest( $this->get_request( 'settings ftp' ) ) ) );

		// the documentation for the constants.
		$result = $commands->get_pages_via_rest( $this->get_request( 'wp-config' ) );
		$this->assertContains( 'docs-constants', $this->get_values( $result ) );
		$this->assertTrue( $result[ array_search( 'docs-constants', $this->get_values( $result ), true ) ]['external'] );

		// users without permission for the settings find no settings.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$values = $this->get_values( $commands->get_pages_via_rest( $this->get_request( 'logs' ) ) );
		$this->assertNotContains( 'settings-eml_status-eml_logs', $values );
		$this->assertContains( 'add-files', $this->get_values( $commands->get_pages_via_rest( $this->get_request( 'add external files' ) ) ) );
	}
}
