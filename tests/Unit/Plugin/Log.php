<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Log.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Log.
 */
class Log extends externalFilesTests {

	/**
	 * Test if HTML from external sources is removed from a log entry before it is saved.
	 *
	 * @return void
	 */
	public function test_create_removes_not_allowed_html(): void {
		$url = 'https://example.com/log-test-html.jpg';
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( 'Not allowed mime-type <code>x<img src=x onerror=alert(1)></code> <script>alert(1)</script>', $url, 'error' );

		// get the entry.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries( array( 'url' => $url ) );
		$this->assertCount( 1, $entries );
		$this->assertStringNotContainsString( '<img', $entries[0]['log'] );
		$this->assertStringNotContainsString( 'onerror', $entries[0]['log'] );
		$this->assertStringNotContainsString( '<script', $entries[0]['log'] );
		$this->assertStringContainsString( '<code>x</code>', $entries[0]['log'] );
	}

	/**
	 * Test if the HTML we use ourselves in log entries is still saved.
	 *
	 * @return void
	 */
	public function test_create_keeps_allowed_html(): void {
		$url     = 'https://example.com/log-test-allowed.jpg';
		$message = 'Text with <code>code</code>, <em>em</em> and <a href="https://example.com/" target="_blank">a link</a>.';
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( $message, $url, 'info' );

		// get the entry.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries( array( 'url' => $url ) );
		$this->assertCount( 1, $entries );
		$this->assertEquals( $message, $entries[0]['log'] );
	}

	/**
	 * Test if an entry with level 2 is not saved if the debug mode is disabled.
	 *
	 * @return void
	 */
	public function test_create_respects_log_level(): void {
		$url = 'https://example.com/log-test-level.jpg';

		// disable the debug mode.
		update_option( 'eml_log_mode', 0 );
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( 'Debug entry', $url, 'info', 2 );
		$this->assertCount( 0, \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries( array( 'url' => $url ) ) );

		// enable the debug mode.
		update_option( 'eml_log_mode', 2 );
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( 'Debug entry', $url, 'info', 2 );
		$this->assertCount( 1, \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries( array( 'url' => $url ) ) );
	}

	/**
	 * Test if the search for a URL with special chars does find the entry, also if the
	 * requested value has been entity-encoded by the sanitizing of the request.
	 *
	 * @return void
	 */
	public function test_get_entries_for_url_with_special_chars(): void {
		$url = 'https://example.com/log-test-search.jpg?a=1&b=2';
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( 'Search entry', $url, 'info' );

		// search with the original URL.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries(
			array(
				'url'      => $url,
				'url_like' => true,
			)
		);
		$this->assertCount( 1, $entries );

		// search with the URL as it is returned by filter_input() with FILTER_SANITIZE_FULL_SPECIAL_CHARS.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries(
			array(
				'url'      => 'https://example.com/log-test-search.jpg?a=1&amp;b=2',
				'url_like' => true,
			)
		);
		$this->assertCount( 1, $entries );
	}

	/**
	 * Test if the log URL contains the given URL in encoded form.
	 *
	 * @return void
	 */
	public function test_get_log_url_encodes_the_url(): void {
		$log_url = \ExternalFilesInMediaLibrary\Plugin\Helper::get_log_url( 'https://example.com/a"onmouseover="alert(1)".jpg?a=1&b=2' );
		$this->assertIsString( $log_url );
		$this->assertStringNotContainsString( '"', $log_url );
		$this->assertStringNotContainsString( '&b=2', $log_url );

		// the value must be readable again from the URL.
		parse_str( (string) wp_parse_url( $log_url, PHP_URL_QUERY ), $query );
		$this->assertEquals( 'https://example.com/a"onmouseover="alert(1)".jpg?a=1&b=2', $query['s'] );
	}
}
