<?php
/**
 * File to handle the hourly interval.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Intervals;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Interval_Base;

/**
 * Object to handle the hourly interval.
 */
class Hourly extends Interval_Base {

	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = 'hourly';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = HOUR_IN_SECONDS;

	/**
	 * Instance of this object.
	 *
	 * @var ?Hourly
	 */
	private static ?Hourly $instance = null;

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Hourly {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Return the title of this interval.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Hourly', 'external-files-in-media-library' );
	}
}
