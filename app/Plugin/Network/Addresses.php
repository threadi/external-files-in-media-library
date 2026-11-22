<?php
/**
 * This file contains the check of the addresses of the files of the central media libraries in the websites.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Files;

/**
 * Object to find and fix copies of files of a central media library, whose address does not match the address of the
 * central media library anymore (e.g. after a change of the domain of the network without replacing it in the
 * database).
 */
class Addresses {
	/**
	 * Name of the action on the page in the network administration.
	 */
	public const ACTION = 'efml_network_addresses';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Addresses
	 */
	private static ?Addresses $instance = null;

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
	 * @return Addresses
	 */
	public static function get_instance(): Addresses {
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
		add_filter( 'site_status_tests', array( $this, 'add_site_health_test' ) );
		add_action( 'network_admin_edit_' . self::ACTION, array( $this, 'fix_via_request' ) );
	}

	/**
	 * Fix the addresses in all websites via the page in the network administration.
	 *
	 * @return void
	 */
	public function fix_via_request(): void {
		// check the nonce.
		check_admin_referer( self::ACTION );

		// bail if the user is not allowed to manage the network.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'external-files-in-media-library' ) );
		}

		// fix them.
		$fixed = 0;
		foreach ( Media_Library::get_instance()->get_groups() as $group ) {
			foreach ( $group['sites'] as $blog_id ) {
				$fixed += $this->fix( $blog_id );
			}
		}

