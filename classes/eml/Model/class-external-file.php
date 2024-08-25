<?php
/**
 * This file contains a model-object for a single External_File.
 *
 * @package thread\eml
 */

namespace threadi\eml\Model;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\Controller\External_Files;
use threadi\eml\Controller\Proxy;

/**
 * Initialize the model for single external file-object.
 */
class External_File {

	/**
	 * The attachment-id of this file (post.ID).
	 *
	 * @var int
	 */
	private int $id = 0;

	/**
	 * External url of this file.
	 *
	 * @var string
	 */
	private string $url = '';

	/**
	 * Title of the file.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * The file availability.
	 *
	 * @var bool
	 */
	private bool $availability = false;

	/**
	 * The file size.
	 *
	 * @var int
	 */
	private int $filesize = 0;

	/**
	 * The marker if this file is locally saved.
	 *
	 * @var bool
	 */
	private bool $locally_saved = false;

	/**
	 * Constructor for this object.
	 *
	 * @param int $attachment_id    The ID of the attachment.
	 */
	public function __construct( int $attachment_id = 0 ) {
		// set the ID in object.
		$this->set_id( $attachment_id );
	}

	/**
	 * Get the ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Set the ID.
	 *
	 * @param int $id   The ID of the attachment.
	 * @return void
	 */
	private function set_id( int $id ): void {
		$this->id = $id;
	}

	/**
	 * Get the external url.
	 *
	 * @param bool $unproxied Whether this call could be use proxy (true) or not (false).
	 *
	 * @return string
	 */
	public function get_url( bool $unproxied = false ): string {
		// if external URL not known, get it now.
		if ( empty( $this->url ) ) {
			$this->url = get_post_meta( $this->get_id(), EML_POST_META_URL, true );
		}

		// use local proxy if enabled, this is an image and if we are not in wp-admin.
		if ( ! empty( $this->url ) && $this->is_image() && 1 === absint( get_option( 'eml_proxy', 0 ) ) && false === $unproxied ) {
			if ( empty( get_option( 'permalink_structure', '' ) ) ) {
				// return link for simple permalinks.
				return trailingslashit( get_home_url() ) . '?' . Proxy::get_instance()->get_slug() . '=' . $this->get_title();
			}

			// return link for pretty permalinks.
			return trailingslashit( get_home_url() ) . Proxy::get_instance()->get_slug() . '/' . $this->get_title();
		}

		// return normal URL.
		return $this->url;
	}

	/**
	 * Set the external url.
	 *
	 * @param string $url  The URL for this attachment-file.
	 * @return void
	 */
	public function set_url( string $url ): void {
		// set in DB.
		update_post_meta( $this->get_id(), EML_POST_META_URL, $url );

		// set in object.
		$this->url = $url;
	}

	/**
	 * Get the edit-URL for this external file.
	 *
	 * @return string
	 */
	public function get_edit_url(): string {
		if ( current_user_can( 'manage_options' ) ) {
			return add_query_arg(
				array(
					'post'   => $this->get_id(),
					'action' => 'edit',
				),
				admin_url( 'post.php' )
			);
		}
		return '';
	}

	/**
	 * Return the title of this attachment.
	 *
	 * @return string
	 */
	public function get_title(): string {
		if ( empty( $this->title ) ) {
			remove_filter( 'the_title', 'wptexturize' );
			$this->title = get_the_title( $this->get_id() );
			add_filter( 'the_title', 'wptexturize' );
		}
		return $this->title;
	}

	/**
	 * Set the title of this attachment.
	 *
	 * @param string $title The title for this attachment-file.
	 * @return void
	 */
	public function set_title( string $title ): void {
		// set in DB.
		$query = array(
			'ID'         => $this->get_id(),
			'post_title' => $title,
		);
		wp_update_post( $query );

		// set in object.
		$this->title = $title;
	}

	/**
	 * Return the mime-type of this attachment.
	 *
	 * @return string
	 */
	public function get_mime_type(): string {
		return get_post_mime_type( $this->get_id() );
	}

	/**
	 * Set the mime-type of this attachment.
	 *
	 * @param string $mime_type The mime-type of this attachment-file.
	 * @return void
	 */
	public function set_mime_type( string $mime_type ): void {
		// set in DB.
		$query = array(
			'ID'             => $this->get_id(),
			'post_mime_type' => $mime_type,
		);
		wp_update_post( $query );

		// Update the meta-field for mime_type.
		$meta = wp_get_attachment_metadata( $this->get_id(), true );
		if ( is_array( $meta ) ) {
			$meta['mime_type'] = $mime_type;
			wp_update_attachment_metadata( $this->get_id(), $meta );
		}
	}

	/**
	 * Get the availability of this file.
	 *
	 * This also checks if the files mime-type is allowed.
	 *
	 * @return bool
	 */
	public function get_availability(): bool {
		// get value from DB.
		if ( empty( $this->availability ) ) {
			$this->availability = get_post_meta( $this->get_id(), EML_POST_META_AVAILABILITY, true );
		}

		// if mime-type of file is not allowed, set availability to false.
		if ( ! in_array( $this->get_mime_type(), External_Files::get_instance()->get_allowed_mime_types(), true ) ) {
			$this->availability = false;
		}

		// return actual value.
		return $this->availability;
	}

