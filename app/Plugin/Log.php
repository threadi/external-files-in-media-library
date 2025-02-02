<?php
/**
 * This file contains the object which logs events.
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
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Log {
		if ( ! static::$instance instanceof static ) {
			static::$instance = new static();
		}
		return static::$instance;
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
            `id` mediumint(9) NOT NULL AUTO_INCREMENT,
            `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            `log` text DEFAULT '' NOT NULL,
            `url` text DEFAULT '' NOT NULL,
            `state` varchar(40) DEFAULT '' NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Delete table on uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'eml_logs' ) );
	}

	/**
	 * Create new Log-entry
	 *
	 * @param string $message The text for this entry.
	 * @param string $url The URL this entry is assigned to.
	 * @param string $state The state for this entry (success/error).
	 * @param int    $level The highest log level this entry will be used for.
	 *
	 * @return void
	 */
	public function create( string $message, string $url, string $state, int $level = 0 ): void {
		global $wpdb;

		// log only if log-level for the new entry is higher or equal actual setting.
		if ( $level > $this->get_level() ) {
			return;
		}

		// add log entry.
		$wpdb->insert(
			$wpdb->prefix . 'eml_logs',
			array(
				'time'  => gmdate( 'Y-m-d H:i:s' ),
				'log'   => $message,
				'url'   => $url,
				'state' => $state,
			)
		);
		$this->clean_log();
	}

	/**
	 * Return logs.
	 *
	 * @param string $url The URL to filter for, get only last entry (optional).
	 * @param string $state The requested state (optional).
	 *
	 * @return array
	 */
	public function get_logs( string $url = '', string $state = '' ): array {
		global $wpdb;
		if ( ! empty( $url ) ) {
			if ( ! empty( $state ) ) {
				return $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->prefix . 'eml_logs WHERE 1 = %s AND `url` = %s AND `state` = %s ORDER BY `time` DESC LIMIT 1', array( 1, $url, $state ) ), ARRAY_A );
			}
			return $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->prefix . 'eml_logs WHERE 1 = %s AND `url` = %s ORDER BY `time` DESC LIMIT 1', array( 1, $url ) ), ARRAY_A );
		} elseif ( ! empty( $state ) ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->prefix . 'eml_logs WHERE 1 = %s AND `state` = %s ORDER BY `time` DESC', array( 1, $state ) ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->prefix . 'eml_logs WHERE 1 = %s ORDER BY `time` DESC', array( 1 ) ), ARRAY_A );
	}

	/**
	 * Cleanup log from old entries.
	 *
	 * @return void
	 * @noinspection SqlResolve
	 */
	private function clean_log(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'DELETE FROM `%s` WHERE `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', $wpdb->prefix . 'eml_logs', 50 ) );
	}

	/**
	 * Complete delete log.
	 *
	 * @return void
	 */
	public function truncate_log(): void {
		global $wpdb;
		$wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->prefix . 'eml_logs' ) );
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
		return get_option( 'eml_log_mode', 0 );
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
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . 'eml_logs WHERE `id` = %d', array( $id ) ) );
	}
}
