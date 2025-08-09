<?php
/**
 * File to handle support for the plugin Media Cloud Sync.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use WP_Query;

/**
 * Object to handle support for this plugin.
 */
class MediaCloudSync extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?MediaCloudSync
	 */
	private static ?MediaCloudSync $instance = null;

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
	 * @return MediaCloudSync
	 */
	public static function get_instance(): MediaCloudSync {
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
		add_action( 'wpmcs_get_relative_file_path_from_upload_directory', array( $this, 'prevent_usage_of_external_files' ), 10, 2 );
		add_filter( 'eml_is_import_running_for_mcs', array( $this, 'allow_real_import' ), 20 );
		add_filter( 'eml_is_import_running_for_mcs', array( $this, 'is_import_running' ) );
	}

	/**
	 * Prevent usage of our external files for their external file tasks.
	 *
	 * @param string $filename The file name.
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function prevent_usage_of_external_files( string $filename, string $url ): string {
		$prevent_import = false;
		/**
		 * Prevent import of external URLs via Media Cloud Sync.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $prevent_import True to prevent the import of external URLs in Media Cloud Sync.
		 * @param string $url The used URL.
		 *
		 * @noinspection PhpConditionAlreadyCheckedInspection
		 */
		if( apply_filters( 'eml_is_import_running_for_mcs', $prevent_import, $url ) ) {
			return '';
		}

		// get the post for the given filename.
		$query = array(
			'post_type' => 'attachment',
			'name' => basename( $url ),
			'post_status' => 'inherit',
			'fields' => 'ids'
		);
		$result = new WP_Query( $query );

		// bail if no result was found.
		if( 0 === $result->found_posts ) {
			return $filename;
		}

		// get external file object for the attachment URL.
		$external_file_obj = Files::get_instance()->get_file( absint( $result->get_posts()[0] ) );

		// bail if object is not valid.
		if( ! $external_file_obj->is_valid() ) {
			return $filename;
		}

		// return empty string to prevent its usage.
		return '';
	}

	/**
	 * Allow real import with Media Cloud Sync if option is not enabled.
	 *
	 * @param bool $result
	 *
	 * @return bool
	 */
	public function allow_real_import( bool $result ): bool {
		// get value from request.
		$real_import = isset( $_POST['real_import'] ) ? absint( $_POST['real_import'] ) : -1;

		// bail if it is not enabled during import.
		if ( 1 !== $real_import ) {
			return $result;
		}

		// return false to allow the import.
		return false;
	}

	/**
	 * Return true if our own import is running.
	 *
	 * @return bool
	 */
	public function is_import_running(): bool {
		return 'eml_add_external_urls' === filter_input( INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
	}
}
