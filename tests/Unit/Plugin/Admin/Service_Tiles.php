<?php
/**
 * Tests for the tiles to choose a service with their features.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin\Admin;

use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\AwsS3;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\Ftp;
use ExternalFilesInMediaLibrary\Services\Rest;
use ExternalFilesInMediaLibrary\Services\Services;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the tiles to choose a service with their features.
 */
class Service_Tiles extends externalFilesTests {
	/**
	 * Run each test as administrator.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Test the features of services.
	 *
	 * @return void
	 */
	public function test_features(): void {
		// FTP supports all features except private files (they are always delivered via the proxy).
		$this->assertEquals(
			array(
				'sync'      => true,
				'export'    => true,
				'private'   => false,
				'constants' => true,
			),
			Ftp::get_instance()->get_features()
		);

		// Dropbox supports all features.
		$this->assertEquals( array( 'sync', 'export', 'private', 'constants' ), Services::get_instance()->get_supported_features( DropBox::get_instance() ) );

		// the features of "Local" are defined by its service object.
		$this->assertContains( 'export', Services::get_instance()->get_supported_features( \easyDirectoryListingForWordPress\Listings\Local::get_instance() ) );

		// the REST API does not support synchronization and export.
		$features = Rest::get_instance()->get_features();
		$this->assertFalse( $features['sync'] );
		$this->assertFalse( $features['export'] );
		$this->assertTrue( $features['constants'] );

		// the features of a service plugin, which is not installed.
		$this->assertEquals( array( 'sync', 'export', 'private', 'constants' ), Services::get_instance()->get_supported_features( AwsS3::get_instance() ) );

		// the features could be changed.
		add_filter( 'efml_service_features', array( $this, 'remove_export' ) );
		$this->assertFalse( Ftp::get_instance()->get_features()['export'] );
		remove_filter( 'efml_service_features', array( $this, 'remove_export' ) );
	}

	/**
	 * Remove the export from the features.
	 *
	 * @param array<string,bool> $features List of features.
	 *
	 * @return array<string,bool>
	 */
	public function remove_export( array $features ): array {
		$features['export'] = false;
		return $features;
	}

	/**
	 * Test the icons: the Dashicon is used, if the SVG icon is not registered.
	 *
	 * @return void
	 */
	public function test_icon_with_dashicon(): void {
		$html = Helper::get_icon( 'core-admin/efml-not-registered', 'dashicons-update', 'Synchronization' );
		$this->assertStringContainsString( 'dashicons dashicons-update', $html );
		$this->assertStringContainsString( 'aria-label="Synchronization"', $html );
		$this->assertStringNotContainsString( '<svg', $html );

		// without label the icon is hidden for screen readers.
		$this->assertStringContainsString( 'aria-hidden="true"', Helper::get_icon( 'core-admin/efml-not-registered', 'dashicons-update' ) );
	}

	/**
	 * Test the icons: the SVG icon is used, if it is registered (like in WordPress 7.2).
	 *
	 * @return void
	 */
	public function test_icon_with_svg(): void {
		// bail if this WordPress version does not support icons.
		if ( ! function_exists( 'wp_register_icon' ) || ! function_exists( 'wp_register_icon_collection' ) ) {
			$this->markTestSkipped( 'Requires WordPress 7.1 or newer.' );
		}

		// register an icon in our own collection.
		@wp_register_icon_collection( 'efml-test', array( 'label' => 'Test' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@wp_register_icon( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'efml-test/update',
			array(
				'label'   => 'Update',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h22v22H1z"/></svg>',
			)
		);

		$html = Helper::get_icon( 'efml-test/update', 'dashicons-update', 'Synchronization', 18 );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( 'svg-icon', $html );
		$this->assertStringContainsString( 'width="18"', $html );
		$this->assertStringContainsString( 'aria-label="Synchronization"', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );

		// the allowed HTML keeps the SVG.
		$this->assertStringContainsString( '<path d="M1 1h22v22H1z"', wp_kses( $html, Helper::get_allowed_html_for_icons() ) );
	}

	/**
	 * Test the output of the tiles with their features, the filter and the legend.
	 *
	 * @return void
	 */
	public function test_tiles(): void {
		unset( $_GET['method'] );
		ob_start();
		Directory_Listing::get_instance()->render_view_directory_page();
		$html = (string) ob_get_clean();

		// the filter and the legend.
		$this->assertStringContainsString( 'id="efml-directory-listing-services-filter"', $html );
		$this->assertStringContainsString( '<option value="private">Private files</option>', $html );
		$this->assertStringContainsString( 'class="efml-services-legend"', $html );

		// the features of the FTP service.
		$this->assertStringContainsString( '<li class="efml-ftp" data-efml-features="sync export constants">', $html );
		$this->assertStringContainsString( '<li class="efml-dropbox" data-efml-features="sync export private constants">', $html );
		$this->assertStringContainsString( 'dashicons-shield', $html );

		// the service plugins, which are not installed, are marked.
		$this->assertStringContainsString( 'efml-service-badge', $html );
		$this->assertStringContainsString( 'efml-is-extension', $html );
		$this->assertStringContainsString( 'dashicons-admin-plugins', $html );
	}
}
