<?php
/**
 * File as base for each schedule.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Define the base object for schedules.
 */
class Schedules_Base {
	/**
	 * Name of this event.
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * Name of the option used to enable this event.
	 *
	 * @var string
	 */
	protected string $option_name = '';

	/**
	 * Name of the option used to define the interval for this event.
	 *
	 * @var string
	 */
	protected string $interval_option_name = '';

	/**
	 * Interval of this event.
	 *
	 * @var string
	 */
	protected string $interval;

	/**
	 * Default interval of this event.
	 *
	 * @var string
	 */
	protected string $default_interval;

	/**
	 * Arguments for the schedule-event.
	 *
	 * @var array<string,mixed>
	 */
	private array $args = array();

	/**
	 * Return the name of this schedule.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Return the interval of this schedule.
	 *
	 * @return string
	 */
	public function get_interval(): string {
		$instance = $this;

		// show deprecated warning for the old hook name.
		$interval = apply_filters_deprecated( 'eml_current_language', array( $this->interval, $instance ), '5.0.0', 'efml_schedule_interval' );

		/**
		 * Filter the interval for a single schedule.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param string $interval The interval.
		 * @param Schedules_Base $instance The schedule-object.
		 */
		return apply_filters( 'efml_schedule_interval', $interval, $instance );
	}

	/**
	 * Set the interval for this schedule.
	 *
	 * @param string $interval The interval to set (e.g. "efml_24hourly").
	 *
	 * @return void
	 */
	public function set_interval( string $interval ): void {
		$this->interval = $interval;
	}

	/**
	 * Run a single schedule.
	 *
	 * @return void
	 */
	public function run(): void {}

	/**
	 * Install this schedule, if it does not exist atm.
	 *
	 * @return void
	 */
	public function install(): void {
		if ( ! wp_next_scheduled( $this->get_name(), $this->get_args() ) ) { // @phpstan-ignore argument.type
			wp_schedule_event( time(), $this->get_interval(), $this->get_name(), $this->get_args(), true ); // @phpstan-ignore argument.type
		}
	}

	/**
	 * Delete a single schedule.
	 *
	 * @return void
	 */
	public function delete(): void {
		wp_clear_scheduled_hook( $this->get_name(), $this->get_args() ); // @phpstan-ignore argument.type
	}

	/**
	 * Return the event attributes.
	 *
	 * @return false|object
	 */
	public function get_event(): false|object {
		return wp_get_scheduled_event( $this->get_name(), $this->get_args() ); // @phpstan-ignore argument.type
	}

	/**
	 * Reset this schedule.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->delete();
		$this->install();
	}

	/**
	 * Return the arguments for the schedule-event.
	 *
	 * @return array<string,mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Set the arguments for the schedule-event.
	 *
	 * @param array<string,mixed> $args The args to set for the hook-event of this schedule.
	 *
	 * @return void
	 */
	public function set_args( array $args ): void {
		$this->args = $args;
	}

	/**
	 * Return the option name which enabled this schedule.
	 *
	 * @return string
	 */
	protected function get_option_name(): string {
		return $this->option_name;
	}

	/**
	 * Return whether the schedule has an option name configured.
	 *
	 * @return bool
	 */
	private function has_option_name(): bool {
		return ! empty( $this->get_option_name() );
	}

	/**
	 * Return whether this schedule should be enabled and active according to configuration.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		$instance = $this;

		// show deprecated warning for the old hook name.
		$false = apply_filters_deprecated( 'eml_schedule_enabling', array( false, $instance ), '5.0.0', 'efml_schedule_enabling' );

		/**
		 * Filter whether to activate this schedule.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param bool $false True if this object should NOT be enabled.
		 * @param Schedules_Base $instance Actual object.
		 */
		if ( apply_filters( 'efml_schedule_enabling', $false, $instance ) ) {
			return false;
		}

		// bail with true if no setting is configured.
		if ( ! $this->has_option_name() ) {
			return true;
		}

		// return the state of this schedule according to configuration.
		return 1 === absint( get_option( $this->get_option_name() ) );
	}

	/**
	 * Return the interval option name.
	 *
	 * @return string
	 */
	public function get_interval_option_name(): string {
		return $this->interval_option_name;
	}

	/**
	 * Return the interval option name.
	 *
	 * @return string
	 */
	public function get_default_interval(): string {
		return $this->default_interval;
	}
}
