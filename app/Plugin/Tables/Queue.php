<?php
/**
 * This file contains the list of the queue of files which will be imported, using WP_List_Table.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Tables;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use WP_List_Table;

/**
 * Initialize the log viewer.
 */
class Queue extends WP_List_Table {
	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return array(
			'options' => __( 'Options', 'external-files-in-media-library' ),
			'state'   => __( 'State', 'external-files-in-media-library' ),
			'date'    => __( 'Date', 'external-files-in-media-library' ),
			'url'     => __( 'URL', 'external-files-in-media-library' ),
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

		// get logs.
		return \ExternalFilesInMediaLibrary\ExternalFiles\Queue::get_instance()->get_urls( $state, true );
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
			'options' => $this->get_options( $item ),
			'date' => Helper::get_format_date_time( $item[ $column_name ] ),
			'state' => $this->get_state( $item[ $column_name ] ),
			'url' => '<a href="' . esc_url( $item[ $column_name ] ) . '" target="_blank">' . esc_url( $item[ $column_name ] ) . '</a>',
			default => '',
		};
	}

	/**
	 * Add process-button on top of table.
	 *
	 * @param string $which The position.
	 * @return void
	 */
	public function extra_tablenav( $which ): void {
		if ( 'top' === $which ) {
			// define process-URL.
			$process_url = add_query_arg(
				array(
					'action' => 'eml_queue_process',
					'nonce'  => wp_create_nonce( 'eml-queue-process' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create empty-dialog.
			$process_dialog = array(
				'title'   => __( 'Process the queue now', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Are you sure you want to process the queue now?', 'external-files-in-media-library' ) . '</strong></p>',
					/* translators: %1$s will be replaced by a number, %2$s by a URL. */
					'<p>' . sprintf( __( 'Only max. %1$d entries will be processed according <a href="%2$s">to your setting</a>.', 'external-files-in-media-library' ), absint( get_option( 'eml_queue_limit' ) ), esc_url( Settings::get_instance()->get_url( 'eml_general' ) ) ) . '</p>',
					'<p>' . __( 'This might take some time. You have to be patient.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
					array(
						'action'  => 'location.href="' . esc_url( $process_url ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Yes, process the queue', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			?>
			<a href="<?php echo esc_url( $process_url ); ?>" class="button button-secondary easy-dialog-for-wordpress<?php echo ( 0 === count( $this->items ) ? ' disabled' : '' ); ?>" data-dialog="<?php echo esc_attr( wp_json_encode( $process_dialog ) ); ?>"><?php echo esc_html__( 'Process queue', 'external-files-in-media-library' ); ?></a>
			<?php

			// define clear-URL.
			$clear_url = add_query_arg(
				array(
					'action' => 'eml_queue_clear',
					'nonce'  => wp_create_nonce( 'eml-queue-clear' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create clear-dialog.
			$clear_dialog = array(
				'title'   => __( 'Clear the queue now', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Are you sure you want to clear the queue now?', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'Every entry in the queue will be deleted.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
					array(
						'action'  => 'location.href="' . esc_url( $clear_url ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Yes, clear the queue', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			?>
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-secondary easy-dialog-for-wordpress<?php echo ( 0 === count( $this->items ) ? ' disabled' : '' ); ?>" data-dialog="<?php echo esc_attr( wp_json_encode( $clear_dialog ) ); ?>"><?php echo esc_html__( 'Clear queue', 'external-files-in-media-library' ); ?></a>
			<?php

			// get all error entries.
			$errors = \ExternalFilesInMediaLibrary\ExternalFiles\Queue::get_instance()->get_urls( 'error' );

			// define clear error URL.
			$clear_errors_url = add_query_arg(
				array(
					'action' => 'eml_queue_clear_errors',
					'nonce'  => wp_create_nonce( 'eml-queue-clear-errors' ),
				),
				get_admin_url() . 'admin.php'
			);

			// create clear error dialog.
			$clear_errors_dialog = array(
				'title'   => __( 'Delete error entries in the queue now', 'external-files-in-media-library' ),
				'texts'   => array(
					'<p><strong>' . __( 'Are you sure you want to delete error entries in the queue now?', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'Every entry with state "error" in the queue will be deleted.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons' => array(
					array(
						'action'  => 'location.href="' . esc_url( $clear_errors_url ) . '";',
						'variant' => 'primary',
						'text'    => __( 'Yes, delete them', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			?>
			<a href="<?php echo esc_url( $clear_errors_url ); ?>" class="button button-secondary easy-dialog-for-wordpress<?php echo ( 0 === count( $errors ) ? ' disabled' : '' ); ?>" data-dialog="<?php echo esc_attr( wp_json_encode( $clear_errors_dialog ) ); ?>"><?php echo esc_html__( 'Delete error entries', 'external-files-in-media-library' ); ?></a>
			<?php
		}
	}

	/**
	 * Message to be displayed when there are no items.
	 */
	public function no_items(): void {
		echo esc_html__( 'No entries in queue found.', 'external-files-in-media-library' );
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
			default => '<span class="dashicons dashicons-admin-generic" title="' . esc_attr__( 'Will be processed', 'external-files-in-media-library' ) . '"></span>',
		};
	}

	/**
	 * Define filter for log table.
	 *
	 * @return array
	 */
	protected function get_views(): array {
		// get main URL without filter.
		$url = remove_query_arg( array( 'errors' ) );

		// get called error-parameter.
		$errors = absint( filter_input( INPUT_GET, 'errors', FILTER_SANITIZE_NUMBER_INT ) );

		// define initial list.
		$list = array(
			'all' => '<a href="' . esc_url( $url ) . '"' . ( 0 === $errors ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'external-files-in-media-library' ) . '</a>',
		);

		// add filter for errors.
		$url            = add_query_arg( array( 'errors' => 1 ) );
		$list['errors'] = '<a href="' . esc_url( $url ) . '"' . ( 1 === $errors ? ' class="current"' : '' ) . '>' . esc_html__( 'Errors', 'external-files-in-media-library' ) . '</a>';

		/**
		 * Filter the list before output.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array $list List of filter.
		 */
		return apply_filters( 'eml_queue_table_filter', $list );
	}

	/**
	 * Return list of options for a single entry.
	 *
	 * @param array $item The item data.
	 *
	 * @return string
	 */
	private function get_options( array $item ): string {
		// collect the output.
		$output = '';

		// create delete-URL.
		$url = add_query_arg(
			array(
				'action' => 'eml_queue_delete_entry',
				'id'     => $item['id'],
				'nonce'  => wp_create_nonce( 'eml-queue-delete-entry' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'title'   => __( 'Delete queue entry', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Are you sure you want to delete this queue entry?', 'external-files-in-media-library' ) . '</strong><br>' . __( 'The URL will not be imported. You can add the URL any time again.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		// add output for delete link.
		$output .= '<a class="dashicons dashicons-trash easy-dialog-for-wordpress" data-dialog="' . esc_attr( wp_json_encode( $dialog ) ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Delete entry', 'external-files-in-media-library' ) . '"></a>';

		// create delete-URL.
		$url = add_query_arg(
			array(
				'action' => 'eml_queue_process_entry',
				'id'     => $item['id'],
				'nonce'  => wp_create_nonce( 'eml-queue-process-entry' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'title'   => __( 'Process queue entry', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Are you sure you want to process this queue entry now?', 'external-files-in-media-library' ) . '</strong><br>' . __( 'The URL will be imported as external file. You may be want to import this URL through the automatic import of the queue.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		// add output for process now link.
		$output .= '<a class="dashicons dashicons-controls-play easy-dialog-for-wordpress" data-dialog="' . esc_attr( wp_json_encode( $dialog ) ) . '" href="' . esc_url( $url ) . '" title="' . esc_attr__( 'Process entry', 'external-files-in-media-library' ) . '"></a>';

		// return the list of options.
		return $output;
	}
}
