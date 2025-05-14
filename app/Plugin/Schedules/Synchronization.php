<?php
/**
 * File to handle synchronization schedules.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Schedules;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Schedules_Base;
use ExternalFilesInMediaLibrary\Services\Services;

/**
 * Object for this schedule.
 */
class Synchronization extends Schedules_Base {

	/**
	 * Name of this event.
	 *
	 * @var string
	 */
	protected string $name = 'eml_sync';

	/**
	 * Name of the option used to enable this event.
	 *
	 * @var string
	 */
	protected string $interval_option_name = 'eml_sync_interval';

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
		$this->interval = get_option( $this->get_interval_option_name() );
	}

	/**
	 * Run this schedule.
	 *
	 * @return void
	 */
	public function run(): void {
		// log event.
		Log::get_instance()->create( __( 'Synchronization schedule starting.', 'external-files-in-media-library' ), '', 'success', 2 );

		// get the arguments.
		$args = $this->get_args();

		// get the method object by its name.
		$directory_listing_obj = Services::get_instance()->get_service_by_name( $args['method'] );

		// bail if listing object could not be found.
		if ( ! $directory_listing_obj ) {
			Log::get_instance()->create( __( 'Synchronization listing object unknown:', 'external-files-in-media-library' ) . ' <code>' . $args['method'] . '</code>', '', 'error' );
			return;
		}

		// get the term data.
		$term_data = Taxonomy::get_instance()->get_entry( $args['term_id'] );

		// bail if term_data could not be loaded.
		if ( empty( $term_data ) ) {
			Log::get_instance()->create( __( 'To synchronize external directory does not have any configuration.', 'external-files-in-media-library' ) . ' <code>' . $args['method'] . '</code>', '', 'error' );
			return;
		}

		// get the URL.
		$url = $directory_listing_obj->get_url( $term_data['directory'] );

		// run the synchronization.
		\ExternalFilesInMediaLibrary\ExternalFiles\Synchronization::get_instance()->sync( $url, $directory_listing_obj, $term_data, $args['term_id'] );

		// log event.
		Log::get_instance()->create( __( 'Synchronization schedule ended.', 'external-files-in-media-library' ), '', 'success', 2 );
	}

	/**
	 * Return whether this schedule should be enabled and active according to configuration.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'eml_disable_check' !== get_option( $this->get_interval_option_name() ) && ! empty( $this->get_args() );
	}
}
