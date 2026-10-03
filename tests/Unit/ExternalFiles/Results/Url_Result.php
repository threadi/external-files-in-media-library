<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Results;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result.
 */
class Url_Result extends externalFilesTests {

	/**
	 * Test if the text of an error for a URL, which is not in the media library, does not contain
	 * HTML from the URL or from the answer of the external server.
	 *
	 * @return void
	 */
	public function test_get_text_for_error_escapes_external_data(): void {
		$result_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result();
		$result_obj->set_url( 'https://example.com/<img src=x onerror=alert(1)>.jpg' );
		$result_obj->set_error( true );
		$result_obj->set_result_text( 'Specified URL response with a not allowed mime-type <code>x<img src=x onerror=alert(2)></code>.' );

		// get the text.
		$text = $result_obj->get_text();
		$this->assertIsString( $text );
		$this->assertStringNotContainsString( '<img', $text );
		$this->assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $text );
		$this->assertStringContainsString( '<code>x</code>', $text );
	}

	/**
	 * Test if the text for a string, which is not a URL, does not contain its HTML.
	 *
	 * @return void
	 */
	public function test_get_text_for_not_valid_url_escapes_external_data(): void {
		$result_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result();
		$result_obj->set_url( '<svg onload=alert(1)>' );
		$result_obj->set_error( false );
		$result_obj->set_result_text( '<script>alert(2)</script>ok' );

		// get the text.
		$text = $result_obj->get_text();
		$this->assertStringNotContainsString( '<svg', $text );
		$this->assertStringNotContainsString( '<script', $text );
	}

	/**
	 * Test if the text for a valid URL contains the link to it and the allowed HTML of the result text.
	 *
	 * @return void
	 */
	public function test_get_text_for_valid_url(): void {
		$result_obj = new \ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result();
		$result_obj->set_url( 'https://example.com/not-imported.jpg' );
		$result_obj->set_error( false );
		$result_obj->set_result_text( 'Text with <em>allowed HTML</em>.' );

		// get the text.
		$text = $result_obj->get_text();
		$this->assertStringContainsString( '<a href="https://example.com/not-imported.jpg"', $text );
		$this->assertStringContainsString( 'Text with <em>allowed HTML</em>.', $text );
	}
}
