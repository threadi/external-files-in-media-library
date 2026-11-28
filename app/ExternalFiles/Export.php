<?php
/**
 * File to handle the export of in the media library uploaded files to supporting external services.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Directory_Listings;
use easyDirectoryListingForWordPress\Init;
use easyDirectoryListingForWordPress\Taxonomy;
use easySettingsForWordPress\Fields\Button;
use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Fields\MultiSelect;
use easySettingsForWordPress\Fields\PermalinkSlug;
use easySettingsForWordPress\Fields\Select;
use easySettingsForWordPress\Fields\TextInfo;
use easySettingsForWordPress\Page;
use ExternalFilesInMediaLibrary\Dependencies\easyTransientsForWordPress\Transients;
use ExternalFilesInMediaLibrary\ExternalFiles\Extensions\Queue;
use ExternalFilesInMediaLibrary\Plugin\Admin\Directory_Listing;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Site_Copy;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Service_Base;
use ExternalFilesInMediaLibrary\Services\Services;
use WP_Post;
use WP_Query;
use WP_Screen;
use WP_Term;
use WP_Term_Query;

/**
 * Object to handle the export of in the media library uploaded files to supporting external services.
 */
class Export extends Tools_Base {
	/**
	 * Name of this object.
	 *
	 * @var string
	 */
	protected string $name = 'export';

	/**
	 * Marker for running sync.
	 *
	 * @var bool
	 */
	private bool $sync_running = false;

	/**
	 * The ID of the external source, which is actually synchronized.
	 *
	 * @var int
	 */
	private int $sync_term_id = 0;

	/**
	 * The URL of the external source, which is actually synchronized.
	 *
	 * @var string
	 */
	private string $sync_url = '';

	/**
	 * The URLs and the relative paths of the files, which have been exported to the actually synchronized external
	 * source (loaded once per synchronization and request).
	 *
	 * @var array{urls: array<string,bool>, paths: array<string,bool>}|null
	 */
	private ?array $exported_files_of_sync = null;

	/**
	 * List of attachment IDs, which have been uploaded in this request or whose export as upload is running.
	 *
	 * @var array<int,bool>
	 */
	private array $uploads = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Export
	 */
	private static ?Export $instance = null;

