<?php
/**
 * File to find external files by their URL via the index of the database.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Query;

/**
 * Object to find external files by their URL via the index of the database.
 *
 * WordPress indexes the post meta by its key, not by its value. A search for an external file by its URL in the post
 * meta "eml_external_file" has to compare the value of each external file. Therefore, each external file gets a
 * post meta, whose key contains the hash of its URL (e.g. "_efml_url_3f2a…" with the value 1): it is found via the
 * index directly.
 *
 * The key is set, changed and removed together with the URL via the hooks for post meta, so it fits for every change
 * via the functions of WordPress. Files saved before 6.0.0 get their key in the background, until then a search
 * without match uses the former search.
 */
class Url_Index {
	/**
	 * The prefix of the post meta keys (with underscore, so they are protected and hidden).
	 */
	public const PREFIX = '_efml_url_';

	/**
	 * Name of the option with the state of the indexing of files saved before (cursor, done).
	 */
	public const BACKFILL_OPTION = 'efml_url_index_backfill';

	/**
	 * Name of the event to index files saved before in the background.
	 */
	public const CRON_HOOK = 'efml_url_index_backfill';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Url_Index
	 */
	private static ?Url_Index $instance = null;

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
	 * @return Url_Index
	 */
	public static function get_instance(): Url_Index {
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
		// keep the key together with the URL.
		add_action( 'added_post_meta', array( $this, 'url_saved' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'url_saved' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'url_deleted' ), 10, 3 );

		// index the files saved before in the background.
		add_action( self::CRON_HOOK, array( $this, 'run_backfill' ) );
		add_action( 'init', array( $this, 'schedule_backfill' ), 30 );
	}

	/**
	 * Return the meta key for the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public function get_key( string $url ): string {
		return self::PREFIX . md5( $url );
	}

	/**
	 * Save the key of the URL, as the URL of an external file has been saved.
	 *
	 * @param mixed $meta_id    The ID of the meta entry.
	 * @param mixed $post_id    The post ID.
	 * @param mixed $meta_key   The meta key.
	 * @param mixed $meta_value The value.
	 *
	 * @return void
	 */
	public function url_saved( mixed $meta_id, mixed $post_id, mixed $meta_key, mixed $meta_value ): void {
		// bail if this is not the URL of an external file.
		if ( EFML_POST_META_URL !== $meta_key || ! is_numeric( $post_id ) ) {
			return;
		}

		// save the key.
		$this->set( absint( $post_id ), is_string( $meta_value ) ? $meta_value : '' );
	}

	/**
	 * Remove the key of the URL, as the URL of an external file has been removed.
	 *
	 * @param mixed $meta_ids The IDs of the meta entries.
	 * @param mixed $post_id  The post ID.
	 * @param mixed $meta_key The meta key.
	 *
	 * @return void
	 */
	public function url_deleted( mixed $meta_ids, mixed $post_id, mixed $meta_key ): void {
		// bail if this is not the URL of an external file.
		if ( EFML_POST_META_URL !== $meta_key || ! is_numeric( $post_id ) ) {
			return;
		}

		// remove the key.
		$this->remove( absint( $post_id ) );
	}

	/**
	 * Save the key for the given URL at the given attachment, a former key is replaced.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $url           The URL.
	 *
	 * @return void
	 */
	public function set( int $attachment_id, string $url ): void {
		// bail if no attachment is given.
		if ( $attachment_id <= 0 ) {
			return;
		}

		// get the key.
		$key = '' !== $url ? $this->get_key( $url ) : '';

		// remove former keys.
		foreach ( $this->get_keys_of_attachment( $attachment_id ) as $former_key ) {
			if ( $former_key !== $key ) {
				delete_post_meta( $attachment_id, $former_key );
			}
		}

		// save the key.
		if ( '' !== $key ) {
			update_post_meta( $attachment_id, $key, 1 );
		}
	}

	/**
	 * Remove the keys of the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function remove( int $attachment_id ): void {
		foreach ( $this->get_keys_of_attachment( $attachment_id ) as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
	}

	/**
	 * Return the keys of the given attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,string>
	 */
	private function get_keys_of_attachment( int $attachment_id ): array {
		// get the keys of all post meta of this attachment (from the cache of WordPress).
		$meta = get_post_meta( $attachment_id );

		// return the keys with our prefix.
		return array_values( array_filter( array_map( 'strval', array_keys( is_array( $meta ) ? $meta : array() ) ), static fn( string $key ) => str_starts_with( $key, self::PREFIX ) ) );
	}

