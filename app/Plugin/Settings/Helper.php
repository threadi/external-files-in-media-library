<?php
/**
 * File with helper functions for settings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object with helper tasks for settings.
 */
class Helper {
	/**
	 * Return list of possible field types.
	 *
	 * @return array
	 */
	public static function get_field_types(): array {
		return array(
			'ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Checkbox',
			'ExternalFilesInMediaLibrary\Plugin\Settings\Fields\MultiSelect',
			'ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Number',
			'ExternalFilesInMediaLibrary\Plugin\Settings\Fields\Select',
		);
	}

	/**
	 * Get field object by type name.
	 *
	 * @param string $type_name The type name.
	 *
	 * @return false|Field_Base
	 */
	public static function get_field_by_type_name( string $type_name ): false|Field_Base {
		// bail if type name is empty.
		if ( empty( $type_name ) ) {
			return false;
		}

		// check each field type.
		foreach ( self::get_field_types() as $field_name ) {
			// bail if object does not exist.
			if ( ! class_exists( $field_name ) ) {
				continue;
			}

			// create object.
			$obj = new $field_name();

			// bail if object is not a Field_Base.
			if ( ! $obj instanceof Field_Base ) {
				continue;
			}

			// compare its name with the searched one.
			if ( $type_name !== $obj->get_type_name() ) {
				continue;
			}

			// return resulting object.
			return $obj;
		}

		// return false if not object could be found.
		return false;
	}
}
