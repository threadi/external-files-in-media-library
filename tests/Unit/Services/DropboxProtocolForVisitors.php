<?php
/**
 * Tests for the protocol of Dropbox in requests without the capability for Dropbox (e.g. of visitors).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Services\DropBox\Protocol;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the protocol of Dropbox for visitors and users without the capability for Dropbox.
 */
class DropboxProtocolForVisitors extends externalFilesTests {
	/**
	 * Test if the protocol of Dropbox is known for visitors: the proxy needs it e.g. for private files.
	 *
	 * Hint: the services are initialized without a user in the tests, like for visitors.
	 *
	 * @return void
	 */
	public function test_protocol_for_visitors(): void {
		wp_set_current_user( 0 );

		// the protocol is registered.
		$this->assertContains( Protocol::class, Protocols::get_instance()->get_protocols() );

		// a private file in Dropbox gets the protocol of Dropbox.
		$attachment_id = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		Files::get_instance()->get_file( $attachment_id )->set_url( 'dropbox/photos/private.jpg' );
		update_post_meta( $attachment_id, 'eml_service', 'dropbox' );
		$this->assertInstanceOf( Protocol::class, Protocols::get_instance()->get_protocol_object_for_external_file( Files::get_instance()->get_file( $attachment_id ) ) );

		// a target of an export to Dropbox gets the protocol of Dropbox (e.g. for uploads of users without the capability for Dropbox).
		$this->assertInstanceOf( Protocol::class, Protocols::get_instance()->get_protocol_object_for_url( 'DropBox/exports/file.jpg' ) );
	}

	/**
	 * Test if only users with the capability for Dropbox are allowed to import files from Dropbox, as well as cron.
	 *
	 * @return void
	 */
	public function test_import_requires_capability(): void {
		$protocol = new Protocol( 'DropBox/photos/red.jpg' );

		// visitors are not allowed.
		wp_set_current_user( 0 );
		$this->assertFalse( $protocol->is_import_allowed_for_user() );

		// users without the capability for Dropbox are not allowed.
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( EFML_CAP_NAME );
		$user->remove_cap( 'efml_cap_dropbox' );
		wp_set_current_user( $user_id );
		$this->assertFalse( current_user_can( 'efml_cap_dropbox' ) );
		$this->assertFalse( $protocol->is_import_allowed_for_user() );

		// the import of a URL of Dropbox is refused with a reason in the log.
		$this->assertFalse( Import::get_instance()->add_url( 'DropBox/photos/red.jpg' ) );
		$this->assertTrue( $this->has_log_entry( 'not allowed to import files from' ) );

		// users with the capability are allowed.
		$user->add_cap( 'efml_cap_dropbox' );
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );
		$this->assertTrue( $protocol->is_import_allowed_for_user() );

		// cron is allowed.
		wp_set_current_user( 0 );
		add_filter( 'wp_doing_cron', '__return_true' );
		$this->assertTrue( $protocol->is_import_allowed_for_user() );
		remove_filter( 'wp_doing_cron', '__return_true' );
	}

	/**
	 * Test if users without the capability for Dropbox could import shared links of Dropbox: they are public URLs.
	 *
	 * Hint: the services are initialized without a user in the tests, like for these users.
	 *
	 * @return void
	 */
	public function test_shared_links_without_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( EFML_CAP_NAME );
		$user->remove_cap( 'efml_cap_dropbox' );
		wp_set_current_user( $user_id );

		// the handling of shared links is active.
		$dropbox = \ExternalFilesInMediaLibrary\Services\DropBox::get_instance();
		$this->assertNotFalse( has_filter( 'efml_import_url', array( $dropbox, 'convert_dropbox_urls' ) ) );
		$this->assertNotFalse( has_filter( 'efml_http_header_response', array( $dropbox, 'get_real_request_headers' ) ) );
		$this->assertNotFalse( has_filter( 'efml_http_check_content_type', array( $dropbox, 'allow_wrong_content_type' ) ) );

		// the import requests the file itself, not the preview page of Dropbox.
		$requests = array();
		$filter   = static function ( $response, array $args, string $url ) use ( &$requests ) {
			$requests[] = $url;
			return new \WP_Error( 'http_request_failed', 'No HTTP requests in tests.' );
		};
		add_filter( 'pre_http_request', $filter, 1, 3 );
		Import::get_instance()->add_url( 'https://www.dropbox.com/scl/fi/abc/red.jpg?rlkey=x&dl=0' );
		remove_filter( 'pre_http_request', $filter, 1 );
		$this->assertNotEmpty( $requests );
		$this->assertEquals( 'https://www.dropbox.com/scl/fi/abc/red.jpg?rlkey=x&raw=1', $requests[0] );
		$this->assertFalse( $this->has_log_entry( 'not allowed to import files from' ) );
	}

	/**
	 * Test if other protocols do not limit the import.
	 *
	 * @return void
	 */
	public function test_other_protocols_allow_import(): void {
		wp_set_current_user( 0 );
		$protocol = Protocols::get_instance()->get_protocol_object_for_url( 'https://example.com/file.jpg' );
		$this->assertNotFalse( $protocol );
		$this->assertTrue( $protocol->is_import_allowed_for_user() );
	}

	/**
	 * Return whether the log contains an entry with the given text.
	 *
	 * @param string $text The text.
	 *
	 * @return bool
	 */
	private function has_log_entry( string $text ): bool {
		return str_contains( (string) wp_json_encode( \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_logs() ), $text );
	}
}
