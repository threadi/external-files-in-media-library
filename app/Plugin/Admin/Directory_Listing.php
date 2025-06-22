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
use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Services\Rest;
use ExternalFilesInMediaLibrary\Services\Services;

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

		// misc.
		add_filter( 'get_edit_term_link', array( $this, 'prevent_edit_of_archive_terms' ), 10, 3 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'remove_edit_action_for_archive_terms' ) );
		add_action( 'registered_taxonomy_' . Taxonomy::get_instance()->get_name(), array( $this, 'show_taxonomy_in_media_menu' ) );
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 30 );

		// initialize the serverside tasks object for directory listing.
		$directory_listing_obj = Init::get_instance();
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
			__( 'Add external files', 'external-files-in-media-library' ),
			__( 'Add external files', 'external-files-in-media-library' ),
			'manage_options',
			$this->get_menu_slug(),
			array( $this, 'render_view_directory_page' ),
			2
		);
	}

	/**
	 * Register directory listing as object.
	 *
	 * @return void
	 */
	public function register_directory_listing(): void {
		// initialize the serverside tasks object for directory listing.
		$directory_listing_obj = Init::get_instance();
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
				<h1 class="wp-heading-inline"><?php echo esc_html__( 'Select the source of your external files', 'external-files-in-media-library' ); ?></h1>
				<ul id="efml-directory-listing-services">
					<?php
					foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
						// show disabled listing object.
						if ( $obj->is_disabled() ) {
							// show enabled listing object.
							?>
							<li class="efml-<?php echo esc_attr( sanitize_html_class( $obj->get_name() ) ); ?>"><span><?php echo esc_html( $obj->get_label() ); ?></span><br><?php echo wp_kses_post( $obj->get_description() ); ?></li>
							<?php
							continue;
						}

						// get the URL.
						$url = $obj->get_view_url();
						if ( empty( $url ) ) {
							$url = $this->get_view_directory_url( $obj );
						}

						// show enabled listing object.
						?>
							<li class="efml-<?php echo esc_attr( sanitize_html_class( $obj->get_name() ) ); ?>"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $obj->get_label() ); ?></a></li>
							<?php
					}

					?>
					<li class="efml-directory"><a href="<?php echo esc_url( $this->get_url() ); ?>"><?php echo esc_html__( 'Your directory archive', 'external-files-in-media-library' ); ?></a></li>
				</ul>
			</div>
			<?php
			return;
		}

		// get the method object by its name.
		$directory_listing_obj = Services::get_instance()->get_service_by_name( $method );

		// bail if no object could be loaded.
		if ( ! $directory_listing_obj ) {
			return;
		}

		// set nonce on listing object configuration.
		$config          = $directory_listing_obj->get_config();
		$config['nonce'] = wp_create_nonce( $this->get_nonce_name() );

		// get directory to connect to from request.
		$term_id = absint( filter_input( INPUT_GET, 'term', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( $term_id > 0 ) {
			// set term in config.
			$config['term'] = $term_id;

			// get the term name (= URL).
			$url = get_term_field( 'name', $term_id, Taxonomy::get_instance()->get_name() );

			// bail if URL is not a string.
			if ( ! is_string( $url ) ) {
				return;
			}

			// update the directory to load.
			$config['directory'] = $url;
		}

		// prepare config.
		$config_json = wp_json_encode( $config );

		// bail if config failed.
		if ( ! $config_json ) {
			return;
		}

		// output.
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $directory_listing_obj->get_title() ); ?></h1>
			<?php
				// bail if directory is not set on loading a concrete listing.
			if ( empty( $config['directory'] ) && isset( $config['term'] ) ) {
				?>
					<div class="eml_add_external_files_wrapper"><p><strong><?php echo esc_html__( 'Directory Archive could not be loaded.', 'external-files-in-media-library' ); ?></strong></p></div>
					<?php
			} else {
				?>
						<div id="easy-directory-listing-for-wordpress" data-type="<?php echo esc_attr( $method ); ?>" data-config="<?php echo esc_attr( $config_json ); ?>"></div>
					<?php
			}
			?>
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
	 * @return array<string,mixed>
	 */
	private function get_translations(): array {
		$translations = array(
			'is_loading'                    => __( 'Directory is loading', 'external-files-in-media-library' ),
			'loading_directory'             => __( 'one sub-directory do load', 'external-files-in-media-library' ),
			/* translators: %1$d will be replaced by a number. */
			'loading_directories'           => __( '%1$d sub-directories do load', 'external-files-in-media-library' ),
			'could_not_load'                => __( 'Directory could not be loaded.', 'external-files-in-media-library' ),
			'reload'                        => __( 'Reload', 'external-files-in-media-library' ),
			'import_directory'              => __( 'Import this directory', 'external-files-in-media-library' ),
			'actions'                       => __( 'Actions', 'external-files-in-media-library' ),
			'filename'                      => __( 'Filename', 'external-files-in-media-library' ),
			'filesize'                      => __( 'Size', 'external-files-in-media-library' ),
			'date'                          => __( 'Date', 'external-files-in-media-library' ),
			'config_missing'                => __( 'Configuration for Directory Listing missing!', 'external-files-in-media-library' ),
			'nonce_missing'                 => __( 'Secure token for Directory Listing missing!', 'external-files-in-media-library' ),
			'empty_directory'               => __( 'Loaded an empty directory. This could also mean that the files in the directory cannot be imported into WordPress, e.g. because they have a non-approved file type.', 'external-files-in-media-library' ),
			'error_title'                   => __( 'The following error occurred:', 'external-files-in-media-library' ),
			'errors_title'                  => __( 'The following errors occurred:', 'external-files-in-media-library' ),
			'serverside_error'              => __( 'Incorrect response received from the server, possibly a server-side error.', 'external-files-in-media-library' ),
			'directory_could_not_be_loaded' => __( 'Directory Listing object could not be read!', 'external-files-in-media-library' ),
			'directory_archive'             => array(
				'connect_now'     => __( 'Open now', 'external-files-in-media-library' ),
				'labels'          => array(
					'name'          => _x( 'Directory Archives', 'taxonomy general name', 'external-files-in-media-library' ),
					'singular_name' => _x( 'Directory Archive', 'taxonomy singular name', 'external-files-in-media-library' ),
					'search_items'  => __( 'Search Directory Archive', 'external-files-in-media-library' ),
					'edit_item'     => __( 'Edit Directory Archive', 'external-files-in-media-library' ),
					'update_item'   => __( 'Update Directory Archive', 'external-files-in-media-library' ),
					'menu_name'     => __( 'Directory Archives', 'external-files-in-media-library' ),
					'back_to_items' => __( 'Back to Directory Archives', 'external-files-in-media-library' ),
					/* translators: %1$s will be replaced by a URL. */
					'not_found'     => sprintf( __( 'No Directory Archives found. <a href="%1$s">Add them</a> from your external files sources.', 'external-files-in-media-library' ), $this->get_view_directory_url( false ) ),
				),
				'messages'        => array(
					'updated' => __( 'Directory Archive updated.', 'external-files-in-media-library' ),
					'deleted' => __( 'Directory Archive deleted.', 'external-files-in-media-library' ),
				),
				'type'            => __( 'Type', 'external-files-in-media-library' ),
				'connect'         => __( 'Connect', 'external-files-in-media-library' ),
				'type_not_loaded' => __( 'Type could not be loaded!', 'external-files-in-media-library' ),
				'login'           => __( 'Login', 'external-files-in-media-library' ),
				'password'        => __( 'Password', 'external-files-in-media-library' ),
				'api_key'         => __( 'API Key', 'external-files-in-media-library' ),
			),
			'form_file'                     => array(
				'title'       => __( 'Enter the path to a ZIP-file', 'external-files-in-media-library' ),
				/* translators: %1$s will be replaced by a file path. */
				'description' => sprintf( __( 'Enter the path to a ZIP file on your hosting. Must start with "file://%1$s" and end with ".zip".', 'external-files-in-media-library' ), ABSPATH ),
				'url'         => array(
					'label' => __( 'Path to the ZIP-file', 'external-files-in-media-library' ),
				),
				'button'      => array(
					'label' => __( 'Use this file', 'external-files-in-media-library' ),
				),
			),
			'form_api'                      => array(
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
			'form_login'                    => array(
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
			'services'                      => array(
				'local' => array(
					'label' => __( 'Local server directory', 'external-files-in-media-library' ),
					'title' => __( 'Choose file(s) from local server directory', 'external-files-in-media-library' ),
				),
			),
		);

		// get all registered directory listings and get their translation-additions.
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $obj ) {
			$translations = $obj->get_translations( $translations );
		}

		/**
		 * Filter the translations to use for directory listings.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $translations List of translations.
		 */
		return apply_filters( 'eml_directory_translations', $translations );
	}

	/**
	 * Prevent edit of archive terms.
	 *
	 * @param string $location The generated URL for edit the term.
	 * @param int    $term_id The term ID.
	 * @param string $taxonomy The used taxonomy.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function prevent_edit_of_archive_terms( string $location, int $term_id, string $taxonomy ): string {
		// bail if this is not our archive taxonomy.
		if ( Taxonomy::get_instance()->get_name() !== $taxonomy ) {
			return $location;
		}

		// return empty string to prevent the link usage.
		return '';
	}

	/**
	 * Remove the edit action for archive terms.
	 *
	 * @param array<string,string> $actions List of actions.
	 *
	 * @return array<string,string>
	 */
	public function remove_edit_action_for_archive_terms( array $actions ): array {
		// bail if edit option is not set.
		if ( ! isset( $actions['edit'] ) ) {
			return $actions;
		}

		// remove the edit entry.
		unset( $actions['edit'] );

		// bail if "inline hide-if-no-js" option is not set.
		if ( ! isset( $actions['inline hide-if-no-js'] ) ) {
			return $actions;
		}

		// remove the "inline hide-if-no-js" entry.
		unset( $actions['inline hide-if-no-js'] );

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * Show taxonomy for archives in media menu.
	 *
	 * @return void
	 */
	public function show_taxonomy_in_media_menu(): void {
		register_taxonomy_for_object_type( Taxonomy::get_instance()->get_name(), 'attachment' );
	}

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array<array<string,string>> $help_list List of help tabs.
	 *
	 * @return array<array<string,string>>
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Directory Archives', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'With directory archives, you can easily save your frequently used connections to external directories and reuse them at any time. Whats more, you can also use them to automatically synchronize the files with your media library.', 'external-files-in-media-library' ) . '</p>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-directory-archives',
			'title'   => __( 'Directory Archives', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Return the URL to the directory listings in backend.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return add_query_arg(
			array(
				'post_type' => 'attachment',
			),
			Directory_Listings::get_instance()->get_directory_archive_url()
		);
	}
}
