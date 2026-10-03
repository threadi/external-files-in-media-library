<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ThirdParty\Brizy.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ThirdParty\Brizy.
 */
class Brizy extends externalFilesTests {

	/**
	 * Test if the callback for image sources does not fail and does not change the value,
	 * if no image is available (WordPress uses false in this case).
	 *
	 * @return void
	 */
	public function test_change_image_src_without_image(): void {
		$this->assertFalse( \ExternalFilesInMediaLibrary\ThirdParty\Brizy::get_instance()->change_image_src( false, 0 ) );
	}

	/**
	 * Test if the callback does not change the image if the request is not from Brizy.
	 *
	 * @return void
	 */
	public function test_change_image_src_without_brizy_request(): void {
		$image = array( 'https://example.org/image.jpg', 300, 200, false );
		$this->assertEquals( $image, \ExternalFilesInMediaLibrary\ThirdParty\Brizy::get_instance()->change_image_src( $image, self::factory()->attachment->create() ) );
	}
}
