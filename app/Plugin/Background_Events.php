<?php
/**
 * File to handle the single events of this plugin in WP-Cron (e.g. delayed exports).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Client_Uploads;
use ExternalFilesInMediaLibrary\ExternalFiles\Delayed_Uploads;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use ExternalFilesInMediaLibrary\ExternalFiles\Local_Urls;
use ExternalFilesInMediaLibrary\ExternalFiles\Migrations;
use ExternalFilesInMediaLibrary\ExternalFiles\Sizes;
use ExternalFilesInMediaLibrary\ExternalFiles\Url_Index;

/**
 * Object to handle the single events of this plugin in WP-Cron.
 *
 * Unlike the schedules (see Schedules), these events are planned for single tasks, e.g. the delayed export of an
 * upload. They are removed, if their file is deleted or the plugin is deactivated. Their tasks are saved in the
 * database (e.g. as post meta), so they are planned again after the plugin has been activated again.
 */
class Background_Events {
	/**
	 * Name of the transient, which limits the check for missing events.
	 *
	 * @var string
	 */
	private const CHECK_TRANSIENT = 'efml_background_events_check';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Background_Events
	 */
	private static ?Background_Events $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Background_Events
	 */
	public static function get_instance(): Background_Events {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'delete_attachment', array( $this, 'remove_events_of_attachment' ) );
		add_action( 'wp_loaded', array( $this, 'check' ) );
	}

	/**
	 * Plan missing events for saved tasks of attachments again (every 5 minutes at most, in WP-Cron and backend).
	 *
	 * Events could get lost, as WordPress saves all of them in one option: if another request (e.g. the background
	 * processing of an image optimizer) saves this option with its older state at the same time, the new event is
	 * missing afterward.
	 *
	 * @return void
	 */
	public function check(): void {
		// only during WP-Cron and in the backend.
		if ( ! wp_doing_cron() && ! is_admin() ) {
			return;
		}

		// bail if it has been checked in the last 5 minutes.
		if ( false !== get_transient( self::CHECK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::CHECK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS );

		// export waiting uploads, whose event is missing and which are due, during WP-Cron directly: a planned event
		// could get lost again, as long as another plugin saves the events in parallel requests.
		if ( wp_doing_cron() ) {
			$this->export_due_uploads();
		}

		// plan the missing events: the exports and deletions of the queue at the time of their next attempt.
		Queue::get_instance()->schedule_waiting_exports();
		$this->resume_attachments( Delayed_Uploads::META, Delayed_Uploads::CRON_HOOK, MINUTE_IN_SECONDS );
		$this->resume_attachments( Client_Uploads::META, Client_Uploads::CRON_HOOK, MINUTE_IN_SECONDS );
		$this->resume_attachments( Sizes::RETRY_META, Sizes::RETRY_HOOK, 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Return the hooks of the single events, which are planned per attachment (with its ID as argument).
	 *
	 * @return array<int,string>
	 */
	private function get_attachment_hooks(): array {
		return array(
			Delayed_Uploads::CRON_HOOK,
			Client_Uploads::CRON_HOOK,
			Sizes::RETRY_HOOK,
		);
	}

	/**
	 * Return the hooks of all single events of this plugin.
	 *
	 * @return array<int,string>
	 */
	public function get_hooks(): array {
		return array_merge(
			$this->get_attachment_hooks(),
			array(
				Import_Runs::CRON_HOOK,
				Local_Urls::CRON_HOOK,
				Url_Index::CRON_HOOK,
				Migrations::CRON_HOOK,
				Queue::EXPORT_HOOK,
			)
		);
	}

	/**
	 * Remove the planned events of the given attachment, as it is deleted.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function remove_events_of_attachment( int $attachment_id ): void {
		foreach ( $this->get_attachment_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook, array( $attachment_id ) );
		}
	}

	/**
	 * Remove all single events of this plugin (e.g. during its deactivation).
	 *
	 * Their tasks stay saved and are planned again via resume() after the activation.
	 *
	 * @return void
	 */
	public function remove_all(): void {
		foreach ( $this->get_hooks() as $hook ) {
			wp_unschedule_hook( $hook );
		}
		delete_transient( self::CHECK_TRANSIENT );
	}

	/**
	 * Plan the single events again for their saved tasks (e.g. after the activation of this plugin).
	 *
	 * @return void
	 */
	public function resume(): void {
		// plan the events per attachment.
		$this->resume_attachments( Delayed_Uploads::META, Delayed_Uploads::CRON_HOOK, MINUTE_IN_SECONDS );
		$this->resume_attachments( Client_Uploads::META, Client_Uploads::CRON_HOOK, MINUTE_IN_SECONDS );
		$this->resume_attachments( Sizes::RETRY_META, Sizes::RETRY_HOOK, 10 * MINUTE_IN_SECONDS );

		// continue the exports and deletions of the queue, the runs in the background, the backfill of local URLs and of
		// the index of URLs and a migration.
		Queue::get_instance()->schedule_waiting_exports();
		Import_Runs::get_instance()->resume();
		Local_Urls::get_instance()->schedule_backfill();
		Url_Index::get_instance()->schedule_backfill();
		Migrations::get_instance()->resume();
	}

	/**
	 * Export waiting uploads, whose delay has passed and whose event is missing, directly (5 at most per request).
	 *
	 * @return void
	 */
	private function export_due_uploads(): void {
		global $wpdb;

		// get the waiting uploads.
		$attachment_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `post_id` FROM %i WHERE `meta_key` = %s', $wpdb->postmeta, Delayed_Uploads::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// export the due ones without planned event.
		$delayed_obj = Delayed_Uploads::get_instance();
		$exported    = 0;
		foreach ( $attachment_ids as $attachment_id ) {
			// get the ID.
			$attachment_id = absint( $attachment_id );

			// bail if its event exists or it is not due.
			if ( wp_next_scheduled( Delayed_Uploads::CRON_HOOK, array( $attachment_id ) ) || $delayed_obj->get_due_time( $attachment_id ) > time() ) {
				continue;
			}

			// log this event.
			Log::get_instance()->create( __( 'The planned export of the uploaded file is missing in WP-Cron. It is exported now.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );

			// export it.
			$delayed_obj->export( $attachment_id );

			// bail after 5 exports.
			if ( ++$exported >= 5 ) {
				return;
			}
		}
	}

	/**
	 * Plan the given event for each attachment with the given post meta.
	 *
	 * @param string $meta_key The post meta, which marks the task.
	 * @param string $hook     The hook of the event.
	 * @param int    $delay    The delay in seconds.
	 *
	 * @return void
	 */
	private function resume_attachments( string $meta_key, string $hook, int $delay ): void {
		global $wpdb;

		// get the attachments with this task.
		$attachment_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT `post_id` FROM %i WHERE `meta_key` = %s', $wpdb->postmeta, $meta_key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery

		// plan the event for each of them.
		foreach ( $attachment_ids as $attachment_id ) {
			$args = array( absint( $attachment_id ) );
			if ( ! wp_next_scheduled( $hook, $args ) ) {
				wp_schedule_single_event( time() + $delay, $hook, $args );
				/* translators: %1$s will be replaced by the name of a hook. */
				Log::get_instance()->create( sprintf( __( 'The missing event %1$s for this file has been planned again.', 'external-files-in-media-library' ), '<code>' . esc_html( $hook ) . '</code>' ), (string) wp_get_attachment_url( absint( $attachment_id ) ), 'info', 2 );
			}
		}
	}
}
