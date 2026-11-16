<?php
/**
 * File to provide local working copies of exported images, e.g. for image optimizers.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;

/**
 * Object to provide local working copies of exported images, e.g. for image optimizers.
 *
 * Image optimizers and other plugins work with the local files of images, which do not exist for exported images.
 * A working copy saves the image, its original image, its sizes and the backups of other plugins at their original
 * local paths and adds the sizes to its metadata. Until it is finished, the image looks like a local image for
 * WordPress and other plugins (get_attached_file() returns the local file), while it is still delivered from its
 * external source. When the work is finished (e.g. the optimizer reports the end of its optimization), the changed
 * and new files are exported (files with the same name are overwritten, files, which are not used anymore, are deleted
 * on the external sources) and the local files are removed again, if local files are not kept after the export.
 */
class Working_Copies {
	/**
	 * Name of the post meta with the data of the working copy.
	 *
	 * @var string
	 */
	public const META = 'efml_working_copy';

	/**
	 * The images, whose working copy is finished at the end of the request.
	 *
	 * @var array<int,int>
	 */
	private array $finish = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Working_Copies
	 */
	private static ?Working_Copies $instance = null;

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
	 * @return Working_Copies
	 */
	public static function get_instance(): Working_Copies {
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
		// start the creation or the finishing of working copies by request (also with disabled export).
		add_action( 'admin_action_efml_create_working_copies', array( $this, 'create_via_request' ) );
		add_action( 'admin_action_efml_finish_working_copies', array( $this, 'finish_via_request' ) );

		// finish the working copies, whose work has been finished in this request.
		add_action( 'shutdown', array( $this, 'finish_pending' ), 5 );
	}

	/**
	 * Return whether the given attachment has a local working copy.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_active( int $attachment_id ): bool {
		// bail if no ID is given.
		if ( $attachment_id <= 0 ) {
			return false;
		}

		// return whether the data exist.
		$data = get_post_meta( $attachment_id, self::META, true );
		return is_array( $data ) && ! empty( $data['file'] );
	}

	/**
	 * Return the data of the working copy of the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array{time: int, file: string, original: string, created: array<int,string>, hashes: array<string,string>, backups: array<string,string>}
	 */
	private function get_data( int $attachment_id ): array {
		// get the data.
		$data = get_post_meta( $attachment_id, self::META, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// return them with defaults.
		return array(
			'time'     => absint( $data['time'] ?? 0 ),
			'file'     => (string) ( $data['file'] ?? '' ),
			'original' => (string) ( $data['original'] ?? '' ),
			'created'  => array_map( 'strval', (array) ( $data['created'] ?? array() ) ),
			'hashes'   => array_map( 'strval', (array) ( $data['hashes'] ?? array() ) ),
			'backups'  => array_map( 'strval', (array) ( $data['backups'] ?? array() ) ),
		);
	}

	/**
	 * Return the given absolute path relative to the content directory of WordPress (empty, if it is outside).
	 *
	 * @param string $path The absolute path.
	 *
	 * @return string
	 */
	private function get_relative_path( string $path ): string {
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		$path        = wp_normalize_path( $path );
		return str_starts_with( $path, $content_dir ) ? substr( $path, strlen( $content_dir ) ) : '';
	}

	/**
	 * Return the absolute path of the given path relative to the content directory of WordPress.
	 *
	 * @param string $path The relative path.
	 *
	 * @return string
	 */
	private function get_absolute_path( string $path ): string {
		// bail on empty or invalid paths.
		if ( '' === $path || str_contains( $path, '..' ) || 0 !== validate_file( $path ) ) {
			return '';
		}

		// return the absolute path.
		return trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ) . $path;
	}

