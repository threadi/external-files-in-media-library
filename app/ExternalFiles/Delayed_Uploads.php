<?php
/**
 * File to export uploads with a delay, e.g. so that image optimizers could work with their local files before.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;

/**
 * Object to export uploads with a delay.
 *
 * Uploads are exported right away. Other plugins (e.g. image optimizers or renaming plugins, see ThirdParty) could
 * request a delay via the hook "efml_export_upload_delay", as they work with the local files after the upload, often
 * in the background. The export then runs via WP-Cron after the delay. Via the hook "efml_export_upload_ready" they
 * could postpone it further, until they are finished (one hour at most).
 */
class Delayed_Uploads {
	/**
	 * Name of the post meta, which marks an upload waiting for its delayed export (time of the upload).
	 *
	 * @var string
	 */
	public const META = 'efml_export_delayed';

	/**
	 * Name of the WP-Cron hook, which exports the upload.
	 *
	 * @var string
	 */
	public const CRON_HOOK = 'efml_export_delayed_upload';

	/**
	 * Name of the post meta, which marks an upload, which another plugin works on (time of the start).
	 *
	 * @var string
	 */
	public const BUSY_META = 'efml_export_busy';

	/**
	 * Marker whether a delayed export is running at the moment.
	 *
	 * @var bool
	 */
	private bool $running = false;

	/**
	 * The uploads, whose optimization has ended in this request (attachment ID => true).
	 *
	 * @var array<int,bool>
	 */
	private array $done = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Delayed_Uploads
	 */
	private static ?Delayed_Uploads $instance = null;

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
	 * @return Delayed_Uploads
	 */
	public static function get_instance(): Delayed_Uploads {
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
		add_action( self::CRON_HOOK, array( $this, 'export' ) );
	}

	/**
	 * Return the delay in seconds for the export of the given upload (0 for none).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function get_delay( int $attachment_id ): int {
		// no delay while the delayed export runs or if the optimizer has finished its work in this request already.
		if ( $this->running || isset( $this->done[ $attachment_id ] ) ) {
			return 0;
		}

		/**
		 * Filter the delay in seconds for the export of an upload (0 to export it right away).
		 *
		 * Used by integrations of image optimizers and renaming plugins, which work with the local files after the
		 * upload.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $delay         The delay in seconds.
		 * @param int $attachment_id The attachment ID.
		 */
		return absint( apply_filters( 'efml_export_upload_delay', 0, $attachment_id ) );
	}

	/**
	 * Plan the export of the given upload after the given delay.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $delay         The delay in seconds.
	 *
	 * @return void
	 */
	public function schedule( int $attachment_id, int $delay ): void {
		// remember the time of the upload.
		if ( 0 === absint( get_post_meta( $attachment_id, self::META, true ) ) ) {
			update_post_meta( $attachment_id, self::META, time() );
		}

		// plan the export.
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $attachment_id ) );
		wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $attachment_id ) );

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( __( 'The export of the uploaded file waits %1$d minutes, as other plugins work with its local files (e.g. to optimize them).', 'external-files-in-media-library' ), max( 1, (int) round( $delay / MINUTE_IN_SECONDS ) ) ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );
	}

	/**
	 * Return whether the given upload waits for its delayed export.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 * @phpstan-impure
	 */
	public function is_waiting( int $attachment_id ): bool {
		return absint( get_post_meta( $attachment_id, self::META, true ) ) > 0;
	}

	/**
	 * Mark the given upload as busy: another plugin works with its local files (e.g. an image optimizer has started).
	 *
	 * Its delayed export waits until the plugin reports the end of its work via done(), one hour after the upload at
	 * most.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function set_busy( int $attachment_id ): void {
		update_post_meta( $attachment_id, self::BUSY_META, time() );
	}

	/**
	 * Return whether another plugin works with the local files of the given upload (for one hour at most).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_busy( int $attachment_id ): bool {
		$since = absint( get_post_meta( $attachment_id, self::BUSY_META, true ) );
		return $since > 0 && time() - $since < HOUR_IN_SECONDS;
	}

	/**
	 * Mark the end of the work of another plugin with the local files of the given upload (e.g. an image optimizer
	 * has finished): a waiting export runs now.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function done( int $attachment_id ): void {
		// end the work.
		delete_post_meta( $attachment_id, self::BUSY_META );

		// remember it for this request: an upload, whose optimization has ended already, is exported without delay.
		$this->done[ $attachment_id ] = true;

		// bail if the upload does not wait for its export.
		if ( ! $this->is_waiting( $attachment_id ) ) {
			return;
		}

		// remove the planned event.
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $attachment_id ) );

		// export it now in this request (e.g. the background processing of the optimizer): an event planned now
		// could get lost, if the optimizer saves the events of WP-Cron in parallel requests.
		$this->export( $attachment_id );
	}

	/**
	 * End the waiting of the given upload without its export, e.g. as it is exported by request of the user.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function end_waiting( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::META );
		delete_post_meta( $attachment_id, self::BUSY_META );
		wp_clear_scheduled_hook( self::CRON_HOOK, array( $attachment_id ) );
	}

	/**
	 * Return the time, when the delayed export of the given upload is due (0 if it does not wait).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int
	 */
	public function get_due_time( int $attachment_id ): int {
		// get the time of the upload.
		$uploaded = absint( get_post_meta( $attachment_id, self::META, true ) );

		// bail if it does not wait.
		if ( 0 === $uploaded ) {
			return 0;
		}

		// return the time after the delay.
		return $uploaded + max( MINUTE_IN_SECONDS, $this->get_delay( $attachment_id ) );
	}

	/**
	 * Export the given upload after its delay (via WP-Cron).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function export( int $attachment_id ): void {
		// get the time of the upload.
		$uploaded = absint( get_post_meta( $attachment_id, self::META, true ) );

		// bail if the upload does not wait anymore (e.g. it has been deleted or exported by hand).
		if ( 0 === $uploaded || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		/**
		 * Filter whether other plugins have finished their work with the local files of an upload.
		 *
		 * The export is postponed by 5 minutes, if not, but one hour after the upload at most.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $ready         True if the upload could be exported.
		 * @param int  $attachment_id The attachment ID.
		 */
		$ready = (bool) apply_filters( 'efml_export_upload_ready', ! $this->is_busy( $attachment_id ), $attachment_id );
		if ( ! $ready && time() - $uploaded < HOUR_IN_SECONDS ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_HOOK, array( $attachment_id ) );
			return;
		}

		// end the waiting.
		delete_post_meta( $attachment_id, self::META );
		delete_post_meta( $attachment_id, self::BUSY_META );

		// bail if the export is not enabled anymore (the file stays local).
		if ( ! Export::get_instance()->is_enabled() ) {
			return;
		}

		// run the export like after an upload, without delay.
		$this->running = true;
		$metadata      = Export::get_instance()->export_file_by_upload( wp_get_attachment_metadata( $attachment_id, true ), $attachment_id );
		$this->running = false;

		// save the resulting metadata, as WordPress does it after an upload.
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * Remove the data during the uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		wp_unschedule_hook( self::CRON_HOOK );
		delete_post_meta_by_key( self::META );
		delete_post_meta_by_key( self::BUSY_META );
	}
}