		// return to the overview.
		wp_safe_redirect( add_query_arg( array( 'efml_fixed' => $fixed ), Settings::get_instance()->get_url( 'overview' ) ) );
		exit;
	}

	/**
	 * Return the HTML of the button to fix the addresses in all websites.
	 *
	 * @return string
	 */
	public function get_button(): string {
		return '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=' . self::ACTION ) ) . '" style="display:inline">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false ) . '<button type="submit" class="button">' . esc_html__( 'Use the actual addresses', 'external-files-in-media-library' ) . '</button></form>';
	}

	/**
	 * Return the number of copies in the given website, whose address does not start with the address of its central
	 * media library.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return int
	 */
	public function count_wrong( int $blog_id ): int {
		global $wpdb;

		// bail if the website does not use a central media library.
		$central = Media_Library::get_instance()->get_central_for( $blog_id );
		if ( 0 === $central || $central === $blog_id ) {
			return 0;
		}

		// count the copies with another address.
		$address = (string) get_blogaddress_by_id( $central );
		switch_to_blog( $blog_id );
		$count = absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT c.`post_id`) FROM %i AS c INNER JOIN %i AS u ON u.`post_id` = c.`post_id` AND u.`meta_key` = %s WHERE c.`meta_key` IN (%s, %s) AND u.`meta_value` NOT LIKE %s', array( $wpdb->postmeta, $wpdb->postmeta, EFML_POST_META_URL, Media_Library::META_CENTRAL_ID, 'efml_multisite_attachment_id', $wpdb->esc_like( $address ) . '%' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		restore_current_blog();

		// return the number.
		return $count;
	}

	/**
	 * Use the actual address of the files of the central media library for the copies in the given website.
	 *
	 * The address is created from the ID of the file in the central media library, so it does not depend on the
	 * former address.
	 *
	 * @param int  $blog_id The website.
	 * @param bool $dry_run True to count only.
	 *
	 * @return int The number of fixed (or wrong) copies.
	 */
	public function fix( int $blog_id, bool $dry_run = false ): int {
		global $wpdb;

		// bail if the website does not use a central media library.
		$central = Media_Library::get_instance()->get_central_for( $blog_id );
		if ( 0 === $central || $central === $blog_id ) {
			return 0;
		}

		// get the copies and own uploads with their ID in the central media library (the ID of the copy is used first).
		switch_to_blog( $blog_id );
		$results = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id`, `meta_key`, `meta_value` FROM %i WHERE `meta_key` IN (%s, %s) ORDER BY `meta_key` = %s DESC', array( $wpdb->postmeta, Media_Library::META_CENTRAL_ID, 'efml_multisite_attachment_id', Media_Library::META_CENTRAL_ID ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		restore_current_blog();
		$rows = array();
		foreach ( $results as $result ) {
			if ( is_array( $result ) && ! isset( $rows[ absint( $result['post_id'] ) ] ) ) {
				$rows[ absint( $result['post_id'] ) ] = array(
					'post_id'    => absint( $result['post_id'] ),
					'meta_value' => absint( $result['meta_value'] ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				);
			}
		}

		// get the actual addresses of the files in the central media library.
		$urls = array();
		switch_to_blog( $central );
		foreach ( $rows as $row ) {
			$central_id = absint( $row['meta_value'] );
			if ( ! isset( $urls[ $central_id ] ) && 'attachment' === get_post_type( $central_id ) ) {
				$urls[ $central_id ] = Media_Library::get_instance()->get_file_url( $central_id );
			}
		}
		restore_current_blog();

		// fix each copy with another address.
		$fixed = 0;
		switch_to_blog( $blog_id );
		foreach ( $rows as $row ) {
			$copy_id    = absint( $row['post_id'] );
			$central_id = absint( $row['meta_value'] );
			if ( empty( $urls[ $central_id ] ) ) {
				continue;
			}
			$external_file_obj = Files::get_instance()->get_file( $copy_id );
			if ( ! $external_file_obj->is_valid() || $external_file_obj->get_url( true ) === $urls[ $central_id ] ) {
				continue;
			}
			++$fixed;
			if ( ! $dry_run ) {
				Media_Library::get_instance()->repoint_copy( $copy_id, $central_id, $central_id, $urls[ $central_id ] );
			}
		}
		restore_current_blog();

		// return the number.
		return $fixed;
	}

	/**
	 * Add the test for the addresses to the site health of a central media library.
	 *
	 * @param array<string,array<string,mixed>> $tests The tests.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_site_health_test( array $tests ): array {
		// bail if this is not a central media library.
		if ( ! Media_Library::get_instance()->is_central() ) {
			return $tests;
		}

		// add the test.
		$tests['direct']['efml_network_addresses'] = array(
			'label' => __( 'Addresses of the files of the central media library in the websites', 'external-files-in-media-library' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		// return the tests.
		return $tests;
	}

	/**
	 * Run the test for the addresses of the files of the actual central media library in its websites.
	 *
	 * @return array<string,mixed>
	 */
	public function run_site_health_test(): array {
		// collect the websites with wrong addresses.
		$sites = array();
		foreach ( array_keys( Media_Library::get_instance()->get_sites() ) as $blog_id ) {
			$count = $this->count_wrong( $blog_id );
			if ( $count > 0 ) {
				/* translators: %1$s will be replaced by the name of a website, %2$d by a number. */
				$sites[] = sprintf( _n( '%1$s: %2$d file', '%1$s: %2$d files', $count, 'external-files-in-media-library' ), Media_Library::get_instance()->get_site_label( $blog_id ), $count );
			}
		}

		// the result, if all addresses are fine.
		$result = array(
			'label'       => __( 'The websites use the actual addresses of the files of the central media library', 'external-files-in-media-library' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'External files in Media Library', 'external-files-in-media-library' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'The files of the central media library could be loaded in all websites.', 'external-files-in-media-library' ) . '</p>',
			'actions'     => '',
			'test'        => 'efml_network_addresses',
		);

		// bail if all addresses are fine.
		if ( empty( $sites ) ) {
			return $result;
		}

		// return the problem.
		$result['label']       = __( 'Some websites use other addresses for files of the central media library', 'external-files-in-media-library' );
		$result['status']      = 'critical';
		$result['description'] = '<p>' . esc_html__( 'These websites use addresses of files of the central media library, which do not start with its actual address, e.g. after a change of the domain:', 'external-files-in-media-library' ) . '</p><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $sites ) ) . '</li></ul><p>' . wp_kses_post( __( 'Use <code>wp eml network fix-addresses</code> or the button in the overview of the network to use the actual addresses.', 'external-files-in-media-library' ) ) . '</p>';
		return $result;
	}
}
