<?php
/**
 * File to handle the 2hourly interval.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Intervals;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Interval_Base;

/**
 * Object to handle the 2hourly interval.
 */
class Hourly2 extends Interval_Base {

	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = '2hourly';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = 2 * HOUR_IN_SECONDS;

	/**
	 * Instance of this object.
	 *
	 * @var ?Hourly2
	 */
	private static ?Hourly2 $instance = null;

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Hourly2 {
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
		return __( 'Every 2 hours', 'external-files-in-media-library' );
	}
}
