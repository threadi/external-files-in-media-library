<?php
/**
 * This file contains a controller-object to handle external files operations.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_Query;

/**
 * Controller for external files-tasks.
 *
 * @noinspection PhpUnused
 */
class Files {
	/**
	 * Instance of actual object.
	 *
	 * @var Files|null
	 */
	private static ?Files $instance = null;

	/**
	 * The login.
	 *
	 * @var string
	 */
	private string $login = '';

	/**
	 * The password.
	 *
	 * @var string
	 */
	private string $password = '';

	/**
	 * The process start time for import.
	 *
	 * @var float
	 */
	private float $start_time = 0.0;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Files
	 */
	public static function get_instance(): Files {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// initialize the queue.
		Queue::get_instance()->init();

		// misc.
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_media_box' ), 20, 1 );

		// main handling of external files in media tasks.
		add_filter( 'attachment_link', array( $this, 'get_attachment_link' ), 10, 2 );
		add_filter( 'wp_get_attachment_url', array( $this, 'get_attachment_url' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'image_downsize' ), 10, 3 );
		add_action( 'import_end', array( $this, 'import_end' ), 10, 0 );
		add_filter( 'redirect_canonical', array( $this, 'disable_attachment_page' ) );
		add_filter( 'template_redirect', array( $this, 'disable_attachment_page' ) );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'get_image_srcset' ), 10, 5 );
		add_filter( 'wp_import_post_meta', array( $this, 'set_import_marker_for_attachments' ), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'get_attachment_metadata' ), 10, 2 );
		add_action( 'delete_attachment', array( $this, 'log_url_deletion' ), 10, 1 );
		add_action( 'delete_attachment', array( $this, 'delete_file_from_cache' ), 10, 1 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'check_srcset_meta' ), 10, 4 );

		// add ajax hooks.
		add_action( 'wp_ajax_eml_check_availability', array( $this, 'check_file_availability_via_ajax' ), 10, 0 );
		add_action( 'wp_ajax_eml_switch_hosting', array( $this, 'switch_hosting_via_ajax' ), 10, 0 );

		// use our own hooks.
		add_filter( 'eml_file_import_title', array( $this, 'optimize_file_title' ), 10, 3 );
		add_filter( 'eml_file_import_title', array( $this, 'set_file_title' ), 10, 3 );
		add_filter( 'eml_http_directory_regex', array( $this, 'use_link_regex' ), 10, 2 );
		add_action( 'eml_file_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_ftp_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_http_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_sftp_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_filter( 'eml_help_tabs', array( $this, 'add_help' ), 20 );
		add_filter( 'eml_file_import_attachment', array( $this, 'add_file_date' ), 10, 3 );

		// add admin actions.
		add_action( 'admin_action_eml_reset_thumbnails', array( $this, 'reset_thumbnails_by_request' ) );
	}

	/**
	 * Return the URL of an external file.
	 *
	 * @param string $url               The URL which is requested.
	 * @param int    $attachment_id     The attachment-ID which is requested.
	 *
	 * @return string
	 */
	public function get_attachment_url( string $url, int $attachment_id ): string {
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if file is not a URL-file.
		if ( false === $external_file_obj ) {
			return $url;
		}

		// return the original URL if this URL-file is not valid or not available.
		if ( false === $external_file_obj->is_valid() || false === $external_file_obj->get_availability() ) {
			return $url;
		}

		// use local URL if URL-file is locally saved.
		if ( false !== $external_file_obj->is_locally_saved() ) {
			$uploads = wp_get_upload_dir();
			if ( false === $uploads['error'] ) {
				return trailingslashit( $uploads['baseurl'] ) . $external_file_obj->get_attachment_url();
			}
		}

		// return the extern URL.
		return $external_file_obj->get_url();
	}

	/**
	 * Disable attachment-page-links for external files, if this is enabled.
	 *
	 * @param string $url               The URL which is requested.
	 * @param ?int   $attachment_id     The attachment-ID which is requested.
	 *
	 * @return string
	 */
	public function get_attachment_link( string $url, ?int $attachment_id ): string {
		$false = false;
		/**
		 * Filter if attachment link should not be changed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $false True if URL should not be changed.
		 * @param string $url The given URL.
		 * @param ?int $attachment_id The ID of the attachment.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( false !== apply_filters( 'eml_attachment_link', $false, $url, $attachment_id ) ) {
			return $url;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( absint( $attachment_id ) );

		// bail if file is not a URL-file.
		if ( false === $external_file_obj ) {
			return $url;
		}

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return $url;
		}

		// bail if attachment pages are not disabled.
		if ( 0 === absint( get_option( 'eml_disable_attachment_pages', 0 ) ) ) {
			return $url;
		}

		// return the external URL.
		return $external_file_obj->get_url( is_admin() );
	}

	/**
	 * Get all external files in media library as external_file-object-array.
	 *
	 * @return array
	 */
	public function get_files(): array {
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $result->post_count ) {
			return array();
		}

		// collect the results.
		$results = array();

		// loop through them.
		foreach ( $result->get_posts() as $attachment_id ) {
			// get the object of the external file.
			$external_file_obj = $this->get_file( $attachment_id );

			// bail if object could not be loaded.
			if ( ! $external_file_obj || ! $external_file_obj->is_valid() ) {
				continue;
			}

			// add object to the list.
			$results[] = $external_file_obj;
		}

		// bail if list is empty.
		if ( empty( $results ) ) {
			return array();
		}

		// return the resulting list.
		return $results;
	}

	/**
	 * Add a URL in media library.
	 *
	 * This is the main function for any import.
	 *
	 * If URL is a directory we try to import all files from this directory.
	 * If URL is a single file, this single file will be imported.
	 *
	 * The import will be use a protocol handler matching the protocol of the used URL.
	 *
	 * @param string $url The URL to add.
	 * @param bool   $add_to_queue Whether this URL should just be added to the queue.
	 *
	 * @return bool true if anything from the URL has been added successfully.
	 */
	public function add_url( string $url, bool $add_to_queue = false ): bool {
		// get the log object.
		$log = Log::get_instance();

		$false = false;
		/**
		 * Filter the given URL against custom blacklists.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $false Return true if blacklist matches.
		 * @param string $url The given URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'eml_blacklist', $false, $url ) ) {
			// log this event.
			$log->create( __( 'URL has matched a blacklist entry.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// do not import this URL.
			return false;
		}

		/**
		 * Save the start time.
		 */
		$this->set_import_start_time();

		/**
		 * Get the handler for this URL depending on its protocol.
		 */
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $url );

		/**
		 * Do nothing if URL is using a not supported tcp protocol.
		 */
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		/**
		 * Add the given credentials, even if none are set.
		 */
		$protocol_handler_obj->set_login( $this->get_login() );
		$protocol_handler_obj->set_password( $this->get_password() );

		/**
		 * Set queue-mode.
		 */
		$protocol_handler_obj->set_queue_mode( $add_to_queue );

		// embed necessary files.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		/**
		 * Get information about files under the given URL.
		 */
		$files = $protocol_handler_obj->get_url_infos();

		/**
		 * Do nothing if check of URL resulted in empty file list if queue is not used.
		 */
		if ( empty( $files ) && ! $add_to_queue ) {
			// log event.
			$log->create( __( 'No files found under given URL.', 'external-files-in-media-library' ), esc_html( $url ), 'error' );

			// do not import this URL.
			return false;
		}

		/**
		 * Do nothing if check of URL resulted in empty list if queue is used.
		 */
		if ( empty( $files ) && $add_to_queue ) {
			// log this event.
			$log->create( __( 'URL has been added to queue.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// do not import this URL.
			return false;
		}

		/**
		 * Get user the attachment would be assigned to.
		 */
		$user_id = Helper::get_current_user_id();

		/**
		 * Filter the user_id for a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param int $user_id The title generated by importer.
		 * @param string $url The requested external URL.
		 */
		$user_id = apply_filters( 'eml_file_import_user', $user_id, $url );

		/**
		 * Run action just before we go through the list of resulting files.
		 */
		do_action( 'eml_before_file_list', $url, $files );

		// show progress.
		/* translators: %1$s is replaced by a URL. */
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( _n( 'Save file from URL', 'Save files from URL', count( $files ), 'external-files-in-media-library' ), count( $files ) ) : '';

		/**
		 * Loop through the results and save each in the media library.
		 */
		foreach ( $files as $file_data ) {
			/**
			 * Run additional tasks before new external file will be added.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array $file_data The array with the file data.
			 */
			do_action( 'eml_before_file_save', $file_data );

			/**
			 * Filter the title for a single file during import.
			 *
			 * @since 1.1.0 Available since 1.1.0
			 *
			 * @param string $title     The title generated by importer.
			 * @param string $url       The requested external URL.
			 * @param array  $file_data List of file settings detected by importer.
			 */
			$title = apply_filters( 'eml_file_import_title', $file_data['title'], $file_data['url'], $file_data );

			/**
			 * Prepare attachment-post-settings.
			 */
			$post_array = array(
				'post_author' => $user_id,
				'post_name'   => $title,
			);

			/**
			 * Filter the attachment settings
			 *
			 * @since 2.0.0 Available since 2.0.0
			 *
			 * @param array $post_array     The attachment settings.
			 * @param string $url       The requested external URL.
			 * @param array  $file_data List of file settings detected by importer.
			 */
			$post_array = apply_filters( 'eml_file_import_attachment', $post_array, $file_data['url'], $file_data );

			/**
			 * Run action just before the file is saved in database.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $file_url   The URL to import.
			 */
			do_action( 'eml_file_import_before_save', $file_data['url'] );

			/**
			 * Save this file local if it is required.
			 */
			if ( false !== $file_data['local'] ) {
				// log this event.
				$log->create( __( 'URL will be saved local.', 'external-files-in-media-library' ), $file_data['url'], 'info', 2 );

				// import file as image via WP-own functions.
				$array         = array(
					'name'     => $title,
					'type'     => $file_data['mime-type'],
					'tmp_name' => $file_data['tmp-file'],
					'error'    => 0,
					'size'     => $file_data['filesize'],
				);
				$attachment_id = media_handle_sideload( $array, 0, null, $post_array );

				// delete the tmp file (if media_handle_sideload() does not have it already done).
				if ( file_exists( $file_data['tmp-file'] ) ) {
					// get WP Filesystem-handler.
					require_once ABSPATH . '/wp-admin/includes/file.php';
					\WP_Filesystem();
					global $wp_filesystem;
					$wp_filesystem->delete( $file_data['tmp-file'] );

					// log this event.
					$log->create( __( 'Temp-file for import has been deleted.', 'external-files-in-media-library' ), $file_data['url'], 'info', 2 );
				}
			} else {
				// log this event.
				$log->create( __( 'URL will be stay external, just save the reference in media library.', 'external-files-in-media-library' ), $file_data['url'], 'info', 2 );

				/**
				 * For all other files: simply create the attachment.
				 */
				$attachment_id = wp_insert_attachment( $post_array, $file_data['url'] );
			}

			// bail on any error.
			if ( is_wp_error( $attachment_id ) ) {
				/* translators: %1$s will be replaced by a WP-error-message */
				$log->create( sprintf( __( 'URL could not be saved because of the following error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_json_encode( $attachment_id->errors['upload_error'][0] ) . '</code>' ), $file_data['url'], 'error' );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail to next file.
				continue;
			}

			// get external file object to update its settings.
			$external_file_obj = $this->get_file( $attachment_id );

			// bail if object could not be loaded.
			if ( ! $external_file_obj ) {
				// log event.
				$log->create( __( 'External file object for URL could not be loaded.', 'external-files-in-media-library' ), $file_data['url'], 'error' );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail to next file.
				continue;
			}

			// mark this attachment as one of our own plugin through setting the URL.
			$external_file_obj->set_url( $file_data['url'] );

			// set title.
			$external_file_obj->set_title( $title );

			// set mime-type.
			$external_file_obj->set_mime_type( $file_data['mime-type'] );

			// set availability-status (true is for 'is available', false if it is not).
			$external_file_obj->set_availability( true );

			// set filesize.
			$external_file_obj->set_filesize( $file_data['filesize'] );

			// mark if this file is an external file locally saved.
			$external_file_obj->set_is_local_saved( $file_data['local'] );

			// save the credentials on the object, if set.
			$external_file_obj->set_login( $this->get_login() );
			$external_file_obj->set_password( $this->get_password() );

			// save file-type-specific meta data.
			$external_file_obj->set_metadata();

			// add file to local cache, if necessary.
			$external_file_obj->add_to_cache();

			// log that URL has been added as file in media library.
			$log->create( __( 'URL successfully added in media library.', 'external-files-in-media-library' ), $file_data['url'], 'success', 0 );
			$log->create( __( 'Using following settings to save this URL:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $file_data ) . '</code>', $file_data['url'], 'success', 2 );

			/**
			 * Run additional tasks after new external file has been added.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param File $external_file_obj The object of the external file.
			 * @param array $file_data The array with the file data.
			 */
			do_action( 'eml_after_file_save', $external_file_obj, $file_data );

			// show progress.
			$progress ? $progress->tick() : '';
		}

		// finish the progress.
		$progress ? $progress->finish() : '';

		// return ok.
		return true;
	}

	/**
	 * Log deletion of external URLs in media library.
	 *
	 * @param int $attachment_id  The attachment_id which will be deleted.
	 *
	 * @return void
	 */
	public function log_url_deletion( int $attachment_id ): void {
		// get the external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file_obj || false === $external_file_obj->is_valid() ) {
			return;
		}

		/**
		 * Run additional tasks for URL deletion.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param File $external_file_obj The object which has been deleted.
		 */
		do_action( 'eml_file_delete', $external_file_obj );

		// log deletion.
		Log::get_instance()->create( __( 'URL has been deleted from media library.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'success', 1 );
	}

	/**
	 * Return external_file object of single attachment by given ID without checking its availability.
	 *
	 * @param int $attachment_id    The attachment_id where we want to call the File-object.
	 *
	 * @return false|File
	 */
	public function get_file( int $attachment_id ): false|File {
		return new File( $attachment_id );
	}

	/**
	 * Delete the given external-file-object with all its data from media library.
	 *
	 * @param File $external_file_obj  The File which will be deleted.
	 *
	 * @return void
	 */
	public function delete_file( File $external_file_obj ): void {
		// delete thumbs.
		$external_file_obj->delete_thumbs();

		// delete the file entry itself.
		wp_delete_attachment( $external_file_obj->get_id(), true );
	}

	/**
	 * Check all external files regarding their availability.
	 *
	 * @return void
	 */
	public function check_files(): void {
		// get all files.
		$files = $this->get_files();

		// bail if no files are found.
		if ( empty( $files ) ) {
			return;
		}

		// loop through the files and check each.
		foreach ( $files as $external_file_obj ) {
			// bail if obj is not an external file object.
			if ( ! $external_file_obj instanceof File ) {
				continue;
			}

			// get the protocol handler for this URL.
			$protocol_handler = $external_file_obj->get_protocol_handler_obj();

			// bail if handler is false.
			if ( ! $protocol_handler ) {
				continue;
			}

			// get and save its availability.
			$external_file_obj->set_availability( $protocol_handler->check_availability( $external_file_obj->get_url() ) );
		}
	}

	/**
	 * Get all imported external files.
	 *
	 * @return array
	 */
	public function get_imported_external_files(): array {
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
				array(
					'key'   => EFML_POST_IMPORT_MARKER,
					'value' => 1,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail if result is 0.
		if ( 0 === $result->post_count ) {
			return array();
		}

		// get the list.
		$results = array();

		// loop through the results.
		foreach ( $result->get_posts() as $attachment_id ) {
			// get the external file object.
			$external_file_obj = $this->get_file( $attachment_id );

			// bail if object could not be loaded.
			if ( ! $external_file_obj || false === $external_file_obj->is_valid() ) {
				continue;
			}

			// add to the list.
			$results[] = $external_file_obj;
		}

		// return resulting list.
		return $results;
	}

	/**
	 * Get file-object by a given URL.
	 *
	 * @param string $url The URL we use to search.
	 *
	 * @return false|File
	 */
	public function get_file_by_url( string $url ): false|File {
		// bail if URL is empty.
		if ( empty( $url ) ) {
			return false;
		}

		// query for the post with the given URL.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EFML_POST_META_URL,
					'value'   => $url,
					'compare' => '=',
				),
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail if more or less than 1 is found.
		if ( 1 !== $result->post_count ) {
			return false;
		}

		// get the external file object for the match.
		$external_file_obj = $this->get_file( $result->posts[0] );

		// bail if the external file object could not be created or is not valid.
		if ( ! ( $external_file_obj && $external_file_obj->is_valid() ) ) {
			return false;
		}

		// return the object.
		return $external_file_obj;
	}

	/**
	 * Get file-object by its title.
	 *
	 * @param string $title The title we use to search.
	 *
	 * @return bool|File
	 */
	public function get_file_by_title( string $title ): bool|File {
		// bail if no title is given.
		if ( empty( $title ) ) {
			return false;
		}

		// query for attachments of this plugin with this title.
		$query  = array(
			'title'          => $title,
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail if not a single result is in array.
		if ( 1 !== $result->post_count ) {
			return false;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( $result->posts[0] );

		// bail if object could not be loaded or is not valid.
		if ( ! ( $external_file_obj && $external_file_obj->is_valid() ) ) {
			return false;
		}

		// return the object.
		return $external_file_obj;
	}

	/**
	 * If file is deleted, delete also its proxy-cache, if set.
	 *
	 * @param int $attachment_id The ID of the attachment.
	 * @return void
	 */
	public function delete_file_from_cache( int $attachment_id ): void {
		// get the external file object.
		$external_file = $this->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file || false === $external_file->is_valid() ) {
			return;
		}

		// call cache file deletion.
		$external_file->delete_thumbs();
		$external_file->delete_cache();
	}

	/**
	 * Return the login.
	 *
	 * @return string
	 */
	private function get_login(): string {
		return $this->login;
	}

	/**
	 * Set the login.
	 *
	 * @param string|null $login The login.
	 *
	 * @return void
	 */
	public function set_login( string|null $login ): void {
		if ( is_null( $login ) ) {
			$login = '';
		}
		$this->login = $login;
	}

	/**
	 * Return the password.
	 *
	 * @return string
	 */
	private function get_password(): string {
		return $this->password;
	}

	/**
	 * Set the password.
	 *
	 * @param string|null $password The password.
	 *
	 * @return void
	 */
	public function set_password( string|null $password ): void {
		if ( is_null( $password ) ) {
			$password = '';
		}
		$this->password = $password;
	}

	/**
	 * Add meta box for external fields on media edit screen.
	 *
	 * @param WP_Post $post The requested post as object.
	 *
	 * @return void
	 */
	public function add_media_box( WP_Post $post ): void {
		// get file by its ID.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return;
		}

		// add the box.
		add_meta_box( 'attachment_external_file', __( 'External file', 'external-files-in-media-library' ), array( $this, 'add_media_box_with_file_info' ), 'attachment', 'side', 'low' );
	}

	/**
	 * Create the content of the meta-box on media-edit-page.
	 *
	 * @param WP_Post $post The requested post as object.
	 *
	 * @return void
	 */
	public function add_media_box_with_file_info( WP_Post $post ): void {
		// get file by its ID.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return;
		}

		// get protocol handler for this URL.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded.
		if ( ! $protocol_handler ) {
			return;
		}

		// get URL for show depending on used protocol.
		$url_to_show = $protocol_handler->get_link();

		// get the unproxied file URL.
		$url = $external_file_obj->get_url( true );

		// output.
		?>
		<div class="misc-pub-external-file">
		<p>
			<?php echo esc_html__( 'External URL of this file:', 'external-files-in-media-library' ); ?><br><a href="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $url ); ?>"><?php echo esc_html( $url_to_show ); ?></a>
		</p>
		</div>
		<ul class="misc-pub-external-file">
		<li>
			<?php
			if ( $external_file_obj->get_availability() ) {
				?>
				<span id="eml_url_file_state"><span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html__( 'File-URL is available.', 'external-files-in-media-library' ); ?></span>
				<?php
			} else {
				$log_url = Helper::get_log_url();
				?>
				<span id="eml_url_file_state"><span class="dashicons dashicons-no-alt"></span>
					<?php
					/* translators: %1$s will be replaced by the URL for the logs */
					echo wp_kses_post( sprintf( __( 'File-URL is NOT available! Check <a href="%1$s">the log</a> for details.', 'external-files-in-media-library' ), esc_url( $log_url ) ) );
					?>
					</span>
				<?php
			}
			if ( $protocol_handler->can_check_availability() ) {
				?>
				<a class="button dashicons dashicons-image-rotate" href="#" id="eml_recheck_availability" title="<?php echo esc_attr__( 'Recheck availability', 'external-files-in-media-library' ); ?>"></a>
				<?php
			}
			?>
		</li>
		<li><span class="dashicons dashicons-yes-alt"></span>
		<?php
		if ( false !== $external_file_obj->is_locally_saved() ) {
			echo '<span class="eml-hosting-state">' . esc_html__( 'File is local hosted.', 'external-files-in-media-library' ) . '</span>';
			if ( $external_file_obj->get_file_type_obj()->is_proxy_enabled() && $protocol_handler->can_change_hosting() ) {
				?>
					<a href="#" class="button dashicons dashicons-controls-repeat eml-change-host" title="<?php echo esc_attr__( 'Switch to extern', 'external-files-in-media-library' ); ?>">&nbsp;</a>
					<?php
			}
		} else {
			echo '<span class="eml-hosting-state">' . esc_html__( 'File is extern hosted.', 'external-files-in-media-library' ) . '</span>';
			if ( $external_file_obj->get_file_type_obj()->is_proxy_enabled() && $protocol_handler->can_change_hosting() ) {
				?>
					<a href="#" class="button dashicons dashicons-controls-repeat eml-change-host" title="<?php echo esc_attr__( 'Switch to local', 'external-files-in-media-library' ); ?>">&nbsp;</a>
					<?php
			}
		}
		?>
		</li>
		<?php
		if ( $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
			?>
			<li>
				<?php
				if ( false !== $external_file_obj->is_cached() ) {
					echo '<span class="dashicons dashicons-yes-alt"></span> ' . esc_html__( 'File is delivered through proxied cache.', 'external-files-in-media-library' );
				} else {
					echo '<span class="dashicons dashicons-no-alt"></span> ' . esc_html__( 'File is not cached in proxy.', 'external-files-in-media-library' );
				}
				?>
			</li>
			<?php
		} else {
			?>
			<li>
				<?php
					echo '<span class="dashicons dashicons-no-alt"></span> ' . esc_html__( 'Proxy is disabled for this file type.', 'external-files-in-media-library' );
				?>
			</li>
			<?php
		}
		if ( $external_file_obj->has_credentials() ) {
			?>
			<li><span class="dashicons dashicons-lock"></span> <?php echo esc_html__( 'File is protected with login and password.', 'external-files-in-media-library' ); ?></li>
			<?php
		}
		?>
		<li><span class="dashicons dashicons-list-view"></span> <a href="<?php echo esc_url( Helper::get_log_url( $url ) ); ?>"><?php echo esc_html__( 'Show log entries', 'external-files-in-media-library' ); ?></a></li>
		<?php
		if ( $external_file_obj->get_file_type_obj()->has_thumbs() ) {
			?>
			<li><span class="dashicons dashicons-images-alt"></span> <a href="<?php echo esc_url( $this->get_thumbnail_reset_url( $external_file_obj ) ); ?>"><?php echo esc_html__( 'Reset thumbnails', 'external-files-in-media-library' ); ?></a></li>
			<?php
		}
		?>
			<li><span class="dashicons dashicons-info"></span> <?php echo esc_html__( 'Mime type:', 'external-files-in-media-library' ); ?> <code><?php echo esc_html( $external_file_obj->get_mime_type() ); ?></code></li>
		</ul>
		<?php
	}

	/**
	 * Check file availability via AJAX request.
	 *
	 * @return       void
	 */
	public function check_file_availability_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-availability-check-nonce', 'nonce' );

		// create error-result.
		$result = array(
			'state'   => 'error',
			'message' => __( 'No ID given.', 'external-files-in-media-library' ),
		);

		// get ID.
		$attachment_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// bail if no file is given.
		if ( 0 === $attachment_id ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// get the single external file-object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// get protocol handler for this url.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler ) {
			// send response as JSON.
			wp_send_json( $result );
		}

		// check and save its availability.
		$external_file_obj->set_availability( $protocol_handler->check_availability( $external_file_obj->get_url() ) );

		// return result depending on availability-value.
		if ( $external_file_obj->get_availability() ) {
			$result = array(
				'state'   => 'success',
				'message' => __( 'File-URL is available.', 'external-files-in-media-library' ),
			);

			// send response as JSON.
			wp_send_json( $result );
		}

		// return error if file is not available.
		$result = array(
			'state'   => 'error',
			/* translators: %1$s will be replaced by the URL for the logs */
			'message' => sprintf( __( 'URL-File is NOT available! Check <a href="%1$s">the log</a> for details.', 'external-files-in-media-library' ), Helper::get_log_url() ),
		);

		// send response as JSON.
		wp_send_json( $result );
	}

	/**
	 * URL-decode the file-title if it is used in admin (via AJAX).
	 * Also sanitize the filename for full compatibility with requirements (incl. file extension).
	 *
	 * @param string $title The title to optimize.
	 * @param string $url The used URL.
	 * @param array  $file_data The file data.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function optimize_file_title( string $title, string $url, array $file_data ): string {
		$title = sanitize_file_name( urldecode( $title ) );

		// bail if title does contain an "." as it seems to be have a file extension.
		if ( false !== str_contains( $title, '.' ) ) {
			return $title;
		}

		// get all possible mime-types.
		$mime_types = Helper::get_possible_mime_types();

		// bail with half title if mime type is unknown.
		if ( empty( $mime_types[ $file_data['mime-type'] ] ) ) {
			return $title;
		}

		// return title with added file extension.
		return $title . '.' . $mime_types[ $file_data['mime-type'] ]['ext'];
	}

	/**
	 * Switch the hosting of a single file from local to extern or extern to local.
	 *
	 * @return       void
	 */
	public function switch_hosting_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-switch-hosting-nonce', 'nonce' );

		// create error-result.
		$result = array(
			'state'   => 'error',
			'message' => __( 'No ID given.', 'external-files-in-media-library' ),
		);

		// get ID.
		$attachment_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		// bail if id is not given.
		if ( 0 === $attachment_id ) {
			wp_send_json( $result );
		}

		// get the file.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			wp_send_json( $result );
		}

		// get the external URL.
		$url = $external_file_obj->get_url( true );

		// bail if file is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			$result = array(
				'state'   => 'error',
				'message' => __( 'Given file is not an external file.', 'external-files-in-media-library' ),
			);
			wp_send_json( $result );
		}

		/**
		 * Switch from local to external.
		 */
		if ( $external_file_obj->is_locally_saved() ) {
			// switch to external and show error if it runs in an error.
			if ( ! $external_file_obj->switch_to_external() ) {
				$result['message'] = __( 'Error during switch to external hosting.', 'external-files-in-media-library' );
				wp_send_json( $result );
			}

			// create return message.
			$result = array(
				'state'   => 'success',
				'message' => __( 'File is now extern hosted.', 'external-files-in-media-library' ),
			);
		} else {
			/**
			 * Switch from external to local.
			 */

			// switch to local and show error if it runs in an error.
			if ( ! $external_file_obj->switch_to_local() ) {
				$result['message'] = __( 'Error during switch to local hosting.', 'external-files-in-media-library' );
				wp_send_json( $result );
			}

			// create return message.
			$result = array(
				'state'   => 'success',
				'message' => __( 'File is local hosted.', 'external-files-in-media-library' ),
			);
		}

		// log this event.
		/* translators: %1$s will be replaced by the file URL. */
		Log::get_instance()->create( __( 'File has been switched the hosting.', 'external-files-in-media-library' ), $url, 'success', 0 );

		// send response as JSON.
		wp_send_json( $result );
	}

	/**
	 * Change media row actions for URL-files.
	 *
	 * @param array   $actions List of action.
	 * @param WP_Post $post The Post.
	 *
	 * @return array
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// get the external file object.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $actions;
		}

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// if file is not available, show hint as action.
		if ( false === $external_file_obj->get_availability() ) {
			// remove actions if file is not available.
			unset( $actions['edit'] );
			unset( $actions['copy'] );
			unset( $actions['download'] );

			// add custom hint.
			$actions['eml-hint'] = '<a href="' . esc_url( Helper::get_config_url() ) . '">' . __( 'Mime-Type not allowed', 'external-files-in-media-library' ) . '</a>';
		}

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * Prevent output as file if availability is not given.
	 *
	 * @param string $file The file.
	 * @param int    $post_id The post-ID.
	 *
	 * @return string
	 */
	public function get_attached_file( string $file, int $post_id ): string {
		// get the external file object.
		$external_file_obj = $this->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $file;
		}

		// return nothing to prevent output as file is not valid.
		if ( false === $external_file_obj->get_availability() ) {
			return '';
		}

		// return normal file-name.
		return $file;
	}

	/**
	 * Prevent image downsizing for external hosted images.
	 *
	 * @param array|bool   $result        The resulting array with image-data.
	 * @param int|string   $attachment_id The attachment ID.
	 * @param array|string $size               The requested size.
	 *
	 * @return bool|array
	 */
	public function image_downsize( array|bool $result, int|string $attachment_id, array|string $size ): bool|array {
		// get the external file object.
		$external_file_obj = $this->get_file( absint( $attachment_id ) );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $result;
		}

		// bail if file type has no thumb support.
		if ( ! $external_file_obj->get_file_type_obj()->has_thumbs() ) {
			return $result;
		}

		// if requested size is a string, get its sizes.
		if ( is_string( $size ) ) {
			$size = array(
				absint( get_option( $size . '_size_w' ) ),
				absint( get_option( $size . '_size_h' ) ),
			);
		}

		// if file type has proxy not enabled we just return the original URL with requested sizes.
		if ( ! $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
			return array(
				$external_file_obj->get_url( true ),
				$size[0],
				$size[1],
			);
		}

		// bail if file is locally saved.
		if ( $external_file_obj->is_locally_saved() ) {
			return $result;
		}

		// get image data.
		$image_data = wp_get_attachment_metadata( $attachment_id );

		// if image data is fall, create the array manually.
		if ( ! $image_data ) {
			$image_data = array(
				'sizes'  => array(),
				'width'  => 0,
				'height' => 0,
			);
		}

		// bail if both sizes are 0.
		if ( 0 === $size[0] && 0 === $size[1] ) {
			// set return-array so that WP won't generate an image for it.
			return array(
				$external_file_obj->get_url(),
				isset( $image_data['width'] ) ? $image_data['width'] : 0,
				isset( $image_data['height'] ) ? $image_data['height'] : 0,
				false,
			);
		}

		// generate the filename for the thumb.
		$generated_filename = Helper::generate_sizes_filename( basename( $external_file_obj->get_cache_file() ), $size[0], $size[1], $external_file_obj->get_file_extension() );

		// public filename.
		$public_filename = Helper::generate_sizes_filename( basename( $external_file_obj->get_url() ), $size[0], $size[1], $external_file_obj->get_file_extension() );

		// use already existing thumb.
		if ( ! empty( $image_data['sizes'][ $size[0] . 'x' . $size[1] ] ) && file_exists( Proxy::get_instance()->get_cache_directory() . $generated_filename ) ) {
			// log the event.
			/* translators: %1$s will be replaced by the image sizes. */
			Log::get_instance()->create( sprintf( __( 'Loading thumb from cache for %1$s', 'external-files-in-media-library' ), $size[0] . 'x' . $size[1] ), $external_file_obj->get_url( true ), 'info', 2 );

			// return the thumb.
			return array(
				trailingslashit( get_home_url() ) . Proxy::get_instance()->get_slug() . '/' . $public_filename,
				$size[0],
				$size[1],
				false,
			);
		}

		// get image editor as object.
		$image_editor = wp_get_image_editor( $external_file_obj->get_cache_file() );

		// on error return the original image.
		if ( is_wp_error( $image_editor ) ) {
			// set return-array so that WP won't generate an image for it.
			return array(
				$external_file_obj->get_url(),
				isset( $image_data['width'] ) ? $image_data['width'] : 0,
				isset( $image_data['height'] ) ? $image_data['height'] : 0,
				false,
			);
		}

		/**
		 * Generate the requested thumb and save it in metadata for the image.
		 */

		// resize the image.
		$image_editor->resize( $size[0], $size[1], true );

		// save the resized image and get its data.
		$new_image_data = $image_editor->save( Proxy::get_instance()->get_cache_directory() . $generated_filename );

		// remove the path from the resized image data.
		unset( $new_image_data['path'] );

		// replace the filename in the resized image data with the public filename we use in our proxy.
		$new_image_data['file'] = $public_filename;

		// update the meta data.
		$image_data['sizes'][ $size[0] . 'x' . $size[1] ] = $new_image_data;
		wp_update_attachment_metadata( $attachment_id, $image_data );

		// log the event.
		/* translators: %1$s will be replaced by the image sizes. */
		Log::get_instance()->create( sprintf( __( 'Generated new thumb for %1$s', 'external-files-in-media-library' ), $size[0] . 'x' . $size[1] ), $external_file_obj->get_url( true ), 'info', 2 );

		// return the thumb.
		return array(
			trailingslashit( get_home_url() ) . Proxy::get_instance()->get_slug() . '/' . $public_filename,
			$size[0],
			$size[1],
			false,
		);
	}

	/**
	 * Set URL of all imported external files and remove the import-marker.
	 *
	 * @return void
	 */
	public function import_end(): void {
		// loop through all imported external files and update their wp_attached_file-setting.
		foreach ( $this->get_imported_external_files() as $external_file ) {
			update_post_meta( $external_file->get_id(), '_wp_attached_file', $external_file->get_url() );
		}

		// get all imported external files attachments.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'   => EFML_POST_IMPORT_MARKER,
					'value' => 1,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail if no results found.
		if ( 0 === $result->post_count ) {
			return;
		}

		// delete the import marker for each of these files.
		foreach ( $result->get_posts() as $attachment_id ) {
			delete_post_meta( $attachment_id, EFML_POST_IMPORT_MARKER );
		}
	}

	/**
	 * Disable attachment-pages for external files.
	 *
	 * @param string $redirect_url The redirect URL to use.
	 *
	 * @return string
	 */
	public function disable_attachment_page( string $redirect_url ): string {
		// bail if this is not an attachment page.
		if ( false === is_attachment() ) {
			return $redirect_url;
		}

		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_disable_attachment_pages', 0 ) ) ) {
			return $redirect_url;
		}

		// get the external files.
		$external_file_obj = $this->get_file( get_the_ID() );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $redirect_url;
		}

		// return 404 page.
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );

		return $redirect_url;
	}

	/**
	 * Change the URL in srcset-attribute for each attachment.
	 *
	 * @param array  $sources Array with srcset-data if the image.
	 * @param array  $size_array Array with sizes for images.
	 * @param string $image_src The src of the image.
	 * @param array  $image_meta The image meta-data.
	 * @param int    $attachment_id The attachment-ID.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_image_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $sources;
		}

		// bail with empty array if this file type supports thumbs and is external hosted.
		if (
			false === $external_file_obj->is_locally_saved()
			&& $external_file_obj->get_file_type_obj()->has_thumbs()
		) {
			// return empty array as we can not optimize external images.
			return array();
		}

		// return resulting array.
		return $sources;
	}

	/**
	 * Force permalink-URL for file-attribute in meta-data for external URL-files
	 * to change the link-target if attachment-pages are disabled via attachment_link-hook.
	 *
	 * @param array $data The image-data.
	 * @param int   $attachment_id The attachment-ID.
	 *
	 * @return array
	 */
	public function get_attachment_metadata( array $data, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $data;
		}

		// set permalink as file.
		$data['file'] = get_permalink( $attachment_id );

		// return resulting data array.
		return $data;
	}

	/**
	 * Set the import-marker for all attachments.
	 *
	 * @param array $post_meta The attachment-meta.
	 * @param int   $post_id The attachment-ID.
	 *
	 * @return array
	 */
	public function set_import_marker_for_attachments( array $post_meta, int $post_id ): array {
		// bail if this is not an attachment.
		if ( 'attachment' !== get_post_type( $post_id ) ) {
			return $post_meta;
		}

		// update the meta query.
		$post_meta[] = array(
			'key'   => 'eml_imported',
			'value' => 1,
		);

		// return resulting meta query.
		return $post_meta;
	}

	/**
	 * Set the file title.
	 *
	 * @param string $title The title.
	 * @param string $url   The used URL.
	 * @param array  $file_data The file data.
	 *
	 * @return string
	 */
	public function set_file_title( string $title, string $url, array $file_data ): string {
		// bail if title is set.
		if ( ! empty( $title ) ) {
			return $title;
		}

		// get URL data.
		$url_info = wp_parse_url( $url );

		// bail if url_info is empty.
		if ( empty( $url_info ) ) {
			return $title;
		}

		// get all possible mime-types our plugin supports.
		$mime_types = Helper::get_possible_mime_types();

		// get basename of path, if available.
		$title = basename( $url_info['path'] );

		// add file extension if we support the mime-type and if the title does not have any atm.
		if ( empty( pathinfo( $title, PATHINFO_EXTENSION ) ) && ! empty( $mime_types[ $file_data['mime-type'] ] ) ) {
			$title .= '.' . $mime_types[ $file_data['mime-type'] ]['ext'];
		}

		// return resulting list of file data.
		return $title;
	}

	/**
	 * Return the thumbnail reset URL for single external file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return string
	 */
	private function get_thumbnail_reset_url( File $external_file_obj ): string {
		return add_query_arg(
			array(
				'action' => 'eml_reset_thumbnails',
				'post'   => $external_file_obj->get_id(),
				'nonce'  => wp_create_nonce( 'eml-reset-thumbnails' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Reset thumbnails if single file by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function reset_thumbnails_by_request(): void {
		// check referer.
		check_admin_referer( 'eml-reset-thumbnails', 'nonce' );

		// get the file id.
		$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if post id is not given.
		if ( 0 === $post_id ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// delete the thumbs of this file.
		$external_file_obj->delete_thumbs();

		// generate the thumbs.

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Parse the content by HTML-links to get their href-values.
	 *
	 * @param array  $matches The matches.
	 * @param string $content The content to parse.
	 *
	 * @return array
	 */
	public function use_link_regex( array $matches, string $content ): array {
		// parse all links in the given content to get their URLs.
		if ( 0 < preg_match_all( "<a href=\x22(.+?)\x22>", $content, $my_matches ) ) {
			return $my_matches;
		}

		// return empty results.
		return $matches;
	}

	/**
	 * Return the import start time.
	 *
	 * @return float
	 */
	private function get_import_start_time(): float {
		return $this->start_time;
	}

	/**
	 * Set the start time.
	 *
	 * @return void
	 */
	private function set_import_start_time(): void {
		$this->start_time = microtime( true );
	}

	/**
	 * Check runtime of the actual PHP-process.
	 *
	 * If it is nearly the configured max_execution_time kill itself.
	 *
	 * In addition to max_execution_time, there are other timeouts in PHP that we cannot access.
	 * Therefore, this is only an approximation of the possible environment.
	 *
	 * As soon as the assumed maximum value is reached, all other URLs in the run are placed
	 * in the queue and the import is aborted by killing the PHP process.
	 *
	 * @param string $url The actual processed file URL.
	 * @param array  $file_list List of files to process.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_runtime( string $url, array $file_list ): void {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_max_execution_check' ) ) ) {
			return;
		}

		// get max_execution_time setting.
		$max_execution_time = absint( ini_get( 'max_execution_time' ) ); // in seconds.

		// bail if max_execution_time is 0 or -1 (e.g. via WP CLI).
		if ( $max_execution_time <= 0 ) {
			return;
		}

		// get the actual runtime.
		$runtime = microtime( true ) - $this->get_import_start_time();

		// cancel process if runtime is nearly reached.
		if ( (float) $runtime >= $max_execution_time ) {
			// add files to queue.
			Queue::get_instance()->add_urls( $file_list, $this->get_login(), $this->get_password() );

			// log the event.
			Log::get_instance()->create( __( 'Import process was terminated because it took too long and would have reached the maximum execution time in hosting. The files to be imported were saved in the queue and are now automatically imported individually.', 'external-files-in-media-library' ), '', 'info', 2 );

			// kill process.
			exit;
		}
	}

	/**
	 * Add help for the settings of this plugin.
	 *
	 * @param array $help_list List of help tabs.
	 *
	 * @return array
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Upload external files', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'The plugin allows you to integrate external files into your media library. These are then handled in exactly the same way as other files that you upload here. You can integrate them into your website as you are used to.', 'external-files-in-media-library' ) . '</p>';
		$content .= '<h3>' . __( 'How to use', 'external-files-in-media-library' ) . '</h3>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<ol><li>' . sprintf( __( 'Go to Media > <a href="%1$s">New file</a>.', 'external-files-in-media-library' ), esc_url( add_query_arg( array(), get_admin_url() . 'media-new.php' ) ) ) . '</li>';
		$content .= '<li>' . __( 'Click on the button "Add external files".', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Paste the URLs you want to add in the field in the new dialog.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Optionally add credentials below the field.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Click on "Add URLs".', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Wait until you get an answer.', 'external-files-in-media-library' ) . '</li>';
		$content .= '<li>' . __( 'Take a look at your added external files in the media library.', 'external-files-in-media-library' ) . '</li>';
		$content .= '</ol>';

		// add help for the settings of this plugin.
		$help_list[] = array(
			'id'      => 'eml-upload',
			'title'   => __( 'Upload external files', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Check the srcset metadata for external files. Remove 'file' entry if file could not have thumbs
	 * as this results in possible warnings via @media.php.
	 *
	 * @param array  $image_meta The meta data.
	 * @param array  $size_array The size array.
	 * @param string $image_src The src.
	 * @param int    $attachment_id The attachment id.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_srcset_meta( array $image_meta, array $size_array, string $image_src, int $attachment_id ): array {
		// get external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! ( false !== $external_file_obj && false !== $external_file_obj->is_valid() ) ) {
			return $image_meta;
		}

		// bail if given file could have thumbs.
		if ( $external_file_obj->get_file_type_obj()->has_thumbs() ) {
			return $image_meta;
		}

		// remove the file-setting from array.
		unset( $image_meta['file'] );

		// return resulting meta.
		return $image_meta;
	}

	/**
	 * Add file date to post array to set the date of the external file.
	 *
	 * @param array  $post_array The attachment settings.
	 * @param string $url        The requested external URL.
	 * @param array  $file_data  List of file settings detected by importer.
	 *
	 * @return array
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_file_date( array $post_array, string $url, array $file_data ): array {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_use_file_dates' ) ) ) {
			return $post_array;
		}

		// bail if no last-modified is given.
		if ( empty( $file_data['last-modified'] ) ) {
			return $post_array;
		}

		// add the last-modified date.
		$post_array['post_date'] = gmdate( 'Y-m-d H:i:s', $file_data['last-modified'] );

		// return the resulting array.
		return $post_array;
	}
}