	/**
	 * Return the ID of the attachment with the given URL, or 0, if none has been found.
	 *
	 * Uses the key of the URL. Files without key (saved before, until they are indexed) are searched by their URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	public function find( string $url ): int {
		// bail if no URL is given.
		if ( '' === $url ) {
			return 0;
		}

		// search via the key of the URL.
		$attachment_id = $this->query(
			array(
				'key'     => $this->get_key( $url ),
				'compare' => 'EXISTS',
			)
		);

		// return it, if it has this URL (it always should, the hash is only the key).
		if ( $attachment_id > 0 && get_post_meta( $attachment_id, EFML_POST_META_URL, true ) === $url ) {
			return $attachment_id;
		}

		// bail if all files have been indexed.
		if ( $this->is_complete() ) {
			return 0;
		}

		// search by the URL itself.
		return $this->query(
			array(
				'key'     => EFML_POST_META_URL,
				'value'   => $url,
				'compare' => '=',
			)
		);
	}

	/**
	 * Return the ID of the first attachment matching the given condition for post meta, or 0.
	 *
	 * @param array<string,string> $condition The condition.
	 *
	 * @return int
	 */
	private function query( array $condition ): int {
		$query  = array(
			'post_type'              => 'attachment',
			'post_status'            => array( 'inherit', 'trash' ),
			'meta_query'             => array( $condition ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Search via the index of the meta key.
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$result = new WP_Query( $query );

		// return the ID of the match.
		return 0 === $result->post_count ? 0 : absint( $result->get_posts()[0] );
	}

	/**
	 * Return whether all files saved before have their key.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		$state = Helper::get_autoloaded_option( self::BACKFILL_OPTION, array() ); // read in every request, so it is autoloaded.
		return is_array( $state ) && ! empty( $state['done'] );
	}

	/**
	 * Schedule the indexing of files saved before, if it is not done yet.
	 *
	 * This runs also on websites, which are already on 6.0.0 (e.g. development websites), as the update routine
	 * runs only on version changes.
	 *
	 * @return void
	 */
	public function schedule_backfill(): void {
		// bail if it is done.
		if ( $this->is_complete() ) {
			return;
		}

		// bail if it is already scheduled.
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// schedule it.
		wp_schedule_single_event( time(), self::CRON_HOOK );
	}

	/**
	 * Save the key for the next external files without it.
	 *
	 * @return void
	 */
	public function run_backfill(): void {
		global $wpdb;

		// get the state.
		$state = get_option( self::BACKFILL_OPTION );
		if ( ! is_array( $state ) ) {
			$state = array();
		}

		// bail if it is done.
		if ( ! empty( $state['done'] ) ) {
			return;
		}

		/**
		 * Filter the number of external files, whose URL is indexed in one step in the background.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $size The number of files.
		 */
		$size = max( 1, absint( apply_filters( 'efml_url_index_backfill_size', 500 ) ) );

		// get the next external files.
		$cursor = isset( $state['cursor'] ) ? absint( $state['cursor'] ) : 0;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id`, `meta_value` FROM %i WHERE `meta_key` = %s AND `post_id` > %d ORDER BY `post_id` ASC LIMIT %d', $wpdb->postmeta, EFML_POST_META_URL, $cursor, $size ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

		// save the key of each file.
		foreach ( (array) $rows as $row ) {
			$state['cursor'] = absint( $row['post_id'] );
			$this->set( absint( $row['post_id'] ), (string) $row['meta_value'] );
		}

		// mark it as done, if this has been the last part.
		if ( count( (array) $rows ) < $size ) {
			$state['done'] = true;
		}

		// save the state (autoloaded, as it is checked in every request).
		update_option( self::BACKFILL_OPTION, $state, true );

		// continue with the next part.
		if ( empty( $state['done'] ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Remove the keys and the state during the uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// remove the event and the state.
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::BACKFILL_OPTION );

		// remove the keys of all files.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `meta_key` LIKE %s', $wpdb->postmeta, $wpdb->esc_like( self::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
