<?php
/**
 * File to let WordPress and other plugins work with exported images, which need their local files.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;

/**
 * Object to work with exported images as local files for a moment.
 *
 * Some functions need the local files of an image, which do not exist for exported images:
 *
 * - "regenerate": WordPress and other plugins create the sizes of an image again from its original image
 *   ("wp media regenerate", "Regenerate Thumbnails", wp_update_image_subsizes(), the image editor of the block editor).
 *   They get its path via wp_get_original_image_path().
 * - "edit": the image editor of WordPress (crop, rotate, scale, restore the original image). It saves the image under
 *   a new name and keeps the former files as backup for "Restore image".
 * - "rename": plugins, which rename the files of an image. They set the new local file via update_attached_file().
 *   Integrations of such plugins (in ThirdParty) start the work via open() before the plugin renames the files.
 * - "optimize": image optimizers changed the local working copies of an image (see Working_Copies). Its changed and
 *   new files are exported via adopt() and close(): files with the same name are overwritten on the external sources,
 *   files, which are not used anymore (e.g. after a conversion from PNG to JPEG), are deleted there.
 *
 * For these functions this object loads the files to their original local paths (only in the backend, via REST API,
 * cron and WP-CLI, never for visitors), lets WordPress work with them as usual, exports the resulting files to every
 * external source of the image and removes the local files again, if local files are not kept after the export.
 * Files, which are not used anymore, are kept on the external sources as former files (regenerate, edit) and deleted
 * together with the image, or deleted at once (rename).
 */
class Local_Work {
	/**
	 * The images, which are worked on as local files (attachment ID => data).
	 *
	 * @var array<int,array{mode: string, url: string, file: string, original: string, created: array<int,string>, main_changed: bool, exported: bool, sizes: array<int,string>, hashes?: array<string,string>, backups?: array<string,string>, kept_backups?: array<int,string>}>
	 */
	private array $sessions = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local_Work
	 */
	private static ?Local_Work $instance = null;

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
	 * @return Local_Work
	 */
	public static function get_instance(): Local_Work {
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
		// start the work with the local files.
		add_filter( 'wp_get_original_image_path', array( $this, 'provide_original_image' ), 20, 2 );
		add_action( 'wp_ajax_image-editor', array( $this, 'start_image_editor' ), 0 );

		// work with the local files.
		add_filter( 'update_attached_file', array( $this, 'change_attached_file' ), 20, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'add_sizes_to_metadata' ), 20, 2 );
		add_filter( 'wp_image_file_matches_image_meta', array( $this, 'match_image_file' ), 10, 4 );

