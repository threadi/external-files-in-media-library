<?php
/**
 * File to remove the local copies of exported files afterward.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;

/**
 * Object to remove the local copies of exported files afterward.
 *
 * With the setting "Do not delete local files" the files stay in the uploads directory after their export. This is
 * the counterpart of the restore (see Restore): the local copies of such files are deleted later, e.g. to free storage
 * space. Only files, which exist on the external source, are deleted: the file itself is loaded from there before,
 * and its sizes, original image and variants only, if they have been exported. Files taken over from other offload
 * plugins keep their local files, as they are needed to switch back.
 */
class Local_Copies {
	/**
	 * Name of the transient, which caches whether local copies exist.
	 *
	 * @var string
	 */
	private const CACHE = 'efml_local_copies_exist';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Local_Copies
	 */
	private static ?Local_Copies $instance = null;

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
	 * @return Local_Copies
	 */
	public static function get_instance(): Local_Copies {
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
		// start the removal by request (also with disabled export).
		add_action( 'admin_action_efml_delete_local_copies', array( $this, 'delete_local_copies_via_request' ) );
	}

	/**
	 * Return the IDs of the next exported files after the given attachment ID, which could have local copies.
	 *
	 * These are all exported files except the files taken over from other offload plugins. Whether their local copies
	 * exist is checked via has_local_copy().
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of files.
	 *
	 * @return array<int,int>
	 */
	public function get_candidates( int $after_id, int $limit ): array {
		// get the files.
		$ids = Restore::get_instance()->get_files( $after_id, $limit );

		// load their post meta with one query, as each of them is checked.
		if ( ! empty( $ids ) ) {
			update_meta_cache( 'post', $ids );
		}

		// return the list.
		return $ids;
	}

	/**
	 * Return the IDs of the exported files with local copies after the given attachment ID.
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of files, which are checked.
	 *
	 * @return array<int,int>
	 */
	public function get_files( int $after_id, int $limit ): array {
		return array_values( array_filter( $this->get_candidates( $after_id, $limit ), array( $this, 'has_local_copy' ) ) );
	}

	/**
	 * Return the number of exported files with local copies.
	 *
	 * @return int
	 */
	public function count_files(): int {
		// check the files page by page.
		$count    = 0;
		$after_id = 0;
		do {
			$ids    = $this->get_candidates( $after_id, 500 );
			$number = count( $ids );
			if ( 0 === $number ) {
				break;
			}
			$after_id = (int) end( $ids );
			$count   += count( array_filter( $ids, array( $this, 'has_local_copy' ) ) );
		} while ( 500 === $number );

		// return the number.
		return $count;
	}

	/**
	 * Return whether exported files with local copies exist.
	 *
	 * The result is cached for an hour, as all exported files are checked, if none has a local copy.
	 *
	 * @return bool
	 */
	public function has_files(): bool {
		// use the cached result.
		$cached = get_transient( self::CACHE );
		if ( false !== $cached ) {
			return 1 === absint( $cached );
		}

		// check the files page by page until one with local copy is found.
		$result   = false;
		$after_id = 0;
		do {
			$ids    = $this->get_candidates( $after_id, 500 );
			$number = count( $ids );
			if ( 0 === $number ) {
				break;
			}
			$after_id = (int) end( $ids );
			foreach ( $ids as $attachment_id ) {
				if ( $this->has_local_copy( $attachment_id ) ) {
					$result = true;
					break 2;
				}
			}
		} while ( 500 === $number );

		// cache the result.
		set_transient( self::CACHE, $result ? 1 : 0, HOUR_IN_SECONDS );

		// return the result.
		return $result;
	}

