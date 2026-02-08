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

	/**
	 * Return whether this tool is in use.
	 *
	 * @return bool
	 */
	public function is_in_use(): bool {
		return true;
	}

	/**
	 * Run tasks to disable this tool.
	 *
	 * We disable it by removing the capability.
	 *
	 * @return void
	 */
	public function disable(): void {
		update_option( 'eml_tools_settings_tools_' . $this->get_name() . '_allowed_roles', array() );
	}
}
