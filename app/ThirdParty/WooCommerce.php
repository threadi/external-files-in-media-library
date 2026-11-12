<?php
/**
 * File to handle support for the plugin "WooCommerce".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Private_Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Proxy;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
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
	 * List of paths to cached files, which are used for downloads during this request.
	 *
	 * @var array<int,string>
	 */
	private array $cached_download_paths = array();

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

		// add hooks for downloadable products.
		add_action( 'woocommerce_after_product_object_save', array( $this, 'mark_download_files' ) );
		add_action( 'woocommerce_after_product_variation_object_save', array( $this, 'mark_download_files' ) );
		add_action( 'before_delete_post', array( $this, 'remove_download_markers' ) );
		add_filter( 'woocommerce_download_product_filepath', array( $this, 'get_download_file_path' ) );
		add_filter( 'woocommerce_file_download_method', array( $this, 'use_force_method_for_cached_files' ), 10, 3 );
		add_filter( 'efml_proxy_allow_delivery', array( $this, 'prevent_proxy_for_protected_downloads' ), 10, 2 );
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_download_info_in_dialog' ), 10, 2 );
	}

	/**
	 * Initialize the WooCommerce-support.
	 *
	 * @return void
	 */
	public function init_woocommerce(): void {
		// get settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add settings tab for WooCommerce.
		$woocommerce_settings_tab = $settings_page->add_tab( 'woocommerce', 120 );
		$woocommerce_settings_tab->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );

		// add a section for WooCommerce settings.
		$woocommerce_settings_section = $woocommerce_settings_tab->add_section( 'eml_woocommerce_settings', 10 );
		$woocommerce_settings_section->set_title( __( 'WooCommerce', 'external-files-in-media-library' ) );

		// add setting to enable the WooCommerce-support.
		$woocommerce_settings_setting = $settings_obj->add_setting( 'eml_woocommerce' );
		$woocommerce_settings_setting->set_section( $woocommerce_settings_section );
		$woocommerce_settings_setting->set_default( 1 );
		$woocommerce_settings_setting->set_field(
			array(
				'title'       => __( 'Enable support for WooCommerce', 'external-files-in-media-library' ),
				'description' => __( 'If enabled any external hosted images during CSV-import will be handled as external files by this plugin. External files from your media library, which you use as files of downloadable products, will be delivered by WooCommerce after its checks. If they are saved privately on their external source, they can only be downloaded this way.', 'external-files-in-media-library' ),
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
		Log::get_instance()->create( __( 'Trying to import the main image of WooCommerce product as external image.', 'external-files-in-media-library' ), (string) $data['raw_image_id'], 'info', 2 );

		// get the import object.
		$import = Import::get_instance();

		// set credentials, if given.
		$fields = array(
			'login'    => array(
				'value' => (string) get_option( 'eml_woocommerce_login' ),
			),
			'password' => array(
				'value' => (string) get_option( 'eml_woocommerce_password' ),
			),
		);
		$import->set_fields( $fields );

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

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
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

		// bail if string is empty (a value like "0" is a valid value).
		if ( '' === $value ) {
			return '';
		}

		// return encrypted string.
		return Crypt::get_instance()->decrypt( $value );
	}

	/**
	 * Return the external file for a given path of a downloadable file.
	 *
	 * The path could be the URL of the external file or the URL of the file via our proxy, which is used, if the
	 * file has been chosen from the media library.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $file_path The path of the downloadable file.
	 *
	 * @return File|false
	 */
	public function get_external_file_by_download_path( string $file_path ): File|false {
		// bail if path is empty.
		if ( empty( $file_path ) ) {
			return false;
		}

		// check the path as URL of an external file.
		$external_file_obj = Files::get_instance()->get_file_by_url( $file_path );
		if ( $external_file_obj instanceof File ) {
			return $external_file_obj;
		}

		// get the proxy slug.
		$slug = Proxy::get_instance()->get_slug();

		// check the path as URL of our proxy (for pretty and simple permalinks).
		foreach ( array( trailingslashit( get_home_url() ) . $slug . '/', trailingslashit( get_home_url() ) . '?' . $slug . '=' ) as $proxy_url ) {
			// bail if path does not start with this proxy URL.
			if ( ! str_starts_with( $file_path, $proxy_url ) ) {
				continue;
			}

			// get the title of the file.
			$title = rawurldecode( (string) substr( $file_path, strlen( $proxy_url ) ) );

			// get the external file by its title.
			$external_file_obj = Files::get_instance()->get_file_by_title( $title );
			if ( $external_file_obj instanceof File && $external_file_obj->is_valid() ) {
				return $external_file_obj;
			}
		}

		// return false if no external file could be found.
		return false;
	}

	/**
	 * Mark the external files, which are used as downloadable files of the given product.
	 *
	 * The marker is used to prevent the delivery of private files via our proxy, so they could only be downloaded
	 * via WooCommerce.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param mixed $product The product object.
	 *
	 * @return void
	 */
	public function mark_download_files( mixed $product ): void {
		// bail if this is not a product.
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// get the product ID.
		$product_id = $product->get_id();

		// bail if product has no ID.
		if ( 0 === $product_id ) {
			return;
		}

		// remove the markers of this product from all files.
		delete_metadata( 'post', 0, 'efml_woocommerce_product', (string) $product_id, true );

		// bail if product is not downloadable.
		if ( ! $product->is_downloadable() ) {
			return;
		}

		// check each downloadable file.
		foreach ( $product->get_downloads() as $download ) {
			// get the external file for this download.
			$external_file_obj = $this->get_external_file_by_download_path( (string) $download->get_file() );

			// bail if this is not an external file.
			if ( ! $external_file_obj instanceof File ) {
				continue;
			}

			// add the marker.
			add_post_meta( $external_file_obj->get_id(), 'efml_woocommerce_product', $product_id );
		}
	}

	/**
	 * Remove the markers of a product, which will be deleted.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $post_id The ID of the post.
	 *
	 * @return void
	 */
	public function remove_download_markers( int $post_id ): void {
		// bail if this is not a product.
		if ( ! in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		// remove the markers of this product from all files.
		delete_metadata( 'post', 0, 'efml_woocommerce_product', (string) $post_id, true );
	}

	/**
	 * Return the path for the download of an external file, after WooCommerce has checked the order.
	 *
	 * - Files, which are saved privately or could not be loaded by a browser, are delivered via a signed URL with
	 *   short validity, if their service supports it, otherwise from the cache of our proxy.
	 * - Other external files are delivered from their external URL.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param mixed $file_path The path of the downloadable file.
	 *
	 * @return mixed
	 */
	public function get_download_file_path( mixed $file_path ): mixed {
		// bail if path is not a string.
		if ( ! is_string( $file_path ) ) {
			return $file_path;
		}

		// get the external file.
		$external_file_obj = $this->get_external_file_by_download_path( $file_path );

		// bail if this is not an external file.
		if ( ! $external_file_obj instanceof File ) {
			return $file_path;
		}

		// use the local URL, if the file is saved locally.
		if ( $external_file_obj->is_locally_saved() ) {
			return (string) wp_get_attachment_url( $external_file_obj->get_id() );
		}

		// use the external URL, if the file does not require the proxy.
		if ( ! $external_file_obj->is_proxy_required() ) {
			return $external_file_obj->get_url( true );
		}

		// get the actual time.
		$now = time();

		/**
		 * Filter the validity of signed URLs for downloads of WooCommerce products in seconds.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $lifetime The validity in seconds.
		 * @param File $external_file_obj The external file object.
		 */
		$lifetime = absint( apply_filters( 'efml_woocommerce_download_lifetime', 5 * MINUTE_IN_SECONDS, $external_file_obj ) );

		// use a signed URL, if the service of the file supports it.
		$signed_url = Private_Files::get_instance()->get_signed_url( $external_file_obj, $now, $now + max( MINUTE_IN_SECONDS, $lifetime ) );
		if ( '' !== $signed_url ) {
			// log this event.
			Log::get_instance()->create( __( 'WooCommerce delivers this file via a signed URL.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

			// return the signed URL.
			return $signed_url;
		}

		// otherwise use the cache of our proxy.
		if ( ! $external_file_obj->is_cached() ) {
			$external_file_obj->add_to_proxy();
		}

		// get the path of the cached file.
		$cache_file = $external_file_obj->get_cache_file();

		// bail if cached file does not exist.
		if ( ! file_exists( $cache_file ) ) {
			// log this event.
			Log::get_instance()->create( __( 'File for the download in WooCommerce could not be loaded in the cache.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );

			// return the original path.
			return $file_path;
		}

		// use a path relative to the WordPress directory, so WooCommerce handles it as local file.
		if ( str_starts_with( $cache_file, ABSPATH ) ) {
			$cache_file = substr( $cache_file, strlen( ABSPATH ) );
		}

		// remember this path to use the matching download method.
		$this->cached_download_paths[] = $cache_file;

		// log this event.
		Log::get_instance()->create( __( 'WooCommerce delivers this file from the cache.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

		// return the path to the cached file.
		return $cache_file;
	}

	/**
	 * Use the method "force downloads" for cached files, as they could not be delivered via a redirect.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param mixed $method     The download method.
	 * @param mixed $product_id The product ID.
	 * @param mixed $file_path  The path of the file.
	 *
	 * @return mixed
	 */
	public function use_force_method_for_cached_files( mixed $method, mixed $product_id, mixed $file_path ): mixed {
		// bail if this is not a redirect or not one of our cached files.
		if ( 'redirect' !== $method || ! in_array( $file_path, $this->cached_download_paths, true ) ) {
			return $method;
		}

		// return the force method.
		return 'force';
	}

	/**
	 * Prevent the delivery of protected downloads via our proxy.
	 *
	 * Files, which are saved privately (or could not be loaded by a browser) and used as files of downloadable
	 * products, could only be downloaded via WooCommerce. Users, who are allowed to edit the file, could still use the
	 * proxy.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param bool $allowed           Whether the delivery is allowed.
	 * @param File $external_file_obj The external file object.
	 *
	 * @return bool
	 */
	public function prevent_proxy_for_protected_downloads( bool $allowed, File $external_file_obj ): bool {
		// bail if delivery is already prevented.
		if ( ! $allowed ) {
			return false;
		}

		// bail if this is not a protected download.
		if ( ! $this->is_protected_download( $external_file_obj ) ) {
			return true;
		}

		// return whether the actual user is allowed to edit this file.
		return current_user_can( 'edit_post', $external_file_obj->get_id() );
	}

	/**
	 * Return whether the given file is a protected download.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return bool
	 */
	public function is_protected_download( File $external_file_obj ): bool {
		return $external_file_obj->is_proxy_required() && ! empty( $this->get_products_of_file( $external_file_obj ) );
	}

	/**
	 * Return the IDs of the products, which use the given file as downloadable file.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return array<int,int>
	 */
	public function get_products_of_file( File $external_file_obj ): array {
		// get the markers.
		$product_ids = get_post_meta( $external_file_obj->get_id(), 'efml_woocommerce_product', false );

		// bail if no markers are set.
		if ( ! is_array( $product_ids ) ) {
			return array();
		}

		// return the unique list of IDs.
		return array_values( array_unique( array_filter( array_map( 'absint', $product_ids ) ) ) );
	}

	/**
	 * Show the info about the usage as downloadable file in the info dialog of an external file.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param array<string,mixed> $dialog            The dialog.
	 * @param File                $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_download_info_in_dialog( array $dialog, File $external_file_obj ): array {
		// get the products of this file.
		$product_ids = $this->get_products_of_file( $external_file_obj );

		// bail if file is not used by any product.
		if ( empty( $product_ids ) ) {
			return $dialog;
		}

		// create the list of links to the products.
		$links = array();
		foreach ( $product_ids as $product_id ) {
			// get the product (or its parent for variations) for the link.
			$parent_id = wp_get_post_parent_id( $product_id );
			$link      = get_edit_post_link( $parent_id > 0 ? $parent_id : $product_id );

			// bail if no link could be created.
			if ( ! is_string( $link ) ) {
				continue;
			}

			// add the link.
			$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a>';
		}

		// create the text depending on the protection.
		$text = __( 'The file is delivered only via WooCommerce.', 'external-files-in-media-library' );
		if ( ! $external_file_obj->is_proxy_required() ) {
			$text = __( 'The file is delivered via WooCommerce, but its external URL is public available.', 'external-files-in-media-library' );
		}

		// add the info.
		$dialog['texts'][] = '<p><strong>' . __( 'Product download', 'external-files-in-media-library' ) . ':</strong> ' . $text . ( ! empty( $links ) ? ' ' . implode( ', ', $links ) : '' ) . '</p>';

		// return the resulting dialog.
		return $dialog;
	}
}
