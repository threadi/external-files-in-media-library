<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Services.
 */
class Services extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_services_as_objects(): void {
		$services = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_services_as_objects();
		$this->assertIsArray( $services );
		$this->assertNotEmpty( $services );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_dropbox_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\DropBox::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\DropBox', $service );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_ftp_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\Ftp', $service );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_local_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\Local::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\easyDirectoryListingForWordPress\Listings\Local', $service );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_webdav_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\WebDav::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\WebDav', $service );
	}

	/**
	 * Test the list of services for the overview in the settings.
	 *
	 * @return void
	 */
	public function test_get_services_for_overview(): void {
		// the settings of the local service are only added for users with its capability.
		$this->do_login();
		\ExternalFilesInMediaLibrary\Services\Local::get_instance()->init_local();

		$services = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_services_for_overview();
		$this->assertNotEmpty( $services );

		// only services with settings are listed.
		foreach ( $services as $service ) {
			$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\Service_Base::class, $service );
		}
		$this->assertContains( \ExternalFilesInMediaLibrary\Services\Ftp::get_instance(), $services );
		$this->assertContains( \ExternalFilesInMediaLibrary\Services\Local::get_instance(), $services );

		// the services are sorted by their labels.
		$labels = array_map(
			static function ( \ExternalFilesInMediaLibrary\Services\Service_Base $service ): string {
				return $service->get_settings_title();
			},
			$services
		);
		$sorted = $labels;
		natcasesort( $sorted );
		$this->assertEquals( array_values( $sorted ), $labels );

		// the list is filterable.
		$callback = static function (): array {
			return array( \ExternalFilesInMediaLibrary\Services\Ftp::get_instance() );
		};
		add_filter( 'efml_services_overview', $callback );
		$this->assertCount( 1, \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_services_for_overview() );
		remove_filter( 'efml_services_overview', $callback );
	}

	/**
	 * Test the URL of the settings of a service.
	 *
	 * @return void
	 */
	public function test_get_settings_url(): void {
		// test 1: a service with settings.
		$this->assertStringContainsString( 'subtab=eml_ftp', \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_settings_url() );

		// test 2: a service without settings tab or with a not existing tab.
		$service    = \ExternalFilesInMediaLibrary\Services\Local::get_instance();
		$reflection = new \ReflectionProperty( \ExternalFilesInMediaLibrary\Services\Service_Base::class, 'settings_sub_tab' );
		$original   = $reflection->getValue( $service );
		$reflection->setValue( $service, '' );
		$this->assertEquals( '', $service->get_settings_url() );
		$reflection->setValue( $service, 'eml_not_existing' );
		$this->assertEquals( '', $service->get_settings_url() );
		$reflection->setValue( $service, $original );
	}

	/**
	 * Test the output of the overview of the services on the page "Services" of the settings.
	 *
	 * @return void
	 */
	public function test_show_settings_services_hint(): void {
		// the settings of the local service are only added for users with its capability.
		$this->do_login();
		\ExternalFilesInMediaLibrary\Services\Local::get_instance()->init_local();

		ob_start();
		\ExternalFilesInMediaLibrary\Services\Services::get_instance()->show_settings_services_hint();
		$output = (string) ob_get_clean();

		// the hint and the table are shown.
		$this->assertStringContainsString( 'Settings for services', $output );
		$this->assertStringContainsString( 'efml-services-overview', $output );

		// each service is listed, with a link to its settings, if available.
		foreach ( \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_services_for_overview() as $service ) {
			$this->assertStringContainsString( esc_html( $service->get_settings_title() ), $output );
			if ( '' !== $service->get_settings_url() ) {
				$this->assertStringContainsString( esc_url( $service->get_settings_url() ), $output );
			}
		}

		// the title of the tab is used, if the service has no label.
		$this->assertStringContainsString( '>Local<', $output );

		// each service has the element for its logo with the class used by the CSS for the logos.
		$this->assertStringContainsString( '<span class="efml-service-logo efml-ftp" aria-hidden="true"><span></span></span>', $output );
		$this->assertStringContainsString( 'efml-service-logo efml-dropbox', $output );

		// no descriptions are shown.
		$this->assertStringNotContainsString( 'SSL is required in your hosting', $output );
	}
}
