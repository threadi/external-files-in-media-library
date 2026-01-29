<?php
/**
 * File as base for each capability set.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Define the base object for capability sets.
 */
class CapabilitySet_Base {
	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Return the name of this object.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the title of this object.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Save the capabilities this set defines.
	 *
	 * @return void
	 */
	public function run(): void {}
}