	/**
	 * Return the local file of the given image with a working copy (empty, if it has no working copy).
	 *
	 * Other plugins could have changed it, e.g. after a conversion from PNG to JPEG they save the new local file as
	 * attached file.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_local_file( int $attachment_id ): string {
		// bail if it has no working copy.
		if ( ! $this->is_active( $attachment_id ) ) {
			return '';
		}

		// use the local file, which another plugin has saved as attached file.
		$attached = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( '' !== $attached && ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $attached ) && ! str_contains( $attached, '..' ) ) {
			$path = path_is_absolute( $attached ) ? $attached : trailingslashit( wp_get_upload_dir()['basedir'] ) . $attached;
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		// return the file of the working copy.
		return $this->get_absolute_path( $this->get_data( $attachment_id )['file'] );
	}

	/**
	 * Return whether a working copy could be created for the given attachment.
	 *
	 * Only for exported images (not for files taken over from other offload plugins) and not on copies of the website,
	 * as the changed files could not be exported there.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_possible( int $attachment_id ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an exported image, whose files are not local.
		if ( ! $external_file_obj->is_valid() || $external_file_obj->is_locally_saved() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		// bail if it has been taken over from another offload plugin, as it still has its local files.
		if ( is_array( get_post_meta( $attachment_id, Migrations::BACKUP_META, true ) ) ) {
			return false;
		}

		// return whether this is not a copy of the website.
		return ! Site_Copy::get_instance()->is_copy();
	}

	/**
	 * Create the local working copy of the given exported image.
	 *
	 * Saves the image, its original image, its exported sizes and the exported backups of other plugins at their
	 * original local paths and adds the sizes to its metadata.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function create( int $attachment_id ): bool {
		// bail if it has a working copy already.
		if ( $this->is_active( $attachment_id ) ) {
			return true;
		}

		// bail if it is not possible.
		if ( ! $this->is_possible( $attachment_id ) ) {
			return false;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get its URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if no external source of the image could be used, as the changed files could not be exported.
		if ( empty( Sizes::get_instance()->get_export_sources_with_sizes( $attachment_id, false ) ) ) {
			Log::get_instance()->create( __( 'The working copy of the exported image could not be created, as none of its external sources could be used at the moment.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the original paths.
		$paths = Restore::get_instance()->get_original_paths( $attachment_id );

		// bail if they are unknown.
		if ( '' === $paths['file'] ) {
			Log::get_instance()->create( __( 'The working copy of the exported image could not be created, as its original path is unknown.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the absolute paths.
		$base_dir  = trailingslashit( wp_get_upload_dir()['basedir'] );
		$file      = $base_dir . $paths['file'];
		$original  = '' !== $paths['original'] ? $base_dir . $paths['original'] : '';
		$directory = trailingslashit( dirname( $file ) );

		// save the image and its original image.
		$created = Restore::get_instance()->fetch_local_files( $external_file_obj, $file, $original );

		// bail if they could not be loaded.
		if ( false === $created ) {
			Log::get_instance()->create( __( 'The working copy of the exported image could not be created, as it could not be downloaded.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// save the exported sizes with their local names and collect them for the metadata.
		$local_sizes = Local_Urls::get_instance()->get_local_sizes( $attachment_id );
		$sizes       = array();
		$size_paths  = array();
		foreach ( Sizes::get_instance()->get_sizes( $attachment_id ) as $size_name => $size ) {
			// get the local name.
			$name = ! empty( $local_sizes[ $size_name ] ) ? wp_basename( (string) $local_sizes[ $size_name ] ) : wp_basename( (string) ( $size['file'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$path = $directory . $name;

			// download it, if it does not exist.
			if ( ! file_exists( $path ) ) {
				if ( empty( $size['url'] ) || ! Restore::get_instance()->download( $external_file_obj, $path, array( (string) $size['url'] ) ) ) {
					continue;
				}
				$created[] = $path;
			}

			// add it to the metadata.
			$sizes[ (string) $size_name ] = array(
				'file'      => $name,
				'width'     => absint( $size['width'] ?? 0 ),
				'height'    => absint( $size['height'] ?? 0 ),
				'mime-type' => (string) ( $size['mime-type'] ?? '' ),
			);
			$size_paths[]                 = $path;
		}

		// save the exported backups of other plugins (e.g. of image optimizers) at their local paths.
		$backups  = Variants::get_instance()->fetch_backups( $attachment_id );
		$created  = array_merge( $created, array_values( $backups ) );
		$all      = array_merge( array( $file, $original ), $size_paths, array_values( $backups ) );
		$local_wo = Local_Work::get_instance();

		// get the hashes of the files.
		$hashes = array();
		foreach ( array_unique( array_filter( $all ) ) as $path ) {
			$hash = $local_wo->get_hash( $path );
			if ( '' !== $hash ) {
				$hashes[ $this->get_relative_path( $path ) ] = $hash;
			}
		}

		// add the sizes to the metadata, so the image looks like a local image (without hooks, as nothing has changed).
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		if ( is_array( $metadata ) && empty( $metadata['sizes'] ) && ! empty( $sizes ) ) {
			$metadata['sizes'] = $sizes;
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		// save the data.
		$backup_paths = array();
		foreach ( $backups as $name => $path ) {
			$backup_paths[ (string) $name ] = $this->get_relative_path( $path );
		}
		update_post_meta(
			$attachment_id,
			self::META,
			array(
				'time'     => time(),
				'file'     => $this->get_relative_path( $file ),
				'original' => '' !== $original ? $this->get_relative_path( $original ) : '',
				'created'  => array_values( array_filter( array_map( array( $this, 'get_relative_path' ), array_unique( $created ) ) ) ),
				'hashes'   => $hashes,
				'backups'  => $backup_paths,
			)
		);

		// remove the cached file object, as its attached file has changed.
		clean_post_cache( $attachment_id );

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( __( 'The local working copy of the exported image has been created, %1$d files have been saved locally.', 'external-files-in-media-library' ), count( $created ) ), $url, 'info', 1 );

		// return true as the working copy has been created.
		return true;
	}

	/**
	 * Finish the working copy of the given image at the end of the request, e.g. after an image optimizer reported
	 * the end of its work (it could still work with the files in this request).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function finish_later( int $attachment_id ): void {
		if ( $this->is_active( $attachment_id ) ) {
			$this->finish[ $attachment_id ] = $attachment_id;
		}
	}

	/**
	 * Finish the working copies, whose work has been finished in this request.
	 *
	 * @return void
	 */
	public function finish_pending(): void {
		foreach ( $this->finish as $attachment_id ) {
			$this->finish( $attachment_id );
		}
		$this->finish = array();
	}

