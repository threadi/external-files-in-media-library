<?php
/**
 * This file holds an object for a single number field.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings\Fields;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Field_Base;
use ExternalFilesInMediaLibrary\Plugin\Settings\Setting;
use ExternalFilesInMediaLibrary\Plugin\Settings\Settings;

/**
 * Object to handle a number field for single setting.
 */
class Number extends Field_Base {
	/**
	 * The type name.
	 *
	 * @var string
	 */
	protected string $type_name = 'Number';

	/**
	 * Return the HTML-code to display this field.
	 *
	 * @param array $attr Attributes for this field.
	 *
	 * @return void
	 */
	public function display( array $attr ): void {
		// bail if no attributes are set.
		if ( empty( $attr ) ) {
			return;
		}

		// bail if no setting object is set.
		if ( empty( $attr['setting'] ) ) {
			return;
		}

		// bail if field is not a Setting object.
		if ( ! $attr['setting'] instanceof Setting ) {
			return;
		}

		// get the setting object.
		$setting = $attr['setting'];

		// get the field object.
		$field = $setting->get_field();

		?>
		<input type="number" id="<?php echo esc_attr( $setting->get_name() ); ?>"
				name="<?php echo esc_attr( $setting->get_name() ); ?>"
				value="<?php echo absint( get_option( $setting->get_name(), 0 ) ); ?>"
			<?php
			echo ( $field->is_readonly() ? ' disabled="disabled"' : '' );
			?>
				class="<?php echo Settings::get_instance()->get_slug(); ?>-field-width"
				title="<?php echo esc_attr( $field->get_title() ); ?>"
		>
		<?php

		// show optional description for this checkbox.
		if ( ! empty( $field->get_description() ) ) {
			echo '<p>' . wp_kses_post( $field->get_description() ) . '</p>';
		}
	}

	/**
	 * The sanitize callback for this field.
	 *
	 * @param mixed $value The value to save.
	 *
	 * @return mixed
	 */
	public function sanitize_callback( mixed $value ): int {
		// bail if value is null.
		if ( is_null( $value ) ) {
			return 0;
		}

		// return the value.
		return absint( $value );
	}
}
