<?php
/**
 * File to handle the command palette for this plugin.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Plugin;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Page;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use WP_REST_Request;
use WP_REST_Server;

/**
 * The object, which handles the command palette for this plugin.
 */
class Commands {
	/**
	 * Instance of this object.
	 *
	 * @var ?Commands
	 */
	private static ?Commands $instance = null;

	/**
	 * Constructor for Schedules-Handler.
	 */
	private function __construct() {}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return the instance of this Singleton object.
	 */
	public static function get_instance(): Commands {
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
		// add action to enqueue scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// add the endpoint to search for settings, pages and documentations.
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register the endpoint to search for settings, pages and documentations of this plugin.
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		register_rest_route(
			'external-files-in-media-library/v1',
			'/pages/',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pages_via_rest' ),
				'permission_callback' => function () {
					return current_user_can( EFML_CAP_NAME );
				},
			)
		);
	}

	/**
	 * Return the settings, pages and documentations matching the search of the request.
	 *
	 * @param WP_REST_Request $data The REST API request data.
	 *
	 * @return array<int,array<string,int|string|bool>>
	 */
	public function get_pages_via_rest( WP_REST_Request $data ): array {
		// get the search.
		$search = $data->get_param( 'search' );

		// bail if search is empty.
		if ( ! is_string( $search ) || '' === trim( $search ) ) {
			return array();
		}

		// collect the matching entries.
		$list = array();
		foreach ( $this->get_pages() as $page ) {
			// bail if the search does not match.
			if ( ! Helper::is_search_matching( $search, array_merge( array( $page['label'] ), $page['keywords'] ) ) ) {
				continue;
			}

			// add the entry.
			$list[] = array(
				'id'       => count( $list ) + 1,
				'label'    => $page['label'],
				'value'    => $page['name'],
				'url'      => $page['url'],
				'external' => $page['external'],
			);
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the settings, pages and documentations of this plugin, which the actual user could use.
	 *
	 * @return array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}>
	 */
	public function get_pages(): array {
		// collect the entries.
		$list = array();

		// add the page to add external files.
		if ( current_user_can( EFML_CAP_NAME ) ) {
			$list[] = $this->get_entry( 'add-files', __( 'Add external files', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_view_directory_url( false ), _x( 'external files, services, import, URL, choose source', 'search terms for the page to add external files, separated by commas', 'external-files-in-media-library' ) );
			$list[] = $this->get_entry( 'add-url', __( 'Add external URLs', 'external-files-in-media-library' ), Helper::get_add_media_url(), _x( 'external files, import, URL, link, add', 'search terms for the dialog to add URLs, separated by commas', 'external-files-in-media-library' ) );
		}

		// add the list of external sources.
		if ( current_user_can( \easyDirectoryListingForWordPress\Init::get_instance()->get_capabilities()['manage_terms'] ) ) {
			$list[] = $this->get_entry( 'sources', __( 'Your external sources', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url(), _x( 'external sources, saved sources, directories, archive', 'search terms for the list of external sources, separated by commas', 'external-files-in-media-library' ) );
		}

		// add the tabs of the settings.
		$list = array_merge( $list, $this->get_settings_pages() );

		// add the documentations.
		$list = array_merge( $list, $this->get_documentations() );

		/**
		 * Filter the settings, pages and documentations of this plugin, which could be found in the command palette.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}> $list List of entries.
		 */
		return apply_filters( 'efml_command_palette_pages', $list );
	}

	/**
	 * Return an entry for the list of pages.
	 *
	 * @param string $name     The internal name.
	 * @param string $label    The label.
	 * @param string $url      The URL.
	 * @param string $keywords The search terms, separated by commas.
	 * @param bool   $external Whether the URL should be opened in a new window.
	 *
	 * @return array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}
	 */
	private function get_entry( string $name, string $label, string $url, string $keywords, bool $external = false ): array {
		return array(
			'name'     => $name,
			'label'    => $label,
			'url'      => $url,
			'keywords' => array_values( array_filter( array_map( 'trim', explode( ',', $keywords ) ) ) ),
			'external' => $external,
		);
	}

	/**
	 * Return the tabs and sub-tabs of the settings as entries for the list of pages.
	 *
	 * @return array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}>
	 */
	private function get_settings_pages(): array {
		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// bail if the user is not allowed to use the settings.
		if ( ! current_user_can( $settings_obj->get_capability() ) ) {
			return array();
		}

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return array();
		}

		// get the search terms for the tabs.
		$keywords = $this->get_settings_keywords();

		// collect the tabs.
		$list = array();
		$done = array();
		foreach ( $settings_page->get_tabs() as $tab ) {
			// bail if this tab has no usable title (e.g. the help link) or is already in the list.
			if ( ! $this->is_tab_usable( $tab ) || isset( $done[ $tab->get_name() ] ) ) {
				continue;
			}
			$done[ $tab->get_name() ] = true;

			// add the tab, if it has no sub-tabs.
			$sub_tabs = $tab->get_tabs();
			if ( empty( $sub_tabs ) ) {
				/* translators: %1$s will be replaced by the title of a tab in the settings. */
				$list[] = $this->get_entry( 'settings-' . $tab->get_name(), sprintf( __( 'External files settings: %1$s', 'external-files-in-media-library' ), $tab->get_title() ), Settings::get_instance()->get_url( $tab->get_name() ), $keywords[ $tab->get_name() ] ?? '' );
				continue;
			}

			// add its sub-tabs.
			foreach ( $sub_tabs as $sub_tab ) {
				// bail if this tab has no usable title or is already in the list.
				if ( ! $this->is_tab_usable( $sub_tab ) || isset( $done[ $tab->get_name() . '/' . $sub_tab->get_name() ] ) ) {
					continue;
				}
				$done[ $tab->get_name() . '/' . $sub_tab->get_name() ] = true;

				// add the sub-tab with the search terms of its tab.
				/* translators: %1$s will be replaced by the title of a tab in the settings, %2$s by the title of its sub-tab. */
				$list[] = $this->get_entry( 'settings-' . $tab->get_name() . '-' . $sub_tab->get_name(), sprintf( __( 'External files settings: %1$s › %2$s', 'external-files-in-media-library' ), $tab->get_title(), $sub_tab->get_title() ), Settings::get_instance()->get_url( $tab->get_name(), $sub_tab->get_name() ), ( $keywords[ $sub_tab->get_name() ] ?? '' ) . ',' . $tab->get_title() );
			}
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return whether the given tab could be shown in the command palette.
	 *
	 * @param Tab $tab The tab.
	 *
	 * @return bool
	 */
	private function is_tab_usable( Tab $tab ): bool {
		return '' !== trim( $tab->get_title() ) && '' === $tab->get_url() && ! $tab->is_show_in_menu() && ! $tab->is_not_linked();
	}

	/**
	 * Return the search terms for the tabs of the settings.
	 *
	 * @return array<string,string>
	 */
	private function get_settings_keywords(): array {
		return array(
			'eml_general'            => _x( 'general, import, dialog, availability, check', 'search terms for the general settings, separated by commas', 'external-files-in-media-library' ),
			'services'               => _x( 'services, overview, external sources, credentials, access data', 'search terms for the settings of the services, separated by commas', 'external-files-in-media-library' ),
			'eml_proxy'              => _x( 'proxy, cache, delivery, SEO', 'search terms for the proxy settings, separated by commas', 'external-files-in-media-library' ),
			'eml_export'             => _x( 'export, offloading, offload, upload, cloud, CDN, storage, private files', 'search terms for the export settings, separated by commas', 'external-files-in-media-library' ),
			'synchronization'        => _x( 'synchronization, sync, schedule, cron, automatic', 'search terms for the synchronization settings, separated by commas', 'external-files-in-media-library' ),
			'eml_advanced_files'     => _x( 'advanced, files, hosting, local', 'search terms for the advanced settings for files, separated by commas', 'external-files-in-media-library' ),
			'eml_advanced_plugin'    => _x( 'advanced, plugin, intro, reset, export settings, import settings', 'search terms for the advanced settings for the plugin, separated by commas', 'external-files-in-media-library' ),
			'eml_advanced_abilities' => _x( 'abilities, AI, MCP, agent', 'search terms for the settings of the abilities, separated by commas', 'external-files-in-media-library' ),
			'eml_advanced_misc'      => _x( 'advanced, miscellaneous, timeout, logs', 'search terms for the miscellaneous settings, separated by commas', 'external-files-in-media-library' ),
			'eml_permissions'        => _x( 'permissions, roles, capabilities, users, rights', 'search terms for the permission settings, separated by commas', 'external-files-in-media-library' ),
			'eml_logs'               => _x( 'logs, log, errors, debug, status', 'search terms for the logs, separated by commas', 'external-files-in-media-library' ),
			'eml_queue_table'        => _x( 'queue, background, jobs, progress, status', 'search terms for the queue, separated by commas', 'external-files-in-media-library' ),
			'eml_statistics'         => _x( 'statistics, counter, size, status', 'search terms for the statistics, separated by commas', 'external-files-in-media-library' ),
		);
	}

	/**
	 * Return the documentations of this plugin as entries for the list of pages.
	 *
	 * @return array<int,array{name: string, label: string, url: string, keywords: array<int,string>, external: bool}>
	 */
	private function get_documentations(): array {
		// return the list.
		return array(
			$this->get_entry( 'docs-features', __( 'Documentation: What is possible?', 'external-files-in-media-library' ), Helper::get_documentation_url( 'features' ), _x( 'documentation, help, features, overview, getting started', 'search terms for the documentation of the features, separated by commas', 'external-files-in-media-library' ), true ),
			$this->get_entry( 'docs-constants', __( 'Documentation: Credentials via constants', 'external-files-in-media-library' ), Helper::get_documentation_url( 'constants' ), _x( 'documentation, help, constants, wp-config.php, credentials, access data, connections', 'search terms for the documentation of the constants, separated by commas', 'external-files-in-media-library' ), true ),
			$this->get_entry( 'docs-private-files', __( 'Documentation: Private files', 'external-files-in-media-library' ), Helper::get_documentation_url( 'private-files' ), _x( 'documentation, help, private files, signed URLs, WooCommerce, downloads', 'search terms for the documentation of private files, separated by commas', 'external-files-in-media-library' ), true ),
			$this->get_entry( 'docs-cli', __( 'Documentation: WP CLI', 'external-files-in-media-library' ), Helper::get_documentation_url( 'cli' ), _x( 'documentation, help, WP CLI, command line, terminal', 'search terms for the documentation of WP CLI, separated by commas', 'external-files-in-media-library' ), true ),
		);
	}

	/**
	 * Add the command palette script to the admin area.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		// bail if the user does not have capabilities to use services.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// get the path for the asset script.
		$script_asset_path = Helper::get_plugin_path() . 'js/commands/commands.asset.php';

		// bail if the asset script does not exist.
		if ( ! file_exists( $script_asset_path ) ) {
			return;
		}

		// embed script.
		$script_asset = require $script_asset_path;

		wp_enqueue_script(
			'external-files-in-media-library-commands',
			Helper::get_plugin_url() . 'js/commands/commands.js',
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
	}
}
