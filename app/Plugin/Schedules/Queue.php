<?php
/**
 * File to handle the queue-schedule.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Schedules;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Schedules_Base;

/**
 * Object for this schedule.
 */
class Queue extends Schedules_Base {

	/**
	 * Name of this event.
	 *
	 * @var string
	 */
	protected string $name = 'eml_queue';

	/**
	 * Name of the option used to enable this event.
	 *
	 * @var string
	 */
	protected string $interval_option_name = 'eml_queue_interval';

	/**
	 * Define the default interval.
	 *
	 * @var string
	 */
	protected string $default_interval = 'efml_hourly';

	/**
	 * Initialize this schedule.
	 */
	public function __construct() {
		// get interval from settings.
		$this->interval = get_option( $this->get_interval_option_name(), $this->get_default_interval() );
	}

	/**
	 * Run this schedule.
	 *
	 * @return void
	 */
	public function run(): void {
		// log event.
		Log::get_instance()->create( __( 'Queue schedule starting.', 'external-files-in-media-library' ), '', 'success', 2 );

		// run the queue.
		\ExternalFilesInMediaLibrary\ExternalFiles\Queue::get_instance()->process_queue();

		// log event.
		Log::get_instance()->create( __( 'Queue schedule ended.', 'external-files-in-media-library' ), '', 'success', 2 );
	}

	/**
	 * Return whether this schedule should be enabled and active according to configuration.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'eml_disable_check' !== get_option( $this->get_interval_option_name() );
	}
}