	/**
	 * Forget the cached state, whether local copies exist.
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * Return whether the given exported file has a local copy (of the file itself or its original image).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function has_local_copy( int $attachment_id ): bool {
		// bail if the image has a working copy (e.g. for image optimizers), which is removed after the work.
		if ( Working_Copies::get_instance()->is_active( $attachment_id ) ) {
			return false;
		}

		// get the original paths.
		$paths = Restore::get_instance()->get_original_paths( $attachment_id );

		// get the uploads directory.
		$base_dir = trailingslashit( wp_get_upload_dir()['basedir'] );

		// return whether one of the files exists.
		foreach ( array( $paths['file'], $paths['original'] ) as $path ) {
			if ( '' !== $path && file_exists( $base_dir . $path ) && ! $this->is_used_by_other_attachment( $path, $attachment_id ) ) {
				return true;
			}
		}

		// return false as no local copy exists.
		return false;
	}

	/**
	 * Delete the local copies of the given exported file and return whether it has been successfully.
	 *
	 * The file itself is loaded from its external source before, its local copy is only deleted, if this works. The
	 * missing sizes of an image are exported before, if its external sources support it. Sizes, the original image and
	 * variants (e.g. WebP) are only deleted, if they have been exported. Other local files stay.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function delete_local_copies( int $attachment_id ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get its URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if this is not an exported file.
		if ( ! $external_file_obj->is_valid() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			return false;
		}

		// bail if it has been taken over from another offload plugin: its local files are needed to switch back.
		if ( ! empty( get_post_meta( $attachment_id, Migrations::BACKUP_META, true ) ) ) {
			Log::get_instance()->create( __( 'The local files of this file are kept, as it has been taken over from another offload plugin.', 'external-files-in-media-library' ), $url, 'info', 1 );
			return false;
		}

		// bail if WordPress works with its local files at the moment.
		if ( Local_Work::get_instance()->is_open( $attachment_id ) ) {
			return false;
		}

		// get the original paths.
		$paths = Restore::get_instance()->get_original_paths( $attachment_id );

		// bail if they are unknown.
		if ( '' === $paths['file'] ) {
			Log::get_instance()->create( __( 'The local copies of the exported file could not be deleted, as its original path is unknown.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// bail if no local copy exists.
		if ( ! $this->has_local_copy( $attachment_id ) ) {
			return true;
		}

		// load the file from its external source, so we know its copy there is usable.
		if ( ! $this->is_usable_on_external_source( $external_file_obj ) ) {
			Log::get_instance()->create( __( 'The local copies of the exported file are kept, as the file could not be loaded from its external source.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// export the missing sizes of an image (and its variants) from its local copy, if its external sources support it.
		if ( wp_attachment_is_image( $attachment_id ) && ! Sizes::get_instance()->has_sizes( $attachment_id ) && ! empty( Sizes::get_instance()->get_export_sources_with_sizes( $attachment_id ) ) ) {
			Sizes::get_instance()->export_sizes_of_exported_image( $attachment_id );
		}

		// get the names of the exported files (they are on the external source) and of all known local files.
		$names       = $this->get_exported_names( $attachment_id, $paths );
		$local_names = $this->get_local_names( $attachment_id, $paths );

		// get the uploads directory and the directory of the file.
		$base_dir  = trailingslashit( wp_get_upload_dir()['basedir'] );
		$directory = dirname( $paths['file'] );
		$directory = '.' === $directory ? '' : trailingslashit( $directory );

		// delete each existing local copy, which is not the file of another attachment.
		$deleted = 0;
		foreach ( $names as $name ) {
			// get the path.
			$path = $directory . $name;

			// bail if the path is not valid or the file does not exist.
			if ( str_contains( $path, '..' ) || 0 !== validate_file( $path ) || ! file_exists( $base_dir . $path ) ) {
				continue;
			}

			// bail if it is the file of another attachment.
			if ( $this->is_used_by_other_attachment( $path, $attachment_id ) ) {
				continue;
			}

			// delete it.
			wp_delete_file( $base_dir . $path );
			++$deleted;
		}

		// delete the local copies of the exported variants in other formats (e.g. WebP, also outside the uploads directory).
		foreach ( Variants::get_instance()->get_local_paths( $attachment_id ) as $path ) {
			if ( is_file( $path ) && ! $this->is_used_by_other_attachment( _wp_relative_upload_path( $path ), $attachment_id ) ) {
				wp_delete_file( $path );
				++$deleted;
			}
		}

		// remove the sizes from the metadata, as they are used from the external source now (like after an export
		// without local copies).
		$metadata = wp_get_attachment_metadata( $attachment_id, true );
		if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
			unset( $metadata['sizes'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// log the files, which have been kept, as they are not on the external source.
		$kept = array_values(
			array_filter(
				$local_names,
				static function ( string $name ) use ( $base_dir, $directory ): bool {
					return file_exists( $base_dir . $directory . $name );
				}
			)
		);
		if ( ! empty( $kept ) ) {
			/* translators: %1$s will be replaced by a list of file names. */
			Log::get_instance()->create( sprintf( __( 'These local files have been kept, as they do not exist on the external source: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( implode( ', ', $kept ) ) . '</code>' ), $url, 'info', 1 );
		}

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( _n( '%1$d local copy of the exported file has been deleted.', '%1$d local copies of the exported file have been deleted.', $deleted, 'external-files-in-media-library' ), $deleted ), $url, 'success', 1 );

		// return true as the local copies have been deleted.
		return true;
	}

