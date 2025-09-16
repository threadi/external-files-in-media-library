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
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Term;

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
		// bail if block support does not exist.
		if ( ! Helper::is_block_support_enabled() ) {
			return;
		}

		// add the page in backend.
		add_action( 'admin_menu', array( $this, 'add_view_directory_page' ) );
		add_action( 'init', array( $this, 'register_directory_listing' ) );

		// misc.
		add_filter( 'get_edit_term_link', array( $this, 'prevent_edit_of_archive_terms' ), 10, 3 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'remove_edit_action_for_archive_terms' ) );
		add_action( 'efml_directory_listing_added', array( $this, 'add_user_mark' ) );
		add_action( 'efml_directory_listing_added', array( $this, 'add_date' ) );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'add_option_to_set_name' ), 10, 2 );
		add_action( 'registered_taxonomy_' . Taxonomy::get_instance()->get_name(), array( $this, 'show_taxonomy_in_media_menu' ) );
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 30 );

		// use AJAX hooks.
		add_action( 'wp_ajax_efml_add_archive', array( $this, 'add_archive_via_ajax' ) );
		add_action( 'wp_ajax_eml_change_term_name', array( $this, 'save_new_listing_name_via_ajax' ) );

		// initialize the serverside tasks object for directory listing.
		$directory_listing_obj = Init::get_instance();
		$directory_listing_obj->set_path( Helper::get_plugin_dir() );
		$directory_listing_obj->set_url( Helper::get_plugin_url() );
		$directory_listing_obj->set_prefix( 'efml' );
		$directory_listing_obj->set_nonce_name( $this->get_nonce_name() );
		$directory_listing_obj->set_preview_state( 1 !== absint( get_option( 'eml_directory_listing_hide_preview', 0 ) ) );
		$directory_listing_obj->set_page_hook( 'media_page_' . $this->get_menu_slug() );
		$directory_listing_obj->set_menu_slug( $this->get_menu_slug() );
		$directory_listing_obj->set_capability( EFML_CAP_NAME );
		$directory_listing_obj->init();
	}

	/**
	 * Add backend page for directory view.
	 *
	 * @return void
	 */
	public function add_view_directory_page(): void {
		add_submenu_page(
			'upload.php',
			__( 'Add External Files', 'external-files-in-media-library' ),
			__( 'Add External Files', 'external-files-in-media-library' ),
			EFML_CAP_NAME,
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
						// hide listing object if user has no capability for it.
						if ( ! current_user_can( 'efml_cap_' . $obj->get_name() ) ) {
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
					<li class="efml-directory"><a href="<?php echo esc_url( $this->get_url() ); ?>"><?php echo esc_html__( 'Your external sources', 'external-files-in-media-library' ); ?></a></li>
					<li class="efml-hint">
						<?php
							/* translators: %1$s will be replaced by a URL. */
							echo wp_kses_post( sprintf( __( 'Missing an external source like FlickR, Instagram, Google Photo ... ? Ask in our <a href="%1$s" target="_blank">supportforum</a>.', 'external-files-in-media-library' ), Helper::get_plugin_support_url() ) );
						if ( current_user_can( 'manage_options' ) ) {
							?>
									<br><br><a href="<?php echo esc_url( Settings::get_instance()->get_url() ); ?>" title="<?php echo esc_attr__( 'Go to settings', 'external-files-in-media-library' ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
								<?php
						}
						?>
					</li>
				</ul>
			</div>
			<?php
			return;
		}

		// get the method object by its name.
		$directory_listing_obj = Services::get_instance()->get_service_by_name( $method );

		// bail if no object could be loaded.
		if ( ! $directory_listing_obj ) {
			$this->show_error( '<p>' . __( 'Requested service for external files could not be found!', 'external-files-in-media-library' ) . '</p>' );
			return;
		}

		// bail if user has no capability for it.
		if ( ! current_user_can( 'efml_cap_' . $directory_listing_obj->get_name() ) ) {
			$this->show_error( '<p>' . __( 'Missing permission to use this service for external files! Contact your administrator for clarification.', 'external-files-in-media-library' ) . '</p>' );
			return;
		}

		// set nonce on listing object configuration.
		$config          = $directory_listing_obj->get_config();
		$config['nonce'] = wp_create_nonce( $this->get_nonce_name() );

		// get directory to connect to from request.
		$term_id = absint( filter_input( INPUT_GET, 'term', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		if ( $term_id > 0 ) {
			// get the user_id which saved this entry.
			$user_id = absint( get_term_meta( $term_id, 'user_id', true ) );

			// bail if ID is set, does not match the actual user and this is not an administrator and setting is disabled.
			if ( $user_id > 0 && get_current_user_id() !== $user_id && ! Helper::has_current_user_role( 'administrator' ) && 1 !== absint( get_option( 'eml_show_all_external_sources' ) ) ) {
				$this->show_error( '<p>' . __( 'Access not allowed. This entry has been saved by another user.', 'external-files-in-media-library' ) . '</p>' );
				return;
			}

			// set term in config.
			$config['term'] = $term_id;

			// get the path to load.
			$url = get_term_meta( $term_id, 'path', true );

			// bail if URL is not a string.
			if ( ! is_string( $url ) ) {
				$this->show_error( '<p>' . __( 'URL of saved external source could not be loaded.', 'external-files-in-media-library' ) . '</p>' );
				return;
			}

			// update the directory to load.
			$config['directory'] = $url;
		}

		// output.
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $directory_listing_obj->get_title() ); ?></h1>
			<?php
			// load nothing if directory is not set on loading a concrete listing.
			if ( empty( $config['directory'] ) && isset( $config['term'] ) ) {
				?>
					<div class="eml_add_external_files_wrapper"><p><strong><?php echo esc_html__( 'External source could not be loaded.', 'external-files-in-media-library' ); ?></strong></p></div>
					<?php
			} else {
				?>
					<div id="easy-directory-listing-for-wordpress" data-type="<?php echo esc_attr( $method ); ?>" data-config="<?php echo esc_attr( Helper::get_json( $config ) ); ?>"></div>
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
			'cancel'                        => __( 'Cancel', 'external-files-in-media-library' ),
			'please_wait'                   => __( 'Cancel loading, please wait', 'external-files-in-media-library' ),
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
					'name'          => _x( 'Your external sources', 'taxonomy general name', 'external-files-in-media-library' ),
					'singular_name' => _x( 'Your external source', 'taxonomy singular name', 'external-files-in-media-library' ),
					'search_items'  => __( 'Search your external sources', 'external-files-in-media-library' ),
					'edit_item'     => __( 'Edit your external source', 'external-files-in-media-library' ),
					'update_item'   => __( 'Update your external source', 'external-files-in-media-library' ),
					'menu_name'     => __( 'Your External Sources', 'external-files-in-media-library' ),
					'back_to_items' => __( 'Back to your external sources', 'external-files-in-media-library' ),
					/* translators: %1$s will be replaced by a URL. */
					'not_found'     => sprintf( __( 'No external sources found. Add them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $this->get_view_directory_url( false ) ),
				),
				'messages'        => array(
					'updated' => __( 'External source updated.', 'external-files-in-media-library' ),
					'deleted' => __( 'External source deleted.', 'external-files-in-media-library' ),
				),
				'type'            => __( 'Type', 'external-files-in-media-library' ),
				'connect'         => __( 'Connect', 'external-files-in-media-library' ),
				'type_not_loaded' => __( 'Type could not be loaded!', 'external-files-in-media-library' ),
				'login'           => __( 'Login', 'external-files-in-media-library' ),
				'password'        => __( 'Password', 'external-files-in-media-library' ),
				'api_key'         => __( 'API Key', 'external-files-in-media-library' ),
			),
			'form_file'                     => array(
				'title'       => __( 'Enter the URL or path to a ZIP-file', 'external-files-in-media-library' ),
				'description' => __( 'The URL or path must end with ".zip".', 'external-files-in-media-library' ),
				'url'         => array(
					'label' => __( 'URL or path to the ZIP-file', 'external-files-in-media-library' ),
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
					'label' => __( 'Save this credentials as external source', 'external-files-in-media-library' ),
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
					'label' => __( 'Save this credentials as external source', 'external-files-in-media-library' ),
				),
				'button'           => array(
					'label' => __( 'Show directory', 'external-files-in-media-library' ),
				),
			),
			'aws_s3_api'                    => array(
				'title'            => __( 'Enter your credentials', 'external-files-in-media-library' ),
				'description'      => __( 'Use the login details for your IAM user who has permissions for the bucket you want to use. See:', 'external-files-in-media-library' ) . ' <a href="https://docs.aws.amazon.com/AmazonS3/latest/userguide/security-iam.html" target="_blank">https://docs.aws.amazon.com/AmazonS3/latest/userguide/security-iam.html</a>',
				'access_key'       => array(
					'label' => __( 'Access Key', 'external-files-in-media-library' ),
				),
				'secret_key'       => array(
					'label' => __( 'Secret Key', 'external-files-in-media-library' ),
				),
				'bucket'           => array(
					'label' => __( 'Bucket', 'external-files-in-media-library' ),
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
		$content  = '<h1>' . __( 'External sources', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'With your external sources, you can easily save your frequently used connections to external directories and reuse them at any time. Whats more, you can also use them to automatically synchronize the files with your media library.', 'external-files-in-media-library' ) . '</p>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-directory-archives',
			'title'   => __( 'External sources', 'external-files-in-media-library' ),
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

	/**
	 * Add external source via AJAX-request.
	 *
	 * @return void
	 */
	public function add_archive_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-add-archive-nonce', 'nonce' );

		// get the type.
		$type = filter_input( INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get the URL.
		$url = filter_input( INPUT_POST, 'url', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if type or URL is not given.
		if ( is_null( $type ) || is_null( $url ) ) {
			wp_send_json(
				array(
					'detail' =>
						array(
							'title'   => __( 'Error', 'external-files-in-media-library' ),
							'texts'   => array( '<p>' . __( 'The directory could not be saved as external source.', 'external-files-in-media-library' ) . '</p>' ),
							'buttons' => array(
								array(
									'action'  => 'closeDialog();',
									'variant' => 'primary',
									'text'    => __( 'OK', 'external-files-in-media-library' ),
								),
							),
						),
				)
			);
		}

		// get the login.
		$login = filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $login ) ) {
			$login = '';
		}

		// get the password.
		$password = filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $password ) ) {
			$password = '';
		}

		// get the API key.
		$api_key = filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $api_key ) ) {
			$api_key = '';
		}

		// get the credentials from the used term.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );
		if ( $term_id > 0 ) {
			// get the term.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );

			if ( ! empty( $term_data ) ) {
				$login    = $term_data['login'];
				$password = $term_data['password'];
				$api_key  = $term_data['api_key'];
			}
		}

		// get service by type.
		$service_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// bail if type is unknown.
		if ( ! $service_obj instanceof Directory_Listing_Base ) {
			wp_send_json(
				array(
					'detail' =>
						array(
							'title'   => __( 'Error', 'external-files-in-media-library' ),
							'texts'   => array( '<p>' . __( 'The type of source for this directory is unknown.', 'external-files-in-media-library' ) . '</p>' ),
							'buttons' => array(
								array(
									'action'  => 'closeDialog();',
									'variant' => 'primary',
									'text'    => __( 'OK', 'external-files-in-media-library' ),
								),
							),
						),
				)
			);
		}

		// check requirements for the service.
		if ( $service_obj->is_login_required() ) {
			// if no credentials are given, show error.
			if ( empty( $login ) || empty( $password ) ) {
				wp_send_json(
					array(
						'detail' =>
							array(
								'title'   => __( 'Error', 'external-files-in-media-library' ),
								'texts'   => array( '<p>' . __( 'Credentials are missing for the requested service.', 'external-files-in-media-library' ) . '</p>' ),
								'buttons' => array(
									array(
										'action'  => 'closeDialog();',
										'variant' => 'primary',
										'text'    => __( 'OK', 'external-files-in-media-library' ),
									),
								),
							),
					)
				);
			}
		}

		// add the archive.
		Taxonomy::get_instance()->add( $type, $url, $login, $password, $api_key );

		// return OK.
		wp_send_json(
			array(
				'detail' =>
					array(
						'title'   => __( 'External source saved', 'external-files-in-media-library' ),
						'texts'   => array(
							'<p><strong>' . __( 'The directory has been saved as your external source.', 'external-files-in-media-library' ) . '</strong></p>',
							/* translators: %1$s will be replaced by a URL. */
							'<p>' . sprintf( __( 'You can find and use it <a href="%1$s">in your external sources</a>.', 'external-files-in-media-library' ), self::get_instance()->get_url() ) . '</p>',
						),
						'buttons' => array(
							array(
								'action'  => 'closeDialog();',
								'variant' => 'primary',
								'text'    => __( 'OK', 'external-files-in-media-library' ),
							),
						),
					),
			)
		);
	}

	/**
	 * Show error.
	 *
	 * @param string $error The error text.
	 *
	 * @return void
	 */
	private function show_error( string $error ): void {
		// output.
		?>
			<div class="wrap">
				<h1 class="wp-heading-inline"><?php echo esc_html__( 'Error loading external source', 'external-files-in-media-library' ); ?></h1>
				<?php echo wp_kses_post( $error ); ?>
			</div>
		<?php
	}

	/**
	 * Add user mark if new listing entry is added.
	 *
	 * @param int $term_id The term ID added.
	 *
	 * @return void
	 */
	public function add_user_mark( int $term_id ): void {
		add_term_meta( $term_id, 'user_id', get_current_user_id() );
	}

	/**
	 * Add user mark if new listing entry is added.
	 *
	 * @param int $term_id The term ID added.
	 *
	 * @return void
	 */
	public function add_date( int $term_id ): void {
		add_term_meta( $term_id, 'date', time() );
	}

	/**
	 * Add option to set name for this entry via dialog.
	 *
	 * @param array<string,string> $new_actions List of actions.
	 * @param WP_Term              $term The used term.
	 *
	 * @return array<string,string>
	 */
	public function add_option_to_set_name( array $new_actions, WP_Term $term ): array {
		// create the form.
		$form  = '<div><label for="name">' . __( 'Name:', 'external-files-in-media-library' ) . '</label><input type="text" name="name" id="name" value="' . esc_attr( $term->name ) . '"></div>';
		$form .= '<input type="hidden" name="term_id" value="' . $term->term_id . '">';

		// create dialog.
		$dialog = array(
			'className' => 'efml-term-change-name',
			'title'     => __( 'Change name', 'external-files-in-media-library' ),
			'texts'     => array(
				$form,
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_change_term_name();',
					'variant' => 'primary',
					'text'    => __( 'Save', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add the action.
		$new_actions['rename'] = '<a href="#" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Rename', 'external-files-in-media-library' ) . '</a>';

		// return list of actions.
		return $new_actions;
	}

	/**
	 * Save the new listing name via AJAX.
	 *
	 * @return void
	 */
	public function save_new_listing_name_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-change-term-name', 'nonce' );

		// create dialog for response.
		$dialog = array(
			'title'   => __( 'Error', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'The new name could not be saved!', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons' => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get the term ID.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term ID is given.
		if ( 0 === $term_id ) {
			$dialog['texts'][] = __( 'Term is missing!', 'external-files-in-media-library' );
			wp_send_json( array( 'detail' => $dialog ) );
			exit; // @phpstan-ignore deadCode.unreachable
		}

		// get the new name.
		$name = filter_input( INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no name is given.
		if ( empty( $name ) ) {
			$dialog['texts'][] = __( 'New name is missing!', 'external-files-in-media-library' );
			wp_send_json( array( 'detail' => $dialog ) );
			exit; // @phpstan-ignore deadCode.unreachable
		}

		// save the new name.
		wp_update_term(
			$term_id,
			Taxonomy::get_instance()->get_name(),
			array(
				'name' => $name,
			)
		);

		// return OK-message.
		$dialog['title']                = __( 'Name has been changed', 'external-files-in-media-library' );
		$dialog['texts']                = array(
			'<p><strong>' . __( 'The new name has been saved!', 'external-files-in-media-library' ) . '</strong></p>',
		);
		$dialog['buttons'][0]['action'] = 'location.reload();';
		wp_send_json( array( 'detail' => $dialog ) );
	}
}
