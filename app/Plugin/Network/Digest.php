<?php
/**
 * This file contains the weekly summary of the central media libraries via e-mail.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Network;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Object to send a weekly summary of problems in the websites of the central media libraries to the e-mail address of
 * the network administration. It is sent only if there is a problem.
 */
class Digest {
	/**
	 * Name of the cron event in the main website of the network.
	 */
	public const CRON = 'efml_network_digest';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Digest
	 */
	private static ?Digest $instance = null;

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
	 * @return Digest
	 */
	public static function get_instance(): Digest {
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
		add_action( self::CRON, array( $this, 'send_via_cron' ) );
	}

	/**
	 * Send the summary via cron.
	 *
	 * @return void
	 */
	public function send_via_cron(): void {
		$this->send();
	}

	/**
	 * Schedule the summary in the main website, if it is enabled, or remove it.
	 *
	 * @return void
	 */
	public function schedule(): void {
		switch_to_blog( get_main_site_id() );
		$scheduled = wp_next_scheduled( self::CRON );
		if ( 1 === (int) Media_Library::get_instance()->get_setting( 'digest' ) && Media_Library::get_instance()->is_active() ) {
			if ( ! $scheduled ) {
				wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::CRON );
			}
		} elseif ( $scheduled ) {
			wp_clear_scheduled_hook( self::CRON );
		}
		restore_current_blog();
	}

	/**
	 * Return the problems in the websites of the central media libraries as lines of text.
	 *
	 * @return array<int,string>
	 */
	public function get_problems(): array {
		$lines = array();
		foreach ( Media_Library::get_instance()->get_groups() as $group ) {
			foreach ( $group['sites'] as $blog_id ) {
				$state = Overview::get_instance()->get_site_state( $blog_id );
				$label = Media_Library::get_instance()->get_site_label( $blog_id );
				if ( $state['errors'] > 0 ) {
					/* translators: %1$s will be replaced by the name of a website, %2$d by a number. */
					$lines[] = sprintf( _n( '%1$s: %2$d error in the last 7 days', '%1$s: %2$d errors in the last 7 days', $state['errors'], 'external-files-in-media-library' ), $label, $state['errors'] );
				}
				if ( $state['overdue'] > 0 ) {
					/* translators: %1$s will be replaced by the name of a website, %2$d by a number. */
					$lines[] = sprintf( _n( '%1$s: WP Cron does not run, %2$d event is overdue', '%1$s: WP Cron does not run, %2$d events are overdue', $state['overdue'], 'external-files-in-media-library' ), $label, $state['overdue'] );
				}
				$wrong = Addresses::get_instance()->count_wrong( $blog_id );
				if ( $wrong > 0 ) {
					/* translators: %1$s will be replaced by the name of a website, %2$d by a number. */
					$lines[] = sprintf( _n( '%1$s: %2$d file uses another address than its central media library', '%1$s: %2$d files use another address than their central media library', $wrong, 'external-files-in-media-library' ), $label, $wrong );
				}
			}
		}
		return $lines;
	}

	/**
	 * Send the summary, if there are problems.
	 *
	 * @return bool True if it has been sent.
	 */
	public function send(): bool {
		// bail if it is disabled.
		if ( 1 !== (int) Media_Library::get_instance()->get_setting( 'digest' ) ) {
			return false;
		}

		// get the problems.
		$problems = $this->get_problems();

		// bail if there are none.
		if ( empty( $problems ) ) {
			return false;
		}

		// send them.
		$body = __( 'These problems have been found in the websites of the central media libraries:', 'external-files-in-media-library' ) . "\n\n- " . implode( "\n- ", $problems ) . "\n\n" . __( 'Details are in the overview:', 'external-files-in-media-library' ) . ' ' . Settings::get_instance()->get_url( 'overview' );
		/* translators: %1$s will be replaced by the name of the network. */
		return wp_mail( (string) get_site_option( 'admin_email' ), sprintf( __( '[%1$s] Problems with the central media libraries', 'external-files-in-media-library' ), (string) get_network()?->site_name ), $body );
	}
}
