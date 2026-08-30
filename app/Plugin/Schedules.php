<?php
/**
 * File to handle every schedule in this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * The object, which handles schedules.
 */
class Schedules {
	/**
	 * Instance of this object.
	 *
	 * @var ?Schedules
	 */
	private static ?Schedules $instance = null;

	/**
	 * Constructor for Schedules-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Schedules {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize all schedules of this plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		// use hooks.
		add_action( 'init', array( $this, 'init_schedules' ) );
		add_filter( 'schedule_event', array( $this, 'add_schedule_to_list' ) );
		add_action( 'shutdown', array( $this, 'check_events_on_shutdown' ) );
	}

	/**
	 * Initialize the schedules via init-hook.
	 *
	 * @return void
	 */
	public function init_schedules(): void {
		// loop through our own events.
		foreach ( $this->get_events() as $event ) {
			// get the schedule object.
			$schedule_obj = $this->get_schedule_object_by_name( $event['name'] );

			// bail if object could not be loaded.
			if ( ! $schedule_obj instanceof Schedules_Base ) {
				continue;
			}

			// define action hook to run the schedule.
			add_action( $schedule_obj->get_name(), array( $this, 'run_schedule' ), 10, 10 );
		}
	}

	/**
	 * Run the schedule which matches the fired cron event.
	 *
	 * The arguments are taken from the event itself, as multiple events may share
	 * one hook name with different arguments.
	 *
	 * @param mixed ...$args The arguments of the fired cron event.
	 *
	 * @return void
	 */
	public function run_schedule( ...$args ): void {
		// get the schedule object for the fired event.
		$schedule_obj = $this->get_schedule_object_by_name( (string) current_action() );

		// bail if no schedule object could be found.
		if ( ! $schedule_obj instanceof Schedules_Base ) {
			return;
		}

		// set the arguments of the event which has been fired.
		$schedule_obj->set_args( $args ); // @phpstan-ignore argument.type

		// run the schedule.
		$schedule_obj->run();
	}

