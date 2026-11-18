<?php
/**
 * File to replace the local URLs of exported and migrated files in contents with their actual URLs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Object to replace the local URLs of exported and migrated files during the output.
 *
 * Files, which have been inserted into contents before their export, are linked there with their local URL
 * (e.g. /wp-content/uploads/2026/11/image-300x200.jpg). After the export their local files are deleted, so these
 * URLs are replaced with their actual URLs during the output. The contents in the database are never changed.
 *
 * The local path of each exported file is saved during its export. For files exported before 6.0.0 it is taken
 * from their GUID in the background, as the GUID still contains the URL of the upload.
 */
class Local_Urls {
	/**
	 * Name of the post meta with the local path of an exported file, relative to the uploads directory.
	 *
	 * An attachment could have several entries (e.g. the scaled file and its original image).
	 */
	public const META = 'efml_local_file';

	/**
	 * Name of the post meta with the file names of the sizes of an exported image (size name => file name).
	 *
	 * Used to restore the sizes with their former names, as their dimensions could differ by one pixel, if they are
	 * created again from the scaled image.
	 */
	public const SIZES_META = 'efml_local_sizes';

	/**
	 * Name of the option, which marks that local paths of exported files have been saved.
	 *
	 * 1 if there are some, 0 if not. If it is missing (e.g. after resetting the plugin), it is restored from the post meta.
	 */
	public const ACTIVE_OPTION = 'efml_local_urls';

	/**
	 * Name of the option with the state of saving the local paths of files exported before 6.0.0.
	 */
	public const BACKFILL_OPTION = 'efml_local_urls_backfill';

	/**
	 * Name of the cron event to save the local paths of files exported before 6.0.0.
	 */
	public const CRON_HOOK = 'efml_local_urls_backfill';

	/**
	 * Maximum number of files, whose actual URLs are returned by one request of the editors.
	 */
	private const REST_MAX_FILES = 100;

	/**
	 * Name of the file in the uploads directory, which is requested by the check in the Site Health.
	 */
	public const REDIRECT_PROBE = 'efml-redirect-check.jpg';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local_Urls
	 */
	private static ?Local_Urls $instance = null;

	/**
	 * Cache of the attachment IDs of local files (relative to the uploads directory) in this request, 0 if none.
	 *
	 * @var array<string,int>
	 */
	private array $file_cache = array();

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
	 * @return Local_Urls
	 */
	public static function get_instance(): Local_Urls {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// save the local paths of files exported before 6.0.0 in the background.
		add_action( self::CRON_HOOK, array( $this, 'run_backfill' ) );
		add_action( 'init', array( $this, 'schedule_backfill' ), 30 );

		// bail if no file needs the replacement of its local URLs.
		if ( ! $this->is_active() ) {
			return;
		}

		/**
		 * Filter the hooks, whose content is checked for local URLs of exported and migrated files.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $hooks List of hooks.
		 */
		$hooks = apply_filters( 'efml_local_url_hooks', array( 'the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content', 'theme_mod_header_image', 'theme_mod_background_image', 'wp_get_custom_css' ) );

		// change the local URLs in the output.
		foreach ( $hooks as $hook ) {
			add_filter( $hook, array( $this, 'replace_urls' ), 100 );
		}

		// use the attachment ID of images in contents to prevent queries for them.
		add_filter( 'wp_content_img_tag', array( $this, 'add_image_to_cache' ), 10, 3 );

		// redirect requests for local files, which do not exist anymore, to their actual URL (e.g. from the block
		// editor, from JavaScript or from static CSS files), before WordPress tries to guess another URL.
		add_action( 'template_redirect', array( $this, 'redirect_missing_upload' ), 1 );
		add_filter( 'efml_site_health_endpoints', array( $this, 'add_site_health_endpoint' ) );

		// show these files in the editors also, if the web server does not pass requests for missing files to
		// WordPress: only their display is changed, not the contents.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'enqueue_block_assets', array( $this, 'add_block_editor_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_classic_editor_script' ) );
	}

	/**
	 * Return whether files exist, whose local URLs must be replaced.
	 *
	 * @return bool
	 */
	private function is_active(): bool {
		// get the marker.
		$active = get_option( self::ACTIVE_OPTION );

		// restore the marker from the data, if it is missing (e.g. after resetting the plugin, which keeps the post meta of exported files).
		if ( false === $active ) {
			$active = $this->has_local_paths() ? 1 : 0;
			update_option( self::ACTIVE_OPTION, $active );
		}

		return absint( $active ) > 0 || absint( get_option( Migrations::COUNT_OPTION ) ) > 0;
	}

