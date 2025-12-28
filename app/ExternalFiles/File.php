<?php
/**
 * This file contains a model-object for a single File.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use Error;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use easyDirectoryListingForWordPress\Crypt;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
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
	 * The availability of this file.
	 *
	 * @var bool
	 */
	private bool $availability = false;

	/**
	 * The size of the file.
	 *
	 * @var int
	 */
	private int $filesize = 0;

	/**
	 * The mime type of the file.
	 *
	 * @var string
	 */
	private string $mime_type = '';

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
	 * Return the ID.
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
	 * Return the external URL.
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

		$instance = $this;

		// show deprecated warning for old hook name.
		$true = apply_filters_deprecated( 'eml_file_prevent_proxied_url', array( true, $instance ), '5.0.0', 'efml_file_prevent_proxied_url' );

		/**
		 * Filter whether file should be proxied.
		 *
		 * @since 3.0.0 Available since 3.0.0.
		 * @param bool $true False to disable proxy-URL.
		 * @param File $instance The external file object.
		 */
		if ( ! apply_filters( 'efml_file_prevent_proxied_url', $true, $instance ) ) {
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
	 * Remove the external URL.
	 *
	 * @return void
	 */
	public function remove_url(): void {
		delete_post_meta( $this->get_id(), EFML_POST_META_URL );
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
		// return value, if it is already known.
		if ( ! empty( $this->mime_type ) ) {
			return $this->mime_type;
		}

		// get the mime type setting of this post.
		$mime_type = get_post_mime_type( $this->get_id() );

		// bail if returned value is not a string.
		if ( ! is_string( $mime_type ) ) {
			return '';
		}

		// set the value.
		$this->mime_type = $mime_type;

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
	 * Return whether the file using this protocol is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		// get value from DB.
		if ( empty( $this->availability ) ) {
			$this->availability = 1 === absint( get_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY, true ) );
		}

		$instance = $this;

		// show deprecated warning for old hook name.
		$this->availability = apply_filters_deprecated( 'eml_file_availability', array( $this->availability, $instance ), '5.0.0', 'efml_file_availability' );

		/**
		 * Filter and return the availability of an external file.
		 *
		 * @since 1.0.0 Available since 1.0.0.
		 *
		 * @param bool $availability The given availability.
		 * @param File $instance The file object.
		 */
		return apply_filters( 'efml_file_availability', $this->availability, $instance );
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
		update_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY, $availability );

		// set in object.
		$this->availability = $availability;
	}

	/**
	 * Delete the availability of this file.
	 *
	 * @return void
	 */
	public function remove_availability(): void {
		delete_post_meta( $this->get_id(), EFML_POST_META_AVAILABILITY );
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
	 * Return whether this URL-file is locally saved.
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
	 * Remove the marker for locally saved external file.
	 *
	 * @return void
	 */
	public function remove_local_saved(): void {
		delete_post_meta( $this->get_id(), 'eml_locally_saved' );
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

		// return value depending on check if cached file has reached its max age.
		return ! $this->get_file_type_obj()->is_cache_expired();
	}

	/**
	 * Add file to cache for proxy.
	 *
	 * @return void
	 */
	public function add_to_proxy(): void {
		// bail if finfo is not available.
		if ( ! class_exists( 'finfo' ) ) {
			return;
		}

		// bail if file type should not be cached in proxy.
		if ( ! $this->get_file_type_obj()->is_proxy_enabled() ) {
			return;
		}

		// bail if file is locally saved.
		if ( $this->is_locally_saved() ) {
			return;
		}

		// disable the check for unsafe URLs during the download of them for the proxy.
		add_filter( 'efml_http_header_args', array( $this, 'disable_check_for_unsafe_urls' ) );

		// get the handler for this URL depending on its protocol.
		$protocol_handler_obj = $this->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return;
		}

		// get info about the file.
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

		// get used WP Filesystem handler.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get temp file.
		$tmp_file = $protocol_handler_obj->get_temp_file( $this->get_url( true ), $wp_filesystem );

		// bail if temp file could not be loaded.
		if ( ! is_string( $tmp_file ) ) {
			return;
		}

		// get the content of this file.
		$body = $wp_filesystem->get_contents( $tmp_file );

		// bail if no contents returned.
		if ( ! $body ) {
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
		try {
			$wp_filesystem->put_contents( $path, $body );
		} catch ( Error $e ) {
			// create the error entry.
			$error_obj = new Url_Result();
			$error_obj->set_result_text( __( 'Error occurred during requesting this file.', 'external-files-in-media-library' ) );
			$error_obj->set_url( $this->get_url( true ) );
			$error_obj->set_error( true );

			// add the error object to the list of errors.
			Results::get_instance()->add( $error_obj );

			// add log entry.
			Log::get_instance()->create( __( 'The following error occurred:', 'external-files-in-media-library' ) . ' <code>' . $e->getMessage() . '</code>', $this->get_url( true ), 'error' );

			// do nothing more.
			return;
		}

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
	 * Save the fields on object.
	 *
	 * @param array<string,array<string,mixed>> $fields The fields.
	 *
	 * @return void
	 */
	public function set_fields( array $fields ): void {
		// bail if no login is given.
		if ( empty( $fields ) ) {
			return;
		}

		// save as encrypted value in db.
		update_post_meta( $this->get_id(), 'eml_fields', Crypt::get_instance()->encrypt( Helper::get_json( $fields ) ) );
	}

	/**
	 * Remove the fields for this file.
	 *
	 * @return void
	 */
	public function remove_fields(): void {
		delete_post_meta( $this->get_id(), 'eml_fields' );
	}

	/**
	 * Return decrypted fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// get the fields for this file.
		$field_encoded_json = get_post_meta( $this->get_id(), 'eml_fields', true );

		// bail if no string returned.
		if ( ! is_string( $field_encoded_json ) ) {
			return array();
		}

		// bail if string is empty.
		if ( empty( $field_encoded_json ) ) {
			return array();
		}

		// return decrypted string.
		$fields = json_decode( Crypt::get_instance()->decrypt( $field_encoded_json ), true );

		// bail if fields is not an array.
		if ( ! is_array( $fields ) ) {
			return array();
		}

		// return the fields.
		return $fields;
	}

	/**
	 * Return whether this file is using credentials.
	 *
	 * @return bool
	 */
	public function has_credentials(): bool {
		return ! empty( $this->get_fields() );
	}

	/**
	 * Switch hosting of this file from extern to local.
	 *
	 * The process:
	 * 1. Get the external URL as temporary local file.
	 * 2. Import the temporary file in media library.
	 * 3. Copy the settings of the newly uploaded file in media library to the target file.
	 * 4. Set attributes on target file to match as local saved external URL.
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
			// log this event.
			Log::get_instance()->create( __( 'Protocol handler for URL could not be found.', 'external-files-in-media-library' ), $this->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		// get the upload directory settings.
		$upload_dir = wp_get_upload_dir();

		// bail if baseurl is not a string.
		if ( ! is_string( $upload_dir['baseurl'] ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Upload directory could not be detected.', 'external-files-in-media-library' ), $this->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		/**
		 * Run tasks before we switch a file to local.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 */
		do_action( 'efml_switch_to_local_before' );

		// prevent duplicate check for this file.
		add_filter( 'efml_duplicate_check', array( $this, 'prevent_checks' ), 10, 2 );
		add_filter( 'efml_locale_file_check', array( $this, 'prevent_checks' ), 10, 2 );

		// get external file infos.
		$file_data = $protocol_handler_obj->get_url_infos();

		// remove prevent duplicate check for this file.
		remove_filter( 'efml_duplicate_check', array( $this, 'prevent_checks' ) );
		remove_filter( 'efml_locale_file_check', array( $this, 'prevent_checks' ) );

		// bail if no file data could be loaded.
		if ( empty( $file_data ) ) {
			// log this event.
			Log::get_instance()->create( __( 'File info for URL could not be loaded.', 'external-files-in-media-library' ), $this->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		// prepare import of the temporary file via WP-own functions.
		$file_array = array(
			'name'     => $this->get_title(),
			'type'     => $file_data[0]['mime-type'],
			'tmp_name' => '',
			'error'    => '0',
			'size'     => $file_data[0]['filesize'],
			'url'      => $this->get_url(),
		);

		// prepare import of post data for the attachment.
		$post_array = array(
			'post_author' => Helper::get_current_user_id(),
		);

		// get temp file for the external URL.
		$tmp_file = $protocol_handler_obj->get_temp_file( $this->get_url( true ), $wp_filesystem );

		// bail if no temp file could be loaded.
		if ( ! is_string( $tmp_file ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Temporary file could not be saved.', 'external-files-in-media-library' ), $this->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		// remove URL from attachment-setting to prevent new file names by WP.
		delete_post_meta( $this->get_id(), '_wp_attached_file' );

		// set the temp file for side load to insert the file in media library.
		$file_array['tmp_name'] = $tmp_file;

		// save the temporary file in media library and get its attachment ID.
		$temp_attachment_id = media_handle_sideload( $file_array, 0, null, $post_array );

		// bail on error.
		if ( is_wp_error( $temp_attachment_id ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Inserting temporary file resulted in error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $temp_attachment_id ) . '</code>', $this->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		// get meta-data from uploaded temporary file.
		$temp_meta_data = wp_get_attachment_metadata( $temp_attachment_id );

		// create an array for meta-data of we got "false" from metadata request.
		if ( ! is_array( $temp_meta_data ) ) {
			$temp_meta_data = array();
		}

		// get the local URL of the temporary file.
		$local_full_path = wp_get_attachment_url( $temp_attachment_id );

		// bail if no URL returned.
		if ( empty( $local_full_path ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Local URL could not be loaded.', 'external-files-in-media-library' ), $this->get_url( true ), 'error' );

			// delete the temporary attachment.
			wp_delete_attachment( $temp_attachment_id, true );

			// do nothing more.
			return false;
		}

		// get the local path as relative path.
		$local_path = str_replace( trailingslashit( $upload_dir['baseurl'] ), '', $local_full_path );

		// set our local relative path on metadata.
		$temp_meta_data['file'] = $local_path;

		// copy the relevant settings of the new uploaded file to the original file.
		wp_update_attachment_metadata( $this->get_id(), $temp_meta_data );

		// set the local relative path on the file.
		update_post_meta( $this->get_id(), '_wp_attached_file', $local_path );

		// set setting for this file to local.
		$this->set_is_local_saved( true );

		// secure the attached (and already updated) thumbnail files of this file.
		$files     = array( trailingslashit( $upload_dir['basedir'] ) . $local_path );
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
				$files[] = trailingslashit( $upload_dir['basedir'] ) . trailingslashit( dirname( $local_path ) ) . $meta_file['file'];
			}
		}

		// secure the files of this attachment by copying them to the tmp directory.
		foreach ( $files as $file ) {
			$destination = trailingslashit( get_temp_dir() ) . basename( $file );
			$wp_filesystem->copy( $file, $destination, true );
		}

		// delete the temporary uploaded file.
		wp_delete_attachment( $temp_attachment_id, true );

		// move the secured files back.
		foreach ( $files as $file ) {
			$source = trailingslashit( get_temp_dir() ) . basename( $file );
			$wp_filesystem->move( $source, $file, true );
		}

		// clear cache.
		$this->delete_cache();
		$this->delete_thumbs();

		/**
		 * Run tasks after we switch a file to local.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param int $attachment_id The attachment ID.
		 */
		do_action( 'efml_switch_to_local_after', $this->get_id() );

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
		if ( $this->has_credentials() ) {
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

		// get the metadata.
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
		$this->add_to_proxy();

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
		$wp_filesystem = Helper::get_wp_filesystem();

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

			// create the file path.
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
		return File_Types::get_instance()->get_type_object_by_mime_type( $this->get_mime_type(), $this );
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
		if ( empty( $date ) ) {
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

	/**
	 * Remove the import datetime.
	 *
	 * @return void
	 */
	public function remove_date(): void {
		delete_post_meta( $this->get_id(), 'eml_external_file_date' );
	}

	/**
	 * Return the thumbnail reset URL for this file.
	 *
	 * @return string
	 */
	public function get_thumbnail_reset_url(): string {
		return add_query_arg(
			array(
				'action' => 'eml_reset_thumbnails',
				'post'   => $this->get_id(),
				'nonce'  => wp_create_nonce( 'eml-reset-thumbnails' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return list of file data as debug info for this file.
	 *
	 * @return array<string,mixed>
	 */
	public function get_debug(): array {
		// get the protocol handler.
		$protocol_handler_obj  = $this->get_protocol_handler_obj();
		$protocol_handler_name = '';
		if ( $protocol_handler_obj instanceof Protocol_Base ) {
			$protocol_handler_name = $protocol_handler_obj->get_title();
		}

		// return the debug array of this file.
		return array(
			'url'         => $this->get_url(),
			'post_id'     => $this->get_id(),
			'title'       => $this->get_title(),
			'date'        => $this->get_date(),
			'filesize'    => $this->get_filesize(),
			'protocol'    => $protocol_handler_name,
			'file_type'   => $this->get_file_type_obj()->get_name(),
			'local_saved' => $this->get_file_type_obj()->is_local(),
			'proxied'     => $this->get_file_type_obj()->is_proxy_enabled(),
		);
	}

	/**
	 * Disable the check for unsafe URLs.
	 *
	 * @param array<string,mixed> $parsed_args List of args for URL request.
	 *
	 * @return array<string,mixed>
	 */
	public function disable_check_for_unsafe_urls( array $parsed_args ): array {
		$parsed_args['reject_unsafe_urls'] = false;
		return $parsed_args;
	}

	/**
	 * Return the name of the used service.
	 *
	 * @return string
	 */
	public function get_service_name(): string {
		// get the service name.
		$service_name = get_post_meta( $this->get_id(), 'eml_service', true );

		// if service name is empty, try to detect it via protocol handler.
		if ( empty( $service_name ) ) {
			// get the protocol handler for this file.
			$protocol_handler_obj = $this->get_protocol_handler_obj();

			// bail if object could not be loaded.
			if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
				return '';
			}

			// get its name.
			$service_name = $protocol_handler_obj->get_name();
		}

		// return the resulting service name.
		return $service_name;
	}

	/**
	 * Set the name of the used service.
	 *
	 * @param string $service_name The service name.
	 *
	 * @return void
	 */
	public function set_service_name( string $service_name ): void {
		update_post_meta( $this->get_id(), 'eml_service', $service_name );
	}
}
