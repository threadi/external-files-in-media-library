<?php
/**
 * This file contains the upload space of the websites, which use a central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to count the uploads of a website in the central media library for its upload space.
 *
 * WordPress measures the upload space of a website by the size of its upload directory. The uploads of websites,
 * which use a central media library, are stored in the central media library. Without this object they would not
 * count for their website, but for the central media library.
 */
class Quota {
	/**
	 * Name of the transient with the bytes of the uploads of a website in the central media library.
	 */
	private const TRANSIENT = 'efml_network_space_';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Quota
	 */
	private static ?Quota $instance = null;

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
	 * @return Quota
	 */
	public static function get_instance(): Quota {
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
		add_filter( 'pre_get_space_used', array( $this, 'get_space_used' ) );
	}

	/**
	 * Return whether the upload space is counted by this object.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// bail if the upload space is not limited in the network.
		if ( ! is_multisite() || get_site_option( 'upload_space_check_disabled' ) ) {
			return false;
		}

		// return whether it is enabled in the settings.
		return Media_Library::get_instance()->is_active() && 1 === (int) Media_Library::get_instance()->get_setting( 'quota' );
	}

	/**
	 * Return the used upload space of the actual website in MB.
	 *
	 * @param int|float|false $space_used The used space (false to let WordPress measure it).
	 *
	 * @return int|float|false
	 */
	public function get_space_used( int|float|false $space_used ): int|float|false {
		// bail if another plugin has set the value or if this is disabled.
		if ( false !== $space_used || ! $this->is_enabled() ) {
			return $space_used;
		}

		// get the website.
		$blog_id = get_current_blog_id();

		// a website, which uses a central media library: its local files and its uploads in the central media library.
		if ( Media_Library::get_instance()->is_using_central( $blog_id ) ) {
			return $this->get_local_space() + $this->get_bytes_of_uploads( Media_Library::get_instance()->get_central_for( $blog_id ), $blog_id ) / MB_IN_BYTES;
		}

		// a central media library: its files without the uploads of the other websites.
		if ( Media_Library::get_instance()->is_central( $blog_id ) ) {
			return max( 0, $this->get_local_space() - $this->get_bytes_of_uploads( $blog_id, 0 ) / MB_IN_BYTES );
		}

		// use the value of WordPress for any other website.
		return $space_used;
	}

	/**
	 * Return the size of the upload directory of the actual website in MB (like WordPress measures it).
	 *
	 * @return float
	 */
	private function get_local_space(): float {
		$upload_dir = wp_upload_dir();
		return (float) get_dirsize( $upload_dir['basedir'] ) / MB_IN_BYTES;
	}

	/**
	 * Return the bytes of the uploads of the given website in the given central media library.
	 *
	 * With 0 as website the bytes of the uploads of all other websites are returned.
	 *
	 * @param int $central The website with the central media library.
	 * @param int $blog_id The website, from which the files come (0 for all).
	 *
	 * @return int
	 */
	public function get_bytes_of_uploads( int $central, int $blog_id ): int {
		global $wpdb;

		// use the cached value.
		$transient = self::TRANSIENT . $central . '_' . $blog_id;
		$bytes     = get_site_transient( $transient );
		if ( false !== $bytes ) {
			return absint( $bytes );
		}

		// get the sum from the central media library.
		switch_to_blog( $central );
		if ( $blog_id > 0 ) {
			$bytes = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(b.`meta_value`) FROM %i AS b INNER JOIN %i AS o ON o.`post_id` = b.`post_id` AND o.`meta_key` = %s AND o.`meta_value` = %s WHERE b.`meta_key` = %s', array( $wpdb->postmeta, $wpdb->postmeta, Media_Library::META_ORIGIN_BLOG, (string) $blog_id, Media_Library::META_BYTES ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			$bytes = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(b.`meta_value`) FROM %i AS b INNER JOIN %i AS o ON o.`post_id` = b.`post_id` AND o.`meta_key` = %s AND o.`meta_value` <> %s WHERE b.`meta_key` = %s', array( $wpdb->postmeta, $wpdb->postmeta, Media_Library::META_ORIGIN_BLOG, (string) $central, Media_Library::META_BYTES ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		restore_current_blog();

		// save it for some minutes: the space is checked on each upload.
		$bytes = absint( $bytes );
		set_site_transient( $transient, $bytes, 5 * MINUTE_IN_SECONDS );

		// return the bytes.
		return $bytes;
	}

	/**
	 * Forget the cached bytes of the given website in the given central media library (and of all websites).
	 *
	 * @param int $central The website with the central media library.
	 * @param int $blog_id The website, from which the file comes.
	 *
	 * @return void
	 */
	public function reset( int $central, int $blog_id ): void {
		delete_site_transient( self::TRANSIENT . $central . '_' . $blog_id );
		delete_site_transient( self::TRANSIENT . $central . '_0' );
	}
}
