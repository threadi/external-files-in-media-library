<?php
/**
 * File to handle the 20minutly interval.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Intervals;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Interval_Base;

/**
 * Object to handle the 20minutly interval.
 */
class Minutly20 extends Interval_Base {

	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = '20minutely';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = 15 * MINUTE_IN_SECONDS;

	/**
	 * Instance of this object.
	 *
	 * @var ?Minutly20
	 */
	private static ?Minutly20 $instance = null;

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Minutly20 {
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
		return __( 'Every 20 minutes', 'external-files-in-media-library' );
	}
}
