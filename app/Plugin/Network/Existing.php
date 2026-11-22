<?php
/**
 * This file contains the transfer of existing files of the websites to their central media library.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Export;
use ExternalFilesInMediaLibrary\ExternalFiles\Import_Runs;
use WP_Error;

/**
 * Object to transfer the local files of a website, which it had before it joined its group, to the central media
 * library.
 *
 * Uses the export of local files of the website in the background. Files with the same content as an existing file of
 * the central media library are linked to it (see Media_Library::find_duplicate()).
 */
class Existing {
	/**
	 * Name of the action on the page in the network administration.
	 */
	public const ACTION = 'efml_network_existing';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Existing
	 */
	private static ?Existing $instance = null;

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
	 * @return Existing
	 */
	public static function get_instance(): Existing {
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
		add_action( 'network_admin_edit_' . self::ACTION, array( $this, 'start_via_request' ) );
	}

	/**
	 * Return the number of local files of the given website, which could be transferred to its central media library.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return int
	 */
	public function count( int $blog_id ): int {
		// bail if the website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central( $blog_id ) ) {
			return 0;
		}

		// count them.
		switch_to_blog( $blog_id );
		$count = Export::get_instance()->count_local_files_for_export();
		restore_current_blog();

		// return the number.
		return $count;
	}

	/**
	 * Start the transfer of the local files of the given website to its central media library in the background.
	 *
	 * @param int $blog_id The website.
	 *
	 * @return string|WP_Error The message on success.
	 */
	public function start( int $blog_id ): string|WP_Error {
		// bail if the website does not use a central media library.
		if ( ! Media_Library::get_instance()->is_using_central( $blog_id ) ) {
			return new WP_Error( 'efml_network_no_member', __( 'The website does not use a central media library.', 'external-files-in-media-library' ) );
		}

		// start the export of the local files of the website: its target is the central media library.
		switch_to_blog( $blog_id );
		$result = Import_Runs::get_instance()->start_export();
		restore_current_blog();

		// return the result.
		if ( 0 === $result['run_id'] ) {
			return new WP_Error( 'efml_network_existing', $result['message'] );
		}
		return $result['message'];
	}

	/**
	 * Start the transfer for one or all websites via the page in the network administration.
	 *
	 * @return void
	 */
	public function start_via_request(): void {
		// check the nonce.
		check_admin_referer( self::ACTION );

		// bail if the user is not allowed to manage the network.
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'external-files-in-media-library' ) );
		}

		// get the websites.
		$blog_id = absint( filter_input( INPUT_POST, 'blog_id', FILTER_SANITIZE_NUMBER_INT ) );
		$sites   = array( $blog_id );
		if ( 0 === $blog_id ) {
			$sites = array();
			foreach ( Media_Library::get_instance()->get_groups() as $group ) {
				$sites = array_merge( $sites, $group['sites'] );
			}
		}

		// start the transfer for each website with local files.
		$started = 0;
		foreach ( $sites as $site_id ) {
			if ( $this->count( $site_id ) > 0 && is_string( $this->start( $site_id ) ) ) {
				++$started;
			}
		}

		// return to the overview.
		wp_safe_redirect(
			add_query_arg(
				array(
					'efml_existing' => $started,
				),
				Settings::get_instance()->get_url( 'overview' )
			)
		);
		exit;
	}

	/**
	 * Return the HTML of the button to start the transfer for the given website (0 for all).
	 *
	 * @param int $blog_id The website (0 for all).
	 *
	 * @return string
	 */
	public function get_button( int $blog_id ): string {
		$label = 0 === $blog_id ? __( 'Transfer the local files of all websites', 'external-files-in-media-library' ) : __( 'Transfer', 'external-files-in-media-library' );
		return '<form method="post" action="' . esc_url( network_admin_url( 'edit.php?action=' . self::ACTION ) ) . '" style="display:inline">' . wp_nonce_field( self::ACTION, '_wpnonce', true, false ) . '<input type="hidden" name="blog_id" value="' . absint( $blog_id ) . '"><button type="submit" class="button button-small" onclick="return confirm(' . esc_attr( (string) wp_json_encode( __( 'The local files are transferred to the central media library in the background. Files with the same content are linked to the existing files. Continue?', 'external-files-in-media-library' ) ) ) . ');">' . esc_html( $label ) . '</button></form>';
	}
}
