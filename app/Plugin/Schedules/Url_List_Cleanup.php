<?php
/**
 * File for the schedule, which deletes the files of lists with URLs (CSV, XML) used via the directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Schedules;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Schedules_Base;
use ExternalFilesInMediaLibrary\Services\Csv;
use ExternalFilesInMediaLibrary\Services\Url_List_Base;
use ExternalFilesInMediaLibrary\Services\Xml;

/**
 * Object for the schedule, which deletes the files of lists with URLs used via the directory listing.
 */
class Url_List_Cleanup extends Schedules_Base {

	/**
	 * Name of this event.
	 *
	 * Must start with "eml_", as only these events are handled as events of this plugin.
	 *
	 * @var string
	 */
	protected string $name = 'eml_url_list_cleanup';

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
		$this->interval = $this->get_default_interval();
	}

	/**
	 * Return the list services.
	 *
	 * @return array<int,Url_List_Base>
	 */
	private function get_services(): array {
		return array( Csv::get_instance(), Xml::get_instance() );
	}

	/**
	 * Return whether this schedule is enabled: the cleanup must be enabled for at least one service.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// check each service.
		$enabled = false;
		foreach ( $this->get_services() as $service_obj ) {
			if ( $service_obj->is_cleanup_enabled() ) {
				$enabled = true;
			}
		}

		// bail if the cleanup is disabled for all services.
		if ( ! $enabled ) {
			return false;
		}

		// return the result of the parent check.
		return parent::is_enabled();
	}

	/**
	 * Run this schedule.
	 *
	 * @return void
	 */
	public function run(): void {
		// bail if uninstallation is running.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// log event.
		Log::get_instance()->create( __( 'Cleanup of used lists with URLs starting.', 'external-files-in-media-library' ), '', 'info', 2 );

		// run the cleanup for each service.
		foreach ( $this->get_services() as $service_obj ) {
			$service_obj->cleanup();
		}

		// log event.
		Log::get_instance()->create( __( 'Cleanup of used lists with URLs ended.', 'external-files-in-media-library' ), '', 'info', 2 );
	}
}
