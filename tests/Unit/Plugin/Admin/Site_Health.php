<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Admin\Site_Health.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin\Admin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Admin\Site_Health.
 */
class Site_Health extends externalFilesTests {
	/**
	 * Test if no credentials are shown in the debug information of site health.
	 *
	 * @return void
	 */
	public function test_debug_info_does_not_show_credentials(): void {
		$debug_information = \ExternalFilesInMediaLibrary\Plugin\Admin\Site_Health::get_instance()->add_debug_info( array() );
		$this->assertCount( 1, $debug_information );

		$fields = current( $debug_information )['fields'];
		$this->assertNotEmpty( $fields );

		foreach ( $fields as $name => $field ) {
			// bail if this is not a setting for credentials.
			if ( ! preg_match( '/(password|passwort|token|secret|login|api_?key|access_?key|credential)/i', $name ) ) {
				continue;
			}

			// bail for the setting where the credentials are saved (global or per user), as it does not contain them.
			if ( str_ends_with( $name, '_credentials_vault' ) ) {
				continue;
			}

			$this->assertTrue( $field['private'], 'Setting ' . $name . ' must be private.' );
		}
	}
}
