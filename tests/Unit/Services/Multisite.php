<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Services\Multisite.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Services;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Services\Multisite.
 */
class Multisite extends externalFilesTests {
	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$name = \ExternalFilesInMediaLibrary\Services\Multisite::get_instance()->get_name();
		$this->assertIsString( $name );
		$this->assertNotEmpty( $name );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_directory(): void {
		$directory = \ExternalFilesInMediaLibrary\Services\Multisite::get_instance()->get_directory();
		$this->assertIsString( $directory );
		$this->assertEmpty( $directory );
	}

	/**
	 * Test if the access to a blog is not allowed without a blog ID or without a user.
	 *
	 * @return void
	 */
	public function test_is_blog_allowed_without_blog_or_user(): void {
		$multisite_obj = \ExternalFilesInMediaLibrary\Services\Multisite::get_instance();

		// without a blog ID, also as administrator.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( $multisite_obj->is_blog_allowed( 0 ) );

		// without a user.
		wp_set_current_user( 0 );
		$this->assertFalse( $multisite_obj->is_blog_allowed( get_current_blog_id() ) );
	}

	/**
	 * Test if the access to a blog depends on the capability of the user to upload files in it.
	 *
	 * @return void
	 */
	public function test_is_blog_allowed_depends_on_capability(): void {
		$multisite_obj = \ExternalFilesInMediaLibrary\Services\Multisite::get_instance();

		// a user who is not allowed to upload files.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( $multisite_obj->is_blog_allowed( get_current_blog_id() ) );

		// a user who is allowed to upload files.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertTrue( $multisite_obj->is_blog_allowed( get_current_blog_id() ) );
	}

	/**
	 * Test if the access to a blog is changeable via hook.
	 *
	 * @return void
	 */
	public function test_is_blog_allowed_is_filterable(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		add_filter( 'efml_multisite_blog_allowed', '__return_true' );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Services\Multisite::get_instance()->is_blog_allowed( get_current_blog_id() ) );
		remove_filter( 'efml_multisite_blog_allowed', '__return_true' );
	}

	/**
	 * Test if the listing of a blog is empty for a user without access to it.
	 *
	 * @return void
	 */
	public function test_get_directory_listing_without_access(): void {
		$multisite_obj = \ExternalFilesInMediaLibrary\Services\Multisite::get_instance();
		$blog_id       = get_current_blog_id();

		// add an attachment.
		self::factory()->attachment->create();

		// request the listing as user without access.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$multisite_obj->set_fields( array( 'website' => array( 'value' => $blog_id ) ) );
		$this->assertEmpty( $multisite_obj->get_directory_listing( '' ) );

		// the actual blog must not be changed.
		$this->assertEquals( $blog_id, get_current_blog_id() );
	}
}
