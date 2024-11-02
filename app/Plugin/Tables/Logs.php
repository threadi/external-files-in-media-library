<?php
/**
 * This file contains a logs-view for this plugin, using WP_List_Table.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Tables;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_List_Table;

/**
 * Initialize the log viewer.
 */
class Logs extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'state' => __( 'State', 'external-files-in-media-library' ),
			'date'  => __( 'Date', 'external-files-in-media-library' ),
			'url'   => __( 'URL', 'external-files-in-media-library' ),
			'log'   => __( 'Log', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data(): array {
		// get state filter.
		$state = 1 === absint( filter_input( INPUT_GET, 'errors', FILTER_SANITIZE_NUMBER_INT ) ) ? 'error' : '';

		// get URL-filter.
		$url = filter_input( INPUT_GET, 'url', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $url ) ) {
			$url = '';
		}

		// get logs.
		return Log::get_instance()->get_logs( $url, $state );
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

		$data = $this->table_data();

		$per_page     = 50;
		$current_page = $this->get_pagenum();
		$total_items  = count( $data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
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
	 * @return string
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function column_default( $item, $column_name ) {
		return match ( $column_name ) {
			'date' => Helper::get_format_date_time( $item[ $column_name ] ),
			'state' => $this->get_state( $item[ $column_name ] ),
			'url' => '<a href="' . esc_url( $item[ $column_name ] ) . '" target="_blank">' . url_shorten( $item[ $column_name ], 50 ) . '</a>',
			'log' => nl2br( $item[ $column_name ] ),
			default => '',
		};
	}

	/**
	 * Add delete-button on top of table.
	 *
	 * @param string $which The position.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			// define empty-URL.
			$empty_url = add_query_arg(
				array(
					'action' => 'eml_empty_log',
					'nonce'  => wp_create_nonce( 'eml-empty-log' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create empty-dialog.
			$empty_dialog = array(
				'title'   => __( 'Empty log entries', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Are you sure you want to empty the log?', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'You will loose any log until now.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
					array(
						'action'  => 'location.href="' . esc_url( $empty_url ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Yes, empty the log', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			?>
			<a href="<?php echo esc_url( $empty_url ); ?>" class="button button-secondary easy-dialog-for-wordpress<?php echo ( 0 === count( $this->items ) ? ' disabled' : '' ); ?>" data-dialog="<?php echo esc_attr( wp_json_encode( $empty_dialog ) ); ?>"><?php echo esc_html__( 'Empty the log', 'external-files-in-media-library' ); ?></a>
			<?php
		}
	}

	/**
	 * Message to be displayed when there are no items.
	 */
	public function no_items(): void {
		echo esc_html__( 'No log entries found.', 'external-files-in-media-library' );
	}

	/**
	 * Get icon for the state.
	 *
	 * @param string $state The state.
	 *
	 * @return string
	 */
	private function get_state( string $state ): string {
		return match ( $state ) {
			'error' => '<span class="dashicons dashicons-no" title="' . esc_attr__( 'Error', 'external-files-in-media-library' ) . '"></span>',
			'success' => '<span class="dashicons dashicons-yes" title="' . esc_attr__( 'Success', 'external-files-in-media-library' ) . '"></span>',
			default => '<span class="dashicons dashicons-info-outline" title="' . esc_attr__( 'Info', 'external-files-in-media-library' ) . '"></span>',
		};
	}

	/**
	 * Define filter for log table.
	 *
	 * @return array
	 */
	protected function get_views(): array {
		// get main URL without filter.
		$url = remove_query_arg( array( 'errors', 'url' ) );

		// get called error-parameter.
		$errors = absint( filter_input( INPUT_GET, 'errors', FILTER_SANITIZE_NUMBER_INT ) );

		// get requested URL to show filter for.
		$requested_url = filter_input( INPUT_GET, 'url', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// define initial list.
		$list = array(
			'all' => '<a href="' . esc_url( $url ) . '"' . ( 0 === $errors && is_null( $requested_url ) ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'external-files-in-media-library' ) . '</a>',
		);

		// add filter for errors.
		$url            = add_query_arg( array( 'errors' => 1 ) );
		$list['errors'] = '<a href="' . esc_url( $url ) . '"' . ( 1 === $errors ? ' class="current"' : '' ) . '>' . esc_html__( 'Errors', 'external-files-in-media-library' ) . '</a>';

		// show filter for requested URL.
		if ( ! is_null( $requested_url ) ) {
			$list['url'] = '<span class="current">' . esc_html( url_shorten( $requested_url ) ) . '</span>';
		}

		/**
		 * Filter the list before output.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of filter.
		 */
		return apply_filters( 'eml_log_table_filter', $list );
	}
}
