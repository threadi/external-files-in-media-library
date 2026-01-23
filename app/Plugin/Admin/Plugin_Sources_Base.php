<?php
/**
 * File as base for each plugin source.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Define the base object for plugin sources.
 */
class Plugin_Sources_Base {
	/**
	 * Name of this source.
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
	 * Return the absolute path of the given file from the external source.
	 *
	 * Returns an empty string on any failure.
	 *
	 * @param array<string,mixed> $config The specific configuration.
	 *
	 * @return string
	 */
	public function get_file( array $config ): string {
		if ( empty( $config ) ) {
			return '';
		}
		return '';
	}
}
