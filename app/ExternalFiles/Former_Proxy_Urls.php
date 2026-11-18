<?php
/**
 * File to keep the proxy URLs of files working, which are no external files anymore.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\Plugin\Log;
use WP_Query;

/**
 * Object to keep the proxy URLs of files working, which are no external files anymore.
 *
 * Contents contain the proxy URLs of external files (e.g. "/emlproxy/image.jpg"), as WordPress inserts the URL of the
 * attachment. If such a file becomes a local file (e.g. via the real import or the restore of exported files), its
 * URL is removed and the proxy does not know the file anymore: the proxy URLs in the contents would result in 404.
 *
 * Therefore, the names of the file in the proxy (its title and its former titles) are saved, when its URL is removed:
 * each name as protected post meta, whose key contains the hash of the name (e.g. "_efml_proxy_name_3f2a…"), so it is
 * found via the index of the database (like the URL index). The proxy redirects requests for such a name (also for
 * its sizes) to the actual URL of the file.
 *
 * These post meta are intentionally not part of Files::get_meta_keys(): they must survive the removal of the data of
 * the plugin from the file (real import, restore). They are removed together with the attachment and during the
 * uninstallation.
 */
class Former_Proxy_Urls {
	/**
	 * The prefix of the post meta keys (with underscore, so they are protected and hidden).
	 */
	public const PREFIX = '_efml_proxy_name_';

	/**
	 * List of attachments, which are deleted in this request.
	 *
	 * @var array<int,bool>
	 */
	private array $deleted_attachments = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Former_Proxy_Urls
	 */
	private static ?Former_Proxy_Urls $instance = null;

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
	 * @return Former_Proxy_Urls
	 */
	public static function get_instance(): Former_Proxy_Urls {
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
		// save the names in the proxy, before the URL of an external file is removed.
		add_action( 'delete_post_meta', array( $this, 'save_names_before_url_deletion' ), 10, 3 );

		// do not save them, if the attachment itself is deleted.
		add_action( 'delete_attachment', array( $this, 'mark_as_deleted' ), 0 );
		add_action( 'deleted_post', array( $this, 'unmark_as_deleted' ) );
	}

	/**
	 * Return the meta key for the given name.
	 *
	 * @param string $name The name of the file in the proxy.
	 *
	 * @return string
	 */
	public function get_key( string $name ): string {
		return self::PREFIX . md5( $name );
	}

	/**
	 * Mark the given attachment as deleted in this request.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function mark_as_deleted( int $attachment_id ): void {
		$this->deleted_attachments[ $attachment_id ] = true;
	}

	/**
	 * Remove the mark of a deleted post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function unmark_as_deleted( int $post_id ): void {
		unset( $this->deleted_attachments[ $post_id ] );
	}

	/**
	 * Save the names of an external file in the proxy, before its URL is removed.
	 *
	 * @param mixed $meta_ids The IDs of the meta entries.
	 * @param mixed $post_id  The post ID.
	 * @param mixed $meta_key The meta key.
	 *
	 * @return void
	 */
	public function save_names_before_url_deletion( mixed $meta_ids, mixed $post_id, mixed $meta_key ): void {
		// bail if this is not the URL of an external file.
		if ( EFML_POST_META_URL !== $meta_key || ! is_numeric( $post_id ) ) {
			return;
		}

		// get the attachment ID.
		$attachment_id = absint( $post_id );

		// bail if the attachment itself is deleted.
		if ( isset( $this->deleted_attachments[ $attachment_id ] ) ) {
			return;
		}

		// bail during the uninstallation, as all data of the plugin will be removed.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// bail if it is not an attachment.
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		// save the names.
		$this->save_names( $attachment_id );
	}

	/**
	 * Save the names of the given file in the proxy (its title and its former titles).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function save_names( int $attachment_id ): void {
		// collect the names: the actual title and the former titles (e.g. before an edit or a rename).
		$names = array( Files::get_instance()->get_file( $attachment_id )->get_title() );
		foreach ( (array) get_post_meta( $attachment_id, Files::FORMER_TITLES_META, false ) as $former_title ) {
			if ( is_string( $former_title ) ) {
				$names[] = $former_title;
			}
		}

		// save each name.
		foreach ( array_unique( array_filter( $names ) ) as $name ) {
			update_post_meta( $attachment_id, $this->get_key( $name ), $name );
		}
	}

	/**
	 * Return the ID of the file, which had the given name in the proxy, or 0 if none (or more than one) has been found.
	 *
	 * Files in trash are ignored, as they should not be delivered.
	 *
	 * @param string $name The name of the file in the proxy.
	 *
	 * @return int
	 */
	public function find( string $name ): int {
		// bail if no name is given.
		if ( '' === $name ) {
			return 0;
		}

		// search via the key of the name.
		$query  = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Search via the index of the meta key.
				array(
					'key'     => $this->get_key( $name ),
					'compare' => 'EXISTS',
				),
			),
			'posts_per_page'         => 2,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		$result = new WP_Query( $query );

