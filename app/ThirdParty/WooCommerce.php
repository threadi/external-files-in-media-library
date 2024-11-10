<?php
/**
 * File to handle support for plugin "WooCommerce".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings\Settings;
use WC_Product;

/**
 * Object to handle support for this plugin.
 */
class WooCommerce {

	/**
	 * Instance of actual object.
	 *
	 * @var ?WooCommerce
	 */
	private static ?WooCommerce $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return WooCommerce
	 */
	public static function get_instance(): WooCommerce {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if WooCommerce is not active.
		if ( ! Helper::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		// add settings.
		add_action( 'init', array( $this, 'init_woocommerce' ) );

		// bail if support is not enabled.
		if ( 1 !== absint( get_option( 'eml_woocommerce' ) ) ) {
			return;
		}

		// add hooks.
		add_filter( 'woocommerce_product_import_process_item_data', array( $this, 'import_product_image' ) );
		add_filter( 'woocommerce_product_import_process_item_data', array( $this, 'import_product_gallery_images' ) );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'add_product_image' ), 10, 2 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'add_product_gallery_images' ), 10, 2 );
	}

	/**
	 * Initialize the WooCommerce-support.
	 *
	 * @return void
	 */
	public function init_woocommerce(): void {
		$settings_obj             = Settings::get_instance();
		$woocommerce_settings_tab = $settings_obj->add_tab( 'woocommerce' );
		$woocommerce_settings_tab->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );
		$woocommerce_settings_section = $woocommerce_settings_tab->add_section( 'eml_woocommerce_settings' );
		$woocommerce_settings_section->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );
		$woocommerce_settings_setting = $settings_obj->add_setting( 'eml_woocommerce' );
		$woocommerce_settings_setting->set_section( $woocommerce_settings_section );
		$woocommerce_settings_setting->set_type( 'integer' );
		$woocommerce_settings_setting->set_default( 1 );
		$woocommerce_settings_setting->set_field(
			array(
				'title'       => __( 'Enable support for WooCommerce', 'external-files-in-media-library' ),
				'description' => __( 'If enabled any external hosted images during CSV-import will be handled as external files by this plugin.', 'external-files-in-media-library' ),
				'type'        => 'Checkbox',
			)
		);
	}

	/**
	 * Import product image step 1.
	 *
	 * @param array $data The import data.
	 *
	 * @return array
	 */
	public function import_product_image( array $data ): array {
		// bail if no main image is given.
		if ( empty( $data['raw_image_id'] ) ) {
			return $data;
		}

		// add the image and bail if it was not successfully.
		if ( ! Files::get_instance()->add_url( $data['raw_image_id'] ) ) {
			return $data;
		}

		// get the external file object for the given main image.
		$external_file_obj = Files::get_instance()->get_file_by_url( $data['raw_image_id'] );

		// bail if file for the URL does not exist.
		if ( ! $external_file_obj ) {
			return $data;
		}

		// remove raw_image_id to prevent usage through WooCommerce.
		unset( $data['raw_image_id'] );

		// add custom column which we use later to assign the media file to the product.
		$data['eml_file'] = $external_file_obj->get_id();

		// return resulting data.
		return $data;
	}

	/**
	 * Import product image step 2.
	 *
	 * @param WC_Product $product The product object.
	 * @param array      $data The import data.
	 *
	 * @return void
	 */
	public function add_product_image( WC_Product $product, array $data ): void {
		// bail if no main image is given.
		if ( empty( $data['eml_file'] ) ) {
			return;
		}

		// set the given main image on product.
		wc_product_attach_featured_image( $data['eml_file'], $product );
	}

	/**
	 * Import product gallery images step 1.
	 *
	 * @param array $data The import data.
	 *
	 * @return array
	 */
	public function import_product_gallery_images( array $data ): array {
		// bail if no gallery images are given.
		if ( empty( $data['raw_gallery_image_ids'] ) ) {
			return $data;
		}

		// bail if given list is not an array.
		if ( ! is_array( $data['raw_gallery_image_ids'] ) ) {
			return $data;
		}

		// collect all images.
		$gallery_images = array();

		// loop through the list.
		foreach ( $data['raw_gallery_image_ids'] as $index => $url ) {
			// add the image and bail if it was not successfully.
			if ( ! Files::get_instance()->add_url( $url ) ) {
				continue;
			}

			// get the external file object for the given image.
			$external_file_obj = Files::get_instance()->get_file_by_url( $url );

			// bail if file for the URL does not exist.
			if ( ! $external_file_obj ) {
				continue;
			}

			// add the ID of the external file object to the list of images.
			$gallery_images[] = $external_file_obj->get_id();

			// remove this URL from main list.
			unset( $data['raw_gallery_image_ids'][ $index ] );
		}

		// bail if list im gallery images is empty.
		if ( empty( $gallery_images ) ) {
			return $data;
		}

		// add custom column which we use later to assign the media files to the product.
		$data['eml_files'] = $gallery_images;

		// return resulting data.
		return $data;
	}

	/**
	 * Import product gallery images step 2.
	 *
	 * @param WC_Product $product The product object.
	 * @param array      $data The import data.
	 *
	 * @return void
	 */
	public function add_product_gallery_images( WC_Product $product, array $data ): void {
		if ( empty( $data['eml_files'] ) ) {
			return;
		}

		$product->set_gallery_image_ids( $data['eml_files'] );
		$product->save();
	}
}
