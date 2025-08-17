<?php
/**
 * File to handle support for plugin "Advanced Media Offloader".
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use CatFolders\Models\FolderModel;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Helper;

/**
 * Object to handle support for this plugin.
 */
class AdvancedMediaOffloader extends ThirdParty_Base implements ThirdParty {

	/**
	 * Instance of actual object.
	 *
	 * @var ?AdvancedMediaOffloader
	 */
	private static ?AdvancedMediaOffloader $instance = null;

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
	 * @return AdvancedMediaOffloader
	 */
	public static function get_instance(): AdvancedMediaOffloader {
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
		// bail if plugin is not enabled.
		if ( ! Helper::is_plugin_active( 'advanced-media-offloader/advanced-media-offloader.php' ) ) {
			return;
		}

		// add hooks.
		add_filter( 'get_post_metadata', array( $this, 'prevent_offloading_for_external_files' ), 10, 3 );
		add_filter( 'get_post_metadata', array( $this, 'show_external_files_as_provider' ), 10, 3 );
	}

	/**
	 * Prevent the usage of external files as offloading files.
	 *
	 * @param mixed  $return_value The return value.
	 * @param int    $object_id The requested object ID.
	 * @param string $meta_key The used meta key.
	 *
	 * @return mixed
	 */
	public function prevent_offloading_for_external_files( mixed $return_value, int $object_id, string $meta_key ): mixed {
		// bail if meta key is not "advmo_offloaded".
		if ( 'advmo_offloaded' !== $meta_key ) {
			return $return_value;
		}

		// get the external file object for the given ID.
		$external_file_obj = Files::get_instance()->get_file( $object_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $return_value;
		}

		// return true to prevent the usage as offloading file.
		return true;
	}

	/**
	 * Show the external files plugin as provider for offloading.
	 *
	 * @param mixed  $return_value The return value.
	 * @param int    $object_id The requested object ID.
	 * @param string $meta_key The used meta key.
	 *
	 * @return mixed
	 */
	public function show_external_files_as_provider( mixed $return_value, int $object_id, string $meta_key ): mixed {
		// bail if meta key is not "advmo_provider".
		if ( 'advmo_provider' !== $meta_key ) {
			return $return_value;
		}

		// get the external file object for the given ID.
		$external_file_obj = Files::get_instance()->get_file( $object_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $return_value;
		}

		// return true to prevent the usage as offloading file.
		return Helper::get_plugin_name();
	}
}
