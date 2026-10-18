<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates.
 */
class Dates extends externalFilesTests {
	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates::get_instance()->remove_action_after_sync();
		unset( $_POST['use_dates'] );
		parent::tear_down();
	}

	/**
	 * Test if the date of the external file is used, if it is enabled in request.
	 *
	 * @return void
	 */
	public function test_add_file_date(): void {
		$timestamp = strtotime( '2024-05-01 12:00:00 UTC' );
		$obj       = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates::get_instance();

		// not enabled.
		$this->assertSame( array(), $obj->add_file_date( array(), 'https://example.com/example.pdf', array( 'last-modified' => $timestamp ) ) );

		// disabled.
		$_POST['use_dates'] = 0;
		$this->assertSame( array(), $obj->add_file_date( array(), 'https://example.com/example.pdf', array( 'last-modified' => $timestamp ) ) );

		// enabled.
		$_POST['use_dates'] = 1;
		$post_array         = $obj->add_file_date( array(), 'https://example.com/example.pdf', array( 'last-modified' => $timestamp ) );
		$this->assertEquals( '2024-05-01 12:00:00', $post_array['post_date'] );

		// enabled, but without date.
		$this->assertSame( array(), $obj->add_file_date( array(), 'https://example.com/example.pdf', array() ) );
	}

	/**
	 * Test if the setting of an external source is not used for the next external source in the same request.
	 *
	 * @return void
	 */
	public function test_setting_is_removed_after_sync(): void {
		$taxonomy = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name();
		$term_a   = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		$term_b   = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		update_term_meta( $term_a, 'use_dates', 1 );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates::get_instance();

		// sync the first source.
		$obj->add_action_before_sync( '', array(), $term_a );
		$this->assertEquals( 1, $_POST['use_dates'] );
		$obj->remove_action_after_sync();

		// sync the second source.
		$obj->add_action_before_sync( '', array(), $term_b );
		$this->assertArrayNotHasKey( 'use_dates', $_POST );
	}
}
