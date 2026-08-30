<?php
/**
 * This file contains the object, which logs events.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object for logging events.
 */
class Log {
	/**
	 * Own instance
	 *
	 * @var Log|null
	 */
	private static ?Log $instance = null;

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
	 * @return Log
	 */
	public static function get_instance(): Log {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Install necessary DB-table.
	 *
	 * @return void
	 */
	public function install(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// table for import-log.
		$sql = 'CREATE TABLE ' . $wpdb->prefix . "eml_logs (
		    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		    `time` datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
		    `log` longtext NOT NULL,
		    `url` text NOT NULL,
		    `state` varchar(40) NOT NULL DEFAULT '',
		    `identifier` varchar(255) NOT NULL DEFAULT '',
		    PRIMARY KEY  (id),
			KEY time (time),
			KEY state (state),
			KEY identifier (identifier)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // @phpstan-ignore requireOnce.fileNotFound
		dbDelta( $sql );
	}

	/**
	 * Delete the table on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'eml_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Create new Log-entry
	 *
	 * @param string $message The text for this entry.
	 * @param string $url     The URL this entry is assigned to.
	 * @param string $state   The state for this entry (success/error).
	 * @param int    $level   The highest log level this entry will be used for.
	 * @param string $identifier The identifier for this entry.
	 *
	 * @return void
	 */
	public function create( string $message, string $url, string $state, int $level = 0, string $identifier = '' ): void {
		global $wpdb;

		// log only if log-level for the new entry is higher or equal actual setting.
		if ( $level > $this->get_level() ) {
			return;
		}

		// add log entry.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'eml_logs',
			array(
				'time'       => gmdate( 'Y-m-d H:i:s' ),
				'log'        => $message,
				'url'        => $url,
				'state'      => $state,
				'identifier' => $identifier,
			)
		);

		// clean the log.
		$this->clean_log();
	}

