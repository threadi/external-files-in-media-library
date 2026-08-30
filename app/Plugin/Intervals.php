<?php
/**
 * File with a handler for our own intervals.
 *
 * Hint: we use our own intervals to prevent intervals of other plugins from being used.
 * This would be an unnecessary dependency that leads to missing execution of our own schedules.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object for our own intervals.
 */
class Intervals {
	/**
	 * Instance of this object.
	 *
	 * @var ?Intervals
	 */
	private static ?Intervals $instance = null;

	/**
	 * Constructor for this object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Intervals {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize all schedules of this plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'cron_schedules', array( $this, 'add_intervals' ) );
	}

	/**
	 * Return the list of our own intervals.
	 *
	 * @return array<int,string>
	 */
	private function get_intervals(): array {
		// add the list.
		$list = array(
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly15',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly20',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Minutly30',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly2',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly3',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly4',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly6',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Hourly12',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Daily',
			'\ExternalFilesInMediaLibrary\Plugin\Intervals\Weekly',
		);

		/**
		 * Filter the list of possible intervals.
		 *
		 * @since 5.4.0 Available since 5.4.0.
		 * @param array<int,string> $list List of our interval objects.
		 */
		return apply_filters( 'efml_intervals', $list );
	}

	/**
	 * Return the list of available intervals as objects.
	 *
	 * @return array<int,Interval_Base>
	 */
	public function get_intervals_as_objects(): array {
		// define the list for the objects.
		$list = array();

		// loop through our compatibility-checks.
		foreach ( $this->get_intervals() as $interval_class_name ) {
			// get the class name.
			$class_name = $interval_class_name . '::get_instance';

			// bail if it is not callable.
			if ( ! is_callable( $class_name ) ) {
				continue;
			}

			// get the object.
			$obj = $class_name();

			// bail if the object is not an "Interval_Base" object.
			if ( ! $obj instanceof Interval_Base ) {
				continue;
			}

			// add to the list.
			$list[] = $obj;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the list of intervals for settings.
	 *
	 * Sorted by its time.
	 *
	 * @return array<string,string>
	 */
	public function get_intervals_for_settings(): array {
		// define the list for the entries.
		$list = array();

		// add entry to disable the setting as first.
		$list['eml_disable_check'] = __( 'Disable check', 'external-files-in-media-library' );

		// add each interval objects as entry.
		foreach ( $this->get_intervals_as_objects() as $obj ) {
			$list[ $this->get_prefix() . $obj->get_name() ] = $obj->get_title();
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Add our own intervals to the WordPress list of intervals.
	 *
	 * @param array<string,array<string,mixed>> $intervals List of intervals.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_intervals( array $intervals ): array {
		// loop through our own intervals and add them to the list.
		foreach ( $this->get_intervals_as_objects() as $obj ) {
			$intervals[ $this->get_prefix() . $obj->get_name() ] = array(
				'interval' => $obj->get_time(),
				'display'  => $obj->get_title(),
			);
		}

		// return the resulting list of intervals.
		return $intervals;
	}

	/**
	 * Return the prefix we use for each our own interval names.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		return 'efml_';
	}
}
