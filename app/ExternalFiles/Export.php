<?php
/**
 * File to handle the export of uploaded files to supporting external services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listing_Base;
use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Checkbox;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
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
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_files' ), 10, 3 );
		add_action( 'efml_before_sync', array( $this, 'add_sync_filter' ), 10, 3 );

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
		if( ! $page instanceof Page ) {
			return;
		}

		// get main settings tab.
		$tab = $page->get_tab( 'eml_general' );

		// bail if tab could not be found.
		if( ! $tab instanceof Tab ) {
			return;
		}

		// add export section.
		$section = $tab->add_section( 'settings_section_export', 30 );
		$section->set_title( __( 'Export of files', 'external-files-in-media-library' ) );

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

		// add the sync columns.
		$columns['export_files'] = __( 'Export', 'external-files-in-media-library' );

		// return the resulting columns.
		return $columns;
	}

	/**
	 * Add the content to mark an external source as possible export target.
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

		// get the type name.
		$type = get_term_meta( $term_id, 'type', true );

		// get the listing object by this name.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// bail if no object could be found.
		if ( ! $listing_obj ) {
			return $content;
		}

		// bail if object does not support export of files.
		if ( ! $listing_obj->can_export_files() ) {
			return __( 'Export not supported', 'external-files-in-media-library' );
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
		$form .= '<div><label for="url">' . __( 'URL:', 'external-files-in-media-library' ) . '</label><input type="url" name="url" id="url" value="' . esc_url( $url ) . '"><br>' . __( 'This must be the URL where files, uploaded to this external source, are available.', 'external-files-in-media-library' ) . '</div>';
		$form .= '<input type="hidden" name="term_id" value="' . $term_id . '">';

		// create dialog for sync config.
		$dialog = array(
			'className' => 'efml-export-config',
			/* translators: %1$s will be replaced by a name. */
			'title'     => sprintf( __( 'Export settings for %1$s', 'external-files-in-media-library' ), $term->name ),
			'texts'     => array(
				'<p><strong>' . __( 'Configure this external source as the destination for files newly added to the media library.', 'external-files-in-media-library' ) . '</strong> ' . __( 'This allows you to use this external directory to store all your files there.', 'external-files-in-media-library' ) . '</p>',
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

		// create dialog for failures.
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

		$this->set_state_for_term( $term_id, $enabled );

		// save URL.
		update_term_meta( $term_id, 'efml_export_url', $url );

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
		$file = get_attached_file( $attachment_id, true );

		// bail if file name could not be loaded.
		if( ! is_string( $file ) ) {
			return;
		}

		// check each term.
		foreach ( $this->get_export_terms() as $term ) {
			// get the type name.
			$type = get_term_meta( $term->term_id, 'type', true );

			// get the listing object by this name.
			$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// bail if object does not support export of files.
			if ( ! $listing_obj->can_export_files() ) {
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
			if ( ! $listing_obj->export_file( $attachment_id, $import_path, $credentials ) ) {
				continue;
			}

			// create the URL.
			$url = $base_url . basename( $file );

			// mark this attachment as one of our own plugin through setting the URL.
			$external_file_obj->set_url( $url );

			// set the title.
			$external_file_obj->set_title( basename( $file ) );

			// set availability-status (true is for 'is available', false if it is not).
			$external_file_obj->set_availability( true );

			// mark if this file is an external file locally saved.
			$external_file_obj->set_is_local_saved( false );

			// save the credentials on the object, if set.
			$external_file_obj->set_login( isset( $credentials['login'] ) ? $credentials['login'] : '' );
			$external_file_obj->set_password( isset( $credentials['password'] ) ? $credentials['password'] : '' );
			$external_file_obj->set_api_key( isset( $credentials['api_key'] ) ? $credentials['api_key'] : '' );

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
		$file = get_attached_file( $attachment_id, true );

		// bail if file name could not be loaded.
		if( ! is_string( $file ) ) {
			return;
		}

		// check each term.
		foreach ( $this->get_export_terms() as $term ) {
			// get the type name.
			$type = get_term_meta( $term->term_id, 'type', true );

			// get the listing object by this name.
			$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Directory_Listing_Base ) {
				continue;
			}

			// bail if object does not support export of files.
			if ( ! $listing_obj->can_export_files() ) {
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
	 * @param string $url The used URL.
	 * @param array<string,mixed>  $term_data The term data.
	 * @param int    $term_id The used term id for sync.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_sync_filter( string $url, array $term_data, int $term_id ): void {
		// get URL for export files for the used term.
		$export_url = (string) get_term_meta( $term_id, 'efml_export_url', true );

		// bail if no URL is set.
		if ( empty( $export_url ) ) {
			return;
		}

		// set marker for running sync to prevent export of synced files.
		$this->sync_running = true;

		// use hooks.
		add_filter( 'eml_external_file_infos', array( $this, 'prevent_sync_of_exported_file' ), 20, 2 );
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
}
