<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ThirdParty\Folders.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ThirdParty\Folders.
 */
class Folders extends externalFilesTests {

	/**
	 * Test if the callback for meta boxes does not fail for other objects than posts,
	 * as WordPress uses this hook also for comments and links.
	 *
	 * @return void
	 */
	public function test_remove_meta_boxes_with_other_objects(): void {
		$folders_obj = \ExternalFilesInMediaLibrary\ThirdParty\Folders::get_instance();

		// with a comment.
		$comment = get_comment( self::factory()->comment->create() );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
		$folders_obj->remove_meta_boxes( 'comment', $comment );

		// with an attachment, which is not an external file.
		$folders_obj->remove_meta_boxes( 'attachment', get_post( self::factory()->attachment->create() ) );

		// with an unexpected value.
		$folders_obj->remove_meta_boxes( 'attachment', null );

		// no error occurred.
		$this->assertTrue( true );
	}
}
