<?php
/**
 * File to handle the import of any external file.
 *
 * This is also a directory listing object.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle the import of files.
 */
class Import extends Directory_Listing_Base {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'import';

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Use a URL';

	/**
	 * The process start time for import.
	 *
	 * @var float
	 */
	private float $start_time = 0.0;

	/**
	 * The import identifier.
	 *
	 * @var string
	 */
	private string $identifier = '';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Import
	 */
	private static ?Import $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {    }

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {    }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Import
	 */
	public static function get_instance(): Import {
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
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'efml_directory_listing_objects', array( $this, 'add_directory_listing' ) );
		add_action( 'eml_file_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_ftp_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_http_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_action( 'eml_sftp_directory_import_file_before_to_list', array( $this, 'check_runtime' ), 10, 2 );
		add_filter( 'eml_file_import_title', array( $this, 'optimize_file_title' ), 10, 3 );
		add_filter( 'eml_file_import_title', array( $this, 'set_file_title' ), 10, 3 );
	}

	/**
	 * Initialize additional tasks for wp-admin.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		$this->label = __( 'Use a URL', 'external-files-in-media-library' );
	}

	/**
	 * Add this object to the list of listing objects on the first position.
	 *
	 * @param array<Directory_Listing_Base> $directory_listing_objects List of directory listing objects.
	 *
	 * @return array<Directory_Listing_Base>
	 */
	public function add_directory_listing( array $directory_listing_objects ): array {
		array_unshift( $directory_listing_objects, $this );
		return $directory_listing_objects;
	}

	/**
	 * Return a custom view URL.
	 *
	 * @return string
	 */
	public function get_view_url(): string {
		return get_admin_url() . 'media-new.php';
	}

