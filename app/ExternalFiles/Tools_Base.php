<?php
/**
 * File for the object to handle base tasks for tools.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object as base for tools.
 */
class Tools_Base {
	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Return the name of this schedule.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return whether is extension require a capability to use it.
	 *
	 * @return bool
	 */
	public function has_capability(): bool {
		return false;
	}

	/**
	 * Return the default roles with capability for this object.
	 *
	 * @return array<int,string>
	 */
	public function get_capability_default(): array {
		return array();
	}

	/**
	 * Return the description for the capability settings.
	 *
	 * @return string
	 */
	public function get_capability_description(): string {
		return '';
	}
}
