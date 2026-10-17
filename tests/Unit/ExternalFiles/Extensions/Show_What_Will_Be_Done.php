<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Show_What_Will_Be_Done.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Show_What_Will_Be_Done.
 */
class Show_What_Will_Be_Done extends externalFilesTests {
	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'eml_import_extensions' );
		parent::tear_down();
	}

	/**
	 * Test if the preview does not import anything and does not prevent following imports in the same request.
	 *
	 * @return void
	 */
	public function test_preview_does_not_prevent_following_imports(): void {
		update_option( 'eml_import_extensions', array( 'show_what_will_be_done' ) );

		$count = $this->get_media_file_count();

		// run the preview.
		$dialog = \ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Show_What_Will_Be_Done::get_instance()->add_info_in_dialog(
			array( 'texts' => array() ),
			array(
				'urls'   => self::get_test_file( 'pdf', 'http' ),
				'fields' => array(),
			)
		);
		$this->assertIsArray( $dialog );

		// the preview must not import anything.
		$this->assertEquals( $count, $this->get_media_file_count() );

		// the filters of the preview must be removed.
		$this->assertFalse( has_filter( 'efml_prevent_file_import', '__return_true' ) );
		$this->assertFalse( has_filter( 'efml_save_temp_file', '__return_false' ) );

		// a following import must be possible.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$this->assertEquals( $count + 1, $this->get_media_file_count() );
	}

	/**
	 * Test if the dialog is not changed, if the extension is disabled.
	 *
	 * @return void
	 */
	public function test_dialog_is_not_changed_if_disabled(): void {
		update_option( 'eml_import_extensions', array() );
		$dialog = array( 'texts' => array() );
		$this->assertSame(
			$dialog,
			\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Show_What_Will_Be_Done::get_instance()->add_info_in_dialog(
				$dialog,
				array(
					'urls'   => self::get_test_file( 'pdf', 'http' ),
					'fields' => array(),
				)
			)
		);
	}
}
