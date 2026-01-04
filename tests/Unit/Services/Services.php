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
	public function test_get_google_cloud_storage_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\GoogleCloudStorage::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\GoogleCloudStorage', $service );
	}

	/**
	 * Test if the returning variable is an object.
	 *
	 * @return void
	 */
	public function test_get_google_drive_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\GoogleDrive::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\GoogleDrive', $service );
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
	public function test_get_s3_service_by_name(): void {
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( \ExternalFilesInMediaLibrary\Services\S3::get_instance()->get_name() );
		$this->assertIsObject( $service );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Services\S3', $service );
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
}