	/**
	 * Return log entries.
	 *
	 * @param array<string,mixed> $args {
	 *     Optional. Arguments to filter the entries.
	 *
	 *     @type string $url        Filter by URL. Empty string for no filter.
	 *     @type bool   $url_like   Whether the URL should be matched partially. Default false.
	 *     @type string $state      Filter by state. Empty string for no filter.
	 *     @type string $identifier Filter by import identifier. Empty string for no filter.
	 *     @type string $orderby    Column to order by: 'date', 'state' or 'url'. Default 'date'.
	 *     @type string $order      'ASC' or 'DESC'. Default 'DESC'.
	 *     @type int    $limit      Max amount of entries. 0 for no limit. Default 0.
	 *     @type int    $offset     Amount of entries to skip. Default 0.
	 * }
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_entries( array $args = array() ): array {
		global $wpdb;

		// get the WHERE-part with its values.
		list( $where, $values ) = $this->get_where( $args );

		// get the ORDER BY-part from a whitelist to never interpolate user input.
		$columns = array(
			'date'  => '`time`',
			'state' => '`state`',
			'url'   => '`url`',
		);
		$orderby = $columns[ $args['orderby'] ?? 'date' ] ?? '`time`';
		$order   = 'ASC' === strtoupper( (string) ( $args['order'] ?? '' ) ) ? 'ASC' : 'DESC';

		// build the query.
		$sql = 'SELECT `id`, `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->prefix . 'eml_logs WHERE ' . implode( ' AND ', $where ) . ' ORDER BY ' . $orderby . ' ' . $order . ', `id` ' . $order;

		// add the limit, if requested.
		$limit = absint( $args['limit'] ?? 0 );
		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$values[] = $limit;
			$values[] = absint( $args['offset'] ?? 0 );
		}

		// run the query.
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// return an empty list if the query returned nothing usable.
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Return the amount of log entries matching the given arguments.
	 *
	 * The value is cached within WordPress-own system.
	 *
	 * @param array<string,mixed> $args The arguments, see get_entries().
	 *
	 * @return int
	 */
	public function get_entry_count( array $args = array() ): int {
		global $wpdb;

		// get the WHERE-part with its values.
		list( $where, $values ) = $this->get_where( $args );

		// build the query.
		$sql = 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'eml_logs WHERE ' . implode( ' AND ', $where );

		// get cache entities.
		$cache_key = 'efml_log_count_' . md5( Helper::get_json( $args ) );
		$count     = wp_cache_get( $cache_key, 'efml' );

		// cache the result after running the prepared query.
		if ( false === $count ) {
			$count = absint( $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Statement is concat above.
			wp_cache_set( $cache_key, $count, 'efml', MINUTE_IN_SECONDS );
		}

		// return the resulting count.
		return absint( $count );
	}

	/**
	 * Return the WHERE-conditions and their values for the given arguments.
	 *
	 * @param array<string,mixed> $args The arguments, see get_entries().
	 *
	 * @return array{0:array<int,string>,1:array<int,mixed>}
	 */
	private function get_where( array $args ): array {
		global $wpdb;

		// start with a neutral condition so the list is never empty.
		$where  = array( '1 = %d' );
		$values = array( 1 );

		// filter by URL.
		if ( ! empty( $args['url'] ) ) {
			if ( ! empty( $args['url_like'] ) ) {
				$where[]  = '`url` LIKE %s';
				$values[] = '%' . $wpdb->esc_like( (string) $args['url'] ) . '%';
			} else {
				$where[]  = '`url` = %s';
				$values[] = (string) $args['url'];
			}
		}

		// filter by state.
		if ( ! empty( $args['state'] ) ) {
			$where[]  = '`state` = %s';
			$values[] = (string) $args['state'];
		}

		// filter by identifier.
		if ( ! empty( $args['identifier'] ) ) {
			$where[]  = '`identifier` = %s';
			$values[] = (string) $args['identifier'];
		}

		return array( $where, $values );
	}

	/**
	 * Return logs.
	 *
	 * @deprecated 5.4.0 Use get_entries() instead.
	 *
	 * @param string $url The URL to filter for (optional).
	 * @param string $state The requested state (optional).
	 * @param string $identifier The identifier (optional).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_logs( string $url = '', string $state = '', string $identifier = '' ): array {
		return $this->get_entries(
			array(
				'url'        => $url,
				'state'      => $state,
				'identifier' => $identifier,
			)
		);
	}

	/**
	 * Cleanup log from old entries.
	 *
	 * @return void
	 * @noinspection SqlResolve
	 */
	private function clean_log(): void {
		// prevent re-entry: on a log-table error create() calls clean_log() again → infinite loop.
		static $is_running = false;
		if ( $is_running ) {
			return;
		}

		// bail on uninstalling.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		global $wpdb;

		// mark as running.
		$is_running = true;

		// get the max age in days, fall back to 50.
		$max_age_in_days = absint( get_option( 'efml_max_age_log_entries', 50 ) );
		if ( 0 === $max_age_in_days ) {
			$max_age_in_days = 50;
		}

		// create the cutoff in UTC, as the entries are saved in UTC too.
		$max_age = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-' . $max_age_in_days . ' days' ) );

		// run the deletion.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'eml_logs WHERE `time` < %s LIMIT 10000', array( $max_age ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// log if any error occurred.
		if ( ! empty( $wpdb->last_error ) ) {
			/* translators: %1$s will be replaced by a DB-error-message. */
			$this->create( sprintf( __( 'Database error: %1$s - This usually indicates that the database system of your hosting does not meet the minimum requirements of WordPress. Please contact your hosts support team for clarification.', 'external-files-in-media-library' ), '<code>' . esc_html( $wpdb->last_error ) . '</code>' ), '', 'error' );
		}

		// mark as not running anymore.
		$is_running = false;
	}

	/**
	 * Complete delete log.
	 *
	 * @return void
	 */
	public function truncate_log(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'eml_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Return the actual log level as integer.
	 *
	 * 0 = not logging anything.
	 * 1 = minimal log.
	 * 2 = log all.
	 *
	 * @return int
	 */
	private function get_level(): int {
		return absint( get_option( 'eml_log_mode', 0 ) );
	}

	/**
	 * Delete single log entry.
	 *
	 * @param int $id The ID of the entry to delete.
	 *
	 * @return void
	 */
	public function delete_log( int $id ): void {
		// bail if id is not given.
		if ( 0 === $id ) {
			return;
		}

		// delete the entry.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'eml_logs WHERE `id` = %d', array( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
