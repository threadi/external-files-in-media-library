<?php
/**
 * This file holds an object for a single select field.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings\Fields;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Field_Base;
use ExternalFilesInMediaLibrary\Plugin\Settings\Setting;

/**
 * Object to handle a select field for single setting.
 */
class Select extends Field_Base {
	/**
	 * The type name.
	 *
	 * @var string
	 */
	protected string $type_name = 'Select';

	/**
	 * The options for this field.
	 *
	 * @var array
	 */
	protected array $options = array();

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

		// get value.
		$value = get_option( $setting->get_name(), '' );

		?>
		<select id="<?php echo esc_attr( $setting->get_name() ); ?>" name="<?php echo esc_attr( $setting->get_name() ); ?>" class="eml-field-width" title="<?php echo esc_attr( $field->get_title() ); ?>">
			<?php
			foreach ( $this->get_options() as $key => $label ) {
				?>
				<option value="<?php echo esc_attr( $key ); ?>"<?php echo ( $value === (string) $key ? ' selected="selected"' : '' ); ?>><?php echo esc_html( $label ); ?></option>
				<?php
			}
			?>
		</select>
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

	/**
	 * Return the options for this field.
	 *
	 * @return array
	 */
	private function get_options(): array {
		return $this->options;
	}

	/**
	 * Set the options for this field.
	 *
	 * @param array $options List of options.
	 *
	 * @return void
	 */
	public function set_options( array $options ): void {
		$this->options = $options;
	}
}
