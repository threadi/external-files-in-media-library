<?php
/**
 * File which provide the base functions for each ThirdParty-plugin we support.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ThirdParty;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle different ThirdParty-plugin-tasks.
 */
class ThirdParty_Base {
	/**
	 * Initialize this support.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Tasks to run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Return the files of the plugins, which this object supports (e.g. "plugin/plugin.php").
	 *
	 * They are used to check whether the plugin is active and to mark the supported plugins in the list of plugins.
	 * Return an empty list for support, which does not belong to a plugin (e.g. WordPress itself).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return array<int,string>
	 */
	public function get_plugin_files(): array {
		return array();
	}

	/**
	 * Return whether one of the supported plugins is active.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function is_supported_plugin_active(): bool {
		foreach ( $this->get_plugin_files() as $plugin_file ) {
			if ( \ExternalFilesInMediaLibrary\Plugin\Helper::is_plugin_active( $plugin_file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return the IDs of the folders or categories of the given selection.
	 *
	 * The configuration dialog saves checkboxes with the ID as key (e.g. array( 5 => 1 )), lists contain the IDs as
	 * values (e.g. array( 5, 7 )).
	 *
	 * @since 6.1.0 Available since 6.1.0.
	 *
	 * @param mixed $selection The selection.
	 *
	 * @return array<int,int>
	 */
	protected function get_ids_from_selection( mixed $selection ): array {
		// bail if nothing is selected.
		if ( empty( $selection ) ) {
			return array();
		}

		// use a single value.
		if ( ! is_array( $selection ) ) {
			$selection = array( $selection );
		}

		// get the IDs from the values of a list or from the keys of a map.
		$ids = array_is_list( $selection ) ? $selection : array_keys( $selection );

		// return the valid IDs.
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}
}
