<?php
/**
 * Tests for the marking of supported plugins and smaller supports of plugins.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\ThirdParty\ThirdPartySupport;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Object to test the marking of supported plugins and smaller supports of plugins.
 */
class SupportedPlugins extends externalFilesTests {

	/**
	 * Test the marking of supported plugins in the list of plugins.
	 *
	 * @return void
	 */
	public function test_mark_supported_plugins(): void {
		$obj      = ThirdPartySupport::get_instance();
		$property = new ReflectionProperty( ThirdPartySupport::class, 'supported_plugin_files' );
		$property->setAccessible( true );
		$property->setValue( $obj, null );

		// test 1: the files of the supported plugins.
		$files = $obj->get_supported_plugin_files();
		foreach ( array( 'media-cleaner/media-cleaner.php', 'media-bulk-download-lite/media-bulk-download.php', 'image-watermark/image-watermark.php', 'prevent-direct-access/prevent-direct-access.php', 'prevent-file-access/media-restriction.php', 'simply-static/simply-static.php', 'woocommerce/woocommerce.php', 'ewww-image-optimizer/ewww-image-optimizer.php' ) as $file ) {
			$this->assertContains( $file, $files );
		}

		// test 2: supported plugins get the hint, others not.
		$meta = $obj->mark_supported_plugin( array( 'Version 1.0' ), 'media-cleaner/media-cleaner.php' );
		$this->assertArrayHasKey( 'efml_supported', $meta );
		$this->assertStringContainsString( 'docs/plugins', $meta['efml_supported'] );
		$this->assertEquals( array( 'Version 1.0' ), $obj->mark_supported_plugin( array( 'Version 1.0' ), 'hello.php' ) );

		// test 3: the hint could be disabled.
		update_option( 'eml_mark_supported_plugins', 0 );
		$this->assertEquals( array( 'Version 1.0' ), $obj->mark_supported_plugin( array( 'Version 1.0' ), 'media-cleaner/media-cleaner.php' ) );
		update_option( 'eml_mark_supported_plugins', 1 );

		// test 4: other plugins could be added.
		$property->setValue( $obj, null );
		$callback = static function ( array $files ): array {
			$files[] = 'my-plugin/my-plugin.php';
			return $files;
		};
		add_filter( 'efml_supported_plugin_files', $callback );
		$this->assertArrayHasKey( 'efml_supported', $obj->mark_supported_plugin( array(), 'my-plugin/my-plugin.php' ) );
		remove_filter( 'efml_supported_plugin_files', $callback );
		$property->setValue( $obj, null );
	}

	/**
	 * Test the hint for Simply Static in the Site Health.
	 *
	 * @return void
	 */
	public function test_simply_static(): void {
		$obj       = \ExternalFilesInMediaLibrary\ThirdParty\SimplyStatic::get_instance();
		$structure = get_option( 'permalink_structure' );

		// test 1: the test is added.
		$tests = $obj->add_site_health_test( array( 'direct' => array() ) );
		$this->assertArrayHasKey( 'efml_simply_static', $tests['direct'] );

		// test 2: good with pretty permalinks.
		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertEquals( 'good', $obj->check_permalinks()['status'] );

		// test 3: a recommendation without them, if the proxy is used.
		update_option( 'permalink_structure', '' );
		$result = $obj->check_permalinks();
		if ( \ExternalFilesInMediaLibrary\ExternalFiles\Proxy::get_instance()->is_any_proxy_enabled() ) {
			$this->assertEquals( 'recommended', $result['status'] );
			$this->assertStringContainsString( 'options-permalink.php', (string) $result['actions'] );
		}
		update_option( 'permalink_structure', $structure );
	}

	/**
	 * Test the backups of Image Watermark, which are exported beside the image.
	 *
	 * @return void
	 */
	public function test_image_watermark_backups(): void {
		$obj    = \ExternalFilesInMediaLibrary\ThirdParty\ImageWatermark::get_instance();
		$method = new ReflectionMethod( \ExternalFilesInMediaLibrary\ThirdParty\ImageWatermark::class, 'get_backup_paths' );
		$method->setAccessible( true );
		$base = trailingslashit( wp_normalize_path( wp_get_upload_dir()['basedir'] ) );

		// test 1: the backup in the directory of the plugin with the path of the image.
		$this->assertEquals( array( $base . 'iw-backup/2026/11/image.jpg' => 'image.iw-backup.jpg' ), $method->invoke( $obj, $base . '2026/11/image.jpg' ) );

		// test 2: none for files outside the uploads directory.
		$this->assertEquals( array(), $method->invoke( $obj, '/tmp/image.jpg' ) );

		// test 3: no delay for uploads, as the plugin adds the watermark before the export.
		$this->assertEquals( 0, $obj->set_delay( 0, 1 ) );
	}
}