	/**
	 * Set the availability of this file.
	 *
	 * @param bool $availability    The availability of this file (true/false).
	 *
	 * @return void
	 */
	public function set_availability( bool $availability ): void {
		// set in DB.
		update_post_meta( $this->get_id(), EML_POST_META_AVAILABILITY, true );

		// set in object.
		$this->availability = $availability;
	}

	/**
	 * Return whether this object is valid.
	 * It must have an url.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return ! empty( $this->get_url() );
	}

	/**
	 * Get file size.
	 *
	 * @return int
	 */
	public function get_filesize(): int {
		// get value from DB.
		if ( empty( $this->filesize ) ) {
			$meta           = wp_get_attachment_metadata( $this->get_id(), true );
			$this->filesize = $meta['filesize'];
		}

		// return the file size.
		return $this->filesize;
	}

	/**
	 * Set file size.
	 *
	 * @param int $file_size The size of the file.
	 *
	 * @return void
	 */
	public function set_filesize( int $file_size ): void {
		// Update the thumbnail filename.
		$meta = wp_get_attachment_metadata( $this->get_id(), true );
		if ( is_array( $meta ) ) {
			$meta['filesize'] = $file_size;
			wp_update_attachment_metadata( $this->get_id(), $meta );
		}

		// set in object.
		$this->filesize = $file_size;
	}

	/**
	 * Return attachment-file-setting.
	 *
	 * @return string
	 */
	public function get_attachment_url(): string {
		return (string) get_post_meta( $this->get_id(), '_wp_attached_file', true );
	}

	/**
	 * Return whether this URL-file an image.
	 *
	 * @return bool
	 */
	public function is_image(): bool {
		return External_Files::get_instance()->is_image_by_mime_type( $this->get_mime_type() );
	}

	/**
	 * Return whether this URL-file is an image.
	 *
	 * @return bool
	 */
	public function is_locally_saved(): bool {
		return 1 === absint( get_post_meta( $this->get_id(), 'eml_locally_saved', true ) );
	}

	/**
	 * Set if this file is locally saved.
	 *
	 * @param bool $locally_saved Whether the file is locally saved (true) or not (false).
	 *
	 * @return void
	 */
	public function set_is_local_saved( bool $locally_saved ): void {
		// set in DB.
		update_post_meta( $this->get_id(), 'eml_locally_saved', $locally_saved );

		// set in object.
		$this->locally_saved = $locally_saved;
	}

	/**
	 * Return whether this file has been cached.
	 *
	 * Also check the cache of the file and return false it time to renew the cache is reached.
	 *
	 * @return bool
	 */
	public function is_cached(): bool {
		if ( file_exists( $this->get_cache_file() ) ) {
			// check the age of the cached file and compare it with max age for cached files.
			if ( filemtime( $this->get_cache_file() ) < ( time() - absint( get_option( 'eml_proxy_max_age', 24 ) ) * 60 * 60 ) ) {
				// return false as file is to old and should be renewed.
				return false;
			}

			// return true as it is cached and not to old.
			return true;
		}

		// set return value to false.
		return false;
	}

	/**
	 * Add file to cache.
	 *
	 * @param string $content The content to save in cache.
	 * @return void
	 */
	public function add_cache( string $content ): void {
		global $wp_filesystem;

		// Make sure that the above variable is properly setup.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		// set path incl. md5-filename and extension.
		$path = $this->get_cache_file();

		// save the given content to the path.
		$wp_filesystem->put_contents( $path, $content );
	}

	/**
	 * Return the cache-filename for this file.
	 * It does also contain the path.
	 *
	 * @return string
	 */
	public function get_cache_file(): string {
		// get path.
		$path = External_Files::get_instance()->get_cache_directory();

		// get filename.
		$filename = md5( $this->get_url() ) . '.' . $this->get_file_extension();

		// return resulting string without further checks.
		return $path . $filename;
	}

	/**
	 * Return file-extension based on mime-type of the file.
	 *
	 * @return string
	 */
	private function get_file_extension(): string {
		// get all possible mime-types.
		$mime_types = External_Files::get_instance()->get_possible_mime_types();

		// return known extension for this mime-type.
		if ( ! empty( $mime_types[ $this->get_mime_type() ] ) ) {
			return $mime_types[ $this->get_mime_type() ]['ext'];
		}

		// return empty string.
		return '';
	}

	/**
	 * Return the content of the cached file.
	 *
	 * @return string
	 */
	public function get_cached_file_content(): string {
		global $wp_filesystem;

		// Make sure that the above variable is properly setup.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		// return the file content.
		return $wp_filesystem->get_contents( $this->get_cache_file() );
	}

	/**
	 * If file is deleted, delete also its proxy cache.
	 *
	 * @return void
	 */
	public function delete_cache(): void {
		if ( $this->is_cached() ) {
			wp_delete_file( $this->get_cache_file() );
		}
	}
}
