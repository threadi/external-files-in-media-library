<?php
/**
 * This file contains a model-object for a single File.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use finfo;

/**
 * Initialize the model for single external file-object.
 */
class File {

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
		if ( ! in_array( $this->get_mime_type(), Helper::get_allowed_mime_types(), true ) ) {
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
			$meta = wp_get_attachment_metadata( $this->get_id(), true );

			// bail if file size is not in meta.
			if ( empty( $meta['filesize'] ) ) {
				return 0;
			}

			// set file size.
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
		return Helper::is_image_by_mime_type( $this->get_mime_type() );
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
	}

	/**
	 * Return whether this file has been cached.
	 *
	 * Also check the cache of the file and return false it time to renew the cache is reached.
	 *
	 * @return bool
	 */
	public function is_cached(): bool {
		// bail if cached file does not exist.
		if ( ! file_exists( $this->get_cache_file() ) ) {
			return false;
		}

		// check the age of the cached file and compare it with max age for cached files.
		if ( filemtime( $this->get_cache_file() ) < ( time() - absint( get_option( 'eml_proxy_max_age', 24 ) ) * 60 * 60 ) ) {
			// return false as file is to old and should be renewed.
			return false;
		}

		// return true as it is cached and not to old.
		return true;
	}

	/**
	 * Add file to cache.
	 *
	 * @return void
	 */
	public function add_to_cache(): void {
		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		/**
		 * Get the handler for this url depending on its protocol.
		 */
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_external_file( $this );

		/**
		 * Do nothing if url is using a not supported tcp protocol.
		 */
		if ( ! $protocol_handler_obj ) {
			return;
		}

		/**
		 * Get info about the external file.
		 */
		$file_data = $protocol_handler_obj->get_url_info( $this->get_url( true ) );

		// compare the retrieved mime-type with the saved mime-type.
		if ( $file_data['mime-type'] !== $this->get_mime_type() ) {
			// other mime-type received => do not proxy this file.
			return;
		}

		// get the body.
		$body = $wp_filesystem->get_contents( $file_data['tmp-file'] );

		// check mime-type of the binary-data and compare it with header-data.
		$binary_data_info = new finfo( FILEINFO_MIME_TYPE );
		$binary_mime_type = $binary_data_info->buffer( $body );
		if ( $binary_mime_type !== $file_data['mime-type'] ) {
			return;
		}

		// set path incl. md5-filename and extension.
		$path = $this->get_cache_file();

		// save the given content to the path.
		$wp_filesystem->put_contents( $path, $body );
	}

	/**
	 * Return the cache-filename for this file.
	 * It does also contain the path.
	 *
	 * @return string
	 */
	public function get_cache_file(): string {
		// get path for cache directory.
		$path = Proxy::get_instance()->get_cache_directory();

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
		$mime_types = Helper::get_possible_mime_types();

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
		// bail if is not cached.
		if ( ! $this->is_cached() ) {
			return;
		}

		// clear the cache.
		wp_delete_file( $this->get_cache_file() );
	}

	/**
	 * Save the login on object.
	 *
	 * @param string $login The login.
	 *
	 * @return void
	 */
	public function set_login( string $login ): void {
		// bail if no login is given.
		if ( empty( $login ) ) {
			return;
		}

		// save as encrypted value in db.
		update_post_meta( $this->get_id(), 'eml_login', Crypt::get_instance()->encrypt( $login ) );
	}

	/**
	 * Save the password on object.
	 *
	 * @param string $password The password.
	 *
	 * @return void
	 */
	public function set_password( string $password ): void {
		// bail if no password is given.
		if ( empty( $password ) ) {
			return;
		}

		// save as encrypted value in db.
		update_post_meta( $this->get_id(), 'eml_password', Crypt::get_instance()->encrypt( $password ) );
	}

	/**
	 * Return decrypted login.
	 *
	 * @return string
	 */
	public function get_login(): string {
		$login = (string) get_post_meta( $this->get_id(), 'eml_login', true );

		// bail if string is empty.
		if ( empty( $login ) ) {
			return '';
		}

		// return decrypted string.
		return Crypt::get_instance()->decrypt( $login );
	}

	/**
	 * Return decrypted login.
	 *
	 * @return string
	 */
	public function get_password(): string {
		$password = (string) get_post_meta( $this->get_id(), 'eml_password', true );

		// bail if string is empty.
		if ( empty( $password ) ) {
			return '';
		}

		// return decrypted string.
		return Crypt::get_instance()->decrypt( $password );
	}

	/**
	 * Return whether this file is using credentials.
	 *
	 * @return bool
	 */
	public function has_credentials(): bool {
		return ! empty( $this->get_login() ) && ! empty( $this->get_password() );
	}

