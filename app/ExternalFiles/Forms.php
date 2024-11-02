<?php
/**
 * This file contains an object which handles the forms to add external files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

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
		// add forms.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ), PHP_INT_MAX );
		add_action( 'post-plupload-upload-ui', array( $this, 'add_multi_form' ), 10, 0 );
		add_action( 'post-html-upload-ui', array( $this, 'add_single_form' ), 10, 0 );

		// add ajax endpoints.
		add_action( 'wp_ajax_eml_add_external_urls', array( $this, 'add_urls_via_ajax' ), 10, 0 );
		add_action( 'wp_ajax_eml_get_external_urls_import_info', array( $this, 'get_external_urls_import_info' ), 10, 0 );
	}

	/**
	 * Add CSS- and JS-files for backend.
	 *
	 * @return void
	 */
	public function add_styles_and_js_admin(): void {
		// backend-JS.
		wp_enqueue_script(
			'eml-admin',
			plugins_url( '/admin/js.js', EML_PLUGIN ),
			array( 'jquery' ),
			filemtime( Helper::get_plugin_dir() . '/admin/js.js' ),
			true
		);

		// admin-specific styles.
		wp_enqueue_style(
			'eml-admin',
			plugins_url( '/admin/style.css', EML_PLUGIN ),
			array(),
			filemtime( Helper::get_plugin_dir() . '/admin/style.css' ),
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-admin',
			'emlJsVars',
			array(
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'urls_nonce'                    => wp_create_nonce( 'eml-urls-upload-nonce' ),
				'availability_nonce'            => wp_create_nonce( 'eml-availability-check-nonce' ),
				'dismiss_nonce'                 => wp_create_nonce( 'eml-dismiss-nonce' ),
				'get_import_info_nonce'         => wp_create_nonce( 'eml-url-upload-info-nonce' ),
				'switch_hosting_nonce'          => wp_create_nonce( 'eml-switch-hosting-nonce' ),
				'review_url'                    => Helper::get_plugin_review_url(),
				'title_rate_us'                 => __( 'Rate this plugin', 'external-files-in-media-library' ),
				'title_import_progress'         => __( 'Import of URLs running', 'external-files-in-media-library' ),
				'title_import_ended'            => __( 'Import has been run', 'external-files-in-media-library' ),
				'text_import_ended'             => __( 'The import of given URLs has been run.', 'external-files-in-media-library' ),
				'lbl_ok'                        => __( 'OK', 'external-files-in-media-library' ),
				'lbl_cancel'                    => __( 'Cancel', 'external-files-in-media-library' ),
				'text_urls_imported'            => __( 'The following URLs has been imported successfully', 'external-files-in-media-library' ),
				'text_urls_errors'              => __( 'Following errors occurred', 'external-files-in-media-library' ),
				'title_no_urls'                 => __( 'No URLs given', 'external-files-in-media-library' ),
				'text_no_urls'                  => __( 'Please enter one or more URLs to import in the field.', 'external-files-in-media-library' ),
				'title_availability_refreshed'  => __( 'Availability refreshed', 'external-files-in-media-library' ),
				'text_not_available'            => __( 'The file is NOT available.', 'external-files-in-media-library' ),
				'text_is_available'             => __( 'The file is available.', 'external-files-in-media-library' ),
				'title_hosting_changed'         => __( 'Hosting changed.', 'external-files-in-media-library' ),
				'text_hosting_has_been_changed' => __( 'The hosting of this file has been changed.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Output form to enter multiple urls for external files.
	 *
	 * @return void
	 */
	public function add_multi_form(): void {
		// bail if user has not the capability for it.
		if ( false === current_user_can( EML_CAP_NAME ) ) {
			return;
		}

		// bail if get_current_screen() is not available (like for Divi).
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// get actual screen.
		$current_screen = get_current_screen();

		// on "add"-screen show our custom form-field to add external files.
		if ( 'add' === $current_screen->action ) {
			// create dialog.
			$dialog = array(
				'id'        => 'add_eml_files',
				'className' => 'eml',
				'title'     => __( 'Add URLs of external files', 'external-files-in-media-library' ),
				'texts'     => array(
					'<label for="external_files">' . esc_html__( 'Enter one URL per line for external files you want to insert in your library', 'external-files-in-media-library' ) . ' <a href="' . esc_url( Helper::get_support_url_for_urls() ) . '" target="_blank"><span class="dashicons dashicons-editor-help"></span></a></label><textarea id="external_files" name="external_files" class="eml_add_external_files" placeholder="https://example.com/file.pdf"></textarea>',
					'<details><summary>' . __( 'Add credentials to access these URLs', 'external-files-in-media-library' ) . '</summary><div><label for="eml_login">' . __( 'Login', 'external-files-in-media-library' ) . ':</label><input type="text" id="eml_login" name="text" value="" autocomplete="off"></div><div><label for="eml_password">' . __( 'Password', 'external-files-in-media-library' ) . ':</label><input type="password" id="eml_password" name="text" value="" autocomplete="off"></div><p><strong>' . __( 'Hint:', 'external-files-in-media-library' ) . '</strong> ' . __( 'files with credentials will be saved locally.', 'external-files-in-media-library' ) . '</p></details>',
				),
				'buttons'   => array(
					array(
						'action'  => 'eml_upload_files();',
						'variant' => 'primary',
						'text'    => __( 'Add URLs', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			?>
			<div class="eml_add_external_files_wrapper">
				<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( wp_json_encode( $dialog ) ); ?>"><?php echo esc_html__( 'Add external files', 'external-files-in-media-library' ); ?></a>
				<?php
				// add link to settings for admin.
				if ( current_user_can( 'manage_options' ) ) {
					?>
					<br><a href="<?php echo esc_url( Helper::get_config_url() ); ?>" class="eml_settings_link" title="<?php echo esc_html__( 'Settings', 'external-files-in-media-library' ); ?>"><span class="dashicons dashicons-admin-generic"></span></a>
					<?php
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
	 * Output form to enter multiple urls for external files.
	 *
	 * @return void
	 */
	public function add_single_form(): void {
		// bail if user has not the capability for it.
		if ( false === current_user_can( EML_CAP_NAME ) ) {
			return;
		}

		// create dialog.
		$dialog = array(
			'id'        => 'add_eml_files',
			'className' => 'eml',
			'title'     => __( 'Add URL', 'external-files-in-media-library' ),
			'texts'     => array(
				'<label for="external_files">' . esc_html__( 'Enter the URL of an external file you want to insert in your library', 'external-files-in-media-library' ) . '</label><input type="url" id="external_files" name="external_files" class="eml_add_external_files">',
				'<details><summary>' . __( 'Add credentials to access these URL', 'external-files-in-media-library' ) . '</summary><div><label for="eml_login">' . __( 'Login', 'external-files-in-media-library' ) . ':</label><input type="text" id="eml_login" name="text" value=""></div><div><label for="eml_password">' . __( 'Password', 'external-files-in-media-library' ) . ':</label><input type="password" id="eml_password" name="text" value=""></div><p>' . __( 'Hint:', 'external-files-in-media-library' ) . '</strong> ' . __( 'files with credentials will be saved locally.', 'external-files-in-media-library' ) . '</p></details>',
			),
			'buttons'   => array(
				array(
					'action'  => 'eml_upload_files();',
					'variant' => 'primary',
					'text'    => __( 'Add URL', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		?>
		<div class="eml_add_external_files_wrapper">
			<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( wp_json_encode( $dialog ) ); ?>"><?php echo esc_html__( 'Add external file', 'external-files-in-media-library' ); ?></a>
			<?php
			// add link to settings for admin.
			if ( current_user_can( 'manage_options' ) ) {
				?>
				<a href="<?php echo esc_url( Helper::get_config_url() ); ?>" class="eml_settings_link"><span class="dashicons dashicons-admin-generic"></span></a>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Process ajax-request for insert multiple urls to media library.
	 *
	 * @return       void
	 */
	public function add_urls_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-urls-upload-nonce', 'nonce' );

		// check capability.
		if ( false === current_user_can( EML_CAP_NAME ) ) {
			wp_send_json( array() );
		}

		// mark import as running.
		update_option( 'eml_import_running', time() );

		// reset counter.
		update_option( 'eml_import_url_count', 0 );

		// cleanup lists.
		delete_option( 'eml_import_errors' );
		delete_option( 'eml_import_files' );

		// set initial title.
		update_option( 'eml_import_title', __( 'Import of URLs starting ..', 'external-files-in-media-library' ) );

		// get files-object.
		$files_obj = Files::get_instance();

		// get the urls from request.
		$urls      = filter_input( INPUT_POST, 'urls', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$url_array = explode( "\n", $urls );

		// get the credentials.
		$login    = filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$password = filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// collect errors.
		$errors = array();
		$files  = array();

		if ( ! empty( $url_array ) ) {
			// save count of URLs.
			update_option( 'eml_import_url_max', count( $url_array ) );

			// add the credentials.
			$files_obj->set_login( $login );
			$files_obj->set_password( $password );

			// loop through them to add them to media library.
			foreach ( $url_array as $url ) {
				// bail if URL is empty.
				if ( empty( $url ) ) {
					continue;
				}

				// cleanup the JS-URL.
				$url = str_replace( '&amp;', '&', $url );

				// update counter for URLs.
				update_option( 'eml_import_url_count', absint( get_option( 'eml_import_url_count', 0 ) ) + 1 );

				// update title for progress.
				/* translators: %1$s will be replaced by the URL which is imported. */
				update_option( 'eml_import_title', sprintf( __( 'Importing URL %1$s', 'external-files-in-media-library' ), esc_html( $url ) ) );

				// import file in media library.
				$file_added = $files_obj->add_from_url( $url );

				// bail on error.
				if ( ! $file_added ) {
					$errors[] = $url;
				}

				// get the newly added file-object for list of files.
				$external_file_obj = $files_obj->get_file_by_url( $url );

				// bail if external file object could not be loaded or is not valid.
				if ( ! ( $external_file_obj instanceof File && $external_file_obj->is_valid() ) ) {
					continue;
				}

				// add file to the list.
				$files[] = array(
					'url'       => $external_file_obj->get_url( true ),
					'edit_link' => $external_file_obj->get_edit_url(),
				);
			}
		}

		// set progress title after import has been run.
		if ( ! empty( $files ) ) {
			update_option( 'eml_import_files', $files );
		} else {
			delete_option( 'eml_import_title' );
		}

		// get log instance.
		$log = Log::get_instance();

		// secure errors.
		$errors_for_response = array();
		foreach ( $errors as $url ) {
			$log_entry             = $log->get_logs( $url, 'error' );
			$errors_for_response[] = array(
				'url' => $url,
				'log' => ! empty( $log_entry ) ? $log_entry[0]['log'] : '',
			);
		}
		update_option( 'eml_import_errors', $errors_for_response );

		// mark import as not running.
		delete_option( 'eml_import_running' );

		// send empty response as JSON.
		wp_send_json( array() );
	}

	/**
	 * Return info about running import of files via AJAX-request.
	 *
	 * @return void
	 */
	public function get_external_urls_import_info(): void {
		// check nonce.
		check_ajax_referer( 'eml-url-upload-info-nonce', 'nonce' );

		// return import info.
		wp_send_json(
			array(
				absint( get_option( 'eml_import_url_count', 0 ) ),
				absint( get_option( 'eml_import_url_max', 0 ) ),
				absint( get_option( 'eml_import_running', 0 ) ),
				wp_kses_post( get_option( 'eml_import_title', '' ) ),
				get_option( 'eml_import_files', array() ),
				get_option( 'eml_import_errors', array() ),
			)
		);
	}
}