		// bail if not a single file has been found (the name is ambiguous then).
		if ( 1 !== $result->post_count ) {
			return 0;
		}

		// get the ID.
		$attachment_id = absint( $result->get_posts()[0] );

		// return it, if it had this name (it always should, the hash is only the key).
		return get_post_meta( $attachment_id, $this->get_key( $name ), true ) === $name ? $attachment_id : 0;
	}

	/**
	 * Return the actual URL of the file, which had the given name in the proxy, or an empty string, if no such file
	 * exists.
	 *
	 * Supports the names of sizes, e.g. "image-300x200.jpg".
	 *
	 * @param string $name The requested name in the proxy.
	 *
	 * @return string
	 */
	public function get_redirect_url( string $name ): string {
		// search the file with this name.
		$dimensions    = array();
		$file_name     = $name;
		$attachment_id = $this->find( $name );

		// search the file of a size, e.g. "image-300x200.jpg".
		if ( 0 === $attachment_id && 1 === preg_match( '/^(.+)-(\d+)x(\d+)\.([A-Za-z0-9]+)$/', $name, $matches ) ) {
			$file_name     = $matches[1] . '.' . $matches[4];
			$attachment_id = $this->find( $file_name );
			$dimensions    = array( absint( $matches[2] ), absint( $matches[3] ) );
		}

		// bail if no file has been found.
		if ( 0 === $attachment_id ) {
			return '';
		}

		// bail if it is an external file with the requested name (the proxy delivers it itself, prevents a loop).
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );
		if ( $external_file_obj->is_valid() && $external_file_obj->get_title() === $file_name ) {
			return '';
		}

		// get the URL of the requested size of an image.
		$url = '';
		if ( ! empty( $dimensions ) && wp_attachment_is_image( $attachment_id ) ) {
			$image = wp_get_attachment_image_src( $attachment_id, $dimensions );
			if ( is_array( $image ) ) {
				$url = $image[0];
			}
		}

		// get the URL of the file.
		if ( '' === $url ) {
			$url = (string) wp_get_attachment_url( $attachment_id );
		}

		/**
		 * Filter the URL, to which a request for the former proxy URL of a file is redirected. The file has been an
		 * external file before, e.g. before its real import or the restore of an exported file.
		 *
		 * Return an empty string to prevent the redirect.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $url           The actual URL of the file.
		 * @param int    $attachment_id The attachment ID.
		 * @param string $name          The requested name in the proxy.
		 */
		return apply_filters( 'efml_former_proxy_url_redirect', $url, $attachment_id, $name );
	}

	/**
	 * Redirect a request of the proxy for a file, which has been an external file before, to its actual URL.
	 *
	 * Does nothing, if no such file exists.
	 *
	 * @param string $name The requested name in the proxy.
	 *
	 * @return void
	 */
	public function redirect( string $name ): void {
		// get the actual URL.
		$url = $this->get_redirect_url( $name );

		// bail if there is none.
		if ( '' === $url ) {
			return;
		}

		// log this event only in debug mode, as this request can be sent by anyone.
		/* translators: %1$s will be replaced by the requested filename. */
		Log::get_instance()->create( sprintf( __( 'Proxy redirects the request for %1$s to the actual URL of the file, as it is no external file anymore.', 'external-files-in-media-library' ), '<code>' . esc_html( $name ) . '</code>' ), $url, 'info', 2 );

		// redirect temporarily, so browsers and CDNs do not save it permanently.
		if ( wp_redirect( $url, 302, 'External Files in Media Library' ) ) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Remove the saved names of all files during the uninstallation.
	 *
	 * @return void
	 */
	public function uninstall(): void {
		global $wpdb;

		// remove the keys of all files.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE `meta_key` LIKE %s', $wpdb->postmeta, $wpdb->esc_like( self::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
