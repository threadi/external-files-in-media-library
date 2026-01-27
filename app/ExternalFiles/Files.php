<?php
/**
 * This file contains the main controller-object to handle external files operations.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Image;
use ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Pdf;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Controller for external files-tasks.
 */
class Files {
	/**
	 * Instance of actual object.
	 *
	 * @var Files|null
	 */
	private static ?Files $instance = null;

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
		// initialize the import.
		Import::get_instance()->init();

		// initialize the synchronization.
		Synchronization::get_instance()->init();

		// initialize the export support.
		Export::get_instance()->init();

		// initialize REST API support.
		Rest::get_instance()->init();

		// initialize the file types support.
		File_Types::get_instance()->init();

		// initialize the file handling extensions.
		Extensions::get_instance()->init();

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
		add_filter( 'media_meta', array( $this, 'show_media_info_in_modal' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'prevent_images' ), 10, 2 );

		// add ajax hooks.
		add_action( 'wp_ajax_eml_switch_hosting', array( $this, 'switch_hosting_via_ajax' ), 10, 0 );

		// use our own hooks.
		add_filter( 'efml_http_directory_regex', array( $this, 'use_link_regex' ), 10, 2 );
		add_filter( 'efml_help_tabs', array( $this, 'add_help' ), 20 );
		add_filter( 'efml_external_file_infos', array( $this, 'prevent_not_allowed_mime_type' ), 10, 2 );
		add_filter( 'efml_filter_options', array( $this, 'add_filter_options' ) );
		add_action( 'efml_filter_query', array( $this, 'use_filter_options' ) );
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_external_source' ), 10, 2 );
		add_filter( 'efml_table_column_source_title', array( $this, 'get_external_source_title' ), 10, 2 );
		add_action( 'efml_show_file_info', array( $this, 'show_external_source_info' ) );
		add_filter( 'efml_add_url', array( $this, 'add_urls_by_hook' ), 10, 3 );

