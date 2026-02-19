<?php
/**
 * Tests a scenario.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Scenarios;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test this scenario.
 */
class SetAdminDominionCapabilitySet extends externalFilesTests {
	/**
	 * Prepare the test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		// get the standard capability object.
		$capability_set = \ExternalFilesInMediaLibrary\Plugin\CapabilitySets::get_instance()->get_capability_set_by_name( 'standard' );
		$this->assertIsObject( $capability_set );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Plugin\CapabilitySet_Base', $capability_set );

		// set it.
		$capability_set->run();

		// login.
		$this->do_login();
	}

	/**
	 * Reset caps to default.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// get the standard capability object.
		$capability_set = \ExternalFilesInMediaLibrary\Plugin\CapabilitySets::get_instance()->get_capability_set_by_name( 'standard' );
		$this->assertIsObject( $capability_set );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Plugin\CapabilitySet_Base', $capability_set );

		// run it.
		$capability_set->run();

		// logout.
		$this->do_logout();
	}

	/**
	 * Set the capability set and check its success.
	 *
	 * @return void
	 */
	public function test_set_capability_set(): void {
		// get the name of the ftp service we use to test.
		$ftp_service_name = \ExternalFilesInMediaLibrary\Services\Ftp::get_instance()->get_name();

		// set the capability for the local service.
		update_option( 'eml_service_' . $ftp_service_name . '_allowed_roles', array( 'administrator' ) );

		// test the capability before.
		$test_before = current_user_can( 'efml_cap_' . $ftp_service_name );
		$this->assertIsBool( $test_before );
		$this->assertTrue( $test_before );

		// get the capability set.
		$capability_set = \ExternalFilesInMediaLibrary\Plugin\CapabilitySets::get_instance()->get_capability_set_by_name( 'admin_dominion' );
		$this->assertIsObject( $capability_set );
		$this->assertInstanceOf( '\ExternalFilesInMediaLibrary\Plugin\CapabilitySet_Base', $capability_set );

		// run it.
		$capability_set->run();

		// and logout.
		$this->do_logout();

		// and login as admin to refresh the caps.
		$this->do_login();

		// test the result after.
		$test_after = current_user_can( 'efml_cap_' . $ftp_service_name );
		$this->assertIsBool( $test_after );
		$this->assertTrue( $test_after );
	}
}
