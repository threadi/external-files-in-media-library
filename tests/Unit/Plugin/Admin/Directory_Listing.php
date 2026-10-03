<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin\Admin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing.
 */
class Directory_Listing extends externalFilesTests {

	/**
	 * The ID of the user who saved the external source.
	 *
	 * @var int
	 */
	private int $owner_id = 0;

	/**
	 * The term ID of the external source.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * Prepare the test environment for each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// create the user who saves the external source.
		$this->owner_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $this->owner_id );

		// create the external source with credentials.
		$this->term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add(
			'ftp',
			'ftp://example.com/directory-listing-test/',
			array(
				'login'    => array( 'value' => 'my-login' ),
				'password' => array( 'value' => 'my-password' ),
			)
		);
		$this->assertGreaterThan( 0, $this->term_id );

		// make sure the user is marked as owner.
		if ( ! in_array( $this->owner_id, array_map( 'absint', get_term_meta( $this->term_id, 'user_id', false ) ), true ) ) {
			add_term_meta( $this->term_id, 'user_id', $this->owner_id );
		}

		// do not show all external sources to all users.
		update_option( 'eml_show_all_external_sources', 0 );
	}

	/**
	 * Test if the user who saved the external source is allowed to use it.
	 *
	 * @return void
	 */
	public function test_source_is_allowed_for_owner(): void {
		wp_set_current_user( $this->owner_id );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $this->term_id ) );
	}

	/**
	 * Test if another user is not allowed to use the external source.
	 *
	 * @return void
	 */
	public function test_source_is_not_allowed_for_other_user(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $this->term_id ) );
	}

	/**
	 * Test if a not logged-in visitor is not allowed to use the external source.
	 *
	 * @return void
	 */
	public function test_source_is_not_allowed_without_user(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $this->term_id ) );
	}

	/**
	 * Test if an administrator is allowed to use the external source of another user.
	 *
	 * @return void
	 */
	public function test_source_is_allowed_for_administrator(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $this->term_id ) );
	}

	/**
	 * Test if another user is allowed to use the external source if the setting for it is enabled.
	 *
	 * @return void
	 */
	public function test_source_is_allowed_for_other_user_with_setting(): void {
		update_option( 'eml_show_all_external_sources', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $this->term_id ) );
	}

	/**
	 * Test if no access is allowed without a term ID.
	 *
	 * @return void
	 */
	public function test_source_is_not_allowed_without_term(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( 0 ) );
	}

	/**
	 * Test if the credentials of an external source are saved encrypted and are returned decrypted.
	 *
	 * @return void
	 */
	public function test_credentials_are_saved_encrypted(): void {
		// the saved value must not contain the credentials.
		$saved_value = get_term_meta( $this->term_id, 'fields', true );
		$this->assertIsString( $saved_value );
		$this->assertNotEmpty( $saved_value );
		$this->assertStringNotContainsString( 'my-password', $saved_value );

		// the entry must return them.
		$entry = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $this->term_id );
		$this->assertEquals( 'my-password', $entry['fields']['password']['value'] );
	}

	/**
	 * Test if saved credentials, which could not be decrypted, are not overwritten by requesting the entry.
	 *
	 * @return void
	 */
	public function test_not_decryptable_credentials_are_not_overwritten(): void {
		// simulate credentials, which could not be decrypted (e.g., after a migration without the key).
		$broken_value = 'this-is-not-decryptable';
		update_term_meta( $this->term_id, 'fields', $broken_value );

		// request the entry.
		$entry = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( $this->term_id );
		$this->assertIsArray( $entry );
		$this->assertEmpty( $entry['fields'] );

		// the saved value must be unchanged.
		$this->assertEquals( $broken_value, get_term_meta( $this->term_id, 'fields', true ) );
	}
}