	/**
	 * Return whether any attachment has saved local paths.
	 *
	 * @return bool
	 */
	private function has_local_paths(): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `meta_id` FROM %i WHERE `meta_key` = %s LIMIT 1', $wpdb->postmeta, self::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
	}

	/**
	 * Save the local paths of the given attachment, before it is changed to an exported file.
	 *
	 * Must be called, before its attached file is changed to its external URL.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function save_local_paths( int $attachment_id ): void {
		// get the attached file.
		$file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );

		// bail if it is not a local file.
		if ( '' === $file || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			return;
		}

		// get the path relative to the uploads directory.
		$file = _wp_relative_upload_path( $file );

		// collect the paths: the file and its original image, if it has been scaled or rotated.
		$paths    = array( $file );
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		if ( is_array( $metadata ) && ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$directory = dirname( $file );
			$paths[]   = ( '.' === $directory ? '' : trailingslashit( $directory ) ) . wp_basename( $metadata['original_image'] );
		}

		// save them.
		$this->set_local_paths( $attachment_id, $paths );

		// save the file names of the sizes.
		$sizes = array();
		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size ) {
				if ( is_array( $size ) && ! empty( $size['file'] ) && is_string( $size['file'] ) ) {
					$sizes[ (string) $size_name ] = wp_basename( $size['file'] );
				}
			}
		}
		if ( ! empty( $sizes ) ) {
			update_post_meta( $attachment_id, self::SIZES_META, $sizes );
		} else {
			delete_post_meta( $attachment_id, self::SIZES_META );
		}
	}

	/**
	 * Add the given local path as actual path of the given exported file, e.g. after it has been edited or renamed.
	 *
	 * The former paths are kept, so local URLs of the former file in contents are still replaced.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $path          The path relative to the uploads directory.
	 *
	 * @return void
	 */
	public function add_local_path( int $attachment_id, string $path ): void {
		$paths = $this->get_local_paths( $attachment_id );
		array_unshift( $paths, $path );
		$this->set_local_paths( $attachment_id, array_values( array_unique( $paths ) ) );
	}

	/**
	 * Save the file names of the sizes of the given exported image, e.g. after its sizes have been created again.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param array<string,string> $sizes         The file names of the sizes (size name => file name).
	 *
	 * @return void
	 */
	public function set_local_sizes( int $attachment_id, array $sizes ): void {
		if ( empty( $sizes ) ) {
			delete_post_meta( $attachment_id, self::SIZES_META );
			return;
		}
		update_post_meta( $attachment_id, self::SIZES_META, $sizes );
	}

	/**
	 * Return the file names of the sizes of the given exported image, saved during its export.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string>
	 */
	public function get_local_sizes( int $attachment_id ): array {
		$sizes = get_post_meta( $attachment_id, self::SIZES_META, true );
		return is_array( $sizes ) ? array_map( 'strval', $sizes ) : array();
	}

	/**
	 * Save the given local paths for the given attachment.
	 *
	 * @param int               $attachment_id The attachment ID.
	 * @param array<int,string> $paths         The paths relative to the uploads directory.
	 *
	 * @return void
	 */
	private function set_local_paths( int $attachment_id, array $paths ): void {
		// remove the previous paths.
		delete_post_meta( $attachment_id, self::META );

		// add the paths.
		foreach ( array_unique( array_filter( $paths ) ) as $path ) {
			add_post_meta( $attachment_id, self::META, $path );
			$this->file_cache[ $path ] = $attachment_id;
		}

		// mark that local URLs must be replaced.
		if ( 0 === absint( get_option( self::ACTIVE_OPTION ) ) ) {
			update_option( self::ACTIVE_OPTION, 1 );
		}
	}

	/**
	 * Schedule the saving of local paths of files exported before 6.0.0, if it is not done yet.
	 *
	 * This runs also on websites, which are already on 6.0.0 (e.g. development websites), as the update routine
	 * runs only on version changes.
	 *
	 * @return void
	 */
	public function schedule_backfill(): void {
		// bail if it is done.
		$state = get_option( self::BACKFILL_OPTION );
		if ( is_array( $state ) && ! empty( $state['done'] ) ) {
			return;
		}

		// bail if it is already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// schedule it.
		wp_schedule_single_event( time(), self::CRON_HOOK );
	}

	/**
	 * Save the local paths of the next exported files without them, taken from their GUID.
	 *
	 * @return void
	 */
	public function run_backfill(): void {
		global $wpdb;

		// get the state.
		$state = get_option( self::BACKFILL_OPTION );
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		// bail if it is done.
		if ( ! empty( $state['done'] ) ) {
			return;
		}

		/**
		 * Filter the number of exported files, whose local paths are saved in one step in the background.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $size The number of files.
		 */
		$size = max( 1, absint( apply_filters( 'efml_local_urls_backfill_size', 500 ) ) );

		// get the next exported files without local paths.
		$cursor = isset( $state['cursor'] ) ? absint( $state['cursor'] ) : 0;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT p.`ID`, p.`guid` FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`ID` > %d AND NOT EXISTS ( SELECT 1 FROM %i AS l WHERE l.`post_id` = p.`ID` AND l.`meta_key` = %s ) ORDER BY p.`ID` ASC LIMIT %d', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', $cursor, $wpdb->postmeta, self::META, $size ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// save the local path of each file.
		foreach ( (array) $rows as $row ) {
			// remember the position.
			$state['cursor'] = absint( $row['ID'] );

			// get the path from the GUID (the URL of the upload).
			$path = $this->get_relative_path( (string) $row['guid'] );

			// bail if this is not a URL in the uploads directory.
			if ( '' === $path ) {
				continue;
			}

			// save it.
			$this->set_local_paths( absint( $row['ID'] ), array( $path ) );
		}

		// mark it as done, if this has been the last part.
		if ( count( (array) $rows ) < $size ) {
			$state['done'] = true;
		}

		// save the state.
		update_option( self::BACKFILL_OPTION, $state, false );

		// continue with the next part.
		if ( empty( $state['done'] ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Return the path of the given URL relative to the uploads directory, or an empty string if it is not in it.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function get_relative_path( string $url ): string {
		// get the URL of the uploads directory without its scheme.
		$base_url = trailingslashit( $this->get_base_url() );

		// get the given URL without its scheme, query and fragment.
		$url = (string) preg_replace( '#^https?:#i', '', (string) strtok( $url, '?#' ) );

		// bail if it is not in the uploads directory.
		if ( ! str_starts_with( $url, $base_url ) ) {
			return '';
		}

		// return the path.
		return rawurldecode( substr( $url, strlen( $base_url ) ) );
	}

	/**
	 * Return the URL of the uploads directory without its scheme.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		return (string) preg_replace( '#^https?:#i', '', wp_get_upload_dir()['baseurl'] );
	}

	/**
	 * Replace the local URLs of exported and migrated files in the given content with their actual URLs.
	 *
	 * @param mixed $content The content.
	 *
	 * @return mixed
	 */
	public function replace_urls( mixed $content ): mixed {
		// bail if this is not a string.
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		// get the URL of the uploads directory without its scheme and its path.
		$base_url  = $this->get_base_url();
		$base_path = $this->get_base_path();

		// bail if the content does not contain any URL of the uploads directory (in any notation). The path is
		// part of the URL, if the uploads directory has one; otherwise only URLs with domain are possible.
		$needle = '' !== $base_path ? $base_path : $base_url;
		if ( ! str_contains( $content, $needle ) && ! str_contains( $content, $this->escape_slashes( $needle ) ) ) {
			return $content;
		}

		// get the URLs of local files in all supported notations: with domain, without domain (e.g.
		// "/wp-content/uploads/…" in a style attribute) and both with escaped slashes like in JSON (e.g. in
		// "data-*" attributes of page builders).
		$path_chars = '[^\s"\'<>()?\#,\\\\]+';
		$patterns   = array(
			'#(?:https?:)?' . preg_quote( $base_url, '#' ) . '/(' . $path_chars . ')#i' => false,
			'#(?:https?:)?' . preg_quote( $this->escape_slashes( $base_url ), '#' ) . '\\\\/((?:[^\s"\'<>()?\#,\\\\]|\\\\/)+)#i' => true,
		);
		if ( '' !== $base_path ) {
			$path_pattern                      = '#(?<=[\s"\'(=,;])' . preg_quote( $base_path, '#' ) . '/(' . $path_chars . ')#i';
			$escaped_path_pattern              = '#(?<=["\';])' . preg_quote( $this->escape_slashes( $base_path ), '#' ) . '\\\\/((?:[^\s"\'<>()?\#,\\\\]|\\\\/)+)#i';
			$patterns[ $path_pattern ]         = false;
			$patterns[ $escaped_path_pattern ] = true;
		}
		$found = array();
		foreach ( $patterns as $pattern => $escaped ) {
			if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $match ) {
				$found[ $match[0] ] = array(
					'file'    => $escaped ? str_replace( '\\/', '/', $match[1] ) : $match[1],
					'escaped' => $escaped,
				);
			}
		}

		// bail if no URL has been found.
		if ( empty( $found ) ) {
			return $content;
		}

		// get the attachments of these files.
		$attachments = $this->get_attachments_of_files( array_values( array_unique( array_column( $found, 'file' ) ) ) );

		// bail if none of them is exported or migrated.
		if ( empty( $attachments ) ) {
			return $content;
		}

		// collect the replacements in the notation of the found URL.
		$replacements = array();
		foreach ( $found as $search => $entry ) {
			// bail if this file is not exported or migrated.
			if ( empty( $attachments[ $entry['file'] ] ) ) {
				continue;
			}

			// get the actual URL.
			$url = $this->get_url_of_local_file( $attachments[ $entry['file'] ], $entry['file'] );

			// add it.
			if ( '' !== $url ) {
				$replacements[ $search ] = $entry['escaped'] ? $this->escape_slashes( $url ) : $url;
			}
		}

		// return the changed content.
		return strtr( $content, $replacements );
	}

	/**
	 * Return the path of the URL of the uploads directory, e.g. "/wp-content/uploads".
	 *
	 * @return string
	 */
	private function get_base_path(): string {
		return untrailingslashit( (string) wp_parse_url( wp_get_upload_dir()['baseurl'], PHP_URL_PATH ) );
	}

	/**
	 * Return the given text with escaped slashes, like in JSON.
	 *
	 * @param string $text The text.
	 *
	 * @return string
	 */
	private function escape_slashes( string $text ): string {
		return str_replace( '/', '\\/', $text );
	}

	/**
	 * Redirect a request for a local file of an exported file, which does not exist anymore, to its actual URL.
	 *
	 * This covers all usages of the local URL, which are not changed during the output: e.g. the block editor, which
	 * uses the saved content, JavaScript, JSON, static CSS files or links from other websites. It works only, if the
	 * web server passes requests for missing files to WordPress (see the check in the Site Health).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return void
	 */
	public function redirect_missing_upload(): void {
		// get the requested file relative to the uploads directory: if WordPress handles such a request, the file
		// does not exist (without pretty permalinks, the request is not marked as 404 by WordPress).
		$file = $this->get_requested_upload();

		// bail if it is not in the uploads directory.
		if ( '' === $file ) {
			return;
		}

		// answer the check of the Site Health.
		if ( self::REDIRECT_PROBE === $file ) {
			status_header( 204 );
			header( 'X-EFML-Upload-Redirect: 1' );
			exit;
		}

		// get the actual URL of this file.
		$url = $this->get_redirect_url( $file );

		// bail if it is not a local file of an exported file.
		if ( '' === $url ) {
			return;
		}

		// redirect temporarily, so browsers and CDNs do not save it permanently.
		if ( wp_redirect( $url, 302, 'External Files in Media Library' ) ) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Return the actual URL for the given local file of an exported or migrated file, which does not exist anymore.
	 * Returns an empty string, if the file is not a local file of such a file.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $file The file relative to the uploads directory.
	 *
	 * @return string
	 */
	public function get_redirect_url( string $file ): string {
		$true = true;
		/**
		 * Filter whether requests for local files of exported files, which do not exist anymore, are redirected to
		 * their actual URL.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool   $true True to redirect.
		 * @param string $file The requested file relative to the uploads directory.
		 */
		if ( ! apply_filters( 'efml_redirect_missing_uploads', $true, $file ) ) {
			return '';
		}

		// bail if the local file exists (e.g. it has been kept or uploaded again).
		if ( file_exists( trailingslashit( wp_get_upload_dir()['basedir'] ) . $file ) ) {
			return '';
		}

		// get the attachment of this file.
		$attachments = $this->get_attachments_of_files( array( $file ) );

		// bail if it is not an exported or migrated file.
		if ( empty( $attachments[ $file ] ) ) {
			return '';
		}

		// get its actual URL.
		$url = $this->get_url_of_local_file( $attachments[ $file ], $file );

		// bail if it is the requested URL (to prevent a loop).
		if ( '' === $url || $this->get_relative_path( $url ) === $file ) {
			return '';
		}

		// return the URL.
		return $url;
	}

	/**
	 * Return the requested file relative to the uploads directory, or an empty string, if the request is not for
	 * a file in it.
	 *
	 * @return string
	 */
	private function get_requested_upload(): string {
		// get the requested path.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$base_path   = $this->get_base_path();

		// bail if the uploads directory has no own path (e.g. on its own domain), as requests for its files
		// could not be distinguished from other requests.
		if ( '' === $base_path ) {
			return '';
		}

		// bail if it is not in the uploads directory.
		$base_path = trailingslashit( $base_path );
		if ( '' === $path || ! str_starts_with( $path, $base_path ) ) {
			return '';
		}

		// return the file, if it is valid.
		return $this->get_valid_file( rawurldecode( substr( $path, strlen( $base_path ) ) ) );
	}

	/**
	 * Return the given file relative to the uploads directory, if it is valid, otherwise an empty string.
	 *
	 * @param string $file The file relative to the uploads directory.
	 *
	 * @return string
	 */
	private function get_valid_file( string $file ): string {
		// bail on empty files, relative paths or directories.
		if ( '' === $file || str_contains( $file, '..' ) || str_contains( $file, "\0" ) || str_ends_with( $file, '/' ) || str_starts_with( $file, '/' ) ) {
			return '';
		}

		// return the file.
		return $file;
	}

	/**
	 * Register the endpoint, which returns the actual URLs of local files for the editors.
	 *
	 * @return void
	 */
	public function register_rest_route(): void {
		register_rest_route(
			'efml/v1',
			'/local-file-urls/',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_urls_via_rest' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'files' => array(
						'type'     => 'array',
						'items'    => array(
							'type' => 'string',
						),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Return the actual URLs of the given local files (relative to the uploads directory) for the editors.
	 *
	 * Files, which exist locally or which are not exported or migrated, get an empty string.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_urls_via_rest( WP_REST_Request $request ): WP_REST_Response {
		// get the valid files.
		$files = array();
		foreach ( (array) $request->get_param( 'files' ) as $file ) {
			$file = is_string( $file ) ? $this->get_valid_file( $file ) : '';
			if ( '' !== $file ) {
				$files[] = $file;
			}
		}
		$files = array_slice( array_values( array_unique( $files ) ), 0, self::REST_MAX_FILES );

		// load their attachments at once.
		if ( ! empty( $files ) ) {
			$this->get_attachments_of_files( $files );
		}

		// get their actual URLs.
		$urls = array();
		foreach ( $files as $file ) {
			$urls[ $file ] = $this->get_redirect_url( $file );
		}

		// return them as object, also if the list is empty.
		return new WP_REST_Response( (object) $urls );
	}

	/**
	 * Add the script, which shows exported images in the block editor, also if their old local URL is used.
	 *
	 * It is added to the iframe of the editor, too.
	 *
	 * @return void
	 */
	public function add_block_editor_script(): void {
		// bail if this is not the backend (the hook is used in the frontend, too).
		if ( ! is_admin() ) {
			return;
		}

		// add the script.
		$this->add_editor_script();
	}

	/**
	 * Add the script, which shows exported images in the classic editor, also if their old local URL is used.
	 *
	 * @param string $hook The actual page.
	 *
	 * @return void
	 */
	public function add_classic_editor_script( string $hook ): void {
		// bail if this is not the editor of posts.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// add the script.
		$this->add_editor_script();
	}

	/**
	 * Add the script for the editors.
	 *
	 * @return void
	 */
	private function add_editor_script(): void {
		// bail if the user could not edit contents.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// register the script once.
		if ( ! wp_script_is( 'efml-local-urls', 'registered' ) ) {
			wp_register_script(
				'efml-local-urls',
				Helper::get_plugin_url() . 'admin/local-urls.js',
				array(),
				Helper::get_file_version( Helper::get_plugin_dir() . 'admin/local-urls.js' ),
				false
			);
			wp_add_inline_script(
				'efml-local-urls',
				'window.efmlLocalUrlsConfig = ' . wp_json_encode(
					array(
						'endpoint' => rest_url( 'efml/v1/local-file-urls/' ),
						'nonce'    => wp_create_nonce( 'wp_rest' ),
						'baseUrl'  => trailingslashit( wp_get_upload_dir()['baseurl'] ),
						'siteUrl'  => home_url( '/' ),
					)
				) . ';',
				'before'
			);
		}

		// add the script.
		wp_enqueue_script( 'efml-local-urls' );
	}

	/**
	 * Add the check of the redirection of missing local files to the Site Health.
	 *
	 * @param array<int,array<string,mixed>> $endpoints List of endpoints.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function add_site_health_endpoint( array $endpoints ): array {
		$endpoints[] = array(
			'label'     => Helper::get_plugin_name() . ' ' . __( 'Local URLs of exported files', 'external-files-in-media-library' ),
			'namespace' => 'efml/v1',
			'route'     => '/local-urls/',
			'callback'  => array( $this, 'check_redirect' ),
			'args'      => array(),
		);

		return $endpoints;
	}

	/**
	 * Return the result of the check, whether requests for missing local files reach WordPress, so they could be
	 * redirected to the actual URL of the exported files.
	 *
	 * @return array<string,mixed>
	 */
	public function check_redirect(): array {
		// define default results.
		$result = array(
			'label'       => __( 'Old URLs of exported files are redirected', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'External Files in Media Library', 'external-files-in-media-library' ),
				'color' => 'gray',
			),
			'description' => '<p>' . __( 'Requests for local files of exported files, which do not exist anymore, reach WordPress and are redirected to the actual URL of the files. So images are shown also where their old URL is still used, e.g. in the block editor, in JavaScript or in CSS files of page builders.', 'external-files-in-media-library' ) . '</p>',
			'actions'     => '',
			'test'        => 'efml_test_local_urls',
		);

		// request the probe file.
		$response = wp_remote_get(
			trailingslashit( wp_get_upload_dir()['baseurl'] ) . self::REDIRECT_PROBE,
			array(
				'timeout'     => 5,
				'sslverify'   => false,
				'redirection' => 0,
			)
		);

		// show a hint if the check could not be run.
		if ( is_wp_error( $response ) ) {
			$result['label']       = __( 'Redirection of old URLs of exported files could not be checked', 'external-files-in-media-library' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . __( 'We tried to request a test file in your uploads directory, but the request did not reach your site. This often happens if loopback requests are blocked, which is common on local or firewalled installations.', 'external-files-in-media-library' ) . '</p>';
			return $result;
		}

		// return the good result, if WordPress has answered.
		if ( '1' === wp_remote_retrieve_header( $response, 'x-efml-upload-redirect' ) ) {
			return $result;
		}

		// the web server answers itself: explain the consequence and how to fix it.
		$result['label']        = __( 'Old URLs of exported files are not redirected', 'external-files-in-media-library' );
		$result['status']       = 'recommended';
		$result['description']  = '<p>' . __( 'Your web server answers requests for missing files in your uploads directory itself, without WordPress. So old URLs of exported files, whose local files have been deleted, cannot be redirected to their actual URL. Your contents are not affected, as their URLs are replaced during the output, and the editors show these images anyway. But e.g. JavaScript or CSS files of page builders, which still use the old URLs, do not show them.', 'external-files-in-media-library' ) . '</p>';
		$result['description'] .= $this->get_redirect_guide();
		$result['actions']      = '<p><a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank">' . __( 'Ask for help in our support forum', 'external-files-in-media-library' ) . '</a></p>';

		return $result;
	}

	/**
	 * Return the help how to pass requests for missing files in the uploads directory to WordPress.
	 *
	 * @return string
	 */
	private function get_redirect_guide(): string {
		// explain the rule for nginx.
		$html  = '<p>' . __( 'Pass requests for missing files in your uploads directory to WordPress. With nginx, the rule in "location /" is not enough: nginx uses the most specific location for a request, e.g. a location for images (for caching, "expires" or image optimizers). Each of these locations needs "/index.php?$args" as last entry of "try_files" instead of "=404":', 'external-files-in-media-library' ) . '</p>';
		$html .= $this->get_code_block( "location ~* \\.(jpe?g|png|gif|webp|avif|svg)$ {\n    expires 365d;\n    try_files \$uri /index.php?\$args;\n}" );

		// add the hint for Converter for Media, whose rules for nginx answer missing images with 404.
		if ( Helper::is_plugin_active( 'webp-converter-for-media/webp-converter-for-media.php' ) ) {
			$html .= '<p><strong>' . __( 'Converter for Media is active:', 'external-files-in-media-library' ) . '</strong> ' . __( 'if you have added its rules to your nginx configuration, replace the last line of its "try_files" as follows:', 'external-files-in-media-library' ) . '</p>';
			$html .= $this->get_code_block( "    try_files\n        /wp-content/uploads-webpc/\$path.\$ext\$ext_avif\n        /wp-content/uploads-webpc/\$path.\$ext\$ext_webp\n        \$uri /index.php?\$args;" );
		}

		// add the hints for Apache and without access to the configuration.
		$html .= '<p>' . __( 'With Apache, the rules of WordPress pass these requests to WordPress already. Check for additional rules in the .htaccess file of your uploads directory, e.g. from security or cache plugins, which answer them themselves.', 'external-files-in-media-library' ) . '</p>';
		$html .= '<p>' . __( 'Reload or restart the web server after the change and run the check again. If you cannot change the server configuration, ask your hosting provider for it or keep the local files during the export.', 'external-files-in-media-library' ) . '</p>';

		// return it as help to expand.
		return '<details class="efml-setup-guide"><summary><span class="dashicons dashicons-editor-help"></span> ' . esc_html__( 'How do I fix this?', 'external-files-in-media-library' ) . '</summary><div class="efml-setup-guide-content">' . $html . '</div></details>';
	}

	/**
	 * Return the given code as field to copy it, like in the help of the services.
	 *
	 * @param string $code The code.
	 *
	 * @return string
	 */
	private function get_code_block( string $code ): string {
		$html  = '<div class="efml-setup-guide-code">';
		$html .= '<textarea readonly="readonly" rows="' . absint( substr_count( $code, "\n" ) + 1 ) . '">' . esc_textarea( $code ) . '</textarea>';
		$html .= '<button type="button" class="button button-secondary efml-setup-guide-copy">' . esc_html__( 'Copy', 'external-files-in-media-library' ) . '</button>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Return the attachments of the given local files.
	 *
	 * Files of sizes (e.g. image-300x200.jpg) are assigned to the attachment of their original file.
	 *
	 * @param array<int,string> $files The files relative to the uploads directory.
	 *
	 * @return array<string,int>
	 */
	private function get_attachments_of_files( array $files ): array {
		global $wpdb;

		// collect each file and the original file of sizes.
		$candidates = array();
		foreach ( $files as $file ) {
			$candidates[] = $file;
			$original     = $this->get_original_of_size( $file );
			if ( '' !== $original ) {
				$candidates[] = $original;
			}
		}
		$candidates = array_unique( $candidates );

		// get the files, which are not in the cache.
		$missing = array_values( array_diff( $candidates, array_keys( $this->file_cache ) ) );

		// load them.
		if ( ! empty( $missing ) ) {
			// mark them as unknown.
			foreach ( $missing as $file ) {
				$this->file_cache[ $file ] = 0;
			}

			// get the exported and migrated ones.
			$placeholders = implode( ', ', array_fill( 0, count( $missing ), '%s' ) );
			$results      = $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id`, `meta_value` FROM %i WHERE `meta_key` IN ( %s, %s ) AND `meta_value` IN (' . $placeholders . ')', array_merge( array( $wpdb->postmeta, self::META, Migrations::FILE_META ), $missing ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.SlowDBQuery
			foreach ( (array) $results as $result ) {
				$this->file_cache[ (string) $result['meta_value'] ] = absint( $result['post_id'] );
			}
		}

		// assign each file to its attachment.
		$attachments = array();
		foreach ( $files as $file ) {
			// use the file itself.
			if ( ! empty( $this->file_cache[ $file ] ) ) {
				$attachments[ $file ] = $this->file_cache[ $file ];
				continue;
			}

			// use the original file of a size.
			$original = $this->get_original_of_size( $file );
			if ( '' !== $original && ! empty( $this->file_cache[ $original ] ) ) {
				$attachments[ $file ] = $this->file_cache[ $original ];
			}
		}

		// return the list.
		return $attachments;
	}

	/**
	 * Return the original file of the given file of a size (e.g. image.jpg for image-300x200.jpg).
	 *
	 * Returns an empty string, if it is not the file of a size.
	 *
	 * @param string $file The file relative to the uploads directory.
	 *
	 * @return string
	 */
	private function get_original_of_size( string $file ): string {
		// bail if this is not the name of a size.
		if ( ! preg_match( '#^(.+)-\d+x\d+(\.[a-z0-9]+)$#i', $file, $match ) ) {
			return '';
		}

		// return the original file.
		return $match[1] . $match[2];
	}

	/**
	 * Return the actual URL of the given local file of an exported or migrated attachment.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file          The file relative to the uploads directory.
	 *
	 * @return string
	 */
	private function get_url_of_local_file( int $attachment_id, string $file ): string {
		// use the data before the migration for migrated files.
		if ( is_array( get_post_meta( $attachment_id, Migrations::BACKUP_META, true ) ) ) {
			return Migrations::get_instance()->get_url_of_local_file( $attachment_id, $file );
		}

		// bail if the file is not an exported file anymore (e.g. it has been switched to a local file).
		if ( empty( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			return '';
		}

		// get the URL of a size.
		if ( preg_match( '#-(\d+)x(\d+)\.[a-z0-9]+$#i', $file, $match ) && ! in_array( $file, $this->get_local_paths( $attachment_id ), true ) ) {
			$image = wp_get_attachment_image_src( $attachment_id, array( absint( $match[1] ), absint( $match[2] ) ) );
			if ( is_array( $image ) && ! empty( $image[0] ) ) {
				return (string) $image[0];
			}
		}

		// return the URL of the file.
		return (string) wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Return the local paths of the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	public function get_local_paths( int $attachment_id ): array {
		return array_map( 'strval', (array) get_post_meta( $attachment_id, self::META, false ) );
	}

	/**
	 * Return all local files of the given exported or migrated attachment, relative to the uploads directory: the
	 * file itself, its original image and the files of its sizes.
	 *
	 * Used e.g. to check whether contents use the attachment via one of its former local URLs.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	public function get_all_local_files( int $attachment_id ): array {
		// get the main files of exported and migrated files.
		$files = array_merge( $this->get_local_paths( $attachment_id ), array_map( 'strval', (array) get_post_meta( $attachment_id, Migrations::FILE_META, false ) ) );
		$files = array_values( array_filter( array_unique( $files ) ) );

		// bail if there are none.
		if ( empty( $files ) ) {
			return array();
		}

		// get the names of the sizes and of the original image, which are saved in the same folder.
		$names    = array_values( $this->get_local_sizes( $attachment_id ) );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) ) {
			$names = array_merge( $names, $this->get_file_names_of_metadata( $metadata ) );
		}

		// add them for the folder of the first file.
		$folder = dirname( $files[0] );
		foreach ( array_unique( $names ) as $name ) {
			$files[] = ( '.' === $folder ? '' : trailingslashit( $folder ) ) . wp_basename( $name );
		}

		// return the list.
		return array_values( array_unique( $files ) );
	}


	/**
	 * Return the file names of the original image and of the sizes in the given metadata of an attachment.
	 *
	 * @param array<string,mixed> $metadata The metadata of the attachment.
	 *
	 * @return array<int,string>
	 */
	private function get_file_names_of_metadata( array $metadata ): array {
		// collect the names.
		$names = array();

		// add the original image.
		if ( ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ) {
			$names[] = $metadata['original_image'];
		}

		// bail if there are no sizes.
		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $names;
		}

		// add the sizes.
		foreach ( $metadata['sizes'] as $size ) {
			if ( ! is_array( $size ) || empty( $size['file'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}
			$names[] = $size['file'];
		}

		// return the resulting list.
		return $names;
	}
	/**
	 * Return the ID of the exported or migrated attachment, to which the given local file belongs (also for the
	 * files of its sizes), or 0, if there is none.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $file The file relative to the uploads directory.
	 *
	 * @return int
	 */
	public function get_attachment_of_local_file( string $file ): int {
		$attachments = $this->get_attachments_of_files( array( $file ) );
		return $attachments[ $file ] ?? 0;
	}

	/**
	 * Add the local paths of images in contents to the cache, as their attachment ID is known there.
	 *
	 * This prevents queries for them during the replacement of the local URLs.
	 *
	 * @param string $filtered_image The HTML of the image.
	 * @param string $context        The context.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return string
	 */
	public function add_image_to_cache( string $filtered_image, string $context, int $attachment_id ): string {
		// bail if no attachment is known.
		if ( $attachment_id <= 0 ) {
			return $filtered_image;
		}

		// add its local paths.
		foreach ( $this->get_local_paths( $attachment_id ) as $path ) {
			$this->file_cache[ $path ] = $attachment_id;
		}

		// return the image unchanged.
		return $filtered_image;
	}

	/**
	 * Remove the data during the uninstallation (the post meta stays at the attachments, like the other data of exported files).
	 *
	 * @return void
	 */
	public function uninstall(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::ACTIVE_OPTION );
		delete_option( self::BACKFILL_OPTION );
	}
}