	/**
	 * Return our own active events from WP-list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_events(): array {
		// get our own events from events list in WordPress.
		$our_events = $this->get_wp_events();

		// show deprecated warning for the old hook name.
		$our_events = apply_filters_deprecated( 'eml_schedule_our_events', array( $our_events ), '5.0.0', 'efml_schedule_our_events' );

		/**
		 * Filter the list of our own events,
		 * e.g., to check if all, which are enabled in setting are active.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string,array<string,mixed>> $our_events List of our own events in WP-cron.
		 */
		return apply_filters( 'efml_schedule_our_events', $our_events );
	}

	/**
	 * Check the available events with the ones, which should be active.
	 *
	 * Re-installs missing events. Log this event.
	 *
	 * Does only run in wp-admin, not frontend.
	 *
	 * @param array<string,array<string,mixed>> $our_events List of our own events.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function check_events( array $our_events ): array {
		// show deprecated warning for the old hook name.
		$false = apply_filters_deprecated( 'eml_disable_cron_check', array( false ), '5.0.0', 'efml_disable_cron_check' );

		/**
		 * Disable the additional cron check.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param bool $false True if check should be disabled.
		 */
		if ( apply_filters( 'efml_disable_cron_check', $false ) ) {
			return $our_events;
		}

		// bail if plugin activation is running.
		if ( defined( 'EFML_ACTIVATION_RUNNING' ) ) {
			return $our_events;
		}

		// bail if plugin deactivation is running.
		if ( defined( 'EFML_DEACTIVATION_RUNNING' ) ) {
			return $our_events;
		}

		// bail if plugin deinstallation is running.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return $our_events;
		}

		// return resulting list.
		return $this->reconcile_events( $our_events );
	}

	/**
	 * Reconcile our schedule events against the WP-cron array: install missing
	 * enabled events, remove present disabled events and heal interval drift on
	 * existing enabled events.
	 *
	 * Split out from check_events() so the reconcile can run - and be tested -
	 * independently of the activation guard in check_events().
	 *
	 * @param array<string,mixed> $our_events The currently scheduled events.
	 *
	 * @return array<string,mixed>
	 */
	public function reconcile_events( array $our_events ): array {
		// check the schedule objects if they are set.
		foreach ( $this->get_schedules_as_objects() as $schedule_obj ) {
			// bail if this schedule manages its own events.
			if ( ! $schedule_obj->is_reconcilable() ) {
				continue;
			}

			// install if schedule is enabled and not in list of our schedules.
			if ( $schedule_obj->is_enabled() && ! isset( $our_events[ $schedule_obj->get_name() ] ) ) {
				// reinstall the missing event.
				$schedule_obj->install();

				// log this event.
				/* translators: %1$s will be replaced by the event name. */
				Log::get_instance()->create( sprintf( __( 'Missing cron event <i>%1$s</i> automatically re-installed.', 'external-files-in-media-library' ), esc_html( $schedule_obj->get_name() ) ), '', 'info', 2 );

				// re-run the check for WP-cron-events.
				$our_events = $this->get_wp_events();
			} elseif ( $schedule_obj->is_enabled() && isset( $our_events[ $schedule_obj->get_name() ] ) ) {
				// the event exists and is enabled: make sure its interval still
				// matches the configuration. install() reschedules only if the
				// interval drifted (e.g. after the user picked another interval);
				// otherwise it is a no-op. This is what makes a changed interval
				// take effect without any extra wiring.
				$schedule_obj->install();
			}

			// add args to object, if set.
			if ( isset( $our_events[ $schedule_obj->get_name() ] ) ) {
				$schedule_obj->set_args( $our_events[ $schedule_obj->get_name() ]['settings'][ array_key_first( $our_events[ $schedule_obj->get_name() ]['settings'] ) ]['args'] );
			}

			// delete if schedule is in list of our events and not enabled.
			if ( ! $schedule_obj->is_enabled() && isset( $our_events[ $schedule_obj->get_name() ] ) ) {
				$schedule_obj->delete();

				// log this event.
				/* translators: %1$s will be replaced by the event name. */
				Log::get_instance()->create( sprintf( __( 'Not enabled cron event <i>%1$s</i> automatically removed.', 'external-files-in-media-library' ), esc_html( $schedule_obj->get_name() ) ), '', 'info', 2 );

				// re-run the check for WP-cron-events.
				$our_events = $this->get_wp_events();
			}
		}

		// return the resulting list.
		return $our_events;
	}

	/**
	 * Delete all our registered schedules.
	 *
	 * @return void
	 */
	public function delete_all(): void {
		foreach ( $this->get_schedules_as_objects() as $schedule_obj ) {
			// delete the schedule independent of their arguments.
			$schedule_obj->delete_all_events();
		}
	}

	/**
	 * Create our schedules per request.
	 *
	 * @return void
	 */
	public function create_schedules(): void {
		// install the schedules if they do not exist atm.
		foreach ( $this->get_schedules_as_objects() as $schedule_obj ) {
			// bail if this schedule is not enabled.
			if ( ! $schedule_obj->is_enabled() ) {
				continue;
			}

			// create the schedule.
			$schedule_obj->install();
		}
	}

	/**
	 * Return list of all schedule-object-names.
	 *
	 * @return array<string>
	 */
	public function get_schedule_object_names(): array {
		// list of schedules: free version supports only one import-schedule.
		$list_of_schedules = array(
			'\ExternalFilesInMediaLibrary\Plugin\Schedules\Check_Files',
			'\ExternalFilesInMediaLibrary\Plugin\Schedules\Queue',
		);

		// show deprecated warning for the old hook name.
		$list_of_schedules = apply_filters_deprecated( 'eml_schedules', array( $list_of_schedules ), '5.0.0', 'efml_schedules' );

		/**
		 * Add custom schedule-objects to use.
		 *
		 * This must be objects based on ExternalFilesInMediaLibrary\Plugin\Schedules_Base.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 *
		 * @param array<string> $list_of_schedules List of additional schedules.
		 */
		return apply_filters( 'efml_schedules', $list_of_schedules );
	}

	/**
	 * Return the list of schedule objects.
	 *
	 * @return array<int,Schedules_Base>
	 */
	private function get_schedules_as_objects(): array {
		// prepare the list of objects.
		$list_of_objects = array();

		// install the schedules if they do not exist atm.
		foreach ( $this->get_schedule_object_names() as $obj_name ) {
			// bail if the class does not exist.
			if ( ! class_exists( $obj_name ) ) {
				continue;
			}

			// get the object.
			$schedule_obj = new $obj_name();

			// bail if this is not a "Schedules_Base" object.
			if ( ! $schedule_obj instanceof Schedules_Base ) {
				continue;
			}

			// add it to the list.
			$list_of_objects[] = $schedule_obj;
		}

		// return the resulting list.
		return $list_of_objects;
	}

	/**
	 * Return schedule object by its name.
	 *
	 * @param string $name The name of the object.
	 *
	 * @return false|Schedules_Base
	 */
	private function get_schedule_object_by_name( string $name ): false|Schedules_Base {
		foreach ( $this->get_schedules_as_objects() as $schedule_obj ) {
			// bail if it does not match.
			if ( $name !== $schedule_obj->get_name() ) {
				continue;
			}

			// return the object.
			return $schedule_obj;
		}
		return false;
	}

	/**
	 * Return our own events from WP-cron-event-list.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_wp_events(): array {
		$our_events = array();
		foreach ( _get_cron_array() as $events ) {
			foreach ( $events as $event_name => $event_settings ) {
				if ( str_starts_with( $event_name, 'eml_' ) ) {
					$our_events[ $event_name ] = array(
						'name'     => $event_name,
						'settings' => $event_settings,
					);
				}
			}
		}

		// return resulting list.
		return $our_events;
	}

	/**
	 * Run check for cronjob in the backend.
	 *
	 * @return void
	 */
	public function check_events_on_shutdown(): void {
		// bail if we are not in wp-admin.
		if ( ! is_admin() ) {
			return;
		}

		$this->check_events( $this->get_wp_events() );
	}

	/**
	 * Add schedule to our list of schedules.
	 *
	 * @param object|bool $event The event properties.
	 *
	 * @return object|bool
	 */
	public function add_schedule_to_list( object|bool $event ): object|bool {
		// bail if event is not an object.
		if ( ! is_object( $event ) ) {
			return $event;
		}

		// bail if hook entity does not exist.
		if ( ! isset( $event->hook ) ) {
			return $event;
		}

		// bail if this is not an event of our plugin.
		if ( ! str_starts_with( (string) $event->hook, 'eml_' ) ) {
			return $event;
		}

		// get our object.
		$schedule_obj = $this->get_schedule_object_by_name( $event->hook );

		// bail if this is not an event of our plugin.
		if ( ! $schedule_obj ) {
			return $event;
		}

		// add the args to the event.
		$schedule_obj->set_args( isset( $event->args ) && is_array( $event->args ) ? $event->args : array() ); // @phpstan-ignore property.notFound

		// get the actual list.
		$list = get_option( 'eml_schedules' );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[ $schedule_obj->get_name() ] = $schedule_obj->get_args();
		update_option( 'eml_schedules', $list );

		// return the event object.
		return $event;
	}
}
