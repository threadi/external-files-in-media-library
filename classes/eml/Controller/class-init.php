<?php
/**
 * This file contains the main init-object for this plugin.
 *
 * @package thread\eml
 */

namespace threadi\eml\Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;
use WP_Query;

/**
 * Initialize the plugin, connect all together.
 */
class Init {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Init
	 */
	private static ?Init $instance = null;

	/**
	 * Parameter to hold the external files object.
	 *
	 * @var External_Files
	 */
	private External_Files $external_files_obj;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
		// get instance of external files object.
		$this->external_files_obj = External_Files::get_instance();
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Init
	 */
	public static function get_instance(): Init {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Init();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 * @noinspection PhpUndefinedClassInspection
	 * @noinspection PhpFullyQualifiedNameUsageInspection
	 */
	public function init(): void {
		// schedule.
		add_action( 'eml_check_files', array( External_Files::get_instance(), 'check_files' ), 10, 0 );

		// attachment-hooks.
		add_filter( 'attachment_link', array( $this, 'get_attachment_link' ), 10, 2 );
		add_action( 'delete_attachment', array( External_Files::get_instance(), 'log_url_deletion' ), 10, 1 );
		add_action( 'delete_attachment', array( External_Files::get_instance(), 'delete_file_from_cache' ), 10, 1 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'get_edit_post_link', array( $this, 'change_edit_post_link' ), 10, 2 );
		add_filter( 'image_downsize', array( $this, 'image_downsize' ), 10, 2 );
		add_filter( 'wp_get_attachment_url', array( $this, 'get_attachment_url' ), 10, 2 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'wp_calculate_image_srcset' ), 10, 5 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'wp_get_attachment_metadata' ), 10, 2 );

		// import-hooks.
		add_filter( 'wp_import_post_meta', array( $this, 'set_import_marker_for_attachments' ), 10, 2 );
		add_action( 'import_end', array( $this, 'import_end' ), 10, 0 );

		// plugin-actions.
		register_activation_hook( EML_PLUGIN, array( Install::get_instance(), 'activation' ) );
		register_deactivation_hook( EML_PLUGIN, array( Install::get_instance(), 'deactivation' ) );

		// misc.
		add_action(
			'cli_init',
			function () {
				\WP_CLI::add_command( 'eml', 'threadi\eml\Controller\Cli' );
			}
		);
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
		add_filter( 'redirect_canonical', array( $this, 'disable_attachment_page' ), 10, 0 );
		add_filter( 'template_redirect', array( $this, 'disable_attachment_page' ), 10, 0 );

		// initialize proxy if enabled.
		if ( 1 === absint( get_option( 'eml_proxy', 0 ) ) ) {
			Proxy::get_instance()->init();
		}

