<?php
/**
 * This file contains an object which handles the forms to add external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\No_Credentials;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\No_Urls;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Screen;

/**
 * Initialize the backend forms for external files.
 */
class Forms {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Forms
	 */
	private static ?Forms $instance = null;

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
	 * @return Forms
	 */
	public static function get_instance(): Forms {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the backend forms for external files.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the dialog.
		ImportDialog::get_instance()->init();

		// add forms.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ) );
		add_action( 'post-plupload-upload-ui', array( $this, 'add_multi_form' ), 10, 0 );
		add_action( 'post-html-upload-ui', array( $this, 'add_single_form' ), 10, 0 );

		// add AJAX endpoints which processes the import from @ImportDialog.
		add_action( 'wp_ajax_eml_add_external_urls', array( $this, 'add_urls_by_ajax' ), 10, 0 );
		add_action( 'wp_ajax_eml_get_external_urls_import_info', array( $this, 'get_external_urls_import_info' ), 10, 0 );

		// add action to process the import from static form in @ImportDialog.
		add_action( 'admin_action_eml_add_external_urls', array( $this, 'add_urls_by_request' ) );

		// use our own actions.
		add_action( 'eml_http_directory_import_start', array( $this, 'set_http_import_title_start' ) );
		add_action( 'eml_ftp_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_http_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_sftp_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_s3_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_file_import_before_save', array( $this, 'set_import_file_save' ) );
		add_action( 'eml_ftp_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_http_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_sftp_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_before_file_list', array( $this, 'set_import_max' ), 10, 2 );
		add_filter( 'eml_import_urls', array( $this, 'filter_urls' ) );
		add_action( 'eml_after_file_save', array( $this, 'add_imported_url_to_list' ), 10, 3 );

		// misc.
		add_filter( 'admin_body_class', array( $this, 'add_sound' ) );
	}

