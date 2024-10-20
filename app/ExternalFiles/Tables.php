<?php
/**
 * This file contains an object which extend the media tables in backend.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Initialize the admin tasks for this plugin.
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
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Forms
	 */
	public static function get_instance(): Tables {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
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
		// check nonce.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'eml-restrict-manage-posts' ) ) {
			// redirect user back.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// bail if get_current_screen() is not available.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// only for upload-screen.
		$scr = get_current_screen();
		if ( 'upload' !== $scr->base ) {
			return;
		}

		// get value from request.
		$request_value = isset( $_GET['admin_filter_media_external_files'] ) ? sanitize_text_field( wp_unslash( $_GET['admin_filter_media_external_files'] ) ) : '';

		// define possible options.
		$options = array(
			'none'         => __( 'All files', 'external-files-in-media-library' ),
			'external'     => __( 'only external URLs', 'external-files-in-media-library' ),
			'non-external' => __( 'no external URLs', 'external-files-in-media-library' ),
		);
		?>
		<!--suppress HtmlFormInputWithoutLabel -->
		<select name="admin_filter_media_external_files">
			<?php
			foreach ( $options as $value => $label ) {
				?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php echo $request_value === $value ? ' selected="selected"' : ''; ?>><?php echo esc_html( $label ); ?></option>
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

		// check nonce.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'eml-filter-posts' ) ) {
			// redirect user back.
			wp_safe_redirect( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' );
			exit;
		}

		// bail if filter is not set.
		if ( ! isset( $_GET['admin_filter_media_external_files'] ) ) {
			return;
		}

		if ( 'external' === $_GET['admin_filter_media_external_files'] ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => EML_POST_META_URL,
						'compare' => 'EXISTS',
					),
				)
			);
		}
		if ( 'non-external' === $_GET['admin_filter_media_external_files'] ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => EML_POST_META_URL,
						'compare' => 'NOT EXISTS',
					),
				)
			);
		}
	}

	/**
	 * Add column to mark external files in media table.
	 *
	 * @param array $columns List of columns in media table.
	 *
	 * @return array
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
		}
	}
}
