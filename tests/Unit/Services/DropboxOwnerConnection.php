<?php
/**
 * Tests for the connection to Dropbox of the owner of the external source of a file.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\DropBox;
use ExternalFilesInMediaLibrary\Services\DropBox\Export;
use ExternalFilesInMediaLibrary\Services\DropBox\Protocol;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionMethod;
use WP_User;

/**
 * Object to test the connection to Dropbox of the owner of the external source of a file.
 */
class DropboxOwnerConnection extends externalFilesTests {
	/**
	 * The ID of the owner of the external source.
	 *
	 * @var int
	 */
	private int $owner_id = 0;

	/**
	 * The ID of the external source of Dropbox.
	 *
	 * @var int
	 */
	private int $term_id = 0;

	/**
	 * The ID of the exported file.
	 *
	 * @var int
	 */
	private int $attachment_id = 0;

	/**
	 * Prepare each test: each user has its own connection, the owner of the external source is connected.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// each user has its own connection to Dropbox.
		update_option( 'eml_dropbox_credentials_vault', 'user' );

		// the owner with its connection.
		$this->owner_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->set_token( $this->owner_id, 'owner-token' );

		// the external source of the owner.
		$this->term_id = absint( \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'dropbox', 'DropBox/owner-' . wp_generate_password( 6, false ) . '/', array() ) );
		$this->assertGreaterThan( 0, $this->term_id );
		delete_term_meta( $this->term_id, 'user_id' );
		update_term_meta( $this->term_id, 'user_id', $this->owner_id );

		// a private file exported to this external source.
		$this->attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		Files::get_instance()->get_file( $this->attachment_id )->set_url( 'dropbox/photos/private.jpg' );
		update_post_meta( $this->attachment_id, 'eml_service', 'dropbox' );
		update_post_meta( $this->attachment_id, 'efml_export_sources', array( $this->term_id ) );

		// requests run as visitor.
		wp_set_current_user( 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'eml_dropbox_credentials_vault' );
		parent::tear_down();
	}

	/**
	 * Save the connection of a user like the OAuth of Dropbox does.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $token The access token.
	 *
	 * @return void
	 */
	private function set_token( int $user_id, string $token ): void {
		$data = array(
			'access_token'  => $token,
			'refresh_token' => 'refresh',
			'expires'       => time() + HOUR_IN_SECONDS,
		);
		update_user_meta( $user_id, 'efml_dropbox_access_tokens', Crypt::get_instance()->encrypt( Helper::get_json( $data ), DropBox::ACCESS_TOKENS_USER_CONTEXT ) );
	}

	/**
	 * Test if the owner of the external source of a file is known, but only if each user has its own connection.
	 *
	 * @return void
	 */
	public function test_owner_of_file(): void {
		$owner = DropBox::get_instance()->get_owner_of_file( $this->attachment_id );
		$this->assertInstanceOf( WP_User::class, $owner );
		$this->assertEquals( $this->owner_id, $owner->ID );
		$this->assertEquals( $this->owner_id, DropBox::get_instance()->get_owner_of_source( $this->term_id )->ID );

		// also via the assigned external source, if the list of export sources is missing (older exports).
		delete_post_meta( $this->attachment_id, 'efml_export_sources' );
		wp_set_object_terms( $this->attachment_id, $this->term_id, \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name() );
		$this->assertEquals( $this->owner_id, DropBox::get_instance()->get_owner_of_file( $this->attachment_id )->ID );

		// not with one connection for the website.
		update_option( 'eml_dropbox_credentials_vault', 'global' );
		$this->assertFalse( DropBox::get_instance()->get_owner_of_file( $this->attachment_id ) );
	}

	/**
	 * Test if the connection of the owner is used for visitors, without changing the connection of the actual user.
	 *
	 * @return void
	 */
	public function test_connection_of_owner(): void {
		$dropbox = DropBox::get_instance();

		// the visitor has no connection.
		$this->assertEmpty( $dropbox->get_access_token() );

		// the connection of the owner.
		$data = $dropbox->get_access_token_for_user( $dropbox->get_owner_of_file( $this->attachment_id ) );
		$this->assertEquals( 'owner-token', $data['access_token'] );

		// afterward the actual user is used again.
		$this->assertEmpty( $dropbox->get_access_token() );

		// another user with its own connection gets the connection of the owner for this file, not its own.
		$other_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->set_token( $other_id, 'other-token' );
		wp_set_current_user( $other_id );
		$this->assertEquals( 'other-token', $dropbox->get_access_token()['access_token'] );
		$this->assertEquals( 'owner-token', $dropbox->get_access_token_for_user( $dropbox->get_owner_of_file( $this->attachment_id ) )['access_token'] );
	}

	/**
	 * Test if the protocol of the file (e.g. for the proxy) uses the owner of its external source.
	 *
	 * @return void
	 */
	public function test_protocol_uses_owner(): void {
		$protocol = Protocols::get_instance()->get_protocol_object_for_external_file( Files::get_instance()->get_file( $this->attachment_id ) );
		$this->assertInstanceOf( Protocol::class, $protocol );
		$this->assertEquals( $this->attachment_id, $protocol->get_external_file()->get_id() );

		$method = new ReflectionMethod( Protocol::class, 'get_owner' );
		$method->setAccessible( true );
		$this->assertEquals( $this->owner_id, $method->invoke( $protocol )->ID );

		// a protocol without file (e.g. for an import) uses the actual user.
		$this->assertFalse( $method->invoke( new Protocol( 'DropBox/photos/private.jpg' ) ) );
	}

	/**
	 * Test if the export uses the owner of the external source it exports to.
	 *
	 * @return void
	 */
	public function test_export_uses_owner_of_target(): void {
		$method = new ReflectionMethod( Export::class, 'get_owner' );
		$method->setAccessible( true );

		// a new file (not exported yet) with the external source in the credentials.
		$new_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		$this->assertEquals( $this->owner_id, $method->invoke( Export::get_instance(), $new_id, array( 'term_id' => $this->term_id ) )->ID );

		// an exported file without the external source in the credentials.
		$this->assertEquals( $this->owner_id, $method->invoke( Export::get_instance(), $this->attachment_id, array() )->ID );

		// unknown: the actual user is used.
		$this->assertFalse( $method->invoke( Export::get_instance(), $new_id, array() ) );
	}
}
