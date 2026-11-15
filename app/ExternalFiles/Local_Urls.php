<?php
/**
 * File to replace the local URLs of exported and migrated files in contents with their actual URLs.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

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
		$hooks = apply_filters( 'efml_local_url_hooks', array( 'the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content' ) );

		// change the local URLs in the output.
		foreach ( $hooks as $hook ) {
			add_filter( $hook, array( $this, 'replace_urls' ), 100 );
		}

		// use the attachment ID of images in contents to prevent queries for them.
		add_filter( 'wp_content_img_tag', array( $this, 'add_image_to_cache' ), 10, 3 );
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

		// get the URL of the uploads directory without its scheme.
		$base_url = $this->get_base_url();

		// bail if the content does not contain any URL of the uploads directory.
		if ( ! str_contains( $content, $base_url ) ) {
			return $content;
		}

		// get the URLs of local files.
		if ( ! preg_match_all( '#(?:https?:)?' . preg_quote( $base_url, '#' ) . '/([^\s"\'<>()?\#,]+)#i', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		// get the attachments of these files.
		$attachments = $this->get_attachments_of_files( array_unique( array_column( $matches, 1 ) ) );

		// bail if none of them is exported or migrated.
		if ( empty( $attachments ) ) {
			return $content;
		}

		// collect the replacements.
		$replacements = array();
		foreach ( $matches as $match ) {
			// bail if this file is not exported or migrated.
			if ( empty( $attachments[ $match[1] ] ) ) {
				continue;
			}

			// get the actual URL.
			$url = $this->get_url_of_local_file( $attachments[ $match[1] ], $match[1] );

			// add it.
			if ( '' !== $url ) {
				$replacements[ $match[0] ] = $url;
			}
		}

		// return the changed content.
		return strtr( $content, $replacements );
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
