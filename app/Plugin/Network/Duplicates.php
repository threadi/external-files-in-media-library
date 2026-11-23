<?php
/**
 * This file contains the merging of files with the same content in a central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_Post;

/**
 * Object to find files with the same content in a central media library and to merge them.
 *
 * Merging uses the kept file for the copies of the duplicate in all websites (their attachments stay, so their usage in
 * contents is kept) and deletes the duplicate in the central media library afterward.
 */
class Duplicates {
	/**
	 * Name of the page in the central media library.
	 */
	public const PAGE = 'efml-network-duplicates';

	/**
	 * Name of the action to merge files.
	 */
	public const ACTION = 'efml_network_merge';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Duplicates
	 */
	private static ?Duplicates $instance = null;

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
	 * @return Duplicates
	 */
	public static function get_instance(): Duplicates {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'merge_via_request' ) );
	}

	/**
	 * Return the files with the same content in the actual central media library as lists of attachment IDs.
	 *
	 * @param int $limit The maximum number of lists.
	 *
	 * @return array<int,array<int,int>>
	 */
	public function get_groups( int $limit = 100 ): array {
		global $wpdb;

		// get the hashes with more than one file.
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT GROUP_CONCAT(m.`post_id` ORDER BY m.`post_id` ASC) FROM %i AS m INNER JOIN %i AS p ON p.`ID` = m.`post_id` AND p.`post_type` = %s WHERE m.`meta_key` = %s AND m.`meta_value` <> %s GROUP BY m.`meta_value` HAVING COUNT(*) > 1 ORDER BY MIN(m.`post_id`) ASC LIMIT %d', array( $wpdb->postmeta, $wpdb->posts, 'attachment', Media_Library::META_HASH, '', $limit ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// return them as lists of IDs.
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[] = array_map( 'absint', explode( ',', (string) $row ) );
		}
		return $groups;
	}

	/**
	 * Merge the given duplicate into the given file of the actual central media library.
	 *
	 * @param int $keep      The attachment ID of the file to keep.
	 * @param int $duplicate The attachment ID of the duplicate, which is deleted afterward.
	 *
	 * @return true|WP_Error
	 */
	public function merge( int $keep, int $duplicate ): true|WP_Error {
		$media_library = Media_Library::get_instance();

		// bail if this is not a central media library.
		if ( ! $media_library->is_central() ) {
			return new WP_Error( 'efml_network_no_central', __( 'The website is not a central media library.', 'external-files-in-media-library' ) );
		}

		// bail if the files are not two different files with the same content.
		$hash = (string) get_post_meta( $keep, Media_Library::META_HASH, true );
		if ( $keep === $duplicate || 'attachment' !== get_post_type( $keep ) || 'attachment' !== get_post_type( $duplicate ) || '' === $hash || get_post_meta( $duplicate, Media_Library::META_HASH, true ) !== $hash ) {
			return new WP_Error( 'efml_network_no_duplicate', __( 'The files are not files with the same content.', 'external-files-in-media-library' ) );
		}

		// bail if the duplicate is used in contents of the central media library: they would lose it.
		if ( Usage::get_instance()->get_post_count( $duplicate ) > 0 ) {
			return new WP_Error( 'efml_network_duplicate_used', __( 'The duplicate is used in contents of the central media library. Use the other file there first.', 'external-files-in-media-library' ) );
		}

		// get the data of both files.
		$urls       = array( $media_library->get_file_url( $duplicate ), (string) wp_get_attachment_url( $duplicate ) );
		$origin     = $media_library->get_origin_blog( $duplicate );
		$keep_url   = $media_library->get_file_url( $keep );
		$keep_owner = $media_library->get_origin_blog( $keep );

		// use the kept file for the copies of the duplicate in all websites.
		foreach ( array_keys( $media_library->get_sites() ) as $blog_id ) {
			switch_to_blog( $blog_id );
			$copies = $media_library->find_copies( $duplicate, $urls, $origin );
			foreach ( $copies as $copy_id ) {
				// the upload of another website than the one of the kept file is not an own upload anymore.
				if ( absint( get_post_meta( $copy_id, 'efml_multisite_attachment_id', true ) ) === $duplicate && $blog_id !== $keep_owner ) {
					update_post_meta( $copy_id, Media_Library::META_LINKED, 1 );
				}
				$media_library->repoint_copy( $copy_id, $duplicate, $keep, $keep_url );
			}
			restore_current_blog();

			// the website could use the kept file.
			if ( ! empty( $copies ) ) {
				$media_library->allow_site( $keep, $blog_id );
			}
		}

		// delete the duplicate: no website has a copy of it anymore.
		$result = wp_delete_attachment( $duplicate, true );

		// log this event.
		/* translators: %1$d and %2$d will be replaced by attachment IDs. */
		\ExternalFilesInMediaLibrary\Plugin\Log::get_instance()->create( sprintf( __( 'Merged the file %1$d into the file %2$d with the same content in the central media library.', 'external-files-in-media-library' ), $duplicate, $keep ), $keep_url, 'info', 1 );

		// return the result.
		return $result instanceof WP_Post ? true : new WP_Error( 'efml_network_merge', __( 'The duplicate could not be deleted.', 'external-files-in-media-library' ) );
	}

	/**
	 * Add the page with the duplicates to the media library of a central media library.
	 *
	 * @return void
	 */
	public function add_page(): void {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return;
		}

		add_submenu_page(
			'upload.php',
			__( 'Duplicates in the network', 'external-files-in-media-library' ),
			__( 'Duplicates in the network', 'external-files-in-media-library' ),
			'upload_files',
			self::PAGE,
			array( $this, 'show_page' )
		);
	}

	/**
	 * Show the page with the duplicates.
	 *
	 * @return void
	 */
	public function show_page(): void {
		// get the result of a merge.
		$merged = filter_input( INPUT_GET, 'efml_merged', FILTER_SANITIZE_NUMBER_INT );
		$error  = filter_input( INPUT_GET, 'efml_error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Duplicates in the network', 'external-files-in-media-library' ); ?></h1>
			<?php
			if ( ! is_null( $merged ) ) {
				/* translators: %1$d will be replaced by a number. */
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( '%1$d file has been merged.', '%1$d files have been merged.', absint( $merged ), 'external-files-in-media-library' ), absint( $merged ) ) ) . '</p></div>';
			}
			if ( ! empty( $error ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
			?>
			<p><?php echo esc_html__( 'These files have the same content. Merging uses the kept file in all websites, also where the others are used in contents, and deletes the others in the central media library. Create a backup beforehand.', 'external-files-in-media-library' ); ?></p>
			<?php
			$groups = $this->get_groups();
			if ( empty( $groups ) ) {
				echo '<p><em>' . esc_html__( 'No files with the same content have been found.', 'external-files-in-media-library' ) . '</em></p>';
			}
			foreach ( $groups as $ids ) {
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em">
					<?php wp_nonce_field( self::ACTION ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<table class="widefat striped" style="max-width:900px">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Keep', 'external-files-in-media-library' ); ?></th>
								<th><?php echo esc_html__( 'File', 'external-files-in-media-library' ); ?></th>
								<th><?php echo esc_html__( 'Date', 'external-files-in-media-library' ); ?></th>
								<th><?php echo esc_html__( 'From website', 'external-files-in-media-library' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $ids as $index => $attachment_id ) {
								$origin = Media_Library::get_instance()->get_origin_blog( $attachment_id );
								?>
								<tr>
									<td><input type="radio" name="keep" value="<?php echo absint( $attachment_id ); ?>"<?php checked( 0, $index ); ?>><input type="hidden" name="ids[]" value="<?php echo absint( $attachment_id ); ?>"></td>
									<td><?php echo wp_get_attachment_image( $attachment_id, array( 40, 40 ), true ); ?> <a href="<?php echo esc_url( (string) get_edit_post_link( $attachment_id ) ); ?>"><?php echo esc_html( get_the_title( $attachment_id ) ); ?></a> (<?php echo absint( $attachment_id ); ?>)</td>
									<td><?php echo esc_html( (string) get_the_date( '', $attachment_id ) ); ?></td>
									<td><?php echo $origin > 0 ? esc_html( Media_Library::get_instance()->get_site_label( $origin ) ) : '&ndash;'; ?></td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
					<p><button type="submit" class="button" onclick="return confirm(<?php echo esc_attr( (string) wp_json_encode( __( 'The other files are deleted in the central media library. The websites use the kept file instead. Continue?', 'external-files-in-media-library' ) ) ); ?>);"><?php echo esc_html__( 'Merge', 'external-files-in-media-library' ); ?></button></p>
				</form>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Merge the files of a request from the page.
	 *
	 * @return void
	 */
	public function merge_via_request(): void {
		// check the nonce.
		check_admin_referer( self::ACTION );

		// get the files.
		$keep = absint( filter_input( INPUT_POST, 'keep', FILTER_SANITIZE_NUMBER_INT ) );
		$ids  = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ids'] ) ) : array();

		// merge each other file, if the user is allowed to delete it.
		$merged = 0;
		$error  = '';
		foreach ( array_diff( $ids, array( $keep ) ) as $duplicate ) {
			if ( ! current_user_can( 'edit_post', $keep ) || ! current_user_can( 'delete_post', $duplicate ) ) {
				$error = __( 'You are not allowed to delete this file.', 'external-files-in-media-library' );
				continue;
			}
			$result = $this->merge( $keep, $duplicate );
			if ( $result instanceof WP_Error ) {
				$error = $result->get_error_message();
				continue;
			}
			++$merged;
		}

		// return to the page.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE,
					'efml_merged' => $merged,
					'efml_error'  => rawurlencode( $error ),
				),
				admin_url( 'upload.php' )
			)
		);
		exit;
	}
}
