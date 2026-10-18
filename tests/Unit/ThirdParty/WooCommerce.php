<?php
/**
 * Tests for class ExternalFilesInMediaLibrary\ThirdParty\WooCommerce.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ThirdParty;

use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test functions in class ExternalFilesInMediaLibrary\ThirdParty\WooCommerce.
 */
class WooCommerce extends externalFilesTests {
	/**
	 * The fields used during the import.
	 *
	 * @var array
	 */
	private array $used_fields = array();

	/**
	 * Reset the environment after each test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'efml_prevent_import', array( $this, 'capture_fields' ) );
		delete_option( 'eml_woocommerce_login' );
		delete_option( 'eml_woocommerce_password' );
		parent::tear_down();
	}

	/**
	 * Capture the used fields and prevent the import.
	 *
	 * @param bool   $prevent The value.
	 * @param string $url The URL.
	 * @param array  $fields The fields.
	 *
	 * @return bool
	 */
	public function capture_fields( bool $prevent, string $url, array $fields ): bool {
		$this->used_fields = $fields;
		return true;
	}

	/**
	 * Test if the saved credentials are used for the import of the product image.
	 *
	 * @return void
	 */
	public function test_import_product_image_uses_credentials(): void {
		update_option( 'eml_woocommerce_login', 'my-login' );
		update_option( 'eml_woocommerce_password', 'my-password' );
		add_filter( 'efml_prevent_import', array( $this, 'capture_fields' ), 10, 3 );

		$data   = array( 'raw_image_id' => 'https://example.com/product.jpg' );
		$result = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance()->import_product_image( $data );

		// the import is prevented by our filter, so the data must be unchanged.
		$this->assertSame( $data, $result );

		// the credentials must be used.
		$this->assertEquals( 'my-login', $this->used_fields['login']['value'] );
		$this->assertEquals( 'my-password', $this->used_fields['password']['value'] );
	}

	/**
	 * Test if the data is not changed without an image.
	 *
	 * @return void
	 */
	public function test_import_product_image_without_image(): void {
		$this->assertSame( array( 'name' => 'x' ), \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance()->import_product_image( array( 'name' => 'x' ) ) );
	}

	/**
	 * Test if values are encrypted and decrypted, also the value "0".
	 *
	 * @return void
	 */
	public function test_encrypt_and_decrypt_value(): void {
		$obj = \ExternalFilesInMediaLibrary\ThirdParty\WooCommerce::get_instance();
		foreach ( array( 'my-password', '0' ) as $value ) {
			$this->assertSame( $value, $obj->decrypt_value( $obj->encrypt_value( $value ) ) );
		}
		$this->assertSame( '', $obj->encrypt_value( '' ) );
		$this->assertSame( '', $obj->decrypt_value( null ) );
	}
}
