<?php
/**
 * File to handle the export of uploaded files to supporting external services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\TextInfo;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use WP_Post;
use WP_Query;
use WP_Term;
use WP_Term_Query;

/**
 * Object to handle the export of uploaded files.
 */
class Export {
	/**
	 * Marker for running sync.
	 *
	 * @var bool
	 */
	private bool $sync_running = false;

	/**
	 * Instance of actual object.
	 *
	 * @var ?Export
	 */
	private static ?Export $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() { }

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() { }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Export
	 */
	public static function get_instance(): Export {
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
		// add the settings.
		add_action( 'init', array( $this, 'init_export' ), 20 );
		add_action( 'post-upload-ui', array( $this, 'show_export_hint_on_file_add_page' ) );
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_export_state_in_info_dialog' ), 10, 2 );
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_column_for_hint' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_hint_content' ), 10, 2 );

		// bail if not enabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_columns' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_options' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_files' ), 10, 3 );
		add_action( 'efml_before_sync', array( $this, 'add_sync_filter' ), 10, 3 );
		add_action( 'efml_before_deleting_synced_files', array( $this, 'add_sync_filter_during_deletion' ) );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'add_export_in_directory_listing' ), 10, 2 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'remove_listing_delete_action' ), 10, 2 );
		add_action( 'efml_show_file_info', array( $this, 'add_info_about_export' ) );
		add_action( 'efml_real_import_local', array( $this, 'delete_exported_file_during_import' ) );
		add_action( 'efml_switch_to_local_before', array( $this, 'prevent_export_checks_on_local_switch' ), 10, 0 );
		add_action( 'efml_switch_to_local_after', array( $this, 'cleanup_exported_file' ) );

		// add admin actions.
		add_action( 'admin_action_efml_delete_exported_files', array( $this, 'delete_exported_file_via_request' ) );
		add_action( 'admin_action_efml_export_file', array( $this, 'export_file_via_request' ) );

		// use AJAX.
		add_action( 'wp_ajax_efml_get_export_config_dialog', array( $this, 'get_export_config_dialog' ) );
		add_action( 'wp_ajax_efml_save_export_config', array( $this, 'save_export_config' ) );
		add_action( 'wp_ajax_efml_change_export_state', array( $this, 'export_state_change_via_ajax' ) );

		// use hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ) );
		add_action( 'add_attachment', array( $this, 'export_file_by_upload' ) );
		add_filter( 'wp_unique_filename', array( $this, 'check_for_exported_filenames' ), 10, 3 );
		add_action( 'delete_attachment', array( $this, 'delete_exported_file' ) );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'update_attachment_metadata' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'on_delete_archive_term' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'change_media_row_actions' ), 20, 2 );
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'run_bulk_action' ), 10, 3 );
	}

	/**
	 * Add settings for export.
	 *
	 * @return void
	 */
	public function init_export(): void {
		// get settings object.
		$settings_obj = Settings::get_instance();

		// get main settings page.
		$page = $settings_obj->get_page( 'eml_settings' );

		// bail if page could not be found.
		if ( ! $page instanceof Page ) {
			return;
		}

		// add a settings tab.
		$tab = $page->add_tab( 'eml_export', 40 );
		$tab->set_title( __( 'Export', 'external-files-in-media-library' ) );

		// add export section.
		$section = $tab->add_section( 'settings_section_export', 10 );
		$section->set_title( __( 'Export of files', 'external-files-in-media-library' ) );
		$section->set_callback( array( $this, 'export_description' ) );

		// add setting.
		$setting_export = $settings_obj->add_setting( 'eml_export' );
		$setting_export->set_section( $section );
		$setting_export->set_type( 'integer' );
		$setting_export->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable export', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by a URL. */
		$field->set_description( sprintf( __( 'If enabled you have to configure one or more of <a href="%1$s">your external sources</a> as export target. Every new file in media library will be exported to the configured source and used as external file.', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url() ) );
		$field->set_setting( $setting_export );
		$setting_export->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_do_not_delete_local_files' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Do not delete local files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'When enabled, exported files are no longer deleted from local hosting. Note that this means you will be storing the files twice: once in your hosting and once in the external source. Files delivered via the proxy still take up storage space on it. However, the proxy cache is emptied regularly so that only files that are actually needed take up storage space.', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// collect the external sources.
		$external_sources = $this->get_external_sources_as_name_list();

		// create URL.
		$url = add_query_arg(
			array(
				'taxonomy'  => 'edlfw_archive',
				'post_type' => 'attachment',
			),
			get_admin_url() . 'edit-tags.php'
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_main_source' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		if ( empty( $external_sources ) ) {
			$field = new TextInfo();
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'You have not enabled export for any external sources. Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $url ) );
		} else {
			$field = new Select();
			$field->set_options( $external_sources );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( sprintf( __( 'Select the external source with export enabled to which all new media files should be assigned. Other external sources with export enabled that are not selected here will also be used, but will not be assigned to the new files. Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $url ) );
		}
		$field->set_title( __( 'Choose your primary external source', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_local_files' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Show option to export local files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'When enabled, you will be able to export local saved files to an external service.', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );
	}

	/**
	 * Add column for hint if export is not enabled.
	 *
	 * @param array<string,string> $columns The columns.
	 *
	 * @return array<string,string>
	 */
	public function add_column_for_hint( array $columns ): array {
		// bail if user has not the capability.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $columns;
		}

		// bail if export is enabled.
		if ( 1 === absint( get_option( 'eml_export' ) ) ) {
			return $columns;
		}

		// add the column.
		$columns['efml_export_hint'] = __( 'Export', 'external-files-in-media-library' );

		// return the list of columns.
		return $columns;
	}

	/**
	 * Add columns to handle exports.
	 *
	 * @param array<string,string> $columns The columns.
	 *
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		// bail if user has not the capability.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $columns;
		}

		// add the export columns.
		$columns['efml_export_files'] = __( 'Exported files', 'external-files-in-media-library' );
		$columns['efml_export']       = __( 'Export', 'external-files-in-media-library' );

		// return the resulting columns.
		return $columns;
	}

	/**
	 * Show hint to enabled export.
	 *
	 * @param string $content The column content.
	 * @param string $column_name The column name.
	 *
	 * @return string
	 */
	public function add_column_hint_content( string $content, string $column_name ): string {
		// bail if column is not 'efml_export_hint'.
		if ( 'efml_export_hint' !== $column_name ) {
			return $content;
		}

		// show simple hint for users without capability to change settings.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Export media files', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Export your media files to this external source.', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'Ask your website administrator about the possibility of activating this feature.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);
		// extend the hint for all others.
		if ( current_user_can( 'manage_options' ) ) {
			/* translators: %1$s will be replaced by a URL. */
			$dialog['texts'][1] = '<p>' . sprintf( __( 'Enable this option <a href="%1$s">in your options</a>', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) ) . '</p>';

			// show extended hint for all others.
			return '<a class="dashicons dashicons-editor-help easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) ) . '"></a>';
		}

		// show simple hint.
		return '<a class="dashicons dashicons-editor-help easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" href="#"></a>';
	}

	/**
	 * Add the content to configure an external source as possible export target.
	 *
	 * @param string $content The content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The used term entry ID.
	 *
	 * @return string
	 */
	public function add_column_content_options( string $content, string $column_name, int $term_id ): string {
		// bail if this is not the "efml_export" column.
		if ( 'efml_export' !== $column_name ) {
			return $content;
		}

		// get the listings object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
			// create dialog for sync now.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Export is not supported', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p><strong>' . __( 'Export for this external source is currently not supported.', 'external-files-in-media-library' ) . '</strong></p>',
					/* translators: %1$s will be replaced by a URL. */
					'<p>' . sprintf( __( 'If you have any questions, please feel free to ask them <a href="%1$s" target="_blank">in our support forum (opens new window)</a>.', 'external-files-in-media-library' ), Helper::get_plugin_support_url() ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);

			// show specific hint.
			if ( $listing_obj instanceof Service_Base ) {
				/* translators: %1$s will be replaced by a title. */
				$dialog['texts'][0] = '<p><strong>' . sprintf( __( 'Export to %1$s is currently not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			}

			// return the resulting link.
			return '<a href="#" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" title="' . esc_attr__( 'Not supported', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-editor-help"></span></a>';
		}

		// get the export object.
		$export_obj = $listing_obj->get_export_object();

		// get actual state.
		$enabled = absint( get_term_meta( $term_id, 'efml_export', true ) );

		// get URL setting, if required.
		$url = 'active';
		if ( $export_obj->is_url_required() ) {
			$url = get_term_meta( $term_id, 'efml_export_url', true );
		}

		// define actions.
		$actions = array(
			'<div class="eml-switch-toggle"><input id="export-on-' . absint( $term_id ) . '" name="export-states[' . absint( $term_id ) . ']" class="green" data-term-id="' . absint( $term_id ) . '" value="1" type="radio"' . ( $enabled > 0 ? ' checked' : '' ) . ( empty( $url ) ? ' readonly="readonly"' : '' ) . ' /><label for="export-on-' . absint( $term_id ) . '" class="green">' . __( 'On', 'external-files-in-media-library' ) . '</label><input id="export-off-' . absint( $term_id ) . '" name="export-states[' . absint( $term_id ) . ']" class="red" type="radio" data-term-id="' . absint( $term_id ) . '" value="0"' . ( 0 === $enabled ? ' checked' : '' ) . ( empty( $url ) ? ' readonly="readonly"' : '' ) . ' /><label for="export-off-' . absint( $term_id ) . '" class="red">' . __( 'Off', 'external-files-in-media-library' ) . '</label></div>',
			'<a href="#" class="button button-secondary efml-export" data-term-id="' . absint( $term_id ) . '">' . __( 'Configure', 'external-files-in-media-library' ) . '</a>',
		);

		// return the link for export settings.
		return implode( ' ', $actions );
	}

	/**
	 * Add the content for synced files.
	 *
	 * @param string $content The content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The used term entry ID.
	 *
	 * @return string
	 */
	public function add_column_content_files( string $content, string $column_name, int $term_id ): string {
		// bail if this is not the "synchronization" column.
		if ( 'efml_export_files' !== $column_name ) {
			return $content;
		}

		// get the files which are assigned to this term.
		$files = $this->get_exported_files_by_term( $term_id );

		// bail on no results.
		if ( empty( $files ) ) {
			return '0';
		}

		// create URL to show the list.
		$url = add_query_arg(
			array(
				'mode'                              => 'list',
				'admin_filter_media_external_files' => $term_id,
			),
			get_admin_url() . 'upload.php'
		);

		// collect the actions.
		$actions = array( '<a href="' . esc_url( $url ) . '">' . absint( count( $files ) ) . '</a>' );

		// only add the delete link on the main term.
		if ( absint( get_option( 'eml_export_main_source' ) ) === $term_id ) {
			// create URL to delete them.
			$url_delete = add_query_arg(
				array(
					'action' => 'efml_delete_exported_files',
					'nonce'  => wp_create_nonce( 'efml-exported-synced-files' ),
					'term'   => $term_id,
				),
				get_admin_url() . 'admin.php'
			);

			// create dialog for delete link.
			$dialog = array(
				'className' => 'efml',
				'title'     => __( 'Delete exported files?', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p><strong>' . __( 'Do you really want to delete this exported files?', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'The files will be deleted in your media library AND the external source.', 'external-files-in-media-library' ) . '</p>',
					'<p>' . __( 'If the files are used on the website, they are no longer visible and usable on the website.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'location.href="' . $url_delete . '"',
						'variant' => 'primary',
						'text'    => __( 'Yes, delete them', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'closeDialog();',
						'variant' => 'secondary',
						'text'    => __( 'Cancel', 'external-files-in-media-library' ),
					),
				),
			);

			// add to the actions.
			$actions[] = '<a href="' . esc_url( $url_delete ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . esc_html__( 'Delete', 'external-files-in-media-library' ) . '</a>';
		}

		/**
		 * Filter the actions for export handlings on the external source terms.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array<int,string> $actions The action.
		 * @param int $term_id The term ID.
		 */
		$actions = apply_filters( 'efml_export_actions', $actions, $term_id );

		// return the actions.
		return implode( ' | ', $actions );
	}

	/**
	 * Return list of all files which are assigned to a given term.
	 *
	 * @param int $term_id The term to filter.
	 *
	 * @return array<int,int>
	 */
	private function get_exported_files_by_term( int $term_id ): array {
		// get all files which are assigned to this term_id.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'eml_exported_file',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'efml_export_sources',
					'value'   => ':' . $term_id . ';',
					'compare' => 'LIKE',
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// bail on no results.
		if ( 0 === $result->found_posts ) {
			return array();
		}

		// fill the list.
		$list = array();
		foreach ( $result->get_posts() as $post_id ) {
			$post_id = absint( $post_id );

			// add to the list.
			$list[] = $post_id;
		}

		// return the resulting list of files.
		return $list;
	}

	/**
	 * Add CSS- and JS-files for backend.
	 *
	 * @param string $hook The used hook.
	 *
	 * @return void
	 */
	public function add_styles_and_js_admin( string $hook ): void {
		// bail if page is used where we do not use it.
		if ( 'edit-tags.php' !== $hook ) {
			return;
		}

		// backend-JS.
		wp_enqueue_script(
			'eml-export-admin',
			Helper::get_plugin_url() . 'admin/export.js',
			array( 'jquery' ),
			Helper::get_file_version( Helper::get_plugin_dir() . 'admin/export.js' ),
			true
		);

		// add php-vars to our js-script.
		wp_localize_script(
			'eml-export-admin',
			'efmlJsExportVars',
			array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'export_config_nonce'      => wp_create_nonce( 'efml-export-config-nonce' ),
				'save_export_config_nonce' => wp_create_nonce( 'efml-export-save-config-nonce' ),
				'export_state_nonce'       => wp_create_nonce( 'efml-export-state-nonce' ),
			)
		);
	}

	/**
	 * Return the export configuration dialog for a given source term.
	 *
	 * @return void
	 */
	public function get_export_config_dialog(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-config-nonce', 'nonce' );

		// create dialog for failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration could not be loaded', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The export configuration for this target could not be loaded.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get term ID from request.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term ID is given.
		if ( 0 === $term_id ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the term.
		$term = get_term_by( 'term_id', $term_id, Taxonomy::get_instance()->get_name() );

		// bail if no term could be found.
		if ( ! $term instanceof WP_Term ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the listings object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base ) {
			$dialog['texts'][] = '<p><strong>' . __( 'Export to this external source is currently not supported.', 'external-files-in-media-library' ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// bail if no object could be found.
		if ( ! $listing_obj->get_export_object() instanceof Export_Base ) {
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][] = '<p><strong>' . sprintf( __( 'Export to %1$s is currently not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get actual state.
		$enabled = absint( get_term_meta( $term_id, 'efml_export', true ) );

		// get URL.
		$url = get_term_meta( $term_id, 'efml_export_url', true );

		// create the form.
		// -> first the state.
		$form = '<div><label for="enable">' . __( 'Enable:', 'external-files-in-media-library' ) . '</label><input type="checkbox" name="enable" id="enable" value="1"' . ( $enabled > 0 ? ' checked="checked"' : '' ) . '></div>';

		// -> marker for main export source, if user has the capability for it.
		if ( current_user_can( 'efml_cap_tools_export' ) ) {
			/* translators: %1$s will be replaced by a URL. */
			$description = sprintf( __( 'Manage this setting <a href="%1$s">here</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) );
			$form       .= '<div><label for="main_export">' . __( 'Main export:', 'external-files-in-media-library' ) . '</label><input type="checkbox" name="main_export" id="main_export" value="1"' . ( absint( get_option( 'eml_export_main_source' ) ) === $term_id ? ' checked="checked"' : '' ) . '> ' . $description . '</div>';
		}

		// -> then the URL, if required.
		if ( $listing_obj->get_export_object()->is_url_required() ) {
			$form .= '<div><label for="url">' . __( 'URL:', 'external-files-in-media-library' ) . '</label><input type="url" placeholder="https://example.com" name="url" id="url" value="' . esc_url( $url ) . '">' . __( 'This must be the URL where files uploaded to this external source are available.', 'external-files-in-media-library' ) . '</div>';
		}

		// -> the term ID.
		$form .= '<input type="hidden" name="term_id" value="' . $term_id . '">';

		// create dialog for sync config.
		$dialog = array(
			'className' => 'efml efml-export-config',
			/* translators: %1$s will be replaced by a name. */
			'title'     => sprintf( __( 'Export settings for %1$s', 'external-files-in-media-library' ), $term->name ),
			'texts'     => array(
				'<p><strong>' . __( 'Configure this external source as the destination for files newly added to the media library.', 'external-files-in-media-library' ) . '</strong> ' . __( 'This allows you to use this external directory to store all your files.', 'external-files-in-media-library' ) . '</p>',
				$form,
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_export_save_config(' . $term_id . ');',
					'variant' => 'primary',
					'text'    => __( 'Save', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		/**
		 * Filter the dialog to configure an export.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $dialog The dialog.
		 * @param int $term_id The term ID.
		 */
		$dialog = apply_filters( 'efml_export_config_dialog', $dialog, $term_id );

		// send the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Save the export settings.
	 *
	 * @return void
	 */
	public function save_export_config(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-save-config-nonce', 'nonce' );

		// create dialog for any failures.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Configuration could not be saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The export configuration for this target could not be saved.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get term ID from request.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term ID is given.
		if ( 0 === $term_id ) {
			$dialog['texts'][] = '<p>' . __( 'No target specified.', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the listings object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base ) {
			$dialog['texts'][] = '<p><strong>' . __( 'Export to this external source is currently not supported.', 'external-files-in-media-library' ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// bail if no object could be found.
		if ( ! $listing_obj->get_export_object() instanceof Export_Base ) {
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][] = '<p><strong>' . sprintf( __( 'Export to %1$s is currently not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the URL, if required.
		if ( $listing_obj->get_export_object()->is_url_required() ) {
			$url = filter_input( INPUT_POST, 'url', FILTER_SANITIZE_URL );

			// bail if URL is not given.
			if ( empty( $url ) ) {
				$dialog['texts'][] = '<p>' . __( 'No URL specified.', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// bail if given URL is not valid.
			if ( ! wp_http_validate_url( $url ) ) {
				$dialog['texts'][] = '<p>' . __( 'Given URL can not be used.', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// send a test request to this URL.
			$response = wp_safe_remote_head( $url );

			// bail on any error.
			if ( is_wp_error( $response ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Given URL could not be reached! Error:', 'external-files-in-media-library' ) . '<em>' . wp_json_encode( $response ) . '</em>', $url, 'error' );

				// configure dialog and return it.
				$dialog['texts'][] = '<p>' . __( 'Given URL could not be reached!', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// get the HTTP status for this URL.
			$response_code = wp_remote_retrieve_response_code( $response );

			// check the HTTP status, should be 200, 403 or 404.
			$result = in_array( $response_code, array( 200, 403, 404 ), true );

			/**
			 * Filter the possible HTTP states for export URLs during export configuration.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 *
			 * @param bool   $result The check result.
			 * @param string $url    The given URL.
			 */
			if ( ! apply_filters( 'efml_export_configuration_url_state', $result, $url ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Given URL is returning a not allowed HTTP state:', 'external-files-in-media-library' ) . ' <code>' . $response_code . '</code>', $url, 'info' );

				// configure dialog and return it.
				$dialog['texts'][] = '<p>' . __( 'Given URL is returning a not allowed HTTP state!', 'external-files-in-media-library' ) . '</p>';
				wp_send_json( array( 'detail' => $dialog ) );
			}

			// save URL.
			update_term_meta( $term_id, 'efml_export_url', $url );
		}

		// get the state.
		$enabled = absint( filter_input( INPUT_POST, 'enable', FILTER_SANITIZE_NUMBER_INT ) );

		// enable the export state for this term.
		$this->set_state_for_term( $term_id, $enabled );

		// get the main_export marker.
		$main_export = absint( filter_input( INPUT_POST, 'main_export', FILTER_SANITIZE_NUMBER_INT ) );

		// if this export is enabled and the main export is set, set this export as main export.
		if ( 1 === $enabled && 1 === $main_export ) {
			update_option( 'eml_export_main_source', $term_id );
		}

		// create dialog.
		$dialog = array(
			'className' => 'efml efml-export-config',
			'title'     => __( 'Configuration saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The configuration has been saved successfully.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.reload();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// show hint if enabled.
		if ( $enabled ) {
			$dialog['texts'][] = '<p>' . __( 'As soon as you upload new files to your media library, they will be stored in this external storage.', 'external-files-in-media-library' ) . '</p>';
		}

		/**
		 * Filter the dialog after saving an updated export configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $dialog The dialog.
		 * @param int $term_id The term ID.
		 */
		$dialog = apply_filters( 'efml_export_save_config_dialog', $dialog, $term_id );

		// send the dialog.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Change export state via AJAX.
	 *
	 * @return void
	 */
	public function export_state_change_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-state-nonce', 'nonce' );

		// get term ID.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if term ID is not given.
		if ( 0 === $term_id ) {
			wp_send_json_error();
		}

		// get the new state.
		$new_state = absint( filter_input( INPUT_POST, 'state', FILTER_SANITIZE_NUMBER_INT ) );

		// save the new state.
		$this->set_state_for_term( $term_id, $new_state );

		// send ok.
		wp_send_json_success();
	}

	/**
	 * Save new state for single term.
	 *
	 * @param int $term_id The term ID.
	 * @param int $enabled The new state.
	 *
	 * @return void
	 */
	private function set_state_for_term( int $term_id, int $enabled ): void {
		// get the term.
		$term = get_term( $term_id, Taxonomy::get_instance()->get_name() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// get the main export source setting.
		$main_export_source_term_id = absint( get_option( 'eml_export_main_source' ) );

		// save the enabled state.
		if ( 1 === $enabled ) {
			add_term_meta( $term_id, 'efml_export', time() );

			// if no main export source is set, set this one.
			if ( 0 === $main_export_source_term_id ) {
				update_option( 'eml_export_main_source', $term_id );
			}

			// log event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'External source %1$s is now enabled for export of files.', 'external-files-in-media-library' ), '<em>' . $term->name . '</em>' ), '', 'info', 2 );
		} else {
			// save the disabled state.
			delete_term_meta( $term_id, 'efml_export' );

			// if this is the main export source, remove it.
			if ( $term_id === $main_export_source_term_id ) {
				update_option( 'eml_export_main_source', 0 );
			}

			// log event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'External source %1$s is now disabled for export of files.', 'external-files-in-media-library' ), '<em>' . $term->name . '</em>' ), '', 'info', 2 );
		}
	}

	/**
	 * Return list of terms which are enabled for export.
	 *
	 * @return array<int,int>
	 */
	private function get_export_terms(): array {
		// get the export terms.
		$query = array(
			'taxonomy'     => Taxonomy::get_instance()->get_name(),
			'hide_empty'   => false,
			'count'        => false,
			'meta_key'     => 'efml_export',
			'meta_compare' => 'EXISTS',
			'fields'       => 'ids',
		);
		$terms = new WP_Term_Query( $query );

		// bail on no results.
		if ( empty( $terms->terms ) ) {
			return array();
		}

		// create the list.
		$list = array();

		// add each WP_Term to the list.
		foreach ( $terms->terms as $term_id ) {
			$list[] = absint( $term_id );
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Export a fresh uploaded and saved media library attachment on external hostings and configure the file data
	 * to use them as external files.
	 *
	 * Does not run if:
	 * - synchronisation is running.
	 * - import of an external URL is running.
	 * - the given attachment is already an external file.
	 *
	 * Loops through all for export enabled external sources and export the file to these sources.
	 * The last external source is the primary source to host the file. All others are backups.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function export_file_by_upload( int $attachment_id ): void {
		// bail if sync is running.
		if ( defined( 'EFML_URL_IMPORT_RUNNING' ) || $this->is_sync_running() ) {
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'Checking if new uploaded file should be exported.', 'external-files-in-media-library' ), '', 'info', 2 );

		// export the file (we do not use the response as we are not in user-env atm).
		$this->export_file( $attachment_id );
	}

	/**
	 * Run export of a given single file.
	 *
	 * This is the main handling to request the export through an Export_Base object of external sources.
	 *
	 * Loops through all external sources with enabled export option. The last one is the main export target.
	 *
	 * If successfully the file will be converted to an external @File object.
	 *
	 * @param int $attachment_id The attachment ID to export.
	 *
	 * @return bool Return true if the file export was successfully, false it not.
	 */
	private function export_file( int $attachment_id ): bool {
		// get external file object for the given attachment ID.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is already an external file.
		if ( $external_file_obj->is_valid() ) {
			// log this event.
			Log::get_instance()->create( __( 'Given file for export is already saved as external file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error', 0 );

			// do nothing more.
			return false;
		}

		// get the file name.
		$file = get_attached_file( $attachment_id, true );

		// bail if file name could not be loaded.
		if ( ! is_string( $file ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Path for given file to export could not be found.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error', 0 );

			// do nothing more.
			return false;
		}

		/**
		 * Run additional tasks before we export a given file.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param int $attachment_id The attachment ID.
		 */
		do_action( 'efml_export_before', $attachment_id );

		// log this event.
		Log::get_instance()->create( __( 'Check which service could be used to export the given file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

		// get the term IDs of all export sources.
		$export_source_terms_list = $this->get_export_terms();

		// move the main export source on last place in array to assign the resulting file to this external source.
		$export_source_terms        = array();
		$main_export_source_term_id = absint( get_option( 'eml_export_main_source' ) );
		foreach ( $export_source_terms_list as $term_id ) {
			// bail if this is the main export source.
			if ( $main_export_source_term_id === $term_id ) {
				continue;
			}
			$export_source_terms[] = $term_id;
		}
		// add the main export source.
		if ( $main_export_source_term_id > 0 ) {
			$export_source_terms[] = $main_export_source_term_id;
		}

		// create result marker.
		$successfully_exported = false;

		// export the file to each external source.
		foreach ( $export_source_terms as $term_id ) {
			// get the term name for logging.
			$term_name = get_term_field( 'name', $term_id, Taxonomy::get_instance()->get_name() );

			// bail if term name could not be loaded.
			if ( ! is_string( $term_name ) ) {
				continue;
			}

			// log this event.
			/* translators: %1$s will be replaced by the service title. */
			Log::get_instance()->create( sprintf( __( 'Checking external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $external_file_obj->get_url( true ), 'info', 2 );

			// get the listings object (which also checks if the term exists).
			$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
				continue;
			}

			/**
			 * Run additional tasks before we export a given file to a specific external source.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param Service_Base $listing_obj The used service object.
			 * @param int $term_id The used term.
			 * @param int $attachment_id The attachment ID.
			 * @param string $file The absolute path to the file.
			 */
			do_action( 'efml_export_before_on_service', $listing_obj, $term_id, $attachment_id, $file );

			// get the export object.
			$export_obj = $listing_obj->get_export_object();

			// bail if export object could not be loaded.
			if ( ! $export_obj instanceof Export_Base ) {
				continue;
			}

			// get the configured URL, if enabled on export object.
			if ( $export_obj->is_url_required() ) {
				$base_url = get_term_meta( $term_id, 'efml_export_url', true );

				// bail if no base URL is given.
				if ( empty( $base_url ) ) {
					continue;
				}
			}

			// get the base URL.
			$term_url = get_term_meta( $term_id, 'path', true );

			// create the import path of this file.
			$import_path = trailingslashit( $term_url ) . basename( $file );

			// get credentials.
			$credentials = Taxonomy::get_instance()->get_entry( $term_id );

			// log this event.
			/* translators: %1$s will be replaced by the service title. */
			Log::get_instance()->create( sprintf( __( 'Exporting file to external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $external_file_obj->get_url( true ), 'info', 2 );

			// export the file via this listing object and get its external public URL.
			$url = $export_obj->export_file( $attachment_id, $import_path, $credentials );
			if ( ! is_string( $url ) ) {
				// log this event.
				/* translators: %1$s will be replaced by the service title. */
				Log::get_instance()->create( sprintf( __( 'File could not be exported to external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $external_file_obj->get_url( true ), 'error' );

				// do nothing more.
				continue;
			}

			// mark export as successfully.
			$successfully_exported = true;

			// get fields.
			$fields = array();
			if ( ! empty( $credentials['fields'] ) ) {
				$fields = $credentials['fields'];
				if ( ! is_array( $fields ) ) { // @phpstan-ignore function.impossibleType
					$fields = array();
				}
			}

			// mark this attachment as one of our own plugin through setting the URL.
			$external_file_obj->set_url( $url );

			// set the title.
			$external_file_obj->set_title( basename( $file ) );

			// set availability-status (true is for 'is available', false if it is not).
			$external_file_obj->set_availability( true );

			// mark if this file is an external file locally saved.
			$external_file_obj->set_is_local_saved( false );

			// save the credentials on the object, if set.
			$external_file_obj->set_fields( $fields );

			// set date of import (this is not the attachment datetime).
			$external_file_obj->set_date();

			// add file to local proxy, if necessary.
			$external_file_obj->add_to_proxy();

			// set the used service.
			$external_file_obj->set_service_name( $listing_obj->get_name() );

			// assign the file to this term.
			wp_set_object_terms( $external_file_obj->get_id(), $term_id, Taxonomy::get_instance()->get_name() );

			// mark as exported file.
			update_post_meta( $external_file_obj->get_id(), 'eml_exported_file', time() );

			// get the metadata.
			$meta_data = wp_get_attachment_metadata( $external_file_obj->get_id() );
			if ( ! is_array( $meta_data ) ) {
				$meta_data = array();
			}

			// delete local files.
			$this->update_attachment_metadata( $meta_data, $external_file_obj->get_id() );

			// add this term to the list of export sources of this file.
			$export_sources = get_post_meta( $external_file_obj->get_id(), 'efml_export_sources', true );
			if ( ! is_array( $export_sources ) ) {
				$export_sources = array();
			}
			$export_sources[] = $term_id;
			update_post_meta( $external_file_obj->get_id(), 'efml_export_sources', $export_sources );

			// log this event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'File exported to external source %1$s', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $url, 'success' );
		}

		// return the result.
		return $successfully_exported;
	}

	/**
	 * Delete an exported attachment before it is deleted in media library.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function delete_exported_file( int $attachment_id ): void {
		// bail if sync is running.
		if ( $this->is_sync_running() ) {
			return;
		}

		// get external file object for the given attachment ID.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// get the file name.
		$file = get_attached_file( $attachment_id, true );

		// bail if file name could not be loaded.
		if ( ! is_string( $file ) ) {
			return;
		}

		// create successfully marker.
		$successfully_deleted = true;

		// check each term.
		foreach ( $this->get_export_terms() as $term_id ) {
			// get the listing object by this name.
			$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
				continue;
			}

			// get the base URL for this term.
			$term_url = get_term_meta( $term_id, 'path', true );

			// create the external public URL of this file.
			$url = trailingslashit( $term_url ) . basename( $file );

			// get credentials.
			$credentials = Taxonomy::get_instance()->get_entry( $term_id );

			// get the export object.
			$export_obj = $listing_obj->get_export_object();

			// delete the exported file.
			if ( ! $export_obj->delete_exported_file( $url, $credentials, $attachment_id ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Exported file could not be deleted.', 'external-files-in-media-library' ), $url, 'error' );

				// mark as not successfully.
				$successfully_deleted = false;
				continue;
			}

			// log this event.
			Log::get_instance()->create( __( 'Exported file has been deleted.', 'external-files-in-media-library' ), $url, 'info', 2 );
		}

		// cleanup after a successfully deletion.
		if ( $successfully_deleted ) {
			$this->cleanup_exported_file( $attachment_id );
		}
	}

	/**
	 * Add filter during sync to prevent import of exported external files.
	 *
	 * @param string              $url The used URL.
	 * @param array<string,mixed> $term_data The term data.
	 * @param int                 $term_id The used term id for sync.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_sync_filter( string $url, array $term_data, int $term_id ): void {
		// set marker for running sync to prevent export of synced files.
		$this->sync_running = true;

		// get URL for export files for the used term.
		$export_url = (string) get_term_meta( $term_id, 'efml_export_url', true );

		// bail if no URL is set.
		if ( empty( $export_url ) ) {
			return;
		}

		// use hooks.
		add_filter( 'efml_external_file_infos', array( $this, 'prevent_sync_of_exported_file' ), 20, 2 );
	}

	/**
	 * Mark sync as running if synced files are deleted.
	 *
	 * @return void
	 */
	public function add_sync_filter_during_deletion(): void {
		// set marker for running sync to prevent export of synced files.
		$this->sync_running = true;
	}

	/**
	 * Prevent import of exported files.
	 *
	 * @param array<string,mixed> $results The result (should be true to prevent the import).
	 * @param string              $url The URL to import.
	 *
	 * @return array<string,mixed>
	 */
	public function prevent_sync_of_exported_file( array $results, string $url ): array {
		// get the external files object for this URL.
		$external_file_obj = Files::get_instance()->get_file_by_title( basename( $url ) );

		// bail if external file is not valid.
		if ( ! $external_file_obj instanceof File || ! $external_file_obj->is_valid() ) {
			return $results;
		}

		// bail if this is not an exported file.
		if ( 0 === absint( get_post_meta( $external_file_obj->get_id(), 'eml_exported_file', true ) ) ) {
			return $results;
		}

		// return empty array as this is an exported file.
		return array();
	}

	/**
	 * Return whether sync is running.
	 *
	 * @return bool
	 */
	private function is_sync_running(): bool {
		return $this->sync_running;
	}

	/**
	 * Remove the delete action from settings if files are synced.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Term              $term The requested WP_Term object.
	 *
	 * @return array<string,string>
	 */
	public function remove_listing_delete_action( array $actions, WP_Term $term ): array {
		// bail if delete option is not set.
		if ( ! isset( $actions['delete'] ) ) {
			return $actions;
		}

		// bail if no files are synced.
		if ( 0 === count( $this->get_exported_files_by_term( $term->term_id ) ) ) {
			return $actions;
		}

		// delete the action.
		unset( $actions['delete'] );

		// return the list of actions.
		return $actions;
	}

	/**
	 * Delete exported files via request.
	 *
	 * @return void
	 */
	public function delete_exported_file_via_request(): void {
		// check referer.
		check_admin_referer( 'efml-exported-synced-files', 'nonce' );

		// get the term ID from request.
		$term_id = absint( filter_input( INPUT_GET, 'term', FILTER_SANITIZE_NUMBER_INT ) );

		// get referer.
		$referer = wp_get_referer();

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// bail if no term is given.
		if ( 0 === $term_id ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// get all files which are assigned to this term.
		$files = $this->get_exported_files_by_term( $term_id );

		// bail if no files could be found.
		if ( empty( $files ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// remove the prevent-deletion for this moment.
		remove_filter( 'pre_delete_attachment', array( $this, 'prevent_deletion' ) );

		// get the listing object by this name.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// loop through the files and delete them.
		foreach ( $files as $post_id ) {
			// delete the exported file.
			$this->delete_exported_file( $post_id );

			// delete the file in media library.
			wp_delete_attachment( $post_id, true );
		}

		// return the user.
		wp_safe_redirect( $referer );
	}

	/**
	 * Return the Save_Base object by given type name, optionally also filtered.
	 *
	 * @param string $type The given type name.
	 *
	 * @return object|false
	 */
	private function get_service_object_by_type( string $type ): object|false {
		// get the listing object by this name.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		/**
		 * Filter the detected service object for export views.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param object|false $listing_obj The object.
		 */
		return apply_filters( 'efml_export_object', $listing_obj );
	}

	/**
	 * Show description for synchronisation.
	 *
	 * @return void
	 */
	public function export_description(): void {
		/* translators: %1$s will be replaced by a URL. */
		echo '<p><strong>' . esc_html__( 'Automatically upload local files uploaded to your media library to an external source.', 'external-files-in-media-library' ) . '</strong> ' . esc_html__( ' The files are then handled as external files according to the settings and take up less local storage space.', 'external-files-in-media-library' ) . ' ' . wp_kses_post( sprintf( __( 'Choose the target for these files from <a href="%1$s">in your external sources</a>.', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url() ) ) . ' ' . wp_kses_post( sprintf( __( 'Set permissions to use these options <a href="%1$s">here</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_permissions' ) ) ) . '</p>';
	}

	/**
	 * Delete the local files of exported files for images.
	 *
	 * @param array<string,mixed> $data The meta-data.
	 * @param int                 $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>
	 */
	public function update_attachment_metadata( array $data, int $attachment_id ): array {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if given file is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $data;
		}

		// bail if this is not an exported file.
		if ( 0 === absint( get_post_meta( $external_file_obj->get_id(), 'eml_exported_file', true ) ) ) {
			return $data;
		}

		// bail if the deletion is prevented.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			return $data;
		}

		// get the metadata.
		$meta_data = wp_get_attachment_metadata( $attachment_id );

		// bail if meta-data could not be loaded.
		if ( ! is_array( $meta_data ) ) {
			$meta_data = array();
		}

		// get sizes.
		$sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		// bail if sizes is not an array.
		if ( ! is_array( $sizes ) ) {
			$sizes = array();
		}

		// get attached file.
		$file = get_attached_file( $attachment_id );

		// bail if file is not a string.
		if ( ! is_string( $file ) ) {
			return $data;
		}

		// log this event.
		Log::get_instance()->create( __( 'Cleanup the attachment files.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), $file );

		// get all files for this attachment and delete them in local project.
		wp_delete_attachment_files( $attachment_id, $meta_data, $sizes, $file );

		// get WP_Filesystem.
		$wp_filesystem = Helper::get_wp_filesystem();

		// get the upload dir.
		$uploadpath = wp_get_upload_dir();

		// get the absolute path to the original file.
		$path = trailingslashit( $uploadpath['basedir'] ) . $file;

		// bail if path does not exist.
		if ( ! $wp_filesystem->exists( $path ) ) {
			return $data;
		}

		// delete the original.
		$wp_filesystem->delete( $path );

		// return the metadata.
		return $data;
	}

	/**
	 * Add info about export state if the given file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_info_about_export( File $external_file_obj ): void {
		// bail if capability is not set.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// bail if given file is not exported.
		if ( 0 === absint( get_post_meta( $external_file_obj->get_id(), 'eml_exported_file', true ) ) ) {
			return;
		}

		// get list of terms, where this file is exported.
		$terms = get_post_meta( $external_file_obj->get_id(), 'efml_export_sources', true );
		if ( ! is_array( $terms ) ) {
			return;
		}

		// collect them as list.
		$list = array();
		foreach ( $terms as $term_id ) {
			// get the term.
			$term = get_term( $term_id, Taxonomy::get_instance()->get_name() );

			// bail if term could not be loaded.
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// add it to the list.
			$list[] = '<em>' . $term->name . '</em>';
		}

		?>
		<li>
			<span id="efml_exported"><span class="dashicons dashicons-database-export"></span> <?php echo esc_html__( 'Exported to:', 'external-files-in-media-library' ) . ' ' . wp_kses_post( implode( ', ', $list ) ); ?></span>
		</li>
		<?php
	}

	/**
	 * Delete the main export source setting if its term is deleted.
	 *
	 * @param int    $term_id The term ID.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return void
	 */
	public function on_delete_archive_term( int $term_id, string $taxonomy ): void {
		// bail if this is not our archive taxonomy.
		if ( Taxonomy::get_instance()->get_name() !== $taxonomy ) {
			return;
		}

		// get the main export source setting.
		$main_export_source_term_id = absint( get_option( 'eml_export_main_source' ) );

		// if they match, delete the main export source.
		if ( $term_id === $main_export_source_term_id ) {
			update_option( 'eml_export_main_source', 0 );
		}
	}

	/**
	 * Show hint to export files on /wp-admin/media-new.php.
	 *
	 * @return void
	 */
	public function show_export_hint_on_file_add_page(): void {
		// show simple messages if user has not the capability for settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( 1 === absint( get_option( 'eml_export' ) ) ) {
				// get the external sources with enabled export option.
				$external_sources = $this->get_external_sources_as_name_list();

				// bail if no external sources are set.
				if ( ! empty( $external_sources ) ) {
					/* translators: %1$s will be replaced by a URL. */
					echo '<div class="efml_export-hint"><p><strong>' . wp_kses_post( sprintf( _n( 'New media files will be exported to the external source %1$s.', 'New media files will be exported to external sources %1$s.', count( $external_sources ), 'external-files-in-media-library' ), '<em>' . implode( ', ', $external_sources ) ) . '</em>' ) . '</strong></p></div>';
					return;
				}
				return;
			}
			return;
		}

		// if export is already enabled, show where the files will be exported.
		if ( 1 === absint( get_option( 'eml_export' ) ) ) {
			// get the external sources with enabled export option.
			$external_sources = $this->get_external_sources_as_name_list();

			// bail if no external sources are set.
			if ( empty( $external_sources ) ) {
				/* translators: %1$s will be replaced by a URL. */
				echo '<div class="efml_export-hint"><p><strong>' . wp_kses_post( __( 'You have enabled the export for new media files but not configured an external source for it.', 'external-files-in-media-library' ) ) . '</strong> ' . wp_kses_post( sprintf( __( 'Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url() ) ) . '</p></div>';
				return;
			}

			// show the hint with the list.
			/* translators: %1$s will be replaced by a URL. */
			echo '<div class="efml_export-hint"><p><strong>' . wp_kses_post( sprintf( _n( 'New media files will be exported to the external source %1$s.', 'New media files will be exported to external sources %1$s.', count( $external_sources ), 'external-files-in-media-library' ), '<em>' . implode( ', ', $external_sources ) ) . '</em>' ) . '</strong> ' . wp_kses_post( sprintf( __( 'Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url() ) ) . '</p></div>';
			return;
		}

		// show the hint.
		/* translators: %1$s will be replaced by a URL. */
		echo '<div class="efml_export-hint"><p>' . wp_kses_post( sprintf( __( '<strong>Export your media files to external servers to save storage space.</strong> Enable this option <a href="%1$s">here</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) ) ) . '</p></div>';
	}

	/**
	 * Return a list of names of the external sources with enabled export option.
	 *
	 * @return array<int,string>
	 */
	private function get_external_sources_as_name_list(): array {
		// bail if this is not in the backend.
		if ( ! is_admin() ) {
			return array();
		}

		// bail if no terms are set.
		if ( 0 === absint( get_option( 'efml_directory_listing_used' ) ) ) {
			return array();
		}

		// get all terms.
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
			)
		);

		// bail if no terms could be loaded.
		if ( ! is_array( $terms ) ) {
			return array();
		}

		// create the list.
		$external_sources = array();

		// add all terms to the list with enabled export.
		foreach ( $terms as $term ) {
			// bail if no export is enabled.
			if ( 0 === absint( get_term_meta( $term->term_id, 'efml_export', true ) ) ) {
				continue;
			}

			// add this source.
			$external_sources[ $term->term_id ] = $term->name;
		}

		// return the resulting list.
		return $external_sources;
	}

	/**
	 * Add info about proxy cache usage in info dialog for single external file.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param File                $external_file_obj The external file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_export_state_in_info_dialog( array $dialog, File $external_file_obj ): array {
		// add the export state of this file.
		$dialog['texts'][] = '<p><strong>' . __( 'Exported', 'external-files-in-media-library' ) . ':</strong> ' . ( absint( get_post_meta( $external_file_obj->get_id(), 'eml_exported_file', true ) ) > 0 ? __( 'File is exported.', 'external-files-in-media-library' ) : __( 'File is not exported.', 'external-files-in-media-library' ) ) . '</p>';

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Return a unique filename for an exported file.
	 *
	 * We check if the filename has been used for another file.
	 * If so, we add a suffix to the filename as WordPress it does with local hostet files.
	 *
	 * Difference to @wp_unique_filename(): we check the DB-entry, not the filesystem, as this is the place where
	 * exported and local files are managed.
	 *
	 * Does not run if export is not enabled OR no external sources are configured for export.
	 *
	 * @param string $filename The filename WordPress would use.
	 * @param string $ext The extension of the file (incl. ".").
	 * @param string $dir The absolute path to the directory where the file would be saved locally.
	 *
	 * @return string
	 */
	public function check_for_exported_filenames( string $filename, string $ext, string $dir ): string {
		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return $filename;
		}

		// get the external sources with enabled export option.
		$external_sources = $this->get_external_sources_as_name_list();

		// bail if no export is enabled.
		if ( empty( $external_sources ) ) {
			return $filename;
		}

		// get the upload dir.
		$upload_dir = wp_upload_dir();

		// build the relative path.
		$dir = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $dir ) . DIRECTORY_SEPARATOR;

		// prepare marker to check for unique filename.
		$having_unique_filename = false;

		// loop until we found a unique filename.
		$i = 0;
		while ( ! $having_unique_filename ) {
			// generate a file name to check.
			$filename_to_check = $filename;
			if ( $i > 0 ) {
				$filename_to_check = (string) str_replace( $ext, '-' . $i . $ext, $filename );
			}

			// search for any files in DB with this name.
			$query   = array(
				'post_type'   => 'attachment',
				'post_status' => array( 'inherit', 'trash' ),
				'meta_query'  => array(
					'relation' => 'AND',
					array(
						'key'   => '_wp_attached_file',
						'value' => $dir . $filename_to_check,
					),
				),
			);
			$results = new WP_Query( $query );

			// bail on no results.
			if ( 0 === $results->found_posts ) {
				// mark that we have a unique filename.
				$having_unique_filename = true;
				$filename               = $filename_to_check;
				continue;
			}

			// update counter.
			++$i;
		}

		// return the resulting filename.
		return $filename;
	}

	/**
	 * Change media row actions for URL-files: add export option for local files.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Post              $post The Post.
	 *
	 * @return array<string,string>
	 */
	public function change_media_row_actions( array $actions, WP_Post $post ): array {
		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $actions;
		}

		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return $actions;
		}

		// bail if option is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			return $actions;
		}

		// bail if file is already an external file.
		if ( absint( get_post_meta( $post->ID, 'eml_exported_file', true ) ) > 0 ) {
			return $actions;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if file is already an external file.
		if ( $external_file_obj->is_valid() ) {
			return $actions;
		}

		// collect the external sources.
		$external_sources = $this->get_external_sources_as_name_list();

		// bail if no external source is enabled for export.
		if ( empty( $external_sources ) ) {
			return $actions;
		}

		// create URL to export this file.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_file',
				'post'   => $post->ID,
				'nonce'  => wp_create_nonce( 'efml-export-file' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Export media file', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Do you really want to export this file?', 'external-files-in-media-library' ) . '</strong></p>',
				/* translators: %1$s will be replaced by a URL. */
				'<p>' . sprintf( __( 'The file will be exported to the external source %1$s.', 'external-files-in-media-library' ), '<em>' . end( $external_sources ) . '</em>' ) . '</p>',
				'<p>' . __( 'It will be handled as external file after the export. It will not be saved in your local hosting.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add the option.
		$actions['eml-export-file'] = '<a href="' . esc_url( $url ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . __( 'Export file', 'external-files-in-media-library' ) . '</a>';

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * Export single local file via request as external file.
	 *
	 * @return void
	 */
	public function export_file_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-export-file', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// bail if option is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// get the post ID.
		$attachment_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no ID is given.
		if ( 0 === $attachment_id ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// log this event.
		Log::get_instance()->create( __( 'Checking if local file should be exported by request.', 'external-files-in-media-library' ), '', 'info', 2 );

		// export the file.
		$result = $this->export_file( $attachment_id );

		// trigger hint depending on result.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_file_export' );
		if ( $result ) {
			$transient_obj->set_message( '<strong>' . __( 'The file has been exported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'It is now stored in the external source and used from there.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
		} else {
			if ( current_user_can( 'manage_options' ) ) {
				/* translators: %1$s will be replaced by a URL. */
				$transient_obj->set_message( '<strong>' . __( 'The file could not be exported.', 'external-files-in-media-library' ) . '</strong> ' . sprintf( __( 'Check <a href="%1$s">the log</a> to see what happened.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_logs' ) ) );
			} else {
				$transient_obj->set_message( '<strong>' . __( 'The file could not be exported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'Talk to your project administration about this.', 'external-files-in-media-library' ) );
			}
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( $referer );
	}

	/**
	 * Add bulk option to export files to external source.
	 *
	 * @param array<string,string> $actions List of actions.
	 *
	 * @return array<string,string>
	 */
	public function add_bulk_action( array $actions ): array {
		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return $actions;
		}

		// bail if option is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			return $actions;
		}

		// collect the external sources.
		$external_sources = $this->get_external_sources_as_name_list();

		// bail if no external source is enabled for export.
		if ( empty( $external_sources ) ) {
			return $actions;
		}

		// add our bulk action.
		$actions['efml-export'] = __( 'Export to external source', 'external-files-in-media-library' );

		// return resulting list of actions.
		return $actions;
	}

	/**
	 * If our bulk action is run, export each marked file if it is not already exported.
	 *
	 * @param string         $sendback The return value.
	 * @param string         $doaction The action used.
	 * @param array<int,int> $items The items to take action.
	 *
	 * @return string
	 */
	public function run_bulk_action( string $sendback, string $doaction, array $items ): string {
		// bail if action is not ours.
		if ( 'efml-export' !== $doaction ) {
			return $sendback;
		}

		// bail if option is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			return $sendback;
		}

		// bail if item list is empty.
		if ( empty( $items ) ) {
			return $sendback;
		}

		// log this event.
		Log::get_instance()->create( __( 'Checking if file should be exported by bulk request.', 'external-files-in-media-library' ), '', 'info', 2 );

		// set marker for successfully export.
		$successfully_exported = false;

		// check the files and change its state.
		foreach ( $items as $attachment_id ) {
			// bail if file is already exported.
			if ( 1 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
				continue;
			}

			// export the file.
			$result = $this->export_file( $attachment_id );

			// if export was successfully, save this state.
			if ( $result ) {
				$successfully_exported = true;
			}
		}

		// trigger hint depending on result.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'eml_files_export' );
		if ( $successfully_exported ) {
			$transient_obj->set_message( '<strong>' . __( 'The files have been exported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'They are now stored in the external source and used from there.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
		} else {
			if ( current_user_can( 'manage_options' ) ) {
				/* translators: %1$s will be replaced by a URL. */
				$transient_obj->set_message( '<strong>' . __( 'The files could not be exported.', 'external-files-in-media-library' ) . '</strong> ' . sprintf( __( 'Check <a href="%1$s">the log</a> to see what happened.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_logs' ) ) );
			} else {
				$transient_obj->set_message( '<strong>' . __( 'The files could not be exported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'Talk to your project administration about this.', 'external-files-in-media-library' ) );
			}
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// return the given value.
		return $sendback;
	}

	/**
	 * Delete an exported external file on external source if it is imported as real media file.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function delete_exported_file_during_import( File $external_file_obj ): void {
		$this->delete_exported_file( $external_file_obj->get_id() );
	}

	/**
	 * Prevent usage of export functions during hosting switch to local.
	 *
	 * @return void
	 */
	public function prevent_export_checks_on_local_switch(): void {
		remove_action( 'add_attachment', array( $this, 'export_file_by_upload' ) );
	}

	/**
	 * Cleanup exported files after switching them to local hosting.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function cleanup_exported_file( int $attachment_id ): void {
		delete_post_meta( $attachment_id, 'eml_exported_file' );
		delete_post_meta( $attachment_id, 'efml_export_sources' );
	}

	/**
	 * Add option to export the settings for this specific external source.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Term              $term The requested WP_Term object.
	 *
	 * @return array<string,string>
	 */
	public function add_export_in_directory_listing( array $actions, WP_Term $term ): array {
		// bail if user is not allowed to export this.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		// create the import URL.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_external_source',
				'nonce'  => wp_create_nonce( 'eml-export-external-source' ),
				'term'   => $term->term_id,
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog.
		$dialog = array(
			'className' => 'efml',
			/* translators: %1$s will be replaced by the file name. */
			'title'     => sprintf( __( 'Export %1$s as JSON', 'external-files-in-media-library' ), $term->name ),
			'texts'     => array(
				'<p>' . __( 'You will receive a JSON file that you can use to import this external source into another project which is using the plugin "External Files in Media Library".', 'external-files-in-media-library' ) . '</p>',
				'<p><strong>' . __( 'The file may also contain access data. Keep it safe.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, export the file', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);

		// add the action.
		$actions['export'] = '<a href="' . esc_url( $url ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . esc_html__( 'Export', 'external-files-in-media-library' ) . '</a>';

		// return the list of actions.
		return $actions;
	}
}