		// add admin actions.
		add_action( 'admin_action_eml_reset_thumbnails', array( $this, 'reset_thumbnails_by_request' ) );
	}

	/**
	 * Return the URL of an external file.
	 *
	 * @param string $url               The URL.
	 * @param int    $attachment_id     The attachment-ID.
	 *
	 * @return string
	 */
	public function get_attachment_url( string $url, int $attachment_id ): string {
		// get external file object by given attachment ID.
		$external_file_obj = $this->get_file( $attachment_id );

		// return the original URL if this URL-file is not valid or not available or is using a not allowed mime type.
		if ( false === $external_file_obj->is_valid() || false === $external_file_obj->is_available() || false === $external_file_obj->is_mime_type_allowed() ) {
			return $url;
		}

		// use local URL if the URL has been locally saved.
		if ( false !== $external_file_obj->is_locally_saved() ) {
			// get upload directory settings.
			$upload_dir = wp_get_upload_dir();

			// bail if any error occurred.
			if ( false !== $upload_dir['error'] ) {
				return $url;
			}

			// bail if baseurl is not a string.
			if ( ! is_string( $upload_dir['baseurl'] ) ) {
				return $url;
			}

			// return our own attachment URL.
			return trailingslashit( $upload_dir['baseurl'] ) . $external_file_obj->get_attachment_url();
		}

		// return the extern URL.
		return $external_file_obj->get_url();
	}

	/**
	 * Disable attachment-page-links for external files, if this is enabled.
	 *
	 * @param string $url               The URL.
	 * @param ?int   $attachment_id     The attachment ID.
	 *
	 * @return string
	 */
	public function get_attachment_link( string $url, ?int $attachment_id ): string {
		// show deprecated warning for the old hook name.
		$false = apply_filters_deprecated( 'eml_attachment_link', array( false, $url, $attachment_id ), '5.0.0', 'efml_attachment_link' );

		/**
		 * Filter if attachment link should not be changed.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $false True if URL should not be changed.
		 * @param string $url The given URL.
		 * @param ?int $attachment_id The ID of the attachment.
		 */
		if ( false !== apply_filters( 'efml_attachment_link', $false, $url, $attachment_id ) ) {
			return $url;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( absint( $attachment_id ) );

		// bail if file is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return $url;
		}

		// bail if attachment pages are not disabled.
		if ( 0 === absint( get_option( 'eml_disable_attachment_pages', 0 ) ) ) {
			return $url;
		}

		// return the external URL (in WP-admin not proxied).
		return $external_file_obj->get_url( is_admin() );
	}

	/**
	 * Return all external files in media library.
	 *
	 * @return array<int,File>
	 */
	public function get_files(): array {
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		/**
		 * Filter the query to load all external files.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<string,mixed> $query The query.
		 */
		$query = apply_filters( 'efml_files_query', $query );

		// run the query.
		$result = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $result->post_count ) {
			return array();
		}

		// collect the results.
		$results = array();

		// loop through them.
		foreach ( $result->get_posts() as $attachment_id ) {
			// bail if attachment_id is not an ID.
			if ( ! is_int( $attachment_id ) ) {
				continue;
			}

			// get the object of the external file.
			$external_file_obj = $this->get_file( $attachment_id );

			// bail if object is not an external file.
			if ( ! $external_file_obj->is_valid() ) {
				continue;
			}

			// add the object to the list.
			$results[] = $external_file_obj;
		}

		// return the resulting list.
		return $results;
	}

	/**
	 * Log the deletion of external URLs in media library.
	 *
	 * @param int $attachment_id  The attachment_id which will be deleted.
	 *
	 * @return void
	 */
	public function log_url_deletion( int $attachment_id ): void {
		// get the external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// show deprecated warning for the old hook name.
		do_action_deprecated( 'eml_file_delete', array( $external_file_obj ), '5.0.0', 'efml_file_delete' );

		/**
		 * Run additional tasks for URL deletion.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param File $external_file_obj The object, which has been deleted.
		 */
		do_action( 'efml_file_delete', $external_file_obj );

		// log deletion.
		Log::get_instance()->create( __( 'URL has been deleted from media library.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'success', 1 );
	}

	/**
	 * Return external file object of single attachment by given ID without checking its validity.
	 *
	 * @param int $attachment_id    The attachment_id where we want to call the File-object.
	 *
	 * @return File
	 */
	public function get_file( int $attachment_id ): File {
		return new File( $attachment_id );
	}

	/**
	 * Return external file object by a given URL.
	 * Also checks for validity of the object.
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
			'post_status'    => array( 'inherit', 'trash' ),
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

		// bail if none has been found.
		if ( 0 === $result->post_count ) {
			return false;
		}

		// get the external file object for the match.
		$external_file_obj = $this->get_file( absint( $result->get_posts()[0] ) );

		// bail if the external file object is not valid.
		if ( ! $external_file_obj->is_valid() ) {
			return false;
		}

		// return the object.
		return $external_file_obj;
	}

	/**
	 * Return external file object by its title.
	 * Also checks for validity of the object.
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
			'post_status'    => array( 'inherit', 'trash' ),
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

		// bail if not a single result is in the array.
		if ( 1 !== $result->post_count ) {
			return false;
		}

		// get the first entry.
		$attachment_id = absint( $result->posts[0] );

		// get the external file object for this attachment.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if external file object is not valid.
		if ( ! $external_file_obj->is_valid() ) {
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
		if ( ! $external_file->is_valid() ) {
			return;
		}

		// call cache file deletion.
		$external_file->delete_thumbs();
		$external_file->delete_cache();
	}

	/**
	 * Add meta box for external fields on media edit screen.
	 *
	 * @param WP_Post $post The requested post as object.
	 *
	 * @return void
	 */
	public function add_media_box( WP_Post $post ): void {
		// bail if user has not the capability.
		if( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// get file by its ID.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
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
		if ( ! $external_file_obj->is_valid() ) {
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
			<?php echo esc_html__( 'External URL of this file:', 'external-files-in-media-library' ); ?><br>
			<?php
			if ( ! empty( esc_url( $url ) ) ) {
				?>
					<a href="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $url ); ?>" target="_blank"><?php echo esc_html( $url_to_show ); ?></a>
				<?php
			} else {
				echo '<code>' . esc_html( $url ) . '</code>';
			}
			?>
		</p>
		</div>
		<ul class="misc-pub-external-file">
			<?php
			$date = $external_file_obj->get_date();
			if ( ! empty( $date ) ) {
				?>
			<li>
				<span class="dashicons dashicons-clock"></span> <?php echo esc_html__( 'Imported at', 'external-files-in-media-library' ) . ' <code>' . esc_html( $date ); ?></code>
			</li>
				<?php
			}
			?>
		<li><span class="dashicons dashicons-yes-alt"></span>
		<?php
		if ( false !== $external_file_obj->is_locally_saved() ) {
			echo '<span class="eml-hosting-state">' . esc_html__( 'File is locally hosted.', 'external-files-in-media-library' ) . '</span>';
			if ( current_user_can( EFML_CAP_NAME ) && $protocol_handler->can_change_hosting() && $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
				?>
					<a href="#" class="button dashicons dashicons-controls-repeat eml-change-host" title="<?php echo esc_attr__( 'Switch to extern', 'external-files-in-media-library' ); ?>">&nbsp;</a>
					<?php
			}
		} else {
			echo '<span class="eml-hosting-state">' . esc_html__( 'File is extern hosted.', 'external-files-in-media-library' ) . '</span>';
			if ( current_user_can( EFML_CAP_NAME ) && $protocol_handler->can_change_hosting() && $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
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
			<li><span class="dashicons dashicons-lock"></span> <?php echo esc_html__( 'The external file is protected with login and password.', 'external-files-in-media-library' ); ?></li>
			<?php
		}
		if ( current_user_can( 'manage_options' ) ) {
			?>
			<li><span class="dashicons dashicons-list-view"></span> <a href="<?php echo esc_url( Helper::get_log_url( $url ) ); ?>"><?php echo esc_html__( 'Show log entries', 'external-files-in-media-library' ); ?></a></li>
			<?php
		}
		if ( current_user_can( EFML_CAP_NAME ) && $external_file_obj->get_file_type_obj()->has_thumbs() ) {
			?>
			<li><span class="dashicons dashicons-images-alt"></span> <a href="<?php echo esc_url( $external_file_obj->get_thumbnail_reset_url() ); ?>"><?php echo esc_html__( 'Reset thumbnails', 'external-files-in-media-library' ); ?></a></li>
			<?php
		}
		?>
			<li><span class="dashicons dashicons-info"></span> <?php echo esc_html__( 'Mime type:', 'external-files-in-media-library' ); ?><br><code><?php echo esc_html( $external_file_obj->get_mime_type() ); ?></code></li>
			<?php

			// show deprecated warning for the old hook name.
			do_action_deprecated( 'eml_show_file_info', array( $external_file_obj ), '5.0.0', 'efml_show_file_info' );

			/**
			 * Add additional infos about this file.
			 *
			 * @since 4.0.0 Available since 4.0.0.
			 * @param File $external_file_obj The external file object.
			 */
			do_action( 'efml_show_file_info', $external_file_obj );
			?>
		</ul>
		<?php
	}

	/**
	 * Switch the hosting of a single file from "local to extern" or "extern to local".
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
		$attachment_id = absint( filter_input( INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if ID is not given.
		if ( 0 === $attachment_id ) {
			wp_send_json( $result );
		}

		// get the file.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			$result = array(
				'state'   => 'error',
				'message' => __( 'Given file is not an external file.', 'external-files-in-media-library' ),
			);
			wp_send_json( $result );
		}

		// get the external URL.
		$url = $external_file_obj->get_url( true );

		/**
		 * Switch from local to external.
		 */
		if ( $external_file_obj->is_locally_saved() ) {
			// switch to external and show error if it runs in an error.
			if ( ! $external_file_obj->switch_to_external() ) {
				$result['message'] = __( 'Error during the switch to external hosting.', 'external-files-in-media-library' );
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
				$result['message'] = __( 'Error during the switch to local hosting.', 'external-files-in-media-library' );
				wp_send_json( $result );
			}

			// create return message.
			$result = array(
				'state'   => 'success',
				'message' => __( 'File is locally hosted.', 'external-files-in-media-library' ),
			);
		}

		// log this event.
		/* translators: %1$s will be replaced by the file URL. */
		Log::get_instance()->create( __( 'File has been switched the hosting.', 'external-files-in-media-library' ), $url, 'success' );

		// send response as JSON.
		wp_send_json( $result );
	}

	/**
	 * Change media row actions for URL-files.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// get the external file object.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// if file is not available, remove actions and show hint.
		if ( false === $external_file_obj->is_available() ) {
			// remove actions if file is not available.
			if ( ! empty( $actions['edit'] ) ) {
				unset( $actions['edit'] ); }
			if ( ! empty( $actions['copy'] ) ) {
				unset( $actions['copy'] ); }
			if ( ! empty( $actions['download'] ) ) {
				unset( $actions['download'] ); }

			// add a custom hint.
			$actions['eml-hint-availability'] = __( 'The external file is NOT available', 'external-files-in-media-library' );
		}

		// if file is using a not allowed mime type, remove action and show hint.
		if ( false === $external_file_obj->is_mime_type_allowed() ) {
			// remove actions if file mime type is not allowed.
			if ( ! empty( $actions['edit'] ) ) {
				unset( $actions['edit'] ); }
			if ( ! empty( $actions['copy'] ) ) {
				unset( $actions['copy'] ); }
			if ( ! empty( $actions['download'] ) ) {
				unset( $actions['download'] ); }

			// add a custom hint depending on capabilities.
			if ( current_user_can( 'manage_options' ) ) {
				$actions['eml-hint-mime'] = '<a href="' . esc_url( Helper::get_config_url() ) . '">' . __( 'Mime-type is not allowed', 'external-files-in-media-library' ) . '</a>';
			} else {
				$actions['eml-hint-mime'] = __( 'Mime-type is not allowed', 'external-files-in-media-library' );
			}
		}

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * Prevent output as external file if availability is not given.
	 *
	 * @source https://developer.wordpress.org/reference/hooks/get_attached_file/
	 *
	 * @param false|string $file The file.
	 * @param int          $post_id The post-ID.
	 *
	 * @return false|string
	 */
	public function get_attached_file( false|string $file, int $post_id ): false|string {
		// get the external file object.
		$external_file_obj = $this->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $file;
		}

		// return nothing to prevent output as file is not available and extern hosted OR is using a not allowed mime type.
		if ( false === $external_file_obj->is_mime_type_allowed() || ( false === $external_file_obj->is_available() && ! $external_file_obj->is_locally_saved() ) ) {
			return false;
		}

		// if file is extern hosted, remove the upload basedir-path before the URL.
		if ( is_string( $file ) && ! $external_file_obj->is_locally_saved() ) {
			$uploads = wp_get_upload_dir();
			$file    = str_replace( $uploads['basedir'] . '/', '', $file );
		}

		// if the basename of the file does not contain a ".", use the file title in the backend.
		if ( is_admin() && ! strpos( basename( (string) $file ), '.' ) ) {
			$file = get_the_title( $post_id );
		}

		// return normal file-name.
		return $file;
	}

	/**
	 * Provide image downsizing for external images.
	 *
	 * @source https://developer.wordpress.org/reference/hooks/image_downsize/
	 *
	 * @param bool|array<int,mixed> $result        The resulting array with image-data.
	 * @param int|string            $attachment_id The attachment ID (also as string for compatibility with other plugins).
	 * @param array<int>|string     $size               The requested size.
	 *
	 * @return bool|array<int,mixed>
	 */
	public function image_downsize( array|bool $result, int|string $attachment_id, array|string $size ): bool|array {
		// get the external file object.
		$external_file_obj = $this->get_file( absint( $attachment_id ) );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
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

		// if file type has proxy not enabled and this file is not saved locally,
		// we just return the original URL with requested sizes.
		if ( ! $external_file_obj->is_locally_saved() && ! $external_file_obj->get_file_type_obj()->is_proxy_enabled() ) {
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
		$image_data = (array) wp_get_attachment_metadata( absint( $attachment_id ) );

		// if image data is empty, create the array manually.
		if ( empty( $image_data['sizes'] ) && empty( $image_data['width'] ) && empty( $image_data['height'] ) ) {
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
				absint( $image_data['width'] ),
				absint( $image_data['height'] ),
				false,
			);
		}

		// generate the filename for the thumb.
		$generated_filename = Helper::generate_sizes_filename( basename( $external_file_obj->get_cache_file() ), $size[0], $size[1], $external_file_obj->get_file_extension() );

		// the public filename.
		$public_filename = Helper::generate_sizes_filename( basename( $external_file_obj->get_url() ), $size[0], $size[1], $external_file_obj->get_file_extension() );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// use the already existing thumb.
		if ( ! empty( $image_data['sizes'][ $size[0] . 'x' . $size[1] ] ) && $wp_filesystem->exists( Proxy::get_instance()->get_cache_directory() . $generated_filename ) ) {
			// log the event.
			/* translators: %1$s will be replaced by the image sizes. */
			Log::get_instance()->create( sprintf( __( 'Loading the thumb from cache for %1$s', 'external-files-in-media-library' ), $size[0] . 'x' . $size[1] ), $external_file_obj->get_url( true ), 'info', 2 );

			// return the thumb.
			return array(
				trailingslashit( get_home_url() ) . Proxy::get_instance()->get_slug() . '/' . $public_filename,
				absint( $size[0] ),
				absint( $size[1] ),
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
				absint( $image_data['width'] ),
				absint( $image_data['height'] ),
				false,
			);
		}

		/**
		 * Generate the requested thumb and save it in metadata for the image.
		 */

		// if sizes are the same, set height to 0 to prevent scaled images.
		if ( $size[0] === $size[1] ) {
			$size[1] = 0;
		}

		// resize the image.
		$image_editor->resize( absint( $size[0] ), absint( $size[1] ) );

		// save the resized image and get its data.
		$new_image_data = $image_editor->save( Proxy::get_instance()->get_cache_directory() . $generated_filename );

		// bail if result is not an array.
		if ( ! is_array( $new_image_data ) ) {
			return array(
				$external_file_obj->get_url(),
				absint( $image_data['width'] ),
				absint( $image_data['height'] ),
				false,
			);
		}

		// remove the path from the resized image data.
		unset( $new_image_data['path'] );

		// replace the filename in the resized image data with the public filename we use in our proxy.
		$new_image_data['file'] = $public_filename;

		// update the meta data.
		$image_data['sizes'][ $size[0] . 'x' . $size[1] ] = $new_image_data;
		wp_update_attachment_metadata( absint( $attachment_id ), $image_data );

		// log the event.
		/* translators: %1$s will be replaced by the image sizes. */
		Log::get_instance()->create( sprintf( __( 'New thumb for %1$s generated.', 'external-files-in-media-library' ), $size[0] . 'x' . $size[1] ), $external_file_obj->get_url( true ), 'info', 2 );

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
		add_filter(
			'efml_files_query',
			function ( array $query ) {
				// extend the query to only get files we imported.
				$query['meta_query'][] = array(
					'key'   => EFML_POST_IMPORT_MARKER,
					'value' => 1,
				);

				// return the resulting query.
				return $query;
			}
		);

		// loop through all imported external files and update their wp_attached_file-setting.
		foreach ( $this->get_files() as $external_file ) {
			update_post_meta( $external_file->get_id(), '_wp_attached_file', $external_file->get_url() );
			delete_post_meta( $external_file->get_id(), EFML_POST_IMPORT_MARKER );
		}
	}

	/**
	 * Disable attachment-pages for external files.
	 *
	 * @source https://developer.wordpress.org/reference/hooks/redirect_canonical/
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

		// get actual ID.
		$post_id = get_the_ID();

		// bail if no post ID is given.
		if ( ! is_int( $post_id ) ) {
			return $redirect_url;
		}

		// get the external files.
		$external_file_obj = $this->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
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
	 * @source https://developer.wordpress.org/reference/hooks/wp_calculate_image_srcset/
	 *
	 * @param array<string> $sources Array with srcset-data if the image.
	 * @param array<string> $size_array Array with sizes for images.
	 * @param string        $image_src The src of the image.
	 * @param array<string> $image_meta The image meta-data.
	 * @param int           $attachment_id The attachment-ID.
	 *
	 * @return array<string>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function get_image_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $sources;
		}

		// bail with empty array if this file type supports thumbs and is external hosted.
		if (
			false === $external_file_obj->is_locally_saved()
			&& $external_file_obj->get_file_type_obj()->has_thumbs()
		) {
			// return empty array as we cannot optimize external images.
			return array();
		}

		// return resulting array.
		return $sources;
	}

	/**
	 * Force permalink-URL for file-attribute in meta-data for external URL-files
	 * to change the link-target if attachment-pages are disabled via "attachment_link"-hook.
	 *
	 * @source https://developer.wordpress.org/reference/hooks/wp_get_attachment_metadata/
	 *
	 * @param array<string,string> $data The image-data.
	 * @param int|string           $attachment_id The attachment-ID (also as string for compatibility with other plugins).
	 *
	 * @return array<string,string>
	 */
	public function get_attachment_metadata( array $data, int|string $attachment_id ): array {
		// bail if attachment pages are not disabled.
		if ( 0 === absint( get_option( 'eml_disable_attachment_pages', 0 ) ) ) {
			return $data;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( absint( $attachment_id ) );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $data;
		}

		// set permalink as file.
		$data['file'] = (string) get_permalink( absint( $attachment_id ) );

		// return resulting data array.
		return $data;
	}

	/**
	 * Set the import-marker for all attachments.
	 *
	 * @param array<int,array<string,mixed>> $post_meta The attachment-meta.
	 * @param int                            $post_id The attachment-ID.
	 *
	 * @return array<int,array<string,mixed>>
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
	 * Reset thumbnails if single file by request.
	 *
	 * Hint: the thumbs are generated by WordPress itself. We must not run this here, we just delete existing thumbs.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function reset_thumbnails_by_request(): void {
		// check referer.
		check_admin_referer( 'eml-reset-thumbnails', 'nonce' );

		// get the file ID.
		$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if post ID is not given.
		if ( 0 === $post_id ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// get the external file object.
		$external_file_obj = $this->get_file( $post_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			wp_safe_redirect( wp_get_referer() );
			exit;
		}

		// delete the thumbs of this file.
		$external_file_obj->delete_thumbs();

		// redirect user.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Parse the content by HTML-links to get their href-values.
	 *
	 * @param array<int,array<string>> $matches The matches.
	 * @param string                   $content The content to parse.
	 *
	 * @return array<int,array<string>>
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
	 * Add help for the settings of this plugin.
	 *
	 * @param array<array<string,string>> $help_list List of help tabs.
	 *
	 * @return array<array<string,string>>
	 */
	public function add_help( array $help_list ): array {
		$content  = '<h1>' . __( 'Add external files', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . __( 'The plugin allows you to integrate external files into your media library. These are then handled in the same way as other files that you upload here. You can integrate them into your website as you are used to.', 'external-files-in-media-library' ) . '</p>';
		$content .= '<h3>' . __( 'How to use', 'external-files-in-media-library' ) . '</h3>';
		/* translators: %1$s will be replaced by a URL. */
		$content .= '<ol><li>' . sprintf( __( 'Go to Media > <a href="%1$s">Add Media File</a>.', 'external-files-in-media-library' ), esc_url( add_query_arg( array(), get_admin_url() . 'media-new.php' ) ) ) . '</li>';
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
			'title'   => __( 'Add external files', 'external-files-in-media-library' ),
			'content' => $content,
		);

		// return list of help.
		return $help_list;
	}

	/**
	 * Check the srcset metadata for external files. Remove 'file' entry if file could not have thumbs
	 * as this results in possible warnings via @media.php.
	 *
	 * @param array<string,mixed> $image_meta The meta data.
	 * @param array<string,mixed> $size_array The size array.
	 * @param string              $image_src The src.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_srcset_meta( array $image_meta, array $size_array, string $image_src, int $attachment_id ): array {
		// get external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
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
	 * Prevent usage of not allowed mime types.
	 *
	 * @param array<string,mixed> $results The result for URL info detection, should include 'mime-type'.
	 * @param string              $url The used URL.
	 *
	 * @return array<string,mixed>
	 */
	public function prevent_not_allowed_mime_type( array $results, string $url ): array {
		// bail if no mime type is present.
		if ( ! isset( $results['mime-type'] ) ) {
			return array();
		}

		// bail if mime-type is set, but empty.
		if ( empty( $results['mime-type'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Mime-type of this file could not be detected. File will not be used for media library.', 'external-files-in-media-library' ), $url, 'error', 0, Import::get_instance()->get_identifier() );

			// return empty array to not import this file.
			return array();
		}

		// show deprecated warning for the old hook name.
		$true = apply_filters_deprecated( 'eml_files_check_content_type', array( true, $url ), '5.0.0', 'efml_files_check_content_type' );

		/**
		 * Filter whether we check the given mime type.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $true The return value.
		 * @param string $url The URL.
		 */
		if ( apply_filters( 'efml_files_check_content_type', $true, $url ) && ! in_array( $results['mime-type'], Helper::get_allowed_mime_types(), true ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Mime-type of this file is not allowed. Used mime type:', 'external-files-in-media-library' ) . ' <code>' . $results['mime-type'] . '</code>', $url, 'error', 0, Import::get_instance()->get_identifier() );

			// return empty array to not import this file.
			return array();
		}

		// return the results of this file.
		return $results;
	}

	/**
	 * Extend info about the external file in the attachment modal.
	 *
	 * @param string  $html The HTML to output.
	 * @param WP_Post $post The "WP_Post" object of the requested attachment.
	 *
	 * @return string
	 */
	public function show_media_info_in_modal( string $html, WP_Post $post ): string {
		global $pagenow;

		// bail if actual page is not "admin-ajax.php".
		if ( 'admin-ajax.php' !== $pagenow ) {
			return $html;
		}

		// get the external file object of this post.
		$external_file_obj = $this->get_file( $post->ID );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $html;
		}

		// add infos about the external file.
		$html .= '<div class="efml-attachment-url"><strong>' . __( 'External file from:', 'external-files-in-media-library' ) . '</strong> <span title="' . esc_attr( $external_file_obj->get_url( true ) ) . '">' . Helper::shorten_url( $external_file_obj->get_url( true ) ) . '</span></div>';

		// get the protocol handler.
		$protocol_handler = $external_file_obj->get_protocol_handler_obj();

		// if it is known, show its title.
		if ( $protocol_handler instanceof Protocol_Base ) {
			$html .= '<div class="efml-attachment-source"><strong>' . __( 'External file source:', 'external-files-in-media-library' ) . '</strong> ' . $protocol_handler->get_title() . '</div>';
		}

		// return the resulting string.
		return $html;
	}

	/**
	 * Add filter options for media library listing.
	 *
	 * @param array<int|string,string> $options The options.
	 *
	 * @return array<int|string,string>
	 */
	public function add_filter_options( array $options ): array {
		// get all external sources.
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
			)
		);

		// add them to the options list.
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ (string) $term->term_id ] = $term->name;
			}
		}

		return $options;
	}

	/**
	 * Use the filter options.
	 *
	 * @param WP_Query $query The "WP_Query" object.
	 *
	 * @return void
	 */
	public function use_filter_options( WP_Query $query ): void {
		// get filter value.
		$filter = filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if filter is not set.
		if ( is_null( $filter ) ) {
			return;
		}

		// get the term of the given URL.
		$term = get_term_by( 'term_id', $filter, Taxonomy::get_instance()->get_name() );

		// bail if no term could be found.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// extend the filter.
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => Taxonomy::get_instance()->get_name(),
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			)
		);
	}

	/**
	 * Show sync info in info dialog for single file.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param File                $external_file_obj The file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_external_source( array $dialog, File $external_file_obj ): array {
		// get sync marker.
		$sync_marker = absint( get_post_meta( $external_file_obj->get_id(), 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return $dialog;
		}

		// get the assigned archive term.
		$term = $this->get_term_by_attachment_id( $external_file_obj->get_id() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return $dialog;
		}

		// add infos in the dialog.
		$dialog['texts'][] = '<p><strong>' . esc_html__( 'External source:', 'external-files-in-media-library' ) . '</strong> ' . esc_html( $term->name ) . '</p>';

		// return resulting dialog.
		return $dialog;
	}

	/**
	 * Return a term for a given synced attachment ID.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return WP_Term|false
	 */
	public function get_term_by_attachment_id( int $attachment_id ): WP_Term|false {
		// get sync marker.
		$sync_marker = absint( get_post_meta( $attachment_id, 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return false;
		}

		// get the assigned archive term.
		$terms = wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name() );

		// bail if result is not an array.
		if ( ! is_array( $terms ) ) {
			return false;
		}

		// bail if none has been found.
		if ( empty( $terms ) ) {
			return false;
		}

		// get first result.
		$term = $terms[0];

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) { // @phpstan-ignore instanceof.alwaysTrue
			return false;
		}

		// return the term.
		return $term;
	}

	/**
	 * Return the title for a synced file.
	 *
	 * @param string $title The title.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_external_source_title( string $title, int $attachment_id ): string {
		// get synced term bei attachment ID.
		$term = $this->get_term_by_attachment_id( $attachment_id );

		// bail if no term could be loaded.
		if ( ! $term instanceof WP_Term ) {
			return $title;
		}

		// get the type name.
		$type = get_term_meta( $term->term_id, 'type', true );

		// get the listing object by this name.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// bail if listing object could not be loaded.
		if ( ! $listing_obj instanceof Directory_Listing_Base ) {
			return $title;
		}

		// return the listing object label.
		return '<span class="efml-icon efml-' . esc_attr( $listing_obj->get_name() ) . '" title="' . esc_attr( $listing_obj->get_title() ) . '"><span></span></span>';
	}

	/**
	 * Show sync info (external source) for files, which has been synced.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function show_external_source_info( File $external_file_obj ): void {
		// get the assigned archive term.
		$term = $this->get_term_by_attachment_id( $external_file_obj->get_id() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// get the title.
		$title = $term->name;
		if ( wp_http_validate_url( $title ) ) {
			$title = Helper::shorten_url( $term->name );
		}

		// show info about sync time.
		?>
		<li><span class="dashicons dashicons-clock"></span> <?php echo esc_html__( 'Synchronized from:', 'external-files-in-media-library' ); ?><br><a href="<?php echo esc_url( Directory_Listing::get_instance()->get_url() ); ?>"><?php echo esc_html( $title ); ?></a></li>
		<?php
	}

	/**
	 * Add URLs by using our hook.
	 *
	 * Example:
	 * apply_filters( 'efml_add_url', 0, 'https://example.com/sample.pdf', 'login', 'password' );
	 *
	 * @param int    $attachment_id The resulting attachment ID.
	 * @param string $url           The URL to add.
	 * @param string $login         The login to access the URL (optional).
	 * @param string $password      The password to access the URL (optional).
	 *
	 * @return int
	 */
	public function add_urls_by_hook( int $attachment_id, string $url, string $login = '', string $password = '' ): int {
		// bail if attachment ID is set.
		if ( $attachment_id > 0 ) {
			return $attachment_id;
		}

		// get the external file object for the added URL.
		$external_file_obj = $this->get_file_by_url( $url );

		// bail if external file object could be loaded and is valid = file exist.
		if ( $external_file_obj instanceof File && $external_file_obj->is_valid() ) {
			return $external_file_obj->get_id();
		}

		// get the import-object.
		$import_obj = Import::get_instance();

		// create the fields-array, we assume it is an HTTP- or FTP-connection.
		$fields = array(
			'login'    => array(
				'value' => $login,
			),
			'password' => array(
				'value' => $password,
			),
		);

		// add the credentials.
		$import_obj->set_fields( $fields );

		// run the import.
		$import_obj->add_url( $url );

		// get the external file object for the added URL.
		$external_file_obj = $this->get_file_by_url( $url );

		// bail if external file object could not be loaded or is not valid.
		if ( ! $external_file_obj instanceof File || ! $external_file_obj->is_valid() ) {
			return 0;
		}

		// return the attachment ID of the external file.
		return $external_file_obj->get_id();
	}

	/**
	 * Prevent usage of external images for non-image files (except PDFs).
	 *
	 * @param array<int,mixed>|false $image The given image to return for the preview.
	 * @param int                    $attachment_id The used attachment ID.
	 *
	 * @return array<int,mixed>|false
	 */
	public function prevent_images( array|false $image, int $attachment_id ): array|false {
		// bail if image is not an array.
		if ( ! is_array( $image ) ) {
			return $image;
		}

		// get external file object.
		$external_file_obj = $this->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $image;
		}

		// bail if file is hostet local.
		if ( $external_file_obj->is_locally_saved() ) {
			return $image;
		}

		// bail if file is an image or PDF.
		if ( $external_file_obj->get_file_type_obj() instanceof Image || $external_file_obj->get_file_type_obj() instanceof Pdf ) {
			return $image;
		}

		// return false to prevent any preview from external images.
		return false;
	}
}
