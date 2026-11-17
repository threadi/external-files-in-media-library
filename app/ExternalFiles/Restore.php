<?php
/**
 * File to restore exported files as local files at their original path.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to restore exported files as local files at their original path.
 *
 * Contents link the files with their local URLs, which are only replaced during the output (see Local_Urls).
 * Without this plugin these URLs are used again, so the files must be restored at exactly the same path
 * (relative to the uploads directory), including the files of their sizes. Restored files are normal local
 * files again without any connection to this plugin, as before their export.
 */
class Restore {
	/**
	 * Instance of actual object.
	 *
	 * @var ?Restore
	 */
	private static ?Restore $instance = null;

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
	 * @return Restore
	 */
	public static function get_instance(): Restore {
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
		// start the restore by request (also with disabled export).
		add_action( 'admin_action_efml_restore_exported_files', array( $this, 'restore_files_via_request' ) );

		// show a dialog before the deactivation of this plugin, if exported files exist.
		add_filter( 'plugin_action_links_' . plugin_basename( EFML_PLUGIN ), array( $this, 'add_deactivation_dialog' ), 20 );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( EFML_PLUGIN ), array( $this, 'add_deactivation_dialog' ), 20 );
	}

	/**
	 * Show a dialog on the link to deactivate this plugin, if exported files exist.
	 *
	 * Without this plugin the contents use the local URLs of the exported files again, which do not exist. The
	 * dialog offers to restore the files before.
	 *
	 * @param array<string,string> $links List of links.
	 *
	 * @return array<string,string>
	 */
	public function add_deactivation_dialog( array $links ): array {
		// bail if no deactivation link exists.
		if ( empty( $links['deactivate'] ) ) {
			return $links;
		}

		// bail if the user could not start the restore.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $links;
		}

		// get the number of exported files.
		$count = $this->count_files();

		// bail if no exported files exist.
		if ( 0 === $count ) {
			return $links;
		}

		// get the URL of the deactivation.
		if ( ! preg_match( '/href="([^"]+)"/', $links['deactivate'], $match ) ) {
			return $links;
		}
		$deactivation_url = html_entity_decode( $match[1] );

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Restore the exported files first?', 'external-files-in-media-library' ),
			'texts'     => array(
				/* translators: %1$d will be replaced by a number. */
				'<p><strong>' . sprintf( _n( '%1$d file of your media library is only saved on an external source.', '%1$d files of your media library are only saved on external sources.', $count, 'external-files-in-media-library' ), $count ) . '</strong></p>',
				'<p>' . __( 'Without this plugin your contents link them with their local URLs again, which do not exist in your hosting. They will be shown as broken files.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'Restore them as local files at their original path before you deactivate the plugin. They are restored one by one in the background, the admin bar shows the progress. Afterward a hint tells you, that you can deactivate the plugin.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . esc_url_raw( $this->get_restore_url() ) . '";',
					'variant' => 'primary',
					'text'    => __( 'Restore them first', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'location.href="' . esc_url_raw( $deactivation_url ) . '";',
					'variant' => 'secondary',
					'text'    => __( 'Deactivate nevertheless', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// replace the URL of the link by the dialog.
		$links['deactivate'] = (string) preg_replace( '/href="[^"]+"/', 'href="#" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '"', $links['deactivate'], 1 );

		// return the list of links.
		return $links;
	}

	/**
	 * Return whether exported files exist, which could be restored.
	 *
	 * @return bool
	 */
	public function has_files(): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT m.`post_id` FROM %i AS m WHERE m.`meta_key` = %s AND NOT EXISTS ( SELECT 1 FROM %i AS b WHERE b.`post_id` = m.`post_id` AND b.`meta_key` = %s ) LIMIT 1', $wpdb->postmeta, 'eml_exported_file', $wpdb->postmeta, Migrations::BACKUP_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}

	/**
	 * Return the URL to start the restore of all exported files.
	 *
	 * @param bool $delete_on_target True to delete the copies on the external sources afterward.
	 *
	 * @return string
	 */
	public function get_restore_url( bool $delete_on_target = false ): string {
		return add_query_arg(
			array(
				'action' => 'efml_restore_exported_files',
				'delete' => $delete_on_target ? 1 : 0,
				'nonce'  => wp_create_nonce( 'efml-restore-exported-files' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the dialog to start the restore of all exported files.
	 *
	 * @return array<string,mixed>
	 */
	public function get_dialog(): array {
		// collect the texts.
		$texts = array(
			'<p><strong>' . __( 'Do you really want to restore all exported files?', 'external-files-in-media-library' ) . '</strong></p>',
			'<p>' . __( 'The exported files are downloaded from their external sources and saved as local files at their original path in your media library. Their sizes are created again. Afterward they are normal local files without any connection to this plugin, so your contents work also without it.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'The export will be disabled for this, so new uploads stay local and no file is exported again.', 'external-files-in-media-library' ) . '</p>',
			'<p>' . __( 'The files are restored one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>',
		);

		// return the dialog.
		return array(
			'className' => 'efml',
			'title'     => __( 'Restore exported files', 'external-files-in-media-library' ),
			'texts'     => $texts,
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $this->get_restore_url() . '";',
					'variant' => 'primary',
					'text'    => __( 'Restore them and keep the copies on the external sources', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'location.href="' . $this->get_restore_url( true ) . '";',
					'variant' => 'secondary',
					'text'    => __( 'Restore them and delete the copies on the external sources', 'external-files-in-media-library' ),
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
	 * Start the restore of all exported files by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function restore_files_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-restore-exported-files', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! Export::get_instance()->is_settings_action_allowed() ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// start the restore.
		$result = Import_Runs::get_instance()->start_localize( 1 === absint( filter_input( INPUT_GET, 'delete', FILTER_SANITIZE_NUMBER_INT ) ) );

		// show the result (not, if all files have been restored already: the hint of the finished run shows it then).
		if ( '' !== $result['message'] ) {
			$transient_obj = \ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients::get_instance()->add();
			$transient_obj->set_name( 'efml_restore_exported_files' );
			$transient_obj->set_type( $result['success'] ? 'success' : 'error' );
			$transient_obj->set_message( '<strong>' . esc_html( $result['message'] ) . '</strong>' );
			$transient_obj->save();
		}

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Return the IDs of the next exported files, which could be restored, after the given attachment ID.
	 *
	 * Files taken over from other offload plugins are not included, as they still have their local files.
	 *
	 * @param int $after_id The attachment ID to start after.
	 * @param int $limit    The maximum number of files.
	 *
	 * @return array<int,int>
	 */
	public function get_files( int $after_id, int $limit ): array {
		global $wpdb;

		// get the files.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND p.`ID` > %d AND NOT EXISTS ( SELECT 1 FROM %i AS b WHERE b.`post_id` = p.`ID` AND b.`meta_key` = %s ) ORDER BY p.`ID` ASC LIMIT %d', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', $after_id, $wpdb->postmeta, Migrations::BACKUP_META, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return the list.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the number of exported files, which could be restored.
	 *
	 * @return int
	 */
	public function count_files(): int {
		global $wpdb;

		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i AS p INNER JOIN %i AS m ON m.`post_id` = p.`ID` AND m.`meta_key` = %s WHERE p.`post_type` = %s AND NOT EXISTS ( SELECT 1 FROM %i AS b WHERE b.`post_id` = p.`ID` AND b.`meta_key` = %s )', $wpdb->posts, $wpdb->postmeta, 'eml_exported_file', 'attachment', $wpdb->postmeta, Migrations::BACKUP_META ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Restore as many exported files as possible until the given deadline and return the number of remaining files.
	 *
	 * Used during the uninstallation and the reset of the plugin. Files, which could not be restored, stay
	 * exported files.
	 *
	 * @param float $deadline         The deadline as timestamp with microseconds.
	 * @param bool  $delete_on_target True to delete the copies on the external sources.
	 *
	 * @return int
	 */
	public function restore_until( float $deadline, bool $delete_on_target = false ): int {
		// restore the files one by one.
		$after_id = 0;
		while ( microtime( true ) < $deadline ) {
			// get the next files.
			$ids = $this->get_files( $after_id, 50 );

			// bail if no files are left.
			if ( empty( $ids ) ) {
				break;
			}

			// restore each of them.
			foreach ( $ids as $attachment_id ) {
				// remember the position, so files, which could not be restored, are not tried again.
				$after_id = $attachment_id;

				// bail if the time is over.
				if ( microtime( true ) >= $deadline ) {
					break 2;
				}

				// restore it.
				$this->restore_file( $attachment_id, $delete_on_target );
			}
		}

		// return the number of remaining files.
		return $this->count_files();
	}

	/**
	 * Restore the given exported file as local file at its original path.
	 *
	 * @param int  $attachment_id    The attachment ID.
	 * @param bool $delete_on_target True to delete the copies on the external sources afterward.
	 *
	 * @return bool
	 */
	public function restore_file( int $attachment_id, bool $delete_on_target = false ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// get the URL for the log.
		$url = $external_file_obj->get_url( true );

		// bail if this is not an exported file.
		if ( ! $external_file_obj->is_valid() || 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			Log::get_instance()->create( __( 'The file could not be restored, as it is not an exported file.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// bail if this file has been taken over from another offload plugin, as it still has its local files.
		if ( is_array( get_post_meta( $attachment_id, Migrations::BACKUP_META, true ) ) ) {
			Log::get_instance()->create( __( 'The file has been taken over from another offload plugin and still has its local files. Use "wp eml migration revert" to switch back.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the original paths.
		$paths = $this->get_original_paths( $attachment_id );

		// bail if they are unknown.
		if ( empty( $paths['file'] ) ) {
			Log::get_instance()->create( __( 'The file could not be restored, as its original path in the uploads directory is unknown.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get the absolute paths.
		$base_dir      = trailingslashit( wp_get_upload_dir()['basedir'] );
		$file          = $base_dir . $paths['file'];
		$original_file = '' !== $paths['original'] ? $base_dir . $paths['original'] : '';

		// bail if one of the paths is used by another file in the media library: nothing is overwritten.
		foreach ( array_filter( array( $paths['file'], $paths['original'] ) ) as $path ) {
			if ( $this->is_path_used_by_other_file( $path, $attachment_id ) ) {
				/* translators: %1$s will be replaced by a path. */
				Log::get_instance()->create( sprintf( __( 'The file could not be restored, as its original path %1$s is used by another file in the media library.', 'external-files-in-media-library' ), '<code>' . esc_html( $path ) . '</code>' ), $url, 'error' );
				return false;
			}
		}

		// get the file, from which the sizes are created: the original image, if the file has been a scaled image.
		$source_file = '' !== $original_file ? $original_file : $file;

		// get the local files: the kept local copy or the download.
		if ( false === $this->fetch_local_files( $external_file_obj, $file, $original_file ) ) {
			return false;
		}

		// save the variants in other formats (e.g. WebP) at their local paths, so image optimizers find them again.
		Variants::get_instance()->restore( $external_file_obj );

		// get the file names of the sizes before the export (before the post meta of this plugin is removed).
		$sizes = Local_Urls::get_instance()->get_local_sizes( $attachment_id );

		// delete the copies on the external sources, if requested.
		if ( $delete_on_target ) {
			Export::get_instance()->delete_exported_file( $attachment_id );
		}

		// remove the connection of this file to this plugin.
		$this->remove_connection( $external_file_obj );

		// set the original path.
		update_post_meta( $attachment_id, '_wp_attached_file', $paths['file'] );

		// create the metadata and the sizes again.
		$this->generate_metadata( $attachment_id, $source_file, $paths, $sizes );

		// log this event.
		/* translators: %1$s will be replaced by a path. */
		Log::get_instance()->create( sprintf( __( 'The file has been restored as local file at its original path %1$s.', 'external-files-in-media-library' ), '<code>' . esc_html( $paths['file'] ) . '</code>' ), $url, 'success', 2 );

		/**
		 * Run additional tasks after an exported file has been restored as local file.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int    $attachment_id The attachment ID.
		 * @param string $url           The former external URL of the file.
		 */
		do_action( 'efml_file_restored', $attachment_id, $url );

		// return true as the file has been restored.
		return true;
	}

	/**
	 * Save the local files of the given exported file at the given absolute paths.
	 *
	 * Uses the local copy, if it has been kept during the export, otherwise the files are downloaded: the file itself
	 * and its exported original image (since 6.0.0). Missing files are created from each other.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param File   $external_file_obj The external file object.
	 * @param string $file              The absolute path of the file.
	 * @param string $original_file     The absolute path of its original image or an empty string.
	 *
	 * @return array<int,string>|false List of the files, which have been created, or false on error.
	 */
	public function fetch_local_files( File $external_file_obj, string $file, string $original_file ): array|false {
		// get the URL for the log.
		$url = $external_file_obj->get_url( true );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the files, which exist before.
		$existing = array_filter( array( $file, $original_file ), array( $wp_filesystem, 'exists' ) );

		// get the file, from which the sizes are created: the original image, if the file has been a scaled image.
		$source_file = '' !== $original_file ? $original_file : $file;

		// use the local copy, if it has been kept during the export.
		if ( $wp_filesystem->exists( $file ) ) {
			Log::get_instance()->create( __( 'The local copy of the file, which has been kept during the export, is used.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// add the original image, if it is missing.
			if ( '' !== $original_file && ! $wp_filesystem->exists( $original_file ) ) {
				$wp_filesystem->copy( $file, $original_file );
			}
		} else {
			// get the exported original image, if it exists (since 6.0.0).
			$original_url = '' !== $original_file ? (string) ( Sizes::get_instance()->get_sizes( $external_file_obj->get_id(), true )[ Sizes::ORIGINAL ]['url'] ?? '' ) : '';

			// create the directory.
			wp_mkdir_p( dirname( $file ) );

			// download the file itself and the original image, if it has been exported.
			if ( '' !== $original_url ) {
				// bail if the file could not be downloaded.
				if ( ! $this->download( $external_file_obj, $file ) ) {
					return false;
				}

				// download the original image: use the file itself, if this is not possible.
				if ( ! $this->download( $external_file_obj, $original_file, array( $original_url ) ) ) {
					$wp_filesystem->copy( $file, $original_file );
				}
			} elseif ( ! $this->download( $external_file_obj, $source_file ) ) {
				// bail if the file could not be downloaded.
				return false;
			}
		}

		// add the scaled file from the original image.
		if ( '' !== $original_file && ! $wp_filesystem->exists( $file ) ) {
			$wp_filesystem->copy( $original_file, $file );
		}

		// return the created files.
		return array_values( array_diff( array_filter( array( $file, $original_file ), array( $wp_filesystem, 'exists' ) ), $existing ) );
	}

	/**
	 * Return the original paths of the given exported file relative to the uploads directory.
	 *
	 * "file" is the attached file (e.g. image-scaled.jpg), "original" its original image (e.g. image.jpg) or empty.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array{file: string, original: string}
	 */
	public function get_original_paths( int $attachment_id ): array {
		// get the paths saved during the export.
		$paths = Local_Urls::get_instance()->get_local_paths( $attachment_id );

		// use the GUID for files exported before 6.0.0, if their paths are not saved yet.
		if ( empty( $paths ) ) {
			$path = Local_Urls::get_instance()->get_relative_path( (string) get_post_field( 'guid', $attachment_id, 'raw' ) );
			if ( '' !== $path ) {
				$paths = array( $path );
			}
		}

		// remove invalid paths.
		$paths = array_values( array_filter( $paths, array( $this, 'is_valid_path' ) ) );

		// get the original image: the path with the name of the original image in the metadata (the further paths are
		// former paths of the file, e.g. before it has been edited or renamed).
		$original = '';
		$metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
		$name     = is_array( $metadata ) && ! empty( $metadata['original_image'] ) && is_string( $metadata['original_image'] ) ? wp_basename( $metadata['original_image'] ) : '';
		foreach ( array_slice( $paths, 1 ) as $path ) {
			if ( '' !== $name && wp_basename( $path ) === $name && $path !== $paths[0] ) {
				$original = $path;
				break;
			}
		}

		// return them.
		return array(
			'file'     => $paths[0] ?? '',
			'original' => $original,
		);
	}

	/**
	 * Return whether the given path relative to the uploads directory could be used for a restored file.
	 *
	 * @param string $path The path.
	 *
	 * @return bool
	 */
	private function is_valid_path( string $path ): bool {
		return '' !== $path && ! str_contains( $path, '..' ) && ! str_starts_with( $path, '/' ) && ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) && 0 === validate_file( $path );
	}

	/**
	 * Return whether the given path relative to the uploads directory is used by another attachment.
	 *
	 * @param string $path          The path.
	 * @param int    $attachment_id The ID of the attachment, which is restored.
	 *
	 * @return bool
	 */
	private function is_path_used_by_other_file( string $path, int $attachment_id ): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` = %s AND `meta_value` = %s AND `post_id` != %d LIMIT 1', $wpdb->postmeta, '_wp_attached_file', $path, $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}

	/**
	 * Download the given external file to the given absolute path.
	 *
	 * @param File              $external_file_obj The external file object.
	 * @param string            $path              The absolute path.
	 * @param array<int,string> $urls              The URLs to use instead of the URL of the file (e.g. of its original image).
	 *
	 * @return bool
	 */
	public function download( File $external_file_obj, string $path, array $urls = array() ): bool {
		// get the URL.
		$url = $external_file_obj->get_url( true );

		// get the protocol handler.
		$protocol_handler_obj = $external_file_obj->get_protocol_handler_obj();

		// bail if no protocol handler could be loaded (e.g. the extension of its service is not active).
		if ( ! $protocol_handler_obj ) {
			Log::get_instance()->create( __( 'The file could not be restored, as the protocol handler for its URL could not be found. Check whether the extension of its service is active.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		if ( empty( $urls ) ) {
			/**
			 * Filter the URLs, from which an exported file is downloaded for its restore. They are tried one by one.
			 *
			 * E.g. Dropbox adds the path of the file for its API, as the public URL of an exported file could expire.
			 *
			 * @since 6.0.0 Available since 6.0.0.
			 * @param array<int,string> $urls              List of URLs.
			 * @param File              $external_file_obj The external file object.
			 */
			$urls = apply_filters( 'efml_restore_download_urls', array( $url ), $external_file_obj );
		}

		// download the file from the first URL, which works.
		$tmp_file = false;
		foreach ( $urls as $download_url ) {
			try {
				$tmp_file = $protocol_handler_obj->get_temp_file( (string) $download_url, $wp_filesystem );
			} catch ( \Throwable $e ) {
				/* translators: %1$s will be replaced by an error message. */
				Log::get_instance()->create( sprintf( __( 'Error during the download of the file for its restore: %1$s', 'external-files-in-media-library' ), '<code>' . esc_html( $e->getMessage() ) . '</code>' ), (string) $download_url, 'error' );
				$tmp_file = false;
			}

			// bail if this is not a valid file.
			if ( ! is_string( $tmp_file ) || ! $wp_filesystem->exists( $tmp_file ) || ! $this->is_valid_download( $tmp_file, $external_file_obj ) ) {
				if ( is_string( $tmp_file ) ) {
					$wp_filesystem->delete( $tmp_file );
				}
				$tmp_file = false;
				continue;
			}

			// use this file.
			break;
		}

		// bail if it could not be downloaded.
		if ( ! is_string( $tmp_file ) ) {
			Log::get_instance()->create( __( 'The file could not be restored, as it could not be downloaded from its external source.', 'external-files-in-media-library' ), $url, 'error' );
			return false;
		}

		// create the directory.
		wp_mkdir_p( dirname( $path ) );

		// move the file to its original path.
		if ( ! $wp_filesystem->move( $tmp_file, $path, true ) ) {
			$wp_filesystem->delete( $tmp_file );
			/* translators: %1$s will be replaced by a path. */
			Log::get_instance()->create( sprintf( __( 'The file could not be restored, as it could not be saved at %1$s.', 'external-files-in-media-library' ), '<code>' . esc_html( $path ) . '</code>' ), $url, 'error' );
			return false;
		}

		// return true as the file has been downloaded.
		return true;
	}

	/**
	 * Return whether the given downloaded file matches the given external file.
	 *
	 * Prevents saving an error page (e.g. of an expired link) as image.
	 *
	 * @param string $file              The absolute path of the downloaded file.
	 * @param File   $external_file_obj The external file object.
	 *
	 * @return bool
	 */
	private function is_valid_download( string $file, File $external_file_obj ): bool {
		// bail if the file is empty.
		if ( 0 === absint( filesize( $file ) ) ) {
			Log::get_instance()->create( __( 'The downloaded file is empty.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );
			return false;
		}

		// bail if this is not an image.
		if ( ! str_starts_with( (string) get_post_mime_type( $external_file_obj->get_id() ), 'image/' ) ) {
			return true;
		}

		// bail if the download is an image.
		if ( false !== wp_get_image_mime( $file ) ) {
			return true;
		}

		// log this event.
		Log::get_instance()->create( __( 'The downloaded file is not an image (e.g. the error page of an expired link).', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );

		// return false as the file is not valid.
		return false;
	}

	/**
	 * Remove the connection of the given file to this plugin, so it is a normal local file again.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	private function remove_connection( File $external_file_obj ): void {
		// get the ID.
		$attachment_id = $external_file_obj->get_id();

		// remove the files in the cache of the proxy (before the URL is removed, as their names depend on it).
		$external_file_obj->delete_cache();
		$external_file_obj->delete_thumbs();
		Variants::get_instance()->delete_cache( $attachment_id );

		// remove all post meta of this plugin and its extensions.
		foreach ( Files::get_instance()->get_meta_keys() as $meta_key ) {
			delete_post_meta( $attachment_id, $meta_key );
		}

		// remove the assignment to the external sources.
		wp_delete_object_term_relationships( $attachment_id, Taxonomy::get_instance()->get_name() );

		// remove the entries of this file in the queue.
		Extensions\Queue::get_instance()->remove_export_entries_of_attachment( $attachment_id );
	}

	/**
	 * Create the metadata and the sizes of the given restored file with the names they had before the export.
	 *
	 * The sizes are created from the original image, so they get its name (e.g. image-300x200.jpg) as before.
	 *
	 * @param int                                  $attachment_id The attachment ID.
	 * @param string                               $source_file   The absolute path of the file to create the sizes from.
	 * @param array{file: string, original: string} $paths         The original paths relative to the uploads directory.
	 * @param array<string,string>                  $sizes         The file names of the sizes before the export (size name => file name).
	 *
	 * @return void
	 */
	private function generate_metadata( int $attachment_id, string $source_file, array $paths, array $sizes ): void {
		// bail if the function does not exist.
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// prevent the export of the restored file.
		$export_obj      = Export::get_instance();
		$export_priority = has_filter( 'wp_generate_attachment_metadata', array( $export_obj, 'export_file_by_upload' ) );
		if ( false !== $export_priority ) {
			remove_filter( 'wp_generate_attachment_metadata', array( $export_obj, 'export_file_by_upload' ), $export_priority );
		}

		// do not scale the image again, so the sizes get the names they had before.
		add_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );

		// create the metadata and the sizes.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $source_file );

		// restore the filters.
		remove_filter( 'big_image_size_threshold', '__return_false', PHP_INT_MAX );
		if ( false !== $export_priority ) {
			add_filter( 'wp_generate_attachment_metadata', array( $export_obj, 'export_file_by_upload' ), $export_priority, 2 );
		}

		// give the sizes their former names, if they differ (e.g. by one pixel, as they are created from the scaled image).
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$metadata['sizes'] = $this->rename_sizes( $metadata['sizes'], $sizes, trailingslashit( dirname( $source_file ) ) );
		}

		// use the scaled file as attached file again, if it has been one.
		if ( '' !== $paths['original'] ) {
			$metadata['file']           = $paths['file'];
			$metadata['original_image'] = wp_basename( $paths['original'] );

			// use the dimensions of the scaled file (the sizes have been created from the original image).
			$scaled_file = trailingslashit( wp_get_upload_dir()['basedir'] ) . $paths['file'];
			$image_size  = wp_getimagesize( $scaled_file );
			if ( is_array( $image_size ) ) {
				$metadata['width']    = absint( $image_size[0] );
				$metadata['height']   = absint( $image_size[1] );
				$metadata['filesize'] = absint( wp_filesize( $scaled_file ) );
			}
		}

		// save the metadata.
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// set the attached file again, as the creation of the metadata could have changed it.
		update_post_meta( $attachment_id, '_wp_attached_file', $paths['file'] );
	}

	/**
	 * Rename the files of the given new sizes to the names they had before the export and return the changed sizes.
	 *
	 * @param array<string,mixed>  $new_sizes The sizes from the new metadata.
	 * @param array<string,string> $old_sizes The file names of the sizes before the export (size name => file name).
	 * @param string               $directory The absolute path of the directory of the files.
	 *
	 * @return array<string,mixed>
	 */
	public function rename_sizes( array $new_sizes, array $old_sizes, string $directory ): array {
		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// check each size.
		foreach ( $new_sizes as $size_name => $size ) {
			// bail if the former name is unknown or the same.
			if ( ! is_array( $size ) || empty( $size['file'] ) || ! is_string( $size['file'] ) || empty( $old_sizes[ $size_name ] ) || $old_sizes[ $size_name ] === $size['file'] ) {
				continue;
			}

			// get the former name.
			$old_name = wp_basename( $old_sizes[ $size_name ] );

			// bail if a file with the former name exists already (e.g. another size uses it now).
			if ( $wp_filesystem->exists( $directory . $old_name ) ) {
				continue;
			}

			// rename the file.
			if ( ! $wp_filesystem->move( $directory . $size['file'], $directory . $old_name ) ) {
				continue;
			}

			// use the former name in the metadata.
			$new_sizes[ $size_name ]['file'] = $old_name;
		}

		// return the sizes.
		return $new_sizes;
	}
}
