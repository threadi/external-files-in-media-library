<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Roles.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Roles.
 */
class Roles extends externalFilesTests {
	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( array( 'editor', 'author' ) as $role ) {
			get_role( $role )->remove_cap( 'efml_cap_testservice' );
		}
		remove_all_filters( 'pre_option_eml_user_forbidden' );
		parent::tear_down();
	}

	/**
	 * Test if the capability of a service is only set for the given roles.
	 *
	 * @return void
	 */
	public function test_save_capabilities_for_service(): void {
		$obj = \ExternalFilesInMediaLibrary\Plugin\Roles::get_instance();

		// set for editors.
		$this->assertSame( array( 'editor' ), $obj->save_capabilities_for_service( array( 'editor' ), array(), 'eml_service_testservice_allowed_roles' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'efml_cap_testservice' ) );
		$this->assertFalse( get_role( 'author' )->has_cap( 'efml_cap_testservice' ) );

		// change to authors: editors lose the capability.
		$obj->save_capabilities_for_service( array( 'author' ), array( 'editor' ), 'eml_service_testservice_allowed_roles' );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'efml_cap_testservice' ) );
		$this->assertTrue( get_role( 'author' )->has_cap( 'efml_cap_testservice' ) );

		// null does not change anything.
		$this->assertSame( array(), $obj->save_capabilities_for_service( null, array(), 'eml_service_testservice_allowed_roles' ) );
		$this->assertTrue( get_role( 'author' )->has_cap( 'efml_cap_testservice' ) );
	}

	/**
	 * Test if forbidden users lose the capability to use external files.
	 *
	 * @return void
	 */
	public function test_check_user_cap_for_forbidden_users(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$obj     = \ExternalFilesInMediaLibrary\Plugin\Roles::get_instance();
		$allcaps = array(
			EFML_CAP_NAME   => true,
			'upload_files' => true,
		);

		// not forbidden.
		$this->assertSame( $allcaps, $obj->check_user_cap( $allcaps, array( EFML_CAP_NAME ) ) );

		// forbidden (set via filter, as the saving of the setting only allows users known during the initialization).
		add_filter(
			'pre_option_eml_user_forbidden',
			static function () use ( $user_id ) {
				return array( (string) $user_id );
			}
		);
		$result = $obj->check_user_cap( $allcaps, array( EFML_CAP_NAME ) );
		$this->assertArrayNotHasKey( EFML_CAP_NAME, $result );
		$this->assertArrayHasKey( 'upload_files', $result );

		// other capabilities are not changed.
		$this->assertSame( $allcaps, $obj->check_user_cap( $allcaps, array( 'upload_files' ) ) );
	}
}
