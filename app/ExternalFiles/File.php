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
			$url = get_post_meta( $this->get_id(), EFML_POST_META_URL, true );

			// bail if returned value is not a string.
			if ( ! is_string( $url ) ) {
				return '';
			}

			// set the url.
			$this->url = $url;
		}

		// bail if proxy URL should not be used.
		if ( $unproxied ) {
			return $this->url;
		}

		$true     = true;
		$instance = $this;
		/**
		 * Filter whether file should be proxied.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param bool $true False to disable proxy-URL.
		 * @param File $instance The external file object.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( ! apply_filters( 'eml_file_prevent_proxied_url', $true, $instance ) ) {
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
	 * Set the external URL.
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
		// get the mime type setting of this post.
		$mime_type = get_post_mime_type( $this->get_id() );

		// bail if returned value is not a string.
		if ( ! is_string( $mime_type ) ) {
			return '';
		}

		// return the mime type.
		return $mime_type;
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
		$meta = (array) wp_get_attachment_metadata( $this->get_id(), true );

		// set the mime type.
		$meta['mime_type'] = $mime_type;

		// save the updated meta-data.
		wp_update_attachment_metadata( $this->get_id(), $meta );
	}

	/**
	 * Return whether this file is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		// get value from DB.
		if ( empty( $this->availability ) ) {
			$this->availability = (bool) get_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY, true );
		}

		$instance = $this;
		/**
		 * Filter and return the file availability.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param bool $availability The given availability.
		 * @param File $instance The file object.
		 */
		return apply_filters( 'eml_file_availability', $this->availability, $instance );
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
	 * Return whether the mime type of this file is allowed (true) or not (false).
	 *
	 * @return bool
	 */
	public function is_mime_type_allowed(): bool {
		return in_array( $this->get_mime_type(), Helper::get_allowed_mime_types(), true );
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
		$meta = (array) wp_get_attachment_metadata( $this->get_id(), true );

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
		// get the value from DB.
		$attachment_url = get_post_meta( $this->get_id(), '_wp_attached_file', true );

		// bail if value is not a string.
		if ( ! is_string( $attachment_url ) ) {
			return '';
		}

		// return the attachment URL.
		return $attachment_url;
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
		 * Get used WP Filesystem handler.
		 */
		$wp_filesystem = Helper::get_wp_filesystem();

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
		if ( ! is_string( $tmp_file ) ) {
			return;
		}

		// get the body.
		$body = $wp_filesystem->get_contents( $tmp_file );

		// bail if no contents returned.
		if ( ! $body ) {
			return;
		}

		// bail if finfo is not available.
		if ( ! class_exists( 'finfo' ) ) {
			return;
		}

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
	 * @param array<int,int> $size The requested size.
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
		// get the login for this file.
		$login = get_post_meta( $this->get_id(), 'eml_login', true );

		// bail if no string returned.
		if ( ! is_string( $login ) ) {
			return '';
		}

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
		// get the password for this file.
		$password = get_post_meta( $this->get_id(), 'eml_password', true );

		// bail if no string returned.
		if ( ! is_string( $password ) ) {
			return '';
		}

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
		$wp_filesystem = Helper::get_wp_filesystem();

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
			'error'    => '0',
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
		if ( ! is_string( $tmp_file ) ) {
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

		// bail if baseurl is not a string.
		if ( ! is_string( $upload_dir['baseurl'] ) ) {
			return false;
		}

		// get the local URL.
		$local_url = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $local_url );

		// update attachment setting.
		update_post_meta( $this->get_id(), '_wp_attached_file', $local_url );

		// set setting for this file to local.
		$this->set_is_local_saved( true );

		// get the file path of the original.
		$file = get_attached_file( $this->get_id() );

		// bail if file is not a string.
		if ( ! is_string( $file ) ) {
			return false;
		}

		// secure the attached thumbnail files of this file.
		$files     = array( $file );
		$meta_data = wp_get_attachment_metadata( $this->get_id() );
		if ( ! empty( $meta_data['sizes'] ) ) {
			foreach ( $meta_data['sizes'] as $meta_file ) {
				// bail if meta_file is not an array.
				if ( ! is_array( $meta_file ) ) {
					continue;
				}

				// bail if file is not a string.
				if ( ! is_string( $meta_file['file'] ) ) {
					continue;
				}

				// add the file to the list.
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

		// bail if protocol handler could not be loaded.
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		// bail if protocol does not support external hosting.
		if ( $protocol_handler_obj->should_be_saved_local() ) {
			return false;
		}

		// get the meta data.
		$meta_data = wp_get_attachment_metadata( $this->get_id() );

		// bail if meta-data could not be loaded.
		if ( ! $meta_data ) {
			$meta_data = array();
		}

		// get sizes.
		$sizes = get_post_meta( $this->get_id(), '_wp_attachment_backup_sizes', true );

		// bail if sizes is not an array.
		if ( ! is_array( $sizes ) ) {
			$sizes = array();
		}

		// get attached file.
		$file = get_attached_file( $this->get_id() );

		// bail if file is not a string.
		if ( ! is_string( $file ) ) {
			return false;
		}

		// get all files for this attachment and delete them local.
		wp_delete_attachment_files( $this->get_id(), $meta_data, $sizes, $file );

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
	 * Delete this external file.
	 *
	 * @return void
	 */
	public function delete(): void {
		// delete thumbs.
		$this->delete_thumbs();

		// delete the file entry itself.
		wp_delete_attachment( $this->get_id(), true );
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
		$wp_filesystem = Helper::get_wp_filesystem( 'local' );

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
			// bail if size_data is not an array.
			if ( ! is_array( $size_data ) ) {
				continue;
			}

			// get file path.
			$file = $proxy_obj->get_cache_directory() . Helper::generate_sizes_filename( basename( $this->get_cache_file() ), absint( $size_data['width'] ), absint( $size_data['height'] ), $this->get_file_extension() );

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

	/**
	 * Return the import datetime.
	 *
	 * Hint: this is not the WP_Post date.
	 *
	 * @return string
	 */
	public function get_date(): string {
		// get date from post meta.
		$date = get_post_meta( $this->get_id(), 'eml_external_file_date', true );

		// bail if date is empty.
		if( empty( $date ) ) {
			return '';
		}

		// return formatted date time.
		return Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', absint( $date ) ) );
	}

	/**
	 * Set the import datetime.
	 *
	 * @return void
	 */
	public function set_date(): void {
		update_post_meta( $this->get_id(), 'eml_external_file_date', time() );
	}
}