		// hooks for third-party-plugins.
		add_filter( 'massedge-wp-eml/export/add_attachment', array( $this, 'prevent_external_attachment_in_export' ), 10, 2 );
		add_filter( 'downloadlist_rel_attribute', array( $this, 'downloadlist_rel_attribute' ), 10, 2 );
	}

	/**
	 * Return the url of an external file.
	 *
	 * @param string $url               The URL which is requested.
	 * @param int    $attachment_id     The attachment-ID which is requested.
	 *
	 * @return string
	 */
	public function get_attachment_url( string $url, int $attachment_id ): string {
		$external_file_obj = $this->external_files_obj->get_file( $attachment_id );

		// quick return the given $url if file is not a URL-file.
		if ( false === $external_file_obj ) {
			return $url;
		}

		// return the original url if this URL-file is not valid or not available.
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
		// if Yoast is available, let it handle this.
		if ( false !== method_exists( 'WPSEO_Options', 'get' ) ) {
			return $url;
		}

		// get the external file object.
		$external_file_obj = $this->external_files_obj->get_file( absint( $attachment_id ) );

		// further checks if it is a valid external file object.
		if ( $external_file_obj && $external_file_obj->is_valid() ) {
			// return the attachment-url if they are not disabled.
			if ( 0 === get_option( 'eml_disable_attachment_pages', 0 ) ) {
				// return the already checked url.
				return $url;
			}

			// return the external URL.
			return $external_file_obj->get_url( is_admin() );
		}

		// return given URL in all other cases.
		return $url;
	}

	/**
	 * Disable attachment-pages for external files.
	 *
	 * @return void
	 */
	public function disable_attachment_page(): void {
		if ( is_attachment() ) {
			$external_file_obj = $this->external_files_obj->get_file( get_the_ID() );
			if ( $external_file_obj && $external_file_obj->is_valid() && 1 === get_option( 'eml_disable_attachment_pages', 0 ) ) {
				global $wp_query;
				$wp_query->set_404();
				status_header( 404 );
			}
		}
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
		$external_file_obj = $this->external_files_obj->get_file( $post->ID );
		if ( $external_file_obj && $external_file_obj->is_valid() ) {

			// if file is not available, show hint as action.
			if ( false === $external_file_obj->get_availability() ) {
				// remove actions if file is not available.
				unset( $actions['edit'] );
				unset( $actions['copy'] );
				unset( $actions['download'] );

				// add custom hint.
				$url                 = add_query_arg( array( 'page' => 'eml_settings' ), 'options-general.php' );
				$actions['eml-hint'] = '<a href="' . esc_url( $url ) . '">' . __( 'Mime-Type not allowed', 'external-files-in-media-library' ) . '</a>';
			}

			// if media_replace or remove_background exist and this is an external hosted file,
			// remove it. (comes from plugin "Enable Media Replace").
			if ( false === $external_file_obj->is_locally_saved() ) {
				if ( isset( $actions['media_replace'] ) ) {
					unset( $actions['media_replace'] );
				}
				if ( isset( $actions['remove_background'] ) ) {
					unset( $actions['remove_background'] );
				}
			}
		}

		return $actions;
	}

	/**
	 * Change edit link if URL-file is not available.
	 *
	 * @param string $link The edit-URL.
	 * @param int    $post_id The post-ID.
	 *
	 * @return string
	 */
	public function change_edit_post_link( string $link, int $post_id ): string {
		$external_file_obj = $this->external_files_obj->get_file( $post_id );
		if ( $external_file_obj && false !== $external_file_obj->is_valid() ) {
			if ( false === $external_file_obj->get_availability() ) {
				$url = add_query_arg( array( 'page' => 'eml_settings' ), 'options-general.php' );
				return esc_url( $url );
			}
		}
		return $link;
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
		$external_file_obj = $this->external_files_obj->get_file( $post_id );
		if ( $external_file_obj && false !== $external_file_obj->is_valid() && false === $external_file_obj->get_availability() ) {
			// return nothing to prevent output as file is not valid.
			return '';
		}

		// return normal file-name.
		return $file;
	}

	/**
	 * Prevent image downsizing for external hosted images.
	 *
	 * @param array|bool $result The resulting array with image-data.
	 * @param int        $attachment_id The attachment ID.
	 *
	 * @return bool|array
	 */
	public function image_downsize( array|bool $result, int $attachment_id ): bool|array {
		// get the external file object.
		$external_file_obj = $this->external_files_obj->get_file( $attachment_id );

		// check if the file is an external file, an image and if it is really external hosted.
		if (
			$external_file_obj
			&& $external_file_obj->is_valid()
			&& false === $external_file_obj->is_locally_saved()
			&& $external_file_obj->is_image()
		) {
			// get image data.
			$image_data = wp_get_attachment_metadata( $attachment_id );

			// set return-array so that WP won't generate an image for it.
			$result = array(
				$external_file_obj->get_url(),
				$image_data['width'] ?? 0,
				$image_data['height'] ?? 0,
				false,
			);
		}

		// return result.
		return $result;
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
	public function wp_calculate_image_srcset( array $sources, array $size_array, string $image_src, array $image_meta, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = $this->external_files_obj->get_file( $attachment_id );

		// check if the file is an external file, an image and if it is really external hosted.
		if (
			$external_file_obj
			&& $external_file_obj->is_valid()
			&& false === $external_file_obj->is_locally_saved()
			&& $external_file_obj->is_image()
		) {
			// return empty array as we can not optimize external images.
			return array();
		}
		return $sources;
	}

	/**
	 * Prevent usage of external hostet attachments by the export via https://wordpress.org/plugins/export-media-library/
	 *
	 * @param array $value The values.
	 * @param array $params The params.
	 *
	 * @return array
	 */
	public function prevent_external_attachment_in_export( array $value, array $params ): array {
		if ( isset( $params['attachment_id'] ) ) {
			// get the external file object.
			$external_file_obj = $this->external_files_obj->get_file( $params['attachment_id'] );

			// check if the file is an external file, an image and if it is really external hosted.
			if (
				$external_file_obj
				&& $external_file_obj->is_valid()
				&& false === $external_file_obj->is_locally_saved()
				&& $external_file_obj->is_image()
			) {
				return array();
			}
		}
		return $value;
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
		if ( 'attachment' === get_post_type( $post_id ) ) {
			$post_meta[] = array(
				'key'   => 'eml_imported',
				'value' => 1,
			);
		}
		return $post_meta;
	}

	/**
	 * Set URL of all imported external files and remove the import-marker.
	 *
	 * @return void
	 */
	public function import_end(): void {
		$external_files = $this->external_files_obj->get_imported_external_files();
		foreach ( $external_files as $external_file ) {
			update_post_meta( $external_file->get_id(), '_wp_attached_file', $external_file->get_url() );
		}

		// get all imported attachments.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array(
				array(
					'key'   => EML_POST_IMPORT_MARKER,
					'value' => 1,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );
		if ( $result->post_count > 0 ) {
			foreach ( $result->posts as $attachment_id ) {
				// delete the import-marker.
				delete_post_meta( $attachment_id, EML_POST_IMPORT_MARKER );
			}
		}
	}

	/**
	 * Force permalink-URL for file-attribute in meta-data
	 * for external url-files to change the link-target if attachment-pages are disabled via attachment_link-hook.
	 *
	 * @param array $data The image-data.
	 * @param int   $attachment_id The attachment-ID.
	 *
	 * @return array
	 */
	public function wp_get_attachment_metadata( array $data, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = $this->external_files_obj->get_file( $attachment_id );

		// check if the file is an external file.
		if ( $external_file_obj && $external_file_obj->is_valid() ) {
			$data['file'] = get_permalink( $attachment_id );
		}
		return $data;
	}

	/**
	 * Set the rel-attribute for external files with Downloadlist-plugin.
	 *
	 * @param string $rel_attribute The rel-value.
	 * @param array  $file The file-attributes.
	 *
	 * @return string
	 */
	public function downloadlist_rel_attribute( string $rel_attribute, array $file ): string {
		// bail if array is empty.
		if ( empty( $file ) ) {
			return $rel_attribute;
		}

		// bail if id is not given.
		if ( empty( $file['id'] ) ) {
			return $rel_attribute;
		}

		// check if this is an external file.
		$external_file_obj = $this->external_files_obj->get_file( $file['id'] );

		// quick return the given $url if file is not a URL-file.
		if ( false === $external_file_obj ) {
			return $rel_attribute;
		}

		// return the original url if this URL-file is not valid or not available.
		if ( false === $external_file_obj->is_valid() || false === $external_file_obj->get_availability() ) {
			return $rel_attribute;
		}

		// return external value for rel-attribute.
		return 'external';
	}
}
