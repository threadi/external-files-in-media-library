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
 * Object to provide the base functions for each file type we support.
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
	 * @var array<int>
	 */
	private array $size = array();

	/**
	 * The external file object which is handled here.
	 *
	 * @var File|false
	 */
	protected File|false $external_file_obj = false;

	/**
	 * The mime type.
	 *
	 * @var string
	 */
	private string $mime_type = '';

	/**
	 * Initialize this object.
	 *
	 * @param File|false $external_file_obj The external file as object or false.
	 */
	public function __construct( false|File|string $external_file_obj ) {
		if ( $external_file_obj instanceof File ) {
			$this->external_file_obj = $external_file_obj;
		}
	}

	/**
	 * Return whether the file is compatible with this object.
	 *
	 * @return bool
	 */
	public function is_file_compatible(): bool {
		// bail if list of possible mime types in object is empty.
		if ( empty( $this->get_mime_types() ) ) {
			return false;
		}

		// set the mime type.
		$mime_type = $this->get_mime_type();

		// get the external file object.
		$external_file_obj = $this->get_file();

		// get mime type from external file object, if set.
		if ( $external_file_obj ) {
			// use the mime type from the external file object.
			$mime_type = $external_file_obj->get_mime_type();
		}

		// check the mime types.
		$result = in_array( $mime_type, $this->get_mime_types(), true );

		// show deprecated warning for old hook name.
		$result = apply_filters_deprecated( 'eml_file_type_compatibility_result', array( $result, $external_file_obj, $mime_type ), '5.0.0', 'efml_file_type_compatibility_result' );

		/**
		 * Filter the result of file type compatibility check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $result The result (true or false).
		 * @param File|false $external_file_obj The external file object.
		 * @param string $mime_type The used mime type (added in 3.0.0).
		 */
		return apply_filters( 'efml_file_type_compatibility_result', $result, $external_file_obj, $mime_type );
	}

	/**
	 * Output the given proxied file.
	 *
	 * @return void
	 */
	public function get_proxied_file(): void {}

	/**
	 * Return the external file object.
	 *
	 * @return File|false
	 */
	protected function get_file(): File|false {
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

		// show deprecated warning for old hook name.
		$mime_type = apply_filters_deprecated( 'eml_file_type_supported_mime_types', array( $mime_type, $external_file_obj ), '5.0.0', 'efml_file_type_supported_mime_types' );

		/**
		 * Filter the supported mime types of single file type.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $mime_type List of mime types.
		 * @param File|false $external_file_obj The file object.
		 */
		return apply_filters( 'efml_file_type_supported_mime_types', $mime_type, $external_file_obj );
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
	 * Return the file type title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return the configured dimensions as array (0 => width, 1 => height).
	 *
	 * @return array<int>
	 */
	protected function get_dimensions(): array {
		return $this->size;
	}

	/**
	 * Set the dimensions to use.
	 *
	 * @param array<int> $size The dimensions as array (0 => width, 1 => height).
	 *
	 * @return void
	 */
	public function set_dimensions( array $size ): void {
		$this->size = $size;
	}

	/**
	 * Return whether this file should be saved locally.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		return false;
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
	 * Return whether files of this type are proxied by default.
	 *
	 * @return bool
	 */
	public function is_proxy_default_enabled(): bool {
		return true;
	}

	/**
	 * Return the default proxy max age.
	 *
	 * @return int
	 */
	public function get_default_proxy_max_age(): int {
		return 24;
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

	/**
	 * Return the given mime type.
	 *
	 * @return string
	 */
	protected function get_mime_type(): string {
		return $this->mime_type;
	}

	/**
	 * Set the mime type of the file.
	 *
	 * @param string $mime_type The given mime type.
	 *
	 * @return void
	 */
	public function set_mime_type( string $mime_type ): void {
		$this->mime_type = $mime_type;
	}
}
