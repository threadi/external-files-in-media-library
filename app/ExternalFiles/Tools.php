<?php
/**
 * File for an object to handle tools in this plugin.
 *
 * Definition:
 * Tools will handle additional functions for external files in media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle tools in this plugin.
 */
class Tools {
	/**
	 * Instance of actual object.
	 *
	 * @var Tools|null
	 */
	private static ?Tools $instance = null;

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
	 * @return Tools
	 */
	public static function get_instance(): Tools {
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
	 * Return list of main tools for external files handling as objects.
	 *
	 * @return array<int,Tools_Base>
	 */
	public function get_tools_as_objects(): array {
		// create the list.
		$list = array();

		// install the schedules if they do not exist atm.
		foreach ( $this->get_tools() as $class_name ) {
			// bail if given object does not exist.
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			// bail if method is not callable.
			if ( ! method_exists( $class_name, 'get_instance' ) ) {
				continue;
			}

			// create the object name to call.
			$obj_name = $class_name . '::get_instance';

			// bail if this object name is not callable.
			if ( ! is_callable( $obj_name ) ) {
				continue;
			}

			// get the object.
			$obj = $obj_name();

			// bail if object is not "Tools_Base".
			if ( ! $obj instanceof Tools_Base ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of tools for external files as class names.
	 *
	 * @return array<int,string>
	 */
	private function get_tools(): array {
		$list = array(
			'ExternalFilesInMediaLibrary\ExternalFiles\Export',
			'ExternalFilesInMediaLibrary\ExternalFiles\Synchronization',
		);

		/**
		 * Filter the list of main tools for external files.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $list List of tools.
		 */
		return apply_filters( 'efml_tools', $list );
	}
}
