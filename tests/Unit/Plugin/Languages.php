<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Languages.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Languages.
 */
class Languages extends externalFilesTests {

	/**
	 * Test if the returning variable is "true".
	 *
	 * @return void
	 */
	public function test_is_german_language(): void {
		// test 1: test default language.
		$is_german_language = \ExternalFilesInMediaLibrary\Plugin\Languages::get_instance()->is_german_language();
		$this->assertIsBool( $is_german_language );
		$this->assertFalse( $is_german_language );

		// test 2: test after switch to german locale.
		switch_to_locale( 'de_DE' );
		$is_german_language = \ExternalFilesInMediaLibrary\Plugin\Languages::get_instance()->is_german_language();
		$this->assertIsBool( $is_german_language );
		$this->assertTrue( $is_german_language );
	}

	/**
	 * Test if the returning variable is the current language with 2 chars.
	 *
	 * @return void
	 */
	public function test_get_current_lang(): void {
		// test 1: test default language.
		$current_lang = \ExternalFilesInMediaLibrary\Plugin\Languages::get_instance()->get_current_lang();
		$this->assertIsString( $current_lang );
		$this->assertNotEquals( substr( get_option( 'WPLANG' ), 0, 2) , $current_lang );

		// test 2: test after switch to german locale.
		switch_to_locale( 'de_DE' );
		$current_lang = \ExternalFilesInMediaLibrary\Plugin\Languages::get_instance()->get_current_lang();
		$this->assertIsString( $current_lang );
		$this->assertNotEquals( substr( get_option( 'WPLANG' ), 0, 2) , $current_lang );
	}
}
