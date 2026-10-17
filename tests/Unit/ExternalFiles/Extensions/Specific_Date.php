<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date.
 */
class Specific_Date extends externalFilesTests {
	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance()->remove_action_after_sync();
		unset( $_POST['use_specific_date'] );
		parent::tear_down();
	}

	/**
	 * Test if a valid date is used as post date.
	 *
	 * @return void
	 */
	public function test_add_file_date_with_valid_date(): void {
		$_POST['use_specific_date'] = '2024-05-01';
		$post_array                 = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance()->add_file_date( array(), 'https://example.com/example.pdf', array() );
		$this->assertEquals( '2024-05-01 00:00:00', $post_array['post_date'] );
	}

	/**
	 * Test if an invalid date is not used (and does not result in 1970-01-01).
	 *
	 * @return void
	 */
	public function test_add_file_date_with_invalid_date(): void {
		$_POST['use_specific_date'] = 'not-a-date';
		$post_array                 = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance()->add_file_date( array( 'post_title' => 'example' ), 'https://example.com/example.pdf', array() );
		$this->assertArrayNotHasKey( 'post_date', $post_array );
	}

	/**
	 * Test if nothing is changed without a date in request.
	 *
	 * @return void
	 */
	public function test_add_file_date_without_date(): void {
		$post_array = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance()->add_file_date( array( 'post_title' => 'example' ), 'https://example.com/example.pdf', array() );
		$this->assertSame( array( 'post_title' => 'example' ), $post_array );
	}

	/**
	 * Test if the date of an external source is not used for the next external source in the same request.
	 *
	 * @return void
	 */
	public function test_date_is_removed_after_sync(): void {
		$taxonomy = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name();
		$term_a   = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		$term_b   = self::factory()->term->create( array( 'taxonomy' => $taxonomy ) );
		update_term_meta( $term_a, 'use_specific_date', '2024-05-01' );

		$obj = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance();

		// sync the first source.
		$obj->add_action_before_sync( '', array(), $term_a );
		$this->assertEquals( '2024-05-01', $_POST['use_specific_date'] );
		$obj->remove_action_after_sync();

		// sync the second source.
		$obj->add_action_before_sync( '', array(), $term_b );
		$this->assertArrayNotHasKey( 'use_specific_date', $_POST );
	}

	/**
	 * Test if a value from the actual request is not removed.
	 *
	 * @return void
	 */
	public function test_request_value_is_kept(): void {
		$_POST['use_specific_date'] = '2023-01-01';
		\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Specific_Date::get_instance()->remove_action_after_sync();
		$this->assertEquals( '2023-01-01', $_POST['use_specific_date'] );
	}
}
