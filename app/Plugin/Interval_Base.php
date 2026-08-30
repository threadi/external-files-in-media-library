<?php
/**
 * File to handle the base functions for each interval object.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to handle the base functions for each interval object.
 */
class Interval_Base {
	/**
	 * Name of the interval.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = 0;

	/**
	 * Constructor for this object.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	protected function __clone() {}

	/**
	 * Return the name of this interval.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the title of this interval.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return '';
	}

	/**
	 * Return the interval time in seconds.
	 *
	 * @return int
	 */
	public function get_time(): int {
		return $this->time;
	}
}
