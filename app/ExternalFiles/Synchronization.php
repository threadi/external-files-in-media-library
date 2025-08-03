<?php
/**
 * This file contains a controller-object to handle the synchronization of files.
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
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Fields\Select;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easySettingsForWordPress\Settings;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Schedules;
use ExternalFilesInMediaLibrary\Plugin\Schedules_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Controller for synchronization of files.
 */
class Synchronization {
	/**
	 * Instance of actual object.
	 *
	 * @var Synchronization|null
	 */
	private static ?Synchronization $instance = null;

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
	 * @return Synchronization
	 */
	public static function get_instance(): Synchronization {
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
		// add setting.
		add_action( 'init', array( $this, 'init_synchronize' ), 20 );

		// bail if synchronization support is not enabled.
		if ( 1 !== absint( get_option( 'eml_sync' ) ) ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_columns' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_files' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_synchronization' ), 10, 3 );
		add_action( 'eml_show_file_info', array( $this, 'show_sync_info' ) );
		add_action( 'eml_table_column_source', array( $this, 'show_sync_info_in_table' ) );
		add_action( 'efml_directory_listing_added', array( $this, 'added_new_directory' ) );
		add_filter( 'eml_table_column_file_source_dialog', array( $this, 'show_sync_info_in_dialog' ), 10, 2 );

		// add AJAX endpoints.
		add_action( 'wp_ajax_efml_sync_from_directory', array( $this, 'sync_via_ajax' ), 10, 0 );
		add_action( 'wp_ajax_efml_get_sync_info', array( $this, 'sync_info' ), 10, 0 );
		add_action( 'wp_ajax_efml_change_sync_state', array( $this, 'sync_state_change_via_ajax' ) );
		add_action( 'wp_ajax_efml_sync_save_config', array( $this, 'save_config_via_ajax' ) );

		// add admin actions.
		add_action( 'admin_action_efml_delete_synced_files', array( $this, 'delete_synced_file_via_request' ) );

		// misc.
		add_filter( 'admin_body_class', array( $this, 'add_sync_marker_on_edit_page' ) );
		add_filter( 'media_row_actions', array( $this, 'remove_delete_action' ), 10, 2 );
		add_filter( 'pre_delete_attachment', array( $this, 'prevent_deletion' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'delete_synced_files' ), 10, 2 );
		add_action( 'pre_delete_term', array( $this, 'delete_schedule' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'add_style' ) );
		add_filter( 'eml_schedules', array( $this, 'add_schedule_obj' ) );
	}

	/**
	 * Initialize the synchronization support.
	 *
	 * @return void
	 */
	public function init_synchronize(): void {
		// get settings object.
		$settings_obj = Settings::get_instance();

		// get the settings page.
		$settings_page = $settings_obj->get_page( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// add settings tab for WooCommerce.
		$sync_settings_tab = $settings_page->add_tab( 'synchronization', 80 );
		$sync_settings_tab->set_title( __( 'Synchronization', 'external-files-in-media-library' ) );

		// add section for WooCommerce settings.
		$sync_settings_section = $sync_settings_tab->add_section( 'eml_synchronisation_settings', 10 );
		$sync_settings_section->set_title( __( 'Synchronization', 'external-files-in-media-library' ) );

		// add setting to enable sync support.
		$sync_settings_setting = $settings_obj->add_setting( 'eml_sync' );
		$sync_settings_setting->set_section( $sync_settings_section );
		$sync_settings_setting->set_type( 'integer' );
		$sync_settings_setting->set_default( 1 );
		$sync_settings_setting->set_field(
			array(
				'title'       => __( 'Enable support for synchronization', 'external-files-in-media-library' ),
				'description' => __( 'If enabled you can synchronize any supported external source with your media library.', 'external-files-in-media-library' ),
				'type'        => 'Checkbox',
			)
		);

		// add setting to enable automatic sync for every new external directory.
		$setting = $settings_obj->add_setting( 'eml_sync_set_automatic' );
		$setting->set_section( $sync_settings_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox();
		$field->set_title( __( 'Enable automatic synchronization', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled every new external source will automatically be synchronized.', 'external-files-in-media-library' ) );
		$field->add_depend( $sync_settings_setting, 1 );
		$setting->set_field( $field );

		// create interval setting.
		$setting = $settings_obj->add_setting( 'eml_sync_interval' );
		$setting->set_section( $sync_settings_section );
		$setting->set_type( 'string' );
		$setting->set_default( 'efml_hourly' );
		$field = new Select();
		$field->set_title( __( 'Interval for synchronization', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Serves as a preset for new external sources. This setting can be changed on each external source.', 'external-files-in-media-library' ) );
		$field->set_options( Helper::get_intervals() );
		$field->set_sanitize_callback( array( $this, 'sanitize_interval_setting' ) );
		$field->add_depend( $sync_settings_setting, 1 );
		$setting->set_field( $field );

		// add setting for unused files after sync.
		$setting = $settings_obj->add_setting( 'eml_sync_delete_unused_files_after_sync' );
		$setting->set_section( $sync_settings_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Delete unused files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Delete files in the media library that are no longer in the external source after each synchronization.', 'external-files-in-media-library' ) );
		$field->add_depend( $sync_settings_setting, 1 );
		$setting->set_field( $field );

		// add setting for deletion of files on deletion of its archive.
		$setting = $settings_obj->add_setting( 'eml_sync_delete_file_on_archive_deletion' );
		$setting->set_section( $sync_settings_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Delete synchronized files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Delete files in media library belonging to an external source when the connection to the external source is deleted.', 'external-files-in-media-library' ) );
		$field->add_depend( $sync_settings_setting, 1 );
		$setting->set_field( $field );

		// add setting to send emails after sync.
		$setting = $settings_obj->add_setting( 'eml_sync_email' );
		$setting->set_section( $sync_settings_section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox();
		$field->set_title( __( 'Send email after sync', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by an email address. */
		$field->set_description( sprintf( __( 'When activated, you will receive an email to the admin email address %1$s or to the email address stored in the synchronization for the external source as soon as a synchronization has been successfully completed.', 'external-files-in-media-library' ), '<code>' . get_option( 'admin_email' ) . '</code>' ) );
		$field->add_depend( $sync_settings_setting, 1 );
		$setting->set_field( $field );
	}

	/**
	 * Run during plugin activation.
	 *
	 * @return void
	 */
	public function activation(): void {
		$this->init_synchronize();
	}

	/**
	 * Add columns to handle synchronization.
	 *
	 * @param array<string,string> $columns The columns.
	 *
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		$columns['synced_files']    = __( 'Synchronized files', 'external-files-in-media-library' );
		$columns['synchronization'] = __( 'Synchronization', 'external-files-in-media-library' );
		return $columns;
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
		if ( 'synced_files' !== $column_name ) {
			return $content;
		}

		// get the files which are assigned to this term.
		$files = $this->get_synced_files_by_term( $term_id );

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
				'action' => 'efml_delete_synced_files',
				'nonce'  => wp_create_nonce( 'efml-deleted-synced-files' ),
				'term'   => $term_id,
			),
			get_admin_url() . 'admin.php'
		);

		// create dialog for delete link.
		$dialog = array(
			'title'   => __( 'Delete synchronized files?', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Do you really want to delete this synchronized files?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The files will be deleted in your media library. The original files on the source will stay untouched.', 'external-files-in-media-library' ) . '</p>',
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

		// prepare dialog for attribute.
		$dialog = wp_json_encode( $dialog );

		// bail if preparation does not worked.
		if ( ! $dialog ) {
			return '';
		}

		// show count and link it to the media library and option to delete all of them.
		return '<a href="' . esc_url( $url ) . '">' . absint( count( $files ) ) . '</a> | <a href="' . esc_url( $url_delete ) . '" class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( $dialog ) . '">' . esc_html__( 'Delete', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Add the content for sync options.
	 *
	 * @param string $content The content.
	 * @param string $column_name The column name.
	 * @param int    $term_id The used term entry ID.
	 *
	 * @return string
	 */
	public function add_column_content_synchronization( string $content, string $column_name, int $term_id ): string {
		// bail if this is not the "synchronization" column.
		if ( 'synchronization' !== $column_name ) {
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

		// get the sync schedule object for this term_id.
		$sync_schedule_obj = $this->get_schedule_by_term_id( $term_id );

		// create dialog for sync now.
		$dialog_sync_now = array(
			'title'   => __( 'Synchronize now?', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p><strong>' . __( 'Are you sure you want to synchronize files from this external source with your media library?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'During synchronization, files are synchronized between the source and the media library.', 'external-files-in-media-library' ) . '<br>' . __( 'New files are imported, existing files are retained, files that no longer exist in the source are deleted from the media library.', 'external-files-in-media-library' ) . '<br>' . __( 'Files that do not belong to this source remain untouched.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'Synchronization may take some time.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'efml_sync_from_directory("' . $listing_obj->get_name() . '", ' . $term_id . ');',
					'variant' => 'primary',
					'text'    => __( 'Yes, synchronize now', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'No', 'external-files-in-media-library' ),
				),
			),
		);

		// get actual interval.
		$term_interval = $sync_schedule_obj ? $sync_schedule_obj->get_interval() : get_term_meta( $term_id, 'interval', true );
		if ( empty( $term_interval ) ) {
			$term_interval = get_option( 'eml_sync_interval' );
		}

		// create the interval field.
		$form = '<div><label for="interval">' . __( 'Choose interval:', 'external-files-in-media-library' ) . '</label><select id="interval">';
		foreach ( Helper::get_intervals() as $name => $label ) {
			// bail if this is the disabled entry.
			if ( 'eml_disable_check' === $name ) {
				continue;
			}

			// add the entry.
			$form .= '<option value="' . $name . '"' . ( $term_interval === $name ? ' selected' : '' ) . '>' . $label . '</option>';
		}
		$form .= '</select><input type="hidden" id="term_id" value="' . absint( $term_id ) . '"></div>';

		/**
		 * Filter the form to configure this external directory.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $form The form HTML-code.
		 * @param int $term_id The term ID.
		 */
		$form = apply_filters( 'efml_sync_configure_form', $form, $term_id );

		// get actual email.
		$email = get_term_meta( $term_id, 'email', true );

		// add option to send email after end of each sync.
		$form .= '<div><label for="email">' . __( 'Send email after sync to:', 'external-files-in-media-library' ) . '</label><input type="email" id="email" name="email" value="' . esc_attr( $email ) . '" placeholder="info@example.com"></div>';

		// add privacy hint, if it is not disabled.
		if ( 1 !== absint( get_user_meta( get_current_user_id(), 'efml_no_privacy_hint', true ) ) ) {
			$form .= '<div><label for="privacy"><input type="checkbox" id="privacy" name="privacy" value="1" required> <strong>' . __( 'I confirm that I will respect the copyrights of these external files:', 'external-files-in-media-library' ) . '</strong></label></div>';
		}

		// create dialog for sync config.
		$dialog_sync_config = array(
			'className' => 'eml-sync-config',
			/* translators: %1$s will be replaced by a name. */
			'title'     => sprintf( __( 'Settings for this %1$s connection', 'external-files-in-media-library' ), $listing_obj->get_label() ),
			'texts'     => array(
				'<p><strong>' . __( 'Configure interval which will be used to automatically synchronize this external source with your media library.', 'external-files-in-media-library' ) . '</strong></p>',
				$form,
			),
			'buttons'   => array(
				array(
					'action'  => 'efml_sync_save_config("' . $listing_obj->get_name() . '", ' . $term_id . ');',
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

		// define actions.
		$actions = array(
			'<div class="eml-switch-toggle"><input id="state-on-' . absint( $term_id ) . '" name="sync-states[' . absint( $term_id ) . ']" class="green" data-term-id="' . absint( $term_id ) . '" value="1" type="radio"' . ( $sync_schedule_obj ? ' checked' : '' ) . ' /><label for="state-on-' . absint( $term_id ) . '" class="green">' . __( 'On', 'external-files-in-media-library' ) . '</label><input id="state-off-' . absint( $term_id ) . '" name="sync-states[' . absint( $term_id ) . ']" class="red" type="radio" data-term-id="' . absint( $term_id ) . '" value="0"' . ( ! $sync_schedule_obj ? ' checked' : '' ) . ' /><label for="state-off-' . absint( $term_id ) . '" class="red">' . __( 'Off', 'external-files-in-media-library' ) . '</label></div>',
			'<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog_sync_now ) ) . '">' . __( 'Now', 'external-files-in-media-library' ) . '</a>',
			'<a href="#" class="button button-secondary easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog_sync_config ) ) . '">' . __( 'Configure', 'external-files-in-media-library' ) . '</a>',
		);

		// return the actions.
		return implode( ' ', $actions );
	}

	/**
	 * Run synchronization.
	 *
	 * @param string                 $url                   The URL to sync.
	 * @param Directory_Listing_Base $directory_listing_obj The directory listing base object we use.
	 * @param array<string,mixed>    $term_data             The term data.
	 * @param int                    $term_id               The used term ID.
	 *
	 * @return void
	 */
	public function sync( string $url, Directory_Listing_Base $directory_listing_obj, array $term_data, int $term_id ): void {
		// remove the update marker on all existing synced files for this URL.
		foreach ( $this->get_synced_files_by_url( $url ) as $post_id ) {
			delete_post_meta( $post_id, 'eml_synced' );
		}

		// disable duplicate check during synchronisation.
		add_filter( 'eml_duplicate_check', array( $this, 'disable_duplicate_check' ) );

		// and get the post_id of the existing file to update it.
		add_filter( 'eml_file_import_attachment', array( $this, 'get_attachment_id' ), 10, 2 );

		// and mark each file as updated.
		add_action( 'eml_after_file_save', array( $this, 'mark_as_synced' ) );

		// add counter handling.
		add_action( 'eml_file_directory_import_files', array( $this, 'set_url_max_count' ), 10, 2 );
		add_action( 'eml_file_directory_import_file_check', array( $this, 'update_url_count' ), 10, 0 );
		add_action( 'eml_ftp_directory_import_files', array( $this, 'set_url_max_count' ), 10, 2 );
		add_action( 'eml_ftp_directory_import_file_check', array( $this, 'update_url_count' ), 10, 0 );
		add_action( 'eml_http_directory_import_files', array( $this, 'set_url_max_count' ), 10, 2 );
		add_action( 'eml_http_directory_import_file_check', array( $this, 'update_url_count' ), 10, 0 );
		add_action( 'eml_sftp_directory_import_files', array( $this, 'set_url_max_count' ), 10, 2 );
		add_action( 'eml_sftp_directory_import_file_check', array( $this, 'set_url_max_count' ), 10, 2 );
		add_action( 'eml_before_file_list', array( $this, 'change_process_title' ) );

		// update the sync title on each file.
		add_action( 'eml_file_import_before_save', array( $this, 'update_sync_title' ) );

		/**
		 * Allow to add additional tasks before sync is running.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param string $url The used URL.
		 * @param array<string,string> $term_data The term data.
		 * @param int $term_id The used term ID.
		 */
		do_action( 'efml_before_sync', $url, $term_data, $term_id );

		// get the import object.
		$import = Import::get_instance();

		// add the credentials.
		$import->set_login( $directory_listing_obj->get_login_from_archive_entry( $term_data ) );
		$import->set_password( $directory_listing_obj->get_password_from_archive_entry( $term_data ) );

		// log this event.
		Log::get_instance()->create( __( 'Synchronization startet.', 'external-files-in-media-library' ), $url, 'info', 1 );

		// and run the import of this directory.
		$import->add_url( $url );

		// log this event.
		Log::get_instance()->create( __( 'Synchronization ended.', 'external-files-in-media-library' ), $url, 'info', 1 );

		// delete unused files, if enabled.
		if ( 1 === absint( get_option( 'eml_sync_delete_unused_files_after_sync' ) ) ) {
			// get again all files from given source URL but which does not have the update marker.
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
						'key'     => 'eml_synced',
						'compare' => 'NOT EXISTS',
					),
				),
				'tax_query'      => array(
					array(
						'taxonomy' => Taxonomy::get_instance()->get_name(),
						'field'    => 'name',
						'terms'    => $url,
					),
				),
				'posts_per_page' => - 1,
				'fields'         => 'ids',
			);
			$result = new WP_Query( $query );

			// delete this files.
			foreach ( $result->get_posts() as $post_id ) {
				// get the external file object of this attachment.
				$external_file_obj = Files::get_instance()->get_file( absint( $post_id ) );

				// bail if object could not be loaded.
				if ( ! $external_file_obj->is_valid() ) {
					continue;
				}

				// delete this file.
				$external_file_obj->delete();
			}

			// log this event.
			Log::get_instance()->create( __( 'Synchronization cleanup ended.', 'external-files-in-media-library' ), $url, 'info', 1 );

			// send email if enabled.
			if ( 1 === absint( get_option( 'eml_sync_email' ) ) ) {
				// get the to-email from settings.
				$to = get_term_meta( $term_id, 'email', true );
				if ( empty( $to ) ) {
					$to = get_option( 'admin_email' );
				}

				// get the term.
				$term = get_term_by( 'term_id', $term_id, Taxonomy::get_instance()->get_name() );

				// bail if term could not be loaded.
				if ( ! $term instanceof WP_Term ) {
					return;
				}

				// define mail.
				$subject = '[' . get_option( 'blogname' ) . '] ' . __( 'Synchronisation completed', 'external-files-in-media-library' );
				/* translators: %1$s will be replaced by a title. */
				$body    = sprintf( __( 'The synchronization of %1$s has been successfully completed.', 'external-files-in-media-library' ), esc_html( $term->name ) ) . '<br><br>' . __( 'This email was generated by the WordPress plugin <em>External files for Media Library</em> based on the settings in your project.', 'external-files-in-media-library' );
				$headers = array(
					'Content-Type: text/html; charset=UTF-8',
				);

				// send mail.
				wp_mail( $to, $subject, $body, $headers );
			}
		}
	}

	/**
	 * Call sync via AJAX request.
	 *
	 * @return void
	 */
	public function sync_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-sync-nonce', 'nonce' );

		// get log object.
		$log = Log::get_instance();

		// get method.
		$method = filter_input( INPUT_POST, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if no method is given.
		if ( is_null( $method ) ) {
			$log->create( __( 'No method given for synchronization.', 'external-files-in-media-library' ), '', 'error' );
			wp_send_json_error();
		}

		// get the method object by its name.
		$directory_listing_obj = Services::get_instance()->get_service_by_name( $method );

		// bail if no service could be found.
		if ( ! $directory_listing_obj ) {
			$log->create( __( 'Requested method is unknown: ', 'external-files-in-media-library' ) . ' <code>' . $method . '</code>', '', 'error' );
			wp_send_json_error();
		}

		// get the requested directory term.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if no term_id is given.
		if ( $term_id <= 0 ) {
			$log->create( __( 'No external source given for synchronization.', 'external-files-in-media-library' ), '', 'error' );
			wp_send_json_error();
		}

		// get the term data.
		$term_data = Taxonomy::get_instance()->get_entry( $term_id );

		// bail if term_data could not be loaded.
		if ( empty( $term_data ) ) {
			$log->create( __( 'Requested external source does not have any configuration.', 'external-files-in-media-library' ), '', 'error' );
			wp_send_json_error();
		}

		// get the URL.
		$url = $directory_listing_obj->get_url( $term_data['directory'] );

		// mark sync as running.
		update_option( 'eml_sync_running', time() );

		// reset counter.
		update_option( 'eml_sync_url_count', 0 );
		update_option( 'eml_sync_url_max', 0 );

		// set initial title.
		update_option( 'eml_sync_title', __( 'Sync of files starting ..', 'external-files-in-media-library' ) );

		// run the synchronization.
		$this->sync( $url, $directory_listing_obj, $term_data, $term_id );

		// mark sync as not running.
		delete_option( 'eml_sync_running' );

		// send success.
		wp_send_json_success();
	}

	/**
	 * Return info about running sync via AJAX.
	 *
	 * @return void
	 */
	public function sync_info(): void {
		// check nonce.
		check_ajax_referer( 'eml-sync-info_nonce', 'nonce' );

		// get the running marker.
		$running = absint( get_option( 'eml_sync_running', 0 ) );

		// create dialog.
		$dialog = array(
			'detail' => array(
				'className' => 'eml',
				'title'     => __( 'Synchronization has been executed', 'external-files-in-media-library' ),
				'texts'     => array(
					'<p><strong>' . __( 'The files in this external source are now synchronized in your media library.', 'external-files-in-media-library' ) . '</strong></p>',
					'<p>' . __( 'You can now use them on your website.', 'external-files-in-media-library' ) . '</p>',
				),
				'buttons'   => array(
					array(
						'action'  => 'location.reload();',
						'variant' => 'primary',
						'text'    => __( 'OK', 'external-files-in-media-library' ),
					),
					array(
						'action'  => 'location.href="' . Helper::get_media_library_url() . '";',
						'variant' => 'secondary',
						'text'    => __( 'Go to media library', 'external-files-in-media-library' ),
					),
				),
			),
		);

		// return sync info.
		wp_send_json(
			array(
				absint( get_option( 'eml_sync_url_count', 0 ) ),
				absint( get_option( 'eml_sync_url_max', 0 ) ),
				$running,
				wp_kses_post( get_option( 'eml_sync_title', '' ) ),
				$dialog,
			)
		);
	}

	/**
	 * Disable duplicate check during synchronisation.
	 *
	 * @return bool
	 */
	public function disable_duplicate_check(): bool {
		return true;
	}

	/**
	 * Get and add the attachment ID to the update statement during import.
	 *
	 * @param array<string,mixed> $post_array Given post array for update.
	 * @param string              $url The given URL.
	 *
	 * @return array<string,mixed>
	 */
	public function get_attachment_id( array $post_array, string $url ): array {
		// get the file object of the given URL.
		$external_file_object = Files::get_instance()->get_file_by_url( $url );

		// bail if no external file object could be found.
		if ( ! $external_file_object ) {
			return $post_array;
		}

		// add the ID.
		$post_array['ID'] = $external_file_object->get_id();

		// return resulting array.
		return $post_array;
	}

	/**
	 * Mark as updated.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function mark_as_synced( File $external_file_obj ): void {
		update_post_meta( $external_file_obj->get_id(), 'eml_synced', 1 );
		update_post_meta( $external_file_obj->get_id(), 'eml_synced_time', time() );
	}

	/**
	 * Show sync info (date-time) for files which has been synced.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function show_sync_info( File $external_file_obj ): void {
		// get sync marker.
		$sync_marker = absint( get_post_meta( $external_file_obj->get_id(), 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return;
		}

		// show info about sync time.
		?>
		<li><span class="dashicons dashicons-clock"></span> <?php echo esc_html__( 'Last synchronized:', 'external-files-in-media-library' ); ?><br><code><?php echo esc_html( Helper::get_format_date_time( gmdate( 'Y-m-d H:i', $sync_marker ) ) ); ?></code></li>
		<?php
	}

	/**
	 * Show sync info in info dialog for single file.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param File                $external_file_obj The file object.
	 *
	 * @return array<string,mixed>
	 */
	public function show_sync_info_in_dialog( array $dialog, File $external_file_obj ): array {
		// get sync marker.
		$sync_marker = absint( get_post_meta( $external_file_obj->get_id(), 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return $dialog;
		}

		// add infos in dialog.
		$dialog['texts'][] = '<p><strong>' . esc_html__( 'Last synchronized:', 'external-files-in-media-library' ) . '</strong> ' . esc_html( Helper::get_format_date_time( gmdate( 'Y-m-d H:i', $sync_marker ) ) ) . '</p>';

		// return resulting dialog.
		return $dialog;
	}

	/**
	 * Update the URL count during sync.
	 *
	 * @return void
	 */
	public function update_url_count(): void {
		update_option( 'eml_sync_url_count', absint( get_option( 'eml_sync_url_count', 0 ) ) + 1 );
	}

	/**
	 * Set new max value during sync.
	 *
	 * @param string              $url The used URL.
	 * @param array<string,mixed> $matches The list of matches on this URL.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function set_url_max_count( string $url, array $matches ): void {
		update_option( 'eml_sync_url_max', absint( get_option( 'eml_sync_url_max' ) + count( $matches ) ) );
	}

	/**
	 * Update the process title.
	 *
	 * @return void
	 */
	public function change_process_title(): void {
		update_option( 'eml_sync_title', __( 'Sync of files running ..', 'external-files-in-media-library' ) );
	}

	/**
	 * Show info about a synced file in media files table.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function show_sync_info_in_table( int $attachment_id ): void {
		// get synced term by given attachment ID.
		$term = Files::get_instance()->get_term_by_attachment_id( $attachment_id );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// get sync marker.
		$sync_marker = absint( get_post_meta( $attachment_id, 'eml_synced_time', true ) );

		// create URL.
		$url = add_query_arg(
			array(
				'taxonomy'  => 'edlfw_archive',
				'post_type' => 'attachment',
				's'         => $term->name,
			),
			get_admin_url() . 'edit-tags.php'
		);

		// create the title-attribute.
		/* translators: %1$s will be replaced by a date-time, %2$s by a URL. */
		$title = sprintf( __( 'Last synchronized at %1$s from %2$s', 'external-files-in-media-library' ), Helper::get_format_date_time( gmdate( 'Y-m-d H:i', $sync_marker ) ), $term->name );

		// show info about sync time.
		?>
		<a class="dashicons dashicons-clock" href="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $title ); ?>"></a>
		<?php
	}

	/**
	 * Add CSS class to mark synced files on edit screen.
	 *
	 * @param string $classes List of classes as string.
	 *
	 * @return string
	 */
	public function add_sync_marker_on_edit_page( string $classes ): string {
		global $pagenow;

		// bail if we are not in post.php.
		if ( 'post.php' !== $pagenow ) {
			return $classes;
		}

		// get the post id.
		$post_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if post id is not given.
		if ( 0 === $post_id ) {
			return $classes;
		}

		// get sync marker.
		$sync_marker = absint( get_post_meta( $post_id, 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return $classes;
		}

		// add a class to mark this file as synced.
		$classes .= ' eml-file-synced';

		// return the list of classes.
		return $classes;
	}

	/**
	 * Remove the delete action for synced files.
	 *
	 * @param array<string,string> $actions List of actions.
	 * @param WP_Post              $post The post object of the file.
	 *
	 * @return array<string,string>
	 */
	public function remove_delete_action( array $actions, WP_Post $post ): array {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_sync_delete_file_on_archive_deletion' ) ) ) {
			return $actions;
		}

		// get sync marker.
		$sync_marker = absint( get_post_meta( $post->ID, 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return $actions;
		}

		// bail if delete action does not exist.
		if ( ! isset( $actions['delete'] ) ) {
			return $actions;
		}

		// remove the delete action.
		unset( $actions['delete'] );

		// return resulting actions.
		return $actions;
	}

	/**
	 * Prevent deletion of synced files.
	 *
	 * @param WP_Post|false|null $delete The marker whether the file should be deleted.
	 * @param WP_Post            $post The post object of the file.
	 *
	 * @return WP_Post|false|null
	 */
	public function prevent_deletion( WP_Post|false|null $delete, WP_Post $post ): WP_Post|false|null {
		// bail if we are running the plugin deinstallation.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return $delete;
		}

		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_sync_delete_file_on_archive_deletion' ) ) ) {
			return $delete;
		}

		// bail if this is running during an archive term deletion.
		if ( ! is_null( filter_input( INPUT_POST, 'tag_ID', FILTER_SANITIZE_NUMBER_INT ) ) || ! is_null( filter_input( INPUT_GET, 'tag_ID', FILTER_SANITIZE_NUMBER_INT ) ) ) {
			return $delete;
		}

		// get sync marker.
		$sync_marker = absint( get_post_meta( $post->ID, 'eml_synced_time', true ) );

		// bail if marker ist not set.
		if ( 0 === $sync_marker ) {
			return $delete;
		}

		// prevent the deletion.
		return false;
	}

	/**
	 * Delete all synced files from media library if the assigned archive term is deleted.
	 *
	 * @param int    $term_id The term ID to delete.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return void
	 */
	public function delete_synced_files( int $term_id, string $taxonomy ): void {
		// bail if this is not our archive taxonomy.
		if ( Taxonomy::get_instance()->get_name() !== $taxonomy ) {
			return;
		}

		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_sync_delete_file_on_archive_deletion' ) ) ) {
			return;
		}

		// get the term.
		$term = get_term( $term_id, $taxonomy );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// get the type name.
		$type = get_term_meta( $term_id, 'type', true );

		// get the listing object by this name.
		$listing_obj = Directory_Listings::get_instance()->get_directory_listing_object_by_name( $type );

		// bail if listing object could not be found.
		if ( ! $listing_obj ) {
			return;
		}

		// remove the prevent-deletion for this moment.
		remove_filter( 'pre_delete_attachment', array( $this, 'prevent_deletion' ) );

		// get the URL.
		$url = $listing_obj->get_url( $term->name );

		// remove all synced files from this URL.
		foreach ( $this->get_synced_files_by_url( $url ) as $post_id ) {
			wp_delete_attachment( $post_id, true );
		}
	}

	/**
	 * Delete schedule if term is deleted.
	 *
	 * @param int    $term_id  The term ID to delete.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return void
	 */
	public function delete_schedule( int $term_id, string $taxonomy ): void {
		// bail if this is not our archive taxonomy.
		if ( Taxonomy::get_instance()->get_name() !== $taxonomy ) {
			return;
		}

		// get the sync schedule object for this term_id.
		$sync_schedule_obj = $this->get_schedule_by_term_id( $term_id );

		// bail if none has been found.
		if ( ! $sync_schedule_obj ) {
			return;
		}

		// delete it.
		$sync_schedule_obj->delete();
	}

	/**
	 * Return the schedule object by its term ID.
	 *
	 * @param int $term_id The term ID.
	 *
	 * @return Schedules\Synchronization|false
	 */
	private function get_schedule_by_term_id( int $term_id ): Schedules\Synchronization|false {
		// get all schedules.
		foreach ( _get_cron_array() as $event ) {
			// get first entry key.
			$key = array_key_first( $event );

			// bail if key is not a string.
			if ( ! is_string( $key ) ) {
				continue;
			}

			// bail if key starts not with "eml_sync".
			if ( ! str_starts_with( $key, 'eml_sync' ) ) {
				continue;
			}

			// get the array content.
			$array = current( $event['eml_sync'] );

			// bail if no term_id is set.
			if ( ! isset( $array['args']['term_id'] ) ) {
				continue;
			}

			// bail if term_id does not match.
			if ( $term_id !== $array['args']['term_id'] ) {
				continue;
			}

			// get schedule object.
			$schedule_obj = new Schedules\Synchronization();

			// set the args.
			$schedule_obj->set_args( $array['args'] );

			// set interval.
			$schedule_obj->set_interval( Helper::get_interval_by_time( absint( $array['interval'] ) ) );

			// return this object.
			return $schedule_obj;
		}

		// return false if no schedule object has been found.
		return false;
	}

	/**
	 * Return list of synced file by source URL.
	 *
	 * @param string $url The used URL.
	 *
	 * @return array<int,int>
	 */
	private function get_synced_files_by_url( string $url ): array {
		// get all files from given source URL which are already in media library.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => EFML_POST_META_URL,
					'compare' => 'EXISTS',
				),
			),
			'tax_query'      => array(
				array(
					'taxonomy' => Taxonomy::get_instance()->get_name(),
					'field'    => 'name',
					'terms'    => $url,
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		if ( 0 === $result->found_posts ) {
			return array();
		}

		// get list of IDs.
		$list = array();
		foreach ( $result->get_posts() as $post_id ) {
			$list[] = absint( $post_id );
		}
		return $list;
	}

	/**
	 * Hide checkbox if file in media library is a synced file.
	 *
	 * @return void
	 */
	public function add_style(): void {
		global $pagenow;

		// bail if called page is not the media library.
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_sync_delete_file_on_archive_deletion' ) ) ) {
			return;
		}

		// get external files as list.
		$external_files = Files::get_instance()->get_files();

		// output the custom css.
		echo '<style>';
		foreach ( $external_files as $extern_file_obj ) {
			// get sync marker.
			$sync_marker = absint( get_post_meta( $extern_file_obj->get_id(), 'eml_synced_time', true ) );

			// bail if marker ist not set.
			if ( 0 === $sync_marker ) {
				continue;
			}

			// hide the column of this plugin in the table.
			?>
			#post-<?php echo absint( $extern_file_obj->get_id() ); ?> .check-column > input { display: none; }
			<?php
		}
		echo '</style>';
	}

	/**
	 * Run after new external directory has been added.
	 *
	 * @param int $term_id The ID of the newly created term.
	 *
	 * @return void
	 */
	public function added_new_directory( int $term_id ): void {
		// bail if this is not enabled.
		if ( 1 !== absint( get_option( 'eml_sync_set_automatic' ) ) ) {
			return;
		}

		// add the schedule.
		$this->add_schedule( $term_id );
	}

	/**
	 * Add a schedule after a new archive term has been created, if this is enabled.
	 *
	 * @param int $term_id The ID of the term.
	 *
	 * @return void
	 */
	private function add_schedule( int $term_id ): void {
		// get the interval to set.
		$interval = get_term_meta( $term_id, 'interval', true );
		if ( empty( $interval ) ) {
			$interval = 'efml_hourly';
		}

		// get the schedule object.
		$schedule_obj = new Schedules\Synchronization();

		// set the arguments.
		$schedule_obj->set_args(
			array(
				'term_id' => $term_id,
				'method'  => (string) get_term_meta( $term_id, 'type', true ),
			)
		);

		// set the interval.
		$schedule_obj->set_interval( $interval );

		// install it.
		$schedule_obj->install();
	}

	/**
	 * Add the schedule object for synchronization.
	 *
	 * @param array<int,string> $schedule_obj List of schedule objects.
	 *
	 * @return array<int,string>
	 */
	public function add_schedule_obj( array $schedule_obj ): array {
		$schedule_obj[] = '\ExternalFilesInMediaLibrary\Plugin\Schedules\Synchronization';
		return $schedule_obj;
	}

	/**
	 * Sanitize the interval setting.
	 *
	 * @param string|null $value The given value.
	 *
	 * @return string
	 */
	public function sanitize_interval_setting( null|string $value ): string {
		// get option.
		$option = str_replace( 'sanitize_option_', '', current_filter() );

		// bail if value is empty.
		if ( empty( $value ) ) {
			add_settings_error( $option, $option, __( 'An interval has to be set.', 'external-files-in-media-library' ) );
			return '';
		}

		// bail if value is 'eml_disable_check'.
		if ( 'eml_disable_check' === $value ) {
			return $value;
		}

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $value ] ) ) {
			/* translators: %1$s will be replaced by the name of the used interval */
			add_settings_error( $option, $option, sprintf( __( 'The given interval %1$s does not exists.', 'external-files-in-media-library' ), esc_html( $value ) ) );
		}

		// return the value.
		return $value;
	}

	/**
	 * Change sync state via AJAX.
	 *
	 * @return void
	 */
	public function sync_state_change_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'eml-sync-state-nonce', 'nonce' );

		// get term ID.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if term ID is not given.
		if ( 0 === $term_id ) {
			wp_send_json_error();
		}

		// get the new state.
		$state = filter_input( INPUT_POST, 'state', FILTER_SANITIZE_NUMBER_INT );

		// bail if state is not given.
		if ( is_null( $state ) ) {
			wp_send_json_error();
		}

		// set new state.
		if ( 1 === absint( $state ) ) {
			$this->add_schedule( $term_id );
		} else {
			// get the schedule object.
			$schedule_obj = $this->get_schedule_by_term_id( $term_id );
			if ( $schedule_obj instanceof Schedules_Base ) {
				$schedule_obj->delete();
			}
		}

		// send ok.
		wp_send_json_success();
	}

	/**
	 * Save new configuration for single synchronization schedule.
	 *
	 * @return void
	 */
	public function save_config_via_ajax(): void {
		// check referer.
		check_ajax_referer( 'eml-sync-save-config-nonce', 'nonce' );

		// create dialog for failures.
		$dialog = array(
			'title'   => __( 'Configuration not saved', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'The configuration for this synchronization could not be saved.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get the fields.
		$fields = isset( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array();

		// bail if term ID or interval is not given.
		if ( empty( $fields['interval'] ) || 0 === absint( $fields['term_id'] ) ) {
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// get the term ID.
		$term_id = absint( $fields['term_id'] );

		// get the interval.
		$interval = $fields['interval'];

		// check if the given interval exists.
		$intervals = wp_get_schedules();
		if ( empty( $intervals[ $interval ] ) ) {
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// save this interval on term as setting.
		update_term_meta( $term_id, 'interval', $interval );

		// save the given email.
		update_term_meta( $term_id, 'email', $fields['email'] );

		/**
		 * Run additional tasks during saving a new sync configuration.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param array $fields List of fields.
		 */
		do_action( 'efml_sync_save_config', $fields );

		// create dialog.
		$dialog = array(
			'title'   => __( 'Configuration saved', 'external-files-in-media-library' ),
			'texts'   => array(
				'<p>' . __( 'The new configuration for this synchronization has been saved.', 'external-files-in-media-library' ) . '</p>',
			),
			'buttons' => array(
				array(
					'action'  => 'location.reload();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// get the sync schedule object for this term_id.
		$sync_schedule_obj = $this->get_schedule_by_term_id( $term_id );

		// bail if no schedule found, but also send OK back.
		if ( ! $sync_schedule_obj instanceof Schedules\Synchronization ) {
			wp_send_json( array( 'detail' => $dialog ) );
		}

		// set the new interval.
		$sync_schedule_obj->set_interval( $interval );

		// re-install schedule.
		$sync_schedule_obj->reset();

		// send ok.
		wp_send_json( array( 'detail' => $dialog ) );
	}

	/**
	 * Delete synced files via request.
	 *
	 * @return void
	 */
	public function delete_synced_file_via_request(): void {
		// check referer.
		check_admin_referer( 'efml-deleted-synced-files', 'nonce' );

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
		$files = $this->get_synced_files_by_term( $term_id );

		// bail if no files could be found.
		if ( empty( $files ) ) {
			wp_safe_redirect( $referer );
		}

		// remove the prevent-deletion for this moment.
		remove_filter( 'pre_delete_attachment', array( $this, 'prevent_deletion' ) );

		// loop through the files and delete them.
		foreach ( $files as $post_id ) {
			wp_delete_attachment( $post_id, true );
		}

		// return the user.
		wp_safe_redirect( $referer );
	}

	/**
	 * Return list of all files which are assigned to a given term.
	 *
	 * @param int $term_id The term to filter.
	 *
	 * @return array<int,int>
	 */
	private function get_synced_files_by_term( int $term_id ): array {
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
					'key'     => 'eml_synced',
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
	 * Update the synchronization title.
	 *
	 * @param string $url The actual processed URL.
	 *
	 * @return void
	 */
	public function update_sync_title( string $url ): void {
		/* translators: %1$s will be replaced by a URL. */
		update_option( 'eml_sync_title', sprintf( __( 'Synchronize URL %1$s ..', 'external-files-in-media-library' ), $url ) );
	}
}
