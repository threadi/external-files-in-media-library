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
	 * @var array<int,File_Types_Base>
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
	 * @return array<string>
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
		 * @param array<string> $list List of file type handler.
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
		// use cached object.
		if ( ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// get the file type object.
		return $this->get_type_object_by_mime_type( $external_file_obj->get_mime_type(), $external_file_obj );
	}

	/**
	 * Get the file type object by given URL and mime type.
	 *
	 * @param string     $mime_type The mime type.
	 * @param false|File $external_file_obj The external file object or simply false.
	 *
	 * @return File_Types_Base
	 */
	public function get_type_object_by_mime_type( string $mime_type, false|File $external_file_obj = false ): File_Types_Base {
		// bail with default file object if mime type is not given.
		if ( empty( $mime_type ) ) {
			// return the default file object if nothing matches.
			return new File_Types\File( $external_file_obj );
		}

		// use cached object.
		if ( $external_file_obj && ! empty( $this->files[ $external_file_obj->get_id() ] ) ) {
			return $this->files[ $external_file_obj->get_id() ];
		}

		// check each file type for compatibility with the given file.
		foreach ( $this->get_file_types() as $file_type ) {
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

			// set the mime type.
			$file_type_obj->set_mime_type( $mime_type );

			// bail if given file does not match.
			if ( ! $file_type_obj->is_file_compatible() ) {
				continue;
			}

			// log this event.
			/* translators: %1$s will be replaced by the type name (e.g. "Images"). */
			Log::get_instance()->create( sprintf( __( 'File has the type %1$s.', 'external-files-in-media-library' ), '<i>' . $file_type_obj->get_name() . '</i>' ), $external_file_obj ? $external_file_obj->get_url( true ) : '', 'info', 2 );

			// add to the list.
			if ( $external_file_obj ) {
				$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
			}

			// return this object.
			return $file_type_obj;
		}

		// log this event.
		Log::get_instance()->create( __( 'File type could not be detected. Fallback to general file.', 'external-files-in-media-library' ), $external_file_obj ? $external_file_obj->get_url( true ) : '', 'info', 1 );

		// get object.
		$file_type_obj = new File_Types\File( $external_file_obj );

		// add to the cache list.
		if ( $external_file_obj ) {
			$this->files[ $external_file_obj->get_id() ] = $file_type_obj;
		}

		// return the default file object if nothing matches.
		return $file_type_obj;
	}

	/**
	 * Return true if any proxy for any file is enabled.
	 *
	 * @return bool
	 */
	public function is_any_proxy_enabled(): bool {
		// check each supported file type.
		foreach ( $this->get_file_types() as $file_type ) {
			// bail if object does not exist.
			if ( ! class_exists( $file_type ) ) {
				continue;
			}

			// get the object.
			$file_type_obj = new $file_type( false );

			// bail if object is not a file type base object.
			if ( ! $file_type_obj instanceof File_Types_Base ) {
				continue;
			}

			// bail if proxy for this file type is not enabled.
			if ( ! $file_type_obj->is_proxy_enabled() ) {
				continue;
			}

			// return true if proxy is enabled.
			return true;
		}

		// return false if no proxy is enabled.
		return false;
	}
}