	/**
	 * Add a URL in media library.
	 *
	 * This is the main function for any integration of external URLs.
	 *
	 * If URL is a directory we try to import all files from this directory.
	 * If URL is a single file, this single file will be imported.
	 *
	 * The import will be use a protocol handler matching the protocol of the used URL.
	 *
	 * @param string $url The URL to add.
	 *
	 * @return bool true if anything from the URL has been added successfully.
	 */
	public function add_url( string $url ): bool {
		$false    = false;
		$login    = $this->get_login();
		$password = $this->get_password();
		/**
		 * Prevent import of this single URL.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $false Return true if normal import should not be started.
		 * @param string $url The given URL.
		 * @param string $login The login to use.
		 * @param string $password The password to use.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if ( apply_filters( 'eml_prevent_import', $false, $url, $login, $password ) ) {
			return false;
		}

		// get the log object.
		$log = Log::get_instance();

		/**
		 * Save the start time.
		 */
		$this->set_import_start_time();

		/**
		 * Get the handler for this URL depending on its protocol.
		 */
		$protocol_handler_obj = Protocols::get_instance()->get_protocol_object_for_url( $url );

		/**
		 * Do nothing if URL is using a not supported tcp protocol (event will be logged via @Protocols in detail).
		 */
		if ( ! $protocol_handler_obj ) {
			return false;
		}

		/**
		 * Add the given credentials, even if none are set.
		 */
		$protocol_handler_obj->set_login( $login );
		$protocol_handler_obj->set_password( $password );

		// embed necessary files.
		require_once ABSPATH . 'wp-admin/includes/image.php'; // @phpstan-ignore requireOnce.fileNotFound
		require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		require_once ABSPATH . 'wp-admin/includes/media.php'; // @phpstan-ignore requireOnce.fileNotFound

		/**
		 * Get information about files under the given URL.
		 */
		$files = $protocol_handler_obj->get_url_infos();

		/**
		 * Do nothing if check of URL resulted in empty file list.
		 */
		if ( empty( $files ) ) {
			return false;
		}

		/**
		 * Get user the attachment would be assigned to.
		 */
		$user_id = Helper::get_current_user_id();

		/**
		 * Filter the user_id for a single file during import.
		 *
		 * @since 1.1.0 Available since 1.1.0
		 *
		 * @param int $user_id The title generated by importer.
		 * @param string $url The requested external URL.
		 */
		$user_id = apply_filters( 'eml_file_import_user', $user_id, $url );

		/**
		 * Run action just before we go through the list of resulting files.
		 */
		do_action( 'eml_before_file_list', $url, $files );

		// show progress.
		/* translators: %1$s is replaced by a URL. */
		$progress = Helper::is_cli() ? \WP_CLI\Utils\make_progress_bar( _n( 'Save file from URL', 'Save files from URL', count( $files ), 'external-files-in-media-library' ), count( $files ) ) : '';

		/**
		 * Loop through the results and save each in the media library.
		 */
		foreach ( $files as $file_data ) {
			/**
			 * Run additional tasks before new external file will be added.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param array $file_data The array with the file data.
			 */
			do_action( 'eml_before_file_save', $file_data );

			// get the title and url as string.
			$title    = Helper::get_as_string( $file_data['title'] );
			$file_url = Helper::get_as_string( $file_data['url'] );

			/**
			 * Filter the title for a single file during import.
			 *
			 * @since 1.1.0 Available since 1.1.0
			 *
			 * @param string $title     The title generated by importer.
			 * @param string $file_url       The requested external URL.
			 * @param array<string,mixed>  $file_data List of file settings detected by importer.
			 */
			$title = apply_filters( 'eml_file_import_title', $title, $file_url, $file_data );

			/**
			 * Prepare attachment-post-settings.
			 */
			$post_array = array(
				'post_author' => $user_id,
				'post_name'   => $title,
			);

			/**
			 * Filter the attachment settings
			 *
			 * @since 2.0.0 Available since 2.0.0
			 *
			 * @param array<string,mixed> $post_array     The attachment settings.
			 * @param string $file_url       The requested external URL.
			 * @param array<string,mixed>  $file_data List of file settings detected by importer.
			 */
			$post_array = apply_filters( 'eml_file_import_attachment', $post_array, $file_url, $file_data );

			/**
			 * Run action just before the file is saved in database.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 *
			 * @param string $file_url   The URL to import.
			 */
			do_action( 'eml_file_import_before_save', $file_url );

			/**
			 * Save this file local if it is required.
			 */
			if ( false !== $file_data['local'] ) {
				// log this event.
				$log->create( __( 'The URL will be saved locally.', 'external-files-in-media-library' ), $file_url, 'info', 2, $this->get_identified() );

				// import file as attachment via WP-own functions, if ID is not already set.
				if ( empty( $post_array['ID'] ) ) {
					$array         = array(
						'name'     => $title,
						'type'     => $file_data['mime-type'],
						'tmp_name' => $file_data['tmp-file'],
						'error'    => '0',
						'size'     => $file_data['filesize'],
					);
					$attachment_id = media_handle_sideload( $array, 0, null, $post_array );
				} else {
					$attachment_id = $post_array['ID'];
				}

				// delete the tmp file (if media_handle_sideload() does not have it already done).
				if ( is_string( $file_data['tmp-file'] ) && file_exists( $file_data['tmp-file'] ) ) {
					// get WP Filesystem-handler.
					$wp_filesystem = Helper::get_wp_filesystem( 'local' );
					$wp_filesystem->delete( $file_data['tmp-file'] );

					// log this event.
					$log->create( __( 'The temp file for the import was deleted.', 'external-files-in-media-library' ), $file_url, 'info', 2, $this->get_identified() );
				}
			} else {
				// log this event.
				$log->create( __( 'The URL will remain external, we simply save the link to the file in the media library.', 'external-files-in-media-library' ), $url, 'info', 2, $this->get_identified() );

				/**
				 * For all other files: simply create the attachment.
				 */
				$attachment_id = wp_insert_attachment( $post_array, $file_url );
			}

			// bail on any error.
			if ( is_wp_error( $attachment_id ) ) {
				/* translators: %1$s will be replaced by a WP-error-message */
				$log->create( sprintf( __( 'The URL could not be saved due to the following error: %1$s', 'external-files-in-media-library' ), '<code>' . wp_json_encode( $attachment_id->errors['upload_error'][0] ) . '</code>' ), $file_url, 'error', 0, $this->get_identified() );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail to next file.
				continue;
			}

			// get external file object to update its settings.
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );

			// bail if object could not be loaded.
			if ( ! $external_file_obj ) {
				// log event.
				$log->create( __( 'External file object for URL could not be loaded.', 'external-files-in-media-library' ), $file_url, 'error', 0, $this->get_identified() );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail to next file.
				continue;
			}

			// do not handle this file as external file it the option for it is enabled.
			$no_external_object = false;
			/**
			 * Filter whether we import no external files.
			 *
			 * Return true if we only import files in media db without external URL.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool $no_external_object The marker.
			 * @param string $url The used URL.
			 * @param array $file_data The file data.
			 * @param File $external_file_obj The resulting external file (without any configuration yet).
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'eml_import_no_external_file', $no_external_object, $url, $file_data, $external_file_obj ) ) {
				/**
				 * Run additional tasks after new external file has been added.
				 *
				 * @since 2.0.0 Available since 2.0.0.
				 * @param File $external_file_obj The object of the external file.
				 * @param array $file_data The array with the file data.
				 * @param string $url The source URL.
				 */
				do_action( 'eml_after_file_save', $external_file_obj, $file_data, $url );

				// log event.
				$log->create( __( 'File from URL has been saved as local file. It will not be handled as external file.', 'external-files-in-media-library' ), $file_url, 'success', 0, $this->get_identified() );

				// show progress.
				$progress ? $progress->tick() : '';

				// bail to next file.
				continue;
			}

