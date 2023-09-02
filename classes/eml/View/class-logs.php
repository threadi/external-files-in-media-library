<?php
/**
 * This file contains a logs-view for this plugin, using WP_List_Table.
 *
 * @package thread\eml
 */

namespace threadi\eml\View;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use threadi\eml\helper;
use threadi\eml\Model\log;
use WP_List_Table;

/**
 * Initialize the viewer.
 */
class Logs extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'url'   => __( 'URL', 'external-files-in-media-library' ),
			'date'  => __( 'date', 'external-files-in-media-library' ),
			'state' => __( 'state', 'external-files-in-media-library' ),
			'log'   => __( 'log', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		$logs = Log::get_instance();
		return $logs->get_logs();
	}

	/**
	 * Get the log-table for the table-view.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $this->table_data();
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return array
	 */
	public function get_hidden_columns(): array {
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		return array( 'date' => array( 'date', false ) );
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  array  $item        Data for single column.
	 * @param  String $column_name - Current iterated column name.
	 *
	 * @return mixed
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date':
				return Helper::get_format_date_time( $item[ $column_name ] );

			case 'state':
			case 'url':
				return $item[ $column_name ];

			case 'log':
				return nl2br( $item[ $column_name ] );
		}
	}
}