		// export the results.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'export_regenerated_files' ), 30, 2 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'export_changed_files' ), 5, 2 );
		add_action( 'shutdown', array( $this, 'close_all' ) );
	}

	/**
	 * Return the local path of the original image of an exported image, which is downloaded for this.
	 *
	 * @param string|false $path          The path of the original image (for exported images the external URL).
	 * @param int          $attachment_id The attachment ID.
	 *
	 * @return string|false
	 */
	public function provide_original_image( string|false $path, int $attachment_id ): string|false {
		// use the local file, if it is already available.
		if ( $this->is_open( $attachment_id ) ) {
			return $this->get_source_file( $attachment_id );
		}

		// bail if the local file is not needed in this request.
		if ( ! $this->is_allowed( $attachment_id ) ) {
			return $path;
		}

		// bail if the path is only requested for its name or the image dimensions, e.g. for the media library.
		if ( $this->is_requested_for_information_only() ) {
			return $path;
		}

		// get the local file.
		$local_path = $this->open( $attachment_id, 'regenerate' );

		// return the local file or the path as before, if it could not be loaded.
		return '' !== $local_path ? $local_path : $path;
	}

	/**
	 * Return whether the path of the original image is only requested for information, not to work with the
	 * image: WordPress asks for it to show the name of the original image in the media library, in the
	 * details of an attachment and in the REST API, and for the dimensions of the original image. The files are
	 * not downloaded for this (it would happen for every exported image on every view of the media library).
	 *
	 * @return bool
	 */
	private function is_requested_for_information_only(): bool {
		$callers = array(
			'wp_prepare_attachment_for_js',
			'attachment_submitbox_metadata',
			'wp_get_missing_image_subsizes',
			'WP_REST_Attachments_Controller::get_attachment_filename',
			'WP_REST_Attachments_Controller::get_attachment_filesize',
		);

		/**
		 * Filter the functions, which request the path of the original image of an exported image only for
		 * information (e.g. its name). For them, the files are not downloaded.
		 *
		 * Use "Class::method" for methods.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,string> $callers The list of functions and methods.
		 */
		$callers = apply_filters( 'efml_local_work_information_callers', $callers );

		// check the calling functions.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$function = ( isset( $frame['class'] ) ? $frame['class'] . '::' : '' ) . $frame['function'];
			if ( in_array( $function, $callers, true ) ) {
				return true;
			}
		}

		// return false as the path is requested to work with the image.
		return false;
	}

	/**
	 * Load the local files of an exported image, before the image editor of WordPress saves or restores it.
	 *
	 * Runs before the AJAX handler of WordPress, so it checks the same permissions.
	 *
	 * @return void
	 */
	public function start_image_editor(): void {
		// get the attachment ID and the action (the nonce is checked below).
		$attachment_id = isset( $_POST['postid'] ) ? absint( wp_unslash( $_POST['postid'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action        = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// bail if this is not an action, which changes the image.
		if ( 0 === $attachment_id || ! in_array( $action, array( 'save', 'scale', 'restore' ), true ) ) {
			return;
		}

		// bail if the user is not allowed to edit the image or the request is not valid (WordPress stops it then).
		if ( ! current_user_can( 'edit_post', $attachment_id ) || false === check_ajax_referer( 'image_editor-' . $attachment_id, false, false ) ) {
			return;
		}

		// bail if the local files could not be used.
		if ( ! $this->is_allowed( $attachment_id ) ) {
			return;
		}

		// load the local files: the restore uses the former files on the external sources.
		$this->open( $attachment_id, 'edit', 'restore' !== $action );
	}

	/**
	 * Return whether the local files of the given attachment could be used in this request.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_allowed( int $attachment_id ): bool {
		// only via cron and WP-CLI, or in the backend and via REST API for users, who are allowed to edit the image,
		// never for visitors of the website.
		$allowed = wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
		if ( ! $allowed && ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) ) {
			$allowed = current_user_can( 'edit_post', $attachment_id );
		}

		/**
		 * Filter whether the local files of an exported image could be used in this request, e.g. to create its
		 * sizes again, to edit or to rename it. They are downloaded for this.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $allowed       True if allowed.
		 * @param int  $attachment_id The attachment ID.
		 */
		if ( ! apply_filters( 'efml_local_work_allowed', $allowed, $attachment_id ) ) {
			return false;
		}

		// bail if the image has local working copies: WordPress and other plugins work with them directly.
		if ( Working_Copies::get_instance()->is_active( $attachment_id ) ) {
			return false;
		}

		// return whether this is an exported image, whose files are not local.
		return $this->is_exported_image( $attachment_id );
	}

	/**
	 * Return whether the given attachment is an exported image, whose files are not local.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_exported_image( int $attachment_id ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an exported image, whose files are not local.
		if ( ! $external_file_obj->is_valid() || $external_file_obj->is_locally_saved() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		// bail if this file has been taken over from another offload plugin, as it still has its local files.
		return ! is_array( get_post_meta( $attachment_id, Migrations::BACKUP_META, true ) );
	}

	/**
	 * Load the local files of the given exported image and return the path of its original image.
	 *
	 * Returns an empty string, if this is not possible.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $mode          The kind of work: "regenerate", "edit" or "rename".
	 * @param bool   $download      True to download the image (false, if only its name is needed).
	 * @param bool   $with_sizes    True to download the files of the sizes, too.
	 *
	 * @return string
	 */
	public function open( int $attachment_id, string $mode, bool $download = true, bool $with_sizes = false ): string {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get its URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if this website is a copy, as the resulting files could not be exported.
		if ( Site_Copy::get_instance()->is_blocked( __( 'The exported image could not be changed, as nothing is exported on a copy of the website.', 'external-files-in-media-library' ), $url ) ) {
			return '';
		}

		// bail if no external source of the image could be used, as the resulting files could not be exported.
		if ( empty( Sizes::get_instance()->get_export_sources_with_sizes( $attachment_id, false ) ) ) {
			Log::get_instance()->create( __( 'The exported image could not be changed, as none of its external sources could be used at the moment.', 'external-files-in-media-library' ), $url, 'error' );
			return '';
		}

		// get the original paths of the image.
		$paths = Restore::get_instance()->get_original_paths( $attachment_id );

		// bail if they are unknown.
		if ( '' === $paths['file'] ) {
			Log::get_instance()->create( __( 'The exported image could not be changed, as its original path is unknown.', 'external-files-in-media-library' ), $url, 'error' );
			return '';
		}

		// get the absolute paths.
		$base_dir      = trailingslashit( wp_get_upload_dir()['basedir'] );
		$file          = $base_dir . $paths['file'];
		$original_file = '' !== $paths['original'] ? $base_dir . $paths['original'] : '';

		// get the local files.
		$created = array();
		if ( $download ) {
			$created = Restore::get_instance()->fetch_local_files( $external_file_obj, $file, $original_file );

			// bail if they could not be loaded.
			if ( false === $created ) {
				Log::get_instance()->create( __( 'The exported image could not be changed, as it could not be downloaded.', 'external-files-in-media-library' ), $url, 'error' );
				return '';
			}
		}

		// remember them.
		$this->sessions[ $attachment_id ] = array(
			'mode'         => $mode,
			'url'          => (string) get_post_meta( $attachment_id, '_wp_attached_file', true ),
			'file'         => $file,
			'original'     => $original_file,
			'created'      => $created,
			'main_changed' => false,
			'exported'     => false,
			'sizes'        => array(),
		);

		// download the files of the sizes, if requested.
		if ( $with_sizes ) {
			$this->download_sizes( $external_file_obj, trailingslashit( dirname( $file ) ) );
		}

		// log this event.
		/* translators: %1$s will be replaced by the kind of work (e.g. "edit"). */
		Log::get_instance()->create( sprintf( __( 'The exported image is used as local file (%1$s).', 'external-files-in-media-library' ), $mode ), $url, 'info', 2 );

		// return the path of the original image.
		return $this->get_source_file( $attachment_id );
	}

	/**
	 * Use the given local working copies of an exported image, which other plugins have worked with (e.g. image
	 * optimizers), to export its changed and new files via close().
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param string               $file          The absolute path of the local file of the image (it could have been renamed).
	 * @param string               $original      The absolute path of the local original image (empty for none).
	 * @param array<int,string>    $created       The absolute paths of the downloaded files, which are removed afterward.
	 * @param array<string,string> $hashes        The MD5 hashes of the downloaded files (absolute path => hash).
	 * @param array<string,string> $backups       The downloaded backups of other plugins (name of the variant => absolute path).
	 *
	 * @return void
	 */
	public function adopt( int $attachment_id, string $file, string $original, array $created, array $hashes, array $backups ): void {
		// get the URL of the file.
		$url = Files::get_instance()->get_file( $attachment_id )->get_url( true );

		// get whether the file of the image has been renamed (e.g. after a conversion from PNG to JPEG).
		$name = (string) get_post_meta( $attachment_id, 'efml_export_filename', true );

		// remember them.
		$this->sessions[ $attachment_id ] = array(
			'mode'         => 'optimize',
			'url'          => $url,
			'file'         => $file,
			'original'     => $original,
			'created'      => $created,
			'main_changed' => '' !== $name && wp_basename( $file ) !== $name,
			'exported'     => false,
			'sizes'        => array(),
			'hashes'       => $hashes,
			'backups'      => $backups,
		);
	}

	/**
	 * Return the MD5 hash of the given local file (empty string, if it does not exist).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param string $path The absolute path.
	 *
	 * @return string
	 */
	public function get_hash( string $path ): string {
		// bail if the file does not exist.
		if ( '' === $path || ! is_file( $path ) ) {
			return '';
		}

		// return the hash.
		$hash = md5_file( $path );
		return is_string( $hash ) ? $hash : '';
	}

	/**
	 * Return whether the given local file has been changed since its download.
	 *
	 * Files, whose hash is unknown, count as changed.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $path          The absolute path.
	 *
	 * @return bool
	 */
	private function is_changed( int $attachment_id, string $path ): bool {
		// get the hashes.
		$hashes = $this->sessions[ $attachment_id ]['hashes'] ?? array();

		// return whether the file has changed.
		return ! isset( $hashes[ $path ] ) || $hashes[ $path ] !== $this->get_hash( $path );
	}

	/**
	 * Download the exported sizes of the given image to the given local directory.
	 *
	 * @param File   $external_file_obj The external file object.
	 * @param string $directory         The local directory with trailing slash.
	 *
	 * @return void
	 */
	private function download_sizes( File $external_file_obj, string $directory ): void {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// download each size.
		foreach ( Sizes::get_instance()->get_sizes( $external_file_obj->get_id() ) as $size ) {
			// get the local path.
			$path = $directory . wp_basename( (string) ( $size['file'] ?? '' ) );

			// bail if it exists already.
			if ( $wp_filesystem->exists( $path ) || empty( $size['url'] ) ) {
				continue;
			}

			// download it.
			if ( Restore::get_instance()->download( $external_file_obj, $path, array( (string) $size['url'] ) ) ) {
				$this->sessions[ $external_file_obj->get_id() ]['created'][] = $path;
			}
		}
	}

	/**
	 * Return whether the local files of the given attachment are in use.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_open( int $attachment_id ): bool {
		return isset( $this->sessions[ $attachment_id ] );
	}

	/**
	 * Return the local path of the file of the given attachment, which is in use.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_local_file( int $attachment_id ): string {
		return $this->sessions[ $attachment_id ]['file'] ?? '';
	}

	/**
	 * Return the local path of the file, from which the sizes of the given attachment are created.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	private function get_source_file( int $attachment_id ): string {
		if ( ! $this->is_open( $attachment_id ) ) {
			return '';
		}
		return '' !== $this->sessions[ $attachment_id ]['original'] ? $this->sessions[ $attachment_id ]['original'] : $this->sessions[ $attachment_id ]['file'];
	}

	/**
	 * Note a new local file of an exported image and keep the external URL as attached file meanwhile.
	 *
	 * WordPress and other plugins set the attached file to a local file, e.g. the image editor to the edited image,
	 * renaming plugins to the renamed file and WordPress to the scaled image, if it creates the sizes again. For an
	 * exported image, whose local files are not in use, the new local file must exist (e.g. a kept local copy, which
	 * has been renamed): then the work with the local files starts now.
	 *
	 * @param string $file          The new attached file.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function change_attached_file( string $file, int $attachment_id ): string {
		// bail if no file is given.
		if ( '' === $file ) {
			return $file;
		}

		// handle a new URL (e.g. a file in a local directory as external source, which another plugin has renamed there).
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			$this->change_url( $file, $attachment_id );
			return $file;
		}

		// get the absolute path.
		$path = path_is_absolute( $file ) ? $file : trailingslashit( wp_get_upload_dir()['basedir'] ) . $file;

		// start the work with the local files, if the image has been renamed by another plugin.
		if ( ! $this->is_open( $attachment_id ) ) {
			// bail if this is not an exported image or the local files could not be used in this request.
			if ( ! $this->is_allowed( $attachment_id ) ) {
				return $file;
			}

			// bail if the new local file does not exist: the image keeps its external URL.
			if ( ! Helper::get_wp_filesystem()->exists( $path ) ) {
				Log::get_instance()->create( __( 'The local file of the exported image has been changed to a file, which does not exist. The exported image keeps its file.', 'external-files-in-media-library' ), $path, 'error' );
				return (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			}

			// start the work without download.
			if ( '' === $this->open( $attachment_id, 'rename', false ) ) {
				return (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
			}
		}

		// note the new file, if its name differs.
		if ( wp_basename( $path ) !== wp_basename( $this->sessions[ $attachment_id ]['file'] ) ) {
			// WordPress creates the scaled image under a new name only if the former one exists: the exported image keeps its name then.
			if ( 'regenerate' === $this->sessions[ $attachment_id ]['mode'] ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'WordPress has created the image with the new name %1$s. The exported image keeps its name, its sizes are created from the original image.', 'external-files-in-media-library' ), '<code>' . esc_html( wp_basename( $path ) ) . '</code>' ), $path, 'info', 1 );
				$this->sessions[ $attachment_id ]['created'][] = $path;
			} else {
				$this->sessions[ $attachment_id ]['file']         = $path;
				$this->sessions[ $attachment_id ]['main_changed'] = true;

				// save the local file meanwhile, as WordPress checks whether the value has been saved (it is replaced by
				// the external URL as soon as the file has been exported, or the former URL is used again).
				return _wp_relative_upload_path( $path );
			}
		}

		// return the actual value (the external URL).
		return (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	}

	/**
	 * Use the given new URL for an exported image, if another plugin has renamed it on its external source.
	 *
	 * This is possible for external sources, whose files could be reached via a path (e.g. a local directory): other
	 * plugins rename the file there and set its new path as attached file. Only a new name in the same directory is
	 * used.
	 *
	 * @param string $url           The new URL.
	 * @param int    $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private function change_url( string $url, int $attachment_id ): void {
		// bail if the local files are in use (this object changes the URL itself then).
		if ( $this->is_open( $attachment_id ) ) {
			return;
		}

		// get the actual URL.
		$old_url = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );

		// bail if the URL has not changed, is not in the same directory or this is not an exported image.
		if ( $url === $old_url || dirname( $url ) !== dirname( $old_url ) || ! $this->is_exported_image( $attachment_id ) ) {
			return;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not the URL of the file.
		if ( $external_file_obj->get_url( true ) !== $old_url ) {
			return;
		}

		// remember the former name, so the proxy still finds the image via its former URLs.
		add_post_meta( $attachment_id, Files::FORMER_TITLES_META, wp_basename( $old_url ) );

		// use the new URL.
		$external_file_obj->set_url( $url );
		$external_file_obj->set_title( wp_basename( $url ) );
		update_post_meta( $attachment_id, 'efml_export_filename', wp_basename( $url ) );

		// remove the file in the cache of the proxy.
		$external_file_obj->delete_cache();

		// log this event.
		/* translators: %1$s will be replaced by a file name. */
		Log::get_instance()->create( sprintf( __( 'The exported image has been renamed on its external source to %1$s.', 'external-files-in-media-library' ), '<code>' . esc_html( wp_basename( $url ) ) . '</code>' ), $url, 'info', 1 );
	}

	/**
	 * Return whether the given URL is a file of the given exported image.
	 *
	 * WordPress compares the URL with the path of the file in the uploads directory, e.g. if the block editor edits
	 * an image. Exported images use other URLs (via the proxy or of the external source), so their file names are
	 * compared.
	 *
	 * @param bool   $match          Whether the URL is a file of the image.
	 * @param string $image_location The URL.
	 * @param mixed  $image_meta     The metadata of the image.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return bool
	 */
	public function match_image_file( bool $match, string $image_location, mixed $image_meta, int $attachment_id ): bool {
		// bail if it matches already or this is not an exported image.
		if ( $match || 0 === $attachment_id || ! $this->is_exported_image( $attachment_id ) ) {
			return $match;
		}

		// WordPress removes the query of the URL, so the URL of the proxy without pretty permalinks ("?emlproxy=…") is
		// the URL of the website: it matches, if the image is used as local file in this request (it has been loaded
		// for this request, e.g. by the block editor).
		if ( $this->is_open( $attachment_id ) && untrailingslashit( explode( '?', $image_location )[0] ) === untrailingslashit( home_url() ) ) {
			return true;
		}

		// get the file name of the URL.
		$name = wp_basename( explode( '?', $image_location )[0] );

		// collect the file names of the image: the file itself, the original image and the sizes.
		$names = array( wp_basename( (string) get_post_meta( $attachment_id, '_wp_attached_file', true ) ), get_the_title( $attachment_id ) );
		if ( is_array( $image_meta ) ) {
			foreach ( array( 'file', 'original_image' ) as $key ) {
				if ( ! empty( $image_meta[ $key ] ) && is_string( $image_meta[ $key ] ) ) {
					$names[] = wp_basename( $image_meta[ $key ] );
				}
			}
		}
		foreach ( Sizes::get_instance()->get_sizes( $attachment_id ) as $size ) {
			$names[] = (string) ( $size['file'] ?? '' );
		}

		// the URL of the proxy uses the title of the image as name, with the dimensions for sizes.
		$title = pathinfo( get_the_title( $attachment_id ), PATHINFO_FILENAME );
		if ( '' !== $title && 1 === preg_match( '#^' . preg_quote( $title, '#' ) . '(-\d+x\d+)?\.[a-z0-9]+$#i', $name ) ) {
			return true;
		}

		// return whether the name is one of the file names.
		return in_array( $name, array_filter( $names ), true );
	}

	/**
	 * Add the exported sizes to the metadata of an exported image, while WordPress works with its local files.
	 *
	 * The metadata of exported images contains no sizes. WordPress needs them, e.g. to keep them as backup in the
	 * image editor or to create only the missing sizes.
	 *
	 * @param mixed $metadata      The metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function add_sizes_to_metadata( mixed $metadata, int $attachment_id ): mixed {
		// bail if the local files of this attachment are not in use or the metadata has sizes.
		if ( ! $this->is_open( $attachment_id ) || ! is_array( $metadata ) || ! empty( $metadata['sizes'] ) ) {
			return $metadata;
		}

		// add the exported sizes.
		$sizes = array();
		foreach ( Sizes::get_instance()->get_sizes( $attachment_id ) as $size_name => $size ) {
			$sizes[ $size_name ] = array(
				'file'      => (string) ( $size['file'] ?? '' ),
				'width'     => absint( $size['width'] ?? 0 ),
				'height'    => absint( $size['height'] ?? 0 ),
				'mime-type' => (string) ( $size['mime-type'] ?? '' ),
			);
		}
		if ( ! empty( $sizes ) ) {
			$metadata['sizes'] = $sizes;
		}

		// return the metadata.
		return $metadata;
	}

	/**
	 * Export the files, which WordPress has created again from the local files of an exported image.
	 *
	 * @param mixed $metadata      The new metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function export_regenerated_files( mixed $metadata, int $attachment_id ): mixed {
		// bail if the local files of this attachment are not in use or not to create the sizes again.
		if ( ! $this->is_open( $attachment_id ) || 'regenerate' !== $this->sessions[ $attachment_id ]['mode'] || ! is_array( $metadata ) ) {
			return $metadata;
		}

		// export them.
		$this->export_files( $attachment_id, $metadata );

		// return the metadata.
		return $metadata;
	}

	/**
	 * Export the files, which have been changed by the image editor or a renaming plugin.
	 *
	 * @param mixed $metadata      The new metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function export_changed_files( mixed $metadata, int $attachment_id ): mixed {
		// bail if the local files of this attachment are not in use, already exported or used to create the sizes again
		// or to optimize them (their files are exported at the end, as image optimizers save the metadata several times).
		if ( ! $this->is_open( $attachment_id ) || $this->sessions[ $attachment_id ]['exported'] || in_array( $this->sessions[ $attachment_id ]['mode'], array( 'regenerate', 'optimize' ), true ) || ! is_array( $metadata ) ) {
			return $metadata;
		}

		// export them.
		$this->export_files( $attachment_id, $metadata );

		// return the metadata.
		return $metadata;
	}

	/**
	 * Export the files of the given metadata of an exported image to each of its external sources.
	 *
	 * @param int                 $attachment_id The attachment ID.
	 * @param array<string,mixed> $metadata      The new metadata.
	 *
	 * @return void
	 */
	private function export_files( int $attachment_id, array $metadata ): void {
		// get the session.
		$session = $this->sessions[ $attachment_id ];

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get whether replaced files are kept (not for renamed and optimized files).
		$keep_replaced = ! in_array( $session['mode'], array( 'rename', 'optimize' ), true );

		// export the changed file of the image itself.
		if ( $session['main_changed'] ) {
			$this->export_main_file( $external_file_obj, $session['file'], $keep_replaced );
		}

		// get the files of the sizes.
		$sizes_obj = Sizes::get_instance();
		$files     = $sizes_obj->get_files_of_metadata( $metadata, $attachment_id );

		// get the local directory of the files.
		$directory = trailingslashit( dirname( $session['file'] ) );

		// export the optimized files of the image and its original image, and keep the unchanged files.
		if ( 'optimize' === $session['mode'] ) {
			$this->prepare_optimized_files( $external_file_obj, $files, $directory );
		}

		// remember the local files of the sizes to remove them.
		foreach ( $files as $size ) {
			$this->sessions[ $attachment_id ]['sizes'][] = $directory . $size['file'];
		}

		// get the files for their variants in other formats (e.g. WebP): the image itself only, if it has been changed
		// or optimized.
		$base_files = wp_list_pluck( $files, 'file' );
		if ( $session['main_changed'] || 'optimize' === $session['mode'] ) {
			$base_files = array( Variants::FULL => wp_basename( $session['file'] ) ) + $base_files;
		}

		// update them on each external source, which supports sizes.
		$uploaded     = 0;
		$variants_obj = Variants::get_instance();
		foreach ( $sizes_obj->get_export_sources_with_sizes( $attachment_id ) as $term_id => $export_obj ) {
			$credentials = $this->get_credentials( $term_id, $external_file_obj );
			$target_dir  = $this->get_target_directory( $attachment_id, $term_id );
			$uploaded   += $sizes_obj->update_files( $attachment_id, $term_id, $export_obj, $credentials, $directory, $target_dir, $files, $keep_replaced );
			$uploaded   += $variants_obj->update( $attachment_id, $export_obj, $term_id, $directory, $target_dir, $credentials, $base_files, $keep_replaced );
		}

		// delete the backups of other plugins on the external sources, which they have removed locally (e.g. after
		// they restored the image from them).
		$kept_backups = $this->sessions[ $attachment_id ]['kept_backups'] ?? array();
		foreach ( $session['backups'] ?? array() as $name => $path ) {
			if ( ! file_exists( $path ) && ! in_array( (string) $name, $kept_backups, true ) ) {
				$variants_obj->remove( $attachment_id, (string) $name );
			}
		}

		// remove the local files of the exported variants.
		$variants_obj->delete_local_files( $attachment_id );
		$variants_obj->delete_cache( $attachment_id );

		// save the new file names of the sizes.
		Local_Urls::get_instance()->set_local_sizes( $attachment_id, wp_list_pluck( array_diff_key( $files, array( Sizes::ORIGINAL => true ) ), 'file' ) );

		// remove the files in the cache of the proxy, so the new files are used.
		$external_file_obj->delete_thumbs();
		if ( $session['main_changed'] ) {
			$external_file_obj->delete_cache();
		}

		// mark the files as exported.
		$this->sessions[ $attachment_id ]['exported'] = true;

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( __( 'The changed files of the exported image have been exported, %1$d files have been uploaded.', 'external-files-in-media-library' ), $uploaded ), $external_file_obj->get_url( true ), 'info', 1 );
	}

	/**
	 * Export the optimized file of the image and its optimized original image with their names to each external source,
	 * and remove the local files of sizes, which have not been changed, so they are not uploaded again.
	 *
	 * @param File                              $external_file_obj The external file object.
	 * @param array<string,array<string,mixed>> $files             The files of the sizes (size name => data).
	 * @param string                            $directory         The local directory of the files, with trailing slash.
	 *
	 * @return void
	 */
	private function prepare_optimized_files( File $external_file_obj, array $files, string $directory ): void {
		// get the attachment ID and the session.
		$attachment_id = $external_file_obj->get_id();
		$session       = $this->sessions[ $attachment_id ];

		// collect the changed files of the image itself and of its original image (size name => absolute path).
		$changed = array();
		if ( ! $session['main_changed'] && $this->is_changed( $attachment_id, $session['file'] ) && file_exists( $session['file'] ) ) {
			$changed[ Variants::FULL ] = $session['file'];
		}
		if ( '' !== $session['original'] && isset( Sizes::get_instance()->get_sizes( $attachment_id, true )[ Sizes::ORIGINAL ] ) && $this->is_changed( $attachment_id, $session['original'] ) && file_exists( $session['original'] ) ) {
			$changed[ Sizes::ORIGINAL ] = $session['original'];
		}

		// upload them with their names to each external source, which supports sizes.
		$sizes_obj = Sizes::get_instance();
		foreach ( $changed as $size_name => $path ) {
			foreach ( $sizes_obj->get_export_sources_with_sizes( $attachment_id ) as $term_id => $export_obj ) {
				// get the target.
				$name   = Variants::FULL === $size_name ? (string) get_post_meta( $attachment_id, 'efml_export_filename', true ) : wp_basename( $path );
				$target = $this->get_target_directory( $attachment_id, $term_id ) . ( '' !== $name ? $name : wp_basename( $path ) );

				// upload it.
				try {
					$url = $export_obj->export_size( $attachment_id, $path, $target, $this->get_credentials( $term_id, $external_file_obj ) );
				} catch ( \Throwable $e ) {
					$url = false;
				}

				// bail if it could not be uploaded.
				if ( ! is_string( $url ) || '' === $url ) {
					/* translators: %1$s will be replaced by a file name. */
					Log::get_instance()->create( sprintf( __( 'The optimized file %1$s could not be exported to an external source.', 'external-files-in-media-library' ), '<code>' . esc_html( wp_basename( $path ) ) . '</code>' ), $target, 'error' );
					continue;
				}

				/* translators: %1$s will be replaced by a file name, %2$s by the target. */
				Log::get_instance()->create( sprintf( __( 'The optimized file %1$s has been uploaded to %2$s.', 'external-files-in-media-library' ), '<code>' . esc_html( wp_basename( $path ) ) . '</code>', '<code>' . esc_html( $target ) . '</code>' ), $url, 'info', 1 );

				// use a changed URL of the image on its main external source.
				if ( Variants::FULL === $size_name && $this->is_main_term( $attachment_id, $term_id ) && $url !== $external_file_obj->get_url( true ) ) {
					$external_file_obj->set_url( $url );
					update_post_meta( $attachment_id, '_wp_attached_file', $url );
				}

				// save a changed URL of the original image.
				if ( Sizes::ORIGINAL === $size_name ) {
					$sizes_obj->set_url( $attachment_id, $term_id, Sizes::ORIGINAL, $url );
				}
			}
		}

		// remove the file of the image in the cache of the proxy, so the optimized file is used.
		if ( isset( $changed[ Variants::FULL ] ) ) {
			$external_file_obj->delete_cache();
		}

		// remove the downloaded files of the sizes, which have not been changed and exist with the same name on the
		// external sources: they are kept there and not uploaded again.
		$exported = wp_list_pluck( $sizes_obj->get_sizes( $attachment_id ), 'file' );
		foreach ( $files as $size ) {
			$path = $directory . (string) ( $size['file'] ?? '' );
			if ( in_array( $path, $session['created'], true ) && in_array( wp_basename( $path ), $exported, true ) && ! $this->is_changed( $attachment_id, $path ) ) {
				wp_delete_file( $path );
			}
		}

		// remove the downloaded backups of other plugins, which have not been changed: they are kept on the external
		// sources and not uploaded again.
		foreach ( $session['backups'] ?? array() as $name => $path ) {
			if ( file_exists( $path ) && ! $this->is_changed( $attachment_id, $path ) ) {
				wp_delete_file( $path );
				$this->sessions[ $attachment_id ]['kept_backups'][] = (string) $name;
			}
		}
	}

	/**
	 * Export the changed file of the image itself (e.g. edited or renamed) to each external source of the image.
	 *
	 * The file is uploaded, if it exists locally. Otherwise, a former file with its name is used again (e.g. after
	 * "Restore image" in the image editor). The previous file is kept as former file or deleted.
	 *
	 * @param File   $external_file_obj The external file object.
	 * @param string $file              The absolute path of the new local file.
	 * @param bool   $keep_replaced     True to keep the previous file as former file.
	 *
	 * @return void
	 */
	private function export_main_file( File $external_file_obj, string $file, bool $keep_replaced ): void {
		// get the attachment ID.
		$attachment_id = $external_file_obj->get_id();

		// get the URL before the change.
		$old_url = $this->sessions[ $attachment_id ]['url'];

		// get the names.
		$new_name = wp_basename( $file );
		$old_name = (string) get_post_meta( $attachment_id, 'efml_export_filename', true );
		if ( '' === $old_name ) {
			$old_name = wp_basename( $old_url );
		}

		// bail if the name has not changed: the image keeps its URL.
		if ( $new_name === $old_name ) {
			update_post_meta( $attachment_id, '_wp_attached_file', $old_url );
			return;
		}

		// let the export objects read the new local file.
		$local_exists = Helper::get_wp_filesystem()->exists( $file );
		update_post_meta( $attachment_id, '_wp_attached_file', _wp_relative_upload_path( $file ) );

		// export it to each external source (the main external source is the last one).
		$main_url = '';
		$sizes    = Sizes::get_instance();
		foreach ( $sizes->get_export_sources_with_sizes( $attachment_id, false ) as $term_id => $export_obj ) {
			// get the target directory and the credentials.
			$target_dir  = $this->get_target_directory( $attachment_id, $term_id );
			$credentials = $this->get_credentials( $term_id, $external_file_obj );

			// upload the local file or use a former file with this name.
			$url = false;
			if ( $local_exists ) {
				$url = $export_obj->export_file( $attachment_id, $target_dir . $new_name, $credentials );
			} else {
				$former = $sizes->take_former_file( $attachment_id, $term_id, $new_name );
				$url    = ! empty( $former['url'] ) ? (string) $former['url'] : false;
			}

			// bail if it could not be exported.
			if ( ! is_string( $url ) || '' === $url ) {
				/* translators: %1$s will be replaced by a file name. */
				Log::get_instance()->create( sprintf( __( 'The changed image %1$s could not be exported to an external source.', 'external-files-in-media-library' ), '<code>' . esc_html( $new_name ) . '</code>' ), $target_dir . $new_name, 'error' );
				continue;
			}

			// get the previous file on this external source.
			$previous = array(
				'file'   => $old_name,
				'url'    => $old_url,
				'target' => $target_dir . $old_name,
			);
			if ( ! $this->is_main_term( $attachment_id, $term_id ) ) {
				$previous['url'] = $previous['target'];
			}

			// keep it as former file or delete it.
			if ( $keep_replaced || ! $export_obj->delete_exported_file( (string) $previous['url'], $credentials, $attachment_id ) ) {
				$sizes->add_former_file( $attachment_id, $term_id, $previous );
			}

			// remember the URL on the main external source.
			if ( $this->is_main_term( $attachment_id, $term_id ) ) {
				$main_url = $url;
			}
		}

		// bail if the file could not be exported to the main external source: the image keeps its file.
		if ( '' === $main_url ) {
			update_post_meta( $attachment_id, '_wp_attached_file', $old_url );
			return;
		}

		// remember the former name, so the proxy still finds the image via its former URLs.
		add_post_meta( $attachment_id, Files::FORMER_TITLES_META, $old_name );

		// save the new local paths: the new file first, the former ones are kept to replace old local URLs in contents.
		Local_Urls::get_instance()->add_local_path( $attachment_id, _wp_relative_upload_path( $file ) );

		// use the new file.
		$external_file_obj->set_url( $main_url );
		$external_file_obj->set_title( $new_name );
		update_post_meta( $attachment_id, 'efml_export_filename', $new_name );
		update_post_meta( $attachment_id, '_wp_attached_file', $main_url );

		// log this event.
		/* translators: %1$s will be replaced by a file name. */
		Log::get_instance()->create( sprintf( __( 'The changed image %1$s has been exported.', 'external-files-in-media-library' ), '<code>' . esc_html( $new_name ) . '</code>' ), $main_url, 'success', 1 );
	}

	/**
	 * Return whether the given external source is the main external source of the given image.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $term_id       The ID of the external source.
	 *
	 * @return bool
	 */
	private function is_main_term( int $attachment_id, int $term_id ): bool {
		$terms = wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) );
		return is_array( $terms ) && ! empty( $terms ) && absint( $terms[0] ) === $term_id;
	}

	/**
	 * Return the target directory of the given image on the given external source, with trailing slash.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $term_id       The ID of the external source.
	 *
	 * @return string
	 */
	private function get_target_directory( int $attachment_id, int $term_id ): string {
		$folders = get_post_meta( $attachment_id, 'efml_export_folders', true );
		$folder  = is_array( $folders ) && ! empty( $folders[ $term_id ] ) ? trailingslashit( (string) $folders[ $term_id ] ) : '';
		return trailingslashit( (string) get_term_meta( $term_id, 'path', true ) ) . $folder;
	}

	/**
	 * Return the credentials of the given external source for the given image.
	 *
	 * @param int  $term_id           The ID of the external source.
	 * @param File $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	private function get_credentials( int $term_id, File $external_file_obj ): array {
		$credentials            = Taxonomy::get_instance()->get_entry( $term_id );
		$credentials['private'] = $external_file_obj->is_private();
		return $credentials;
	}

	/**
	 * Return whether the files of the given attachment have been exported after the work with its local files.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_exported( int $attachment_id ): bool {
		return $this->is_open( $attachment_id ) && $this->sessions[ $attachment_id ]['exported'];
	}

	/**
	 * End the work with the local files of the given attachment: remove them, if local files are not kept.
	 *
	 * @param int  $attachment_id   The attachment ID.
	 * @param bool $update_metadata True to remove the sizes from the saved metadata (not needed, if WordPress saves it now).
	 *
	 * @return void
	 */
	public function close( int $attachment_id, bool $update_metadata = true ): void {
		// bail if the local files of this attachment are not in use.
		if ( ! $this->is_open( $attachment_id ) ) {
			return;
		}

		// export a changed file, which has not been exported yet (e.g. if no metadata has been saved after the change),
		// and the optimized files.
		if ( ! $this->sessions[ $attachment_id ]['exported'] && ( $this->sessions[ $attachment_id ]['main_changed'] || 'optimize' === $this->sessions[ $attachment_id ]['mode'] ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$this->export_files( $attachment_id, is_array( $metadata ) ? $metadata : array() );
		}

		// get the data and end the work.
		$session = $this->sessions[ $attachment_id ];
		unset( $this->sessions[ $attachment_id ] );

		// bail if the local files are kept after the export.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			return;
		}

		// get the files: the downloaded files and, if they have been exported, the new files.
		$files = $session['created'];
		if ( $session['exported'] ) {
			$files = array_merge( $files, $session['sizes'] );
			if ( $session['main_changed'] ) {
				$files[] = $session['file'];
			}
		}

		// remove them, only in the uploads directory.
		$base_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
		foreach ( array_unique( array_filter( $files ) ) as $file ) {
			if ( str_starts_with( $file, $base_dir ) && ! str_contains( $file, '..' ) ) {
				wp_delete_file( $file );
			}
		}

		// remove the sizes from the saved metadata, as their local files do not exist anymore.
		if ( $session['exported'] && $update_metadata ) {
			$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
			if ( is_array( $metadata ) && isset( $metadata['sizes'] ) ) {
				unset( $metadata['sizes'] );
				update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
			}
		}
	}

	/**
	 * End the work with the local files of all attachments at the end of the request.
	 *
	 * @return void
	 */
	public function close_all(): void {
		foreach ( array_keys( $this->sessions ) as $attachment_id ) {
			$this->close( $attachment_id );
		}
	}
}