	/**
	 * Finish the working copy of the given image: export its changed and new files and remove its local files.
	 *
	 * Files with the same name are overwritten on the external sources, files, which are not used anymore (e.g. after
	 * a conversion from PNG to JPEG or a removed backup), are deleted there. The local files are kept, if local files
	 * are kept after the export.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function finish( int $attachment_id ): bool {
		// bail if it has no working copy.
		if ( ! $this->is_active( $attachment_id ) ) {
			return true;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get its URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if this website is a copy: the working copy stays until this is the main website.
		if ( Site_Copy::get_instance()->is_blocked( __( 'The changed files of the working copy could not be exported.', 'external-files-in-media-library' ), $url ) ) {
			return false;
		}

		// bail if no external source of the image could be used: the working copy stays.
		if ( empty( Sizes::get_instance()->get_export_sources_with_sizes( $attachment_id, false ) ) ) {
			Log::get_instance()->create( __( 'The changed files of the working copy could not be exported, as none of the external sources of the image could be used at the moment. It stays local.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the data.
		$data = $this->get_data( $attachment_id );

		// get the absolute paths.
		$created = array_values( array_filter( array_map( array( $this, 'get_absolute_path' ), $data['created'] ) ) );
		$hashes  = array();
		foreach ( $data['hashes'] as $path => $hash ) {
			$absolute = $this->get_absolute_path( (string) $path );
			if ( '' !== $absolute ) {
				$hashes[ $absolute ] = $hash;
			}
		}
		$backups = array();
		foreach ( $data['backups'] as $name => $path ) {
			$absolute = $this->get_absolute_path( $path );
			if ( '' !== $absolute ) {
				$backups[ (string) $name ] = $absolute;
			}
		}

		// get the actual local file (another plugin could have renamed it, e.g. after a conversion).
		$file = $this->get_local_file( $attachment_id );

		// export the changed and new files and remove the local files.
		$local_work_obj = Local_Work::get_instance();
		$local_work_obj->adopt( $attachment_id, $file, $this->get_absolute_path( $data['original'] ), $created, $hashes, $backups );
		$local_work_obj->close( $attachment_id );

		// get the external file object again, as its URL could have been changed (e.g. after a conversion).
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// use the external URL as attached file again, if another plugin has changed it.
		$attached = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( $attached !== $external_file_obj->get_url( true ) ) {
			update_post_meta( $attachment_id, '_wp_attached_file', $external_file_obj->get_url( true ) );
		}

		// remove the sizes from the metadata, as they are used from the external sources (like after the export).
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		if ( is_array( $metadata ) && isset( $metadata['sizes'] ) ) {
			unset( $metadata['sizes'] );
			update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
		}

		// remove the data of the working copy.
		delete_post_meta( $attachment_id, self::META );
		clean_post_cache( $attachment_id );

		// log this event.
		Log::get_instance()->create( __( 'The working copy of the exported image has been finished: its changed files have been exported.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'success', 1 );

		// return true as the working copy has been finished.
		return true;
	}

	/**
	 * Return the IDs of the next exported images without working copy after the given attachment ID.
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of files.
	 *
	 * @return array<int,int>
	 */
	public function get_candidates( int $after_id, int $limit ): array {
		global $wpdb;

		// get the images.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`post_mime_type` LIKE %s AND p.`ID` > %d AND NOT EXISTS ( SELECT 1 FROM %i AS b WHERE b.`post_id` = p.`ID` AND b.`meta_key` IN (%s, %s) ) ORDER BY p.`ID` ASC LIMIT %d', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', 'image/%', $after_id, $wpdb->postmeta, Migrations::BACKUP_META, self::META, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return the list.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the number of exported images without working copy.
	 *
	 * @return int
	 */
	public function count_candidates(): int {
		global $wpdb;

		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`post_mime_type` LIKE %s AND NOT EXISTS ( SELECT 1 FROM %i AS b WHERE b.`post_id` = p.`ID` AND b.`meta_key` IN (%s, %s) )', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', 'image/%', $wpdb->postmeta, Migrations::BACKUP_META, self::META ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Return the IDs of the next images with working copy after the given attachment ID.
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of files.
	 *
	 * @return array<int,int>
	 */
	public function get_active( int $after_id, int $limit ): array {
		global $wpdb;

		// get the images.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `post_id` FROM %i WHERE `meta_key` = %s AND `post_id` > %d ORDER BY `post_id` ASC LIMIT %d', $wpdb->postmeta, self::META, $after_id, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery

		// return the list.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the number of images with working copy.
	 *
	 * @return int
	 */
	public function count_active(): int {
		global $wpdb;

		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT `post_id`) FROM %i WHERE `meta_key` = %s', $wpdb->postmeta, self::META ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}

	/**
	 * Return the URL to start the creation or the finishing of the working copies.
	 *
	 * @param string $action "create" or "finish".
	 *
	 * @return string
	 */
	public function get_url( string $action ): string {
		return add_query_arg(
			array(
				'action' => 'efml_' . $action . '_working_copies',
				'nonce'  => wp_create_nonce( 'efml-' . $action . '-working-copies' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the dialog to start the creation of working copies.
	 *
	 * @return array<string,mixed>
	 */
	public function get_create_dialog(): array {
		return array(
			'className' => 'efml',
			'title'     => __( 'Prepare exported images for image optimizers', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Do you want to save local working copies of all exported images?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'Each exported image is saved with its sizes and the backups of image optimizers at its original path. Afterward image optimizers and other plugins could work with them like with local images, e.g. via their bulk optimization. The images are still delivered from their external sources.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'As soon as an image optimizer reports the end of its work for an image, its changed and new files are exported and its local files are removed again. For other plugins use the button "Export optimized images" afterward.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'This needs storage space for the files of all exported images on your hosting, until they are exported again. The images are processed one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $this->get_url( 'create' ) . '";',
					'variant' => 'primary',
					'text'    => __( 'Save the working copies', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Return the dialog to start the finishing of all working copies.
	 *
	 * @return array<string,mixed>
	 */
	public function get_finish_dialog(): array {
		return array(
			'className' => 'efml',
			'title'     => __( 'Export optimized images', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Do you want to export the changed files of all working copies?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The changed and new files of the images (e.g. optimized files, WebP files and backups of image optimizers) are exported to their external sources and the local working copies are removed. Use this after the image optimizer, which does not report the end of its work (e.g. Converter for Media), has finished.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'The images are processed one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $this->get_url( 'finish' ) . '";',
					'variant' => 'primary',
					'text'    => __( 'Export the optimized images', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Start the creation of the working copies by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function create_via_request(): void {
		$this->run_via_request( 'create' );
	}

	/**
	 * Start the finishing of the working copies by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function finish_via_request(): void {
		$this->run_via_request( 'finish' );
	}

	/**
	 * Start the creation or the finishing of the working copies by request.
	 *
	 * @param string $action "create" or "finish".
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	private function run_via_request( string $action ): void {
		// check nonce.
		check_admin_referer( 'efml-' . $action . '-working-copies', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// start the run.
		$result = 'create' === $action ? Import_Runs::get_instance()->start_working_copies() : Import_Runs::get_instance()->start_finish_working_copies();

		// show the result (not, if all images have been processed already: the hint of the finished run shows it then).
		if ( '' !== $result['message'] ) {
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_working_copies' );
			$transient_obj->set_type( $result['success'] ? 'success' : 'error' );
			$transient_obj->set_message( '<strong>' . esc_html( $result['message'] ) . '</strong>' );
			$transient_obj->save();
		}

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Show a marker for images with working copy in the media table.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function show_marker( int $attachment_id ): void {
		// bail if it has no working copy.
		if ( ! $this->is_active( $attachment_id ) ) {
			return;
		}

		// show the marker.
		echo ' <span class="dashicons dashicons-images-alt2" title="' . esc_attr__( 'This exported image has a local working copy, e.g. for image optimizers. Its changed files are exported as soon as the work has been finished.', 'external-files-in-media-library' ) . '"></span>';
	}
}