	/**
	 * The usable external sources for the export action in the media library, collected once per request.
	 *
	 * Empty if files could not be exported at the moment.
	 *
	 * @var array<string,string>|null
	 */
	private ?array $row_action_sources = null;

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
		add_action( 'init', array( $this, 'add_settings' ), 20 );
		add_action( 'post-upload-ui', array( $this, 'show_export_hint_on_file_add_page' ) );
		add_filter( 'efml_table_column_file_source_dialog', array( $this, 'show_export_state_in_info_dialog' ), 10, 2 );
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_column_for_hint' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_hint_content' ), 10, 3 );
		add_action( 'current_screen', array( $this, 'add_hint_for_unusable_sources' ) );
		add_action( 'admin_action_efml_export_again', array( $this, 'export_again_via_request' ) );

		// remember the uploads of this request, as only they are exported with their metadata.
		add_action( 'add_attachment', array( $this, 'mark_as_upload' ) );

		// bail if not enabled.
		if ( ! $this->is_enabled() ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_directory_listing_columns', array( $this, 'add_columns' ) );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_options' ), 10, 3 );
		add_filter( 'efml_directory_listing_column', array( $this, 'add_column_content_files' ), 10, 3 );
		add_action( 'efml_before_sync', array( $this, 'add_sync_filter' ), 10, 3 );
		add_action( 'efml_after_sync', array( $this, 'remove_sync_filter' ), 10, 0 );
		add_action( 'efml_before_deleting_synced_files', array( $this, 'add_sync_filter_during_deletion' ) );
		add_action( 'efml_after_deleting_synced_files', array( $this, 'remove_sync_filter' ), 10, 0 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'add_export_in_directory_listing' ), 10, 2 );
		add_filter( 'efml_directory_listing_item_actions', array( $this, 'remove_listing_delete_action' ), 10, 2 );
		add_action( 'efml_show_file_info', array( $this, 'add_info_about_export' ) );
		add_action( 'efml_real_import_local', array( $this, 'delete_exported_file_during_import' ) );
		add_action( 'efml_switch_to_local_before', array( $this, 'prevent_export_checks_on_local_switch' ), 10, 0 );
		add_action( 'efml_switch_to_local_after', array( $this, 'cleanup_exported_file' ) );
		add_action( 'efml_switch_to_local_after', array( $this, 'allow_export_checks_after_local_switch' ), 20, 0 );
		add_action( 'efml_switch_to_local_failed', array( $this, 'allow_export_checks_after_local_switch' ), 20, 0 );

		// add admin actions.
		add_action( 'admin_action_efml_delete_exported_files', array( $this, 'delete_exported_file_via_request' ) );
		add_action( 'admin_action_efml_export_file', array( $this, 'export_file_via_request' ) );
		add_action( 'admin_action_efml_export_local_files', array( $this, 'export_local_files_via_request' ) );

		// use AJAX.
		add_action( 'wp_ajax_efml_change_export_state', array( $this, 'export_state_change_via_ajax' ) );

		// use hooks.
		add_action( 'admin_enqueue_scripts', array( $this, 'add_styles_and_js_admin' ) );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'export_file_by_upload' ), 20, 2 );
		add_filter( 'wp_unique_filename', array( $this, 'check_for_exported_filenames' ), 10, 3 );
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
	public function add_settings(): void {
		// get settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

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
		if ( method_exists( $setting_export, 'set_soft_reload_on_save' ) ) { // @phpstan-ignore function.alreadyNarrowedType
			$setting_export->set_soft_reload_on_save( true );
		}
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Enable export', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by a URL. */
		$field->set_description( sprintf( __( 'If enabled you have to configure one or more of <a href="%1$s">your external sources</a> as export target. Every new file in media library will be exported to the configured source and used as external file.', 'external-files-in-media-library' ), Directory_Listing::get_instance()->get_listing_url() ) );
		$field->set_setting( $setting_export );
		$setting_export->set_field( $field );

		// get the available extensions for import.
		$extensions = array();
		foreach ( Extension_Types::get_instance()->get_extensions_for_type( 'export_dialog' ) as $extension_obj ) {
			$extensions[ $extension_obj->get_name() ] = $extension_obj->get_title();
		}

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_extensions' );
		$setting->set_section( $section );
		$setting->set_type( 'array' );
		$setting->set_default( ImportDialog::get_instance()->get_default_extensions() );
		$setting->set_show_in_rest( array( 'schema' => array( 'items' => array( 'type' => 'string' ) ) ) );
		if ( method_exists( $setting, 'set_soft_reload_on_save' ) ) { // @phpstan-ignore function.alreadyNarrowedType
			$setting->set_soft_reload_on_save( true );
		}
		$field = new MultiSelect( $settings_obj );
		$field->set_title( __( 'Options for export', 'external-files-in-media-library' ) );
		$field->set_description( __( 'Select the options you want to have available in your export dialog. You will be able to enable or disable these settings on each external source.', 'external-files-in-media-library' ) );
		$field->set_options( $extensions );
		$field->add_depend( $setting_export, 1 );
		$setting->set_field( $field );
		$setting->set_help( '<p>' . $field->get_description() . '</p>' );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_do_not_delete_local_files' );
		$setting->set_section( $section );
		$setting->set_default( 0 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Do not delete local files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'When enabled, exported files are no longer deleted from local hosting. Note that this means you will be storing the files twice: once in your hosting and once in the external source. Files delivered via the proxy still take up storage space on it. However, the proxy cache is emptied regularly so that only files that are actually needed to take up storage space.', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_in_background' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 0 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Export in background', 'external-files-in-media-library' ) );
		/* translators: %1$s will be replaced by a URL. */
		$field->set_description( sprintf( __( 'When enabled, new files in the media library and files of the bulk action are not exported directly during the upload, but added to <a href="%1$s">the queue</a>. They are exported in the background right away, the oldest first. Until then, they are used as local files. If an export fails, it will be tried again after 5 and after 15 minutes. After 3 failed attempts, the file stays a local file and is marked in the media library, where you can filter for it and export it again. If the queue is disabled, the files are exported directly.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_queue_table' ) ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// get whether WordPress organizes the uploads in year- and month-based folders, which is required for the folders.
		$yearmonth_folders = $this->is_yearmonth_folders_enabled();

		// create the hint, if WordPress does not use year- and month-based folders.
		$yearmonth_hint = '';
		if ( ! $yearmonth_folders ) {
			/* translators: %1$s will be replaced by a URL. */
			$yearmonth_hint = ' <strong>' . sprintf( __( 'This requires the setting "Organize my uploads into month- and year-based folders" in <a href="%1$s">the media settings of WordPress</a>.', 'external-files-in-media-library' ), esc_url( admin_url( 'options-media.php' ) ) ) . '</strong>';
		}

		// add setting.
		$setting_folders = $settings_obj->add_setting( 'eml_export_folders' );
		$setting_folders->set_section( $section );
		$setting_folders->set_type( 'integer' );
		$setting_folders->set_default( 0 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Use folders for exported files', 'external-files-in-media-library' ) );
		$field->set_description( __( 'If enabled, exported files are saved in folders on the external sources according to the following structure. This applies to all external sources, whose service supports folders. Already exported files keep their location.', 'external-files-in-media-library' ) . $yearmonth_hint );
		$field->add_depend( $setting_export, 1 );
		$field->set_readonly( ! $yearmonth_folders );
		$field->set_setting( $setting_folders );
		$setting_folders->set_field( $field );

		// add setting.
		$setting = $settings_obj->add_setting( 'eml_export_folder_structure' );
		$setting->set_section( $section );
		$setting->set_type( 'string' );
		$setting->set_default( '%year%/%monthnum%' );
		$field = new PermalinkSlug( $settings_obj );
		$field->set_title( __( 'Structure of the folders', 'external-files-in-media-library' ) );
		$field->set_list_title( __( 'Available placeholders:', 'external-files-in-media-library' ) );
		$field->set_options( $this->get_folder_structure_placeholders() );
		$field->set_description( __( 'Year, month and day are the date of the upload of the file, like in the folders of WordPress. Use "/" to separate folders.', 'external-files-in-media-library' ) . ' ' . __( 'The timestamp is the time of the export and enables the object versioning: each export gets its own URL, so outdated caches of CDNs and browsers are bypassed and services like AWS S3 and Google Cloud Storage let them cache the files for one week instead of one day.', 'external-files-in-media-library' ) . $yearmonth_hint );
		$field->set_sanitize_callback( array( $this, 'sanitize_folder_structure' ) );
		$field->add_depend( $setting_export, 1 );
		$field->add_depend( $setting_folders, 1 );
		$field->set_readonly( ! $yearmonth_folders );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// check whether the settings are shown or saved in this request: the external sources and the buttons, which
		// need queries, are only needed there.
		$is_settings_request = Settings::get_instance()->is_settings_request();

		// collect the external sources (also not usable ones, so the setting is not changed, while e.g. an extension is not active).
		$external_sources = $is_settings_request ? $this->get_external_sources_as_name_list( false ) : array();

		// create URL.
		$url = add_query_arg(
			array(
				'taxonomy'  => Taxonomy::get_instance()->get_name(),
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
			$field = new TextInfo( $settings_obj );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( '<p>' . sprintf( __( 'You have not enabled export for any external sources. Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $url ) . '</p>' );
		} else {
			$field = new Select( $settings_obj );
			$field->set_options( $external_sources );
			/* translators: %1$s will be replaced by a URL. */
			$field->set_description( '<p>' . sprintf( __( 'Select the external source with export enabled to which all new media files should be assigned. Other external sources with export enabled that are not selected here will also be used, but will not be assigned to the new files. Manage them <a href="%1$s">here</a>.', 'external-files-in-media-library' ), $url ) . '</p>' );
		}
		$field->set_title( __( 'Choose your primary external source', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_sanitize_callback( array( $this, 'sanitize_main_source' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting.
		$setting_local_files = $settings_obj->add_setting( 'eml_export_local_files' );
		$setting_local_files->set_section( $section );
		$setting_local_files->set_default( 1 );
		$field = new Checkbox( $settings_obj );
		$field->set_title( __( 'Export files from media library', 'external-files-in-media-library' ) );
		$field->set_description( __( 'When this option is enabled, you can export files that are not yet stored externally from the media library to external services.', 'external-files-in-media-library' ) );
		$field->add_depend( $setting_export, 1 );
		$field->set_setting( $setting_local_files );
		$setting_local_files->set_field( $field );

		// add the button to export all local files, if an external source is enabled for the export and files could be exported at the moment.
		if ( ! empty( $external_sources ) && '' === $this->get_export_sources_problem() ) {
			$setting = $settings_obj->add_setting( 'eml_export_local_files_now' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );
			$field = new Button( $settings_obj );
			$field->set_title( __( 'Export existing files', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Export now', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Export all local files in your media library, which are not exported yet. They are exported one by one in the background, independent of the settings of the queue. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) );
			$field->add_class( 'easy-dialog-for-wordpress' );
			$field->add_data( 'dialog', Helper::get_json( $this->get_export_local_files_dialog( $this->get_main_source_name( $external_sources ) ) ) );
			$field->add_depend( $setting_export, 1 );
			$field->add_depend( $setting_local_files, 1 );
			$setting->set_field( $field );
		}

		// add the button to export the sizes of exported images, if images without them exist and they could be exported.
		if ( ! empty( $external_sources ) && '' === $this->get_export_sources_problem() && Sizes::get_instance()->has_images_without_sizes() && Sizes::get_instance()->is_export_of_sizes_possible() ) {
			$setting = $settings_obj->add_setting( 'efml_export_sizes_now' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );
			$field = new Button( $settings_obj );
			$field->set_title( __( 'Export sizes of exported images', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Export sizes now', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Images, which have been exported before, get their sizes on the external sources. The sizes are delivered like the images themselves afterward: via the proxy, if it is enabled for images, which loads them from there instead of creating them. The images are processed one by one in the background.', 'external-files-in-media-library' ) );
			$field->add_class( 'easy-dialog-for-wordpress' );
			$field->add_data( 'dialog', Helper::get_json( Sizes::get_instance()->get_dialog() ) );
			$field->add_depend( $setting_export, 1 );
			$setting->set_field( $field );
		}

		// add the button to delete the local copies of exported files, if some exist (also with disabled export).
		if ( $is_settings_request && Local_Copies::get_instance()->has_files() ) {
			$setting = $settings_obj->add_setting( 'efml_delete_local_copies_now' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );
			$field = new Button( $settings_obj );
			$field->set_title( __( 'Delete local copies', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Delete local copies now', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Exported files, whose local files have been kept (setting "Do not delete local files"), are used from their external sources only afterward, to free storage space. Each file is loaded from its external source before, only files, which exist there, are deleted locally. They are processed one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) );
			$field->add_class( 'easy-dialog-for-wordpress' );
			$field->add_data( 'dialog', Helper::get_json( Local_Copies::get_instance()->get_dialog() ) );
			$setting->set_field( $field );
		}

		// add the buttons for local working copies of exported images, e.g. for image optimizers (only in the backend).
		if ( $is_settings_request ) {
			$working_copies_obj = Working_Copies::get_instance();
			if ( $working_copies_obj->count_candidates() > 0 ) {
				$setting = $settings_obj->add_setting( 'efml_create_working_copies_now' );
				$setting->set_section( $section );
				$setting->set_autoload( false );
				$setting->prevent_export( true );
				$field = new Button( $settings_obj );
				$field->set_title( __( 'Prepare exported images for image optimizers', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Save working copies', 'external-files-in-media-library' ) );
				$field->set_description( __( 'Image optimizers and other plugins need the local files of images, e.g. for their bulk optimization. This saves local working copies of all exported images, which are still delivered from their external sources. As soon as an image optimizer reports the end of its work for an image, its changed files are exported and its working copy is removed. With EWWW Image Optimizer, single images get their working copy automatically, if you optimize them in the media library.', 'external-files-in-media-library' ) );
				$field->add_class( 'easy-dialog-for-wordpress' );
				$field->add_data( 'dialog', Helper::get_json( $working_copies_obj->get_create_dialog() ) );
				$setting->set_field( $field );
			}
			$active = $working_copies_obj->count_active();
			if ( $active > 0 ) {
				$setting = $settings_obj->add_setting( 'efml_finish_working_copies_now' );
				$setting->set_section( $section );
				$setting->set_autoload( false );
				$setting->prevent_export( true );
				$field = new Button( $settings_obj );
				$field->set_title( __( 'Export optimized images', 'external-files-in-media-library' ) );
				$field->set_button_title( __( 'Export now', 'external-files-in-media-library' ) );
				/* translators: %1$d will be replaced by a number. */
				$field->set_description( sprintf( _n( '%1$d exported image has a local working copy. Export its changed files and remove the working copy, e.g. after an image optimizer, which does not report the end of its work (like Converter for Media), has finished.', '%1$d exported images have a local working copy. Export their changed files and remove the working copies, e.g. after an image optimizer, which does not report the end of its work (like Converter for Media), has finished.', $active, 'external-files-in-media-library' ), $active ) );
				$field->add_class( 'easy-dialog-for-wordpress' );
				$field->add_data( 'dialog', Helper::get_json( $working_copies_obj->get_finish_dialog() ) );
				$setting->set_field( $field );
			}
		}

		// add the button to restore the exported files, if some exist (also with disabled export).
		if ( $is_settings_request && Restore::get_instance()->has_files() ) {
			$setting = $settings_obj->add_setting( 'efml_restore_exported_files_now' );
			$setting->set_section( $section );
			$setting->set_autoload( false );
			$setting->prevent_export( true );
			$field = new Button( $settings_obj );
			$field->set_title( __( 'Restore exported files', 'external-files-in-media-library' ) );
			$field->set_button_title( __( 'Restore now', 'external-files-in-media-library' ) );
			$field->set_description( __( 'Download all exported files from their external sources and save them as local files at their original path in your media library, e.g. before you deactivate this plugin. The export will be disabled for this. They are restored one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) );
			$field->add_class( 'easy-dialog-for-wordpress' );
			$field->add_data( 'dialog', Helper::get_json( Restore::get_instance()->get_dialog() ) );
			$setting->set_field( $field );
		}

		// add the setting for private files.
		Private_Files::get_instance()->add_settings( $settings_obj, $section, $setting_export );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Export of files', 'external-files-in-media-library' );
	}

	/**
	 * Add a column for hint if export is not enabled.
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
	 * @param string $content     The column content.
	 * @param string $column_name The column name.
	 * @param int    $term_id     The term ID.
	 *
	 * @return string
	 */
	public function add_column_hint_content( string $content, string $column_name, int $term_id ): string {
		// bail if column is not 'efml_export_hint'.
		if ( 'efml_export_hint' !== $column_name ) {
			return $content;
		}

		// get the listings object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
			return $this->get_not_supported_hint( $listing_obj );
		}

		// show a simple hint for users without capability to change settings.
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

		// change the hint for users with necessary capability.
		if ( current_user_can( 'manage_options' ) ) {
			/* translators: %1$s will be replaced by a URL. */
			$dialog['texts'][1] = '<p>' . sprintf( __( 'Enable this option <a href="%1$s">in your settings</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) ) . '</p>';

			// show extended hint for all others.
			return '<a class="dashicons dashicons-editor-help easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" href="' . esc_url( \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_export' ) ) . '"></a>';
		}

		// show a simple hint.
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

		// show the state, if the export is enabled, but the service is not available at the moment (e.g. its extension is not active).
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base || $listing_obj->is_disabled() ) {
			if ( absint( get_term_meta( $term_id, 'efml_export', true ) ) > 0 ) {
				return $this->get_unusable_export_state( $term_id );
			}
		}

		// bail if no object could be found.
		if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
			return $this->get_not_supported_hint( $listing_obj );
		}

		// bail if listing object is disabled.
		if ( $listing_obj->is_disabled() ) {
			return $this->get_not_enabled_hint( $listing_obj );
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
	 * Return the state of an external source with enabled export, whose service is not available at the moment.
	 *
	 * The export could be switched off then, e.g. if the service is not needed anymore.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return string
	 */
	private function get_unusable_export_state( int $term_id ): string {
		// get the reason.
		$problem = $this->get_export_source_problem( $term_id );
		if ( '' === $problem ) {
			$problem = __( 'It is not usable at the moment.', 'external-files-in-media-library' );
		}

		// show the enabled state, which could only be switched off, and the reason.
		return '<div class="eml-switch-toggle"><input id="export-on-' . absint( $term_id ) . '" name="export-states[' . absint( $term_id ) . ']" class="green" data-term-id="' . absint( $term_id ) . '" value="1" type="radio" checked /><label for="export-on-' . absint( $term_id ) . '" class="green">' . esc_html__( 'On', 'external-files-in-media-library' ) . '</label><input id="export-off-' . absint( $term_id ) . '" name="export-states[' . absint( $term_id ) . ']" class="red" type="radio" data-term-id="' . absint( $term_id ) . '" value="0" /><label for="export-off-' . absint( $term_id ) . '" class="red">' . esc_html__( 'Off', 'external-files-in-media-library' ) . '</label></div><p class="efml-export-unusable"><span class="dashicons dashicons-warning"></span> <strong>' . esc_html__( 'Not usable at the moment.', 'external-files-in-media-library' ) . '</strong> ' . wp_kses_post( $problem ) . '</p>';
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

		// get the files, which are assigned to this term.
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

		// create URL to delete them.
		$url_delete = add_query_arg(
			array(
				'action' => 'efml_delete_exported_files',
				'nonce'  => wp_create_nonce( 'efml-exported-synced-files' ),
				'term'   => $term_id,
			),
			get_admin_url() . 'admin.php'
		);

		// create the dialog for delete the link.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Delete exported files?', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Are you sure you want to delete these exported files?', 'external-files-in-media-library' ) . '</strong></p>',
				'<p>' . __( 'The files will be deleted in your media library AND the external source.', 'external-files-in-media-library' ) . '</p>',
				'<p>' . __( 'When the files are deleted, they will be no longer visible or usable on your website.', 'external-files-in-media-library' ) . '</p>',
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
	 * Return list of all files, which are assigned to a given term.
	 *
	 * @param int $term_id The term to filter.
	 *
	 * @return array<int,int>
	 */
	private function get_exported_files_by_term( int $term_id ): array {
		// get all files, which are assigned to this term_id.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => array( 'inherit', 'trash' ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for meta.
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
	 * Add CSS- and JS-files for the backend.
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
				'title_loading'            => __( 'Loading ..', 'external-files-in-media-library' ),
				'text_loading'             => __( 'Please wait for a moment.', 'external-files-in-media-library' ),
			)
		);
	}

	/**
	 * Change export state via AJAX.
	 *
	 * @return void
	 */
	public function export_state_change_via_ajax(): void {
		// check nonce.
		check_ajax_referer( 'efml-export-state-nonce', 'nonce' );

		// bail if capability is not set (the user must be allowed to manage external sources and to use the export).
		if ( ! current_user_can( Init::get_instance()->get_capabilities()['manage_terms'] ) || ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_send_json_error();
		}

		// get term ID.
		$term_id = absint( filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if term ID is not given or the actual user is not allowed to use this external source.
		if ( 0 === $term_id || ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term_id ) ) {
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
	public function set_state_for_term( int $term_id, int $enabled ): void {
		// get the term.
		$term = get_term( $term_id, Taxonomy::get_instance()->get_name() );

		// bail if term could not be loaded.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		// get the main export source setting.
		$main_export_source_term_id = absint( get_option( 'eml_export_main_source' ) );

		// forget the external sources collected for the media library.
		$this->row_action_sources = null;

		// save the enabled state.
		if ( 1 === $enabled ) {
			add_term_meta( $term_id, 'efml_export', time() );

			// if no main export source is set, set this one.
			if ( 0 === $main_export_source_term_id ) {
				update_option( 'eml_export_main_source', $term_id );
			}

			// log event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'The external source %1$s is now enabled for export of files.', 'external-files-in-media-library' ), '<em>' . $term->name . '</em>' ), '', 'info', 2 );
		} else {
			// save the disabled state.
			delete_term_meta( $term_id, 'efml_export' );

			// if this is the main export source, remove it.
			if ( $term_id === $main_export_source_term_id ) {
				update_option( 'eml_export_main_source', 0 );
			}

			// log event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'The external source %1$s is now disabled for export of files.', 'external-files-in-media-library' ), '<em>' . $term->name . '</em>' ), '', 'info', 2 );
		}
	}

	/**
	 * Return list of terms, which are enabled for export.
	 *
	 * @return array<int,int>
	 */
	public function get_export_terms(): array {
		// get the export terms.
		$query = array(
			'taxonomy'     => Taxonomy::get_instance()->get_name(),
			'hide_empty'   => false,
			'count'        => false,
			'meta_key'     => 'efml_export',  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Filter for meta.
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

		// add each "WP_Term" to the list.
		foreach ( $terms->terms as $term_id ) {
			$list[] = absint( $term_id );
		}

		// return resulting list.
		return $list;
	}

	/**
	 * Return the external sources with enabled export, which could be used at the moment.
	 *
	 * External sources are not usable, if the credentials of their service are defined via constants
	 * for another server or if their service is not available (e.g. its extension is not active).
	 * Nothing is written to or deleted on them then.
	 *
	 * @param bool $log True to log each external source, which is not usable.
	 *
	 * @return array<int,int>
	 */
	public function get_usable_export_terms( bool $log = false ): array {
		// collect the usable terms.
		$list = array();
		foreach ( $this->get_export_terms() as $term_id ) {
			// bail if this external source is not usable.
			if ( ! $this->is_export_source_usable( $term_id ) ) {
				// log this event, if requested.
				if ( $log ) {
					$this->log_unusable_source( $term_id );
				}
				continue;
			}

			// add it to the list.
			$list[] = $term_id;
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the reason, why the given external source could not be used for exports at the moment.
	 *
	 * Returns an empty string, if it could be used. The stored settings are not changed, so the external
	 * source is usable again as soon as the reason is gone (e.g. the extension of its service is active again).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return string
	 */
	public function get_export_source_problem( int $term_id ): string {
		// bail if export is not enabled for this external source.
		if ( empty( get_term_meta( $term_id, 'efml_export', true ) ) ) {
			return __( 'The export is not enabled for this external source.', 'external-files-in-media-library' );
		}

		// bail if the credentials of its service are defined via constants for another server.
		if ( Services::get_instance()->is_source_blocked_by_constants( $term_id ) ) {
			return Services::get_instance()->get_blocked_source_hint( $term_id );
		}

		// bail if the services are not initialized yet, as no service is known before (the behavior before 6.0.0 applies then).
		if ( ! Services::get_instance()->is_initialized() ) {
			return '';
		}

		// bail if the service is not available or does not support exports.
		if ( $this->is_service_missing( $term_id ) ) {
			return Services::get_instance()->get_missing_service_hint( (string) get_term_meta( $term_id, 'type', true ) );
		}

		// all fine.
		return '';
	}

	/**
	 * Return whether the service of the given external source is not available at the moment.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return bool
	 */
	private function is_service_missing( int $term_id ): bool {
		// bail if the services are not initialized yet.
		if ( ! Services::get_instance()->is_initialized() ) {
			return false;
		}

		// get the service object.
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

		// return whether it is not available or does not support exports.
		return ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base;
	}

	/**
	 * Return whether the given external source could be used for exports at the moment.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return bool
	 */
	public function is_export_source_usable( int $term_id ): bool {
		// get the result.
		$result = '' === $this->get_export_source_problem( $term_id );

		/**
		 * Filter whether an external source could be used for exports at the moment.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $result True if it could be used.
		 * @param int $term_id The ID of the external source.
		 */
		return apply_filters( 'efml_export_source_usable', $result, $term_id );
	}

	/**
	 * Log that the given external source could not be used for exports.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return void
	 */
	private function log_unusable_source( int $term_id ): void {
		// use the existing message for sources blocked by constants.
		if ( Services::get_instance()->is_source_blocked_by_constants( $term_id ) ) {
			Services::get_instance()->log_blocked_source( $term_id );
			return;
		}

		// log the reason.
		Log::get_instance()->create( $this->get_unusable_source_text( $term_id ), (string) get_term_meta( $term_id, 'path', true ), 'error' );
	}

	/**
	 * Return the text, that the given external source could not be used for exports, with its reason.
	 *
	 * @param int $term_id The ID of the external source.
	 *
	 * @return string
	 */
	private function get_unusable_source_text( int $term_id ): string {
		// get the name of the external source.
		$name = get_term_field( 'name', $term_id, Taxonomy::get_instance()->get_name() );
		if ( ! is_string( $name ) ) {
			$name = (string) $term_id;
		}

		// get the reason.
		$problem = $this->get_export_source_problem( $term_id );
		if ( '' === $problem ) {
			$problem = __( 'It is not usable at the moment.', 'external-files-in-media-library' );
		}

		/* translators: %1$s will be replaced by the name of the external source, %2$s by the reason. */
		return sprintf( __( 'The external source %1$s could not be used for the export: %2$s', 'external-files-in-media-library' ), '<em>' . esc_html( $name ) . '</em>', $problem );
	}

	/**
	 * Return the ID of the main external source for exports.
	 *
	 * Returns 0 if none is set or if the export has been disabled for it.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return int
	 */
	public function get_main_export_source(): int {
		// get the setting.
		$term_id = absint( get_option( 'eml_export_main_source' ) );

		// bail if none is set or its export is not enabled.
		if ( 0 === $term_id || ! in_array( $term_id, $this->get_export_terms(), true ) ) {
			return 0;
		}

		// return the ID.
		return $term_id;
	}

	/**
	 * Return the reason, why files could not be exported at the moment because of the external sources.
	 *
	 * Files are not exported, if no external source with enabled export is usable or if the main
	 * external source for exports is not usable. In the latter case the files are not exported to the
	 * other external sources, as they would otherwise be assigned to one of them unexpectedly.
	 *
	 * Returns an empty string, if files could be exported.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	public function get_export_sources_problem(): string {
		// get the external sources with enabled export.
		$export_terms = $this->get_export_terms();

		// bail if no external source is enabled for the export.
		if ( empty( $export_terms ) ) {
			return __( 'No external source is enabled for the export.', 'external-files-in-media-library' );
		}

		// bail if the main external source is set, but not usable.
		$main_term_id = $this->get_main_export_source();
		if ( $main_term_id > 0 && ! $this->is_export_source_usable( $main_term_id ) ) {
			return $this->get_unusable_source_text( $main_term_id );
		}

		// bail if one external source is usable.
		if ( ! empty( $this->get_usable_export_terms() ) ) {
			return '';
		}

		// collect the reasons of all external sources.
		$reasons = array();
		foreach ( $export_terms as $term_id ) {
			$reasons[] = $this->get_unusable_source_text( $term_id );
		}

		// return the reasons.
		return implode( ' ', $reasons );
	}


	/**
	 * Return whether the actual page in the backend is one, on which the external sources for the export are checked.
	 *
	 * These are the pages, where the hint is relevant: the media library and the upload of files, the plugins (e.g.
	 * after the activation of a missing extension), the external sources and the settings of this plugin.
	 *
	 * @return bool
	 */
	private function is_screen_for_source_checks(): bool {
		// check always, if no page is set (e.g. via WP CLI).
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen instanceof WP_Screen ) {
			return true;
		}

		// check the external sources.
		if ( in_array( $screen->base, array( 'edit-tags', 'term' ), true ) ) {
			return Taxonomy::get_instance()->get_name() === $screen->taxonomy;
		}

		// check the other pages.
		$is_screen = in_array( $screen->base, array( 'upload', 'media', 'plugins', 'settings_page_' . Settings::get_instance()->get_menu_slug() ), true );

		/**
		 * Filter whether the external sources for the export are checked on the actual page in the backend.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool      $is_screen True, if they are checked.
		 * @param WP_Screen $screen    The actual page.
		 */
		return (bool) apply_filters( 'efml_export_check_sources_on_screen', $is_screen, $screen );
	}
	/**
	 * Show a hint in the backend, if files could not be exported because of their external sources.
	 *
	 * Uploaded files stay local files during this time. They could be exported later via the settings.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return void
	 */
	public function add_hint_for_unusable_sources(): void {
		// bail if user has no permission to change it.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// bail if this is not one of the pages, on which the external sources are checked (the check needs queries,
		// the saved hint is shown on all pages until the check removes it).
		if ( ! $this->is_screen_for_source_checks() ) {
			return;
		}

		// get the reason, if the export is enabled and at least one external source is enabled for the export.
		$problem = '';
		if ( $this->is_enabled() && ! empty( $this->get_export_terms() ) ) {
			$problem = $this->get_export_sources_problem();
		}

		// remove the hint, if files could be exported.
		if ( '' === $problem ) {
			Helper::delete_hint( 'efml_export_sources_problem' );
			return;
		}

		// create the message.
		$message  = '<strong>' . __( 'New files in your media library are not exported at the moment.', 'external-files-in-media-library' ) . '</strong> ' . $problem . '<br><br>';
		$message .= __( 'They stay local files. As soon as the reason is gone, you can export them with "Export now" in the export settings.', 'external-files-in-media-library' ) . ' ';
		// name the plugins, if a service is missing.
		foreach ( $this->get_export_terms() as $term_id ) {
			if ( $this->is_service_missing( $term_id ) ) {
				/* translators: %1$s will be replaced by a URL. */
				$message .= sprintf( __( 'Activate the missing extension in <a href="%1$s">your plugins</a> or switch off the export of the external source in your external sources.', 'external-files-in-media-library' ), esc_url( admin_url( 'plugins.php' ) ) ) . ' ';
				break;
			}
		}
		/* translators: %1$s will be replaced by a URL. */
		$message .= sprintf( __( 'Manage <a href="%1$s">your external sources</a>.', 'external-files-in-media-library' ), esc_url( Directory_Listing::get_instance()->get_listing_url() ) );

		// bail if the hint is saved with this message.
		if ( Helper::is_hint_unchanged( 'efml_export_sources_problem', $message ) ) {
			return;
		}

		// add the hint.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_export_sources_problem' );
		$transient_obj->set_message( $message );
		$transient_obj->set_type( 'error' );
		$transient_obj->set_prioritized( true );
		$transient_obj->save();
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
	/**
	 * Export a new uploaded file to the enabled external sources.
	 *
	 * Runs after WordPress created the metadata for the upload, as the export
	 * deletes the local files afterward.
	 *
	 * @param mixed $metadata The metadata WordPress created for this attachment.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return mixed
	 */
	public function export_file_by_upload( mixed $metadata, int $attachment_id ): mixed {
		// bail if sync or import is running.
		if ( defined( 'EFML_URL_IMPORT_RUNNING' ) || $this->is_sync_running() ) {
			return $metadata;
		}

		// use an array for the further handling.
		if ( ! is_array( $metadata ) ) {
			$metadata = array();
		}

		// bail if WordPress works with the local files of an exported image (e.g. to create its sizes again).
		if ( Local_Work::get_instance()->is_open( $attachment_id ) ) {
			return $metadata;
		}

		// bail if this is not an upload, but the metadata of an existing local file are created again (e.g. by a plugin
		// to regenerate the sizes): it stays local, as other plugins could still work with its files.
		if ( ! $this->is_upload( $attachment_id ) ) {
			Log::get_instance()->create( __( 'The metadata of an existing local file have been created again. It is not exported now, use "Export existing files" for it.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 2 );
			return $metadata;
		}

		// log this event.
		Log::get_instance()->create( __( 'Checking if new uploaded file should be exported.', 'external-files-in-media-library' ), '', 'info', 2 );

		// bail if files could not be exported at the moment, the file stays local then.
		$problem = $this->get_export_sources_problem();
		if ( '' !== $problem ) {
			/* translators: %1$s will be replaced by the reason. */
			Log::get_instance()->create( sprintf( __( 'The file has not been exported and stays local: %1$s', 'external-files-in-media-library' ), $problem ), (string) wp_get_attachment_url( $attachment_id ), 'error' );
			return $metadata;
		}

		// bail if the file is not exported at all (e.g. its type is not allowed in the settings): it stays local
		// without waiting or a marker in the media library.
		if ( ! $this->is_exportable( $attachment_id ) ) {
			// log this event, with its reason, if it is known.
			$reason = $this->get_prevent_reason( Files::get_instance()->get_file( $attachment_id ) );
			if ( '' !== $reason ) {
				/* translators: %1$s will be replaced by the reason. */
				Log::get_instance()->create( sprintf( __( 'The uploaded file is not exported and stays local: %1$s', 'external-files-in-media-library' ), $reason ), (string) wp_get_attachment_url( $attachment_id ), 'info' );
			} else {
				Log::get_instance()->create( __( 'The uploaded file is not exported, as its type is not allowed in the settings or its export is prevented.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 2 );
			}
			return $metadata;
		}

		// wait with the export, if the sizes of this upload are created in the browser (they are not available yet).
		$client_uploads_obj = Client_Uploads::get_instance();
		if ( $client_uploads_obj->is_client_upload() ) {
			$client_uploads_obj->wait_for_finalization( $attachment_id );
			return $metadata;
		}

		// the upload does not wait anymore, e.g. as its sizes have been finalized now.
		$client_uploads_obj->end_waiting( $attachment_id );

		// wait with the export, if other plugins work with the local files of uploads (e.g. image optimizers).
		$delay = Delayed_Uploads::get_instance()->get_delay( $attachment_id );
		if ( $delay > 0 ) {
			Delayed_Uploads::get_instance()->schedule( $attachment_id, $delay );
			return $metadata;
		}

		// add the file to the queue, if the export should run in background.
		if ( $this->is_export_in_background_enabled() && Queue::get_instance()->add_export( $attachment_id ) ) {
			// return the metadata unchanged, as the file stays local until it has been exported.
			return $metadata;
		}

		// add a large file to the queue, so the upload is not interrupted by time limits of the web server during the
		// export.
		if ( $this->is_large_file( $attachment_id ) && Queue::get_instance()->add_export( $attachment_id ) ) {
			/* translators: %1$s will be replaced by a file size, e.g. "50 MB". */
			Log::get_instance()->create( sprintf( __( 'The file is larger than %1$s. It is exported in the background right away, so the upload is not interrupted by time limits of the web server.', 'external-files-in-media-library' ), size_format( $this->get_large_file_size() ) ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );

			// return the metadata unchanged, as the file stays local until it has been exported.
			return $metadata;
		}

		// save the metadata now, so the cleanup during the export knows which local files exist.
		wp_update_attachment_metadata( $attachment_id, $metadata );

		// bail if the file has not been exported: try it again later via the queue, if it has failed.
		if ( ! $this->export_file( $attachment_id ) ) {
			$this->retry_failed_upload_export( $attachment_id );
			return $metadata;
		}

		// remove the sizes, as their local files have been deleted during the export.
		unset( $metadata['sizes'] );

		// return the metadata WordPress will save now.
		return $metadata;
	}

	/**
	 * Return the reason, why the export of the given file is prevented (e.g. its size), for the log and the media
	 * library, or an empty string, if it is unknown.
	 *
	 * @param File $external_file_obj The file object.
	 * @param int  $term_id           The ID of the external source, if the export to it is prevented, otherwise 0.
	 *
	 * @return string
	 */
	public function get_prevent_reason( File $external_file_obj, int $term_id = 0 ): string {
		$reason = '';

		/**
		 * Filter the reason, why the export of a file is prevented via the hook "efml_prevent_export" or
		 * "efml_prevent_export_on_service". It is shown in the log.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $reason            The reason (a sentence) or an empty string.
		 * @param File   $external_file_obj The file object.
		 * @param int    $term_id           The ID of the external source or 0.
		 */
		return (string) apply_filters( 'efml_export_prevent_reason', $reason, $external_file_obj, $term_id );
	}

	/**
	 * Mark the given attachment as upload, whose metadata could be exported via the hook
	 * "wp_generate_attachment_metadata" in this request (e.g. as it has been added to the media library now).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function mark_as_upload( int $attachment_id ): void {
		$this->uploads[ $attachment_id ] = true;
	}

	/**
	 * Return whether the metadata of the given attachment are created for an upload, which should be exported: it has
	 * been added in this request, it is marked as upload or it waits for its export (e.g. for the sizes created in the
	 * browser or for an image optimizer).
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_upload( int $attachment_id ): bool {
		// check the attachment.
		$is_upload = isset( $this->uploads[ $attachment_id ] ) || Client_Uploads::get_instance()->is_waiting( $attachment_id ) || Delayed_Uploads::get_instance()->is_waiting( $attachment_id );

		/**
		 * Filter whether the metadata of an attachment are created for an upload, which is exported then.
		 *
		 * Return true to export also existing local files, whose metadata are created again (e.g. by a plugin to
		 * regenerate the sizes of images), like before version 6.0.0.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $is_upload     True if this is an upload.
		 * @param int  $attachment_id The attachment ID.
		 */
		return (bool) apply_filters( 'efml_export_is_upload', $is_upload, $attachment_id );
	}

	/**
	 * Run export of a given single file.
	 *
	 * This is the main handling to request the export through an "Export_Base" object of external sources.
	 *
	 * Loops through all external sources with enabled export option. The last one is the main export target.
	 *
	 * If successfully the file will be converted to an external @File object.
	 *
	 * @param int $attachment_id The attachment ID to export.
	 *
	 * @return bool Return true if the file export was successfully, false it not.
	 */
	public function export_file( int $attachment_id ): bool {
		// bail if this website is a copy (e.g. a staging site), as it must not write to the external sources.
		if ( Site_Copy::get_instance()->is_blocked( __( 'Export of file has been skipped.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ) ) ) {
			return false;
		}

		// get external file object for the given attachment ID.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is already an external file.
		if ( $external_file_obj->is_valid() ) {
			// log this event.
			Log::get_instance()->create( __( 'Given file for export is already saved as external file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		// bail if mime is not allowed.
		if ( ! $external_file_obj->is_mime_type_allowed() ) {
			// log this event.
			Log::get_instance()->create( __( 'Given file uses a mime type, not allowed according to the plugin settings.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'error' );

			// do nothing more.
			return false;
		}

		$false = false;
		/**
		 * Filter whether the given file should be exported.
		 *
		 * @since 5.0.0 Available since 5.0.0.
		 * @param bool $false True for prevent the export.
		 * @param File $external_file_obj The external file object to use.
		 */
		if ( apply_filters( 'efml_prevent_export', $false, $external_file_obj ) ) {
			// log this event, with its reason, if it is known.
			$reason = $this->get_prevent_reason( $external_file_obj );
			if ( '' !== $reason ) {
				/* translators: %1$s will be replaced by the reason. */
				Log::get_instance()->create( sprintf( __( 'The file has not been exported: %1$s', 'external-files-in-media-library' ), $reason ), (string) wp_get_attachment_url( $attachment_id ), 'info' );
			} else {
				Log::get_instance()->create( __( 'Export of file has been prevented.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );
			}

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

		// bail if files could not be exported because of the external sources (e.g. the main external source is not usable).
		$problem = $this->get_export_sources_problem();
		if ( '' !== $problem ) {
			/* translators: %1$s will be replaced by the reason. */
			Log::get_instance()->create( sprintf( __( 'The file has not been exported and stays local: %1$s', 'external-files-in-media-library' ), $problem ), $external_file_obj->get_url( true ), 'error' );
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
		Log::get_instance()->create( __( 'Check, which service could be used to export the given file.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2 );

		// get the term IDs of all export sources.
		$export_source_terms_list = $this->get_usable_export_terms( true );

		// move the main export source on last place in an array to assign the resulting file to this external source.
		$export_source_terms        = array();
		$main_export_source_term_id = absint( get_option( 'eml_export_main_source' ) );
		foreach ( $export_source_terms_list as $term_id ) {
			// bail if this is the main export source.
			if ( $main_export_source_term_id === $term_id ) {
				continue;
			}
			$export_source_terms[] = $term_id;
		}
		// add the main export source, if it is usable.
		if ( $main_export_source_term_id > 0 && in_array( $main_export_source_term_id, $export_source_terms_list, true ) ) {
			$export_source_terms[] = $main_export_source_term_id;
		}

		// create result marker.
		$successfully_exported = false;

		// get the time of this export (the same for all external sources).
		$export_time = time();

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

			$false = false;
			/**
			 * Filter whether the given file should be exported.
			 *
			 * @since 5.0.0 Available since 5.0.0.
			 * @param bool $false True for prevent the export.
			 * @param File $external_file_obj The external file object to use.
			 * @param int $term_id The ID of the external source.
			 */
			if ( apply_filters( 'efml_prevent_export_on_service', $false, $external_file_obj, $term_id ) ) {
				// log this event, with its reason, if it is known.
				$reason = $this->get_prevent_reason( $external_file_obj, $term_id );
				if ( '' !== $reason ) {
					/* translators: %1$s will be replaced by the name of an external source, %2$s by the reason. */
					Log::get_instance()->create( sprintf( __( 'The file has not been exported to %1$s: %2$s', 'external-files-in-media-library' ), $term_name, $reason ), (string) wp_get_attachment_url( $attachment_id ), 'info' );
				} else {
					/* translators: %1$s will be replaced by a title. */
					Log::get_instance()->create( sprintf( __( 'Export of file via %1$s has been prevented.', 'external-files-in-media-library' ), $term_name ), $external_file_obj->get_url( true ), 'info', 2 );
				}

				// do nothing more.
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

			// check the configured URL, if required for the export object.
			if ( $export_obj->is_url_required() ) {
				$base_url = get_term_meta( $term_id, 'efml_export_url', true );

				// bail if no URL is given.
				if ( empty( $base_url ) ) {
					continue;
				}
			}

			// get the base URL for each file on this external source.
			$term_url = get_term_meta( $term_id, 'path', true );

			// get the folder for this file on this external source (empty, if not supported or not enabled).
			$folder = $export_obj->supports_folders() ? $this->get_export_folder( $attachment_id, $export_time ) : '';

			// create the export path of this file.
			$export_path = trailingslashit( $term_url ) . ( '' !== $folder ? trailingslashit( $folder ) : '' ) . basename( $file );

			// get credentials, with the ID of the external source (e.g. to use the connection of its owner).
			$credentials            = Taxonomy::get_instance()->get_entry( $term_id );
			$credentials['term_id'] = $term_id;

			// get whether the file should be saved privately on this external source.
			$private_file = $this->is_private_export( $term_id, $export_obj );

			// tell the export object, whether the file should be saved privately.
			$credentials['private'] = $private_file;

			// log this event.
			/* translators: %1$s will be replaced by the service title. */
			Log::get_instance()->create( sprintf( __( 'Exporting file to external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $export_path, 'info', 2 );

			// export the file via the given export object and get its external public URL if it was successfully.
			$url = $export_obj->export_file( $attachment_id, $export_path, $credentials );
			if ( ! is_string( $url ) ) {
				// log this event.
				/* translators: %1$s will be replaced by the service title. */
				Log::get_instance()->create( sprintf( __( 'File could not be exported to external source %1$s.', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $export_path, 'error' );

				// do nothing more.
				continue;
			}

			// remove the filter.
			if ( method_exists( $listing_obj, 'disable_check_for_unsafe_urls' ) ) {
				remove_filter( 'efml_http_header_args', array( $listing_obj, 'disable_check_for_unsafe_urls' ) );
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

			// export the sizes and the original image of an image (before its local files are deleted).
			Sizes::get_instance()->export_sizes( $attachment_id, $export_obj, $term_id, $export_path, $credentials );

			// save the attachment as exported external file.
			$this->save_as_exported( $external_file_obj, $url, $listing_obj->get_name(), basename( $file ), $private_file, $fields, $term_id, $folder );

			// log this event.
			/* translators: %1$s will be replaced by the external source title. */
			Log::get_instance()->create( sprintf( __( 'File has been exported to the external source %1$s', 'external-files-in-media-library' ), '<em>' . $term_name . '</em>' ), $url, 'success' );
		}

		// reset the cached statistics, as this file is now an external hosted file.
		if ( $successfully_exported ) {
			\ExternalFilesInMediaLibrary\Plugin\Statistics::get_instance()->clear_sizes_per_service_cache();
		}

		// return the result.
		return $successfully_exported;
	}

	/**
	 * Save the given attachment as exported external file.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param File                $external_file_obj The external file object of the attachment.
	 * @param string              $url               The URL of the file on the external source.
	 * @param string              $service_name      The name of the service.
	 * @param string              $file_name         The name of the file.
	 * @param bool                $private_file      Whether the file is saved privately.
	 * @param array<string,mixed> $fields            The fields of the external source.
	 * @param int                 $term_id           The ID of the external source.
	 * @param string              $folder            The folder on the external source (empty for none).
	 *
	 * @return void
	 */
	public function save_as_exported( File $external_file_obj, string $url, string $service_name, string $file_name, bool $private_file, array $fields, int $term_id, string $folder = '' ): void {
		// save the local paths of the file, so its local URLs in contents could be replaced (before its URL is changed).
		Local_Urls::get_instance()->save_local_paths( $external_file_obj->get_id() );

		// mark this attachment as one of our own plugin through setting the URL.
		$external_file_obj->set_url( $url );

		// set the used service.
		$external_file_obj->set_service_name( $service_name );

		// set the title.
		$external_file_obj->set_title( $file_name );

		// set availability-status (true is for 'is available', false if it is not).
		$external_file_obj->set_availability( true );

		// mark if this file is an external file locally saved.
		$external_file_obj->set_is_local_saved( false );

		// mark if this file is saved privately on the external source.
		$external_file_obj->set_private( $private_file );

		// save the credentials on the object, if set.
		$external_file_obj->set_fields( $fields );

		// set date of import (this is not the attachment datetime).
		$external_file_obj->set_date();

		// assign the file to this term.
		wp_set_object_terms( $external_file_obj->get_id(), $term_id, Taxonomy::get_instance()->get_name() );

		// mark as exported file.
		update_post_meta( $external_file_obj->get_id(), 'eml_exported_file', time() );

		// save the used filename, so it will not be used for other exported files.
		update_post_meta( $external_file_obj->get_id(), 'efml_export_filename', $file_name );

		// remove the markers of the export via queue.
		delete_post_meta( $external_file_obj->get_id(), 'efml_export_queued' );
		delete_post_meta( $external_file_obj->get_id(), 'efml_export_failed' );

		// forget whether local copies exist, if they are kept.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			Local_Copies::get_instance()->reset_cache();
		}

		// get the metadata.
		$meta_data = wp_get_attachment_metadata( $external_file_obj->get_id() );
		if ( ! is_array( $meta_data ) ) {
			$meta_data = array();
		}

		// remove the sizes, as their local files will be deleted.
		unset( $meta_data['sizes'] );

		// save the metadata - this triggers update_attachment_metadata(), which deletes the local files.
		wp_update_attachment_metadata( $external_file_obj->get_id(), $meta_data );

		// point the attachment at the external URL - after the local files have been removed.
		update_post_meta( $external_file_obj->get_id(), '_wp_attached_file', $url );

		// add this term to the list of export sources of this file.
		$export_sources = get_post_meta( $external_file_obj->get_id(), 'efml_export_sources', true );
		if ( ! is_array( $export_sources ) ) {
			$export_sources = array();
		}
		$export_sources[] = $term_id;
		update_post_meta( $external_file_obj->get_id(), 'efml_export_sources', $export_sources );

		// save the used folder on this external source, so the file could be deleted there later.
		if ( '' !== $folder ) {
			$folders = get_post_meta( $external_file_obj->get_id(), 'efml_export_folders', true );
			if ( ! is_array( $folders ) ) {
				$folders = array();
			}
			$folders[ $term_id ] = $folder;
			update_post_meta( $external_file_obj->get_id(), 'efml_export_folders', $folders );
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

		// bail during the uninstallation or the reset of this plugin, as it must never delete the files on the external sources.
		if ( defined( 'EFML_DEINSTALLATION_RUNNING' ) ) {
			return;
		}

		// get external file object for the given attachment ID.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if this is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return;
		}

		// get the external sources this file has been exported to.
		$export_sources = $this->get_export_sources_of_file( $attachment_id );

		// bail if this file has not been exported by us. Without this check any deleted external file
		// would result in the deletion of a file with the same name on each external source.
		if ( empty( $export_sources ) ) {
			return;
		}

		// bail if this website is a copy (e.g. a staging site), as it must not delete files on the external sources.
		if ( Site_Copy::get_instance()->is_blocked( __( 'Deletion of the exported file on the external source has been skipped.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ) ) ) {
			return;
		}

		// get the file name.
		$file = get_attached_file( $attachment_id, true );

		// bail if file name could not be loaded.
		if ( ! is_string( $file ) ) {
			return;
		}

		// delete the files on each external source the file has been exported to, independent of whether the export
		// is enabled for it: files, which could not be deleted now, are deleted later via the queue.
		$successfully_deleted = true;
		foreach ( $export_sources as $term_id ) {
			if ( ! Deletion::get_instance()->delete_on_source( $attachment_id, absint( $term_id ), basename( $file ) ) ) {
				$successfully_deleted = false;
			}
		}

		// cleanup after a successfully deletion.
		if ( $successfully_deleted ) {
			$this->cleanup_exported_file( $attachment_id );
			delete_post_meta( $attachment_id, 'efml_former_export_sources' );
			delete_post_meta( $attachment_id, 'efml_export_folders' );
			delete_post_meta( $attachment_id, 'efml_private_file' );
		}
	}

	/**
	 * Return the state of the export of the given local file: "pending" if it waits for its export (in the queue,
	 * for other plugins or for its sizes from the browser), "failed" if its export has failed, otherwise empty.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_export_state( int $attachment_id ): string {
		// bail if this is an external file.
		if ( Files::get_instance()->get_file( $attachment_id )->is_valid() ) {
			return '';
		}

		// return "failed", if its export via the queue has failed.
		if ( absint( get_post_meta( $attachment_id, 'efml_export_failed', true ) ) > 0 ) {
			return 'failed';
		}

		// return "pending", if it waits for its export.
		if ( absint( get_post_meta( $attachment_id, 'efml_export_queued', true ) ) > 0 || Delayed_Uploads::get_instance()->is_waiting( $attachment_id ) || Client_Uploads::get_instance()->is_waiting( $attachment_id ) ) {
			return 'pending';
		}

		// return empty string for any other state.
		return '';
	}

	/**
	 * Return the reason, why the given local file could not be exported again at the moment (empty if it could).
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_export_again_problem( int $attachment_id ): string {
		// bail if the export is disabled.
		if ( ! $this->is_enabled() ) {
			return __( 'The export is disabled.', 'external-files-in-media-library' );
		}

		// bail if the file does not exist or does not wait for its export and its export has not failed.
		if ( 'attachment' !== get_post_type( $attachment_id ) || '' === $this->get_export_state( $attachment_id ) ) {
			return __( 'The file does not wait for its export and its export has not failed.', 'external-files-in-media-library' );
		}

		// bail if the file is not exported at all (e.g. its type is not allowed in the settings).
		if ( ! $this->is_exportable( $attachment_id ) ) {
			return __( 'The file is not exported, as its type is not allowed in the settings or its export is prevented.', 'external-files-in-media-library' );
		}

		// return the reason, if the external sources could not be used at the moment.
		return $this->get_export_sources_problem();
	}

	/**
	 * Export the given local file again right away, whose export has failed or which waits for its export.
	 *
	 * An upload, which waits for other plugins (e.g. image optimizers), is exported without waiting. An upload, which
	 * waits for its sizes from the browser, gets its missing sizes from the server before. If the export fails again,
	 * the file keeps its state: a file in the queue is tried again there, a failed file stays failed. A waiting upload
	 * is added to the queue then, like an upload whose export has failed.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool True if the file has been exported.
	 */
	public function export_again( int $attachment_id ): bool {
		// get the URL of the file for the log.
		$url = (string) wp_get_attachment_url( $attachment_id );

		// bail if it could not be exported at the moment.
		$problem = $this->get_export_again_problem( $attachment_id );
		if ( '' !== $problem ) {
			/* translators: %1$s will be replaced by the reason. */
			Log::get_instance()->create( sprintf( __( 'The file could not be exported again: %1$s', 'external-files-in-media-library' ), $problem ), $url, 'error' );
			return false;
		}

		// log this event.
		Log::get_instance()->create( __( 'The file is exported again by request.', 'external-files-in-media-library' ), $url, 'info', 1 );

		// remember whether the file has failed before.
		$failed = 'failed' === $this->get_export_state( $attachment_id );

		// create the missing sizes of an upload, whose sizes should have been created in the browser: this runs its
		// export like after an upload.
		$client_uploads_obj = Client_Uploads::get_instance();
		if ( $client_uploads_obj->is_waiting( $attachment_id ) ) {
			$client_uploads_obj->export_unfinalized_upload( $attachment_id );
		}

		// end the waiting for other plugins (e.g. image optimizers).
		$delayed_uploads_obj = Delayed_Uploads::get_instance();
		$was_waiting         = $delayed_uploads_obj->is_waiting( $attachment_id );
		$delayed_uploads_obj->end_waiting( $attachment_id );

		// export the file, if it is still a local file.
		$result = Files::get_instance()->get_file( $attachment_id )->is_valid() || $this->export_file( $attachment_id );

		// bail if it has been exported: remove its entries in the queue.
		if ( $result ) {
			Queue::get_instance()->remove_export_entries_of_attachment( $attachment_id );
			return true;
		}

		// add an upload, which waited for other plugins and is not in the queue, to the queue, so it is tried again.
		if ( ! $failed && $was_waiting && ! Queue::get_instance()->has_export_entry( $attachment_id ) ) {
			$this->retry_failed_upload_export( $attachment_id );
		}

		// return false as the file could not be exported.
		return false;
	}

	/**
	 * Export a local file again by request.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_again_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-export-again', 'nonce' );

		// get referer (the media library, if it is not known).
		$referer = (string) wp_get_referer();
		if ( '' === $referer ) {
			$referer = admin_url( 'upload.php' );
		}

		// get the post ID.
		$attachment_id = absint( filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT ) );

		// bail if cap is missing.
		if ( 0 === $attachment_id || ! current_user_can( 'efml_cap_tools_export' ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// export the file.
		$result = $this->export_again( $attachment_id );

		// show the result.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_export_again' );
		if ( $result ) {
			$transient_obj->set_message( '<strong>' . __( 'The file has been exported.', 'external-files-in-media-library' ) . '</strong> ' . __( 'It is now stored in the external source and used from there.', 'external-files-in-media-library' ) );
			$transient_obj->set_type( 'success' );
		} else {
			$message = '<strong>' . __( 'The file could not be exported.', 'external-files-in-media-library' ) . '</strong>';
			if ( 'pending' === $this->get_export_state( $attachment_id ) ) {
				$message .= ' ' . __( 'It waits in the queue and is tried again automatically.', 'external-files-in-media-library' );
			}
			if ( current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() ) ) {
				/* translators: %1$s will be replaced by a URL. */
				$message .= ' ' . sprintf( __( 'Check <a href="%1$s">the log</a> to see what happened.', 'external-files-in-media-library' ), esc_url( $this->get_errors_url( $attachment_id ) ) );
			}
			$transient_obj->set_message( $message );
			$transient_obj->set_type( 'error' );
		}
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Return the URL to export the given local file again.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_export_again_url( int $attachment_id ): string {
		return add_query_arg(
			array(
				'action' => 'efml_export_again',
				'post'   => $attachment_id,
				'nonce'  => wp_create_nonce( 'efml-export-again' ),
			),
			get_admin_url() . 'admin.php'
		);
	}

	/**
	 * Return the URL of the log with the errors of the export of the given local file.
	 *
	 * The log is filtered by the name of the file, as the entries of the export use its local URL, its local path or
	 * its URL on the external source.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_errors_url( int $attachment_id ): string {
		return add_query_arg( array( 'errors' => 1 ), Helper::get_log_url( wp_basename( (string) wp_get_attachment_url( $attachment_id ) ) ) );
	}

	/**
	 * Return whether the given local file could be exported at all: not on a copy of the website, not for a type of
	 * file, which is not allowed in the settings, and not if the export is prevented via the hook "efml_prevent_export".
	 *
	 * A pure check without log entries, e.g. before a file waits for its export or is added to the queue.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_exportable( int $attachment_id ): bool {
		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $attachment_id );

		// bail if it is an external file already, this website is a copy or its type is not allowed.
		if ( $external_file_obj->is_valid() || Site_Copy::get_instance()->is_copy() || ! $external_file_obj->is_mime_type_allowed() ) {
			return false;
		}

		// return whether the export is prevented (the hook is used during the export, too).
		$false = false;
		return ! apply_filters( 'efml_prevent_export', $false, $external_file_obj );
	}

	/**
	 * Add an upload to the queue, if its export has failed, so it is tried again (e.g. after a timeout or a rate limit
	 * of the service, which happens if many files are uploaded at once).
	 *
	 * Not if the export has been prevented on purpose: on a copy of the website, for a not allowed type of file or
	 * by the hook "efml_prevent_export".
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	private function retry_failed_upload_export( int $attachment_id ): void {
		// bail if it has been exported or the export has been prevented on purpose.
		if ( ! $this->is_exportable( $attachment_id ) ) {
			return;
		}

		// add the file to the queue.
		if ( ! Queue::get_instance()->add_export( $attachment_id, 5 * MINUTE_IN_SECONDS ) ) {
			return;
		}

		// log this event.
		Log::get_instance()->create( __( 'The export of the uploaded file has failed. It has been added to the queue and will be tried again.', 'external-files-in-media-library' ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );
	}

	/**
	 * Return the list of external sources (as term IDs) a file has been exported to.
	 *
	 * This also returns the external sources of a file, which has been switched to local hosting
	 * after its export, as its exported copy still exists on the external source.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<int,int>
	 */
	private function get_export_sources_of_file( int $attachment_id ): array {
		// if this file is not marked as exported, use the list we saved during the switch to local hosting.
		if ( 0 === absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) ) {
			$former_export_sources = get_post_meta( $attachment_id, 'efml_former_export_sources', true );
			return is_array( $former_export_sources ) ? array_values( array_map( 'absint', $former_export_sources ) ) : array();
		}

		// get the external sources this file has been exported to.
		$export_sources = get_post_meta( $attachment_id, 'efml_export_sources', true );
		if ( ! is_array( $export_sources ) ) {
			$export_sources = array();
		}

		// fallback for files without this list: use the external sources this file is assigned to.
		if ( empty( $export_sources ) ) {
			$assigned_terms = wp_get_object_terms( $attachment_id, Taxonomy::get_instance()->get_name(), array( 'fields' => 'ids' ) );
			if ( is_array( $assigned_terms ) ) {
				$export_sources = $assigned_terms;
			}
		}

		// return the list.
		return array_values( array_map( 'absint', $export_sources ) );
	}

	/**
	 * Add a filter during sync to prevent import of exported external files.
	 *
	 * @param string              $url The used URL.
	 * @param array<string,mixed> $term_data The term data.
	 * @param int                 $term_id The used term ID for sync.
	 *
	 * @return void
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_sync_filter( string $url, array $term_data, int $term_id ): void {
		// set marker for running sync to prevent export of synced files.
		$this->sync_running = true;

		// bail if this external source has never been used for exports (no URL for exported files is set).
		if ( empty( get_term_meta( $term_id, 'efml_export_url', true ) ) ) {
			$this->sync_term_id           = 0;
			$this->exported_files_of_sync = null;
			return;
		}

		// remember the external source, as its exported files are not synchronized (in any directory of it).
		if ( $this->sync_term_id !== $term_id || $this->sync_url !== $url ) {
			$this->exported_files_of_sync = null;
		}
		$this->sync_term_id = $term_id;
		$this->sync_url     = $url;

		// use hooks.
		add_filter( 'efml_external_file_infos', array( $this, 'prevent_sync_of_exported_file' ), 20, 2 );
	}

	/**
	 * Sanitize the main export source: allow only external sources with enabled export.
	 *
	 * The options of the field contain only the external sources, which had the export enabled while the
	 * settings were loaded. So it does not use them, otherwise a source enabled in the actual request
	 * could not be saved as main export source.
	 *
	 * @param mixed $value The value.
	 *
	 * @return int
	 */
	public function sanitize_main_source( mixed $value ): int {
		// get the term ID.
		$term_id = absint( $value );

		// bail if no source is set.
		if ( 0 === $term_id ) {
			return 0;
		}

		// return the term ID only if the export is enabled for it.
		return empty( get_term_meta( $term_id, 'efml_export', true ) ) ? 0 : $term_id;
	}

	/**
	 * Remove the marker and the filter of the synchronization after it (or a part of it in the background) has ended.
	 *
	 * Otherwise, the following exports and deletions of exported files in the same request (e.g. in the queue via
	 * cron or in WP CLI) would be skipped, as if the synchronization was still running.
	 *
	 * @return void
	 */
	public function remove_sync_filter(): void {
		$this->sync_running           = false;
		$this->sync_term_id           = 0;
		$this->sync_url               = '';
		$this->exported_files_of_sync = null;
		remove_filter( 'efml_external_file_infos', array( $this, 'prevent_sync_of_exported_file' ), 20 );
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
		// bail if this is not an exported file.
		if ( ! $this->is_exported_file_of_sync( $url ) ) {
			return $results;
		}

		// log this event.
		Log::get_instance()->create( __( 'The file has been exported to this external source and is not synchronized.', 'external-files-in-media-library' ), $url, 'info', 2 );

		// return empty array as this is an exported file.
		return array();
	}

	/**
	 * Return whether the given URL is a file, which has been exported to the actually synchronized external source.
	 *
	 * This includes the sizes and the original image of exported images. The files are compared with their URL and
	 * with their path in the external source (e.g. "2026/11/image.jpg"), as the URL of an exported file could be
	 * another than the URL used by the synchronization (e.g. its public URL).
	 *
	 * @param string $url The URL of the file to synchronize.
	 *
	 * @return bool
	 */
	public function is_exported_file_of_sync( string $url ): bool {
		// bail if no synchronization is running.
		if ( 0 === $this->sync_term_id ) {
			return false;
		}

		// get the exported files of this external source.
		$exported_files = $this->get_exported_files_of_sync();

		// return true, if the URL is the URL of an exported file.
		if ( isset( $exported_files['urls'][ $url ] ) ) {
			return true;
		}

		// return whether the path of the file in the external source is the path of an exported file.
		$path = $this->get_path_in_sync_source( $url );
		return '' !== $path && isset( $exported_files['paths'][ $path ] );
	}

	/**
	 * Return the path of the given URL relative to the actually synchronized external source.
	 *
	 * @param string $url The URL of the file.
	 *
	 * @return string
	 */
	private function get_path_in_sync_source( string $url ): string {
		// get the protocol object of the external source.
		$protocol_obj = Protocols::get_instance()->get_protocol_object_for_url( $this->sync_url );

		// bail if no protocol could be found.
		if ( ! $protocol_obj ) {
			return '';
		}

		// get the directory of the file in the external source.
		$directory = $protocol_obj->get_relative_directory( $url );

		// return the path with the file name.
		return ( '' !== $directory ? trailingslashit( $directory ) : '' ) . wp_basename( $url );
	}

	/**
	 * Return the URLs and the paths of the files, which have been exported to the actually synchronized external source.
	 *
	 * @return array{urls: array<string,bool>, paths: array<string,bool>}
	 */
	private function get_exported_files_of_sync(): array {
		// return the list, if it has already been loaded.
		if ( is_array( $this->exported_files_of_sync ) ) {
			return $this->exported_files_of_sync;
		}

		// collect the URLs and the paths.
		$urls  = array();
		$paths = array();
		foreach ( $this->get_exported_files_by_term( $this->sync_term_id ) as $attachment_id ) {
			// get the URL of the exported file.
			$file_url = (string) get_post_meta( $attachment_id, EFML_POST_META_URL, true );
			if ( '' !== $file_url ) {
				$urls[ $file_url ] = true;
			}

			// get the folder of the file on this external source.
			$folders = get_post_meta( $attachment_id, 'efml_export_folders', true );
			$folder  = is_array( $folders ) && ! empty( $folders[ $this->sync_term_id ] ) ? trailingslashit( (string) $folders[ $this->sync_term_id ] ) : '';

			// get the file name of the exported file.
			$file_name = (string) get_post_meta( $attachment_id, 'efml_export_filename', true );
			if ( '' === $file_name && '' !== $file_url ) {
				$file_name = wp_basename( $file_url );
			}
			if ( '' !== $file_name ) {
				$paths[ ltrim( $folder . $file_name, '/' ) ] = true;
			}

			// add the sizes and the original image of exported images (also former ones, which are kept).
			$sizes_data = Sizes::get_instance()->get_data( $attachment_id );
			$entries    = array_merge( (array) ( $sizes_data['terms'][ $this->sync_term_id ] ?? array() ), (array) ( $sizes_data['former'][ $this->sync_term_id ] ?? array() ) );
			foreach ( $entries as $entry ) {
				// add its URLs.
				foreach ( array( 'url', 'target' ) as $key ) {
					if ( ! empty( $entry[ $key ] ) ) {
						$urls[ (string) $entry[ $key ] ] = true;
					}
				}

				// add its path.
				if ( ! empty( $entry['file'] ) ) {
					$paths[ ltrim( $folder . wp_basename( (string) $entry['file'] ), '/' ) ] = true;
				}
			}
		}

		// save the list for this synchronization.
		$this->exported_files_of_sync = array(
			'urls'  => $urls,
			'paths' => $paths,
		);

		// return the list.
		return $this->exported_files_of_sync;
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
	 * @param WP_Term              $term The requested "WP_Term" object.
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

		// get referer.
		$referer = wp_get_referer();

		// bail if cap is missing.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// get the term ID from request.
		$term_id = absint( filter_input( INPUT_GET, 'term', FILTER_SANITIZE_NUMBER_INT ) );

		// if referer is false, set empty string.
		if ( ! $referer ) {
			$referer = '';
		}

		// bail if no term is given or the actual user is not allowed to use this external source.
		if ( 0 === $term_id || ! Directory_Listing::get_instance()->is_source_allowed_for_current_user( $term_id ) ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// get all files, which are assigned to this term.
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
	 * Return the "Save_Base" object by given type name, optionally also filtered.
	 *
	 * @param string $type The given type name.
	 *
	 * @return object|false
	 */
	public function get_service_object_by_type( string $type ): object|false {
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
		// show the hint, if this website is a copy.
		$site_copy_hint = Site_Copy::get_instance()->get_settings_hint();
		if ( '' !== $site_copy_hint ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post( $site_copy_hint ) . '</p></div>';
		}

		// show the hint, if files could not be exported at the moment, although external sources are enabled for it.
		if ( $this->is_enabled() && ! empty( $this->get_export_terms() ) ) {
			$problem = $this->get_export_sources_problem();
			if ( '' !== $problem ) {
				echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'New files in your media library are not exported at the moment.', 'external-files-in-media-library' ) . '</strong> ' . wp_kses_post( $problem ) . '</p></div>';
			}
		}

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
		// do not remove the local files, while WordPress works with them (e.g. to create the sizes again).
		$local_work_obj = Local_Work::get_instance();
		if ( $local_work_obj->is_open( $attachment_id ) ) {
			// bail if WordPress has not finished its work.
			if ( ! $local_work_obj->is_exported( $attachment_id ) ) {
				return $data;
			}

			// remove the local files and the sizes from the metadata, as their files are exported now.
			$local_work_obj->close( $attachment_id, false );
			if ( 1 !== absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
				unset( $data['sizes'] );
			}
			return $data;
		}

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

		// bail if this file has been taken over from another offload plugin, as its local files are kept.
		if ( ! empty( get_post_meta( $external_file_obj->get_id(), 'efml_migration', true ) ) ) {
			return $data;
		}

		// bail if the image has a local working copy (e.g. for image optimizers): its files are used locally.
		if ( Working_Copies::get_instance()->is_active( $attachment_id ) ) {
			return $data;
		}

		// bail if the deletion is prevented.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			return $data;
		}

		// get the metadata (unfiltered, as its file must be the local file).
		$meta_data = wp_get_attachment_metadata( $attachment_id, true );

		// bail if metadata could not be loaded.
		if ( ! is_array( $meta_data ) ) {
			$meta_data = array();
		}

		// get sizes.
		$sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

		// bail if sizes is not an array.
		if ( ! is_array( $sizes ) ) {
			$sizes = array();
		}

		// get the local file relative to the uploads directory: the attached file could be the external URL already,
		// which must not be used, as WordPress would delete the files with the names of the sizes and the original
		// image beside it, i.e. on the external source.
		$local_paths = Local_Urls::get_instance()->get_local_paths( $attachment_id );
		$file        = ! empty( $local_paths ) ? $local_paths[0] : get_attached_file( $attachment_id, true );

		// bail if file is not a string or not a local file.
		if ( ! is_string( $file ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			return $data;
		}

		// use the path relative to the uploads directory.
		$file = _wp_relative_upload_path( $file );

		// add the path to the metadata, if not set.
		if ( ! isset( $meta_data['file'] ) ) {
			$meta_data['file'] = $file;
		}

		// log this event.
		Log::get_instance()->create( __( 'Cleanup the attachment files starting.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2, $file );

		// get all files for this attachment and delete them in local project.
		wp_delete_attachment_files( $attachment_id, $meta_data, $sizes, $file );

		// delete the local files of the exported variants in other formats (e.g. WebP).
		Variants::get_instance()->delete_local_files( $attachment_id );

		// remove the sizes from the metadata, as their local files do not exist anymore.
		unset( $data['sizes'] );

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

		// log this event.
		Log::get_instance()->create( __( 'Cleanup the attachment files completed.', 'external-files-in-media-library' ), $external_file_obj->get_url( true ), 'info', 2, $file );

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

			// get the folder, which has been used for this file on this external source.
			$folder = $this->get_used_export_folder( $external_file_obj->get_id(), absint( $term_id ) );

			// add it to the list (with the used folder, if one has been used).
			$list[] = '<em>' . esc_html( '' !== $folder ? trailingslashit( $term->name ) . $folder : $term->name ) . '</em>';
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
		// bail if he also does not have the capability for external files.
		if ( ! current_user_can( EFML_CAP_NAME ) ) {
			return;
		}

		// show the reason, if files could not be exported at the moment, although external sources are enabled for it.
		if ( 1 === absint( get_option( 'eml_export' ) ) && ! empty( $this->get_export_terms() ) ) {
			$problem = $this->get_export_sources_problem();
			if ( '' !== $problem ) {
				echo '<div class="efml_export-hint"><p><strong>' . esc_html__( 'New files in your media library are not exported at the moment.', 'external-files-in-media-library' ) . '</strong> ' . wp_kses_post( $problem ) . '</p></div>';
				return;
			}
		}

		// show simple messages if user has not the capability for settings.
		if ( ! current_user_can( 'manage_options' ) ) {
			// show info if export is enabled.
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
		if ( $this->is_enabled() ) {
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
	 * The main external source for exports is always the last entry.
	 *
	 * @param bool $only_usable True to return only external sources, which could be used at the moment, false to
	 *                          return all and mark the not usable ones (e.g. for the settings, which must not lose them).
	 *
	 * @return array<string,string>
	 */
	private function get_external_sources_as_name_list( bool $only_usable = true ): array {
		// bail if this is not in the backend.
		if ( ! is_admin() ) {
			return array();
		}

		// bail if no terms are set.
		if ( 0 === absint( get_option( 'efml_directory_listing_used' ) ) ) {
			return array();
		}

		// get all terms of our listing taxonomy.
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

			// get the name.
			$name = $term->name;

			// check whether this external source could be used at the moment.
			if ( ! $this->is_export_source_usable( $term->term_id ) ) {
				// bail if only usable external sources are requested.
				if ( $only_usable ) {
					continue;
				}

				// mark it.
				/* translators: %1$s will be replaced by the name of an external source. */
				$name = sprintf( __( '%1$s (not usable at the moment)', 'external-files-in-media-library' ), $name );
			}

			// add this source.
			$external_sources[ (string) $term->term_id ] = $name;
		}

		// move the main external source to the end of the list.
		$main_term_id = (string) absint( get_option( 'eml_export_main_source' ) );
		if ( isset( $external_sources[ $main_term_id ] ) ) {
			$main_name = $external_sources[ $main_term_id ];
			unset( $external_sources[ $main_term_id ] );
			$external_sources[ $main_term_id ] = $main_name;
		}

		// return the resulting list.
		return $external_sources; // @phpstan-ignore return.type
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
	 * Difference to @wp_unique_filename(): we check the DB-entries and the external sources, not the local
	 * filesystem, as these are the places where exported and local files are managed.
	 *
	 * As exported files are saved flat in the directory of each external source, the filename must be unique
	 * for all exported files, not only for the actual upload directory (e.g. "2026/11").
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

		// get the export targets to check the filename there.
		// (not the list of external sources for the backend, as uploads also happen via REST API, WP-CLI or cron).
		$export_targets = $this->get_export_targets();

		// bail if no export target is usable.
		if ( empty( $export_targets ) ) {
			return $filename;
		}

		// get the upload dir.
		$upload_dir = wp_upload_dir();

		// build the relative path.
		// (normalized to "/" as WordPress saves "_wp_attached_file" always with forward slashes, also on Windows).
		$dir = str_replace( trailingslashit( wp_normalize_path( $upload_dir['basedir'] ) ), '', wp_normalize_path( $dir ) ) . '/';

		/**
		 * Filter the maximum number of tries to find a unique filename for an exported file.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $max_tries The maximum number of tries.
		 * @param string $filename The filename WordPress would use.
		 */
		$max_tries = absint( apply_filters( 'efml_export_unique_filename_max_tries', 100, $filename ) );

		// loop until we found a unique filename.
		for ( $i = 0; $i <= $max_tries; $i++ ) {
			// generate a file name to check.
			$filename_to_check = $this->get_filename_with_suffix( $filename, $ext, $i );

			// bail if this name or one of its variants is already in use.
			if ( $this->is_filename_in_use( $filename_to_check, $ext, $dir, $export_targets ) ) {
				continue;
			}

			// return the unique filename.
			return $filename_to_check;
		}

		// log this event.
		/* translators: %1$d will be replaced by a number. */
		Log::get_instance()->create( sprintf( __( 'No unique filename for the export could be found after %1$d tries. The filename will be used as it is.', 'external-files-in-media-library' ), $max_tries ), $filename, 'error' );

		// return the given filename as fallback.
		return $filename;
	}

	/**
	 * Return whether the given filename or one of its variants is already used in the media library or on
	 * one of the export targets.
	 *
	 * WordPress could save an image with another name than the uploaded one, e.g. "image-scaled.jpg" for
	 * large images or "image-rotated.jpg" for rotated images. This name is used for the export. So these
	 * variants are checked, too.
	 *
	 * @param string                         $filename The filename to check.
	 * @param string                         $ext The extension of the file (incl. ".").
	 * @param string                         $dir The relative upload directory (e.g. "2026/11/").
	 * @param array<int,array<string,mixed>> $export_targets List of export targets.
	 *
	 * @return bool
	 */
	private function is_filename_in_use( string $filename, string $ext, string $dir, array $export_targets ): bool {
		// get the base name without extension.
		$base = ( '' !== $ext && str_ends_with( $filename, $ext ) ) ? substr( $filename, 0, -strlen( $ext ) ) : $filename;

		// collect the names to check.
		$names = array( $filename );

		// add the variants WordPress uses for images.
		$file_type = wp_check_filetype( $filename );
		if ( is_string( $file_type['type'] ) && str_starts_with( $file_type['type'], 'image/' ) ) {
			$names[] = $base . '-scaled' . $ext;
			$names[] = $base . '-rotated' . $ext;
		}

		// check each name.
		foreach ( $names as $name ) {
			// bail if this name is used by another file in the media library.
			if ( $this->is_filename_used_in_media_library( $name, $dir ) ) {
				return true;
			}

			// bail if this name is already used on one of the external sources.
			if ( $this->is_filename_used_on_export_targets( $name, $export_targets ) ) {
				return true;
			}
		}

		// return false as the name is not in use.
		return false;
	}

	/**
	 * Return the given filename with a numeric suffix before its extension.
	 *
	 * @param string $filename The filename.
	 * @param string $ext The extension of the file (incl. ".").
	 * @param int    $number The number to add (0 for no suffix).
	 *
	 * @return string
	 */
	private function get_filename_with_suffix( string $filename, string $ext, int $number ): string {
		// bail if no suffix should be added.
		if ( 0 === $number ) {
			return $filename;
		}

		// add the suffix before the extension, if the filename ends with it.
		if ( '' !== $ext && str_ends_with( $filename, $ext ) ) {
			return substr( $filename, 0, -strlen( $ext ) ) . '-' . $number . $ext;
		}

		// otherwise add it to the end.
		return $filename . '-' . $number;
	}

	/**
	 * Return whether the given filename is already used by a file in the media library.
	 *
	 * Checks:
	 * - local files in the given upload directory.
	 * - exported files with this filename (saved since 6.0.0).
	 * - exported files from before 6.0.0, which have the external URL with this filename as attached file.
	 *
	 * @param string $filename The filename to check.
	 * @param string $dir The relative upload directory (e.g. "2026/11/").
	 *
	 * @return bool
	 */
	private function is_filename_used_in_media_library( string $filename, string $dir ): bool {
		$query   = array(
			'post_type'              => 'attachment',
			'post_status'            => array( 'inherit', 'trash' ),
			'fields'                 => 'ids',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for additional data.
				'relation' => 'OR',
				array(
					'key'   => '_wp_attached_file',
					'value' => $dir . $filename,
				),
				array(
					'key'   => 'efml_export_filename',
					'value' => $filename,
				),
				array(
					'relation' => 'AND',
					array(
						'key'     => 'eml_exported_file',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_wp_attached_file',
						'value'   => '/' . $filename,
						'compare' => 'LIKE',
					),
				),
			),
		);
		$results = new WP_Query( $query );

		// return whether we found any file.
		return ! empty( $results->posts );
	}

	/**
	 * Return whether the given filename is already used on one of the given export targets.
	 *
	 * @param string                         $filename The filename to check.
	 * @param array<int,array<string,mixed>> $export_targets List of export targets.
	 *
	 * @return bool
	 */
	private function is_filename_used_on_export_targets( string $filename, array $export_targets ): bool {
		foreach ( $export_targets as $export_target ) {
			// get the export object.
			$export_obj = $export_target['export_obj'];

			// bail if export object is not valid.
			if ( ! $export_obj instanceof Export_Base ) {
				continue;
			}

			// create the target path of this file.
			$target = trailingslashit( (string) $export_target['path'] ) . $filename;

			// check the target.
			try {
				if ( $export_obj->file_exists_on_target( $target, (array) $export_target['credentials'] ) ) {
					// log this event.
					Log::get_instance()->create( __( 'A file with this name already exists on the external source. Another name will be used for the export.', 'external-files-in-media-library' ), $target, 'info', 2 );

					// return true as the filename is in use.
					return true;
				}
			} catch ( \Throwable $e ) {
				// log this event.
				Log::get_instance()->create( __( 'Error during check for existing file on external source:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $e->getMessage() ) . '</code>', $target, 'error' );
			}
		}

		// return false as the filename is not used on any external source.
		return false;
	}

	/**
	 * Return whether WordPress organizes the uploads in year- and month-based folders.
	 *
	 * @return bool
	 */
	private function is_yearmonth_folders_enabled(): bool {
		return 1 === absint( get_option( 'uploads_use_yearmonth_folders' ) );
	}

	/**
	 * Return whether folders should be used for exported files.
	 *
	 * This requires the setting and the year- and month-based folders of WordPress.
	 *
	 * @return bool
	 */
	public function is_folder_structure_enabled(): bool {
		return 1 === absint( get_option( 'eml_export_folders' ) ) && $this->is_yearmonth_folders_enabled();
	}

	/**
	 * Return whether the object versioning is used: the folders of exported files contain the time of the export,
	 * so each export gets its own URL.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return bool
	 */
	public function is_object_versioning_enabled(): bool {
		return $this->is_folder_structure_enabled() && str_contains( (string) get_option( 'eml_export_folder_structure', '' ), '%timestamp%' );
	}

	/**
	 * Return the value of the header "Cache-Control" for files, which are exported to services, which save it with
	 * the file (e.g. AWS S3 and Google Cloud Storage).
	 *
	 * Browsers and CDNs could cache public files for one day, with object versioning for one week. Private files get
	 * no value, as they are delivered via signed URLs or the proxy.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $is_private       Whether the file is saved privately.
	 *
	 * @return string
	 */
	public function get_cache_control( int $attachment_id, bool $is_private = false ): string {
		// get the value.
		$cache_control = '';
		if ( ! $is_private ) {
			$cache_control = 'public, max-age=' . ( $this->is_object_versioning_enabled() ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		}

		/**
		 * Filter the value of the header "Cache-Control" for exported files.
		 *
		 * Used by services, which save it with the file (e.g. AWS S3 and Google Cloud Storage). Return an empty
		 * string to save no value.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $cache_control The value of the header.
		 * @param int    $attachment_id The attachment ID.
		 * @param bool   $is_private       Whether the file is saved privately.
		 */
		return (string) apply_filters( 'efml_export_cache_control', $cache_control, $attachment_id, $is_private );
	}

	/**
	 * Return whether files should be saved privately on the given external source.
	 *
	 * This requires the support of the service and the enabled option in the export settings of the external source.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int         $term_id    The term ID of the external source.
	 * @param Export_Base $export_obj The export object of its service.
	 *
	 * @return bool
	 */
	public function is_private_export( int $term_id, Export_Base $export_obj ): bool {
		// bail if the service does not support private files.
		if ( ! $export_obj->supports_private_files() ) {
			return false;
		}

		// get the setting of the external source.
		$private = 1 === absint( get_term_meta( $term_id, 'efml_export_private', true ) );

		/**
		 * Filter whether files should be saved privately on the given external source.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $private True if files should be saved privately.
		 * @param int $term_id The term ID of the external source.
		 */
		return (bool) apply_filters( 'efml_export_private', $private, $term_id );
	}

	/**
	 * Return the placeholders for the structure of the folders.
	 *
	 * @return array<string,string>
	 */
	public function get_folder_structure_placeholders(): array {
		$placeholders = array(
			'year'      => __( 'Year', 'external-files-in-media-library' ),
			'monthnum'  => __( 'Month', 'external-files-in-media-library' ),
			'day'       => __( 'Day', 'external-files-in-media-library' ),
			'timestamp' => __( 'Timestamp (object versioning)', 'external-files-in-media-library' ),
		);

		/**
		 * Filter the placeholders for the structure of the folders for exported files.
		 *
		 * Each placeholder must be replaced via the hook "efml_export_folder".
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param array<string,string> $placeholders List of placeholders (without "%") with their labels.
		 */
		return apply_filters( 'efml_export_folder_placeholders', $placeholders );
	}

	/**
	 * Sanitize the structure of the folders: only letters, numbers, "-", "_", "/" and placeholders are allowed.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string
	 */
	public function sanitize_folder_structure( mixed $value ): string {
		// bail if value is not a string.
		if ( ! is_string( $value ) ) {
			return '%year%/%monthnum%';
		}

		// remove not allowed characters.
		$value = (string) preg_replace( '/[^A-Za-z0-9_\-\/%]/', '', $value );

		// remove empty folders and leading or trailing slashes.
		return implode(
			'/',
			array_filter(
				explode( '/', $value ),
				static function ( string $folder ): bool {
					return '' !== $folder;
				}
			)
		);
	}

	/**
	 * Return the folder for an exported file according to the configured structure, without slashes at start and end.
	 *
	 * Returns an empty string if folders are not enabled.
	 *
	 * @param int $attachment_id The attachment ID (0 for a new file, which is uploaded now).
	 * @param int $export_time   The time of the export.
	 *
	 * @return string
	 */
	public function get_export_folder( int $attachment_id, int $export_time ): string {
		// bail if folders are not enabled.
		if ( ! $this->is_folder_structure_enabled() ) {
			return '';
		}

		// get the date of the upload of the file, like WordPress uses it for its folders.
		$upload_time = $attachment_id > 0 ? get_post_time( 'U', true, $attachment_id ) : false;
		if ( ! is_int( $upload_time ) ) {
			$upload_time = time();
		}

		// get the date in the timezone of the website, like WordPress uses it for its folders.
		$date = wp_date( 'Y/m/d', $upload_time );
		if ( ! is_string( $date ) ) {
			$date = gmdate( 'Y/m/d', $upload_time );
		}
		list( $year, $month, $day ) = explode( '/', $date );

		// replace the placeholders.
		$folder = str_replace(
			array( '%year%', '%monthnum%', '%day%', '%timestamp%' ),
			array( $year, $month, $day, (string) $export_time ),
			$this->sanitize_folder_structure( get_option( 'eml_export_folder_structure', '%year%/%monthnum%' ) )
		);

		/**
		 * Filter the folder for an exported file.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param string $folder The folder (e.g. "2026/11").
		 * @param int $attachment_id The attachment ID (0 for a new file, which is uploaded now).
		 * @param int $export_time The time of the export.
		 */
		$folder = (string) apply_filters( 'efml_export_folder', $folder, $attachment_id, $export_time );

		// return the folder with safe names only.
		$folders = array();
		foreach ( explode( '/', $folder ) as $name ) {
			$name = sanitize_file_name( $name );
			if ( '' === $name || '.' === $name || '..' === $name ) {
				continue;
			}
			$folders[] = $name;
		}
		return implode( '/', $folders );
	}

	/**
	 * Return the folder, which has been used for the export of the given file to the given external source.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $term_id       The ID of the external source.
	 *
	 * @return string
	 */
	private function get_used_export_folder( int $attachment_id, int $term_id ): string {
		// get the folders.
		$folders = get_post_meta( $attachment_id, 'efml_export_folders', true );

		// bail if no folder has been saved for this external source.
		if ( ! is_array( $folders ) || empty( $folders[ $term_id ] ) || ! is_string( $folders[ $term_id ] ) ) {
			return '';
		}

		// return the folder.
		return $folders[ $term_id ];
	}

	/**
	 * Return the list of export targets with their export object, path and credentials.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_export_targets(): array {
		// collect the targets.
		$targets = array();

		// check each external source with enabled export.
		foreach ( $this->get_usable_export_terms() as $term_id ) {
			$true = true;
			/**
			 * Filter whether the external source should be checked for existing files with the same name
			 * before a new file is exported.
			 *
			 * @since 6.0.0 Available since 6.0.0.
			 * @param bool $true True to check the external source.
			 * @param int $term_id The ID of the external source.
			 */
			if ( ! apply_filters( 'efml_export_check_filename_on_target', $true, $term_id ) ) {
				continue;
			}

			// get the listing object.
			$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );

			// bail if no object could be found.
			if ( ! $listing_obj instanceof Service_Base || ! $listing_obj->get_export_object() instanceof Export_Base ) {
				continue;
			}

			// get the export object.
			$export_obj = $listing_obj->get_export_object();

			// bail if this export object requires a URL, which is not set (as the export is not used then).
			if ( $export_obj->is_url_required() && empty( get_term_meta( $term_id, 'efml_export_url', true ) ) ) {
				continue;
			}

			// get the path, incl. the folder for a new file, if supported.
			$path   = (string) get_term_meta( $term_id, 'path', true );
			$folder = $export_obj->supports_folders() ? $this->get_export_folder( 0, time() ) : '';
			if ( '' !== $folder ) {
				$path = trailingslashit( $path ) . $folder;
			}

			// add this target to the list.
			$targets[] = array(
				'term_id'     => $term_id,
				'export_obj'  => $export_obj,
				'path'        => $path,
				'credentials' => array_merge( Taxonomy::get_instance()->get_entry( $term_id ), array( 'term_id' => $term_id ) ),
			);
		}

		// return the resulting list.
		return $targets;
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
		// bail if cap is missing or the user is not allowed to edit this file.
		if ( ! current_user_can( 'efml_cap_tools_export' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
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

		// bail if mime type is not allowed.
		if ( ! in_array( get_post_mime_type( $post->ID ), Helper::get_allowed_mime_types(), true ) ) {
			return $actions;
		}

		// get the external file object.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if file is already an external file.
		if ( $external_file_obj->is_valid() ) {
			return $actions;
		}

		// bail if the file waits for its export or its export has failed: it gets the action "Export now" instead.
		if ( '' !== $this->get_export_state( $post->ID ) ) {
			return $actions;
		}

		// get the usable external sources (once per request, as this runs for each row).
		if ( is_null( $this->row_action_sources ) ) {
			$this->row_action_sources = '' === $this->get_export_sources_problem() ? $this->get_external_sources_as_name_list() : array();
		}
		$external_sources = $this->row_action_sources;

		// bail if no external source is enabled for export.
		if ( empty( $external_sources ) ) {
			return $actions;
		}

		// get the last one from list, that is the main external source for exports.
		$external_source_for_export = end( $external_sources );

		// get the URL of the external source.
		$term_id = absint( array_search( $external_source_for_export, $external_sources, true ) );
		if ( 0 === $term_id ) {
			return $actions;
		}
		$external_url = get_term_meta( $term_id, 'efml_export_url', true );

		// get the folder for this file, if the service of this external source supports folders.
		$folder      = '';
		$listing_obj = $this->get_service_object_by_type( (string) get_term_meta( $term_id, 'type', true ) );
		if ( $listing_obj instanceof Service_Base && $listing_obj->get_export_object() instanceof Export_Base && $listing_obj->get_export_object()->supports_folders() ) {
			$folder = $this->get_export_folder( $post->ID, time() );
		}

		// assume the export URL.
		$external_url_of_file = $external_url . ( '' !== $folder ? trailingslashit( $folder ) : '' ) . basename( $external_file_obj->get_attachment_url() );

		// create URL to export this file.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_file',
				'post'   => $post->ID,
				'nonce'  => wp_create_nonce( 'efml-export-file' ),
			),
			get_admin_url() . 'admin.php'
		);

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Export media file', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Are you sure you want to export this file?', 'external-files-in-media-library' ) . '</strong></p>',
				/* translators: %1$s will be replaced by a title. */
				'<p>' . sprintf( __( 'The file will be exported to the external source %1$s.<br>After the export the file will be available under this URL:', 'external-files-in-media-library' ) . ' <a href="' . $external_url_of_file . '">' . $external_url_of_file . '</a>', '<em>' . $external_source_for_export . '</em>' ) . '</p>',
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

		// bail if no ID is given or the user is not allowed to edit this file.
		if ( 0 === $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
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
		// bail if user has not the capability to export files.
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

		// bail if files could not be exported at the moment (e.g. the service of the main external source is not available).
		if ( '' !== $this->get_export_sources_problem() ) {
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
	 * @param array<int,int> $items The items to act.
	 *
	 * @return string
	 */
	public function run_bulk_action( string $sendback, string $doaction, array $items ): string {
		// bail if action is not ours.
		if ( 'efml-export' !== $doaction ) {
			return $sendback;
		}

		// bail if user has not the capability to export files.
		if ( ! current_user_can( 'efml_cap_tools_export' ) ) {
			return $sendback;
		}

		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
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

		// set marker for files added to the queue.
		$added_to_queue = false;

		// check whether the export should run in background.
		$export_in_background = $this->is_export_in_background_enabled();

		// check the files and change its state.
		foreach ( $items as $attachment_id ) {
			// get the ID as integer.
			$attachment_id = absint( $attachment_id );

			// bail if the user is not allowed to edit this file.
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}

			// bail if file is already exported (the marker contains the time of the export).
			if ( absint( get_post_meta( $attachment_id, 'eml_exported_file', true ) ) > 0 ) {
				continue;
			}

			// add the file to the queue, if the export should run in background or the file is large.
			if ( ( $export_in_background || $this->is_large_file( $attachment_id ) ) && Queue::get_instance()->add_export( $attachment_id ) ) {
				$added_to_queue = true;
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
		if ( $added_to_queue && ! $successfully_exported ) {
			/* translators: %1$s will be replaced by a URL. */
			$transient_obj->set_message( '<strong>' . __( 'The files have been added to the queue.', 'external-files-in-media-library' ) . '</strong> ' . sprintf( __( 'They will be exported in the background. You can see the progress in <a href="%1$s">the queue</a>.', 'external-files-in-media-library' ), \ExternalFilesInMediaLibrary\Plugin\Settings::get_instance()->get_url( 'eml_queue_table' ) ) );
			$transient_obj->set_type( 'success' );
		} elseif ( $successfully_exported ) {
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
	 * Return the file size in bytes, from which uploads are exported in the background via the queue, even if the
	 * export in background is not enabled: 50 MB by default, 0 to disable this.
	 *
	 * @return int
	 */
	public function get_large_file_size(): int {
		$size = 50 * MB_IN_BYTES;

		/**
		 * Filter the file size in bytes, from which files are exported in the background via the queue, even if the
		 * export in background is not enabled. So the upload or the bulk action is not interrupted by time limits of
		 * the web server during the export of large files.
		 *
		 * Return 0 to export all files during the upload.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param int $size The size in bytes.
		 */
		return absint( apply_filters( 'efml_export_large_file_size', $size ) );
	}

	/**
	 * Return whether the given local file is large: it is exported in the background via the queue then.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_large_file( int $attachment_id ): bool {
		// get the size, from which files are large.
		$large_size = $this->get_large_file_size();

		// bail if this is disabled or the queue is not processed.
		if ( 0 === $large_size || ! Queue::get_instance()->is_processing_enabled() ) {
			return false;
		}

		// get the local file.
		$file = get_attached_file( $attachment_id, true );

		// bail if it does not exist.
		if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
			return false;
		}

		// return whether it is large.
		return absint( wp_filesize( $file ) ) >= $large_size;
	}

	/**
	 * Return whether files should be exported in background via the queue.
	 *
	 * Requires the enabled setting and an active queue.
	 *
	 * @return bool
	 */
	public function is_export_in_background_enabled(): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_export_in_background' ) ) ) {
			return false;
		}

		$true = true;
		/**
		 * Filter whether files should be exported in background via the queue.
		 *
		 * @since 6.0.0 Available since 6.0.0.
		 * @param bool $true True to export in background.
		 */
		if ( ! apply_filters( 'efml_export_in_background', $true ) ) {
			return false;
		}

		// return whether the queue is processed.
		return Queue::get_instance()->is_processing_enabled();
	}

	/**
	 * Return the name of the main external source for exports from the given list of external sources.
	 *
	 * Returns the last one of the list, if no main external source is chosen.
	 *
	 * @param array<string,string> $external_sources List of external sources with enabled export.
	 *
	 * @return string
	 */
	private function get_main_source_name( array $external_sources ): string {
		// get the ID of the main external source.
		$main_source_id = absint( get_option( 'eml_export_main_source' ) );

		// return its name, if it is in the list.
		foreach ( $external_sources as $term_id => $name ) {
			if ( absint( $term_id ) === $main_source_id ) {
				return $name;
			}
		}

		// return the last one otherwise.
		return (string) end( $external_sources );
	}

	/**
	 * Return the dialog to export all local files in the media library.
	 *
	 * @param string $main_source The name of the main external source for exports.
	 *
	 * @return array<string,mixed>
	 */
	private function get_export_local_files_dialog( string $main_source ): array {
		// create the URL to start the export.
		$url = add_query_arg(
			array(
				'action' => 'efml_export_local_files',
				'nonce'  => wp_create_nonce( 'efml-export-local-files' ),
			),
			get_admin_url() . 'admin.php'
		);

		// collect the texts.
		$texts = array(
			'<p><strong>' . __( 'Do you really want to export all local files in your media library?', 'external-files-in-media-library' ) . '</strong></p>',
			/* translators: %1$s will be replaced by the name of an external source. */
			'<p>' . sprintf( __( 'All local files, which are not exported yet, will be exported to %1$s and to the other external sources with enabled export. Afterward they are used as external files.', 'external-files-in-media-library' ), '<em>' . esc_html( $main_source ) . '</em>' ) . '</p>',
		);

		// add the hint about the local files.
		if ( 1 === absint( get_option( 'eml_export_do_not_delete_local_files' ) ) ) {
			$texts[] = '<p>' . __( 'Their local files are kept, as you have chosen this in the settings.', 'external-files-in-media-library' ) . '</p>';
		} else {
			$texts[] = '<p>' . __( 'Their local files are deleted in your hosting after the export.', 'external-files-in-media-library' ) . '</p>';
		}

		// add the hint about the progress.
		$texts[] = '<p>' . __( 'The files are exported one by one in the background. The admin bar shows the progress, a click on it opens the queue.', 'external-files-in-media-library' ) . '</p>';

		// return the dialog.
		return array(
			'className' => 'efml',
			'title'     => __( 'Export existing files', 'external-files-in-media-library' ),
			'texts'     => $texts,
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";',
					'variant' => 'primary',
					'text'    => __( 'Yes, export them', 'external-files-in-media-library' ),
				),
				array(
					'action'  => 'closeDialog();',
					'variant' => 'secondary',
					'text'    => __( 'Cancel', 'external-files-in-media-library' ),
				),
			),
		);
	}

	/**
	 * Start the export of all local files in the media library by request.
	 *
	 * @return void
	 * @noinspection PhpNoReturnAttributeCanBeAddedInspection
	 */
	public function export_local_files_via_request(): void {
		// check nonce.
		check_admin_referer( 'efml-export-local-files', 'nonce' );

		// get referer.
		$referer = (string) wp_get_referer();

		// bail if cap is missing.
		if ( ! $this->is_settings_action_allowed() ) {
			wp_safe_redirect( $referer );
			exit;
		}

		// start the export.
		$result = Import_Runs::get_instance()->start_export();

		// show the result.
		$transient_obj = Transients::get_instance()->add();
		$transient_obj->set_name( 'efml_export_local_files' );
		$transient_obj->set_type( $result['success'] ? 'success' : 'error' );
		$transient_obj->set_message( '<strong>' . esc_html( $result['message'] ) . '</strong>' );
		$transient_obj->save();

		// forward user.
		wp_safe_redirect( $referer );
		exit;
	}

	/**
	 * Return the reason, why the local files of the media library could not be exported at the moment.
	 *
	 * Returns an empty string, if they could be exported.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return string
	 */
	public function get_export_run_problem(): string {
		// bail if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return __( 'The export is not enabled in the plugin settings.', 'external-files-in-media-library' );
		}

		// bail if the export of local files is disabled.
		if ( 1 !== absint( get_option( 'eml_export_local_files' ) ) ) {
			return __( 'The export of files from the media library is not enabled in the plugin settings.', 'external-files-in-media-library' );
		}

		// bail if the external sources could not be used for the export.
		$problem = $this->get_export_sources_problem();
		if ( '' !== $problem ) {
			return $problem;
		}

		// bail if this website is a copy.
		if ( Site_Copy::get_instance()->is_copy() ) {
			return __( 'This website is a copy of another website (e.g. a staging site). Files are not exported to external sources from copies.', 'external-files-in-media-library' );
		}

		// all fine.
		return '';
	}

	/**
	 * Return the IDs of local files in the media library, which could be exported, ordered by their ID.
	 *
	 * Returns files with a mime type allowed in the plugin settings, which are not external files and which
	 * are not waiting for their export in the queue.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @param int $after_id Return only files with a higher ID.
	 * @param int $limit    The maximum number of files.
	 *
	 * @return array<int,int>
	 */
	public function get_local_files_for_export( int $after_id, int $limit ): array {
		global $wpdb;

		// get the conditions.
		$query = $this->get_local_files_query();

		// bail if no mime type is allowed.
		if ( empty( $query ) ) {
			return array();
		}

		// get the IDs.
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT p.`ID` FROM %i p WHERE ' . $query['where'] . ' AND p.`ID` > %d ORDER BY p.`ID` ASC LIMIT %d', array_merge( array( $wpdb->posts ), $query['args'], array( $after_id, max( 1, $limit ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only static placeholders, all values passed via prepare().

		// return them as integers.
		return array_map( 'absint', $ids );
	}

	/**
	 * Return the number of local files in the media library, which could be exported.
	 *
	 * @since 6.0.0 Available since 6.0.0.
	 *
	 * @return int
	 */
	public function count_local_files_for_export(): int {
		global $wpdb;

		// get the conditions.
		$query = $this->get_local_files_query();

		// bail if no mime type is allowed.
		if ( empty( $query ) ) {
			return 0;
		}

		// return the number.
		return absint( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i p WHERE ' . $query['where'], array_merge( array( $wpdb->posts ), $query['args'] ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Only static placeholders, all values passed via prepare().
	}

	/**
	 * Return the conditions to get the local files in the media library, which could be exported.
	 *
	 * Returns an empty array, if no mime type is allowed.
	 *
	 * @return array{where: string, args: array<int,int|string>}|array{}
	 */
	private function get_local_files_query(): array {
		global $wpdb;

		// get the allowed mime types.
		$mime_types = Helper::get_allowed_mime_types();

		// bail if no mime type is allowed.
		if ( empty( $mime_types ) ) {
			return array();
		}

		// collect the conditions.
		$where = array(
			'p.`post_type` = %s',
			'p.`post_status` NOT IN (%s, %s)',
			'p.`post_mime_type` IN (' . implode( ', ', array_fill( 0, count( $mime_types ), '%s' ) ) . ')',
			'NOT EXISTS (SELECT 1 FROM %i m WHERE m.`post_id` = p.`ID` AND m.`meta_key` IN (%s, %s))',
		);
		$args  = array_merge( array( 'attachment', 'trash', 'auto-draft' ), array_values( $mime_types ), array( $wpdb->postmeta, EFML_POST_META_URL, 'eml_exported_file' ) );

		// exclude files waiting for their export in the queue, if the queue exists.
		if ( Helper::is_table_available( 'eml_queue' ) ) {
			$where[] = 'NOT EXISTS (SELECT 1 FROM %i q WHERE q.`attachment_id` = p.`ID` AND q.`type` = %s AND q.`state` IN (%s, %s))';
			$args    = array_merge( $args, array( $wpdb->prefix . 'eml_queue', 'export', 'new', 'processing' ) );
		}

		// return the conditions.
		return array(
			'where' => implode( ' AND ', $where ),
			'args'  => $args,
		);
	}

	/**
	 * Delete an exported external file on an external source if it is imported as real media file.
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
		remove_filter( 'wp_generate_attachment_metadata', array( $this, 'export_file_by_upload' ), 20 );
	}

	/**
	 * Re-enable the usage of export functions after the hosting switch to local (also if it failed).
	 *
	 * @return void
	 */
	public function allow_export_checks_after_local_switch(): void {
		if ( false === has_filter( 'wp_generate_attachment_metadata', array( $this, 'export_file_by_upload' ) ) ) {
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'export_file_by_upload' ), 20, 2 );
		}
	}

	/**
	 * Cleanup exported files after switching them to local hosting.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	public function cleanup_exported_file( int $attachment_id ): void {
		// remember the external sources this file has been exported to, as the exported copy
		// still exists there and must be deletable later (e.g., during a real import or if the file is deleted).
		$export_sources = $this->get_export_sources_of_file( $attachment_id );
		if ( ! empty( $export_sources ) ) {
			update_post_meta( $attachment_id, 'efml_former_export_sources', $export_sources );
		}

		// remove the export markers.
		delete_post_meta( $attachment_id, 'eml_exported_file' );
		delete_post_meta( $attachment_id, 'efml_export_sources' );
	}

	/**
	 * Add option to export the settings for this specific external source.
	 *
	 * @param array<string,string> $actions List of action.
	 * @param WP_Term              $term The requested "WP_Term" object.
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

		// create the dialog.
		$dialog = array(
			'className' => 'efml',
			/* translators: %1$s will be replaced by the file name. */
			'title'     => sprintf( __( 'Export %1$s as JSON', 'external-files-in-media-library' ), $term->name ),
			'texts'     => array(
				'<p>' . __( 'You will receive a JSON file that you can use to import this external source into another project that uses the “External Files in Media Library” plugin.', 'external-files-in-media-library' ) . '</p>',
				'<p><strong>' . __( 'The file may also contain access data. Keep it safe.', 'external-files-in-media-library' ) . '</strong></p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'location.href="' . $url . '";closeDialog();',
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

	/**
	 * Return whether export is enabled:
	 * - either we are in a multisite, and the actual site is NOT the main media library site
	 * - or we are NOT in a multisite and export is enabled in it.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		// bail if this is not a multisite.
		if ( ! is_multisite() ) {
			return $this->is_enabled_on_single_site();
		}

		// get the configured site ID for the main media library.
		$efml_media_library_site_id = \ExternalFilesInMediaLibrary\Plugin\Network\Settings::get_instance()->get_main_media_library_site_id();

		// use single site setting, if not main media library is set.
		if ( 0 === $efml_media_library_site_id ) {
			return $this->is_enabled_on_single_site();
		}

		// return true if this is NOT the main media library site to enable the export.
		return get_current_blog_id() !== $efml_media_library_site_id;
	}

	/**
	 * Return whether export is enabled on the actual single website.
	 *
	 * @return bool
	 */
	private function is_enabled_on_single_site(): bool {
		return 1 === absint( get_option( 'eml_export' ) );
	}

	/**
	 * Return whether is extension require a capability to use it.
	 *
	 * @return bool
	 */
	public function has_capability(): bool {
		return true;
	}

	/**
	 * Return whether the actual user is allowed to start an action of the export settings, which works with all
	 * files of the media library (e.g. "Export now", "Restore now", "Delete local copies now"): the user needs the
	 * capability for the export and the capability for the settings, as these actions also change settings
	 * (e.g. the restore disables the export) and are only available in the settings.
	 *
	 * @return bool
	 */
	public function is_settings_action_allowed(): bool {
		return current_user_can( 'efml_cap_tools_export' ) && current_user_can( Settings::get_instance()->get_settings_obj()->get_capability() );
	}

	/**
	 * Return the default roles with capability for this object.
	 *
	 * @return array<int,string>
	 */
	public function get_capability_default(): array {
		return array( 'administrator' );
	}

	/**
	 * Return the description for the capability settings.
	 *
	 * @return string
	 */
	public function get_capability_description(): string {
		return __( 'Select roles, which should be allowed to export files in the media library to external sources.', 'external-files-in-media-library' );
	}

	/**
	 * Return whether this tool is in use.
	 *
	 * @return bool
	 */
	public function is_in_use(): bool {
		// bail with false if export is disabled.
		if ( 1 !== absint( get_option( 'eml_export' ) ) ) {
			return false;
		}

		// get list of external sources.
		$external_sources = $this->get_usable_export_terms();

		// return result depending on terms for export.
		return ! empty( $external_sources );
	}

	/**
	 * Run tasks to disable this tool.
	 *
	 * @return void
	 */
	public function disable(): void {
		update_option( 'eml_export', 0 );
		parent::disable();
	}

	/**
	 * Return the "not supported" hint for table-view.
	 *
	 * @param object|false $listing_obj The used service object.
	 *
	 * @return string
	 */
	private function get_not_supported_hint( object|false $listing_obj ): string {
		// create the dialog for sync now.
		$dialog = array(
			'className' => 'efml',
			'title'     => __( 'Export is not supported', 'external-files-in-media-library' ),
			'texts'     => array(
				'<p><strong>' . __( 'Export for this external source is not supported.', 'external-files-in-media-library' ) . '</strong></p>',
				/* translators: %1$s will be replaced by a URL. */
				'<p>' . sprintf( __( 'If you have any questions, please feel free to ask them <a href="%1$s" target="_blank">in our support forum%2$s</a>.', 'external-files-in-media-library' ), Helper::get_plugin_support_url(), Helper::get_a11n_window_hint() ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);

		// show a specific hint.
		if ( $listing_obj instanceof Service_Base ) {
			/* translators: %1$s will be replaced by a title. */
			$dialog['texts'][0] = '<p><strong>' . sprintf( __( 'Export to %1$s is not supported.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</strong></p>';
		}

		// return the resulting link.
		return '<span class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" title="' . esc_attr__( 'Not supported', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-editor-help"></span></span>';
	}

	/**
	 * Return the "not enabled" hint for table-view.
	 *
	 * @param object|false $listing_obj The used service object.
	 *
	 * @return string
	 */
	private function get_not_enabled_hint( object|false $listing_obj ): string {
		// bail if object is not an "Extension_Base" object.
		if ( ! $listing_obj instanceof Service_Base ) {
			return '';
		}

		// create the dialog for sync now.
		$dialog = array(
			'className' => 'efml',
			/* translators: %1$s will be replaced by a title. */
			'title'     => sprintf( __( '%1$s is not enabled', 'external-files-in-media-library' ), $listing_obj->get_label() ),
			'texts'     => array(
				/* translators: %1$s will be replaced by a title. */
				'<p>' . sprintf( __( 'Export to %1$s cannot be used as the service is not available.', 'external-files-in-media-library' ), $listing_obj->get_label() ) . '</p>',
			),
			'buttons'   => array(
				array(
					'action'  => 'closeDialog();',
					'variant' => 'primary',
					'text'    => __( 'OK', 'external-files-in-media-library' ),
				),
			),
		);
		return '<span class="easy-dialog-for-wordpress" data-dialog="' . esc_attr( Helper::get_json( $dialog ) ) . '" title="' . esc_attr__( 'Not enabled', 'external-files-in-media-library' ) . '"><span class="dashicons dashicons-editor-help"></span></span>';
	}
}
