<?php
/**
 * This file contains the search for files of the central media libraries, which no website uses.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Query;

/**
 * Object to find the files of a central media library, which are not used in any content of the network.
 *
 * The usage is checked in the background via WP Cron in parts and saved on the files. The media library of the
 * central media library could be filtered for the unused files. Nothing is deleted automatically.
 */
class Unused {
	/**
	 * Meta key on a file of a central media library: the number of contents in the network, which use it.
	 */
	public const META_USAGE = 'efml_network_usage';

	/**
	 * Meta key on a file of a central media library: the time of the last check of its usage.
	 */
	public const META_CHECKED = 'efml_network_usage_checked';

	/**
	 * Name of the cron event, which checks the usage.
	 */
	public const CRON = 'efml_network_check_usage';

	/**
	 * Name of the option in a central media library with the last checked file of the actual round.
	 */
	private const OPTION_AFTER = 'efml_network_usage_after';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Unused
	 */
	private static ?Unused $instance = null;

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
	 * @return Unused
	 */
	public static function get_instance(): Unused {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::CRON, array( $this, 'run_via_cron' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );

		// the filter in the media library of the central media library.
		add_filter( 'efml_filter_options', array( $this, 'add_filter_option' ) );
		add_action( 'efml_filter_query', array( $this, 'filter_query' ) );
	}

	/**
	 * Check the usage via cron.
	 *
	 * @return void
	 */
	public function run_via_cron(): void {
		$this->run();
	}

	/**
	 * Schedule the check in a central media library, if it is not scheduled.
	 *
	 * @return void
	 */
	public function schedule(): void {
		// bail if this is not a central media library or the check is scheduled.
		if ( ! Media_Library::get_instance()->is_central() || wp_next_scheduled( self::CRON ) ) {
			return;
		}

		// schedule it.
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::CRON );
	}

	/**
	 * Check the usage of the next files of the actual central media library.
	 *
	 * Starts a new round one day after the last file of a round has been checked.
	 *
	 * @param int $time_limit The time in seconds, which could be used.
	 *
	 * @return bool True if the round is complete.
	 */
	public function run( int $time_limit = 20 ): bool {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return true;
		}

		// get the start time.
		$start = time();

		// check the files after the last checked one.
		$after = absint( get_option( self::OPTION_AFTER, 0 ) );
		do {
			// stop, if the time is over.
			if ( time() - $start >= $time_limit ) {
				update_option( self::OPTION_AFTER, $after, false );
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON );
				return false;
			}

			// get the next files.
			$ids = $this->get_next_files( $after, 20 );

			// check each.
			foreach ( $ids as $attachment_id ) {
				$this->check( $attachment_id );
				$after = $attachment_id;
			}
		} while ( ! empty( $ids ) );

		// the round is complete: start the next one tomorrow.
		delete_option( self::OPTION_AFTER );
		wp_schedule_single_event( time() + DAY_IN_SECONDS, self::CRON );
		return true;
	}

	/**
	 * Return the IDs of the next files of the actual website after the given ID.
	 *
	 * @param int $after The ID.
	 * @param int $limit The maximum number.
	 *
	 * @return array<int,int>
	 */
	private function get_next_files( int $after, int $limit ): array {
		global $wpdb;
		return array_map( 'absint', $wpdb->get_col( $wpdb->prepare( 'SELECT `ID` FROM %i WHERE `post_type` = %s AND `ID` > %d ORDER BY `ID` ASC LIMIT %d', array( $wpdb->posts, 'attachment', $after, $limit ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Check the usage of the given file of the actual central media library and save it.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int The number of contents in the network, which use it.
	 */
	public function check( int $attachment_id ): int {
		// sum the usage in all websites.
		$count = 0;
		foreach ( Usage::get_instance()->get_usage( $attachment_id ) as $entry ) {
			$count += absint( $entry['posts'] );
		}

		// save it.
		update_post_meta( $attachment_id, self::META_USAGE, $count );
		update_post_meta( $attachment_id, self::META_CHECKED, time() );

		// return it.
		return $count;
	}

	/**
	 * Return the IDs of the files of the actual central media library, which are not used (after their last check).
	 *
	 * @return array<int,int>
	 */
	public function get_unused(): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => $this->get_meta_query(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		$ids   = array();
		foreach ( $query->get_posts() as $post ) {
			$ids[] = $post instanceof \WP_Post ? $post->ID : absint( $post );
		}
		return $ids;
	}

	/**
	 * Return the meta query for unused files.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_meta_query(): array {
		return array(
			array(
				'key'     => self::META_USAGE,
				'value'   => 0,
				'compare' => '=',
				'type'    => 'NUMERIC',
			),
		);
	}

	/**
	 * Add the option to filter for unused files to the media library of a central media library.
	 *
	 * @param array<string,string> $options The options.
	 *
	 * @return array<string,string>
	 */
	public function add_filter_option( array $options ): array {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return $options;
		}

		// add the option.
		$options['efml-network-unused'] = __( 'Unused in the network', 'external-files-in-media-library' );

		// return the resulting list.
		return $options;
	}

	/**
	 * Filter the query of the media library for unused files.
	 *
	 * @param WP_Query $query The query.
	 *
	 * @return void
	 */
	public function filter_query( WP_Query $query ): void {
		// get the value.
		$filter = $query->get( 'efml_media_filter' );
		if ( ! is_string( $filter ) || '' === $filter ) {
			$filter = $query->is_main_query() ? (string) filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
		}

		// bail if it is not ours.
		if ( 'efml-network-unused' !== $filter ) {
			return;
		}

		// add it to the query, together with an existing meta query.
		$meta_query = $this->get_meta_query();
		$existing   = $query->get( 'meta_query' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$existing,
				$meta_query,
			);
		}
		$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
	}
}
