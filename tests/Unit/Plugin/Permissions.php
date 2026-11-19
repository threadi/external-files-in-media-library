<?php
/**
 * Tests for the permissions of the functions of this plugin (P44).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\ExternalFiles\Abilities;
use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Plugin\Connection_Test;
use ExternalFilesInMediaLibrary\Plugin\Setup;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Object to test the permissions.
 */
class Permissions extends externalFilesTests {
	/**
	 * Return a user with the given role, which may manage external files.
	 *
	 * @param string            $role The role.
	 * @param array<int,string> $caps Further capabilities of the user.
	 *
	 * @return int
	 */
	private function get_user( string $role, array $caps = array() ): int {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		$user    = get_user_by( 'id', $user_id );
		$this->assertInstanceOf( \WP_User::class, $user );
		$user->add_cap( EFML_CAP_NAME );
		foreach ( $caps as $cap ) {
			$user->add_cap( $cap );
		}
		return $user_id;
	}

	/**
	 * Return an external file of another user (an administrator).
	 *
	 * @return int
	 */
	private function get_external_file_of_other_user(): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$attachment_id = Abilities::get_instance()->add_url( array( 'url' => self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertIsInt( $attachment_id );
		return $attachment_id;
	}

	/**
	 * Test that users on the list "Prevent access for these users" lose all capabilities of this plugin, also
	 * those of the services and tools, and that only these users are affected.
	 *
	 * @return void
	 */
	public function test_forbidden_users_lose_all_capabilities(): void {
		$forbidden = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$other     = self::factory()->user->create( array( 'role' => 'administrator' ) );
		// set via filter, as the saving of the setting only allows users known during the initialization.
		$filter = static fn() => array( (string) $forbidden );
		add_filter( 'pre_option_eml_user_forbidden', $filter );

		foreach ( array( EFML_CAP_NAME, 'efml_cap_tools_export', 'efml_cap_tools_sync', 'efml_cap_ftp' ) as $cap ) {
			get_role( 'administrator' )->add_cap( $cap );
			$this->assertFalse( user_can( $forbidden, $cap ), $cap );
			$this->assertTrue( user_can( $other, $cap ), $cap );
		}

		// other capabilities are not affected.
		$this->assertTrue( user_can( $forbidden, 'manage_options' ) );

		// the check uses the requested user, not the actual user.
		wp_set_current_user( $forbidden );
		$this->assertTrue( user_can( $other, EFML_CAP_NAME ) );
		$this->assertFalse( current_user_can( 'efml_cap_tools_export' ) );

		// the quickstart is not available for them.
		$this->assertFalse( Setup::get_instance()->is_allowed() );

		remove_filter( 'pre_option_eml_user_forbidden', $filter );
	}

	/**
	 * Test that a file could only be deleted via REST API by users, who are allowed to delete it.
	 *
	 * @return void
	 */
	public function test_rest_delete_requires_permission_for_the_file(): void {
		$attachment_id = $this->get_external_file_of_other_user();
		$url           = (string) get_post_meta( $attachment_id, 'eml_external_file', true );

		// an author with the capability for external files is not allowed to delete the file of another user.
		wp_set_current_user( $this->get_user( 'author' ) );
		$request = new WP_REST_Request( 'DELETE', '/efml/v1/file' );
		$request->set_param( 'url', $url );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
		$this->assertInstanceOf( \WP_Post::class, get_post( $attachment_id ) );

		// an administrator is allowed.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( get_post( $attachment_id ) );
	}

	/**
	 * Test that the abilities for a single file check the permission for this file.
	 *
	 * @return void
	 */
	public function test_abilities_require_permission_for_the_file(): void {
		$attachment_id = $this->get_external_file_of_other_user();

		// an author is not allowed to change the file of another user.
		wp_set_current_user( $this->get_user( 'author' ) );
		$abilities = Abilities::get_instance();
		foreach ( array( 'switch_to_local', 'reset_thumbnails', 'check_availability' ) as $method ) {
			$result = $abilities->$method( array( 'attachment_id' => $attachment_id ) );
			$this->assertWPError( $result, $method );
			$this->assertSame( 'efml_not_allowed', $result->get_error_code(), $method );
		}

		// an administrator is allowed (the result depends on the file, but it is not "not allowed").
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$result = $abilities->reset_thumbnails( array( 'attachment_id' => $attachment_id ) );
		$this->assertFalse( is_wp_error( $result ) && 'efml_not_allowed' === $result->get_error_code() );
	}

	/**
	 * Test that the queue could only be managed by users, who are allowed to manage the settings.
	 *
	 * @return void
	 */
	public function test_queue_requires_settings_capability(): void {
		wp_set_current_user( $this->get_user( 'editor' ) );
		$this->assertFalse( Queue::get_instance()->is_queue_action_allowed() );
		$this->assertFalse( Abilities::get_instance()->can_manage_queue() );

		wp_set_current_user( $this->get_user( 'administrator' ) );
		$this->assertTrue( Queue::get_instance()->is_queue_action_allowed() );
		$this->assertTrue( Abilities::get_instance()->can_manage_queue() );
	}

	/**
	 * Test that the actions of the export settings for all files require the capability for the settings.
	 *
	 * @return void
	 */
	public function test_export_settings_actions_require_settings_capability(): void {
		wp_set_current_user( $this->get_user( 'editor', array( 'efml_cap_tools_export' ) ) );
		$this->assertTrue( current_user_can( 'efml_cap_tools_export' ) );
		$this->assertFalse( Export::get_instance()->is_settings_action_allowed() );

		wp_set_current_user( $this->get_user( 'administrator', array( 'efml_cap_tools_export' ) ) );
		$this->assertTrue( Export::get_instance()->is_settings_action_allowed() );
	}

	/**
	 * Test that the connection test requires the capability for external files, also with the capability for
	 * the service.
	 *
	 * @return void
	 */
	public function test_connection_test_requires_capability_for_external_files(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		get_user_by( 'id', $user_id )->add_cap( 'efml_cap_ftp' );
		$this->assertFalse( user_can( $user_id, EFML_CAP_NAME ) );
		wp_set_current_user( $user_id );

		$result = $this->call_ajax(
			array( Connection_Test::get_instance(), 'run_via_ajax' ),
			array(
				'nonce' => wp_create_nonce( 'efml_connection_test' ),
				'type'  => 'ftp',
			)
		);
		$this->assertStringContainsString( 'could not be run', $result['output'] );
	}

	/**
	 * Test that the directory listing does not load an external source of another user.
	 *
	 * @return void
	 */
	public function test_directory_listing_checks_the_source(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$term_id = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->add( 'ftp', 'ftp://permissions.example.com/', array() );
		$this->assertTrue( apply_filters( 'efml_directory_listing_term_allowed', true, $term_id ) );

		// another user is not allowed to use it.
		update_option( 'eml_show_all_external_sources', 0 );
		wp_set_current_user( $this->get_user( 'author' ) );
		$this->assertFalse( apply_filters( 'efml_directory_listing_term_allowed', true, $term_id ) );
	}

	/**
	 * Test that the REST routes of this plugin are not usable for visitors.
	 *
	 * @return void
	 */
	public function test_rest_routes_require_login(): void {
		wp_set_current_user( 0 );
		foreach ( array( array( 'GET', '/efml/v1/file' ), array( 'DELETE', '/efml/v1/file' ), array( 'POST', '/efml/v1/file' ) ) as $route ) {
			$request = new WP_REST_Request( $route[0], $route[1] );
			$request->set_param( 'url', 'https://example.com/file.pdf' );
			$this->assertSame( 401, rest_get_server()->dispatch( $request )->get_status(), implode( ' ', $route ) );
		}
		$this->assertInstanceOf( WP_REST_Server::class, rest_get_server() );
	}
}
