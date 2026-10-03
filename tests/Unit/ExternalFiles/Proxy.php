<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ExternalFiles\Proxy.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ExternalFiles\Proxy.
 */
class Proxy extends externalFilesTests {

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), '' );
		update_option( 'eml_log_mode', 2 );
		parent::tear_down();
	}

	/**
	 * Return the amount of log entries.
	 *
	 * @return int
	 */
	private function get_log_count(): int {
		return count( \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries() );
	}

	/**
	 * Test if the template is returned unchanged if the proxy is not requested.
	 *
	 * @return void
	 */
	public function test_run_without_proxy_request(): void {
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );
	}

	/**
	 * Test if a request for an unknown file does not create any log entry, if the debug mode is disabled,
	 * as this request could be sent by any visitor of the website.
	 *
	 * @return void
	 */
	public function test_run_with_unknown_file_creates_no_log_entry(): void {
		// disable the debug mode.
		update_option( 'eml_log_mode', 0 );
		$log_count = $this->get_log_count();

		// request unknown files.
		foreach ( array( 'unknown-file.jpg', 'unknown-file-150x150.jpg', '<img src=x onerror=alert(1)>' ) as $title ) {
			set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), $title );
			$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );
		}

		// no new log entry must exist.
		$this->assertEquals( $log_count, $this->get_log_count() );
	}

	/**
	 * Test if the requested name is saved escaped in the log, if the debug mode is enabled.
	 *
	 * @return void
	 */
	public function test_run_with_unknown_file_escapes_the_name_in_debug_mode(): void {
		// enable the debug mode.
		update_option( 'eml_log_mode', 2 );
		$log_count = $this->get_log_count();

		// request an unknown file with HTML in its name.
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), 'x<img src=x onerror=alert(1)>.jpg' );
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );

		// log entries must exist, but without the HTML.
		$entries = \ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->get_entries();
		$this->assertGreaterThan( $log_count, count( $entries ) );
		foreach ( $entries as $entry ) {
			$this->assertStringNotContainsString( '<img', $entry['log'] );
		}
	}

	/**
	 * Test if not usable values do not result in an error or a log entry.
	 *
	 * @return void
	 */
	public function test_run_with_not_usable_values(): void {
		$log_count = $this->get_log_count();

		// an array, e.g. via "?emlproxy[]=x".
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), array( 'x' ) );
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );

		// an overlong value.
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), str_repeat( 'a', 5000 ) . '.jpg' );
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );

		// no new log entry must exist.
		$this->assertEquals( $log_count, $this->get_log_count() );
	}
}
