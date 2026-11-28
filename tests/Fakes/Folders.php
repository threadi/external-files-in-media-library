<?php
/**
 * Minimal version of the main class of the plugin Folders for the tests (only in separate processes).
 *
 * @package external-files-in-media-library
 */

/**
 * Minimal version of \WCP_Folders: it assigns the attachment to the folder in the request like the plugin.
 */
class WCP_Folders {
	/**
	 * Assign the attachment to the folder in the request.
	 *
	 * @param int $post_id The ID of the attachment.
	 *
	 * @return void
	 */
	public function add_attachment_category( $post_id ) {
		$folder_id = isset( $_REQUEST['folder_for_media'] ) ? absint( $_REQUEST['folder_for_media'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term      = $folder_id > 0 ? get_term( $folder_id ) : null;
		if ( $term instanceof WP_Term ) {
			wp_set_object_terms( $post_id, $term->slug, 'media_folder' );
		}
	}

	/**
	 * Return the taxonomy for the given post type.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return string
	 */
	public static function get_custom_post_type( $post_type ) {
		return 'attachment' === $post_type ? 'media_folder' : $post_type . '_folder';
	}
}
