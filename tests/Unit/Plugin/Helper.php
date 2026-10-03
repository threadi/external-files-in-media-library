<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\Plugin\Helper.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\Plugin\Helper.
 */
class Helper extends externalFilesTests {

	/**
	 * Test if the returning variable is an array and has the key "image/jpeg".
	 *
	 * @return void
	 */
	public function test_get_possible_mime_types(): void {
		$possible_mime_types = \ExternalFilesInMediaLibrary\Plugin\Helper::get_possible_mime_types();
		$this->assertIsArray( $possible_mime_types );
		$this->assertArrayHasKey( 'image/jpeg', $possible_mime_types );
	}

	/**
	 * Test if the returning variable is an array and contains "image/jpeg".
	 *
	 * @return void
	 */
	public function test_get_allowed_mime_types(): void {
		$allowed_mime_types = \ExternalFilesInMediaLibrary\Plugin\Helper::get_allowed_mime_types();
		$this->assertIsArray( $allowed_mime_types );
		$this->assertContains( 'image/jpeg', $allowed_mime_types );
	}

	/**
	 * Test if the returning variable is an int.
	 *
	 * @return void
	 */
	public function test_get_current_user_id(): void {
		$current_user_id = \ExternalFilesInMediaLibrary\Plugin\Helper::get_current_user_id();
		$this->assertIsInt( $current_user_id );
		$this->assertEquals( 1, $current_user_id );
	}

	/**
	 * Test if the returning variable is an array.
	 *
	 * @return void
	 */
	public function test_get_intervals(): void {
		$intervals = \ExternalFilesInMediaLibrary\Plugin\Intervals::get_instance()->get_intervals_for_settings();
		$this->assertIsArray( $intervals );
		$this->assertArrayHasKey( 'eml_disable_check', $intervals );
	}

	/**
	 * Test if the returning variable is a shortened URL.
	 *
	 * @return void
	 */
	public function test_shorten_url(): void {
		$shorten_url = \ExternalFilesInMediaLibrary\Plugin\Helper::shorten_url( 'https://github.com/threadi/external-files-in-media-library' );
		$this->assertIsString( $shorten_url );
		$this->assertEquals( 'https://github.com/../external-files-in-media-library', $shorten_url );
	}

	/**
	 * Test if the returning variable is a WP_Filesysten_Base object.
	 *
	 * @return void
	 */
	public function test_get_wp_filesystem(): void {
		$wp_filesystem = \ExternalFilesInMediaLibrary\Plugin\Helper::get_wp_filesystem();
		$this->assertInstanceOf( \WP_Filesystem_Base::class, $wp_filesystem );
	}

	/**
	 * Test if the returning variable is a string.
	 *
	 * @return void
	 */
	public function test_get_as_string(): void {
		// test 1 with an array.
		$string_1 = \ExternalFilesInMediaLibrary\Plugin\Helper::get_as_string( array( 'test' ) );
		$this->assertIsString( $string_1 );
		$this->assertEquals( '', $string_1 );

		// test 2 with an int.
		$string_2 = \ExternalFilesInMediaLibrary\Plugin\Helper::get_as_string( 1 );
		$this->assertIsString( $string_2 );
		$this->assertEquals( '', $string_2 );

		// test 3 with a string.
		$string_3 = \ExternalFilesInMediaLibrary\Plugin\Helper::get_as_string( 'test 3' );
		$this->assertIsString( $string_3 );
		$this->assertEquals( 'test 3', $string_3 );
	}

	/**
	 * Test if the returning variable is correct.
	 *
	 * @return void
	 */
	public function test_has_current_user_role(): void {
		// test 1: not logged in.
		$not_logged_in = \ExternalFilesInMediaLibrary\Plugin\Helper::has_current_user_role( 'administrator' );
		$this->assertIsBool( $not_logged_in );
		$this->assertFalse( $not_logged_in );

		// test 2: logged in.
		$user_id = 1;
		$user_login = 'admin';
		wp_set_current_user( $user_id, $user_login );
		wp_set_auth_cookie( $user_id );
		do_action('wp_login', $user_login );
		$logged_in = \ExternalFilesInMediaLibrary\Plugin\Helper::has_current_user_role( 'administrator' );
		$this->assertIsBool( $logged_in );
		$this->assertTrue( $logged_in );
	}

