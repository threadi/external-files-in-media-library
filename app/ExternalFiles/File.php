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
	 * External URL of this file.
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
	 * Get the external URL.
	 *
	 * @param bool $unproxied Whether this call could be use proxy (true) or not (false).
	 *
	 * @return string
	 */
	public function get_url( bool $unproxied = false ): string {
		// if external URL not known in object, get it now.
		if ( empty( $this->url ) ) {
			$this->url = get_post_meta( $this->get_id(), EFML_POST_META_URL, true );
		}

		// bail if proxy URL should not be used.
		if ( $unproxied ) {
			return $this->url;
		}

		$true = true;
		/**
		 * Filter whether file should be proxied.
		 *
		 * @since 2.1.0 Available since 2.1.0.
		 * @param bool $true False to disable proxy-URL.
		 * @param File $this The external file object.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( ! apply_filters( 'eml_file_prevent_proxied_url', $true, $this ) ) {
			return $this->url;
		}

		// bail if file type is not proxy compatible.
		if ( ! $this->get_file_type_obj()->is_proxy_enabled() ) {
			return $this->url;
		}

		// if no permalink structure is set, generate a parameterized URL.
		if ( empty( get_option( 'permalink_structure', '' ) ) ) {
			// return link for simple permalinks.
			return trailingslashit( get_home_url() ) . '?' . Proxy::get_instance()->get_slug() . '=' . $this->get_title();
		}

		// return link for pretty permalinks.
		return trailingslashit( get_home_url() ) . Proxy::get_instance()->get_slug() . '/' . $this->get_title();
	}

	/**
	 * Set the external url.
	 *
	 * @param string $url  The URL for this attachment-file.
	 * @return void
	 */
	public function set_url( string $url ): void {
		// set in DB.
		update_post_meta( $this->get_id(), EFML_POST_META_URL, $url );

		// set in object.
		$this->url = $url;
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

		// get the meta-data for this attachment.
		$meta = wp_get_attachment_metadata( $this->get_id(), true );

		// if no meta-data are set, create an array for them.
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		// set the mime type.
		$meta['mime_type'] = $mime_type;

		// save the updated meta-data.
		wp_update_attachment_metadata( $this->get_id(), $meta );
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
			$this->availability = get_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY, true );
		}

		// if mime-type of file is not allowed, set availability to false.
		if ( ! in_array( $this->get_mime_type(), Helper::get_allowed_mime_types(), true ) ) {
			$this->availability = false;
		}

		/**
		 * Filter and return the file availability.
		 *
		 * @param bool $availability The given availability.
		 * @param File $this The file object.
		 */
		return apply_filters( 'eml_file_availability', $this->availability, $this );
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
		update_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY, true );

		// set in object.
		$this->availability = $availability;
	}

	/**
	 * Return whether this object is valid.
	 * It must have an external URL.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return ! empty( $this->get_url( true ) );
	}

	/**
	 * Get file size.
	 *
	 * @return int
	 */
	public function get_filesize(): int {
		// return value if it is already known.
		if ( ! empty( $this->filesize ) ) {
			return $this->filesize;
		}

		// get value from DB.
		$meta = wp_get_attachment_metadata( $this->get_id(), true );

		// bail if file size is not in meta.
		if ( empty( $meta['filesize'] ) ) {
			return 0;
		}

		// set file size.
		$this->filesize = $meta['filesize'];

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
		// get the meta-data for this attachment.
		$meta = wp_get_attachment_metadata( $this->get_id(), true );

		// if no meta-data are set, create an array for them.
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		// add the file size.
		$meta['filesize'] = $file_size;

		// update the meta data.
		wp_update_attachment_metadata( $this->get_id(), $meta );

		// set the file size in object.
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
	public function is_video(): bool {
		return Helper::is_video_by_mime_type( $this->get_mime_type() );
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
		// bail if file is not marked as cached in DB.
		if ( empty( get_post_meta( $this->get_id(), 'eml_proxied', true ) ) ) {
			return false;
		}

		// get path for cached file.
		$cached_file = $this->get_cache_file();

		// bail if cached file does not exist.
		if ( ! file_exists( $cached_file ) ) {
			return false;
		}

		// check if cached file has reached its max age.
		if ( $this->get_file_type_obj()->is_cache_expired() ) {
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
		// bail if file type should not be cached in proxy.
		if ( ! $this->get_file_type_obj()->is_proxy_enabled() ) {
			return;
		}

		// bail if file is locally saved.
		if ( $this->is_locally_saved() ) {
			return;
		}

		global $wp_filesystem;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();

		/**
		 * Get the handler for this URL depending on its protocol.
		 */
		$protocol_handler_obj = $this->get_protocol_handler_obj();

		/**
		 * Do nothing if URL is using a not supported tcp protocol.
		 */
		if ( ! $protocol_handler_obj ) {
			return;
		}

		/**
		 * Get info about the external file.
		 */
		$file_data = $protocol_handler_obj->get_url_info( $this->get_url( true ) );

		// do not proxy this file if no mime-type has been received.
		if ( empty( $file_data['mime-type'] ) ) {
			return;
		}

		// compare the retrieved mime-type with the saved mime-type.
		if ( $file_data['mime-type'] !== $this->get_mime_type() ) {
			// other mime-type received => do not proxy this file.
			return;
		}

		// get temp file.
		$tmp_file = $protocol_handler_obj->get_temp_file( $this->get_url( true ), $wp_filesystem );

		// bail if temp file could not be loaded.
		if ( ! $tmp_file ) {
			return;
		}

		// get the body.
		$body = $wp_filesystem->get_contents( $tmp_file );

		// check mime-type of the binary-data and compare it with header-data.
		$binary_data_info = new finfo( FILEINFO_MIME_TYPE );
		$binary_mime_type = $binary_data_info->buffer( $body );
		if ( $binary_mime_type !== $file_data['mime-type'] ) {
			return;
		}

		// delete the temporary file.
		$protocol_handler_obj->cleanup_temp_file( $tmp_file );

		// set path incl. md5-filename and extension.
		$path = $this->get_cache_file();

		// save the given content to the path.
		$wp_filesystem->put_contents( $path, $body );

		// save that file has been cached.
		update_post_meta( $this->get_id(), 'eml_proxied', time() );
	}

	/**
	 * Return the cache-filename for this file.
	 * It does also contain the path.
	 *
	 * @param array $size The requested size.
	 *
	 * @return string
	 */
	public function get_cache_file( array $size = array() ): string {
		// get filename.
		$filename = md5( $this->get_url( true ) ) . '.' . $this->get_file_extension();

		// check size.
		if ( ! empty( $size ) ) {
			$filename = md5( $this->get_url( true ) ) . '-' . $size[0] . 'x' . $size[1] . '.' . $this->get_file_extension();
		}

		// get path for cache directory.
		$path = Proxy::get_instance()->get_cache_directory();

		// return resulting string without further checks.
		return $path . $filename;
	}

	/**
	 * Return file-extension based on mime-type of the file.
	 *
	 * @return string
	 */
	public function get_file_extension(): string {
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

		// delete the marker.
		delete_post_meta( $this->get_id(), 'eml_proxied' );
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

		// get the handler for this URL depending on its protocol.
		$protocol_handler_obj = $this->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded.
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		// prevent duplicate check for this file.
		add_filter( 'eml_duplicate_check', array( $this, 'prevent_checks' ), 10, 2 );
		add_filter( 'eml_locale_file_check', array( $this, 'prevent_checks' ), 10, 2 );

		// get external file infos.
		$file_data = $protocol_handler_obj->get_url_infos();

		// remove prevent duplicate check for this file.
		remove_filter( 'eml_duplicate_check', array( $this, 'prevent_checks' ) );
		remove_filter( 'eml_locale_file_check', array( $this, 'prevent_checks' ) );

		// bail if no file data could be loaded.
		if ( empty( $file_data ) ) {
			return false;
		}

		// import file via WP-own functions.
		$array = array(
			'name'     => $this->get_title(),
			'type'     => $file_data[0]['mime-type'],
			'tmp_name' => '',
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

		// get temp file.
		$tmp_file = $protocol_handler_obj->get_temp_file( $this->get_url(), $wp_filesystem );

		// bail if no temp file could be loaded.
		if ( ! $tmp_file ) {
			return false;
		}

		// set temp file for side load.
		$array['tmp_name'] = $tmp_file;

		// upload the external file.
		$attachment_id = media_handle_sideload( $array, 0, null, $post_array );

		// bail on error.
		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		// get meta-data from original.
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		// create array for meta-data if it is not one.
		if ( ! is_array( $meta_data ) ) {
			$meta_data = array();
		}

		// copy the relevant settings of the new uploaded file to the original.
		wp_update_attachment_metadata( $this->get_id(), $meta_data );

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

		// clear cache.
		$this->delete_cache();
		$this->delete_thumbs();

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
		$protocol_handler_obj = $this->get_protocol_handler_obj();

		// bail if protocol does not support external hosting.
		if ( $protocol_handler_obj->should_be_saved_local() ) {
			return false;
		}

		// get all files for this attachment and delete them local.
		wp_delete_attachment_files( $this->get_id(), wp_get_attachment_metadata( $this->get_id() ), get_post_meta( $this->get_id(), '_wp_attachment_backup_sizes', true ), get_attached_file( $this->get_id() ) );

		// update attachment setting.
		update_post_meta( $this->get_id(), '_wp_attached_file', $this->get_url( true ) );

		// set setting to extern.
		$this->set_is_local_saved( false );

		// add to cache.
		$this->add_to_cache();

		// return true if switch was successfully.
		return true;
	}

	/**
	 * Prevent check (for duplicate or local file).
	 *
	 * @param bool   $return_value The resulting value.
	 * @param string $url The used URL.
	 *
	 * @return bool
	 */
	public function prevent_checks( bool $return_value, string $url ): bool {
		// bail if URL is not our URL.
		if ( $url !== $this->get_url( true ) ) {
			return $return_value;
		}

		// return true to prevent the check.
		return true;
	}

	/**
	 * Delete thumbs of this file.
	 *
	 * @return void
	 */
	public function delete_thumbs(): void {
		// bail if this is not cached.
		if ( ! $this->is_cached() ) {
			return;
		}

		// get WP Filesystem-handler.
		require_once ABSPATH . '/wp-admin/includes/file.php';
		\WP_Filesystem();
		global $wp_filesystem;

		// get the image meta data.
		$image_meta_data = wp_get_attachment_metadata( $this->get_id(), true );

		// bail if no sizes are given.
		if ( empty( $image_meta_data['sizes'] ) ) {
			return;
		}

		// get proxy-object.
		$proxy_obj = Proxy::get_instance();

		// loop through the sizes.
		foreach ( $image_meta_data['sizes'] as $size_data ) {
			// get file path.
			$file = $proxy_obj->get_cache_directory() . Helper::generate_sizes_filename( basename( $this->get_cache_file() ), $size_data['width'], $size_data['height'], $this->get_file_extension() );

			// bail if file does not exist.
			if ( ! $wp_filesystem->exists( $file ) ) {
				continue;
			}

			// delete it.
			$wp_filesystem->delete( $file );
		}
	}

	/**
	 * Return the file type object of this file.
	 *
	 * @return File_Types_Base
	 */
	public function get_file_type_obj(): File_Types_Base {
		return File_Types::get_instance()->get_type_object_for_file_obj( $this );
	}

	/**
	 * Set the file meta-data.
	 *
	 * @return void
	 */
	public function set_metadata(): void {
		$this->get_file_type_obj()->set_metadata();
	}

	/**
	 * Return the protocol handler of this file.
	 *
	 * @return Protocol_Base|false
	 */
	public function get_protocol_handler_obj(): Protocol_Base|false {
		return Protocols::get_instance()->get_protocol_object_for_external_file( $this );
	}
}