	/**
	 * Switch hosting of this file to local.
	 *
	 * @return bool
	 */
	public function switch_to_local(): bool {
		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		// get the handler for this url depending on its protocol.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_external_file( $this );

		// prevent duplicate check for this file.
		add_filter( 'eml_duplicate_check', array( $this, 'prevent_duplicate_check' ), 10, 2 );

		// get external file infos.
		$file_data = $protocol_handler_obj->get_external_infos();

		// remove prevent duplicate check for this file.
		remove_filter( 'eml_duplicate_check', array( $this, 'prevent_duplicate_check' ) );

		// bail if no file data could be loaded.
		if( empty( $file_data ) ) {
			return false;
		}

		// import file via WP-own functions.
		$array = array(
			'name'     => $this->get_title(),
			'type'     => $file_data[0]['mime-type'],
			'tmp_name' => $file_data[0]['tmp-file'],
			'error'    => 0,
			'size'     => $file_data[0]['filesize'],
			'url'      => $this->get_url(),
		);

		// remove URL from attachment-setting.
		delete_post_meta( $this->get_id(), '_wp_attached_file' );

		/**
		 * Get user the attachment would be assigned to.
		 */
		$user_id = Helper::get_current_user_id();

		/**
		 * Prepare attachment-post-settings.
		 */
		$post_array = array(
			'post_author' => $user_id,
		);

		// upload the external file.
		$attachment_id = media_handle_sideload( $array, 0, null, $post_array );

		// bail on error.
		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		// copy the relevant settings of the new uploaded file to the original.
		wp_update_attachment_metadata( $this->get_id(), wp_get_attachment_metadata( $attachment_id ) );

		// get the new local url.
		$local_url = wp_get_attachment_url( $attachment_id );

		// bail if no URL returned.
		if ( empty( $local_url ) ) {
			return false;
		}

		// remove base_url from local_url.
		$upload_dir = wp_get_upload_dir();
		$local_url  = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $local_url );

		// update attachment setting.
		update_post_meta( $this->get_id(), '_wp_attached_file', $local_url );

		// set setting for this file to local.
		$this->set_is_local_saved( true );

		// get the file path of the original.
		$file = get_attached_file( $this->get_id() );

		// secure the attached thumbnail files of this file.
		$files     = array( $file );
		$meta_data = wp_get_attachment_metadata( $this->get_id() );
		if ( ! empty( $meta_data['sizes'] ) ) {
			foreach ( $meta_data['sizes'] as $meta_file ) {
				$files[] = trailingslashit( dirname( $file ) ) . $meta_file['file'];
			}
		}

		// secure the files of this attachment.
		foreach ( $files as $file ) {
			$destination = trailingslashit( get_temp_dir() ) . basename( $file );
			$wp_filesystem->copy( $file, $destination, true );
		}

		// delete the temporary uploaded file.
		wp_delete_attachment( $attachment_id, true );

		// copy the secured files back.
		foreach ( $files as $file ) {
			$source = trailingslashit( get_temp_dir() ) . basename( $file );
			$wp_filesystem->copy( $source, $file, true );
		}

		// add to cache.
		$this->add_to_cache();

		// return true if switch was successfully.
		return true;
	}

	/**
	 * Switch hosting of this file to external.
	 *
	 * Only if used protocol supports this.
	 * And no credentials are used.
	 *
	 * @return bool
	 */
	public function switch_to_external(): bool {
		// bail if credentials are used.
		if ( ! empty( $this->get_login() ) && ! empty( $this->get_password() ) ) {
			return false;
		}

		// get protocol object for this file.
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_external_file( $this );

		// bail if protocol does not support external hosting.
		if ( $protocol_handler_obj->should_be_saved_local() ) {
			return false;
		}

		// get all files for this attachment and delete them local.
		wp_delete_attachment_files( $this->get_id(), wp_get_attachment_metadata( $this->get_id() ), get_post_meta( $this->get_id(), '_wp_attachment_backup_sizes', true ), get_attached_file( $this->get_id() ) );

		// update attachment setting.
		update_post_meta( $this->get_id(), '_wp_attached_file', $this->get_url() );

		// set setting to extern.
		$this->set_is_local_saved( false );

		// delete from cache.
		$this->delete_cache();

		// return true if switch was successfully.
		return true;
	}

	/**
	 * Prevent duplicate check.
	 *
	 * @param bool   $return_value The resulting value.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function prevent_duplicate_check( bool $return_value, string $url ): bool {
		// bail if URL is not our URL.
		if( $url !== $this->get_url( true ) ) {
			return $return_value;
		}

		return true;
	}
}
