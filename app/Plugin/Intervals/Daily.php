<?php
/**
 * File to handle the daily interval.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Intervals;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Interval_Base;

/**
 * Object to handle the daily interval.
 */
class Daily extends Interval_Base {

	/**
	 * Name of the method.
	 *
	 * @var string
	 */
	protected string $name = '24hourly';

	/**
	 * Time of the interval.
	 *
	 * @var int
	 */
	protected int $time = DAY_IN_SECONDS;

	/**
	 * Instance of this object.
	 *
	 * @var ?Daily
	 */
	private static ?Daily $instance = null;

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Daily {
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
		return __( 'Once a day', 'external-files-in-media-library' );
	}
}
