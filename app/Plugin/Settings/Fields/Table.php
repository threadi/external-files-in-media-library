<?php
/**
 * This file holds an object for a table field.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Settings\Fields;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Settings\Field_Base;
use ExternalFilesInMediaLibrary\Plugin\Settings\Setting;

/**
 * Object to handle a table for a setting.
 */
class Table extends Field_Base {
	/**
	 * The type name.
	 *
	 * @var string
	 */
	protected string $type_name = 'Table';

	/**
	 * List of options for each entry.
	 *
	 * @var array
	 */
	private array $table_options = array();

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

		// get values.
		$values = get_option( $setting->get_name() );
		if ( ! is_array( $values ) ) {
			$values = array();
		}

		// create table object.
		$table_obj = new \ExternalFilesInMediaLibrary\Plugin\Settings\Tables\Table();
		$table_obj->set_table_data( $values );
		$table_obj->set_table_options( $this->table_options );
		$table_obj->prepare_items();
		$table_obj->display();

		// add each item as setting.
		foreach ( $values as $value ) {
			?><input type="hidden" name="<?php echo esc_attr( $setting->get_name() ); ?>[]" value="<?php echo esc_attr( $value ); ?>">
			<?php
		}

		// show optional description for this checkbox.
		if ( ! empty( $field->get_description() ) ) {
			echo '<p>' . wp_kses_post( $field->get_description() ) . '</p>';
		}
	}

	/**
	 * Set options for each entry in the table.
	 *
	 * Format:
	 * array(
	 *     'url' => 'url_to_use',
	 *     'icon' => 'icon_to_use'
	 * )
	 *
	 * @param array $options List of options.
	 *
	 * @return void
	 */
	public function set_table_options( array $options ): void {
		$this->table_options = $options;
	}
}
