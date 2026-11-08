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

	/**
	 * Test if files in trash are not delivered by the proxy.
	 *
	 * @return void
	 */
	public function test_run_with_trashed_file(): void {
		// import an external file.
		$this->assertTrue( \ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) ) );
		$external_file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $external_file_obj );

		// move it to trash.
		$this->assertInstanceOf( \WP_Post::class, wp_trash_post( $external_file_obj->get_id() ) );

		// request it via proxy.
		$proxy_actions = did_action( 'efml_proxy_before' );
		set_query_var( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->get_slug(), get_the_title( $external_file_obj->get_id() ) );
		$this->assertEquals( 'my-template.php', \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->run( 'my-template.php' ) );

		// the proxy must not try to load or cache the file.
		$this->assertSame( $proxy_actions, did_action( 'efml_proxy_before' ) );
		$this->assertFalse( $external_file_obj->is_cached() );
	}
	/**
	 * Test that changing a proxy setting plans the refresh of the rewrite rules.
	 *
	 * @return void
	 */
	public function test_proxy_setting_change_plans_refresh_of_rewrite_rules(): void {
		$transient = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->get_transient_by_name( 'eml_refresh_rewrite_rules' );
		$transient->delete();
		$this->assertFalse( $transient->is_set() );

		// another option does not plan it.
		update_option( 'eml_log_mode', 1 );
		$this->assertFalse( $transient->is_set() );

		// the proxy setting of a file type plans it.
		update_option( 'eml_images_proxy', 0 === absint( get_option( 'eml_images_proxy' ) ) ? 1 : 0 );
		$this->assertTrue( $transient->is_set() );

		// also the mode of a file type.
		$transient->delete();
		update_option( 'eml_pdf_mode', 'local' === get_option( 'eml_pdf_mode' ) ? 'external' : 'local' );
		$this->assertTrue( $transient->is_set() );
		$transient->delete();
	}

	/**
	 * Test that the proxy is registered (rewrite rule, query var and delivery) also if all proxy settings are disabled.
	 *
	 * Files which could not be loaded by the browser (e.g. via FTP) are always delivered via the proxy.
	 *
	 * @return void
	 */
	public function test_proxy_is_registered_without_enabled_proxy(): void {
		$proxy = \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance();
		foreach ( \ExternalFilesInMediaLibrary\ExternalFiles\File_Types::get_instance()->get_file_types_as_objects() as $file_type_obj ) {
			update_option( 'eml_' . strtolower( $file_type_obj->get_name() ) . '_proxy', 0 );
		}
		$this->assertFalse( $proxy->is_any_proxy_enabled() );

		// initialize the proxy like in a request with these settings.
		remove_action( 'init', array( $proxy, 'wp_init' ) );
		remove_filter( 'query_vars', array( $proxy, 'set_query_vars' ) );
		remove_filter( 'template_include', array( $proxy, 'run' ) );
		$proxy->init();
		$this->assertSame( 10, has_action( 'init', array( $proxy, 'wp_init' ) ) );
		$this->assertSame( 10, has_filter( 'query_vars', array( $proxy, 'set_query_vars' ) ) );
		$this->assertSame( 10, has_filter( 'template_include', array( $proxy, 'run' ) ) );

		// the rewrite rule is created during the refresh.
		$this->set_permalink_structure( '/%postname%/' );
		\ExternalFilesInMediaLibrary\ExternalFiles\Proxy::do_refresh();
		$rules = get_option( 'rewrite_rules' );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( $proxy->get_slug() . '/([a-zA-Z0-9-_.]+)?$', $rules );
		$this->set_permalink_structure( '' );
	}
}
