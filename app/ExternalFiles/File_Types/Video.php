<?php
/**
 * File to handle video files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\File_Types;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;

/**
 * Object to handle videos.
 */
class Video extends File_Types_Base {
	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array(
		'video/mp4',
	);

	/**
	 * Output of proxied file.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_proxied_file(): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// set start byte.
		$start = 0;

		// set end byte to size - 1.
		$end = $external_file_obj->get_filesize() - 1;

		// set content type in header.
		header( 'Content-type: ' . $external_file_obj->get_mime_type() );

		// set ranges.
		header( 'Accept-Ranges: bytes' );

		// set bytes for response.
		header( 'Content-Range: bytes ' . ( $start - $end / $external_file_obj->get_filesize() ) );

		// set max length.
		header( 'Content-Length: ' . $external_file_obj->get_filesize() );

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $external_file_obj->get_cache_file() ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Set meta-data for the file by given file data.
	 *
	 * @param array $file_data The file data.
	 *
	 * @return void
	 */
	public function set_metadata( array $file_data ): void {
		// get the file object.
		$external_file_obj = $this->get_file();

		// collect meta data.
		$video_meta = array(
			'filesize' => $file_data['filesize'],
		);

		// save the resulting image-data.
		wp_update_attachment_metadata( $external_file_obj->get_id(), $video_meta );
	}
}