	/**
	 * Test if the returning variable is correct.
	 *
	 * @return void
	 */
	public function test_get_plugin_name(): void {
		$plugin_name = \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_name();
		$this->assertIsString( $plugin_name );
		$this->assertEquals( 'External files in Media Library', $plugin_name );
	}

	/**
	 * Test if not allowed HTML is removed from texts for log entries and results.
	 *
	 * @return void
	 */
	public function test_kses_log_removes_not_allowed_html(): void {
		// an image with event handler, e.g. from an HTTP header of an external server.
		$text = \ExternalFilesInMediaLibrary\Plugin\Helper::kses_log( '<code>x<img src=x onerror=alert(1)></code>' );
		$this->assertEquals( '<code>x</code>', $text );

		// a script.
		$text = \ExternalFilesInMediaLibrary\Plugin\Helper::kses_log( 'a<script>alert(1)</script>b' );
		$this->assertStringNotContainsString( '<script', $text );

		// a link with attributes, which could be used to open a dialog, and a not allowed protocol.
		$text = \ExternalFilesInMediaLibrary\Plugin\Helper::kses_log( '<a class="easy-dialog-for-wordpress" data-dialog="{}" href="javascript:alert(1)">link</a>' );
		$this->assertStringNotContainsString( 'class=', $text );
		$this->assertStringNotContainsString( 'data-dialog', $text );
		$this->assertStringNotContainsString( 'javascript', $text );
	}

	/**
	 * Test if the HTML we use ourselves in log entries and results is not removed.
	 *
	 * @return void
	 */
	public function test_kses_log_keeps_allowed_html(): void {
		$text = 'Check the <a href="https://example.com/wp-admin/" target="_blank">log' . \ExternalFilesInMediaLibrary\Plugin\Helper::get_a11n_window_hint() . '</a> for <code>details</code>, <em>please</em>.<br>';
		$this->assertEquals( $text, \ExternalFilesInMediaLibrary\Plugin\Helper::kses_log( $text ) );
	}

	/**
	 * Test the check for local paths, which must be located in the allowed base directory.
	 *
	 * @return void
	 */
	public function test_is_local_path_allowed(): void {
		// use the plugin path as allowed base directory.
		add_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );
		$plugin_path = \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path();

		// existing file with and without protocol.
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( $plugin_path . 'tests/Data/example.pdf' ) );
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( 'file://' . $plugin_path . 'tests/Data/example.pdf' ) );

		// not existing file in an existing directory (e.g., the target of an export).
		$this->assertTrue( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( $plugin_path . 'tests/Data/not-existing-file.pdf' ) );

		// file outside the base directory.
		$outside_file = wp_tempnam( 'efml-outside' );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( $outside_file ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( 'file://' . $outside_file ) );

		// path, which leaves the base directory.
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( $plugin_path . 'tests/Data/../../../' . basename( $outside_file ) ) );

		// not existing directory and empty path.
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( $plugin_path . 'not-existing-directory/sub/file.pdf' ) );
		$this->assertFalse( \ExternalFilesInMediaLibrary\Plugin\Helper::is_local_path_allowed( '' ) );

		// cleanup.
		wp_delete_file( $outside_file );
		remove_filter( 'efml_file_base', array( $this, 'set_plugin_path_as_base' ) );
	}

	/**
	 * Test if the file version for enqueued files is not the path of the plugin.
	 *
	 * @return void
	 */
	public function test_get_file_version(): void {
		$file_version = \ExternalFilesInMediaLibrary\Plugin\Helper::get_file_version( \ExternalFilesInMediaLibrary\Plugin\Helper::get_plugin_path() . 'admin/js.js' );
		$this->assertIsString( $file_version );
		$this->assertNotEmpty( $file_version );
		$this->assertStringNotContainsString( '/', $file_version );
		$this->assertStringNotContainsString( '.php', $file_version );
	}
}
