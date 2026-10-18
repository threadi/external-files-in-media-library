<?php
/**
 * Tests for the setting of the proxy path.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\Plugin;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the change of the proxy path.
 */
class ProxyPath extends externalFilesTests {
	/**
	 * The first directory of the used paths in wp-content.
	 *
	 * @var string
	 */
	private string $base = '';

	/**
	 * Prepare each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->base = 'efml-proxy-test-' . strtolower( wp_generate_password( 8, false ) );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		Helper::delete_directory_recursively( trailingslashit( WP_CONTENT_DIR ) . $this->base );
		parent::tear_down();
	}

	/**
	 * Test that a new proxy path with several not existing directories is created.
	 *
	 * @return void
	 */
	public function test_new_path_with_missing_parent_directories(): void {
		$new_value = $this->base . '/cache/proxy/';
		$this->assertSame( $new_value, Settings::get_instance()->save_proxy_path( $new_value, $this->base . '/not-existing/' ) );
		$this->assertDirectoryExists( trailingslashit( WP_CONTENT_DIR ) . $new_value );
	}

	/**
	 * Test that the cached files are moved to a new proxy path with several not existing directories.
	 *
	 * @return void
	 */
	public function test_move_to_path_with_missing_parent_directories(): void {
		$old_value = $this->base . '/old/';
		$old_path  = trailingslashit( WP_CONTENT_DIR ) . $old_value;
		$this->assertTrue( Helper::create_directory( $old_path ) );
		file_put_contents( $old_path . 'cached.jpg', 'cached' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$new_value = $this->base . '/new/cache/proxy/';
		$this->assertSame( $new_value, Settings::get_instance()->save_proxy_path( $new_value, $old_value ) );
		$this->assertFileExists( trailingslashit( WP_CONTENT_DIR ) . $new_value . 'cached.jpg' );
		$this->assertFileDoesNotExist( $old_path . 'cached.jpg' );
	}
}
