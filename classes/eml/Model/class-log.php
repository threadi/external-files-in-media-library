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
	 * DB-Table-name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {
		global $wpdb;

		// get the db-connection.
		$this->wpdb = $wpdb;

		// set the table-name.
		$this->table_name = $this->wpdb->prefix . 'eml_logs';
	}

	/**
	 * Return logs.
	 *
	 * @return array
	 * @noinspection SqlResolve
	 */
	public function get_logs(): array {
		global $wpdb;
		$table = $this->table_name;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT `state`, `time` AS `date`, `log`, `url` FROM ' . $table . ' WHERE 1 = %s ORDER BY `time` DESC', array( 1 ) ), ARRAY_A ); // phpcs:ignore
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
		$sql = 'CREATE TABLE ' . $this->table_name . " (
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
		$sql = "DROP TABLE IF EXISTS {$this->table_name}";
		$this->wpdb->query( $sql ); // phpcs:ignore
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
			$this->table_name,
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
		$sql = sprintf( 'DELETE FROM `%s` WHERE `time` < DATE_SUB(NOW(), INTERVAL %d DAY)', $this->table_name, 50 );
		$this->wpdb->query( $sql ); // phpcs:ignore
	}

	/**
	 * Complete delete log.
	 *
	 * @return void
	 */
	public function truncate_log(): void {
		$sql = 'TRUNCATE TABLE `' . $this->table_name . '`';
		$this->wpdb->query( $sql ); // phpcs:ignore
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
