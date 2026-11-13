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
use ExternalFilesInMediaLibrary\Plugin\Settings;
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
		add_filter( 'efml_help_tabs', array( $this, 'add_overview' ), 5 );
	}

	/**
	 * Add the help tab with the overview of what is possible with this plugin.
	 *
	 * @param array<int,array<string,string>> $help_list List of help tabs.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function add_overview( array $help_list ): array {
		// get the settings object.
		$settings = Settings::get_instance();

		// collect the goals with their links to the settings and the documentation.
		$goals = array(
			array(
				'title'    => __( 'Use external files', 'external-files-in-media-library' ),
				'text'     => __( 'Add files from URLs or from one of the services (e.g. FTP, Dropbox, AWS S3) to your media library. They are used like local files, but stay on their external source.', 'external-files-in-media-library' ),
				'url'      => Directory_Listing::get_instance()->get_view_directory_url( false ),
				'link'     => __( 'Add external files', 'external-files-in-media-library' ),
				'docs_url' => Helper::get_documentation_url( 'quickstart' ),
			),
			array(
				'title'    => __( 'Synchronize directories', 'external-files-in-media-library' ),
				'text'     => __( 'Save a directory as external source and let new, changed and deleted files be synchronized with your media library automatically.', 'external-files-in-media-library' ),
				'url'      => $settings->get_url( 'synchronization' ),
				'link'     => __( 'Settings for synchronization', 'external-files-in-media-library' ),
				'docs_url' => Helper::get_documentation_url( 'import/directories' ),
			),
			array(
				'title'    => __( 'Offload your media', 'external-files-in-media-library' ),
				'text'     => __( 'Export new uploads and existing files to an external source (e.g. AWS S3, FTP or WebDAV) to save storage space on your hosting. The files are then used as external files.', 'external-files-in-media-library' ),
				'url'      => $settings->get_url( 'eml_export' ),
				'link'     => __( 'Settings for export', 'external-files-in-media-library' ),
				'docs_url' => Helper::get_documentation_url( 'features' ),
			),
			array(
				'title'    => __( 'Deliver files privately', 'external-files-in-media-library' ),
				'text'     => __( 'Save exported files in private storages and deliver them via the proxy or via temporary links, e.g. for WooCommerce downloads.', 'external-files-in-media-library' ),
				'url'      => $settings->get_url( 'eml_proxy' ),
				'link'     => __( 'Settings for the proxy', 'external-files-in-media-library' ),
				'docs_url' => Helper::get_documentation_url( 'private-files' ),
			),
			array(
				'title'    => __( 'Keep access data out of the database', 'external-files-in-media-library' ),
				'text'     => __( 'Define the access data of the services as constants in your wp-config.php, also for several connections per service.', 'external-files-in-media-library' ),
				'url'      => $settings->get_url( 'services' ),
				'link'     => __( 'Settings for services', 'external-files-in-media-library' ),
				'docs_url' => Helper::get_documentation_url( 'constants' ),
			),
			array(
				'title'    => __( 'Protect copies of your website', 'external-files-in-media-library' ),
				'text'     => __( 'On copies of your website (e.g. staging), files are not exported to or deleted on external sources and scheduled synchronizations are paused. This works automatically.', 'external-files-in-media-library' ),
				'url'      => '',
				'link'     => '',
				'docs_url' => Helper::get_documentation_url( 'cli' ),
			),
		);

		// create the content.
		$content  = '<h1>' . esc_html__( 'What is possible?', 'external-files-in-media-library' ) . '</h1>';
		$content .= '<p>' . esc_html__( 'This plugin offers more than adding single URLs. Choose your goal:', 'external-files-in-media-library' ) . '</p>';
		foreach ( $goals as $goal ) {
			$content .= '<h3>' . esc_html( $goal['title'] ) . '</h3><p>' . esc_html( $goal['text'] );
			if ( '' !== $goal['url'] ) {
				$content .= ' <a href="' . esc_url( $goal['url'] ) . '">' . esc_html( $goal['link'] ) . '</a> &middot;';
			}
			$content .= ' <a href="' . esc_url( $goal['docs_url'] ) . '" target="_blank">' . esc_html__( 'Documentation', 'external-files-in-media-library' ) . Helper::get_a11n_window_hint() . '</a></p>';
		}

		// add the hint for the command palette.
		$content .= '<p><strong>' . esc_html__( 'Tip:', 'external-files-in-media-library' ) . '</strong> ' . esc_html__( 'Press Ctrl + K (Cmd + K on macOS) to search for services, your external sources and the settings of this plugin, e.g. "FTP" or "Export". This is available in WordPress 6.9 and newer.', 'external-files-in-media-library' ) . '</p>';

		// add the help tab as first tab.
		array_unshift(
			$help_list,
			array(
				'id'      => 'efml-overview',
				'title'   => __( 'What is possible?', 'external-files-in-media-library' ),
				'content' => $content,
			)
		);

		// return the list of help tabs.
		return $help_list;
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
		$sidebar_content = '<p><strong>' . __( 'Question not answered?', 'external-files-in-media-library' ) . '</strong></p><p><a href="' . esc_url( Helper::get_plugin_support_url() ) . '" target="_blank">' . esc_html__( 'Ask in our forum', 'external-files-in-media-library' ) . Helper::get_a11n_window_hint() . '</a></p>';

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
