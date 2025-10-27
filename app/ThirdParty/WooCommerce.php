<?php
/**
 * File to handle support for plugin "WooCommerce".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WC_Product;
use WC_Product_CSV_Importer_Controller;

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
		add_filter( 'woocommerce_product_csv_importer_steps', array( $this, 'add_settings_before_csv_import' ) );

		// bail if support is not enabled.
		if ( 1 !== absint( get_option( 'eml_woocommerce' ) ) ) {
			return;
		}

		// add hooks for usage during CSV-import.
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

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add settings tab for WooCommerce.
		$woocommerce_settings_tab = $settings_page->add_tab( 'woocommerce', 120 );
		$woocommerce_settings_tab->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );

		// add section for WooCommerce settings.
		$woocommerce_settings_section = $woocommerce_settings_tab->add_section( 'eml_woocommerce_settings', 10 );
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

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_woocommerce_login' );
		$setting->set_section( $woocommerce_settings_section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->prevent_export( true );
		$setting->set_save_callback( array( $this, 'encrypt_value' ) );
		$setting->set_read_callback( array( $this, 'decrypt_value' ) );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_woocommerce_password' );
		$setting->set_section( $woocommerce_settings_section );
		$setting->set_type( 'string' );
		$setting->set_default( '' );
		$setting->prevent_export( true );
		$setting->set_save_callback( array( $this, 'encrypt_value' ) );
		$setting->set_read_callback( array( $this, 'decrypt_value' ) );
	}

	/**
	 * Add hint for import of external files during CSV-import through our plugin.
	 *
	 * @param array<string,array<string,mixed>> $steps List of steps during WooCommerce CSV-import.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_settings_before_csv_import( array $steps ): array {
		// set the key after we want to add our settings page.
		$after_key = 'upload';

		// get the index of this key.
		$index = array_search( $after_key, array_keys( $steps ), true );

		// add our entry after the given key and return the resulting list.
		return array_slice( $steps, 0, $index + 1 ) + array(
			'efml_external_files' => array(
				'name'    => __( 'Handling of external files', 'external-files-in-media-library' ),
				'view'    => array( $this, 'show_hint_before_csv_import' ),
				'handler' => array( $this, 'save_import_settings' ),
			),
		) + $steps;
	}

	/**
	 * Show the hint after the first step in CSV-importer of WooCommerce.
	 *
	 * @source WooCommerce\html-product-csv-import-form.php
	 *
	 * @return void
	 */
	public function show_hint_before_csv_import(): void {
		// get actual setting.
		$checked = 1 === absint( get_option( 'eml_woocommerce' ) ) ? ' checked' : '';

		// output.
		?>
		<form class="wc-progress-form-content woocommerce-importer" enctype="multipart/form-data" method="post">
			<header>
				<h2><?php esc_html_e( 'Handling of external files', 'external-files-in-media-library' ); ?></h2>
				<p>
				<?php
					/* translators: %1$s will be replaced by our plugin name. */
					echo wp_kses_post( sprintf( __( 'This option is provided by the plugin %1$s.', 'external-files-in-media-library' ), '<em>' . Helper::get_plugin_name() . '</em>' ) );
				?>
				</p>
			</header>
			<section>
				<table class="form-table woocommerce-importer-options">
					<tbody>
						<tr>
							<th><label for="woocommerce-importer-efml-import"><?php esc_html_e( 'External files', 'external-files-in-media-library' ); ?></label><br/></th>
							<td>
								<input type="hidden" name="efml_import" value="0" />
								<input type="checkbox" id="woocommerce-importer-efml-import" name="efml_import" value="1"<?php echo esc_html( $checked ); ?> />
								<label for="woocommerce-importer-efml-import"><?php esc_html_e( 'Save URLs for images in the CSV file as external files in the project.', 'external-files-in-media-library' ); ?></label>
							</td>
						</tr>
						<tr>
							<th><label for="woocommerce-importer-efml-import-credentials"><?php esc_html_e( 'Credentials', 'external-files-in-media-library' ); ?></label><br/></th>
							<td>
								<input type="hidden" name="efml_import_credentials" value="0" />
								<input type="checkbox" id="woocommerce-importer-efml-import-credentials" name="efml_import_credentials" value="1" />
								<label for="woocommerce-importer-efml-import-credentials"><?php esc_html_e( 'Enable this to provide credentials to download the external files from your CSV file. This could be the login for FTP access, for example. The URLs must be structured accordingly, including the complete path.', 'external-files-in-media-library' ); ?></label>
							</td>
						</tr>
						<tr class="woocommerce-importer-efml-import-credentials hidden">
							<th><label for="woocommerce-importer-efml-import-login"><?php esc_html_e( 'Login', 'external-files-in-media-library' ); ?></label><br/></th>
							<td>
								<input type="text" id="woocommerce-importer-efml-import-login" name="efml_import_login" value="" placeholder="<?php echo esc_attr__( 'The external login', 'external-files-in-media-library' ); ?>" />
							</td>
						</tr>
						<tr class="woocommerce-importer-efml-import-credentials hidden">
							<th><label for="woocommerce-importer-efml-import-password"><?php esc_html_e( 'Password', 'external-files-in-media-library' ); ?></label><br/></th>
							<td>
								<input type="password" id="woocommerce-importer-efml-import-password" name="efml_import_password" value="" placeholder="<?php echo esc_attr__( 'The external password', 'external-files-in-media-library' ); ?>" />
							</td>
						</tr>
					</tbody>
				</table>
			</section>
			<script type="text/javascript">
				jQuery(function() {
					jQuery( '#woocommerce-importer-efml-import-credentials' ).on( 'click', function() {
						let elements = jQuery( '.woocommerce-importer-efml-import-credentials' );
						if ( elements.is( '.hidden' ) ) {
							elements.removeClass( 'hidden' );
						} else {
							elements.addClass( 'hidden' );
						}
					} );
				});
			</script>
			<div class="wc-actions">
				<button type="submit" class="button button-primary button-next" value="<?php esc_attr_e( 'Continue', 'external-files-in-media-library' ); ?>" name="save_step"><?php esc_html_e( 'Continue', 'external-files-in-media-library' ); ?></button>
				<?php wp_nonce_field( 'woocommerce-csv-importer-efml' ); ?>
			</div>
		</form>
		<?php
	}

	/**
	 * Save import settings for CSV-import from WooCommerce dialog.
	 *
	 * @return void
	 */
	public function save_import_settings(): void {
		// check nonce.
		check_admin_referer( 'woocommerce-csv-importer-efml' );

		// save the import setting.
		update_option( 'eml_woocommerce', 1 === absint( filter_input( INPUT_POST, 'efml_import', FILTER_SANITIZE_NUMBER_INT ) ) );

		// get the credentials, if enabled.
		if ( 1 === absint( filter_input( INPUT_POST, 'efml_import_credentials', FILTER_SANITIZE_NUMBER_INT ) ) ) {
			// get the login.
			$login = filter_input( INPUT_POST, 'efml_import_login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			// get the password.
			$password = filter_input( INPUT_POST, 'efml_import_password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$password = html_entity_decode( $password );

			// bail if one of the credentials is missing.
			if ( empty( $login ) || empty( $password ) ) {
				// actually we cannot return an error message for the CSV importer of WooCommerce.
				// the site will just reload.
				return;
			}

			// save the login.
			update_option( 'eml_woocommerce_login', $login );

			// save the password.
			update_option( 'eml_woocommerce_password', $password );
		}

		// get the importer object.
		$importer = new WC_Product_CSV_Importer_Controller();

		// redirect user to next step.
		wp_safe_redirect( esc_url_raw( $importer->get_next_step_link( 'efml_external_files' ) ) );
		exit;
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

		// get the import object.
		$import = Import::get_instance();

		// set credentials, if given.
		$import->set_login( get_option( 'eml_woocommerce_login' ) );
		$import->set_password( get_option( 'eml_woocommerce_password' ) );

		// add the image and bail if it was not successfully.
		if ( ! $import->add_url( (string) $data['raw_image_id'] ) ) {
			return $data;
		}

		// get the external file object for the given main image.
		$external_file_obj = Files::get_instance()->get_file_by_url( (string) $data['raw_image_id'] );

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
			// add the image and bail if it was not successfully.
			if ( ! Import::get_instance()->add_url( $url ) ) {
				Log::get_instance()->create( __( 'Failed to import given URL during WooCommerce-import.', 'external-files-in-media-library' ), $url, 'error' );
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

	/**
	 * Encrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function encrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty.
		if ( empty( $value ) ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->encrypt( $value );
	}

	/**
	 * Decrypt a given value.
	 *
	 * @param string|null $value The value.
	 *
	 * @return string
	 */
	public function decrypt_value( ?string $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '';
		}

		// bail if string is empty.
		if ( empty( $value ) ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}
}
