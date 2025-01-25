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
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;

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

		// use our own actions.
		add_action( 'eml_http_directory_import_start', array( $this, 'set_http_import_title_start' ) );
		add_action( 'eml_ftp_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_http_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_sftp_directory_import_file_check', array( $this, 'set_import_file_check' ) );
		add_action( 'eml_file_import_before_save', array( $this, 'set_import_file_save' ) );
		add_action( 'eml_ftp_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_http_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_sftp_directory_import_files', array( $this, 'set_import_max' ), 10, 2 );
		add_action( 'eml_before_file_list', array( $this, 'set_import_max' ), 10, 2 );
		add_filter( 'eml_import_urls', array( $this, 'filter_urls' ) );
		add_action( 'eml_after_file_save', array( $this, 'add_imported_url_to_list' ) );
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
		if( ! in_array( $hook, array( 'media-new.php', 'edit-tags.php', 'post.php', 'settings_page_eml_settings', 'options-general.php', 'media_page_efml_local_directories' ),true ) ) {
			return;
		}

		// backend-JS.
		wp_enqueue_script(
			'eml-admin',
			plugins_url( '/admin/js.js', EFML_PLUGIN ),
			array( 'jquery' ),
			filemtime( Helper::get_plugin_dir() . '/admin/js.js' ),
			true
		);

		// admin-specific styles.
		wp_enqueue_style(
			'eml-admin',
			plugins_url( '/admin/style.css', EFML_PLUGIN ),
			array(),
			filemtime( Helper::get_plugin_dir() . '/admin/style.css' ),
		);

		$info_timeout = 200;
		/**
		 * Filter the timeout for AJAX-info-request.
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
				'availability_nonce'            => wp_create_nonce( 'eml-availability-check-nonce' ),
				'dismiss_nonce'                 => wp_create_nonce( 'eml-dismiss-nonce' ),
				'get_import_info_nonce'         => wp_create_nonce( 'eml-url-upload-info-nonce' ),
				'switch_hosting_nonce'          => wp_create_nonce( 'eml-switch-hosting-nonce' ),
				'reset_proxy_nonce'             => wp_create_nonce( 'eml-reset-proxy-nonce' ),
				'review_url'                    => Helper::get_plugin_review_url(),
				'add_file_url'                  => Helper::get_add_media_url(),
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
				'title_availability_refreshed'  => __( 'Availability refreshed', 'external-files-in-media-library' ),
				'text_not_available'            => __( 'The file is NOT available.', 'external-files-in-media-library' ),
				'text_is_available'             => '<strong>' . __( 'The file is available.', 'external-files-in-media-library' ) . '</strong> ' . __( 'It is no problem to continue using the URL in your media library.', 'external-files-in-media-library' ),
				'title_hosting_changed'         => __( 'Hosting changed', 'external-files-in-media-library' ),
				'text_hosting_has_been_changed' => __( 'The hosting of this file has been changed.', 'external-files-in-media-library' ),
				'txt_error'                     => '<strong>' . __( 'The following error occurred:', 'external-files-in-media-library' ) . '</strong>',
				'title_error'                   => __( 'An error occurred', 'external-files-in-media-library' ),
				'info_timeout'                  => $info_timeout,
				'title_hosting_change_wait'     => __( 'Please wait', 'external-files-in-media-library' ),
				'text_hosting_change_wait'      => __( 'The hosting of the file will be changed.', 'external-files-in-media-library' ),
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
		if ( false === current_user_can( EFML_CAP_NAME ) ) {
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
				'texts'     => $this->get_fields(),
				'buttons'   => array(
					array(
						'action'  => 'efml_upload_files();',
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

			// add link to settings for admin in dialog.
			if ( current_user_can( 'manage_options' ) ) {
				$dialog['buttons'][] = array(
					'action'  => 'location.href="' .  Helper::get_config_url() . '";',
					'className' => 'settings',
					'text'    => '',
				);
			}

			/**
			 * Filter the add-dialog.
			 *
			 * @since 2.1.0 Available since 2.1.0.
			 * @param array $dialog The dialog configuration.
			 */
			$dialog = apply_filters( 'eml_add_dialog', $dialog )

			?>
			<div class="eml_add_external_files_wrapper">
				<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( wp_json_encode( $dialog ) ); ?>"><?php echo esc_html__( 'Add external files', 'external-files-in-media-library' ); ?></a>
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
	 * Output form to enter multiple URLs for external files.
	 *
	 * @return void
	 */
	public function add_single_form(): void {
		// bail if user has not the capability for it.
		if ( false === current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// create dialog.
		$dialog = array(
			'id'        => 'add_eml_files',
			'className' => 'eml',
			'title'     => __( 'Add URL', 'external-files-in-media-library' ),
			'texts'     => $this->get_fields( true ),
			'buttons'   => array(
				array(
					'action'  => 'efml_upload_files();',
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

		// add link to settings for admin in dialog.
		if ( current_user_can( 'manage_options' ) ) {
			$dialog['buttons'][] = array(
				'action'  => 'location.href="' .  Helper::get_config_url() . '";',
				'className' => 'settings',
				'text'    => '',
			);
		}

		?>
		<div class="eml_add_external_files_wrapper">
			<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="<?php echo esc_attr( wp_json_encode( $dialog ) ); ?>"><?php echo esc_html__( 'Add external file', 'external-files-in-media-library' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Process ajax-request for insert multiple URLs to media library.
	 *
	 * @return       void
	 */
	public function add_urls_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-urls-upload-nonce', 'nonce' );

		// check capability.
		if ( false === current_user_can( EFML_CAP_NAME ) ) {
			wp_send_json( array() );
		}

		// get log object.
		$log = Log::get_instance();

		// log this event.
		$log->create( __( 'AJAX-request to import URLs has been called.', 'external-files-in-media-library' ), '', 'info', 2 );

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

		// get the URLs from request.
		$urls      = filter_input( INPUT_POST, 'urls', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$url_array = explode( "\n", $urls );

		// get the credentials.
		$login    = filter_input( INPUT_POST, 'login', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$password = filter_input( INPUT_POST, 'password', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// get additional fields.
		$additional_fields = isset( $_POST['additional_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['additional_fields'] ) ) : array();

		// get the term for credentials from Directory Listing Archive, if set.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );
		if( $term_id > 0 ) {
			// get the term data.
			$term_data = Taxonomy::get_instance()->get_entry( $term_id );

			// if term_data could be loaded, use them.
			if( ! empty( $term_data ) ) {
				foreach( $url_array as $i => $url ) {
					if( $term_data['directory'] !== $url ) {
						$url_array[$i] = $term_data['directory'] . $url;
					}
				}
				$login = $term_data['login'];
				$password = $term_data['password'];
			}
		}

		$false = false;
		/**
		 * Get the queue setting for the import.
		 *
		 * @since 2.1.0 Available since 2.1.0.
		 * @param bool $false Set to true to import the files via queue.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		$add_to_queue = apply_filters( 'eml_import_add_to_queue', $false, $additional_fields );

		// collect errors.
		$errors = array();

		/**
		 * Run additional tasks just before AJAX-related import of URLs is started.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $url_array List of URLs to import.
		 * @param array $additional_fields List of additional fields from form (since 2.1.0).
		 */
		do_action( 'eml_import_ajax_start', $url_array, $additional_fields );

		// if URLs are given, check them.
		if ( ! empty( $url_array ) ) {
			// log this event.
			$log->create( __( 'URLs has been transferred via AJAX and will now be checked and imported.', 'external-files-in-media-library' ), '', 'info', 2 );

			/**
			 * Loop through them to add them to media library after filtering the URL list.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array $url_array The list of URLs to add.
			 * @param array $additional_fields List of additional fields from form (since 2.1.0).
			 */
			$url_array = apply_filters( 'eml_import_urls', $url_array, $additional_fields );

			// save count of URLs.
			update_option( 'eml_import_url_max', count( $url_array ) );

			// add the credentials.
			$files_obj->set_login( $login );
			$files_obj->set_password( $password );

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
				update_option( 'eml_import_title', sprintf( __( 'Importing URL %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $url ) ) ) );

				/**
				 * Filter single URL before it will be added as external file.
				 *
				 * @since 2.1.0 Available since 2.1.0.
				 * @param string $url The URL.
				 * @param array $additional_fields List of additional fields from form.
				 */
				$url = apply_filters( 'eml_import_url_before', $url, $additional_fields );

				// import file in media library if enqueue option is not set.
				$file_added = $files_obj->add_url( $url, $add_to_queue );

				// update counter for URLs.
				update_option( 'eml_import_url_count', absint( get_option( 'eml_import_url_count', 0 ) ) + 1 );

				// add URL to list of errors if add to queue was not used.
				if ( ! $file_added && ! $add_to_queue ) {
					$errors[] = $url;

					// log this event.
					$log->create( __( 'Error occurred during check of URL. The URL has not been added.', 'external-files-in-media-library' ), $url, 'info', 1 );
				} else {
					/**
					 * Run additional tasks for single URL after it has been successfully added as external file.
					 *
					 * @since 2.1.0 Available since 2.1.0.
					 * @param string $url The URL.
					 * @param array $additional_fields List of additional fields from form.
					 */
					do_action( 'eml_import_url_after', $url, $additional_fields );
				}
			}
		}

		// collect the errors for response.
		$errors_for_response = array();

		/**
		 * Filter the errors during an AJAX-request to add URLs.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $errors List of errors.
		 */
		$errors = apply_filters( 'eml_import_urls_errors', $errors );

		// loop through the errors.
		foreach ( $errors as $url ) {
			// get log entry for this URLs.
			$log_entry = $log->get_logs( $url, 'error' );

			// bail if log is empty.
			if ( empty( $log_entry ) ) {
				continue;
			}

			// add the result to the response.
			$errors_for_response[] = array(
				'url' => $url,
				'log' => $log_entry[0]['log'],
			);
		}

		// add errors for output via info-request.
		update_option( 'eml_import_errors', $errors_for_response );

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
		delete_option( 'eml_import_running' );

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

		// get the running marker.
		$running = absint( get_option( 'eml_import_running', 0 ) );

		// if import is not running build the dialog for the response.
		$dialog = array();
		if ( 1 !== $running ) {
			// collect result text.
			$result = '';

			// get list of errors.
			$errors = get_option( 'eml_import_errors' );

			// add errors to the resulting text list.
			if ( ! empty( $errors ) ) {
				$result .= '<p><strong>' . _n( 'The following error occurred:', 'The following error occurred:', count( $errors ), 'external-files-in-media-library' ) . '</strong></p><ul class="eml-error-list">';
				foreach ( $errors as $error ) {
					// if string is not a valid URL just show it.
					if ( ! filter_var( $error['url'], FILTER_VALIDATE_URL ) ) {
						$result .= '<li>' . esc_html( $error['url'] ) . '<br>' . wp_kses_post( $error['log'] ) . '</li>';
					} else {
						// otherwise link it.
						$result .= '<li><a href="' . esc_url( $error['url'] ) . '" target="_blank">' . esc_html( Helper::shorten_url( $error['url'] ) ) . '</a><br>' . wp_kses_post( $error['log'] ) . '</li>';
					}
				}
				$result .= '</ul>';
			}

			// get list of successfully imported URLs.
			$successfully_imported_urls = get_option( 'eml_import_files' );

			// check if this is an array.
			if ( ! is_array( $successfully_imported_urls ) ) {
				$successfully_imported_urls = array();
			}

			// set URL count.
			$url_count = count( $successfully_imported_urls );

			// add successfully imported URLs to the resulting text list.
			if ( ! empty( $successfully_imported_urls ) ) {
				$result .= '<p><strong>' . _n( 'The following URL have been saved successfully:', 'The following URLs has been saved successfully:', count( $successfully_imported_urls ), 'external-files-in-media-library' ) . '</strong></p><ul class="eml-success-list">';
				foreach ( $successfully_imported_urls as $url ) {
					$result .= '<li><a href="' . esc_url( $url['url'] ) . '" target="_blank">' . esc_html( Helper::shorten_url( $url['url'] ) ) . '</a> <a href="' . esc_url( $url['edit_link'] ) . '" target="_blank" class="dashicons dashicons-edit"></a></li>';
				}
				$result .= '</ul>';
			}

			// show hint of no URLs has been imported.
			if ( 0 === $url_count ) {
				// create dialog.
				$dialog = array(
					'detail' => array(
						'className' => 'eml',
						'title'     => __( 'Import has been run', 'external-files-in-media-library' ),
						'texts'     => array(
							'<p><strong>' . __( 'No URLs has been imported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'They might have been added to the queue if you used this option.', 'external-files-in-media-library' ) . '</p>',
							$result,
						),
						'buttons'   => array(
							array(
								'action'  => 'closeDialog();',
								'variant' => 'primary',
								'text'    => __( 'Finalized', 'external-files-in-media-library' ),
							),
							array(
								'action'  => 'location.href="' . Settings::get_instance()->get_url( 'eml_logs' ) . '";',
								'variant' => 'secondary',
								'text'    => __( 'Go to logs', 'external-files-in-media-library' ),
							),
							array(
								'action'  => 'location.href="' . Settings::get_instance()->get_url( 'eml_queue_table' ) . '";',
								'variant' => 'secondary',
								'text'    => __( 'Go to queue', 'external-files-in-media-library' ),
							),
						),
					),
				);
			} else {
				// create dialog.
				$dialog = array(
					'detail' => array(
						'className' => 'eml',
						'title'     => __( 'Import has been run', 'external-files-in-media-library' ),
						'texts'     => array(
							/* translators: %1$d will be replaced by a number. */
							'<p><strong>' . sprintf( _n( 'The import has checked %1$d URL with the following result:', 'The import has checked %1$d URLs with the following result:', $url_count, 'external-files-in-media-library' ), $url_count ) . '</strong></p>',
							$result,
						),
						'buttons'   => array(
							array(
								'action'  => 'closeDialog();',
								'variant' => 'primary',
								'text'    => __( 'Finalized', 'external-files-in-media-library' ),
							),
							array(
								'action'  => 'location.href="' . Settings::get_instance()->get_url( 'eml_logs' ) . '";',
								'variant' => 'secondary',
								'text'    => __( 'Go to logs', 'external-files-in-media-library' ),
							),
							array(
								'action'  => 'location.href="' . Helper::get_media_library_url() . '";',
								'variant' => 'secondary',
								'text'    => __( 'Go to media library', 'external-files-in-media-library' ),
							),
						),
					),
				);
			}
		}

		// return import info.
		wp_send_json(
			array(
				absint( get_option( 'eml_import_url_count', 0 ) ),
				absint( get_option( 'eml_import_url_max', 0 ) ),
				$running,
				wp_kses_post( get_option( 'eml_import_title', '' ) ),
				$dialog,
			)
		);
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
		update_option( 'eml_import_title', sprintf( __( 'Import of presumed directory URL %1$s starting ..', 'external-files-in-media-library' ), $url ) );
	}

	/**
	 * Set import title if HTTP import will check a URL.
	 *
	 * @param string $url The used URL.
	 *
	 * @return void
	 */
	public function set_import_file_check( string $url ): void {
		/* translators: %1$s is replaced by a URL. */
		update_option( 'eml_import_title', sprintf( __( 'Checking URL %1$s ..', 'external-files-in-media-library' ), $url ) );
		update_option( 'eml_import_url_count', absint( get_option( 'eml_import_url_count' ) ) + 1 );
	}

	/**
	 * Set import title if HTTP import will save a URL in the media library.
	 *
	 * @param string $url The used URL.
	 *
	 * @return void
	 */
	public function set_import_file_save( string $url ): void {
		/* translators: %1$s is replaced by a URL. */
		update_option( 'eml_import_title', sprintf( __( 'Saving URL %1$s ..', 'external-files-in-media-library' ), $url ) );
		update_option( 'eml_import_url_count', absint( get_option( 'eml_import_url_count' ) ) + 1 );
	}

	/**
	 * Set new max value during import.
	 *
	 * @param string $url The used URL.
	 * @param array  $matches The list of matches on this URL.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function set_import_max( string $url, array $matches ): void {
		update_option( 'eml_import_url_max', absint( get_option( 'eml_import_url_max' ) + count( $matches ) ) );
	}

	/**
	 * Filter the URLs to add.
	 *
	 * @param array $urls The list of URLs.
	 *
	 * @return array
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
	 * @param File $external_file_obj The file object.
	 *
	 * @return void
	 */
	public function add_imported_url_to_list( File $external_file_obj ): void {
		// get actual list.
		$files = get_option( 'eml_import_files' );

		// if list is not an array, create one.
		if ( ! is_array( $files ) ) {
			$files = array();
		}

		// add this file in the array.
		$files[] = array(
			'url'       => $external_file_obj->get_url( true ),
			'edit_link' => get_edit_post_link( $external_file_obj->get_id() ),
		);

		// update the list.
		update_option( 'eml_import_files', $files );
	}

	/**
	 * Return list of fields.
	 *
	 * @param bool $single True if the single fields should be returned.
	 *
	 * @return array
	 */
	private function get_fields( bool $single = false ): array {
		// collect the fields.
		$fields = array();

		// use single fields.
		if ( $single ) {
			// add URL field.
			$fields[] = '<label for="external_files">' . esc_html__( 'Enter the URL of an external file you want to insert in your library', 'external-files-in-media-library' ) . ' <a href="' . esc_url( Helper::get_support_url_for_urls() ) . '" target="_blank"><span class="dashicons dashicons-editor-help"></span></a></label><input type="url" id="external_files" name="external_files" class="eml_add_external_files" placeholder="https://example.com/file.pdf">';

			// add credentials fields.
			$fields[] = '<details><summary>' . __( 'Add credentials to access this URL', 'external-files-in-media-library' ) . '</summary><div><label for="eml_use_credentials"><input type="checkbox" name="eml_use_credentials" value="1" id="eml_use_credentials"> ' . esc_html__( 'Use below credentials to import the URL', 'external-files-in-media-library' ) . '</label></div><div><label for="eml_login">' . __( 'Login', 'external-files-in-media-library' ) . ':</label><input type="text" id="eml_login" name="text" value="" autocomplete="off"></div><div><label for="eml_password">' . __( 'Password', 'external-files-in-media-library' ) . ':</label><input type="password" id="eml_password" name="text" value="" autocomplete="off"></div><p><strong>' . __( 'Hint:', 'external-files-in-media-library' ) . '</strong> ' . __( 'Files with credentials will be saved locally.', 'external-files-in-media-library' ) . '</p></details>';

			/**
			 * Filter the fields for the dialog. Additional fields must be marked with "eml-use-for-import" as class.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array $fields List of fields.
			 */
			return apply_filters( 'eml_import_fields', $fields );
		}

		// add URLs field.
		$fields[] = '<label for="external_files">' . esc_html__( 'Enter one URL per line for external files you want to insert in your library', 'external-files-in-media-library' ) . ' <a href="' . esc_url( Helper::get_support_url_for_urls() ) . '" target="_blank"><span class="dashicons dashicons-editor-help"></span></a></label><textarea id="external_files" name="external_files" class="eml_add_external_files" placeholder="https://example.com/file.pdf"></textarea>';

		// add credentials fields.
		$fields[] = '<details><summary>' . __( 'Add credentials to access these URLs', 'external-files-in-media-library' ) . '</summary><div><label for="eml_use_credentials"><input type="checkbox" name="eml_use_credentials" value="1" id="eml_use_credentials"> ' . esc_html__( 'Use below credentials to import these URLs', 'external-files-in-media-library' ) . '</label></div><div><label for="eml_login">' . __( 'Login', 'external-files-in-media-library' ) . ':</label><input type="text" id="eml_login" name="text" value="" autocomplete="off"></div><div><label for="eml_password">' . __( 'Password', 'external-files-in-media-library' ) . ':</label><input type="password" id="eml_password" name="text" value="" autocomplete="off"></div><p><strong>' . __( 'Hint:', 'external-files-in-media-library' ) . '</strong> ' . __( 'Files with credentials will be saved locally.', 'external-files-in-media-library' ) . '</p></details>';

		/**
		 * Filter the fields for the dialog. Additional fields must be marked with "eml-use-for-import" as class.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $fields List of fields.
		 */
		return apply_filters( 'eml_import_fields', $fields );
	}
}
