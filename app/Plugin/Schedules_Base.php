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
	 * @return bool
	 */
	public function install(): bool {
		// determine the interval to use: validated against the registered
		// cron-schedules, with a fallback to the default if it is unknown.
		$interval = $this->get_scheduled_interval();

		// if the schedule already exists, only (re)schedule it if its interval
		// changed - otherwise there is nothing to do.
		if ( wp_next_scheduled( $this->get_name(), $this->get_args() ) ) { // @phpstan-ignore argument.type
			// the recurrence currently stored in WP-cron for this event.
			$current_interval = wp_get_schedule( $this->get_name(), $this->get_args() ); // @phpstan-ignore argument.type

			// nothing to do if the interval is unchanged.
			if ( $current_interval === $interval ) {
				return true;
			}

			// the interval changed: remove the existing event so it is recreated
			// below with the new interval. wp_schedule_event() would otherwise
			// refuse to change the interval of an already-scheduled event.
			$this->delete();

			// log the re-schedule.
			/* translators: %1$s will be replaced by the name of the schedule, %2$s by the old interval, %3$s by the new interval. */
			Log::get_instance()->create( sprintf( __( 'Interval of schedule %1$s changed from %2$s to %3$s - rescheduling.', 'external-files-in-media-library' ), $this->get_name(), (string) $current_interval, $interval ), '', 'info', 1 );
		}

		// create the schedule.
		$result = wp_schedule_event( time(), $interval, $this->get_name(), $this->get_args(), true ); // @phpstan-ignore argument.type

		// log event if the schedule could not be created.
		if ( is_wp_error( $result ) ) { // @phpstan-ignore function.impossibleType
			/* translators: %1$s will be replaced by the name of the schedule. */
			Log::get_instance()->create( sprintf( __( 'Error during creation of schedule %1$s:', 'external-files-in-media-library' ), $this->get_name() ) . ' <code>' . Helper::get_json( $result->get_error_messages() ) . '</code>', '', 'error' );

			// return false as an error occurred.
			return false;
		}

		// return true as anything was ok.
		return true;
	}

	/**
	 * Delete a single schedule.
	 *
	 * @return void
	 */
	public function delete(): void {
		// delete the schedule.
		$result = wp_clear_scheduled_hook( $this->get_name(), $this->get_args(), true ); // @phpstan-ignore argument.type

		// log event if the schedule could not be deleted.
		if ( is_wp_error( $result ) ) { // @phpstan-ignore function.impossibleType
			Log::get_instance()->create( __( 'Error during deleting of schedule:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $result->get_error_message() ) . '</code>', '', 'error' );
		}
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

	/**
	 * Return the interval to schedule this event with.
	 *
	 * @return string
	 */
	protected function get_scheduled_interval(): string {
		// get the interval of this schedule.
		$interval = $this->get_interval();

		// get all schedules.
		$schedules = Intervals::get_instance()->get_intervals_for_settings();

		// use the configured interval if it is registered.
		if ( isset( $schedules[ $interval ] ) ) {
			return $interval;
		}

		// otherwise fall back to the class default, but only if that one is
		// actually registered. If neither is available we keep the configured
		// value and let wp_schedule_event() report the error (as before).
		if ( isset( $this->default_interval, $schedules[ $this->default_interval ] ) && '' !== $this->default_interval ) {
			// log the fallback so the misconfiguration is visible.
			/* translators: %1$s will be replaced by the invalid interval, %2$s by the name of the schedule, %3$s by the fallback interval. */
			Log::get_instance()->create( sprintf( __( 'The configured interval %1$s for schedule %2$s is not registered - falling back to %3$s.', 'external-files-in-media-library' ), '<code>' . $interval . '</code>', '<code>' . $this->get_name() . '</code>', '<code>' . $this->default_interval . '</code>' ), '', 'info', 1 );

			// return the default interval.
			return $this->default_interval;
		}

		// use the configured value if nothing better available.
		return $interval;
	}

	/**
	 * Delete all events of this schedule, regardless of their arguments.
	 *
	 * Multiple events may share one hook name with different arguments, e.g. one
	 * synchronization per external source. delete() only removes the event which
	 * matches the arguments of this object.
	 *
	 * @return void
	 */
	public function delete_all_events(): void {
		wp_unschedule_hook( $this->get_name() );
	}

	/**
	 * Return whether this schedule can be reconciled automatically.
	 *
	 * Schedules which use arguments may exist multiple times under the same hook
	 * name. A single object cannot represent them, so they manage themselves.
	 *
	 * @return bool
	 */
	public function is_reconcilable(): bool {
		return true;
	}
}
