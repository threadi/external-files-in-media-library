<?php
/**
 * File to handle support for plugin "WooCommerce".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WC_Product;

/**
 * Object to handle support for this plugin.
 */
class WooCommerce extends ThirdParty_Base implements ThirdParty {
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
	 * Tasks to run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		$this->init_woocommerce();
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
		// get settings object.
		$settings_obj = Settings::get_instance();

		// add settings tab for WooCommerce.
		$woocommerce_settings_tab = $settings_obj->add_tab( 'woocommerce' );
		$woocommerce_settings_tab->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );

		// add section for WooCommerce settings.
		$woocommerce_settings_section = $woocommerce_settings_tab->add_section( 'eml_woocommerce_settings' );
		$woocommerce_settings_section->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );

		// add setting to enable the WooCommerce-support.
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
	 * @param array<string, int|string> $data The import data.
	 *
	 * @return array<string, int|string>
	 */
	public function import_product_image( array $data ): array {
		// bail if no main image is given.
		if ( empty( $data['raw_image_id'] ) ) {
			return $data;
		}

		// log event.
		Log::get_instance()->create( __( 'Trying to import main image of WooCommerce product as external image.', 'external-files-in-media-library' ), (string) $data['raw_image_id'], 'info', 2 );

		// get the files object.
		$external_files_obj = Files::get_instance();

		// add the image and bail if it was not successfully.
		if ( ! $external_files_obj->add_url( (string) $data['raw_image_id'] ) ) {
			return $data;
		}

		// get the external file object for the given main image.
		$external_file_obj = $external_files_obj->get_file_by_url( (string) $data['raw_image_id'] );

		// bail if file for the URL does not exist.
		if ( ! $external_file_obj ) {
			return $data;
		}

		// log event.
		Log::get_instance()->create( __( 'WooCommerce Product main image has been imported as external file.', 'external-files-in-media-library' ), (string) $data['raw_image_id'], 'info', 2 );

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
	 * @param WC_Product                $product The product object.
	 * @param array<string, int|string> $data The import data.
	 *
	 * @return void
	 */
	public function add_product_image( WC_Product $product, array $data ): void {
		// bail if no main image is given.
		if ( empty( $data['eml_file'] ) ) {
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'Save external image file as WooCommerce Product main image.', 'external-files-in-media-library' ), '', 'info', 2 );

		// set the given main image on product.
		wc_product_attach_featured_image( $data['eml_file'], $product );
	}

	/**
	 * Import product gallery images step 1.
	 *
	 * @param array<string, mixed> $data The import data.
	 *
	 * @return array<string, mixed>
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

		// log this event.
		Log::get_instance()->create( __( 'Trying to save WooCommerce product gallery images as external files.', 'external-files-in-media-library' ), '', 'info', 2 );

		// collect all images.
		$gallery_images = array();

		// loop through the list.
		foreach ( $data['raw_gallery_image_ids'] as $index => $url ) {
			/** @var string $url */
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

		// log this event.
		Log::get_instance()->create( __( 'WooCommerce product gallery images has been saved as external files.', 'external-files-in-media-library' ), '', 'info', 2 );

		// add custom column which we use later to assign the media files to the product.
		$data['eml_files'] = $gallery_images;

		// return resulting data.
		return $data;
	}

	/**
	 * Import product gallery images step 2.
	 *
	 * @param WC_Product                $product The product object.
	 * @param array<string, int|string> $data The import data.
	 *
	 * @return void
	 */
	public function add_product_gallery_images( WC_Product $product, array $data ): void {
		// bail if no gallery images are given.
		if ( empty( $data['eml_files'] ) ) {
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'WooCommerce product gallery images will be saved as external files on the product.', 'external-files-in-media-library' ), '', 'info', 2 );

		// add them to the product as gallery.
		$product->set_gallery_image_ids( $data['eml_files'] );
		$product->save();
	}
}