			// mark this attachment as one of our own plugin through setting the URL.
			$external_file_obj->set_url( $file_url );

			// set title.
			$external_file_obj->set_title( $title );

			// set mime-type.
			$external_file_obj->set_mime_type( Helper::get_as_string( $file_data['mime-type'] ) );

			// set availability-status (true is for 'is available', false if it is not).
			$external_file_obj->set_availability( true );

			// set filesize.
			$external_file_obj->set_filesize( absint( $file_data['filesize'] ) );

			// mark if this file is an external file locally saved.
			$external_file_obj->set_is_local_saved( (bool) $file_data['local'] );

			// save the credentials on the object, if set.
			$external_file_obj->set_login( $this->get_login() );
			$external_file_obj->set_password( $this->get_password() );

			// save file-type-specific meta data.
			$external_file_obj->set_metadata();

			// add file to local cache, if necessary.
			$external_file_obj->add_to_cache();

			// set date of import (this is not the attachment datetime).
			$external_file_obj->set_date();

			// log that URL has been added as file in media library.
			$log->create( __( 'URL successfully added in media library.', 'external-files-in-media-library' ), $file_url, 'success', 0, $this->get_identified() );
			$log->create( __( 'Using following settings to save this URL:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $file_data ) . '</code>', $file_url, 'success', 2, $this->get_identified() );

			/**
			 * Run additional tasks after new external file has been added.
			 *
			 * @since 2.0.0 Available since 2.0.0.
			 * @param File $external_file_obj The object of the external file.
			 * @param array $file_data The array with the file data.
			 * @param string $url The source URL.
			 */
			do_action( 'eml_after_file_save', $external_file_obj, $file_data, $url );

			// show progress.
			$progress ? $progress->tick() : '';
		}

		// finish the progress.
		$progress ? $progress->finish() : '';

		// return ok.
		return true;
	}

	/**
	 * Return the import start time.
	 *
	 * @return float
	 */
	private function get_import_start_time(): float {
		return $this->start_time;
	}

	/**
	 * Set the start time.
	 *
	 * @return void
	 */
	private function set_import_start_time(): void {
		$this->start_time = microtime( true );
	}

