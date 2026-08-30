<?php
/**
 * File to handle the 4hourly interval.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Intervals;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Interval_Base;

/**
 * Object to handle the 4hourly interval.
 */
class Hourly4 extends Interval_Base {

	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = '4hourly';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = 4 * HOUR_IN_SECONDS;

	/**
	 * Instance of this object.
	 *
	 * @var ?Hourly4
	 */
	private static ?Hourly4 $instance = null;

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Hourly4 {
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
		return __( 'Every 4 hours', 'external-files-in-media-library' );
	}
}
