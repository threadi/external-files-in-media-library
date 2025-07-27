<?php
/**
 * File to handle general files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\File_Types;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File_Types_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle general files.
 */
class File extends File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = 'File';

	/**
	 * Return the file type title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Files', 'external-files-in-media-library' );
	}

	/**
	 * Output of proxied file.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function get_proxied_file(): void {
		// bail if no file is set.
		if ( ! $this->get_file() ) {
			exit;
		}

		// get the file object.
		$external_file_obj = $this->get_file();

		// get the cached file.
		$cached_file = $external_file_obj->get_cache_file( $this->get_dimensions() );

		// get WP Filesystem-handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// return header.
		header( 'Content-Type: ' . $external_file_obj->get_mime_type() );
		header( 'Content-Disposition: inline; filename="' . basename( $external_file_obj->get_url() ) . '"' );
		header( 'Content-Length: ' . wp_filesize( $cached_file ) );

		// return file content via WP filesystem.
		echo $wp_filesystem->get_contents( $cached_file ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
