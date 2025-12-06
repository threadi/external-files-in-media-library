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
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Service_Base;
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
	private function __construct() {
	}

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {
	}

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
		add_action( 'init', array( $this, 'init_export' ), 20 );

		// bail if not enabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_columns' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_options' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_files' ), 10, 3 );
		add_action( 'efml_before_sync', array( $this, 'add_sync_filter' ), 10, 3 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'remove_listing_delete_action' ), 10, 2 );

		// add admin actions.
		add_action( 'admin_action_efml_delete_exported_files', array( $this, 'delete_exported_file_via_request' ) );

		// use AJAX.
		add_action( 'wp_ajax_efml_get_export_config_dialog', array( $this, 'get_export_config_dialog' ) );
		add_action( 'wp_ajax_efml_save_export_config', array( $this, 'save_export_config' ) );
		add_action( 'wp_ajax_efml_change_export_state', array( $this, 'export_state_change_via_ajax' ) );

		// use hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ) );
		add_action( 'add_attachment', array( $this, 'export_file' ) );
		add_action( 'delete_attachment', array( $this, 'delete_exported_file' ) );
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

		// create URL.
		$url = add_query_arg(
			array(
				'taxonomy'  => 'edlfw_archive',
				'post_type' => 'attachment',
			),
			get_admin_url() . 'edit-tags.php'
		);

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable export', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by a URL. */
		$field->set_description( sprintf( __( 'If enabled you have to configure one or more of <a href="%1$s">your external sources</a> as export target. Every new file in media library will be exported to the configured source and used as external file.', 'external-files-in-media-library' ), $url ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );
	}

	/**
	 * Add columns to handle synchronization.
	 *
	 * @param array<string,string> $columns The columns.
	 *
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		// bail if user has not the capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $columns;
		}

		// add the export columns.
		$columns['export_files'] = __( 'Exported files', 'external-files-in-media-library' );
		$columns['export'] = __( 'Export', 'external-files-in-media-library' );

		// return the resulting columns.
		return $columns;
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
		// bail if this is not the "synchronization" column.
		if ( 'export' !== $column_name ) {
			return $content;
		}

		// get the listings object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->can_export_files() ) {
			// create dialog for sync now.
			$dialog = array(
				'title'   => __( 'Export not supported', 'external-files-in-media-library' ),
				'texts'   => array(
					/* translators: %1$s will be replaced by a title. */
					'<p><strong>' . sprintf( __( 'Export to %1$s is currently not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>',
					/* translators: %1$s will be replaced by a URL. */
					'<p>' . sprintf( __( 'If you have any questions, please feel free to ask them <a href="%1$s" target="_blank">in our support forum (opens new window)</a>.', 'external-files-in-media-library' ), Helper::get_plugin_support_url() ) . '</p>',
				),
				'buttons' => array(
					array(
						'action'  => 'closeDialog();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
				),
			);
			return '<a href="#" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" title="' . esc_attr__( 'Not supported', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-editor-help"></span></a>';
		}

		// get actual state.
		$enabled = absint( get_term_meta( $term_id, 'efml_export', true ) );

		// get URL setting.
		$url = get_term_meta( $term_id, 'efml_export_url', true );

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
		if ( 'export_files' !== $column_name ) {
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
			'title'   => __( 'Delete exported files?', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Do you really want to delete this exported files?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The files will be deleted in your media library AND the external source.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'If the files are used on the website, they are no longer visible and usable on the website.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
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

		// show count and link it to the media library and option to delete all of them.
		return '<a href="' . esc_url( $url ) . '">' . absint( count( $files ) ) . '</a> | <a href="' . esc_url( $url_delete ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '">' . esc_html__( 'Delete', 'external-files-in-media-library' ) . '</a>';
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
			),
			'tax_query'      => array(
				array(
					'taxonomy' => Taxonomy::get_instance()->get_name(),
					'field'    => 'term_id',
					'terms'    => $term_id,
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
			plugins_url( '/admin/export.js', EFML_PLUGIN ),
			array( 'jquery' ),
			(string) filemtime( Helper::get_plugin_dir() . '/admin/export.js' ),
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
			'title'   => __( 'Configuration could not be loaded', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'The export configuration for this target could not be loaded.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
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

		// get actual state.
		$enabled = absint( get_term_meta( $term_id, 'efml_export', true ) );

		// get URL.
		$url = get_term_meta( $term_id, 'efml_export_url', true );

		// create the form.
		$form  = '<div><label for="enable">' . __( 'Enable:', 'external-files-in-media-library' ) . '</label><input type="checkbox" name="enable" id="enable" value="1"' . ( $enabled > 0 ? ' checked="checked"' : '' ) . '></div>';
		$form .= '<div><label for="url">' . __( 'URL:', 'external-files-in-media-library' ) . '</label><input type="url" placeholder="https://example.com" name="url" id="url" value="' . esc_url( $url ) . '">' . __( 'This must be the URL where files uploaded to this external source are available.', 'external-files-in-media-library' ) . '</div>';
		$form .= '<input type="hidden" name="term_id" value="' . $term_id . '">';

		// create dialog for sync config.
		$dialog = array(
			'className' => 'efml-export-config',
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
			'title'   => __( 'Configuration could not be saved', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'The export configuration for this target could not be saved.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
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

		// get the state.
		$enabled = absint( filter_input( INPUT_POST, 'enable', FILTER_SANITIZE_NUMBER_INT ) );

		// get the URL.
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
		if( is_wp_error( $response ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given URL could not be reached! Error:', 'external-files-in-media-library' ). '<em>' . wp_json_encode( $response ). '</em>', $url, 'error' );

			// configure dialog and return it.
			$dialog['texts'][] = '<p>' . __( 'Given URL could not be reached!', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the HTTP status for this URL.
		$response_code = wp_remote_retrieve_response_code( $response );

		// check the HTTP status, should be 200 or 404.
		$result = in_array( $response_code, array( 200, 404 ), true );

		/**
		 * Filter the possible HTTP states for export URLs during export configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $result The check result.
		 * @param string $url The given URL.
		 */
		if( ! apply_filters( 'efml_export_configuration_url_state', $result, $url ) ) {
			// log this event.
			Log::get_instance()->create( __( 'Given URL is returning a not allowed HTTP state!', 'external-files-in-media-library' ), $url, 'info' );

			// configure dialog and return it.
			$dialog['texts'][] = '<p>' . __( 'Given URL is returning a not allowed HTTP state!', 'external-files-in-media-library' ) . '</p>';
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// save URL.
		update_term_meta( $term_id, 'efml_export_url', $url );

		// enable the export state for this term.
		$this->set_state_for_term( $term_id, $enabled );

		// create dialog.
		$dialog = array(
			'className' => 'efml-export-config',
			'title'     => __( 'Configuration saved', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'The configuration has been saved.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

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
		$enabled = absint( filter_input( INPUT_POST, 'state', FILTER_SANITIZE_NUMBER_INT ) );

		// save the new state.
		$this->set_state_for_term( $term_id, $enabled );

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
		// save state.
		if ( 1 === $enabled ) {
			add_term_meta( $term_id, 'efml_export', time() );
		} else {
			delete_term_meta( $term_id, 'efml_export' );
		}
	}

	/**
	 * Return list of terms which are enabled for export.
	 *
	 * @return array<int,WP_Term>
	 */
	private function get_export_terms(): array {
		// get the export terms.
		$query = array(
			'taxonomy'     => Taxonomy::get_instance()->get_name(),
			'hide_empty'   => false,
			'count'        => false,
			'meta_key'     => 'efml_export',
			'meta_compare' => 'EXISTS',
		);
		$terms = new WP_Term_Query( $query );

		// bail on no results.
		if ( empty( $terms->terms ) ) {
			return array();
		}

		// create the list.
		$list = array();

		// add each WP_Term to the list.
		foreach ( $terms->terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			// add it to the list.
			$list[] = $term;
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Export a fresh uploaded and saved attachment on external hostings and configure the data for it.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function export_file( int $attachment_id ): void {
		// bail if sync is running.
		if ( $this->is_sync_running() ) {
			return;
		}

		// get external file object for the given attachment ID.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is already an external file.
		if ( $external_file_obj->is_valid() ) {
			return;
		}

		// get the file name.
		$file = wp_get_original_image_path( $attachment_id, true );

		// bail if file name could not be loaded.
		if ( ! is_string( $file ) ) {
			return;
		}

		// check each external source term.
		foreach ( $this->get_export_terms() as $term ) {
			// get the listings object.
			$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term->term_id, 'type', true ) );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->can_export_files() ) {
				continue;
			}

			// get the configured URL.
			$base_url = get_term_meta( $term->term_id, 'efml_export_url', true );

			// bail if no base URL is given.
			if ( empty( $base_url ) ) {
				continue;
			}

			// get the base URL.
			$term_url = get_term_meta( $term->term_id, 'path', true );

			// create the import path of this file.
			$import_path = trailingslashit( $term_url ) . basename( $file );

			// get credentials.
			$credentials = Taxonomy::get_instance()->get_entry( $term->term_id );

			// export the file via this listing object.
			$url = $listing_obj->export_file( $attachment_id, $import_path, $credentials );
			if ( ! is_string( $url ) ) {
				continue;
			}

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

			// add file to local cache, if necessary.
			$external_file_obj->add_to_cache();

			// assign the file to this term.
			wp_set_object_terms( $external_file_obj->get_id(), $term->term_id, Taxonomy::get_instance()->get_name() );

			// mark as exported file.
			update_post_meta( $external_file_obj->get_id(), 'eml_exported_file', time() );

			// log this event.
			/* translators: %1$s will be replaced by the title. */
			Log::get_instance()->create( sprintf( __( 'File exported to external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term->name . '</em>' ), $url, 'success' );
		}
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
		$file = wp_get_original_image_path( $attachment_id, true );

		// bail if file name could not be loaded.
		if ( ! is_string( $file ) ) {
			return;
		}

		// check each term.
		foreach ( $this->get_export_terms() as $term ) {
			// get the listing object by this name.
			$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term->term_id, 'type', true ) );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->can_export_files() ) {
				continue;
			}

			// get the base URL.
			$term_url = get_term_meta( $term->term_id, 'path', true );

			// create the URL of this file.
			$url = trailingslashit( $term_url ) . basename( $file );

			// get credentials.
			$credentials = Taxonomy::get_instance()->get_entry( $term->term_id );

			// delete the exported file.
			if ( ! $listing_obj->delete_exported_file( $url, $credentials ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Exported file could not be deleted.', 'external-files-in-media-library' ), $url, 'error' );
				continue;
			}

			// log this event.
			Log::get_instance()->create( __( 'Exported file has been deleted.', 'external-files-in-media-library' ), $url, 'info', 2 );
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
	 * Prevent import of exported files.
	 *
	 * @param array<string,mixed> $results The result (should be true to prevent the import).
	 * @param string              $url The URL to import.
	 *
	 * @return array<string,mixed>
	 */
	public function prevent_sync_of_exported_file( array $results, string $url ): array {
		// get the external files object for this URL.
		$external_files_obj = Files::get_instance()->get_file_by_title( basename( $url ) );

		// bail if external file is not valid.
		if ( ! $external_files_obj instanceof File || ! $external_files_obj->is_valid() ) {
			return $results;
		}

		// bail if this is not an exported file.
		if ( 0 === absint( get_post_meta( $external_files_obj->get_id(), 'eml_exported_file', true ) ) ) {
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
	 * @param array   $actions List of action.
	 * @param WP_Term $term The requested WP_Term object.
	 *
	 * @return array
	 */
	public function remove_listing_delete_action( array $actions, WP_Term $term ): array {
		// bail if delete option is not set.
		if ( ! isset( $actions['delete'] ) ) {
			return $actions;
		}

		// bail if no files are synced.
		if( 0 === count( $this->get_exported_files_by_term( $term->term_id ) ) ) {
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
		}

		// get all files which are assigned to this term.
		$files = $this->get_exported_files_by_term( $term_id );

		// bail if no files could be found.
		if ( empty( $files ) ) {
			wp_safe_redirect( $referer );
		}

		// remove the prevent-deletion for this moment.
		remove_filter( 'pre_delete_attachment', array( $this, 'prevent_deletion' ) );

		// get the type name.
		$type = get_term_meta( $term_id, 'type', true );

		// get the listing object by this name.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->can_export_files() ) {
			wp_safe_redirect( $referer );
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
		echo '<p>' . wp_kses_post( __( '<strong>Automatically upload local files uploaded to your media library to an external source.</strong> The files are then handled as external files according to the settings and take up less local storage space.', 'external-files-in-media-library' ) ) .'</p>';
	}
}
