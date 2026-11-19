<?php
/**
 * Tests for the downloads of products in class ExternalFilesInMediaLibrary\ThirdParty\WooCommerce.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the downloads of products in class ExternalFilesInMediaLibrary\ThirdParty\WooCommerce.
 */
class WooCommerceDownloads extends externalFilesTests {
	/**
	 * Prepare a file.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		// add the test URL in the media library.
		\ExternalFilesInMediaLibrary\ExternalFiles\Import::get_instance()->add_url( self::get_test_file( 'pdf', 'http' ) );

		// disable the proxy for PDF files.
		update_option( 'eml_pdf_mode', 'external' );
		update_option( 'eml_pdf_proxy', 0 );
	}

	/**
	 * Cleanup after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'efml_private_file_signed_url' );
		$this->do_logout();
		parent::tear_down();
	}

	/**
	 * Return the file object of the test URL.
	 *
	 * @return \ExternalFilesInMediaLibrary\ExternalFiles\File
	 */
	private function get_file_object(): \ExternalFilesInMediaLibrary\ExternalFiles\File {
		$file_obj = \ExternalFilesInMediaLibrary\ExternalFiles\Files::get_instance()->get_file_by_url( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $file_obj );
		return $file_obj;
	}

	/**
	 * Return a signed URL with the given validity in the query.
	 *
	 * @param string                                          $url The signed URL.
	 * @param \ExternalFilesInMediaLibrary\ExternalFiles\File $external_file_obj The file.
	 * @param int                                             $start The start.
	 * @param int                                             $expires The end.
	 *
	 * @return string
	 */
	public function get_signed_url( string $url, \ExternalFilesInMediaLibrary\ExternalFiles\File $external_file_obj, int $start, int $expires ): string {
		return 'https://signed.example.com/example.pdf?validity=' . ( $expires - $start );
	}

	/**
	 * Test to find external files by the path of a download.
	 *
	 * @return void
	 */
	public function test_get_external_file_by_download_path(): void {
		$woocommerce = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance();
		$file_obj    = $this->get_file_object();

		// by the external URL.
		$result = $woocommerce->get_external_file_by_download_path( self::get_test_file( 'pdf', 'http' ) );
		$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $result );
		$this->assertEquals( $file_obj->get_id(), $result->get_id() );

		// by the proxy URL (simple and pretty permalinks).
		$title = basename( self::get_test_file( 'pdf', 'http' ) );
		foreach ( array( 'http://example.org/?emlproxy=' . $title, 'http://example.org/emlproxy/' . $title ) as $proxy_url ) {
			$result = $woocommerce->get_external_file_by_download_path( $proxy_url );
			$this->assertInstanceOf( \ExternalFilesInMediaLibrary\ExternalFiles\File::class, $result );
			$this->assertEquals( $file_obj->get_id(), $result->get_id() );
		}

		// unknown paths.
		$this->assertFalse( $woocommerce->get_external_file_by_download_path( '' ) );
		$this->assertFalse( $woocommerce->get_external_file_by_download_path( 'https://example.com/unknown.pdf' ) );
		$this->assertFalse( $woocommerce->get_external_file_by_download_path( 'http://example.org/emlproxy/unknown.pdf' ) );
	}

	/**
	 * Test the path for downloads.
	 *
	 * @return void
	 */
	public function test_get_download_file_path(): void {
		$woocommerce = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance();
		$file_obj    = $this->get_file_object();
		$proxy_url   = 'http://example.org/?emlproxy=' . basename( self::get_test_file( 'pdf', 'http' ) );

		// other values are not changed.
		$this->assertEquals( 123, $woocommerce->get_download_file_path( 123 ) );
		$this->assertEquals( 'https://example.com/unknown.pdf', $woocommerce->get_download_file_path( 'https://example.com/unknown.pdf' ) );

		// public files are delivered from their external URL.
		$this->assertEquals( self::get_test_file( 'pdf', 'http' ), $woocommerce->get_download_file_path( $proxy_url ) );

		// private files are delivered via a signed URL with short validity.
		$file_obj->set_private( true );
		add_filter( 'efml_private_file_signed_url', array( $this, 'get_signed_url' ), 10, 4 );
		$this->assertEquals( 'https://signed.example.com/example.pdf?validity=300', $woocommerce->get_download_file_path( $proxy_url ) );
	}

	/**
	 * Test the path for downloads of private files, which could not be signed.
	 *
	 * @return void
	 */
	public function test_get_download_file_path_from_cache(): void {
		$woocommerce = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance();
		$file_obj    = $this->get_file_object();
		$file_obj->set_private( true );

		// the file is delivered from the cache with a path relative to the WordPress directory.
		$path = $woocommerce->get_download_file_path( self::get_test_file( 'pdf', 'http' ) );
		$this->assertIsString( $path );
		$this->assertFileExists( ABSPATH . $path );
		$this->assertEquals( $file_obj->get_cache_file(), ABSPATH . $path );

		// the method "redirect" is replaced for this path.
		$this->assertEquals( 'force', $woocommerce->use_force_method_for_cached_files( 'redirect', 1, $path ) );
		$this->assertEquals( 'xsendfile', $woocommerce->use_force_method_for_cached_files( 'xsendfile', 1, $path ) );
		$this->assertEquals( 'redirect', $woocommerce->use_force_method_for_cached_files( 'redirect', 1, 'https://example.com/other.pdf' ) );
	}

	/**
	 * Test the prevention of the delivery via proxy for protected downloads.
	 *
	 * @return void
	 */
	public function test_prevent_proxy_for_protected_downloads(): void {
		$woocommerce = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance();
		$file_obj    = $this->get_file_object();

		// mark the file as file of a product.
		$product_id = self::factory()->post->create( array( 'post_title' => 'My product' ) );
		add_post_meta( $file_obj->get_id(), 'efml_woocommerce_product', $product_id );
		$this->assertEquals( array( $product_id ), $woocommerce->get_products_of_file( $file_obj ) );

		// public files are still delivered.
		$this->assertFalse( $woocommerce->is_protected_download( $file_obj ) );
		$this->assertTrue( $woocommerce->prevent_proxy_for_protected_downloads( true, $file_obj ) );

		// private files are not delivered to visitors.
		$file_obj->set_private( true );
		$this->assertTrue( $woocommerce->is_protected_download( $file_obj ) );
		$this->assertFalse( $woocommerce->prevent_proxy_for_protected_downloads( true, $file_obj ) );

		// but to users, who could edit the file.
		$this->do_login();
		$this->assertTrue( $woocommerce->prevent_proxy_for_protected_downloads( true, $file_obj ) );

		// a prevented delivery stays prevented.
		$this->assertFalse( $woocommerce->prevent_proxy_for_protected_downloads( false, $file_obj ) );

		// the dialog shows the info with the product.
		$dialog = $woocommerce->show_download_info_in_dialog( array( 'texts' => array() ), $file_obj );
		$this->assertCount( 1, $dialog['texts'] );
		$this->assertStringContainsString( 'My product', $dialog['texts'][0] );

		// the markers of a deleted product are removed.
		$woocommerce->remove_download_markers( $product_id );
		$this->assertEquals( array( $product_id ), $woocommerce->get_products_of_file( $file_obj ) );
		wp_update_post(
			array(
				'ID'        => $product_id,
				'post_type' => 'product',
			)
		);
		$woocommerce->remove_download_markers( $product_id );
		$this->assertEquals( array(), $woocommerce->get_products_of_file( $file_obj ) );
		$this->assertFalse( $woocommerce->is_protected_download( $file_obj ) );
	}
}
