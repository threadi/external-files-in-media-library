<?php
/**
 * File which provide the base functions for each file type we support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle different file types.
 */
class File_Types_Base {
	/**
	 * Name of the file type.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Define mime types this object is used for.
	 *
	 * @var array|string[]
	 */
	protected array $mime_types = array();

	/**
	 * The size (e.g. for images).
	 *
	 * @var array
	 */
	private array $size = array();

	/**
	 * The external file object which is handled here.
	 *
	 * @var File
	 */
	protected File $external_file_obj;

	/**
	 * The contructor for this object.
	 *
	 * @param File $external_file_obj The external file as object.
	 */
	public function __construct( File $external_file_obj ) {
		$this->external_file_obj = $external_file_obj;
	}

	/**
	 * Return whether the file is compatible with this object.
	 *
	 * @return bool
	 */
	public function is_file_compatible(): bool {
		// bail if list is empty.
		if ( empty( $this->get_mime_types() ) ) {
			return false;
		}

		// get the external file object.
		$external_file_obj = $this->get_file();

		// check the mime types.
		$result = in_array( $this->get_file()->get_mime_type(), $this->get_mime_types(), true );

		/**
		 * Filter the result of file type compatibility check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $result The result (true or false).
		 * @param File $external_file_obj The external file object.
		 */
		return apply_filters( 'eml_file_type_compatibility_result', $result, $external_file_obj );
	}

	/**
	 * Return the given proxied file.
	 *
	 * @return void
	 */
	public function get_proxied_file(): void {}

	/**
	 * Return the external file object.
	 *
	 * @return File
	 */
	protected function get_file(): File {
		return $this->external_file_obj;
	}

	/**
	 * Return the mime types this object could be used for.
	 *
	 * @return array|string[]
	 */
	private function get_mime_types(): array {
		$mime_type         = $this->mime_types;
		$external_file_obj = $this->get_file();

		/**
		 * Filter the supported mime types of single file type.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $mime_type List of mime types.
		 * @param File $external_file_obj The file object.
		 */
		return apply_filters( 'eml_file_type_supported_mime_types', $mime_type, $external_file_obj );
	}

	/**
	 * Set meta-data for the file by given file data.
	 *
	 * @return void
	 */
	public function set_metadata(): void {}

	/**
	 * Return the object name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the configured size.
	 *
	 * @return array
	 */
	protected function get_size(): array {
		return $this->size;
	}

	/**
	 * Set the size to use.
	 *
	 * @param array $size The size as array (0 => width, 1 => height).
	 *
	 * @return void
	 */
	public function set_size( array $size ): void {
		$this->size = $size;
	}

	/**
	 * Return whether this file should be proxied.
	 *
	 * @return bool
	 */
	public function is_proxy_enabled(): bool {
		return false;
	}

	/**
	 * Return true if cache age has been reached its expiration.
	 *
	 * @return bool
	 */
	public function is_cache_expired(): bool {
		return false;
	}

	/**
	 * Return whether this file type has thumbs.
	 *
	 * @return bool
	 */
	public function has_thumbs(): bool {
		return false;
	}
}
