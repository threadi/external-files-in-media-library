<?php
/**
 * File to handle support for the plugin "Media Cleaner".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_REST_Request;

/**
 * Object to handle support for this plugin.
 *
 * Media Cleaner searches for media, which are not used in contents or whose file is missing, and deletes them on
 * request. External files have no local file and their usage could only partially be detected by it. Deleting such
 * a false hit would delete the file on its external sources, too. So this support:
 *
 * - uses the availability known by this plugin instead of the missing local file to detect broken entries.
 * - shows the former local files of exported files to Media Cleaner, so it checks their usage like for other
 *   local files, and prevents connections to external sources (e.g. FTP) during its scans.
 * - checks the former local files of exported files against the references found by Media Cleaner.
 * - considers files, which are imported from external sources, as used, as their usage could not be detected.
 * - considers local copies and working copies of exported files as used in the filesystem scan.
 */
class MediaCleaner extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?MediaCleaner
	 */
	private static ?MediaCleaner $instance = null;

	/**
	 * Marker for a request of Media Cleaner.
	 *
	 * @var bool
	 */
	private bool $is_scan = false;

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
	 * @return MediaCleaner
	 */
	public static function get_instance(): MediaCleaner {
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
		return array( 'media-cleaner/media-cleaner.php', 'media-cleaner-pro/media-cleaner-pro.php' );
	}

	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if Media Cleaner is not active.
		if ( ! Helper::is_plugin_active( 'media-cleaner/media-cleaner.php' ) && ! Helper::is_plugin_active( 'media-cleaner-pro/media-cleaner-pro.php' ) ) {
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
		if ( false !== has_filter( 'wpmc_check_media', array( $this, 'check_media' ) ) ) {
			return;
		}

		add_filter( 'rest_pre_dispatch', array( $this, 'detect_scan' ), 10, 3 );
		add_filter( 'get_attached_file', array( $this, 'prevent_stream_access' ), 20, 2 );
		add_filter( 'wpmc_is_file_broken', array( $this, 'is_file_broken' ), 10, 2 );
		add_filter( 'wpmc_check_media', array( $this, 'check_media' ), 20, 3 );
		add_filter( 'wpmc_check_file', array( $this, 'check_file' ), 1000, 2 );
	}

	/**
	 * Mark requests to the REST API of Media Cleaner, which runs its scans and deletions.
	 *
	 * @param mixed           $result  The result (null to run the callback of the route).
	 * @param mixed           $server  The REST server.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return mixed
	 */
	public function detect_scan( mixed $result, mixed $server, WP_REST_Request $request ): mixed {
		if ( str_starts_with( $request->get_route(), '/media-cleaner/' ) ) {
			$this->is_scan = true;
		}
		return $result;
	}

	/**
	 * Return whether this is a request of Media Cleaner (via its REST API or its WP CLI commands).
	 *
	 * @return bool
	 */
	public function is_scan(): bool {
		// a request via the REST API.
		if ( $this->is_scan ) {
			return true;
		}

		// bail if this is not a request via WP CLI.
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return false;
		}

		// return whether a command of Media Cleaner is used.
		$args = isset( $GLOBALS['argv'] ) && is_array( $GLOBALS['argv'] ) ? $GLOBALS['argv'] : array();
		foreach ( $args as $arg ) {
			if ( is_string( $arg ) && str_starts_with( $arg, 'media-cleaner' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the former local file of exported files during requests of Media Cleaner and no file for other external
	 * files.
	 *
	 * Media Cleaner checks the existence of the file with "file_exists()", which would connect to the external
	 * source for each file with a URL of a stream like "ftp://", and expects a file in the uploads directory. With
	 * the former local file of exported files, it finds their usage via their local URLs in contents and shows their
	 * path in its results.
	 *
	 * @param false|string $file          The file.
	 * @param mixed        $attachment_id The attachment ID.
	 *
	 * @return false|string
	 */
	public function prevent_stream_access( false|string $file, mixed $attachment_id ): false|string {
		// bail if this is not a request of Media Cleaner or no file is given.
		if ( ! is_string( $file ) || ! $this->is_scan() ) {
			return $file;
		}

		// bail if this is not an external file.
		$attachment_id = absint( $attachment_id );
		if ( ! Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			return $file;
		}

		// return the former local file of exported and migrated files.
		$files = Local_Urls::get_instance()->get_all_local_files( $attachment_id );
		if ( ! empty( $files ) ) {
			return trailingslashit( wp_get_upload_dir()['basedir'] ) . $files[0];
		}

		// return no file for other external files, as their URL is no file in the uploads directory.
		return false;
	}

	/**
	 * Return whether the file of an external file is broken: external files have no local file, so their
	 * availability, which this plugin checks, is used.
	 *
	 * @param mixed $is_broken     Whether the file is broken.
	 * @param mixed $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function is_file_broken( mixed $is_broken, mixed $attachment_id ): mixed {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( absint( $attachment_id ) );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $is_broken;
		}

		// return whether it is not available.
		return ! $external_file_obj->is_available() && ! $external_file_obj->is_locally_saved();
	}

	/**
	 * Check the usage of external files, which Media Cleaner considers as not used.
	 *
	 * Exported files are used, if their former local files are referenced. Files imported from external sources
	 * are considered as used, as their usage via their external URL could not be detected by Media Cleaner.
	 *
	 * @param mixed $in_use        Whether the media is used.
	 * @param mixed $attachment_id The attachment ID.
	 * @param mixed $is_broken     Whether its file is broken.
	 *
	 * @return mixed
	 */
	public function check_media( mixed $in_use, mixed $attachment_id, mixed $is_broken ): mixed {
		// bail if it is used or broken.
		if ( $in_use || $is_broken ) {
			return $in_use;
		}

		// get the external file object.
		$attachment_id     = absint( $attachment_id );
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $in_use;
		}

		// check the former local files of exported and migrated files.
		$files = Local_Urls::get_instance()->get_all_local_files( $attachment_id );
		if ( ! empty( $files ) ) {
			return $this->is_one_referenced( $files, $attachment_id );
		}

		/**
		 * Filter whether Media Cleaner may check the usage of files imported from external sources.
		 *
		 * Media Cleaner could not detect their usage via their external URL, so they are considered as used by
		 * default. Return true to use the result of Media Cleaner (e.g. if they are only used via their ID).
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $false False to consider them as used.
		 * @param int  $attachment_id The attachment ID.
		 */
		if ( apply_filters( 'efml_media_cleaner_check_external_files', false, $attachment_id ) ) {
			return $in_use;
		}

		// consider it as used.
		return true;
	}

	/**
	 * Return whether one of the given files is referenced in the references found by Media Cleaner.
	 *
	 * @param array<int,string> $files         The files relative to the uploads directory.
	 * @param int               $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_one_referenced( array $files, int $attachment_id ): bool {
		global $wpmc_core;

		// consider it as used, if the references could not be checked.
		if ( ! is_object( $wpmc_core ) || ! method_exists( $wpmc_core, 'reference_exists' ) ) {
			return true;
		}

		// check each file.
		foreach ( $files as $file ) {
			if ( $wpmc_core->reference_exists( $file, $attachment_id ) ) {
				return true;
			}
		}

		// none of them is used.
		return false;
	}

	/**
	 * Consider local files of exported files as used in the filesystem scan of Media Cleaner: local copies kept
	 * after the export, working copies for image optimizers, their variants (e.g. WebP) and backups.
	 *
	 * @param mixed $result True, if the file is used, otherwise the code of the issue.
	 * @param mixed $file   The file (relative to the uploads directory or absolute).
	 *
	 * @return mixed
	 */
	public function check_file( mixed $result, mixed $file ): mixed {
		// bail if the file is used or not given.
		if ( true === $result || ! is_string( $file ) || '' === $file ) {
			return $result;
		}

		// get the file relative to the uploads directory.
		$base = trailingslashit( wp_normalize_path( wp_get_upload_dir()['basedir'] ) );
		$file = wp_normalize_path( $file );
		if ( str_starts_with( $file, $base ) ) {
			$file = substr( $file, strlen( $base ) );
		}
		$file = ltrim( $file, '/' );

		// check the file itself, the image of a variant (e.g. image.jpg.webp or image.webp) and of a backup
		// (e.g. image.ewww-backup.jpg).
		$candidates = array( $file, (string) preg_replace( '#\.(webp|avif)$#i', '', $file ), (string) preg_replace( '#\.[a-z]+-backup(\.[a-z0-9]+)$#i', '$1', $file ) );
		foreach ( array( 'jpg', 'jpeg', 'png', 'gif' ) as $extension ) {
			$candidates[] = (string) preg_replace( '#\.(webp|avif)$#i', '.' . $extension, $file );
		}
		foreach ( array_unique( $candidates ) as $candidate ) {
			if ( '' !== $candidate && Local_Urls::get_instance()->get_attachment_of_local_file( $candidate ) > 0 ) {
				return true;
			}
		}

		// return the result of Media Cleaner.
		return $result;
	}
}
