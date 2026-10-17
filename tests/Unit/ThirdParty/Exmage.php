<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ThirdParty\Exmage.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ThirdParty\Exmage.
 */
class Exmage extends externalFilesTests {
	/**
	 * Test if only files of Exmage are returned, including files in trash.
	 *
	 * @return void
	 */
	public function test_get_files(): void {
		$exmage_file = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $exmage_file, '_exmage_external_url', 'https://example.com/a.jpg' );

		$trashed_exmage_file = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );
		update_post_meta( $trashed_exmage_file, '_exmage_external_url', 'https://example.com/b.jpg' );
		wp_trash_post( $trashed_exmage_file );

		$other_file = self::factory()->attachment->create( array( 'post_mime_type' => 'image/jpeg' ) );

		$files = \ExternalFilesInMediaLibrary\ThirdParty\Exmage::get_instance()->get_files();
		sort( $files );
		$expected = array( $exmage_file, $trashed_exmage_file );
		sort( $expected );
		$this->assertEquals( $expected, $files );
		$this->assertNotContains( $other_file, $files );
	}
}
