<?php
/**
 * This file holds an object for a single button field.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings\Fields;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Field_Base;
use ExternalFilesInMediaLibrary\Plugin\Settings\Setting;

/**
 * Object to handle a checkbox for single setting.
 */
class Button extends Field_Base {
	/**
	 * The type name.
	 *
	 * @var string
	 */
	protected string $type_name = 'Button';

	/**
	 * The button title.
	 *
	 * @var string
	 */
	private string $button_title = '';

	/**
	 * The button URL.
	 *
	 * @var string
	 */
	private string $button_url = '#';

	/**
	 * Custom attributes for the button.
	 *
	 * @var array
	 */
	private array $button_custom_attributes = array();

	/**
	 * List of additional classes for the button.
	 *
	 * @var array
	 */
	private array $button_classes = array();

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

		// output.
		?>
		<a href="<?php echo esc_url( $this->get_button_url() ); ?>"<?php echo wp_kses_post( $this->get_custom_attributes() ); ?> class="button button-primary<?php echo esc_attr( $this->get_classes() ); ?>"><?php echo esc_html( $this->get_button_title() ); ?></a>
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
	 * Return the button title.
	 *
	 * @return string
	 */
	private function get_button_title(): string {
		return $this->button_title;
	}

	/**
	 * Set button title.
	 *
	 * @param string $title The title to set.
	 *
	 * @return void
	 */
	public function set_button_title( string $title ): void {
		$this->button_title = $title;
	}

	/**
	 * Return the button URL.
	 *
	 * @return string
	 */
	private function get_button_url(): string {
		return $this->button_url;
	}

	/**
	 * Set the URL.
	 *
	 * @param string $url The URL to use as button target.
	 *
	 * @return void
	 */
	public function set_button_url( string $url ): void {
		$this->button_url = $url;
	}

	/**
	 * Return the custom attributes as string for output.
	 *
	 * @return string
	 */
	private function get_custom_attributes(): string {
		$attributes = '';
		foreach ( $this->button_custom_attributes as $name => $value ) {
			$attributes .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}

		return $attributes;
	}

	/**
	 * Set custom attributes.
	 *
	 * @param array $custom_attributes List of custom attributes.
	 *
	 * @return void
	 */
	public function set_custom_attributes( array $custom_attributes ): void {
		$this->button_custom_attributes = $custom_attributes;
	}

	/**
	 * Return list of classes as string for output on button.
	 *
	 * @return string
	 */
	private function get_classes(): string {
		// get the list as string.
		$classes = implode( ' ', $this->button_classes );

		// bail if list is empty.
		if ( empty( $classes ) ) {
			return '';
		}

		// return the list of classes.
		return ' ' . $classes;
	}

	/**
	 * Add single class to the list of classes for the button.
	 *
	 * @param string $class_name The class name.
	 *
	 * @return void
	 */
	public function add_class( string $class_name ): void {
		$this->button_classes[] = $class_name;
	}
}
