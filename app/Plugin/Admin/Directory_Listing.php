<?php
/**
 * File to handle each directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Initialize the directory listing support.
 */
class Directory_Listing {

	/**
	 * The nonce name.
	 *
	 * @var string
	 */
	private string $nonce_name = 'efml-directory-listing';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Directory_Listing
	 */
	private static ?Directory_Listing $instance = null;

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
	 * @return Directory_Listing
	 */
	public static function get_instance(): Directory_Listing {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// add the page in backend.
		add_action( 'admin_menu', array( $this, 'add_view_directory_page' ) );

		// initialize the serverside tasks object for directory listing.
		require_once Helper::get_plugin_dir() . 'vendor/threadi/easy-directory-listing-for-wordpress/lib/Init.php';
		$directory_listing_obj = \easyDirectoryListingForWordPress\Init::get_instance();
		$directory_listing_obj->set_path( Helper::get_plugin_dir() );
		$directory_listing_obj->set_url( Helper::get_plugin_url() );
		$directory_listing_obj->set_prefix( 'efml' );
		$directory_listing_obj->set_nonce_name( $this->get_nonce_name() );
		$directory_listing_obj->set_preview_state( 1 !== absint( get_option( 'eml_directory_listing_hide_preview', 0 ) ) );
		$directory_listing_obj->init();
	}

	/**
	 * Add hidden backend page for directory view.
	 *
	 * @return void
	 */
	public function add_view_directory_page(): void {
		add_submenu_page(
			'upload.php',
			__( 'Import from directories', 'external-files-in-media-library' ),
			__( 'Import from directories', 'external-files-in-media-library' ),
			'manage_options',
			'efml_local_directories',
			array( $this, 'render_view_directory_page' )
		);
	}

	/**
	 * Return the URL where the directory view will be displayed.
	 *
	 * @param Directory_Listing_Base $obj The object to use.
	 *
	 * @return string
	 */
	public function get_view_directory_url( Directory_Listing_Base $obj ): string {
		return add_query_arg(
			array(
				'page' => 'efml_local_directories',
				'method' => $obj->get_name()
			),
			get_admin_url() . 'upload.php'
		);
	}

	/**
	 * Initialize the output of the directory listing.
	 *
	 * @return void
	 */
	public function render_view_directory_page(): void {
		// get method from request.
		$method = filter_input( INPUT_GET, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// show hint if method is not set.
		if( is_null( $method ) ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Choose protocol', 'external-files-in-media-library' ); ?></h1>
				<ul>
					<?php
						foreach( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
							// bail if this is not a base object.
							if( ! $obj instanceof Directory_Listing_Base ) {
								continue;
							}

							?><li><a href="<?php echo esc_url( $this->get_view_directory_url( $obj ) ); ?>"><?php echo esc_html( $obj->get_label() ); ?></a></li><?php
						}
					?>
				</ul>
			</div>
			<?php
			return;
		}

		// get the method object by its name.
		$directory_listing_obj = false;
		foreach( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			// bail if this is not a base object.
			if( ! $obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// bail if name does not match.
			if( $method !== $obj->get_name() ) {
				continue;
			}

			$directory_listing_obj = $obj;
		}

		// bail if no object could be loaded.
		if( ! $directory_listing_obj ) {
			return;
		}

		// set nonce on listing object configuration.
		$config = $directory_listing_obj->get_config();
		$config['nonce'] = wp_create_nonce( $this->get_nonce_name() );

		// output.
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $directory_listing_obj->get_title() ); ?></h1>
			<div id="easy-directory-listing-for-wordpress" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Return the nonce name.
	 *
	 * @return string
	 */
	private function get_nonce_name(): string {
		return $this->nonce_name;
	}

	/**
	 * Return additional global actions from our plugin.
	 *
	 * @return array
	 */
	private function get_global_actions(): array {
		return array(
			array(
				'action' => 'alert("ok");',
				'label' => __( 'Import active directory', 'external-files-in-media-library' )
			),
		);
	}
}
