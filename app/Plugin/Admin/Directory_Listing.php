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
	 * The menu slug.
	 *
	 * @var string
	 */
	private string $menu_slug = 'efml_local_directories';

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
		add_action( 'init', array( $this, 'register_directory_listing' ) );

		// initialize the serverside tasks object for directory listing.
		require_once Helper::get_plugin_dir() . 'vendor/threadi/easy-directory-listing-for-wordpress/lib/Init.php';
		$directory_listing_obj = \easyDirectoryListingForWordPress\Init::get_instance();
		$directory_listing_obj->set_path( Helper::get_plugin_dir() );
		$directory_listing_obj->set_url( Helper::get_plugin_url() );
		$directory_listing_obj->set_prefix( 'efml' );
		$directory_listing_obj->set_nonce_name( $this->get_nonce_name() );
		$directory_listing_obj->set_preview_state( 1 !== absint( get_option( 'eml_directory_listing_hide_preview', 0 ) ) );
		$directory_listing_obj->set_page_hook( 'media_page_' . $this->get_menu_slug() );
		$directory_listing_obj->set_menu_slug( $this->get_menu_slug() );
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
			$this->get_menu_slug(),
			array( $this, 'render_view_directory_page' )
		);
	}

	/**
	 * Register directory listing as object.
	 *
	 * @return void
	 */
	public function register_directory_listing(): void {
		// initialize the serverside tasks object for directory listing.
		require_once Helper::get_plugin_dir() . 'vendor/threadi/easy-directory-listing-for-wordpress/lib/Init.php';
		$directory_listing_obj = \easyDirectoryListingForWordPress\Init::get_instance();
		$directory_listing_obj->set_translations( $this->get_translations() );
	}

	/**
	 * Return the URL where the directory view will be displayed.
	 *
	 * @param Directory_Listing_Base|false $obj The object to use (or false for listing).
	 *
	 * @return string
	 */
	public function get_view_directory_url( Directory_Listing_Base|false $obj ): string {
		if ( ! $obj ) {
			return add_query_arg(
				array(
					'page' => $this->get_menu_slug(),
				),
				get_admin_url() . 'upload.php'
			);
		}
		return add_query_arg(
			array(
				'page'   => $this->get_menu_slug(),
				'method' => $obj->get_name(),
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
		if ( is_null( $method ) ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Choose protocol', 'external-files-in-media-library' ); ?></h1>
				<ul id="efml-directory-listing-services">
					<?php
					foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
						// bail if this is not a base object.
						if ( ! $obj instanceof Directory_Listing_Base ) {
							continue;
						}

						// show disabled listing object.
						if ( $obj->is_disabled() ) {
							// show enabled listing object.
							?>
							<li class="efml-<?php echo esc_attr( sanitize_html_class( $obj->get_name() ) ); ?>"><span><?php echo esc_html( $obj->get_label() ); ?></span><br><?php echo wp_kses_post( $obj->get_description() ); ?></li>
							<?php
							continue;
						}

						// show enabled listing object.
						?>
							<li class="efml-<?php echo esc_attr( sanitize_html_class( $obj->get_name() ) ); ?>"><a href="<?php echo esc_url( $this->get_view_directory_url( $obj ) ); ?>"><?php echo esc_html( $obj->get_label() ); ?></a></li>
							<?php
					}
					?>
					<li class="efml-directory"><a href="<?php echo esc_url( Directory_Listings::get_instance()->get_directory_archive_url() ); ?>"><?php echo esc_html__( 'Your directory archive', 'external-files-in-media-library' ); ?></a></li>
				</ul>
			</div>
			<?php
			return;
		}

		// get the method object by its name.
		$directory_listing_obj = false;
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			// bail if this is not a base object.
			if ( ! $obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// bail if name does not match.
			if ( $method !== $obj->get_name() ) {
				continue;
			}

			$directory_listing_obj = $obj;
		}

		// bail if no object could be loaded.
		if ( ! $directory_listing_obj ) {
			return;
		}

		// set nonce on listing object configuration.
		$config          = $directory_listing_obj->get_config();
		$config['nonce'] = wp_create_nonce( $this->get_nonce_name() );

		// get directory to connect to from request.
		$term = absint( filter_input( INPUT_GET, 'term', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( $term > 0 ) {
			$config['term'] = $term;
		}

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
	 * Return the menu slug.
	 *
	 * @return string
	 */
	private function get_menu_slug(): string {
		return $this->menu_slug;
	}

	/**
	 * Return the translations for each text.
	 *
	 * @return array
	 */
	private function get_translations(): array {
		return array(
			'is_loading'        => __( 'Please wait, list is loading.', 'external-files-in-media-library' ),
			'could_not_load'    => __( 'Directory could not be loaded.', 'external-files-in-media-library' ),
			'reload'            => __( 'Reload', 'external-files-in-media-library' ),
			'import_directory'  => __( 'Import this directory', 'external-files-in-media-library' ),
			'actions'           => __( 'Actions', 'external-files-in-media-library' ),
			'filename'          => __( 'Filename', 'external-files-in-media-library' ),
			'filesize'          => __( 'Size', 'external-files-in-media-library' ),
			'date'              => __( 'Date', 'external-files-in-media-library' ),
			'config_missing'    => __( 'Configuration for Directory Listing missing!', 'external-files-in-media-library' ),
			'nonce_missing'     => __( 'Secure token for Directory Listing missing!', 'external-files-in-media-library' ),
			'empty_directory'   => __( 'Loaded an empty directory. This could also mean that the files in the directory cannot be imported into WordPress, e.g. because they have a non-approved file type.', 'external-files-in-media-library' ),
			'error_title'       => __( 'The following error occurred:', 'external-files-in-media-library' ),
			'errors_title'      => __( 'The following errors occurred:', 'external-files-in-media-library' ),
			'serverside_error'  => __( 'Incorrect response received from the server, possibly a server-side error.', 'external-files-in-media-library' ),
			'directory_archive' => array(
				'connect_now'     => __( 'Open now', 'external-files-in-media-library' ),
				'labels'          => array(
					'name'          => _x( 'Directory Credentials', 'taxonomy general name', 'external-files-in-media-library' ),
					'singular_name' => _x( 'Directory Credential', 'taxonomy singular name', 'external-files-in-media-library' ),
					'search_items'  => __( 'Search Directory Credential', 'external-files-in-media-library' ),
					'edit_item'     => __( 'Edit Directory Credential', 'external-files-in-media-library' ),
					'update_item'   => __( 'Update Directory Credential', 'external-files-in-media-library' ),
					'menu_name'     => __( 'Directory Credentials', 'external-files-in-media-library' ),
					'back_to_items' => __( 'Back to Directory Credentials', 'external-files-in-media-library' ),
				),
				'messages'        => array(
					'updated' => __( 'Directory Credential updated.', 'external-files-in-media-library' ),
					'deleted' => __( 'Directory Credential deleted.', 'external-files-in-media-library' ),
				),
				'type'            => __( 'Type', 'external-files-in-media-library' ),
				'connect'         => __( 'Connect', 'external-files-in-media-library' ),
				'type_not_loaded' => __( 'Type could not be loaded!', 'external-files-in-media-library' ),
				'login'           => __( 'Login', 'external-files-in-media-library' ),
				'password'        => __( 'Password', 'external-files-in-media-library' ),
				'api_key'         => __( 'API Key', 'external-files-in-media-library' ),
			),
			'form_file'         => array(
				'title'       => __( 'Enter the path to a local ZIP-file', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced by a file path. */
				'description' => sprintf( __( 'Enter the path to a ZIP file on your hosting. Must start with "file://%1$s" and end with ".zip".', 'external-files-in-media-library' ), ABSPATH ),
				'url'         => array(
					'label' => __( 'Path to the ZIP-file', 'external-files-in-media-library' ),
				),
				'button'      => array(
					'label' => __( 'Use this file', 'external-files-in-media-library' ),
				),
			),
			'form_api'          => array(
				'title'            => __( 'Enter your credentials', 'external-files-in-media-library' ),
				'url'              => array(
					'label' => __( 'Channel-ID', 'external-files-in-media-library' ),
				),
				'key'              => array(
					'label' => __( 'API Key', 'external-files-in-media-library' ),
				),
				'save_credentials' => array(
					'label' => __( 'Save this credentials in directory archive', 'external-files-in-media-library' ),
				),
				'button'           => array(
					'label' => __( 'Show directory', 'external-files-in-media-library' ),
				),
			),
			'form_login'        => array(
				'title'            => __( 'Enter your credentials', 'external-files-in-media-library' ),
				'url'              => array(
					'label' => __( 'Server-IP or -name (starting with ftp:// or ftps://)', 'external-files-in-media-library' ),
				),
				'login'            => array(
					'label' => __( 'Login', 'external-files-in-media-library' ),
				),
				'password'         => array(
					'label' => __( 'Password', 'external-files-in-media-library' ),
				),
				'save_credentials' => array(
					'label' => __( 'Save this credentials in directory archive', 'external-files-in-media-library' ),
				),
				'button'           => array(
					'label' => __( 'Show directory', 'external-files-in-media-library' ),
				),
			),
			'services'          => array(
				'local' => array(
					'label' => __( 'Local server directory', 'external-files-in-media-library' ),
					'title' => __( 'Choose file from local server directory', 'external-files-in-media-library' ),
				),
			),
		);
	}
}
