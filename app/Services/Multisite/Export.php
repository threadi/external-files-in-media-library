<?php
/**
 * File to handle export tasks for multisite.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services\Multisite;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object for export files to local hosting.
 */
class Export extends Export_Base {
	/**
	 * Instance of actual object.
	 *
	 * @var Export|null
	 */
	private static ?Export $instance = null;

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
	 * @return Export
	 */
	public static function get_instance(): Export {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Export a file to this service. Returns the external URL if it was successfully and false if not.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param string              $target The target.
	 * @param array<string,mixed> $credentials The credentials.
	 * @return string|bool
	 */
	public function export_file( int $attachment_id, string $target, array $credentials ): string|bool {
		// get the file path.
		$file_path = get_attached_file( $attachment_id );

		// bail if file path could not be loaded.
		if ( ! is_string( $file_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Could not load file path for given attachment ID.', 'external-files-in-media-library' ), $target, 'error' );

			// do nothing more.
			return false;
		}

		// get the fields.
		$fields = $credentials['fields'];

		// bail if fields are empty.
		if( empty( $fields ) ) {
			return false;
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the attachment post object.
		$attachment_post_obj = get_post( $attachment_id );

		// get the file size.
		$file_path = get_attached_file( $attachment_id );
		$filesize = $wp_filesystem->size( $file_path );

		// get its URL.
		$url = wp_get_attachment_url( $attachment_id );

		// get the protocol handler for this URL.
		$protocol_handler = Protocols::get_instance()->get_protocol_object_for_url( $url );

		// get the tmp file.
		$tmp_file = $protocol_handler->get_temp_file( $url, $wp_filesystem );

		// bail if tmp file could not be saved.
		if( ! is_string( $tmp_file ) ) {
			return false;
		}

		// get the blog ID from the configuration.
		$blog_id = absint( $fields['website']['value'] );

		// get our own blog ID.
		$original_blog_id = get_current_blog_id();

		// switch to the given blog.
		switch_to_blog( $blog_id );

		// get the upload directory settings.
		$upload_dir = wp_get_upload_dir();

		// bail if baseurl is not a string.
		if ( ! is_string( $upload_dir['baseurl'] ) ) {
			// switch back to our own blog.
			switch_to_blog( $original_blog_id );

			// do nothing more.
			return false;
		}

		// prepare import of the file via WP-own functions.
		$file_array = array(
			'name'     => basename( $url ),
			'type'     => $attachment_post_obj->post_mime_type,
			'tmp_name' => $tmp_file,
			'error'    => '0',
			'size'     => $filesize,
			'url'      => $url,
		);

		// create post array.
		$post_array = array(
			'post_author' => Helper::get_current_user_id(),
		);

		// save the temporary file in media library and get its attachment ID.
		$new_attachment_id = media_handle_sideload( $file_array, 0, null, $post_array );

		// get the file URL if no error occurred.
		if ( is_int( $new_attachment_id ) ) {
			$target = wp_get_attachment_url( $new_attachment_id );

			// get meta-data from uploaded file.
			$meta_data = wp_get_attachment_metadata( $new_attachment_id );

			// get the local URL of the file.
			$local_full_path = wp_get_attachment_url( $new_attachment_id );

			// get the local path as relative path.
			$local_path = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $local_full_path );

			// set our local relative path on metadata.
			$meta_data['file'] = $local_path;

			// copy the relevant settings of the new uploaded file to the original file.
			wp_update_attachment_metadata( $new_attachment_id, $meta_data );
		}

		// switch back to our own blog.
		switch_to_blog( $original_blog_id );

		// bail on error.
		if ( is_wp_error( $new_attachment_id ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Inserting file in a multisite website resulted in error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $new_attachment_id ) . '</code>', $target, 'error' );

			// do nothing more.
			return false;
		}

		/* translators: %1$s will be replaced by a website title. */
		Log::get_instance()->create( sprintf( __( 'Successful exported file to %1$s.', 'external-files-in-media-library' ), '<em>' . get_blogaddress_by_id( $blog_id ) . '</em>' ), $target, 'info', 2 );

		// return the file URL.
		return $target;
	}

	/**
	 * Delete an exported file.
	 *
	 * @param string              $url           The URL to delete.
	 * @param array<string,mixed> $credentials   The credentials to use.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_exported_file( string $url, array $credentials, int $attachment_id ): bool {
		// TODO !!!

		// return true as file has been deleted.
		return true;
	}

	/**
	 * Return whether this export requires a specific URL.
	 *
	 * If this is false, the external plattform must create this URL.
	 *
	 * @return bool
	 */
	public function is_url_required(): bool {
		return true;
	}
}
