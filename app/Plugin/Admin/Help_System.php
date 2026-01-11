<?php
/**
 * File for handle help system options of this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin\Admin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use WP_Screen;

/**
 * Helper-function for dashboard options of this plugin.
 */
class Help_System {
	/**
	 * Instance of this object.
	 *
	 * @var ?Help_System
	 */
	private static ?Help_System $instance = null;

	/**
	 * Constructor for Init-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Help_System {
		if ( is_null( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Initialize the site health support.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'current_screen', array( $this, 'add_help' ) );
	}

	/**
	 * Add the help box to our own pages with the configured contents.
	 *
	 * @param WP_Screen $screen The screen object.
	 *
	 * @return void
	 */
	public function add_help( WP_Screen $screen ): void {
		// bail if we are not in our settings screen OR not in the media upload screen.
		if ( ! in_array( $screen->base, array( 'settings_page_eml_settings', 'upload', 'media', 'media_page_efml_local_directories' ), true ) && 'edit-' . Taxonomy::get_instance()->get_name() !== $screen->id ) {
			return;
		}

		// get the help tabs.
		$help_tabs = $this->get_help_tabs();

		// bail if list is empty.
		if ( empty( $help_tabs ) ) {
			return;
		}

		// add our own help tabs.
		foreach ( $help_tabs as $help_tab ) {
			$screen->add_help_tab( $help_tab );
		}

		// add the sidebar.
		$this->add_sidebar( $screen );
	}

	/**
	 * Add the sidebar with its content.
	 *
	 * @param WP_Screen $screen The screen object.
	 *
	 * @return void
	 */
	private function add_sidebar( WP_Screen $screen ): void {
		// get content for the sidebar.
		$sidebar_content = '<p><strong>' . __( 'Question not answered?', 'external-files-in-media-library' ) . '</strong></p><p><a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank">' . esc_html__( 'Ask in our forum', 'external-files-in-media-library' ) . '</a></p>';

		// add help sidebar with the given content.
		$screen->set_help_sidebar( $sidebar_content );
	}

	/**
	 * Return the list of help tabs.
	 *
	 * @return array<string,mixed>
	 */
	private function get_help_tabs(): array {
		$list = array();

		// show deprecated warning for the old hook name.
		$list = apply_filters_deprecated( 'eml_help_tabs', array( $list ), '5.0.0', 'efml_help_tabs' );

		/**
		 * Filter the list of help tabs with its contents.
		 *
		 * @since 2.0.0 Available since 2.0.0.
		 * @param array<string,mixed> $list List of help tabs.
		 */
		return apply_filters( 'efml_help_tabs', $list );
	}
}
