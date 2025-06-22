<?php
/**
 * This file an object to handle extension for file handlings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
use ExternalFilesInMediaLibrary\Plugin\Crypt_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Object to handle extension for file handlings.
 */
class Extensions {
	/**
	 * Instance of actual object.
	 *
	 * @var Extensions|null
	 */
	private static ?Extensions $instance = null;

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
	 * @return Extensions
	 */
	public static function get_instance(): Extensions {
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
		// loop through all supported file handling extensions.
		foreach ( $this->get_extensions_as_objects() as $obj ) {
			$obj->init();
		}
	}

	/**
	 * Return the list of file handling extension objects.
	 *
	 * @return array<int,Extension_Base>
	 */
	public function get_extensions_as_objects(): array {
		// collect the list.
		$list = array();

		// loop through all supported file handling extensions.
		foreach ( $this->get_extensions() as $method_class_name ) {
			// get function name.
			$class_name = $method_class_name . '::get_instance';

			// bail if class is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// get object.
			$obj = $class_name();

			// bail if object is not Crypt_Base.
			if ( ! $obj instanceof Extension_Base ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Run the installation tasks on each file handling extension.
	 *
	 * @return void
	 */
	public function install(): void {
		// loop through all supported file handling extensions.
		foreach ( $this->get_extensions_as_objects() as $obj ) {
			$obj->install();
		}
	}

	/**
	 * Run the installation tasks on each file handling extension.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		// loop through all supported file handling extensions.
		foreach ( $this->get_extensions_as_objects() as $obj ) {
			$obj->uninstall();
		}
	}

	/**
	 * Return list of supported extensions for file handling.
	 *
	 * @return array<int,string>
	 */
	private function get_extensions(): array {
		$list = array(
			'\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Dates',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue',
			'\ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Real_Import',
		);

		/**
		 * Filter the list of available file handling extensions.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of extensions.
		 */
		return apply_filters( '', $list );
	}
}