	/**
	 * Add CSS- and JS-files for backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_styles_and_js_admin( string $hook ): void {
		// bail if page is used where we do not use it.
		if ( ! in_array( $hook, array( 'upload.php', 'media-new.php', 'edit-tags.php', 'post.php', 'settings_page_eml_settings', 'options-general.php', 'media_page_efml_local_directories', 'term.php', 'profile.php' ), true ) ) {
			// backend-JS.
			wp_enqueue_script(
				'eml-admin',
				plugins_url( '/admin/public.js', EFML_PLUGIN ),
				array( 'jquery' ),
				(string) filemtime( Helper::get_plugin_dir() . '/admin/public.js' ),
				true
			);
			// admin-specific styles.
			wp_enqueue_style(
				'eml-public-admin',
				plugins_url( '/admin/public.css', EFML_PLUGIN ),
				array(),
				(string) filemtime( Helper::get_plugin_dir() . '/admin/public.css' ),
			);
			// add php-vars to our js-script.
			wp_localize_script(
				'eml-admin',
				'efmlJsVars',
				array(
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'dismiss_nonce' => wp_create_nonce( 'eml-dismiss-nonce' ),
				)
			);
			return;
		}

		// backend-JS.
		wp_enqueue_script(
			'eml-admin',
			plugins_url( '/admin/js.js', EFML_PLUGIN ),
			array( 'jquery' ),
			(string) filemtime( Helper::get_plugin_dir() . '/admin/js.js' ),
			true
		);

		// admin-specific styles.
		wp_enqueue_style(
			'eml-admin',
			plugins_url( '/admin/style.css', EFML_PLUGIN ),
			array(),
			(string) filemtime( Helper::get_plugin_dir() . '/admin/style.css' ),
		);

		$info_timeout = 200;
		/**
		 * Filter the timeout for the AJAX-info-request.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param int $info_timeout The timeout in ms (default 200ms).
		 */
		$info_timeout = apply_filters( 'eml_import_info_timeout', $info_timeout );

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-admin',
			'efmlJsVars',
			array(
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'urls_nonce'                    => wp_create_nonce( 'eml-urls-upload-nonce' ),
				'dismiss_nonce'                 => wp_create_nonce( 'eml-dismiss-nonce' ),
				'get_import_info_nonce'         => wp_create_nonce( 'eml-url-upload-info-nonce' ),
				'switch_hosting_nonce'          => wp_create_nonce( 'eml-switch-hosting-nonce' ),
				'reset_proxy_nonce'             => wp_create_nonce( 'eml-reset-proxy-nonce' ),
				'add_archive_nonce'             => wp_create_nonce( 'eml-add-archive-nonce' ),
				'import_dialog_nonce'           => wp_create_nonce( 'efml-import-dialog-nonce' ),
				'change_term_name_nonce'        => wp_create_nonce( 'efml-change-term-name' ),
				'review_url'                    => Helper::get_plugin_review_url(),
				'directory_listing_url'         => Directory_Listing::get_instance()->get_view_directory_url( false ),
				'title_add_file'                => __( 'Add external file', 'external-files-in-media-library' ),
				'title_rate_us'                 => __( 'Rate this plugin', 'external-files-in-media-library' ),
				'title_import_progress'         => __( 'Import of URLs running', 'external-files-in-media-library' ),
				'title_import_ended'            => __( 'Import has been run', 'external-files-in-media-library' ),
				'text_import_ended'             => __( 'The specified URLs have been processed.', 'external-files-in-media-library' ),
				'lbl_ok'                        => __( 'OK', 'external-files-in-media-library' ),
				'lbl_cancel'                    => __( 'Cancel', 'external-files-in-media-library' ),
				'lbl_close'                     => __( 'Close', 'external-files-in-media-library' ),
				'text_urls_imported'            => __( 'The following URLs were successfully imported:', 'external-files-in-media-library' ),
				'text_urls_errors'              => __( 'The following errors occurred:', 'external-files-in-media-library' ),
				'title_no_urls'                 => __( 'No URLs given', 'external-files-in-media-library' ),
				'text_no_urls'                  => __( 'Please enter one or more URLs to import in the field.', 'external-files-in-media-library' ),
				'title_hosting_changed'         => __( 'Hosting changed', 'external-files-in-media-library' ),
				'text_hosting_has_been_changed' => __( 'The hosting of this file has been changed.', 'external-files-in-media-library' ),
				'txt_error'                     => '<strong>' . __( 'The following error occurred:', 'external-files-in-media-library' ) . '</strong>',
				'title_error'                   => __( 'An error occurred', 'external-files-in-media-library' ),
				'info_timeout'                  => $info_timeout,
				'title_hosting_change_wait'     => __( 'Please wait', 'external-files-in-media-library' ),
				'text_hosting_change_wait'      => __( 'The hosting of the file will be changed.', 'external-files-in-media-library' ),
				'title_loading'                 => __( 'Loading ..', 'external-files-in-media-library' ),
				'text_loading'                  => __( 'Please wait a moment ..', 'external-files-in-media-library' ),
				/* source of file: https://pixabay.com */
				'success_sound_file'            => Helper::get_plugin_url() . 'gfx/success.mp3',
			)
		);
	}

	/**
	 * Output form to enter multiple URLs for external files.
	 *
	 * @return void
	 */
	public function add_multi_form(): void {
		// bail if user has not the capability for it.
		if ( ! current_user_can( EFML_CAP_NAME ) || ! current_user_can( 'efml_cap_import' ) ) {
			return;
		}

		// bail if "get_current_screen()" is not available (like for Divi).
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// get actual screen.
		$current_screen = get_current_screen();

		// bail if screen could not be loaded.
		if ( ! $current_screen instanceof WP_Screen ) {
			return;
		}

		// on "add"-screen show our custom form-field to add external files.
		if ( 'add' === $current_screen->action ) {
			?>
			<div class="eml_add_external_files_wrapper">
				<?php
					// check if block support is available.
				if ( Helper::is_block_support_enabled() ) {
					?>
							<a href="#" class="button button-secondary efml-import-dialog"><?php echo esc_html__( 'Add external files', 'external-files-in-media-library' ); ?></a>
						<?php
				} else {
					// show simple form without dialog.
					ImportDialog::get_instance()->get_form();
				}
				?>
			</div>
			<?php
		} else {
			$url = 'media-new.php';
			?>
			<div class="eml_add_external_files_wrapper">
				<p>
					<?php
					/* translators: %1$s will be replaced with the URL for add new media */
					echo wp_kses_post( sprintf( __( 'Add external files via their URL <a href="%1$s" target="_blank">here (opens new window)</a>.', 'external-files-in-media-library' ), esc_url( $url ) ) );
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Output form to enter single URL of an external file.
	 *
	 * @return void
	 */
	public function add_single_form(): void {
		// bail if user has not the capability for it.
		if ( ! current_user_can( EFML_CAP_NAME ) || ! current_user_can( 'efml_cap_import' ) ) {
			return;
		}

		?>
		<div class="eml_add_external_files_wrapper">
			<a href="#" class="button button-secondary efml-import-dialog"><?php echo esc_html__( 'Add external file', 'external-files-in-media-library' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Process AJAX-request to insert single or multiple URLs in the media library.
	 *
	 * @return       void
	 */
	public function add_urls_by_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-urls-upload-nonce', 'nonce' );

		// check capability.
		if ( false === current_user_can( EFML_CAP_NAME ) ) {
			wp_send_json( array() );
		}

		// get the current user ID.
		$user_id = get_current_user_id();

		// get log object.
		$log = Log::get_instance();

		// log this event.
		$log->create( __( 'AJAX-request to add external URLs has been called.', 'external-files-in-media-library' ), '', 'info', 2 );

		// mark import as running.
		update_option( 'eml_import_running_' . $user_id, time() );

		// reset counter.
		update_option( 'eml_import_url_count_' . $user_id, 0 );

		// reset loading more.
		update_option( 'eml_import_url_loading_more_' . $user_id, 0 );

		// prepare the results.
		Results::get_instance()->prepare();

		// set initial title.
		update_option( 'eml_import_title_' . $user_id, __( 'Import of URLs starting ..', 'external-files-in-media-library' ) );

		// get the URLs from request.
		$urls = filter_input( INPUT_POST, 'urls', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no URLs are given.
		if ( empty( $urls ) ) {
			// add the result to the list.
			Results::get_instance()->add( new No_Urls() );

			// mark import as not running.
			delete_option( 'eml_import_running_' . $user_id );

			// send empty response as JSON.
			wp_send_json( array() );
		}

		// convert the list from request to an array.
		$url_array = preg_split( '/\r\n|[\r\n]/', $urls );

		// bail if it is not an array.
		if ( ! is_array( $url_array ) ) {
			// add the result to the list.
			Results::get_instance()->add( new No_Urls() );

			// mark import as not running.
			delete_option( 'eml_import_running_' . $user_id );

			// send empty response as JSON.
			wp_send_json( array() );
		}

		// get the credential marker.
		$eml_use_credentials = absint( filter_input( INPUT_POST, 'use_credentials', FILTER_SANITIZE_NUMBER_INT ) );

		// get the credentials, if enabled.
		$login    = '';
		$password = '';
		if ( 1 === $eml_use_credentials ) {
			$login = filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! is_string( $login ) ) {
				$login = '';
			}
			$password = filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! is_string( $password ) ) {
				$password = '';
			}
			$password = html_entity_decode( $password );

			// bail if no credentials are given.
			if ( empty( $login ) || empty( $password ) ) {
				// add this error to the list.
				Results::get_instance()->add( new No_Credentials() );

				// mark import as not running.
				delete_option( 'eml_import_running_' . $user_id );

				// send empty response as JSON.
				wp_send_json( array() );
			}
		}

		// get the API key, if set.
		$api_key = filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $api_key ) ) {
			$api_key = '';
		}

		// get the term for credentials from Directory Listing Archive, if set.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );
		if ( $term_id > 0 ) {
			// get the term data.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );

			// if term_data could be loaded, use them.
			if ( ! empty( $term_data ) ) {
				// get the domain part of the directory.
				$term_directory_url = wp_parse_url( $term_data['directory'] );

				// complete the URL.
				foreach ( $url_array as $i => $url ) {
					// get the path from given URL.
					$parse_url = wp_parse_url( $url );

					// only change the given URL if the URL part is a part and not a URL.
					if ( ( empty( $parse_url ) || empty( $parse_url['scheme'] ) ) && $term_data['directory'] !== $url ) {
						$url_array[ $i ] = $term_data['directory'] . $url;
						if ( ! empty( $term_directory_url['scheme'] ) && ! empty( $term_directory_url['host'] ) ) {
							$url_array[ $i ] = $term_directory_url['scheme'] . '://' . $term_directory_url['host'] . $url;
						}
					}
				}

				// get the credentials.
				$login    = $term_data['login'];
				$password = $term_data['password'];
				$api_key  = $term_data['api_key'];
			}
		}

		/**
		 * Mark this hook as deprecated as we do not use it anymore since 5.0.0.
		 */
		apply_filters_deprecated( 'eml_import_add_to_queue', array( false, array() ), '5.0.0' );

		// collect errors.
		$errors = array();

		/**
		 * Run additional tasks just before AJAX-related import of URLs is starting.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $url_array List of URLs to import.
		 */
		do_action( 'eml_import_ajax_start', $url_array );

		// log this event.
		$log->create( __( 'URLs has been transferred via AJAX and will now be checked and imported.', 'external-files-in-media-library' ), '', 'info', 2 );

		/**
		 * Filter the URLs for use for this import.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $url_array The list of URLs to add.
		 */
		$url_array = apply_filters( 'eml_import_urls', $url_array );

		// save count of URLs.
		update_option( 'eml_import_url_max_' . $user_id, count( $url_array ) );

		// get the import-object.
		$import_obj = Import::get_instance();

		// add the credentials.
		$import_obj->set_login( $login );
		$import_obj->set_password( $password );
		$import_obj->set_api_key( $api_key );

		// loop through the list of URLs to add them.
		foreach ( $url_array as $url ) {
			// bail if URL is empty.
			if ( empty( $url ) ) {
				continue;
			}

			// cleanup the JS-URL.
			$url = str_replace( '&amp;', '&', $url );

			// log this event.
			$log->create( __( 'Try to import the URL.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// update title for progress.
			/* translators: %1$s will be replaced by the URL which is imported. */
			update_option( 'eml_import_title_' . $user_id, sprintf( __( 'Importing URL %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $url ) ) ) );

			/**
			 * Filter single URL before it will be added as external file.
			 *
			 * @since 3.0.0 Available since 3.0.0.
			 * @param string $url The URL.
			 */
			$url = apply_filters( 'eml_import_url', $url );

			// import the given URL in media library.
			$url_added = $import_obj->add_url( $url );

			// update counter for URLs.
			update_option( 'eml_import_url_count_' . $user_id, absint( get_option( 'eml_import_url_count_' . $user_id, 0 ) ) + 1 );

			// add URL to list of errors if it was not successfully.
			if ( ! $url_added ) {
				$errors[] = $url;
			}
		}

		// is marker to load more is set, return this info via AJAX.
		if ( 1 === absint( get_option( 'eml_import_url_loading_more_' . $user_id ) ) ) {
			wp_send_json( array( 'load_more' => 1 ) );
		}

		/**
		 * Filter the errors during an AJAX-request to add URLs.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $errors List of errors.
		 */
		$errors = apply_filters( 'eml_import_urls_errors', $errors );

		// loop through the errors and add them as URL_Error-objects to the list.
		foreach ( $errors as $url ) {
			// get log entries for this URL.
			$log_entries = $log->get_logs( $url, 'error', Import::get_instance()->get_identified() );

			// bail if log is empty.
			if ( empty( $log_entries ) ) {
				continue;
			}

			// add each log entry of this URL to the list.
			foreach ( $log_entries as $log_entry ) {
				// create the error entry.
				$error_obj = new Url_Result();
				$error_obj->set_result_text( $log_entry['log'] );
				$error_obj->set_url( $url );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );
			}
		}

		/**
		 * Run additional tasks just before AJAX-related import of URLs is marked as completed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $url_array List of URLs to import.
		 */
		do_action( 'eml_import_ajax_end', $url_array );

		// log this event.
		$log->create( __( 'End of AJAX-request to import URLs.', 'external-files-in-media-library' ), '', 'info', 2 );

		// mark import as not running.
		delete_option( 'eml_import_running_' . $user_id );

		// send empty response as JSON.
		wp_send_json( array() );
	}

	/**
	 * Return info about running import of URLs via AJAX-request.
	 *
	 * @return void
	 */
	public function get_external_urls_import_info(): void {
		// check nonce.
		check_ajax_referer( 'eml-url-upload-info-nonce', 'nonce' );

		// get the user ID.
		$user_id = get_current_user_id();

		// get the running marker.
		$running = absint( get_option( 'eml_import_running_' . $user_id, 0 ) );

		// if import is not running anymore, build the dialog for the response.
		$dialog = array();
		if ( 1 !== $running ) {
			// collect result text.
			$text = '';

			// get the results.
			$results = Results::get_instance()->get_results();

			// add them to the list.
			foreach ( $results as $result ) {
				$text .= '<li class="' . ( $result->is_error() ? 'error' : 'success' ) . '">' . $result->get_text() . '</li>';
			}

			// surround with hint and list, if not empty.
			if ( ! empty( $text ) ) {
				$text = '<p><strong>' . _n( 'The import returned the following result:', 'The import returned the following results:', count( $results ), 'external-files-in-media-library' ) . '</strong></p><ul class="efml-import-result-list">' . $text . '</ul>';
			}

			// create dialog.
			$dialog = array(
				'detail' => array(
					'className' => 'eml',
					'callback'  => 'document.dispatchEvent(new Event("efml-import-finished"));',
					'title'     => __( 'Import has been executed', 'external-files-in-media-library' ),
					'texts'     => array( $text ),
					'buttons'   => array(
						array(
							'action'  => 'closeDialog();',
							'variant' => 'primary',
							'text'    => __( 'Done', 'external-files-in-media-library' ),
						),
						array(
							'action'  => 'location.href="' . Helper::get_media_library_url() . '";',
							'variant' => 'secondary',
							'text'    => __( 'Go to media library', 'external-files-in-media-library' ),
						),
					),
				),
			);

			/**
			 * Filter the dialog after adding files.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param array<string,mixed> $dialog The dialog configuration.
			 */
			$dialog = apply_filters( 'eml_dialog_after_adding', $dialog );
		}

		// return import info.
		wp_send_json(
			array(
				absint( get_option( 'eml_import_url_count_' . $user_id, 0 ) ),
				absint( get_option( 'eml_import_url_max_' . $user_id, 0 ) ),
				$running,
				wp_kses_post( get_option( 'eml_import_title_' . $user_id, '' ) ),
				$dialog,
			)
		);
	}

	/**
	 * Process POST-request to insert single or multiple URLs in the media library.
	 *
	 * @return void
	 */
	public function add_urls_by_request(): void {
		// check nonce.
		check_admin_referer( 'efml-add-external-files', 'nonce' );

		// bail if user is missing the capability.
		if ( false === current_user_can( EFML_CAP_NAME ) ) {
			wp_safe_redirect( wp_get_referer() );
		}

		// get the URLs from request.
		$urls = filter_input( INPUT_POST, 'urls', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no URLs are given.
		if ( empty( $urls ) ) {
			// show error.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_url_import_error' );
			$transient_obj->set_message( __( 'No URLs given!', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'error' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( wp_get_referer() );
		}

		// convert the list from request to an array.
		$url_array = preg_split( '/\r\n|[\r\n]/', $urls );

		// bail if it is not an array.
		if ( ! is_array( $url_array ) ) {
			// show error.
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_url_import_error' );
			$transient_obj->set_message( __( 'No URLs given!', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'error' );
			$transient_obj->save();

			// forward user.
			wp_safe_redirect( wp_get_referer() );
		}

		// get the credential marker.
		$eml_use_credentials = absint( filter_input( INPUT_POST, 'use_credentials', FILTER_SANITIZE_NUMBER_INT ) );

		// get the credentials, if enabled.
		$login    = '';
		$password = '';
		if ( 1 === $eml_use_credentials ) {
			$login = filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! is_string( $login ) ) {
				$login = '';
			}
			$password = filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( ! is_string( $password ) ) {
				$password = '';
			}
			$password = html_entity_decode( $password );

			// bail if no credentials are given.
			if ( empty( $login ) || empty( $password ) ) {
				// show error.
				$transient_obj = Transients::get_instance()->add();
				$transient_obj->set_name( 'efml_url_import_error' );
				$transient_obj->set_message( '<strong>' . __( 'No credentials are given!', 'external-files-in-media-library' ) . '</strong> ' . __( 'You indicated that you would provide login details, but did not do so.', 'external-files-in-media-library' ) );
				$transient_obj->set_type( 'error' );
				$transient_obj->save();

				// forward user.
				wp_safe_redirect( wp_get_referer() );
			}
		}

		// get the API key, if set.
		$api_key = filter_input( INPUT_POST, 'api_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! is_string( $api_key ) ) {
			$api_key = '';
		}

		// get the term for credentials from Directory Listing Archive, if set.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );
		if ( $term_id > 0 ) {
			// get the term data.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );

			// if term_data could be loaded, use them.
			if ( ! empty( $term_data ) ) {
				// get the domain part of the directory.
				$term_directory_url = wp_parse_url( $term_data['directory'] );

				// complete the URL.
				foreach ( $url_array as $i => $url ) { // @phpstan-ignore foreach.nonIterable
					// get the path from given URL.
					$parse_url = wp_parse_url( $url );

					// only change the given URL if the URL part is a part and not a URL.
					if ( ( empty( $parse_url ) || empty( $parse_url['scheme'] ) ) && $term_data['directory'] !== $url ) {
						$url_array[ $i ] = $term_data['directory'] . $url; // @phpstan-ignore offsetAccess.nonOffsetAccessible
						if ( ! empty( $term_directory_url['scheme'] ) && ! empty( $term_directory_url['host'] ) ) {
							$url_array[ $i ] = $term_directory_url['scheme'] . '://' . $term_directory_url['host'] . $url;
						}
					}
				}

				// get the credentials.
				$login    = $term_data['login'];
				$password = $term_data['password'];
				$api_key  = $term_data['api_key'];
			}
		}

		// collect errors.
		$errors = array();

		// prepare the results.
		Results::get_instance()->prepare();

		// get the import-object.
		$import_obj = Import::get_instance();

		// add the credentials.
		$import_obj->set_login( $login );
		$import_obj->set_password( $password );
		$import_obj->set_api_key( $api_key );

		// loop through the list of URLs to add them.
		foreach ( (array) $url_array as $url ) {
			// bail if URL is empty.
			if ( empty( $url ) ) {
				continue;
			}

			// cleanup the JS-URL.
			$url = str_replace( '&amp;', '&', $url );

			/**
			 * Filter single URL before it will be added as external file.
			 *
			 * @since 3.0.0 Available since 3.0.0.
			 * @param string $url The URL.
			 */
			$url = apply_filters( 'eml_import_url', $url );

			// import the given URL in media library.
			$url_added = $import_obj->add_url( $url );

			// add URL to list of errors if it was not successfully.
			if ( ! $url_added ) {
				$errors[] = $url;
			}
		}

		/**
		 * Filter the errors during an AJAX-request to add URLs.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $errors List of errors.
		 */
		$errors = apply_filters( 'eml_import_urls_errors', $errors );

		// loop through the errors and add them as URL_Error-objects to the list.
		foreach ( $errors as $url ) {
			// get log entries for this URL.
			$log_entries = Log::get_instance()->get_logs( $url, 'error', Import::get_instance()->get_identified() );

			// bail if log is empty.
			if ( empty( $log_entries ) ) {
				continue;
			}

			// add each log entry of this URL to the list.
			foreach ( $log_entries as $log_entry ) {
				// create the error entry.
				$error_obj = new Url_Result();
				$error_obj->set_result_text( $log_entry['log'] );
				$error_obj->set_url( $url );
				$error_obj->set_error( true );

				// add the error object to the list of errors.
				Results::get_instance()->add( $error_obj );
			}
		}

		// collect result text.
		$text = '';

		// get the results.
		$results = Results::get_instance()->get_results();

		// set status for response message.
		$status = 'success';

		// add them to the list.
		foreach ( $results as $result ) {
			if ( $result->is_error() ) {
				$status = 'error';
			}
			$text .= '<li class="' . ( $result->is_error() ? 'error' : 'success' ) . '">' . $result->get_text() . '</li>';
		}

		// surround with hint and list, if not empty.
		if ( ! empty( $text ) ) {
			$text = '<p><strong>' . _n( 'The import returned the following result:', 'The import returned the following results:', count( $results ), 'external-files-in-media-library' ) . '</strong></p><ul class="efml-import-result-list">' . $text . '</ul>';
		}

		// show ok-message.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_url_import_error' );
		$transient_obj->set_message( $text );
		$transient_obj->set_type( $status );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( wp_get_referer() );
	}

	/**
	 * Set import title if HTTP import of presumed directory URL is starting.
	 *
	 * @param string $url The used URL.
	 *
	 * @return void
	 */
	public function set_http_import_title_start( string $url ): void {
		/* translators: %1$s is replaced by a URL. */
		update_option( 'eml_import_title_' . get_current_user_id(), sprintf( __( 'Import of presumed directory URL %1$s starting ..', 'external-files-in-media-library' ), $url ) );
	}

	/**
	 * Set import title if HTTP import will check a URL.
	 *
	 * @param string $url The used URL.
	 *
	 * @return void
	 */
	public function set_import_file_check( string $url ): void {
		// get current user ID.
		$user_id = get_current_user_id();

		/* translators: %1$s is replaced by a URL. */
		update_option( 'eml_import_title_' . $user_id, sprintf( __( 'Checking URL %1$s ..', 'external-files-in-media-library' ), $url ) );
		update_option( 'eml_import_url_count_' . $user_id, absint( get_option( 'eml_import_url_count_' . $user_id ) ) + 1 );
	}

	/**
	 * Set import title if HTTP import will save a URL in the media library.
	 *
	 * @param string $url The used URL.
	 *
	 * @return void
	 */
	public function set_import_file_save( string $url ): void {
		// get current user ID.
		$user_id = get_current_user_id();

		/* translators: %1$s is replaced by a URL. */
		update_option( 'eml_import_title_' . $user_id, sprintf( __( 'Saving URL %1$s ..', 'external-files-in-media-library' ), $url ) );
		update_option( 'eml_import_url_count_' . $user_id, absint( get_option( 'eml_import_url_count_' . $user_id ) ) + 1 );
	}

	/**
	 * Set new max value during import.
	 *
	 * @param string              $url The used URL.
	 * @param array<string,mixed> $matches The list of matches on this URL.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function set_import_max( string $url, array $matches ): void {
		// get current user ID.
		$user_id = get_current_user_id();

		// update entry.
		update_option( 'eml_import_url_max_' . $user_id, absint( get_option( 'eml_import_url_max_' . $user_id ) + count( $matches ) ) );
	}

	/**
	 * Filter the URLs to add.
	 *
	 * @param array<string,mixed> $urls The list of URLs.
	 *
	 * @return list<string>
	 */
	public function filter_urls( array $urls ): array {
		$url_array = array();

		// loop through them to check if they are additionally separated by comma.
		foreach ( $urls as $url ) {
			$url_array = array_merge( $url_array, explode( ',', $url ) );
		}

		// return the resulting list.
		return $url_array;
	}

	/**
	 * Add successfully imported URL to the list of successfully imported URLs.
	 *
	 * @param File                $external_file_obj The file object.
	 * @param array<string,mixed> $file_data The file data.
	 * @param string              $url The URL.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_imported_url_to_list( File $external_file_obj, array $file_data, string $url ): void {
		// create the entry.
		$result_obj = new Url_Result();
		$result_obj->set_error( false );
		$result_obj->set_url( $url );
		$result_obj->set_attachment_id( $external_file_obj->get_id() );

		// add the error object to the list of errors.
		Results::get_instance()->add( $result_obj );
	}

	/**
	 * Enabled to play a sound if import finishes.
	 *
	 * @param string $classes List of classes as string.
	 *
	 * @return string
	 */
	public function add_sound( string $classes ): string {
		// bail if setting is not enabled.
		if ( 1 !== absint( get_option( 'eml_play_sound' ) ) ) {
			return $classes;
		}

		// add the class.
		$classes .= ' efml-play-found';

		// return resulting list of classes.
		return $classes;
	}
}
