<?php
/**
 * This file contains an object, which extend the media tables in the backend.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Post;
use WP_Query;
use WP_Term_Query;
use WP_User;

/**
 * Object, which extends the attachment tables in the backend.
 */
class Tables {

	/**
	 * Instance of actual object.
	 *
	 * @var ?Tables
	 */
	private static ?Tables $instance = null;

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
	 * @return Tables
	 */
	public static function get_instance(): Tables {
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
		add_action( 'restrict_manage_posts', array( $this, 'add_media_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'add_media_do_filter' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'add_media_filter_to_grid_query' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_media_filter_to_grid' ) );
		add_filter( 'media_row_actions', array( $this, 'add_export_row_actions' ), 20, 2 );
		add_action( 'pre_get_terms', array( $this, 'hide_services' ) );
		add_action( 'pre_get_terms', array( $this, 'use_user_mark' ) );
		add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'add_media_column_content' ), 10, 2 );
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_columns' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_user' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_date' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'show_unknown_type' ), 10, 2 );
	}

	/**
	 * Add a filter in media library for external files.
	 *
	 * @return void
	 */
	public function add_media_filter(): void {
		// bail if "get_current_screen()" is not available.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// only for upload-screen.
		$scr = get_current_screen();
		if ( is_null( $scr ) || 'upload' !== $scr->base ) {
			return;
		}

		// get value from request.
		$request_value = filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( is_null( $request_value ) ) {
			$request_value = '';
		}

		// get the possible options.
		$options = $this->get_filter_options();

		?>
		<!--suppress HtmlFormInputWithoutLabel -->
		<select name="admin_filter_media_external_files">
			<?php
			foreach ( $options as $value => $label ) {
				?>
				<option value="<?php echo esc_attr( (string) $value ); ?>"<?php echo $request_value === (string) $value ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * Return the options of the filter for external files in the media library.
	 *
	 * @return array<int|string,string>
	 */
	public function get_filter_options(): array {
		// define possible options.
		$options = array(
			'none'         => __( 'All files', 'external-files-in-media-library' ),
			'external'     => __( 'only external URLs', 'external-files-in-media-library' ),
			'non-external' => __( 'no external URLs', 'external-files-in-media-library' ),
		);

		// add the options for the state of the export, if it is enabled or files with these states exist.
		if ( Export::get_instance()->is_enabled() || $this->has_files_with_export_state() ) {
			$options['export-pending'] = __( 'Will be exported', 'external-files-in-media-library' );
			$options['export-failed']  = __( 'Export failed', 'external-files-in-media-library' );
		}

		/**
		 * Filter the possible options.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<string,string> $options The list of possible options.
		 */
		return apply_filters( 'efml_filter_options', $options );
	}

	/**
	 * Return whether local files wait for their export or their export has failed.
	 *
	 * @return bool
	 */
	private function has_files_with_export_state(): bool {
		global $wpdb;

		// return whether a file with one of the markers exists.
		return null !== $wpdb->get_var( $wpdb->prepare( 'SELECT `post_id` FROM %i WHERE `meta_key` IN (%s, %s, %s, %s) LIMIT 1', $wpdb->postmeta, 'efml_export_queued', 'efml_export_failed', Delayed_Uploads::META, Client_Uploads::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery
	}

	/**
	 * Return the requested value of the filter for external files in the media library for the given query.
	 *
	 * In the list view it is part of the request, in the grid view it is set as query variable of the query (see
	 * add_media_filter_to_grid_query()).
	 *
	 * @param WP_Query $query The query object.
	 *
	 * @return string|null Null if the filter is not requested for this query.
	 */
	public function get_requested_filter( WP_Query $query ): ?string {
		// use the value of the grid view.
		$filter = $query->get( 'efml_media_filter' );
		if ( is_string( $filter ) && '' !== $filter ) {
			return $filter;
		}

		// bail if this is not the main query (e.g. queries of other plugins on the same page).
		if ( ! $query->is_main_query() ) {
			return null;
		}

		// return the value of the list view.
		$filter = filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		return is_string( $filter ) ? $filter : null;
	}

	/**
	 * Return the meta query for the given value of the filter for external files (empty, if it is not ours).
	 *
	 * @param string $filter The value of the filter.
	 *
	 * @return array<int|string,mixed>
	 */
	private function get_meta_query_for_filter( string $filter ): array {
		// filter to any external file.
		if ( 'external' === $filter ) {
			return array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			);
		}

		// filter for any non-external file.
		if ( 'non-external' === $filter ) {
			return array(
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		// filter for local files, which wait for their export (in the queue, for other plugins or their sizes).
		if ( 'export-pending' === $filter ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'efml_export_failed',
					'compare' => 'NOT EXISTS',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => 'efml_export_queued',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Delayed_Uploads::META,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => Client_Uploads::META,
						'compare' => 'EXISTS',
					),
				),
			);
		}

		// filter for local files, whose export has failed.
		if ( 'export-failed' === $filter ) {
			return array(
				'relation' => 'AND',
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'efml_export_failed',
					'compare' => 'EXISTS',
				),
			);
		}

		// return an empty list for any other value (e.g. the ID of an external source).
		return array();
	}

	/**
	 * Change a query to filter external files in media library if requested.
	 *
	 * Used for the main query of the list view and for the queries of the grid view.
	 *
	 * @param WP_Query $query The Query-object.
	 * @return void
	 */
	public function add_media_do_filter( WP_Query $query ): void {
		// bail if this is not the backend.
		if ( ! is_admin() ) {
			return;
		}

		// get filter value.
		$filter = $this->get_requested_filter( $query );

		// bail if filter is not set.
		if ( is_null( $filter ) ) {
			return;
		}

		// bail if this is not the attachment post type.
		if ( 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		// get the meta query for this filter.
		$meta_query = $this->get_meta_query_for_filter( $filter );

		// add it to the query, together with an existing meta query.
		if ( ! empty( $meta_query ) ) {
			$existing = $query->get( 'meta_query' );
			if ( is_array( $existing ) && ! empty( $existing ) ) {
				$meta_query = array(
					'relation' => 'AND',
					$existing,
					$meta_query,
				);
			}
			$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
		}

		/**
		 * Filter the query.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param WP_Query $query The "WP_Query" object.
		 */
		do_action_ref_array( 'efml_filter_query', array( &$query ) );
	}

	/**
	 * Add the requested value of the filter for external files to the query of the grid view of the media library.
	 *
	 * WordPress removes unknown values from the request of the grid view, so the value is read from it here and set
	 * as query variable, which add_media_do_filter() uses.
	 *
	 * @param array<string,mixed> $query The query arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function add_media_filter_to_grid_query( array $query ): array {
		// get the request.
		$request = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress checks the request, the value is sanitized below.

		// bail if our filter is not requested.
		if ( empty( $request['admin_filter_media_external_files'] ) || ! is_scalar( $request['admin_filter_media_external_files'] ) ) {
			return $query;
		}

		// get the value.
		$filter = sanitize_text_field( (string) $request['admin_filter_media_external_files'] );

		// bail if it is not one of the options.
		if ( 'none' === $filter || ! array_key_exists( $filter, $this->get_filter_options() ) ) {
			return $query;
		}

		// add it to the query.
		$query['efml_media_filter'] = $filter;

		// return the resulting query.
		return $query;
	}

	/**
	 * Add the filter for external files to the grid view of the media library.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_media_filter_to_grid( string $hook ): void {
		// bail if this is not the media library in grid view.
		if ( 'upload.php' !== $hook || ! wp_script_is( 'media-grid' ) ) {
			return;
		}

		// collect the options in their order.
		$options = array();
		foreach ( $this->get_filter_options() as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => $label,
			);
		}

		// add the script.
		wp_enqueue_script(
			'efml-media-filter',
			Helper::get_plugin_url() . 'admin/media-filter.js',
			array( 'media-views' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/media-filter.js' ),
			true
		);

		// show the filter beside the other filters of WordPress.
		wp_add_inline_style( 'media-views', '.media-frame.mode-grid select#media-attachment-efml-filter { width: auto; max-width: 15em; }' );

		// add the options.
		wp_localize_script(
			'efml-media-filter',
			'efmlMediaFilter',
			array(
				'label'   => __( 'Filter by external files', 'external-files-in-media-library' ),
				'options' => $options,
			)
		);
	}

	/**
	 * Add the actions to export a local file again and to show the errors of its export in the media table.
	 *
	 * Only for local files, which wait for their export or whose export has failed.
	 *
	 * @param array<string,string> $actions List of actions.
	 * @param WP_Post              $post    The post object of the attachment.
	 *
	 * @return array<string,string>
	 */
	public function add_export_row_actions( array $actions, WP_Post $post ): array {
		// bail if the user could not use the export.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $actions;
		}

		// get the export object.
		$export_obj = Export::get_instance();

		// get the state of the export.
		$state = $export_obj->get_export_state( $post->ID );

		// bail if the file does not wait for its export and its export has not failed.
		if ( '' === $state ) {
			return $actions;
		}

		// add the action to export it again, if it could be exported at the moment.
		if ( '' === $export_obj->get_export_again_problem( $post->ID ) ) {
			$actions['efml-export-again'] = '<a href="' . esc_url( $export_obj->get_export_again_url( $post->ID ) ) . '">' . esc_html__( 'Export again', 'external-files-in-media-library' ) . '</a>';
		}

		// add the link to the errors in the log, if the user could see the log.
		if ( current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() ) ) {
			$actions['efml-export-errors'] = '<a href="' . esc_url( $export_obj->get_errors_url( $post->ID ) ) . '">' . esc_html__( 'Show errors', 'external-files-in-media-library' ) . '</a>';
		}

		// return the resulting list.
		return $actions;
	}

	/**
	 * Hide entries in table for services the actual user does not have access to.
	 *
	 * @param WP_Term_Query $query The query object for the term table.
	 *
	 * @return void
	 */
	public function hide_services( WP_Term_Query $query ): void {
		// bail if this is not admin.
		if ( ! is_admin() ) {
			return;
		}

		// bail if this is not our taxonomy.
		if ( Taxonomy::get_instance()->get_name() !== $query->query_vars['taxonomy'] ) {
			return;
		}

		// collect the names of the services the actual user could use.
		$included_services = array();
		foreach ( Directory_Listings::get_instance()->get_directory_listings_objects() as $directory_listings_object ) {
			// bail if user has no capability.
			if ( ! current_user_can( 'efml_cap_' . $directory_listings_object->get_name() ) ) {
				continue;
			}

			// add to the list.
			$included_services[] = $directory_listings_object->get_name();
		}

		// if list is empty, prevent usage of any query.
		if ( empty( $included_services ) ) {
			$query->meta_query->queries = array(
				array(
					'key'   => 'type',
					'value' => 1,
				),
			);
			return;
		}

		// extend the query.
		$query->meta_query->queries['relation'] = 'AND';
		$query->meta_query->queries[]           = array(
			'key'     => 'type',
			'value'   => $included_services,
			'compare' => 'IN',
		);
	}

	/**
	 * Add a column to mark external files in the media table.
	 *
	 * @param array<string,string> $columns List of columns in the media table.
	 *
	 * @return array<string,string>
	 */
	public function add_media_columns( array $columns ): array {
		// bail if user has not the capability.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return $columns;
		}

		// add the column.
		$columns['external_files'] = __( 'External file', 'external-files-in-media-library' );

		// return the resulting list of columns.
		return $columns;
	}

	/**
	 * Add content for our custom column in the media table.
	 *
	 * @param string $column_name The requested column.
	 * @param int    $attachment_id The requested attachment ID.
	 *
	 * @return void
	 */
	public function add_media_column_content( string $column_name, int $attachment_id ): void {
		// show marker if this is an external file.
		if ( 'external_files' === $column_name ) {
			// get the external object for this file.
			$external_file_obj = Files::get_instance()->get_file( $attachment_id );

			// show no-icon if it is not an external file.
			if ( ! $external_file_obj->is_valid() ) {
				echo '<span class="dashicons dashicons-no" title="' . esc_html__( 'local file', 'external-files-in-media-library' ) . '"></span>';

				// show marker if this file is planned for export via queue.
				if ( absint( get_post_meta( $attachment_id, 'efml_export_queued', true ) ) > 0 ) {
					echo '<span class="dashicons dashicons-clock" title="' . esc_attr__( 'The export of this file to the external source is planned in the queue.', 'external-files-in-media-library' ) . '"></span> <span class="efml-export-pending">' . esc_html__( 'Will be exported', 'external-files-in-media-library' ) . '</span>';
				} elseif ( Delayed_Uploads::get_instance()->is_waiting( $attachment_id ) ) {
					// show marker if the export of this upload waits for other plugins (e.g. image optimizers).
					echo '<span class="dashicons dashicons-clock" title="' . esc_attr__( 'The export of this file waits a few minutes until other plugins have finished their work with it (e.g. image optimizers).', 'external-files-in-media-library' ) . '"></span> <span class="efml-export-pending">' . esc_html__( 'Will be exported', 'external-files-in-media-library' ) . '</span>';
				} elseif ( Client_Uploads::get_instance()->is_waiting( $attachment_id ) ) {
					// show marker if the export of this upload waits for the sizes created in the browser.
					echo '<span class="dashicons dashicons-clock" title="' . esc_attr__( 'The export of this file waits until its sizes have been created in the browser.', 'external-files-in-media-library' ) . '"></span> <span class="efml-export-pending">' . esc_html__( 'Will be exported', 'external-files-in-media-library' ) . '</span>';
				}

				// show marker if the export of this file via queue failed.
				if ( absint( get_post_meta( $attachment_id, 'efml_export_failed', true ) ) > 0 ) {
					echo '<span class="dashicons dashicons-warning" title="' . esc_attr__( 'The export of this file to the external source failed. Check the log and the queue for details.', 'external-files-in-media-library' ) . '"></span>';
				}
				return;
			}

			// show yes-icon as it is an external file.
			echo '<span class="dashicons dashicons-yes" title="' . esc_html__( 'external file', 'external-files-in-media-library' ) . '"></span>';

			// show a marker, if it has a local working copy.
			Working_Copies::get_instance()->show_marker( $attachment_id );

			// show deprecated hint for the old hook.
			do_action_deprecated( 'eml_table_column_content', array( $attachment_id ), '5.0.0' );

			// get protocol handler.
			$protocol_handler = $external_file_obj->get_protocol_handler_obj();

			// bail if handler could not be found.
			if ( ! $protocol_handler instanceof Protocol_Base ) {
				return;
			}

			// get the unproxied URL.
			$url = $external_file_obj->get_url( true );

			// get URL to show depending on used protocol.
			$url_to_show = $protocol_handler->get_link();

			// get link or string for the URL.
			$url_html = '<code>' . esc_html( $url ) . '</code>';
			if ( ! empty( esc_url( $url ) ) ) {
				$url_html = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( $url ) . '" target="_blank">' . esc_html( $url_to_show ) . '</a>';
			}

			// get URL.
			$edit_url = get_edit_post_link( $external_file_obj->get_id() );
			if ( ! is_string( $edit_url ) ) {
				$edit_url = '#';
			}

			// get the service name used for this file.
			$service_name = $external_file_obj->get_service_name();

			// get the service object.
			$service_obj   = Services::get_instance()->get_service_by_name( $service_name );
			$service_title = $protocol_handler->get_title();
			if ( $service_obj instanceof Service_Base ) {
				$service_title = $service_obj->get_label();
			}

			// create the dialog.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'File info', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p><strong>' . __( 'URL', 'external-files-in-media-library' ) . ':</strong> ' . $url_html . '</p>',
					'<p><strong>' . __( 'Source', 'external-files-in-media-library' ) . ':</strong> ' . $service_title . '</p>',
					'<p><strong>' . __( 'Imported at', 'external-files-in-media-library' ) . ':</strong> ' . $external_file_obj->get_date() . '</p>',
					'<p><strong>' . __( 'Hosting', 'external-files-in-media-library' ) . ':</strong> ' . ( $external_file_obj->is_locally_saved() ? __( 'File is locally hosted.', 'external-files-in-media-library' ) : __( 'File is extern hosted.', 'external-files-in-media-library' ) ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'location.href="' . $edit_url . '#attachment_external_file"',
						'variant' => 'secondary',
						'text'    => __( 'Show all infos', 'external-files-in-media-library' ),
					),
				),
			);

			/**
			 * Filter the dialog for this file info.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param array<string,mixed> $dialog The dialog.
			 * @param File $external_file_obj The external file object.
			 */
			$dialog = apply_filters( 'efml_table_column_file_source_dialog', $dialog, $external_file_obj );

			// get the title.
			$title = '<span class="efml-icon efml-' . esc_attr( $service_name ) . '" title="' . esc_attr( $service_title ) . '"><span></span></span>';

			/**
			 * Filter the title for show in source column in the media table for external files.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param string $title The title to use.
			 * @param int $attachment_id The post ID of the attachment.
			 */
			$title = apply_filters( 'efml_table_column_source_title', $title, $attachment_id );

			// output.
			echo wp_kses_post( $title . ' <a href="' . esc_url( $edit_url ) . '" class="dashicons dashicons-info-outline easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" title="' . esc_html__( 'File info', 'external-files-in-media-library' ) . '"></a>' );

			// show deprecated hint for the old hook.
			do_action_deprecated( 'eml_table_column_source', array( $attachment_id ), '5.0.0', 'efml_table_column_source' );

			/**
			 * Run additional tasks for show more infos here.
			 *
			 * @since 1.0.0 Available since 1.0.0.
			 * @param int $attachment_id The ID of the attachment.
			 */
			do_action( 'efml_table_column_source', $attachment_id );
		}
	}

	/**
	 * Only show entries from the actual user, unless he has the role of an administrator.
	 *
	 * @param WP_Term_Query $query The query object for the term table.
	 *
	 * @return void
	 */
	public function use_user_mark( WP_Term_Query $query ): void {
		// bail if this is not the backend.
		if ( ! is_admin() ) {
			return;
		}

		// bail if this is not our taxonomy.
		if ( is_array( $query->query_vars['taxonomy'] ) && ! in_array( Taxonomy::get_instance()->get_name(), $query->query_vars['taxonomy'], true ) ) {
			return;
		}

		// bail if this is not our taxonomy.
		if ( ! is_array( $query->query_vars['taxonomy'] ) && Taxonomy::get_instance()->get_name() !== $query->query_vars['taxonomy'] ) {
			return;
		}

		// bail if the user has the administrator role OR setting to show all is enabled.
		if ( Helper::has_current_user_role( 'administrator' ) || 1 === absint( get_option( 'eml_show_all_external_sources' ) ) ) {
			return;
		}

		// extend the query.
		$query->meta_query->queries['relation'] = 'AND';
		$query->meta_query->queries[]           = array(
			'key'     => 'user_id',
			'value'   => get_current_user_id(),
			'compare' => '=',
		);
	}

	/**
	 * Add a column for the date the entry has been saved.
	 * Add a column for the user, only visible for administrators.
	 *
	 * @param array<string,string> $columns List of columns.
	 *
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		// add the column to show the date this entry has been saved.
		$columns['date'] = __( 'Date', 'external-files-in-media-library' );

		// bail if user has not the administrator role.
		if ( ! Helper::has_current_user_role( 'administrator' ) ) {
			return $columns;
		}

		// add the column to show the user, which saved these entry.
		$columns['user'] = __( 'User', 'external-files-in-media-library' );

		// return the resulting columns.
		return $columns;
	}

	/**
	 * Add info about the user, which saved this entry.
	 *
	 * @param string $content The column content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The term ID used.
	 *
	 * @return string
	 */
	public function add_column_content_user( string $content, string $column_name, int $term_id ): string {
		// bail if user has not the administrator role.
		if ( ! Helper::has_current_user_role( 'administrator' ) ) {
			return $content;
		}

		// bail if this is not the "user" column.
		if ( 'user' !== $column_name ) {
			return $content;
		}

		// get the user_id which saved this entry.
		$user_id = absint( get_term_meta( $term_id, 'user_id', true ) );

		// bail if no user_id is set.
		if ( 0 === $user_id ) {
			return '<em>' . __( 'No user set', 'external-files-in-media-library' ) . '</em>';
		}

		// get the user object by its ID.
		$user = get_user_by( 'ID', $user_id );

		// bail on not result.
		if ( ! $user instanceof WP_User ) {
			return '<em>' . __( 'Unknown user', 'external-files-in-media-library' ) . '</em>';
		}

		// return the linked user-name.
		return '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
	}

	/**
	 * Add info about the user, which saved this entry.
	 *
	 * @param string $content The column content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The term ID used.
	 *
	 * @return string
	 */
	public function add_column_content_date( string $content, string $column_name, int $term_id ): string {
		// bail if this is not the "date" column.
		if ( 'date' !== $column_name ) {
			return $content;
		}

		// get the date entry.
		$date = absint( get_term_meta( $term_id, 'date', true ) );

		// bail if date is not set.
		if ( 0 === $date ) {
			return '<em>' . __( 'Unknown date', 'external-files-in-media-library' ) . '</em>';
		}

		// return the formatted date.
		return Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $date ) );
	}

	/**
	 * Show info about unknown type.
	 *
	 * @param string $content The column content.
	 * @param string $column_name The column name.
	 *
	 * @return string
	 */
	public function show_unknown_type( string $content, string $column_name ): string {
		// bail if this is not the "type" column.
		if ( 'type' !== $column_name ) {
			return $content;
		}

		// return hint on unknown type.
		return '<em>' . __( 'Unknown', 'external-files-in-media-library' ) . '</em>';
	}
}
