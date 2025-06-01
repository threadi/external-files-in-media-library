<?php
/**
 * This file contains an object which extend the media tables in backend.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Query;

/**
 * Object which extends the attachment tables in backend.
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
		add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'add_media_column_content' ), 10, 2 );
	}

	/**
	 * Add filter in media library for external files.
	 *
	 * @return void
	 */
	public function add_media_filter(): void {
		// bail if get_current_screen() is not available.
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

		// define possible options.
		$options = array(
			'none'         => __( 'All files', 'external-files-in-media-library' ),
			'external'     => __( 'only external URLs', 'external-files-in-media-library' ),
			'non-external' => __( 'no external URLs', 'external-files-in-media-library' ),
		);

		/**
		 * Filter the possible options.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param array<string,string> $options The list of possible options.
		 */
		$options = apply_filters( 'efml_filter_options', $options );

		?>
		<!--suppress HtmlFormInputWithoutLabel -->
		<select name="admin_filter_media_external_files">
			<?php
			foreach ( $options as $value => $label ) {
				?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php echo $request_value === (string) $value ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * Change main query to filter external files in media library if requested.
	 *
	 * @param WP_Query $query The Query-object.
	 * @return void
	 */
	public function add_media_do_filter( WP_Query $query ): void {
		// bail if this is not admin.
		if ( ! is_admin() ) {
			return;
		}

		// bail if this is not the main query.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// get filter value.
		$filter = filter_input( INPUT_GET, 'admin_filter_media_external_files', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if filter is not set.
		if ( is_null( $filter ) ) {
			return;
		}

		// filter to any external file.
		if ( 'external' === $filter ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => EFML_POST_META_URL,
						'compare' => 'EXISTS',
					),
				)
			);
		}

		// filter for any non-external file.
		if ( 'non-external' === $filter ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => EFML_POST_META_URL,
						'compare' => 'NOT EXISTS',
					),
				)
			);
		}

		/**
		 * Filter the query.
		 *
		 * @since 4.0.0 Available since 4.0.0.
		 * @param WP_Query $query The WP_Query object.
		 */
		do_action_ref_array( 'efml_filter_query', array( &$query ) );
	}

	/**
	 * Add column to mark external files in media table.
	 *
	 * @param array<string,string> $columns List of columns in media table.
	 *
	 * @return array<string,string>
	 */
	public function add_media_columns( array $columns ): array {
		$columns['external_files'] = __( 'External file', 'external-files-in-media-library' );
		return $columns;
	}

	/**
	 * Add content for our custom column in media table.
	 *
	 * @param string $column_name The requested column.
	 * @param int    $attachment_id The requested attachment id.
	 *
	 * @return void
	 */
	public function add_media_column_content( string $column_name, int $attachment_id ): void {
		if ( 'external_files' === $column_name ) {
			// get the external object for this file.
			$external_file = Files::get_instance()->get_file( $attachment_id );

			// bail if it is not an external file.
			if ( ! $external_file || false === $external_file->is_valid() ) {
				echo '<span class="dashicons dashicons-no"></span>';
			} else {
				echo '<span class="dashicons dashicons-yes"></span>';
			}

			/**
			 * Run additional tasks for show more infos here.
			 */
			do_action( 'eml_table_column_content', $attachment_id );
		}
	}
}
