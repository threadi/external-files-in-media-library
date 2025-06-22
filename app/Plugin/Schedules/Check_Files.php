<?php
/**
 * File to handle the import-schedule.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Schedules;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Schedules_Base;

/**
 * Object for this schedule.
 */
class Check_Files extends Schedules_Base {

	/**
	 * Name of this event.
	 *
	 * @var string
	 */
	protected string $name = 'eml_check_files';

	/**
	 * Name of the option used to enable this event.
	 *
	 * @var string
	 */
	protected string $interval_option_name = 'eml_check_interval';

	/**
	 * Define the default interval.
	 *
	 * @var string
	 */
	protected string $default_interval = 'efml_24hourly';

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
		Log::get_instance()->create( __( 'Check file schedule starting.', 'external-files-in-media-library' ), '', 'success', 2 );

		// run the availability check.
		Files::get_instance()->check_files();

		// log event.
		Log::get_instance()->create( __( 'Check file schedule ended.', 'external-files-in-media-library' ), '', 'success', 2 );
	}
}
