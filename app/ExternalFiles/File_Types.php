<?php
/**
 * File which handle different file types.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to handle different protocols.
 */
class File_Types {

	/**
	 * Instance of actual object.
	 *
	 * @var ?File_Types
	 */
	private static ?File_Types $instance = null;

	/**
	 * List of files.
	 *
	 * @var array
	 */
	private array $files = array();

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
	 * @return File_Types
	 */
	public static function get_instance(): File_Types {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return list of supported file types.
	 *
	 * @return array
	 */
	private function get_file_types(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Audio',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\File',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Image',
			'ExternalFilesInMediaLibrary\ExternalFiles\File_Types\Video',
		);

		/**
		 * Filter the list of available file types.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array $list List of file type handler.
		 */
		return apply_filters( 'eml_file_types', $list );
	}

	/**
	 * Return the file handler for the given file object.
	 *
	 * This can be used before an external file object for this URL exist.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return File_Types_Base
	 */
	public function get_type_object_for_file_obj( File $external_file_obj ): File_Types_Base {
		// bail with default file object if mime type is not given.
		if ( empty( $external_file_obj->get_mime_type() ) ) {
			// return the default file object if nothing matches.
			return new File_Types\File( $external_file_obj );
		}

		// use cached object.
		if ( ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// check each file type for compatibility with the given file.
		foreach ( $this->get_file_types() as $file_type ) {
			// bail if file type is not a string.
			if ( ! is_string( $file_type ) ) {
				continue;
			}

			// bail if object does not exist.
			if ( ! class_exists( $file_type ) ) {
				continue;
			}

			// get the object.
			$file_type_obj = new $file_type( $external_file_obj );

			// bail if object is not of File_Types_Base.
			if ( ! $file_type_obj instanceof File_Types_Base ) {
				continue;
			}

			// bail if given file does not match.
			if ( ! $file_type_obj->is_file_compatible() ) {
				continue;
			}

			// log this event.
			/* translators: %1$s will be replaced by the type name (e.g. "Images"). */
			Log::get_instance()->create( sprintf( __( 'File has the type %1$s.', 'external-files-in-media-library' ), '<i>' . $file_type_obj->get_name() . '</i>' ), $external_file_obj->get_url( true ), 'info', 2 );

			// add to the list.
			$this->files[ $external_file_obj->get_id() ] = $file_type_obj;

			// return this object.
			return $file_type_obj;
		}

		// log this event.
		Log::get_instance()->create( __( 'File type could not be detected. Fallback to general file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 1 );

		// get object.
		$file_type_obj = new File_Types\File( $external_file_obj );

		// add to the list.
		$this->files[ $external_file_obj->get_id() ] = $file_type_obj;

		// return the default file object if nothing matches.
		return $file_type_obj;
	}
}
