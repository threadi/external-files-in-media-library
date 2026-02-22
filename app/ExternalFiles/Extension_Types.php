<?php
/**
 * File, which handle the different extension types.
 *
 * Hint: these are not file extensions but extensions for functionalities of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the different extension types.
 */
class Extension_Types {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Extension_Types
	 */
	private static ?Extension_Types $instance = null;

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
	 * @return Extension_Types
	 */
	public static function get_instance(): Extension_Types {
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
	public function init(): void {}

	/**
	 * Return list of supported extension types.
	 *
	 * @return array<int,string>
	 */
	private function get_extension_types(): array {
		$list = array(
			'advanced',
			'import',
			'import_dialog',
			'export_dialog',
			'sync_dialog',
		);

		/**
		 * Filter the list of supported extension types.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $list List of supported extension types.
		 */
		return apply_filters( 'efml_extension_types', $list );
	}

	/**
	 * Return list of extensions for a given extension type.
	 *
	 * @param string $type_name The name of the extension type.
	 *
	 * @return array<int,Extension_Base>
	 */
	public function get_extensions_for_type( string $type_name ): array {
		// bail if given type is not supported.
		if ( ! in_array( $type_name, $this->get_extension_types(), true ) ) {
			return array();
		}

		// collect the list.
		$list = array();

		// loop through all supported file handling extensions.
		foreach ( Extensions::get_instance()->get_extensions() as $method_class_name ) {
			// get function name.
			$class_name = $method_class_name . '::get_instance';

			// bail if class is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// get object.
			$obj = $class_name();

			// bail if object is not "Extension_Base".
			if ( ! $obj instanceof Extension_Base ) {
				continue;
			}

			// bail if the extension is not of this type.
			if ( ! in_array( $type_name, $obj->get_types(), true ) ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}
}
