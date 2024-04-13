<?php
/**
 * This file contains the log-model.
 *
 * @package thread\eml
 */

namespace threadi\eml\Model;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use wpdb;

/**
 * Model for log.
 */
class Log {
	/**
	 * Own instance
	 *
	 * @var Log|null
	 */
	private static ?Log $instance = null;

	/**
	 * DB-connection.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// set the table-name.
		$this->wpdb->eml_table = $this->wpdb->prefix . 'eml_logs';
	}

	/**
	 * Return logs.
	 *
	 * @param string $url The URL to filter for, get only last entry (optional).
	 *
	 * @return array
	 */
	public function get_logs( string $url = '' ): array {
		global $wpdb;
		if ( ! empty( $url ) ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->eml_table . ' WHERE 1 = %s AND `url` = %s ORDER BY `time` DESC LIMIT 1', array( 1, $url ) ), ARRAY_A );
		}
		return $wpdb->get_results( $wpdb->prepare( 'SELECT `state`, `time` AS `date`, `log`, `url` FROM ' . $wpdb->eml_table . ' WHERE 1 = %s ORDER BY `time` DESC', array( 1 ) ), ARRAY_A );
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): log {
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
		$charset_collate = $this->wpdb->get_charset_collate();

		// table for import-log.
		$sql = 'CREATE TABLE ' . $this->wpdb->eml_table . " (
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
		$this->wpdb->query( sprintf( 'DROP TABLE IF EXISTS %s', $wpdb->eml_table ) );
	}

	/**
	 * Create new log-entry
	 *
	 * @param string $message The text for this entry.
	 * @param string $url The URL this entry is assigned to.
	 * @param string $state The state for this entry (success/error).
	 * @param int    $level The highest log level this entry will be used for.
	 *
	 * @return void
	 */
	public function create( string $message, string $url, string $state, int $level = 0 ): void {
		// log only if log-level for the new entry is higher or equal actual setting.
		if ( ! ( $level >= $this->get_level() ) ) {
			return;
		}

		// add log entry.
		$this->wpdb->insert(
			$this->wpdb->eml_table,
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
	 * Cleanup log from old entries.
	 *
	 * @return void
	 * @noinspection SqlResolve
	 */
	private function clean_log(): void {
		global $wpdb;
		$this->wpdb->query( sprintf( 'DELETE FROM `%s` WHERE `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', $wpdb->eml_table, 50 ) );
	}

	/**
	 * Complete delete log.
	 *
	 * @return void
	 */
	public function truncate_log(): void {
		global $wpdb;
		$this->wpdb->query( sprintf( 'TRUNCATE TABLE %s', $wpdb->eml_table ) );
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
}