	/**
	 * Return whether the given exported file could be loaded from its external source.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return bool
	 */
	private function is_usable_on_external_source( File $external_file_obj ): bool {
		// create a temporary path.
		$path = trailingslashit( get_temp_dir() ) . 'efml-local-copies-' . wp_generate_password( 12, false ) . '/file.tmp';

		// download the file.
		$result = Restore::get_instance()->download( $external_file_obj, $path );

		// remove the temporary files.
		Helper::delete_directory_recursively( dirname( $path ) );

		/**
		 * Filter whether an exported file is usable on its external source, before its local copies are deleted.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result            True if the file has been loaded from its external source.
		 * @param File $external_file_obj The external file object.
		 */
		return (bool) apply_filters( 'efml_local_copies_file_usable', $result, $external_file_obj );
	}

	/**
	 * Return the local names of the files of the given exported file, which exist on its external sources.
	 *
	 * These are the file itself, and its sizes, original image and former files, if they have been exported. A size is
	 * only used with its known local name: sizes of files, which got another name on the external source and whose
	 * local names are not known (exported before 6.0.0), are kept.
	 *
	 * @param int                                   $attachment_id The attachment ID.
	 * @param array{file: string, original: string} $paths         The original paths of the file.
	 *
	 * @return array<int,string>
	 */
	private function get_exported_names( int $attachment_id, array $paths ): array {
		// the file itself has been exported.
		$names = array( wp_basename( $paths['file'] ) );

		// get the exported files on the external sources.
		$data = Sizes::get_instance()->get_data( $attachment_id );

		// get the local names of the sizes and whether the names on the external source are the same as the local names.
		$local_sizes = Local_Urls::get_instance()->get_local_sizes( $attachment_id );
		$same_names  = (string) get_post_meta( $attachment_id, 'efml_export_filename', true ) === wp_basename( $paths['file'] );

		// add the exported sizes and the original image.
		foreach ( $data['terms'] as $files ) {
			foreach ( (array) $files as $size_name => $file ) {
				// the original image.
				if ( Sizes::ORIGINAL === $size_name ) {
					if ( '' !== $paths['original'] ) {
						$names[] = wp_basename( $paths['original'] );
					}
					continue;
				}

				// use the local name of the size, or the name on the external source, if it is the same.
				if ( ! empty( $local_sizes[ $size_name ] ) ) {
					$names[] = wp_basename( (string) $local_sizes[ $size_name ] );
				} elseif ( $same_names && ! empty( $file['file'] ) ) {
					$names[] = wp_basename( (string) $file['file'] );
				}
			}
		}

		// add the former files (e.g. before the image has been edited), if their names are the same.
		if ( $same_names ) {
			foreach ( $data['former'] as $files ) {
				foreach ( (array) $files as $file ) {
					if ( ! empty( $file['file'] ) ) {
						$names[] = wp_basename( (string) $file['file'] );
					}
				}
			}
		}

		// return the list without duplicates.
		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * Return the names of all known local files of the given exported file (file, original image, sizes, backups).
	 *
	 * @param int                                   $attachment_id The attachment ID.
	 * @param array{file: string, original: string} $paths         The original paths of the file.
	 *
	 * @return array<int,string>
	 */
	private function get_local_names( int $attachment_id, array $paths ): array {
		// the file and its original image.
		$names = array( wp_basename( $paths['file'] ), '' !== $paths['original'] ? wp_basename( $paths['original'] ) : '' );

		// the local names of the sizes.
		$names = array_merge( $names, array_map( 'wp_basename', array_map( 'strval', Local_Urls::get_instance()->get_local_sizes( $attachment_id ) ) ) );

		// the sizes in the metadata and the backups of the image editor.
		foreach ( array( '_wp_attachment_metadata', '_wp_attachment_backup_sizes' ) as $meta_key ) {
			$meta = get_post_meta( $attachment_id, $meta_key, true );
			if ( ! is_array( $meta ) ) {
				continue;
			}
			$entries = '_wp_attachment_metadata' === $meta_key ? ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() ) : $meta;
			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['file'] ) && is_string( $entry['file'] ) ) {
					$names[] = wp_basename( $entry['file'] );
				}
			}
		}

		// return the list without duplicates.
		return array_values( array_unique( array_filter( $names ) ) );
	}

	/**
	 * Return whether the given path relative to the uploads directory is the attached file of another attachment.
	 *
	 * @param string $path          The path.
	 * @param int    $attachment_id The ID of the attachment.
	 *
	 * @return bool
	 */
	private function is_used_by_other_attachment( string $path, int $attachment_id ): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s AND `post_id` != %d LIMIT 1', $wpdb->postmeta, '_wp_attached_file', $path, $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}

	/**
	 * Return the URL to start the deletion of the local copies.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return add_query_arg(
			array(
				'action' => 'efml_delete_local_copies',
				'nonce'  => wp_create_nonce( 'efml-delete-local-copies' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the dialog to start the deletion of the local copies.
	 *
	 * @return array<string,mixed>
	 */
	public function get_dialog(): array {
		// collect the texts.
		$texts = array(
			'<p><strong>' . __( 'Do you really want to delete the local copies of the exported files?', 'external-files-in-media-library' ) . '</strong></p>',
			'<p>' . __( 'Exported files, whose local files have been kept, are used from their external sources only afterward. Each file is loaded from its external source before, its local copy is only deleted, if this works. Sizes, original images and variants (e.g. WebP) are only deleted, if they exist on the external source. Files taken over from other offload plugins keep their local files.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'The files are processed one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>',
		);

		// add a hint, if new exports keep their local files.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			$texts[] = '<p>' . __( 'The setting "Do not delete local files" is still enabled, so files, which are exported in future, keep their local files.', 'external-files-in-media-library' ) . '</p>';
		}

		// return the dialog.
		return array(
			'className' => 'efml',
			'title'     => __( 'Delete local copies', 'external-files-in-media-library' ),
			'texts'     => $texts,
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $this->get_url() . '";',
					'variant' => 'primary',
					'text'    => __( 'Delete the local copies', 'external-files-in-media-library' ),
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
	 * Start the deletion of the local copies by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function delete_local_copies_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-delete-local-copies', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// start the deletion.
		$result = Import_Runs::get_instance()->start_delete_local();

		// show the result (not, if all files have been processed already: the hint of the finished run shows it then).
		if ( '' !== $result['message'] ) {
			$transient_obj = Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_delete_local_copies' );
			$transient_obj->set_type( $result['success'] ? 'success' : 'error' );
			$transient_obj->set_message( '<strong>' . esc_html( $result['message'] ) . '</strong>' );
			$transient_obj->save();
		}

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Return whether the deletion of local copies is paused (on copies of the website, as the missing sizes are
	 * exported to the external sources).
	 *
	 * @return bool
	 */
	public function is_paused(): bool {
		return Site_Copy::get_instance()->is_copy();
	}
}
