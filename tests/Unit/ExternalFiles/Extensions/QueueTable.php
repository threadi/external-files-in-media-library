<?php
/**
 * Tests for the structure of the queue table.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Tests\Unit\ExternalFiles\Extensions;

use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Tests\externalFilesTests;

/**
 * Object to test the structure of the queue table.
 */
class QueueTable extends externalFilesTests {

	/**
	 * Return the names of the keys of the queue table.
	 *
	 * @return array<int,string>
	 */
	private function get_keys(): array {
		global $wpdb;
		return array_values( array_unique( wp_list_pluck( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $wpdb->prefix . 'eml_queue' ), ARRAY_A ), 'Key_name' ) ) );
	}

	/**
	 * Test that the table of older versions is updated with its entries.
	 *
	 * @return void
	 */
	public function test_update_of_old_table(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'eml_queue';

		// create the table of older versions with entries.
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $table );
		$wpdb->query(
			'CREATE TABLE ' . $table . " (
			`id` mediumint(9) NOT NULL AUTO_INCREMENT,
			`time` datetime DEFAULT '1970-01-01 00:00:00' NOT NULL,
			`url` text NOT NULL,
			`fields` text NOT NULL,
			`state` text NOT NULL,
			`options` text NOT NULL,
			`type` varchar(20) DEFAULT 'import' NOT NULL,
			`attachment_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
			`attempts` smallint(5) unsigned DEFAULT 0 NOT NULL,
			`url_hash` char(32) DEFAULT '' NOT NULL,
			`run_id` bigint(20) unsigned DEFAULT 0 NOT NULL,
			UNIQUE KEY id (id),
			KEY url_hash (url_hash),
			KEY run_id (run_id)
		) " . $wpdb->get_charset_collate()
		);
		$wpdb->insert( $table, array( 'url' => 'https://example.com/a.jpg', 'fields' => '', 'state' => 'new', 'options' => '', 'type' => 'import' ) );
		$wpdb->insert( $table, array( 'url' => 'https://example.com/b.jpg', 'fields' => '', 'state' => 'error', 'options' => '', 'type' => 'export', 'attachment_id' => 5 ) );
		$this->assertContains( 'id', $this->get_keys() );
		delete_option( Queue::DB_VERSION_OPTION );

		// update it.
		Queue::get_instance()->maybe_install();

		// it has the new keys and still its entries.
		$keys = $this->get_keys();
		$this->assertContains( 'PRIMARY', $keys );
		$this->assertNotContains( 'id', $keys );
		$this->assertContains( 'state_type', $keys );
		$this->assertContains( 'attachment_type', $keys );
		$this->assertEquals( 2, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ) );
		$this->assertEquals( 'error', $wpdb->get_var( $wpdb->prepare( 'SELECT `state` FROM %i WHERE `attachment_id` = %d', $table, 5 ) ) );
		$this->assertEquals( md5( 'https://example.com/a.jpg' ), $wpdb->get_var( $wpdb->prepare( 'SELECT `url_hash` FROM %i WHERE `url` = %s', $table, 'https://example.com/a.jpg' ) ) );

		// new entries get IDs above the existing ones.
		$wpdb->insert( $table, array( 'url' => 'https://example.com/c.jpg', 'fields' => '', 'state' => 'new', 'options' => '', 'type' => 'import' ) );
		$this->assertEquals( 3, (int) $wpdb->insert_id );

		// a second call changes nothing.
		Queue::get_instance()->install();
		$this->assertEquals( 3, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ) );
	}
}