	/**
	 * Check runtime of the actual PHP-process.
	 *
	 * If it is nearly the configured max_execution_time kill itself.
	 *
	 * In addition to max_execution_time, there are other timeouts in PHP that we cannot access.
	 * Therefore, this is only an approximation of the possible environment.
	 *
	 * As soon as the assumed maximum value is reached, all other URLs in the run are placed
	 * in the queue and the import is aborted by killing the PHP process.
	 *
	 * @param string           $url The actual processed file URL.
	 * @param array<int,mixed> $file_list List of files to process.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function check_runtime( string $url, array $file_list ): void {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_max_execution_check' ) ) ) {
			return;
		}

		// get max_execution_time setting.
		$max_execution_time = absint( ini_get( 'max_execution_time' ) ); // in seconds.

		// bail if max_execution_time is 0 or -1 (e.g. via WP CLI).
		if ( $max_execution_time <= 0 ) {
			return;
		}

		// get the actual runtime.
		$runtime = microtime( true ) - $this->get_import_start_time();

		// cancel process if runtime is nearly reached.
		if ( $runtime >= $max_execution_time ) {
			// log the event.
			Log::get_instance()->create( __( 'Import process was terminated because it took too long and would have reached the maximum execution time in hosting. The files to be imported were saved in the queue and are now automatically imported individually.', 'external-files-in-media-library' ), '', 'info', 2, $this->get_identified() );

			// kill process.
			exit;
		}
	}

	/**
	 * URL-decode the file-title if it is used in admin (via AJAX).
	 * Also sanitize the filename for full compatibility with requirements (incl. file extension).
	 *
	 * @param string              $title The title to optimize.
	 * @param string              $url The used URL.
	 * @param array<string,mixed> $file_data The file data.
	 *
	 * @return string
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function optimize_file_title( string $title, string $url, array $file_data ): string {
		$title = sanitize_file_name( urldecode( $title ) );

		// bail if title does contain an "." as it seems to be had a file extension.
		if ( false !== str_contains( $title, '.' ) ) {
			return $title;
		}

		// get all possible mime-types.
		$mime_types = Helper::get_possible_mime_types();

		// bail with half title if mime type is unknown.
		if ( empty( $mime_types[ $file_data['mime-type'] ] ) ) {
			return $title;
		}

		// return title with added file extension.
		return $title . '.' . $mime_types[ $file_data['mime-type'] ]['ext'];
	}

	/**
	 * Set the file title.
	 *
	 * @param string              $title The title.
	 * @param string              $url   The used URL.
	 * @param array<string,mixed> $file_data The file data.
	 *
	 * @return string
	 */
	public function set_file_title( string $title, string $url, array $file_data ): string {
		// bail if title is set.
		if ( ! empty( $title ) ) {
			return $title;
		}

		// get URL data.
		$url_info = wp_parse_url( $url );

		// bail if url_info is empty.
		if ( empty( $url_info ) ) {
			return $title;
		}

		// get all possible mime-types our plugin supports.
		$mime_types = Helper::get_possible_mime_types();

		// get basename of path, if available.
		$title = basename( $url_info['path'] );

		// add file extension if we support the mime-type and if the title does not have any atm.
		if ( ! empty( $mime_types[ $file_data['mime-type'] ] ) && empty( pathinfo( $title, PATHINFO_EXTENSION ) ) ) {
			$title .= '.' . $mime_types[ $file_data['mime-type'] ]['ext'];
		}

		// return resulting list of file data.
		return $title;
	}

	/**
	 * Return a unique import identifier.
	 *
	 * Create one if it does not already exist.
	 *
	 * @return string
	 */
	public function get_identified(): string {
		// create a new identifier if none exist atm.
		if ( empty( $this->identifier ) ) {
			$this->identifier = uniqid( '', true );
		}

		// return the identifier.
		return $this->identifier;
	}
}
