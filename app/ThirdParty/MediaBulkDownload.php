<?php
/**
 * File to handle support for the plugin "Media Bulk Download".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocol_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle support for this plugin.
 *
 * The plugin packs the files of the selected attachments into a ZIP file. It reads them via their local file, which
 * external files do not have. During its requests, external files get a local file: the file in the cache of the
 * proxy or a temporary file, which is deleted at the end of the request.
 */
class MediaBulkDownload extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?MediaBulkDownload
	 */
	private static ?MediaBulkDownload $instance = null;

	/**
	 * The AJAX actions of the plugin, which create a ZIP file.
	 */
	private const AJAX_ACTIONS = array( 'pluginnests_mbdl_grid_download', 'pluginnests_mbdl_month_download', 'pluginnests_mbdl_type_download' );

	/**
	 * The bulk action of the plugin in the list view of the media library.
	 */
	private const BULK_ACTION = 'pluginnests_mbdl_download_zip';

	/**
	 * Marker for a request of the plugin.
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * The local files of external files in this request (attachment ID => path).
	 *
	 * @var array<int,string>
	 */
	private array $files = array();

	/**
	 * The names of the local files for the ZIP file (path => name).
	 *
	 * @var array<string,string>
	 */
	private array $names = array();

	/**
	 * The temporary files, which are deleted at the end of the request.
	 *
	 * @var array<int,string>
	 */
	private array $temp_files = array();

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
	 * @return MediaBulkDownload
	 */
	public static function get_instance(): MediaBulkDownload {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the plugin files of the supported plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array( 'media-bulk-download-lite/media-bulk-download.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if the plugin is not active.
		if ( ! $this->is_supported_plugin_active() ) {
			return;
		}

		// use the hooks.
		$this->add_hooks();
	}

	/**
	 * Add the hooks for this plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		// bail if they are added already.
		if ( false !== has_filter( 'get_attached_file', array( $this, 'get_local_file' ) ) ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'detect_ajax_request' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'detect_bulk_action' ), 1, 2 );
		add_filter( 'get_attached_file', array( $this, 'get_local_file' ), 20, 2 );
		add_filter( 'pluginnests_mbdl_zip_entry_name', array( $this, 'get_entry_name' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'delete_temp_files' ) );
	}

	/**
	 * Mark the AJAX requests of the plugin, which create a ZIP file.
	 *
	 * @return void
	 */
	public function detect_ajax_request(): void {
		// bail if this is not an AJAX request.
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// mark the request, if it is one of the plugin (the plugin checks the nonce and the capability).
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $action, self::AJAX_ACTIONS, true ) ) {
			$this->active = true;
		}
	}

	/**
	 * Mark the bulk action of the plugin in the list view of the media library.
	 *
	 * @param mixed  $redirect_url The URL to redirect to.
	 * @param string $action       The bulk action.
	 *
	 * @return mixed
	 */
	public function detect_bulk_action( mixed $redirect_url, string $action ): mixed {
		if ( self::BULK_ACTION === $action ) {
			$this->active = true;
		}
		return $redirect_url;
	}

	/**
	 * Return whether this is a request of the plugin.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->active;
	}

	/**
	 * Set the marker for a request of the plugin (e.g. for tests).
	 *
	 * @param bool $active The marker.
	 *
	 * @return void
	 */
	public function set_active( bool $active ): void {
		$this->active = $active;
	}

	/**
	 * Return a local file for external files during the requests of the plugin.
	 *
	 * @param false|string $file          The file.
	 * @param mixed        $attachment_id The attachment ID.
	 *
	 * @return false|string
	 */
	public function get_local_file( false|string $file, mixed $attachment_id ): false|string {
		// bail if this is not a request of the plugin.
		if ( ! $this->active ) {
			return $file;
		}

		// use the file of this request.
		$attachment_id = absint( $attachment_id );
		if ( isset( $this->files[ $attachment_id ] ) ) {
			return $this->files[ $attachment_id ];
		}

		// bail if this is not an external file or it is saved locally.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		if ( ! $external_file_obj->is_valid() || $external_file_obj->is_locally_saved() ) {
			return $file;
		}

		// get a local file.
		$local_file = $this->load_file( $external_file_obj );

		// bail if it could not be loaded.
		if ( '' === $local_file ) {
			return $file;
		}

		// save it for this request.
		$this->files[ $attachment_id ]                   = $local_file;
		$this->names[ wp_normalize_path( $local_file ) ] = $this->get_file_name( $external_file_obj );

		// return it.
		return $local_file;
	}

	/**
	 * Return a local file with the content of the given external file: the file in the cache of the proxy or a
	 * temporary file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return string
	 */
	private function load_file( File $external_file_obj ): string {
		// use the file in the cache of the proxy.
		if ( $external_file_obj->is_cached() ) {
			return $external_file_obj->get_cache_file();
		}

		// get the protocol handler.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if it could not be loaded.
		if ( ! $protocol_handler_obj instanceof Protocol_Base ) {
			return '';
		}

		// load the file as temporary file.
		$protocol_handler_obj->set_fields( $external_file_obj->get_fields() );
		add_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );
		$tmp_file = $protocol_handler_obj->get_temp_file( $external_file_obj->get_url( true ), Helper::get_wp_filesystem() );
		remove_filter( 'efml_http_header_args', array( $external_file_obj, 'disable_check_for_unsafe_urls' ) );

		// bail if it could not be loaded.
		if ( ! is_string( $tmp_file ) || ! file_exists( $tmp_file ) ) {
			/* translators: %1$s will be replaced by the name of a plugin. */
			Log::get_instance()->create( sprintf( __( 'The file could not be loaded for the ZIP file of %1$s.', 'external-files-in-media-library' ), 'Media Bulk Download' ), $external_file_obj->get_url( true ), 'error' );
			return '';
		}

		// delete it at the end of the request.
		$this->temp_files[] = $tmp_file;

		// return it.
		return $tmp_file;
	}

	/**
	 * Return the name of the given external file for the ZIP file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return string
	 */
	private function get_file_name( File $external_file_obj ): string {
		// use the name of the file in its URL.
		$name = sanitize_file_name( rawurldecode( wp_basename( (string) wp_parse_url( $external_file_obj->get_url( true ), PHP_URL_PATH ) ) ) );

		// use the title with the extension of the file, if the URL has no file name.
		if ( '' === $name || ! str_contains( $name, '.' ) ) {
			$name = sanitize_file_name( $external_file_obj->get_title() );
			if ( ! str_contains( $name, '.' ) ) {
				$name .= '.' . $external_file_obj->get_file_extension();
			}
		}

		// return the name.
		return $name;
	}

	/**
	 * Return the name of local files of external files in the ZIP file.
	 *
	 * @param mixed $entry_name The name.
	 * @param mixed $path       The path of the file.
	 *
	 * @return mixed
	 */
	public function get_entry_name( mixed $entry_name, mixed $path ): mixed {
		// bail if this is not the file of an external file.
		if ( ! is_string( $path ) || ! isset( $this->names[ wp_normalize_path( $path ) ] ) ) {
			return $entry_name;
		}

		// return its name.
		return $this->names[ wp_normalize_path( $path ) ];
	}

	/**
	 * Delete the temporary files of this request.
	 *
	 * @return void
	 */
	public function delete_temp_files(): void {
		foreach ( $this->temp_files as $tmp_file ) {
			wp_delete_file( $tmp_file );
		}
		$this->temp_files = array();
	}
}
