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

	/**
	 * Test if a term of another taxonomy is not allowed, also if all external sources are visible for all users.
	 *
	 * @return void
	 */
	public function test_term_of_other_taxonomy_is_not_allowed(): void {
		$category_id = self::factory()->category->create();

		update_option( 'eml_show_all_external_sources', 1 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $category_id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->is_source_allowed_for_current_user( $category_id ) );
	}

	/**
	 * Test if the configuration in the attribute "data-config" is valid JSON in the browser, also if a text of it
	 * contains escaped quotes (like the IAM policy in the help for AWS S3, which broke the form of AWS S3).
	 *
	 * @return void
	 */
	public function test_config_with_escaped_quotes_is_valid_json(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		// add a help with a code to copy, as AWS S3 does it.
		$code   = "{\n    \"Version\": \"2012-10-17\",\n    \"Resource\": \"arn:aws:s3:::my-bucket/*\"\n}";
		$filter = static function ( array $guide ) use ( $code ): array {
			$guide['code'] = array(
				'label'   => 'IAM policy',
				'content' => $code,
			);
			return $guide;
		};
		add_filter( 'efml_service_setup_guide', $filter );

		// get the configuration of the FTP service.
		$service = \ExternalFilesInMediaLibrary\Services\Services::get_instance()->get_service_by_name( 'ftp' );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\Services\Service_Base::class, $service );
		$config = $service->get_config();
		remove_filter( 'efml_service_setup_guide', $filter );
		$this->assertStringContainsString( '&quot;Version&quot;', $config['form_description'] );

		// the value of the attribute as the browser reads it.
		$attribute = esc_attr( \ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing::get_instance()->get_config_json( $config ) );
		$value     = html_entity_decode( $attribute, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// the JSON must be valid and contain the escaped code, which the browser shows as the original code.
		$decoded = json_decode( $value, true );
		$this->assertIsArray( $decoded );
		$this->assertEquals( $config['form_description'], $decoded['form_description'] );
		$this->assertEquals( count( $config['fields'] ), count( $decoded['fields'] ) );

		// with the former flags the JSON was broken in the browser.
		$former = html_entity_decode( esc_attr( \ExternalFilesInMediaLibrary\Plugin\Helper::get_json( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$this->assertNull( json_decode( $former, true ) );
	}
}
