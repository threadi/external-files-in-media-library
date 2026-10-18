<?php
/**
 * File as base for services, which import URLs from lists (like CSV or XML files).
 *
 * The list is used from an uploaded file in the media library or downloaded from a URL into the
 * media library. The file in the media library will not be changed or deleted by this plugin.
 *
 * Addresses used by these services:
 * - "<source>" for all URLs of the list (with the URL fields from the mapping)
 * - "<source>#efml-field=<field>" for all URLs of a single field (e.g., a column of a CSV)
 * - "<source>#efml-url=<url>" for a single URL of the list
 * where "<source>" is the URL of the list in the media library (for uploaded files) or the external URL
 * (for downloaded files).
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easySettingsForWordPress\Fields\Checkbox;
use easySettingsForWordPress\Fields\Number;
use easySettingsForWordPress\Page;
use easySettingsForWordPress\Section;
use easySettingsForWordPress\Tab;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols;
use ExternalFilesInMediaLibrary\ExternalFiles\Protocols\Http;
use ExternalFilesInMediaLibrary\ExternalFiles\Results;
use ExternalFilesInMediaLibrary\ExternalFiles\Results\Url_Result;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Plugin\Settings;
use ExternalFilesInMediaLibrary\Services\Url_List\Parser_Base;
use WP_Error;
use WP_Post;
use WP_Query;

/**
 * Object as base for services, which import URLs from lists.
 */
abstract class Url_List_Base extends Service_Base implements Service {
	/**
	 * Name of the post meta with the source URL of a downloaded list.
	 */
	public const META_SOURCE = 'efml_url_list_source';

	/**
	 * Name of the post meta with the hash of the content of a list.
	 */
	public const META_HASH = 'efml_url_list_hash';

	/**
	 * Name of the post meta with the ETag of a downloaded list.
	 */
	public const META_ETAG = 'efml_url_list_etag';

	/**
	 * Name of the post meta with the Last-Modified header of a downloaded list.
	 */
	public const META_LAST_MODIFIED = 'efml_url_list_last_modified';

	/**
	 * Name of the post meta with the timestamp of the last check of a downloaded list.
	 */
	public const META_CHECKED = 'efml_url_list_checked';

	/**
	 * Name of the post and term meta with the mapping of the fields.
	 */
	public const META_MAPPING = 'efml_url_list_mapping';

	/**
	 * Name of the post meta on lists, which are used via the directory listing (uploaded or downloaded), with the service name.
	 *
	 * These files could be deleted automatically, if the setting for it is enabled.
	 */
	public const META_TEMPORARY = 'efml_url_list_temporary';

	/**
	 * Name of the post meta with the timestamp of the last usage of a list.
	 */
	public const META_USED = 'efml_url_list_used';

	/**
	 * Name of the post meta on imported files with the ID of the list they are imported from.
	 */
	public const META_IMPORTED_FROM = 'efml_url_list';

	/**
	 * Marker in addresses for all URLs of a single field.
	 */
	private const ADDRESS_FIELD = 'efml-field=';

	/**
	 * Marker in addresses for a single URL.
	 */
	private const ADDRESS_URL = 'efml-url=';

	/**
	 * List of roles a field could have in the mapping.
	 */
	private const ROLES = array( 'url', 'alt', 'caption', 'description', 'date' );

	/**
	 * Marker for sync support (false to enable it).
	 *
	 * @var bool
	 */
	protected bool $sync_disabled = false;

	/**
	 * Cache for the parsed lists of this request (attachment ID as key).
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private array $lists = array();

	/**
	 * List of addresses, which are imported actually via this service (address as key).
	 *
	 * @var array<string,bool>
	 */
	private array $active_imports = array();

	/**
	 * The fields of the import object, which are removed during the saving of the files of a list.
	 *
	 * @var array<string,array<string,array<string,mixed>>>
	 */
	private array $removed_import_fields = array();

	/**
	 * Return the parser for this list format.
	 *
	 * @return Parser_Base
	 */
	abstract protected function get_parser(): Parser_Base;

	/**
	 * Return the possible file extensions for this list format (e.g. "csv").
	 *
	 * @return array<int,string>
	 */
	abstract protected function get_extensions(): array;

	/**
	 * Return the possible mime types for this list format.
	 *
	 * @return array<int,string>
	 */
	abstract protected function get_list_mime_types(): array;

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// use parent initialization.
		parent::init();

		// set the capability for this service on its first use (this object is initialized during "init").
		$this->set_default_capability();

		// add settings.
		add_action( 'init', array( $this, 'add_settings' ), 30 );

		// bail if user has no capability for this service.
		// scheduled synchronizations via cron and WP CLI run without user, but need the hooks for importing.
		if ( ! $this->is_import_allowed() ) {
			return;
		}

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_add_dialog', array( $this, 'add_mapping_in_dialog' ), 25, 2 );
		add_filter( 'efml_prevent_import', array( $this, 'save_mapping_from_request' ), 5, 3 );
		add_filter( 'efml_directory_translations', array( $this, 'change_translations' ) );
		add_action( 'efml_before_file_list', array( $this, 'remove_import_fields' ) );
		add_action( 'efml_after_import', array( $this, 'restore_import_fields' ) );
		add_action( 'efml_after_file_save', array( $this, 'set_file_data_after_saving' ), 10, 2 );
		add_action( 'efml_before_sync', array( $this, 'add_sync_hooks' ) );
		add_action( 'efml_url_list_import_file_before_to_list', array( Import::get_instance(), 'check_runtime' ), 10, 2 );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'add_media_row_action' ), 20, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 20, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_cleanup_hint' ), 21, 2 );
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_cleanup_meta_box' ), 20 );
		add_filter( 'upload_mimes', array( $this, 'allow_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'check_upload' ), 10, 3 );
	}

	/**
	 * Set the capability for this service on the default roles, if this has not been done yet.
	 *
	 * This is necessary for existing installations, as the capabilities of services are otherwise only set during
	 * the first installation or by saving the permissions in the settings. It runs only once per service.
	 *
	 * @return void
	 */
	public function set_default_capability(): void {
		// get the name of the marker.
		$marker = 'efml_' . $this->get_name() . '_capability_set';

		// bail if the capability has already been set.
		if ( 1 === absint( get_option( $marker, 0 ) ) ) {
			return;
		}

		// mark the capability as set (also if a role has it already, e.g. after a manual setting).
		update_option( $marker, 1 );

		// bail if any role has already the capability for this service.
		foreach ( wp_roles()->role_objects as $role_obj ) {
			if ( $role_obj->has_cap( $this->get_permission_name() ) ) {
				return;
			}
		}

		// save the default roles and set the capability for them.
		$option = 'eml_service_' . $this->get_name() . '_allowed_roles';
		update_option( $option, $this->get_default_roles() );
		\ExternalFilesInMediaLibrary\Plugin\Roles::get_instance()->save_capabilities_for_service( $this->get_default_roles(), array(), $option );

		// update the capabilities of the actual user for this request.
		if ( is_user_logged_in() ) {
			wp_get_current_user()->get_role_caps();
		}
	}

	/**
	 * Add settings for this service.
	 *
	 * @return void
	 */
	public function add_settings(): void {
		// bail if user has no capability for this service.
		if ( ! Helper::is_cli() && ! current_user_can( $this->get_permission_name() ) ) {
			return;
		}

		// get the settings object.
		$settings_obj = Settings::get_instance()->get_settings_obj();

		// get the settings page.
		$settings_page = $settings_obj->get_page( Settings::get_instance()->get_menu_slug() );

		// bail if page does not exist.
		if ( ! $settings_page instanceof Page ) {
			return;
		}

		// get tab for services.
		$services_tab = $settings_page->get_tab( $this->get_settings_tab_slug() );

		// bail if tab does not exist.
		if ( ! $services_tab instanceof Tab ) {
			return;
		}

		// get the tab of this service.
		$tab = $services_tab->get_tab( $this->get_settings_subtab_slug() );

		// bail if tab does not exist.
		if ( ! $tab instanceof Tab ) {
			return;
		}

		// get the main section of this service.
		$section = $tab->get_section( 'section_' . $this->get_name() . '_main' );

		// bail if section does not exist.
		if ( ! $section instanceof Section ) {
			return;
		}

		// add setting to show the import option in the media library.
		$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_media_library' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox( $settings_obj );
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		$field->set_title( sprintf( __( 'Show option to import URLs from %1$s files in media library', 'external-files-in-media-library' ), $this->get_label() ) );
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		$field->set_description( sprintf( __( 'If enabled, each %1$s file in the media library gets the option "Import URLs". It imports the URLs contained in the file as external files in your media library.', 'external-files-in-media-library' ), $this->get_label() ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// save the default value, if the setting does not exist (e.g., after an update), as the field shows the saved value.
		if ( is_null( get_option( 'eml_' . $this->get_name() . '_media_library', null ) ) ) {
			add_option( 'eml_' . $this->get_name() . '_media_library', 1 );
		}

		// add setting for the count of files per iteration.
		$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_import_limit' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 10 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Max. files to load during import per iteration', 'external-files-in-media-library' ) );
		$field->set_description( __( 'This value specifies how many files should be loaded during an import via the dialog. The higher the value, the greater the likelihood of timeouts during import.', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting to delete the files used in the directory listing automatically.
		$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_cleanup' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 1 );
		$field = new Checkbox( $settings_obj );
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		$field->set_title( sprintf( __( 'Delete used %1$s files automatically', 'external-files-in-media-library' ), $this->get_label() ) );
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		$field->set_description( sprintf( __( 'If enabled, %1$s files, which have been uploaded or downloaded for this service, are deleted from the media library once a day, if they have not been used for the configured days. The last downloaded version of a URL saved as external source is kept. Files you add to your media library yourself are never deleted.', 'external-files-in-media-library' ), $this->get_label() ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// add setting for the days after which the files are deleted.
		$setting = $settings_obj->add_setting( 'eml_' . $this->get_name() . '_cleanup_days' );
		$setting->set_section( $section );
		$setting->set_type( 'integer' );
		$setting->set_default( 7 );
		$field = new Number( $settings_obj );
		$field->set_title( __( 'Delete after days without usage', 'external-files-in-media-library' ) );
		$field->set_setting( $setting );
		$setting->set_field( $field );

		// save the default values, if the settings do not exist (e.g., after an update), as the fields show the saved values.
		foreach ( array(
			'cleanup'      => 1,
			'cleanup_days' => 7,
		) as $name => $default ) {
			if ( is_null( get_option( 'eml_' . $this->get_name() . '_' . $name, null ) ) ) {
				add_option( 'eml_' . $this->get_name() . '_' . $name, $default );
			}
		}
	}

	/**
	 * Return whether the automatic cleanup of used lists is enabled for this service.
	 *
	 * @return bool
	 */
	public function is_cleanup_enabled(): bool {
		return 1 === absint( get_option( 'eml_' . $this->get_name() . '_cleanup', 1 ) );
	}

	/**
	 * Return the days without usage, after which the used lists of this service are deleted (at least one day).
	 *
	 * @return int
	 */
	public function get_cleanup_days(): int {
		return max( 1, absint( get_option( 'eml_' . $this->get_name() . '_cleanup_days', 7 ) ) );
	}

	/**
	 * Return whether the given file is a list, which will be deleted automatically by the cleanup.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_deleted_by_cleanup( int $attachment_id ): bool {
		return $this->is_cleanup_enabled() && $this->get_name() === get_post_meta( $attachment_id, self::META_TEMPORARY, true ) && ! $this->is_kept_as_source( $attachment_id );
	}

	/**
	 * Return whether the given list is the last downloaded version of a URL, which is saved as external source.
	 *
	 * These lists are kept during the cleanup.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_kept_as_source( int $attachment_id ): bool {
		// get the source URL of this list.
		$source = (string) get_post_meta( $attachment_id, self::META_SOURCE, true );

		// bail if this is not a downloaded list or not its last version.
		if ( '' === $source || $this->get_attachment_id_by_source( $source ) !== $attachment_id ) {
			return false;
		}

		// return whether an external source of this service uses this URL.
		$terms = get_terms(
			array(
				'taxonomy'   => \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_name(),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_array( $terms ) ) {
			return false;
		}
		foreach ( $terms as $term_id ) {
			if ( $this->get_name() === get_term_meta( absint( $term_id ), 'type', true ) && get_term_meta( absint( $term_id ), 'path', true ) === $source ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Delete the lists of this service, which have been used via the directory listing and not used since the
	 * configured days. The last downloaded version of a URL, which is saved as external source, is kept.
	 *
	 * @param int  $days The days without usage (0 to use the setting).
	 * @param bool $force True to run the cleanup also if it is disabled in the settings.
	 * @param bool $dry_run True to only return the files, which would be deleted.
	 *
	 * @return array<int,int> List of IDs of the deleted files (or of the files, which would be deleted).
	 */
	public function cleanup( int $days = 0, bool $force = false, bool $dry_run = false ): array {
		// bail if cleanup is disabled.
		if ( ! $force && ! $this->is_cleanup_enabled() ) {
			return array();
		}

		// get the minimum time without usage (at least one day, so lists in use are never deleted).
		$days = $days > 0 ? $days : $this->get_cleanup_days();

		// get the lists to delete.
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for used lists.
				'relation' => 'AND',
				array(
					'key'   => self::META_TEMPORARY,
					'value' => $this->get_name(),
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => self::META_USED,
						'value'   => time() - $days * DAY_IN_SECONDS,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::META_USED,
						'compare' => 'NOT EXISTS',
					),
				),
			),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// delete the files.
		$deleted = array();
		foreach ( $result->get_posts() as $attachment_id ) {
			$attachment_id = absint( $attachment_id );

			// keep the last version of a URL, which is saved as external source.
			if ( $this->is_kept_as_source( $attachment_id ) ) {
				continue;
			}

			// delete the file, if this is not a dry run.
			if ( $dry_run || wp_delete_attachment( $attachment_id, true ) ) {
				$deleted[] = $attachment_id;
			}
		}

		// log this event.
		if ( ! $dry_run && ! empty( $deleted ) ) {
			/* translators: %1$d will be replaced by a number, %2$s by the label of the format (e.g. CSV). */
			Log::get_instance()->create( sprintf( __( '%1$d used %2$s files have been deleted from the media library.', 'external-files-in-media-library' ), count( $deleted ), $this->get_label() ), '', 'info', 1 );
		}

		// return the list of files.
		return $deleted;
	}

	/**
	 * Return the texts of the hint about the automatic deletion of the given list.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,string>
	 */
	private function get_cleanup_hint( int $attachment_id ): array {
		// get the date of the deletion.
		$used = absint( get_post_meta( $attachment_id, self::META_USED, true ) );
		$date = (string) wp_date( get_option( 'date_format' ), max( time(), $used + $this->get_cleanup_days() * DAY_IN_SECONDS ) );

		// get the hint about the settings.
		/* translators: %1$s will be replaced by a URL. */
		$settings = sprintf( __( 'You can change this behavior <a href="%1$s">in the settings</a>.', 'external-files-in-media-library' ), esc_url( Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			$settings = __( 'Your administrator can change this behavior in the settings.', 'external-files-in-media-library' );
		}

		// return the texts.
		return array(
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV), %2$d by a number of days. */
			'text'     => sprintf( __( 'This %1$s file has been saved to import its URLs. It will be deleted automatically if it is not used for %2$d days.', 'external-files-in-media-library' ), $this->get_label(), $this->get_cleanup_days() ),
			'date'     => $date,
			'delete'   => __( 'You can also delete it yourself at any time.', 'external-files-in-media-library' ),
			'settings' => $settings,
		);
	}

	/**
	 * Show a hint in the details of a list in the media modal, which will be deleted automatically.
	 *
	 * On the edit page of the file the hint is shown in a meta box.
	 *
	 * @param array<string,array<string,mixed>> $form_fields List of fields.
	 * @param WP_Post                           $post The attachment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_cleanup_hint( array $form_fields, WP_Post $post ): array {
		// bail if this file will not be deleted automatically.
		if ( ! $this->is_deleted_by_cleanup( $post->ID ) ) {
			return $form_fields;
		}

		// get the hint.
		$hint = $this->get_cleanup_hint( $post->ID );

		// create the text.
		/* translators: %1$s will be replaced by a date. */
		$text = $hint['text'] . ' ' . sprintf( __( 'At the earliest on %1$s.', 'external-files-in-media-library' ), $hint['date'] ) . ' ' . $hint['delete'] . ' ' . $hint['settings'];

		// add the field.
		$form_fields[ 'efml_url_list_cleanup_' . $this->get_name() ] = array(
			'label'         => __( 'Automatic deletion', 'external-files-in-media-library' ),
			'input'         => 'html',
			'html'          => '<p class="efml-url-list-cleanup-hint"><span class="dashicons dashicons-clock"></span> ' . wp_kses_post( $text ) . '</p>',
			'show_in_edit'  => false,
			'show_in_modal' => true,
		);

		// return the resulting list of fields.
		return $form_fields;
	}

	/**
	 * Add a meta box with the hint about the automatic deletion on the edit page of a list.
	 *
	 * @param WP_Post $post The attachment.
	 *
	 * @return void
	 */
	public function add_cleanup_meta_box( WP_Post $post ): void {
		// bail if this file will not be deleted automatically.
		if ( ! $this->is_deleted_by_cleanup( $post->ID ) ) {
			return;
		}

		// add the box.
		add_meta_box( 'efml_url_list_cleanup_' . $this->get_name(), __( 'Automatic deletion', 'external-files-in-media-library' ), array( $this, 'show_cleanup_meta_box' ), 'attachment', 'side', 'low' );
	}

	/**
	 * Show the content of the meta box with the hint about the automatic deletion.
	 *
	 * @param WP_Post $post The attachment.
	 *
	 * @return void
	 */
	public function show_cleanup_meta_box( WP_Post $post ): void {
		// get the hint.
		$hint = $this->get_cleanup_hint( $post->ID );

		// output.
		?>
		<div class="misc-pub-external-file">
			<p><?php echo esc_html( $hint['text'] ); ?></p>
		</div>
		<ul class="misc-pub-external-file">
			<li><span class="dashicons dashicons-clock"></span> <?php echo esc_html__( 'Deletion at the earliest on', 'external-files-in-media-library' ) . ' <code>' . esc_html( $hint['date'] ) . '</code>'; ?></li>
			<li><span class="dashicons dashicons-trash"></span> <?php echo esc_html( $hint['delete'] ); ?></li>
			<li><span class="dashicons dashicons-admin-settings"></span> <?php echo wp_kses_post( $hint['settings'] ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Add our own protocol, which handles the addresses of lists.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// bail if protocol is already in the list (it is used by each list service).
		if ( in_array( 'ExternalFilesInMediaLibrary\Services\Url_List\Protocol', $protocols, true ) ) {
			return $protocols;
		}

		// add the protocol before all others and return the resulting list.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\Url_List\Protocol' );
		return $protocols;
	}

	/**
	 * Return whether this service could be used for imports in the actual request.
	 *
	 * @return bool
	 */
	public function is_usable_for_import(): bool {
		return $this->is_import_allowed();
	}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			$accept = implode( ',', array_merge( array_map( fn( $extension ) => '.' . $extension, $this->get_extensions() ), $this->get_list_mime_types() ) );

			/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
			$one_of_error = sprintf( __( 'Please use only one %1$s file: either upload a file or enter a URL.', 'external-files-in-media-library' ), $this->get_label() );

			$this->fields = array(
				'list_type' => array(
					'name'         => 'list_type',
					'type'         => 'hidden',
					'value'        => $this->get_name(),
					'not_required' => true,
				),
				'list_file' => array(
					'name'         => 'list_file',
					'type'         => 'file',
					/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
					'label'        => sprintf( __( 'Upload a %1$s file', 'external-files-in-media-library' ), $this->get_label() ),
					'accept'       => $accept,
					'upload_path'  => '/wp/v2/media',
					'one_of'       => 'source',
					'one_of_error' => $one_of_error,
					'description'  => __( 'The file will be saved in your media library.', 'external-files-in-media-library' ),
				),
				'list_url'  => array(
					'name'         => 'list_url',
					'type'         => 'url',
					/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
					'label'        => sprintf( __( 'Or enter the URL of a %1$s file', 'external-files-in-media-library' ), $this->get_label() ),
					'placeholder'  => 'https://example.com/list.' . $this->get_extensions()[0],
					'one_of'       => 'source',
					'one_of_error' => $one_of_error,
					'description'  => __( 'The file will be downloaded into your media library. If it changes, it will be downloaded again.', 'external-files-in-media-library' ),
				),
				'login'     => array(
					'name'         => 'login',
					'type'         => 'text',
					'label'        => __( 'Auth Basic Login for the URL (optional)', 'external-files-in-media-library' ),
					'placeholder'  => __( 'Your login', 'external-files-in-media-library' ),
					'not_required' => true,
					'credential'   => true,
				),
				'password'  => array(
					'name'         => 'password',
					'type'         => 'password',
					'label'        => __( 'Auth Basic Password for the URL (optional)', 'external-files-in-media-library' ),
					'placeholder'  => __( 'Your password', 'external-files-in-media-library' ),
					'not_required' => true,
					'credential'   => true,
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return the configuration for the directory listing.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config(): array {
		// get the base config.
		$config = parent::get_config();

		// show the option to save the source only for URLs.
		$config['archive_requires_field'] = 'list_url';

		// return the resulting config.
		return $config;
	}

	/**
	 * Return whether the actual configuration could be saved as external source.
	 *
	 * This is only possible for downloaded lists, not for uploaded files.
	 *
	 * @return bool
	 */
	public function is_archive_allowed(): bool {
		return '' !== $this->get_field_value( $this->get_fields(), 'list_url' ) && 0 === absint( $this->get_field_value( $this->get_fields(), 'list_file' ) );
	}

	/**
	 * Return the directory to load from fields.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// get the source from the fields.
		$source = $this->get_source( $this->get_fields() );

		// use the configured directory, if no source is set in fields.
		if ( '' === $source ) {
			return parent::get_directory();
		}

		// return the source.
		return $source;
	}

	/**
	 * Check the configuration and prepare the list: download it into the media library, if necessary.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		// get the fields.
		$fields = $this->get_fields();

		// get the list.
		$url_list = $this->get_list( $fields );

		// bail on any error.
		if ( $url_list instanceof WP_Error ) {
			$this->add_error( $url_list );
			return false;
		}

		// mark the file as used via the directory listing: it could be deleted automatically later.
		update_post_meta( absint( $url_list['attachment_id'] ), self::META_TEMPORARY, $this->get_name() );

		// return true as the list is usable.
		return true;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * The fields with URLs are shown as directories, their URLs as files.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get the fields.
		$fields = $this->get_fields();

		// get the list.
		$url_list = $this->get_list( $fields );

		// bail on any error.
		if ( $url_list instanceof WP_Error ) {
			$this->add_error( $url_list );
			return array();
		}

		// get the source.
		$source = $this->get_source( $fields );

		// get the mapping to sort the fields.
		$mapping = $this->get_mapping( $url_list, false );

		// get the date field from the mapping.
		$date_field = $mapping['date'];

		// get the host of a downloaded list: files on this host could be imported with the credentials of the list.
		$list_host = strtolower( (string) wp_parse_url( $this->get_field_value( $fields, 'list_url' ), PHP_URL_HOST ) );

		// create the root entry.
		$root = array(
			'title' => $this->get_list_title( $url_list ),
			'files' => array(),
			'dirs'  => array(),
		);

		// add each field with URLs as directory.
		$folders = array();
		foreach ( $this->get_sorted_url_fields( $url_list, $mapping ) as $key ) {
			// create the path of this directory.
			$path = $source . '#' . self::ADDRESS_FIELD . rawurlencode( $key ) . '/';

			// collect the files of this directory.
			$files = array();
			$urls  = array();
			foreach ( $url_list['records'] as $record ) {
				foreach ( $record[ $key ] ?? array() as $url ) {
					// bail if this is not a URL or already in the list.
					if ( ! $this->is_url( $url ) || isset( $urls[ $url ] ) ) {
						continue;
					}

					// mark this URL as used.
					$urls[ $url ] = true;

					// get the date of this URL.
					$date = '';
					if ( '' !== $date_field && ! empty( $record[ $date_field ][0] ) ) {
						$timestamp = strtotime( $record[ $date_field ][0] );
						if ( $timestamp ) {
							$date = Helper::get_format_date_time( gmdate( 'Y-m-d H:i:s', $timestamp ) );
						}
					}

					// get the mime type by the file extension.
					$mime_type = $this->get_mime_type_by_url( $url );

					// add the entry with the URL from the list, which is imported like any other URL.
					$files[] = array(
						'title'         => Helper::shorten_url( $url ),
						'file'          => $url,
						'same_host'     => '' !== $list_host && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === $list_host,
						'filesize'      => 0,
						'mime-type'     => $mime_type,
						'icon'          => '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $mime_type ) . '"></span>',
						'last-modified' => $date,
						'preview'       => '',
					);
				}
			}

			// add this directory to the root.
			$root['dirs'][ $path ] = array(
				'title' => $url_list['fields'][ $key ]['label'],
				'files' => array(),
				'dirs'  => array(),
			);

			// add the directory with its files.
			$folders[ $path ] = array(
				/* translators: %1$s will be replaced by the name of a field, %2$d by a number. */
				'title' => sprintf( __( '%1$s (%2$d)', 'external-files-in-media-library' ), $url_list['fields'][ $key ]['label'], count( $files ) ),
				'files' => $files,
				'dirs'  => array(),
			);
		}

		// return the complete tree.
		return array_merge( array( 'completed' => true ), array( $source => $root ), $folders );
	}

	/**
	 * Return the actions for each file.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "fields": file.same_host ? config.fields : {}, "term": file.same_host ? config.term : 0 } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";! file["mime-type"] || mimetypes.includes( file["mime-type"] )',
				'hint'   => '<span class="dashicons dashicons-editor-help" title="' . esc_attr__( 'File-type is not supported', 'external-files-in-media-library' ) . '"></span>',
			),
		);
	}

	/**
	 * Return global actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_global_actions(): array {
		return array_merge(
			parent::get_global_actions(),
			array(
				array(
					'action' => 'location.href="' . esc_url( Settings::get_instance()->get_url( $this->get_settings_tab_slug(), $this->get_settings_subtab_slug() ) ) . '";',
					'label'  => __( 'Settings', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "fields": config.fields, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", config.fields.list_url.value, config.fields, config.term );',
					'label'  => __( 'Save this URL as your external source', 'external-files-in-media-library' ),
					'show'   => '! config.term && config.fields.list_url && config.fields.list_url.value && ! ( config.fields.list_file && config.fields.list_file.value )',
				),
			)
		);
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		return sprintf( __( 'Choose the %1$s file with the URLs', 'external-files-in-media-library' ), $this->get_label() );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		return sprintf( __( 'Upload a %1$s file or enter its URL. The URLs it contains are displayed afterwards, and you can import all or individual of them into your media library.', 'external-files-in-media-library' ), $this->get_label() );
	}

	/**
	 * Change some translations on directory listing.
	 *
	 * @param array<string,mixed> $translations List of translations.
	 *
	 * @return array<string,mixed>
	 */
	public function change_translations( array $translations ): array {
		// add the texts for the file upload in forms.
		$translations['form_file'] = array(
			'uploading'       => __( 'Uploading file …', 'external-files-in-media-library' ),
			'uploaded'        => __( 'Uploaded file:', 'external-files-in-media-library' ),
			'remove'          => __( 'Remove file', 'external-files-in-media-library' ),
			'upload_error'    => __( 'The file could not be uploaded.', 'external-files-in-media-library' ),
			'one_of_multiple' => __( 'Please fill in only one of the fields.', 'external-files-in-media-library' ),
		);

		// get requested method.
		$method = filter_input( INPUT_GET, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if method is not this service.
		if ( $this->get_name() !== $method ) {
			return $translations;
		}

		// change translations.
		$translations['form_login']['button']['label'] = __( 'Show URLs', 'external-files-in-media-library' );

		// return the resulting list of translations.
		return $translations;
	}

	/**
	 * Return the value of a field.
	 *
	 * @param array<string,mixed> $fields The fields.
	 * @param string              $name The name of the field.
	 *
	 * @return string
	 */
	protected function get_field_value( array $fields, string $name ): string {
		// bail if field is not set.
		if ( ! isset( $fields[ $name ] ) || ! is_array( $fields[ $name ] ) || ! isset( $fields[ $name ]['value'] ) || ! is_scalar( $fields[ $name ]['value'] ) ) {
			return '';
		}

		// return the value.
		return trim( (string) $fields[ $name ]['value'] );
	}

	/**
	 * Return whether the given fields are for this service.
	 *
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return bool
	 */
	public function is_for_fields( array $fields ): bool {
		return $this->get_name() === $this->get_field_value( $fields, 'list_type' );
	}

	/**
	 * Return the source of the list: the URL of the uploaded file in media library or the external URL.
	 *
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return string
	 */
	public function get_source( array $fields ): string {
		// use the uploaded file, if set.
		$attachment_id = absint( $this->get_field_value( $fields, 'list_file' ) );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			return is_string( $url ) ? $url : '';
		}

		// use the URL without fragment.
		$url = $this->get_field_value( $fields, 'list_url' );
		if ( str_contains( $url, '#' ) ) {
			$url = (string) strstr( $url, '#', true );
		}

		// return the URL.
		return $url;
	}

	/**
	 * Parse the given address of a list.
	 *
	 * @param string              $url The address.
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return array{scope: string, value: string}|false
	 */
	public function parse_address( string $url, array $fields ): array|false {
		// bail if fields are not for this service.
		if ( ! $this->is_for_fields( $fields ) ) {
			return false;
		}

		// get the source.
		$source = $this->get_source( $fields );

		// bail if no source is given.
		if ( '' === $source ) {
			return false;
		}

		// compare the URLs without their scheme, as the URL of a file in media library could use another scheme
		// depending on the request (e.g. behind a proxy with SSL).
		$url    = (string) preg_replace( '#^https?://#i', '//', $url );
		$source = (string) preg_replace( '#^https?://#i', '//', $source );

		// the source itself means all URLs of the list (also with "/" at the end, like the directory listing uses it).
		if ( $url === $source || untrailingslashit( $url ) === untrailingslashit( $source ) ) {
			return array(
				'scope' => 'all',
				'value' => '',
			);
		}

		// bail if the address does not start with the source.
		if ( ! str_starts_with( $url, $source . '#' ) ) {
			return false;
		}

		// get the fragment without "/" at the end (the values are encoded and do not contain "/").
		$fragment = untrailingslashit( substr( $url, strlen( $source ) + 1 ) );

		// a single field.
		if ( str_starts_with( $fragment, self::ADDRESS_FIELD ) ) {
			return array(
				'scope' => 'field',
				'value' => rawurldecode( substr( $fragment, strlen( self::ADDRESS_FIELD ) ) ),
			);
		}

		// a single URL.
		if ( str_starts_with( $fragment, self::ADDRESS_URL ) ) {
			return array(
				'scope' => 'url',
				'value' => rawurldecode( substr( $fragment, strlen( self::ADDRESS_URL ) ) ),
			);
		}

		// return false for any other address.
		return false;
	}

	/**
	 * Return the ID of the list file in media library.
	 *
	 * For uploaded files the given ID is checked. For URLs the file will be downloaded into the media library,
	 * if it is not there or if it has been changed.
	 *
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return int|WP_Error
	 */
	public function get_list_attachment_id( array $fields ): int|WP_Error {
		// get the values.
		$attachment_id = absint( $this->get_field_value( $fields, 'list_file' ) );
		$url           = $this->get_field_value( $fields, 'list_url' );

		// bail if both are given.
		if ( $attachment_id > 0 && '' !== $url ) {
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'Please use only one %1$s file: either upload a file or enter a URL.', 'external-files-in-media-library' ), $this->get_label() ) );
		}

		// use the uploaded file.
		if ( $attachment_id > 0 ) {
			return $this->check_attachment( $attachment_id );
		}

		// bail if no URL is given.
		if ( '' === $url ) {
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'Please upload a %1$s file or enter its URL.', 'external-files-in-media-library' ), $this->get_label() ) );
		}

		// download the file, if necessary.
		return $this->get_attachment_for_url( $this->get_source( $fields ), $this->get_field_value( $fields, 'login' ), $this->get_field_value( $fields, 'password' ) );
	}

	/**
	 * Check whether the given attachment is a usable list.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return int|WP_Error
	 */
	private function check_attachment( int $attachment_id ): int|WP_Error {
		// get the attachment.
		$post = get_post( $attachment_id );

		// bail if this is not an attachment.
		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'efml_service_' . $this->get_name(), __( 'The file could not be found in the media library.', 'external-files-in-media-library' ) );
		}

		// bail if the actual user is not allowed to use this file. Requests without a user (e.g. via cron) are already checked.
		if ( get_current_user_id() > 0 && ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'efml_service_' . $this->get_name(), __( 'You are not allowed to use this file.', 'external-files-in-media-library' ) );
		}

		// bail if the file is not a list of this format.
		if ( ! $this->is_list_attachment( $attachment_id ) ) {
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The file is not a %1$s file.', 'external-files-in-media-library' ), $this->get_label() ) );
		}

		// return the ID as the file is usable.
		return $attachment_id;
	}

	/**
	 * Return whether the given attachment is a list of this format, which is readable in the local filesystem.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	public function is_list_attachment( int $attachment_id ): bool {
		// get the local file.
		$file = get_attached_file( $attachment_id );

		// bail if file is not readable.
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		// return whether the file extension or the mime type matches.
		return in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), $this->get_extensions(), true ) || in_array( get_post_mime_type( $attachment_id ), $this->get_list_mime_types(), true );
	}

	/**
	 * Return the attachment ID for the list from the given URL.
	 *
	 * If the file has been downloaded before, its ETag and Last-Modified header are used to check whether it has
	 * been changed. If it has been changed (also checked by the hash of its content), the new version is saved as
	 * new file in the media library. Existing files are never changed.
	 *
	 * @param string $url The URL of the list.
	 * @param string $login The login for Auth Basic.
	 * @param string $password The password for Auth Basic.
	 *
	 * @return int|WP_Error
	 */
	public function get_attachment_for_url( string $url, string $login = '', string $password = '' ): int|WP_Error {
		// bail if this is not a usable URL.
		if ( ! $this->is_url( $url ) ) {
			return new WP_Error( 'efml_service_' . $this->get_name(), __( 'The given URL is not valid. Please use a URL beginning with http:// or https://.', 'external-files-in-media-library' ) );
		}

		// get the last downloaded version of this list.
		$attachment_id = $this->get_attachment_id_by_source( $url );

		/**
		 * Filter the time in seconds, in which a downloaded list is not checked again for changes.
		 *
		 * This prevents a check on each request of the directory listing or the import dialog.
		 * Synchronizations always check the list.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param int $seconds The time in seconds.
		 * @param string $url The URL of the list.
		 */
		$cache_time = absint( apply_filters( 'efml_url_list_check_interval', 60, $url ) );

		// use the existing file, if it has been checked recently.
		if ( $attachment_id > 0 && ! $this->is_sync_running() && absint( get_post_meta( $attachment_id, self::META_CHECKED, true ) ) > time() - $cache_time ) {
			return $attachment_id;
		}

		// prepare the headers for the request.
		$headers = array();
		if ( $attachment_id > 0 ) {
			$etag = get_post_meta( $attachment_id, self::META_ETAG, true );
			if ( is_string( $etag ) && '' !== $etag ) {
				$headers['If-None-Match'] = $etag;
			}
			$last_modified = get_post_meta( $attachment_id, self::META_LAST_MODIFIED, true );
			if ( is_string( $last_modified ) && '' !== $last_modified ) {
				$headers['If-Modified-Since'] = $last_modified;
			}
		}

		// get a temporary file for the download.
		require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		$tmp_file = wp_tempnam( $url );

		// request the URL.
		$response = $this->request( $url, $headers, $login, $password, $tmp_file );

		// bail on any error.
		if ( $response instanceof WP_Error ) {
			wp_delete_file( $tmp_file );
			return $response;
		}

		// get the HTTP status.
		$http_status = absint( wp_remote_retrieve_response_code( $response ) );

		// use the existing file, if it has not been changed.
		if ( 304 === $http_status && $attachment_id > 0 ) {
			wp_delete_file( $tmp_file );
			update_post_meta( $attachment_id, self::META_CHECKED, time() );

			// log this event.
			Log::get_instance()->create( __( 'The list has not been changed since the last download.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// return the ID of the existing file.
			return $attachment_id;
		}

		// bail if the status is not 200.
		if ( 200 !== $http_status ) {
			wp_delete_file( $tmp_file );

			// log this event.
			/* translators: %1$d will be replaced by the HTTP status. */
			Log::get_instance()->create( sprintf( __( 'The list could not be downloaded. The URL responded with HTTP status %1$d.', 'external-files-in-media-library' ), $http_status ), $url, 'error' );

			// return specific error for missing or wrong credentials.
			if ( in_array( $http_status, array( 401, 403 ), true ) ) {
				/* translators: %1$d will be replaced by the HTTP status. */
				return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'Access to the URL was denied (HTTP status %1$d). Please check the login and password.', 'external-files-in-media-library' ), $http_status ) );
			}

			/* translators: %1$d will be replaced by the HTTP status. */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The file could not be downloaded. The URL responded with HTTP status %1$d.', 'external-files-in-media-library' ), $http_status ) );
		}

		// get the hash of the content.
		$hash = (string) hash_file( 'sha256', $tmp_file );

		// get the headers for the next check.
		$new_etag          = $this->get_header( $response, 'etag' );
		$new_last_modified = $this->get_header( $response, 'last-modified' );

		// use the existing file, if the content has not been changed.
		if ( $attachment_id > 0 && get_post_meta( $attachment_id, self::META_HASH, true ) === $hash ) {
			wp_delete_file( $tmp_file );
			update_post_meta( $attachment_id, self::META_ETAG, $new_etag );
			update_post_meta( $attachment_id, self::META_LAST_MODIFIED, $new_last_modified );
			update_post_meta( $attachment_id, self::META_CHECKED, time() );

			// log this event.
			Log::get_instance()->create( __( 'The content of the list has not been changed since the last download.', 'external-files-in-media-library' ), $url, 'info', 2 );

			// return the ID of the existing file.
			return $attachment_id;
		}

		// check whether the downloaded file could be parsed.
		$parser = $this->get_parser();
		if ( ! $parser->parse( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV), %2$s by an error message. */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The downloaded file could not be read as %1$s file: %2$s', 'external-files-in-media-library' ), $this->get_label(), $parser->get_error() ) );
		}

		// save the file in the media library.
		$new_attachment_id = $this->save_in_media_library( $tmp_file, $this->get_filename_for_download( $url, $this->get_header( $response, 'content-disposition' ) ) );

		// bail on any error.
		if ( $new_attachment_id instanceof WP_Error ) {
			wp_delete_file( $tmp_file );
			return $new_attachment_id;
		}

		// save the data of the download.
		update_post_meta( $new_attachment_id, self::META_SOURCE, $url );
		update_post_meta( $new_attachment_id, self::META_HASH, $hash );
		update_post_meta( $new_attachment_id, self::META_ETAG, $new_etag );
		update_post_meta( $new_attachment_id, self::META_LAST_MODIFIED, $new_last_modified );
		update_post_meta( $new_attachment_id, self::META_CHECKED, time() );
		update_post_meta( $new_attachment_id, self::META_TEMPORARY, $this->get_name() );

		// use the mapping of the previous version.
		if ( $attachment_id > 0 ) {
			$mapping = get_post_meta( $attachment_id, self::META_MAPPING, true );
			if ( is_array( $mapping ) ) {
				update_post_meta( $new_attachment_id, self::META_MAPPING, $mapping );
			}
		}

		// log this event.
		/* translators: %1$s will be replaced by a URL. */
		Log::get_instance()->create( sprintf( __( 'The list has been downloaded and saved in the media library: %1$s', 'external-files-in-media-library' ), '<a href="' . esc_url( (string) get_edit_post_link( $new_attachment_id ) ) . '">' . esc_html( get_the_title( $new_attachment_id ) ) . '</a>' ), $url, 'success', 1 );

		// return the ID of the new file.
		return $new_attachment_id;
	}

	/**
	 * Request the given URL and save the response body in the given file.
	 *
	 * Redirects are followed manually, so the credentials are only sent to the host of the given URL.
	 *
	 * @param string               $url The URL.
	 * @param array<string,string> $headers The additional headers.
	 * @param string               $login The login for Auth Basic.
	 * @param string               $password The password for Auth Basic.
	 * @param string               $file The file to save the body in.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	private function request( string $url, array $headers, string $login, string $password, string $file ): array|WP_Error {
		// get the host of the URL, which gets the credentials.
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		/**
		 * Filter the maximum size of a list to download in bytes.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param int $max_size The maximum size in bytes (default: maximum upload size of WordPress).
		 * @param string $url The URL of the list.
		 */
		$max_size = absint( apply_filters( 'efml_url_list_max_size', wp_max_upload_size(), $url ) );

		// add the plugin to the user agent, if enabled.
		Import::get_instance()->add_task_to_set_user_agent();

		// follow up to 5 redirects.
		for ( $i = 0; $i <= 5; $i++ ) {
			// prepare the request.
			$args = array(
				'timeout'     => max( 5, absint( get_option( 'eml_timeout', 30 ) ) ),
				'redirection' => 0,
				'stream'      => true,
				'filename'    => $file,
				'headers'     => $headers,
			);
			if ( $max_size > 0 ) {
				$args['limit_response_size'] = $max_size;
			}

			// add the credentials only for the host of the list.
			if ( '' !== $login && '' !== $password && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === $host ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64 is the encoding required for HTTP Basic authentication (RFC 7617).
				$args['headers']['Authorization'] = 'Basic ' . base64_encode( $login . ':' . $password );
			}

			// send the request.
			$response = wp_safe_remote_get( $url, $args );

			// bail on any error.
			if ( is_wp_error( $response ) ) {
				// log this event.
				Log::get_instance()->create( __( 'The list could not be downloaded. Error occurred:', 'external-files-in-media-library' ) . ' <code>' . esc_html( Helper::get_json( $response->get_error_messages() ) ) . '</code>', $url, 'error' );

				/* translators: %1$s will be replaced by an error message. */
				return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The file could not be downloaded: %1$s', 'external-files-in-media-library' ), esc_html( $response->get_error_message() ) ) );
			}

			// get the HTTP status.
			$http_status = absint( wp_remote_retrieve_response_code( $response ) );

			// bail if this is not a redirect.
			if ( ! in_array( $http_status, array( 301, 302, 303, 307, 308 ), true ) ) {
				return $response;
			}

			// get the target of the redirect.
			$location = $this->get_header( $response, 'location' );
			if ( '' === $location ) {
				return $response;
			}

			// use the absolute target as URL for the next request.
			$url = \WP_Http::make_absolute_url( $location, $url );
		}

		// return error as there were too many redirects.
		return new WP_Error( 'efml_service_' . $this->get_name(), __( 'The file could not be downloaded: too many redirects.', 'external-files-in-media-library' ) );
	}

	/**
	 * Return a header of the given response as string.
	 *
	 * @param array<string,mixed> $response The response.
	 * @param string              $name The name of the header.
	 *
	 * @return string
	 */
	private function get_header( array $response, string $name ): string {
		// get the header.
		$header = wp_remote_retrieve_header( $response, $name );

		// use the last value, if the header is set multiple times.
		if ( is_array( $header ) ) {
			$header = end( $header );
		}

		// return the header as string.
		return is_string( $header ) ? trim( $header ) : '';
	}

	/**
	 * Return the file name for a downloaded list.
	 *
	 * @param string $url The URL.
	 * @param string $content_disposition The Content-Disposition header.
	 *
	 * @return string
	 */
	private function get_filename_for_download( string $url, string $content_disposition ): string {
		// use the file name from the Content-Disposition header, if set.
		$filename = '';
		if ( preg_match( '/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $content_disposition, $matches ) ) {
			$filename = wp_basename( rawurldecode( $matches[1] ) );
		}

		// use the file name from the URL.
		if ( '' === $filename ) {
			$filename = wp_basename( rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		}

		// use the host as file name, if no other is given.
		if ( '' === $filename ) {
			$filename = (string) wp_parse_url( $url, PHP_URL_HOST );
		}

		// add the extension of this format, if the file name has another one.
		if ( ! in_array( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ), $this->get_extensions(), true ) ) {
			$filename .= '.' . $this->get_extensions()[0];
		}

		// return the sanitized file name.
		return sanitize_file_name( $filename );
	}

	/**
	 * Save the given file in the media library.
	 *
	 * @param string $tmp_file The path to the temporary file.
	 * @param string $filename The file name.
	 *
	 * @return int|WP_Error
	 */
	private function save_in_media_library( string $tmp_file, string $filename ): int|WP_Error {
		// embed necessary files.
		require_once ABSPATH . 'wp-admin/includes/image.php'; // @phpstan-ignore requireOnce.fileNotFound
		require_once ABSPATH . 'wp-admin/includes/file.php'; // @phpstan-ignore requireOnce.fileNotFound
		require_once ABSPATH . 'wp-admin/includes/media.php'; // @phpstan-ignore requireOnce.fileNotFound

		// prepare the file.
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
			'error'    => '0',
			'size'     => (string) filesize( $tmp_file ),
		);

		// prepare the attachment.
		$post_data = array(
			'post_author' => Helper::get_current_user_id(),
		);

		// allow the upload of this format during this request, also without user (e.g. during synchronization).
		add_filter( 'upload_mimes', array( $this, 'add_mime_types' ) );

		// save the file.
		$attachment_id = media_handle_sideload( $file_array, 0, null, $post_data );

		// remove our filter.
		remove_filter( 'upload_mimes', array( $this, 'add_mime_types' ) );

		// bail on any error.
		if ( is_wp_error( $attachment_id ) ) {
			// log this event.
			Log::get_instance()->create( __( 'The list could not be saved in the media library. Error occurred:', 'external-files-in-media-library' ) . ' <code>' . esc_html( $attachment_id->get_error_message() ) . '</code>', $filename, 'error' );

			/* translators: %1$s will be replaced by an error message. */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The file could not be saved in the media library: %1$s', 'external-files-in-media-library' ), esc_html( $attachment_id->get_error_message() ) ) );
		}

		// return the ID.
		return $attachment_id;
	}

	/**
	 * Return the ID of the last downloaded version of the list from the given URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return int
	 */
	private function get_attachment_id_by_source( string $url ): int {
		$query  = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filter for the source of the list.
				array(
					'key'   => self::META_SOURCE,
					'value' => $url,
				),
			),
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);
		$result = new WP_Query( $query );

		// return the first ID, if one has been found.
		return empty( $result->posts ) ? 0 : absint( $result->posts[0] );
	}

	/**
	 * Return the parsed and analyzed list for the given fields.
	 *
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_list( array $fields ): array|WP_Error {
		// get the attachment.
		$attachment_id = $this->get_list_attachment_id( $fields );

		// bail on any error.
		if ( $attachment_id instanceof WP_Error ) {
			return $attachment_id;
		}

		// use the cache, if set.
		if ( isset( $this->lists[ $attachment_id ] ) ) {
			return $this->lists[ $attachment_id ];
		}

		// save the time of the last usage of this list (for the automatic cleanup).
		update_post_meta( $attachment_id, self::META_USED, time() );

		// get the parser.
		$parser = $this->get_parser();

		/**
		 * Filter the maximum count of records to read from a list.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param int $max_records The maximum count.
		 * @param int $attachment_id The ID of the list in the media library.
		 */
		$parser->set_max_records( absint( apply_filters( 'efml_url_list_max_records', 100000, $attachment_id ) ) );

		// parse the file.
		if ( ! $parser->parse( (string) get_attached_file( $attachment_id ) ) ) {
			/* translators: %1$s will be replaced by the label of the format (e.g. CSV), %2$s by an error message. */
			return new WP_Error( 'efml_service_' . $this->get_name(), sprintf( __( 'The file could not be read as %1$s file: %2$s', 'external-files-in-media-library' ), $this->get_label(), $parser->get_error() ) );
		}

		// get the result.
		$result = $parser->get_result();

		// log the reached limit.
		if ( $result['limit_reached'] ) {
			/* translators: %1$d will be replaced by a number. */
			Log::get_instance()->create( sprintf( __( 'The list contains more than %1$d entries. Only these entries are used.', 'external-files-in-media-library' ), count( $result['records'] ) ), (string) wp_get_attachment_url( $attachment_id ), 'info', 1 );
		}

		// analyze the fields.
		$url_list = array(
			'attachment_id' => $attachment_id,
			'format'        => $result['format'],
			'fields'        => $this->analyze_fields( $result['fields'], $result['records'] ),
			'records'       => $result['records'],
			'limit_reached' => $result['limit_reached'],
		);

		// bail if list does not contain any URL.
		if ( empty( $this->get_url_fields( $url_list ) ) ) {
			return new WP_Error( 'efml_service_' . $this->get_name(), __( 'The file does not contain any URL, which could be imported.', 'external-files-in-media-library' ) );
		}

		// save in cache.
		$this->lists[ $attachment_id ] = $url_list;

		// return the list.
		return $url_list;
	}

	/**
	 * Analyze the fields of the list: count their values, URLs and URLs of files with allowed type.
	 *
	 * @param array<string,string>                       $field_labels List of fields with their labels.
	 * @param array<int,array<string,array<int,string>>> $records List of records.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function analyze_fields( array $field_labels, array $records ): array {
		// prepare the list of fields.
		$fields = array();
		foreach ( $field_labels as $key => $label ) {
			$fields[ (string) $key ] = array(
				'key'     => (string) $key,
				'label'   => $label,
				'values'  => 0,
				'urls'    => 0,
				'files'   => 0,
				'samples' => array(),
			);
		}

		// count the values of each field.
		foreach ( $records as $record ) {
			foreach ( $record as $key => $values ) {
				// bail if field is unknown.
				if ( ! isset( $fields[ $key ] ) ) {
					continue;
				}

				foreach ( $values as $value ) {
					++$fields[ $key ]['values'];

					// add as sample, if it is not already in the list.
					if ( count( $fields[ $key ]['samples'] ) < 3 && ! in_array( $value, $fields[ $key ]['samples'], true ) ) {
						$fields[ $key ]['samples'][] = $value;
					}

					// bail if this is not a URL.
					if ( ! $this->is_url( $value ) ) {
						continue;
					}

					// count it as URL.
					++$fields[ $key ]['urls'];

					// count it as file, if the file extension matches an allowed type.
					if ( $this->is_file_url( $value ) ) {
						++$fields[ $key ]['files'];
					}
				}
			}
		}

		// return the fields with values.
		return array_filter(
			$fields,
			function ( array $field ): bool {
				return $field['values'] > 0;
			}
		);
	}

	/**
	 * Return the keys of all fields with URLs.
	 *
	 * @param array<string,mixed> $url_list The list.
	 *
	 * @return array<int,string>
	 */
	private function get_url_fields( array $url_list ): array {
		$keys = array();
		foreach ( $url_list['fields'] as $key => $field ) {
			if ( $field['urls'] > 0 ) {
				$keys[] = (string) $key;
			}
		}
		return $keys;
	}

	/**
	 * Return the keys of all fields with URLs, sorted by the mapping and the count of file URLs.
	 *
	 * @param array<string,mixed>                $url_list The list.
	 * @param array<string,string|array<string>> $mapping The mapping.
	 *
	 * @return array<int,string>
	 */
	private function get_sorted_url_fields( array $url_list, array $mapping ): array {
		// get the fields with URLs.
		$keys = $this->get_url_fields( $url_list );

		// sort them: fields from mapping first, then by the count of file URLs.
		$mapped = (array) $mapping['url'];
		usort(
			$keys,
			function ( string $a, string $b ) use ( $url_list, $mapped ): int {
				$a_mapped = in_array( $a, $mapped, true );
				$b_mapped = in_array( $b, $mapped, true );
				if ( $a_mapped !== $b_mapped ) {
					return $a_mapped ? -1 : 1;
				}
				return $url_list['fields'][ $b ]['files'] <=> $url_list['fields'][ $a ]['files'];
			}
		);

		// return the sorted list.
		return $keys;
	}

	/**
	 * Return the mapping of the fields for the given list.
	 *
	 * The mapping defines which fields contain the URLs to import and which fields are used for alternative text,
	 * caption, description and date of the imported files. It is used from (in this order):
	 * - the import dialog (and saved for the next imports)
	 * - the external source, if it is a synchronization
	 * - the list in the media library
	 * - the automatic detection
	 *
	 * @param array<string,mixed> $url_list The list.
	 * @param bool                $use_request Whether to use the mapping from the request of the import dialog.
	 *
	 * @return array<string,string|array<string>>
	 */
	public function get_mapping( array $url_list, bool $use_request = true ): array {
		// get the mapping from the request.
		$mapping = $use_request ? $this->get_mapping_from_request( $url_list ) : false;

		// save the mapping from the request.
		if ( is_array( $mapping ) ) {
			update_post_meta( absint( $url_list['attachment_id'] ), self::META_MAPPING, $mapping );
			if ( Import::get_instance()->get_term_id() > 0 ) {
				update_term_meta( Import::get_instance()->get_term_id(), self::META_MAPPING, $mapping );
			}
			return $mapping;
		}

		// get the mapping from the external source.
		if ( Import::get_instance()->get_term_id() > 0 ) {
			$mapping = $this->validate_mapping( get_term_meta( Import::get_instance()->get_term_id(), self::META_MAPPING, true ), $url_list );
			if ( is_array( $mapping ) ) {
				return $mapping;
			}
		}

		// get the mapping from the list.
		$mapping = $this->validate_mapping( get_post_meta( absint( $url_list['attachment_id'] ), self::META_MAPPING, true ), $url_list );
		if ( is_array( $mapping ) ) {
			return $mapping;
		}

		// return the automatic detected mapping.
		return $this->get_automatic_mapping( $url_list );
	}

	/**
	 * Save the mapping from the import dialog before the import starts.
	 *
	 * This is necessary if the import is not run directly, e.g. if the URLs are added to the queue.
	 *
	 * @param bool                $result Whether the import should be prevented.
	 * @param string              $url The address.
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return bool
	 */
	public function save_mapping_from_request( bool $result, string $url, array $fields ): bool {
		// bail if no mapping has been sent.
		// the nonce is checked by the AJAX handler of the import (@Forms::add_urls_by_ajax()).
		if ( ! isset( $_POST['efml_url_list_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $result;
		}

		// bail if this is not an address of a list of this service.
		if ( false === $this->parse_address( $url, $fields ) ) {
			return $result;
		}

		// get the list.
		$url_list = $this->get_list( $fields );

		// save the mapping from the request.
		if ( ! $url_list instanceof WP_Error ) {
			$this->get_mapping( $url_list );
		}

		// return the unchanged result.
		return $result;
	}

	/**
	 * Return the mapping from the request of the import dialog.
	 *
	 * @param array<string,mixed> $url_list The list.
	 *
	 * @return array<string,string|array<string>>|false
	 */
	private function get_mapping_from_request( array $url_list ): array|false {
		// bail if the mapping has not been sent.
		// the nonce is checked by the AJAX handler of the import (@Forms::add_urls_by_ajax()).
		if ( ! isset( $_POST['efml_url_list_role'] ) || ! is_array( $_POST['efml_url_list_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}

		// get the roles from the request.
		$roles = map_deep( wp_unslash( $_POST['efml_url_list_role'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// convert to mapping.
		$mapping = array();
		foreach ( (array) $roles as $key => $role ) {
			// get the field key.
			$key = rawurldecode( (string) $key );

			// bail if role is unknown.
			if ( ! is_string( $role ) || ! in_array( $role, self::ROLES, true ) ) {
				continue;
			}

			// add URL fields to the list.
			if ( 'url' === $role ) {
				$mapping['url'][] = $key;
				continue;
			}

			// use the first field for each other role.
			if ( empty( $mapping[ $role ] ) ) {
				$mapping[ $role ] = $key;
			}
		}

		// keep the saved URL fields, if the dialog has been used for a single field or URL.
		// the URL fields of the mapping are only changeable for imports of the complete list.
		$scope = isset( $_POST['efml_url_list_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['efml_url_list_scope'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'all' !== $scope ) {
			$mapping['url'] = $this->get_mapping( $url_list, false )['url'];
		}

		// return the validated mapping.
		return $this->validate_mapping( $mapping, $url_list );
	}

	/**
	 * Validate the given mapping against the fields of the list.
	 *
	 * @param mixed               $mapping The mapping.
	 * @param array<string,mixed> $url_list The list.
	 *
	 * @return array<string,string|array<string>>|false False if the mapping is not usable.
	 */
	private function validate_mapping( mixed $mapping, array $url_list ): array|false {
		// bail if mapping is not an array.
		if ( ! is_array( $mapping ) ) {
			return false;
		}

		// get the fields with URLs.
		$url_fields = $this->get_url_fields( $url_list );

		// create the validated mapping.
		$validated = array(
			'url' => array_values( array_intersect( array_map( 'strval', (array) ( $mapping['url'] ?? array() ) ), $url_fields ) ),
		);
		foreach ( self::ROLES as $role ) {
			if ( 'url' === $role ) {
				continue;
			}
			$validated[ $role ] = isset( $mapping[ $role ] ) && is_string( $mapping[ $role ] ) && isset( $url_list['fields'][ $mapping[ $role ] ] ) ? $mapping[ $role ] : '';
		}

		// bail if no URL field is set.
		if ( empty( $validated['url'] ) ) {
			return false;
		}

		// return the validated mapping.
		return $validated;
	}

	/**
	 * Return the automatic detected mapping for the given list.
	 *
	 * Fields where at least half of the values are URLs of files with an allowed type are used as URL fields.
	 * If no such field exists, fields with URLs only are used. Other roles are detected by the name of the fields.
	 *
	 * @param array<string,mixed> $url_list The list.
	 *
	 * @return array<string,string|array<string>>
	 */
	public function get_automatic_mapping( array $url_list ): array {
		// get the fields with files.
		$url_fields = array();
		foreach ( $url_list['fields'] as $key => $field ) {
			if ( $field['files'] > 0 && $field['files'] * 2 >= $field['values'] ) {
				$url_fields[] = (string) $key;
			}
		}

		// use the fields with URLs only, if no field with files exist.
		if ( empty( $url_fields ) ) {
			foreach ( $url_list['fields'] as $key => $field ) {
				if ( $field['urls'] > 0 && $field['urls'] * 10 >= $field['values'] * 8 ) {
					$url_fields[] = (string) $key;
				}
			}
		}

		// create the mapping.
		$mapping = array(
			'url' => $url_fields,
		);

		// detect the other roles by the names of the fields.
		foreach ( $this->get_role_names() as $role => $names ) {
			$mapping[ $role ] = $this->get_field_by_names( $url_list, $names );
		}

		// return the mapping.
		return $mapping;
	}

	/**
	 * Return the possible names of fields for each role.
	 *
	 * The names are compared with the complete key of the field and its last part (without namespace prefix).
	 *
	 * @return array<string,array<int,string>>
	 */
	private function get_role_names(): array {
		$names = array(
			'alt'         => array( '_wp_attachment_image_alt', 'alt', 'alt_text', 'alt-text', 'alttext', 'alternative_text', 'alternativtext', 'image_alt' ),
			'caption'     => array( 'excerpt:encoded', 'caption', 'image:caption', 'bildunterschrift', 'beschriftung', 'post_excerpt' ),
			'description' => array( 'content:encoded', 'description', 'beschreibung', 'desc', 'post_content' ),
			'date'        => array( 'wp:post_date', 'date', 'datum', 'pubdate', 'lastmod', 'published', 'updated', 'post_date', 'created', 'modified' ),
		);

		$instance = $this;
		/**
		 * Filter the possible names of fields for each role during the automatic detection of the mapping for lists.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param array<string,array<int,string>> $names List of names per role.
		 * @param Url_List_Base $instance The service object.
		 */
		return apply_filters( 'efml_url_list_role_names', $names, $instance );
	}

	/**
	 * Return the key of the first field, which matches one of the given names and does not contain URLs.
	 *
	 * @param array<string,mixed> $url_list The list.
	 * @param array<int,string>   $names The names in the order of their priority.
	 *
	 * @return string
	 */
	private function get_field_by_names( array $url_list, array $names ): string {
		foreach ( $names as $name ) {
			foreach ( $url_list['fields'] as $key => $field ) {
				// bail if this field contains mostly URLs.
				if ( $field['urls'] * 2 >= $field['values'] ) {
					continue;
				}

				// get the complete key and its last part without namespace prefix and attribute marker.
				$full = strtolower( (string) $key );
				$last = ltrim( (string) preg_replace( '/^.*[\/:]/', '', $full ), '@' );

				// return this field if one matches.
				if ( $full === $name || $last === $name || str_ends_with( $full, '/' . $name ) ) {
					return (string) $key;
				}
			}
		}

		// return empty string if no field matches.
		return '';
	}

	/**
	 * Return the title for the given list.
	 *
	 * @param array<string,mixed> $url_list The list.
	 *
	 * @return string
	 */
	private function get_list_title( array $url_list ): string {
		return wp_basename( (string) get_attached_file( absint( $url_list['attachment_id'] ) ) );
	}

	/**
	 * Return whether the given value is a URL, which could be used for import.
	 *
	 * @param string $value The value.
	 *
	 * @return bool
	 */
	public function is_url( string $value ): bool {
		return $this->get_parser()->is_url( $value );
	}

	/**
	 * Return whether the given URL has a file extension of an allowed file type.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	private function is_file_url( string $url ): bool {
		// get the mime type.
		$mime_type = $this->get_mime_type_by_url( $url );

		// bail if no mime type could be detected.
		if ( '' === $mime_type ) {
			return false;
		}

		// get the allowed mime types.
		$allowed_mime_types = Helper::get_allowed_mime_types();

		// return whether the mime type is allowed (all are allowed if no setting exist).
		return empty( $allowed_mime_types ) || in_array( $mime_type, $allowed_mime_types, true );
	}

	/**
	 * Return the mime type of the given URL by its file extension.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	private function get_mime_type_by_url( string $url ): string {
		// get the file name.
		$filename = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		// bail if file name does not have an extension.
		if ( ! str_contains( $filename, '.' ) ) {
			return '';
		}

		// get the mime type from all known mime types.
		$type = wp_check_filetype( $filename, wp_get_mime_types() );

		// return the type.
		return is_string( $type['type'] ) ? $type['type'] : '';
	}


	/**
	 * Return the list of files to import for the given address.
	 *
	 * This is used by our protocol during the import and synchronization. During imports via the dialog, the files
	 * are loaded in parts to prevent timeouts.
	 *
	 * @param string              $url The address.
	 * @param array<string,mixed> $fields The fields.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_file_infos( string $url, array $fields ): array {
		// get the address.
		$address = $this->parse_address( $url, $fields );

		// bail if address is not valid.
		if ( false === $address ) {
			return array();
		}

		// get the list.
		$url_list = $this->get_list( $fields );

		// bail on any error.
		if ( $url_list instanceof WP_Error ) {
			// log this event.
			Log::get_instance()->create( $url_list->get_error_message(), $url, 'error', 0, Import::get_instance()->get_identifier() );

			// return an empty list.
			return array();
		}

		// get the entries to import.
		$entries = $this->get_entries( $url_list, $address );

		// mark this address as actual import via this service.
		$this->active_imports[ $url ] = true;

		// get the host of the list for the credentials.
		$list_host = strtolower( (string) wp_parse_url( $this->get_field_value( $fields, 'list_url' ), PHP_URL_HOST ) );

		// get the credentials.
		$login    = $this->get_field_value( $fields, 'login' );
		$password = $this->get_field_value( $fields, 'password' );

		// load the files in parts during imports via dialog (not during synchronization).
		$in_parts = defined( 'EFML_URL_IMPORT_RUNNING' ) && ! $this->is_sync_running();
		$limit    = max( 1, absint( get_option( 'eml_' . $this->get_name() . '_import_limit', 10 ) ) );
		$progress = $in_parts ? $this->get_progress( $url ) : array();

		// get the user.
		$user_id = get_current_user_id();

		// set the max count for the progress.
		update_option( 'eml_import_url_max_' . $user_id, count( $entries ) );

		/**
		 * Run action before the files of a list are checked.
		 *
		 * @since 5.6.0 Available since 5.6.0.
		 * @param string $url The address.
		 * @param array<int,string> $urls The URLs to check.
		 */
		do_action( 'efml_url_list_import_files', $url, array_keys( $entries ) );

		// collect the files.
		$files     = array();
		$attempted = 0;
		foreach ( $entries as $file_url => $meta ) {
			// get the hash of this URL for the progress.
			$hash = md5( $file_url );

			// bail if this URL has been processed in a previous part.
			if ( isset( $progress[ $hash ] ) ) {
				continue;
			}

			// get the HTTP handler for this URL.
			$http = new Http( $file_url );

			// use the credentials of the list only for files on the same host.
			if ( '' !== $login && '' !== $password && '' !== $list_host && strtolower( (string) wp_parse_url( $file_url, PHP_URL_HOST ) ) === $list_host ) {
				$http->set_fields(
					array(
						'login'    => array( 'value' => $login ),
						'password' => array( 'value' => $password ),
					)
				);
			}

			// bail if this URL exists in the media library.
			if ( $http->check_for_duplicate( $file_url ) || $this->is_local_file( $file_url ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Specified URL already exist in your media library.', 'external-files-in-media-library' ), esc_url( $file_url ), 'info', 1, Import::get_instance()->get_identifier() );

				// mark as processed.
				$progress[ $hash ] = true;
				continue;
			}

			// bail if the limit for this part has been reached.
			if ( $in_parts && $attempted >= $limit ) {
				$files['load_more'] = true;
				break;
			}
			++$attempted;

			// mark as processed.
			$progress[ $hash ] = true;

			// update the progress.
			/* translators: %1$s will be replaced by the URL. */
			update_option( 'eml_import_title_' . $user_id, sprintf( __( 'Check URL %1$s', 'external-files-in-media-library' ), esc_html( Helper::shorten_url( $file_url ) ) ) );
			update_option( 'eml_import_url_count_' . $user_id, count( $progress ) );

			/**
			 * Run action before a single file of a list is checked.
			 *
			 * @since 5.6.0 Available since 5.6.0.
			 * @param string $file_url The URL of the file.
			 */
			do_action( 'efml_url_list_import_file_check', $file_url );

			// get the file infos.
			$file_info = $http->get_url_info( $file_url );

			// bail if no infos could be loaded.
			if ( empty( $file_info ) ) {
				$this->add_result_for_failed_url( $file_url );
				continue;
			}

			// set the date from the list, if given.
			if ( ! empty( $meta['date'] ) ) {
				$timestamp = strtotime( $meta['date'] );
				if ( $timestamp ) {
					$file_info['last-modified'] = $timestamp;
				}
			}

			// add the data from the list.
			$file_info['efml_url_list'] = array(
				'service' => $this->get_name(),
				'list'    => absint( $url_list['attachment_id'] ),
				'meta'    => $meta,
			);

			/**
			 * Run action just before the file is added to the list of files to import.
			 *
			 * @since 5.6.0 Available since 5.6.0.
			 * @param string $file_url The URL of the file.
			 * @param array<int,string> $urls The URLs of the list.
			 */
			do_action( 'efml_url_list_import_file_before_to_list', $file_url, array_keys( $entries ) );

			// add the file.
			$files[] = $file_info;
		}

		// save the progress for the next part or remove it.
		if ( $in_parts ) {
			if ( isset( $files['load_more'] ) ) {
				$this->set_progress( $url, $progress );
			} else {
				$this->delete_progress( $url );
			}
		}

		// return the list of files.
		return $files;
	}

	/**
	 * Return the entries to import from the list for the given address.
	 *
	 * @param array<string,mixed>                 $url_list The list.
	 * @param array{scope: string, value: string} $address The address.
	 *
	 * @return array<string,array<string,string>> List of URLs with their meta data.
	 */
	private function get_entries( array $url_list, array $address ): array {
		// get the mapping.
		$mapping = $this->get_mapping( $url_list );

		// get the fields with URLs to use.
		$url_fields = (array) $mapping['url'];
		if ( 'field' === $address['scope'] ) {
			$url_fields = array( $address['value'] );
		}
		if ( 'url' === $address['scope'] ) {
			$url_fields = $this->get_url_fields( $url_list );
		}

		// collect the entries.
		$entries = array();
		foreach ( $url_list['records'] as $record ) {
			foreach ( $url_fields as $key ) {
				foreach ( $record[ $key ] ?? array() as $url ) {
					// bail if this is not a URL or already in the list.
					if ( ! $this->is_url( $url ) || isset( $entries[ $url ] ) ) {
						continue;
					}

					// bail if this is not the requested single URL.
					if ( 'url' === $address['scope'] && $url !== $address['value'] ) {
						continue;
					}

					// collect the meta data of this record.
					$meta = array();
					foreach ( self::ROLES as $role ) {
						// bail for URL fields and roles without field.
						if ( 'url' === $role || empty( $mapping[ $role ] ) || ! is_string( $mapping[ $role ] ) ) {
							continue;
						}

						// add the first value of the field.
						if ( ! empty( $record[ $mapping[ $role ] ][0] ) ) {
							$meta[ $role ] = $record[ $mapping[ $role ] ][0];
						}
					}

					// add the entry.
					$entries[ $url ] = $meta;
				}
			}
		}

		// return the entries.
		return $entries;
	}

	/**
	 * Return whether the given URL is a file of the local media library.
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	private function is_local_file( string $url ): bool {
		// bail if no attachment uses this URL.
		$attachment_id = attachment_url_to_postid( $url );
		if ( 0 === $attachment_id ) {
			return false;
		}

		// external files of this plugin are no local files.
		return empty( get_post_meta( $attachment_id, EFML_POST_META_URL, true ) );
	}

	/**
	 * Add the log entries of a URL, which could not be imported, to the results of the import.
	 *
	 * @param string $url The URL.
	 *
	 * @return void
	 */
	private function add_result_for_failed_url( string $url ): void {
		// get the log entries of this URL.
		$log_entries = Log::get_instance()->get_logs( $url, 'error', Import::get_instance()->get_identifier() );

		// use a general text, if no log entry exist.
		if ( empty( $log_entries ) ) {
			$log_entries = array( array( 'log' => __( 'The URL could not be imported.', 'external-files-in-media-library' ) ) );
		}

		// add each entry as result.
		foreach ( $log_entries as $log_entry ) {
			$error_obj = new Url_Result();
			$error_obj->set_result_text( (string) $log_entry['log'] );
			$error_obj->set_url( $url );
			$error_obj->set_error( true );
			Results::get_instance()->add( $error_obj );
		}
	}

	/**
	 * Return the processed URLs of an import in parts.
	 *
	 * The progress is reset if the last part is older than 2 minutes (e.g., after an aborted import).
	 *
	 * @param string $url The address.
	 *
	 * @return array<string,bool>
	 */
	private function get_progress( string $url ): array {
		// get the progress.
		$progress = get_transient( $this->get_progress_key( $url ) );

		// bail if no usable progress exist.
		if ( ! is_array( $progress ) || empty( $progress['time'] ) || absint( $progress['time'] ) < time() - 2 * MINUTE_IN_SECONDS || ! is_array( $progress['done'] ) ) {
			return array();
		}

		// return the list of processed URLs.
		return $progress['done'];
	}

	/**
	 * Save the processed URLs of an import in parts.
	 *
	 * @param string             $url The address.
	 * @param array<string,bool> $done The processed URLs.
	 *
	 * @return void
	 */
	private function set_progress( string $url, array $done ): void {
		set_transient(
			$this->get_progress_key( $url ),
			array(
				'time' => time(),
				'done' => $done,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Delete the progress of an import in parts.
	 *
	 * @param string $url The address.
	 *
	 * @return void
	 */
	private function delete_progress( string $url ): void {
		delete_transient( $this->get_progress_key( $url ) );
	}

	/**
	 * Return the key for the progress of an import in parts.
	 *
	 * @param string $url The address.
	 *
	 * @return string
	 */
	private function get_progress_key( string $url ): string {
		return 'efml_url_list_' . get_current_user_id() . '_' . md5( $url );
	}

	/**
	 * Return whether a synchronization is running.
	 *
	 * @return bool
	 */
	private function is_sync_running(): bool {
		return defined( 'EFML_SYNC_RUNNING' ) || Import::get_instance()->get_term_id() > 0;
	}

	/**
	 * Add the counter of the synchronization for the files of lists.
	 *
	 * @return void
	 */
	public function add_sync_hooks(): void {
		// bail if hooks are already added (by the other list service).
		if ( has_action( 'efml_url_list_import_files', array( Synchronization::get_instance(), 'set_url_max_count' ) ) ) {
			return;
		}

		// add the hooks.
		add_action( 'efml_url_list_import_files', array( Synchronization::get_instance(), 'set_url_max_count' ), 10, 2 );
		add_action( 'efml_url_list_import_file_check', array( Synchronization::get_instance(), 'update_url_count' ), 10, 0 );
	}

	/**
	 * Remove the fields (with the credentials of the list) from the import object before the files of a list are saved.
	 *
	 * Otherwise, the credentials of the list would be saved on each imported file and used for their requests,
	 * also on other hosts.
	 *
	 * @param string $url The address.
	 *
	 * @return void
	 */
	public function remove_import_fields( string $url ): void {
		// bail if this is not an import via this service.
		if ( ! isset( $this->active_imports[ $url ] ) ) {
			return;
		}

		// save the fields and remove them.
		$this->removed_import_fields[ $url ] = Import::get_instance()->get_fields();
		Import::get_instance()->set_fields( array() );
	}

	/**
	 * Restore the fields of the import object after the import of a list.
	 *
	 * @param string $url The address.
	 *
	 * @return void
	 */
	public function restore_import_fields( string $url ): void {
		// bail if no fields have been removed for this address.
		if ( ! isset( $this->removed_import_fields[ $url ] ) ) {
			return;
		}

		// restore the fields.
		Import::get_instance()->set_fields( $this->removed_import_fields[ $url ] );
		unset( $this->removed_import_fields[ $url ], $this->active_imports[ $url ] );
	}

	/**
	 * Set the data from the list on the imported file: service, alternative text, caption and description.
	 *
	 * The title is not changed, as it must match the file name for the proxy of external files.
	 *
	 * @param File                $external_file_obj The external file object.
	 * @param array<string,mixed> $file_data The file data.
	 *
	 * @return void
	 */
	public function set_file_data_after_saving( File $external_file_obj, array $file_data ): void {
		// bail if this file is not from a list of this service.
		if ( empty( $file_data['efml_url_list'] ) || ! is_array( $file_data['efml_url_list'] ) || $this->get_name() !== ( $file_data['efml_url_list']['service'] ?? '' ) ) {
			return;
		}

		// get the attachment ID.
		$attachment_id = $external_file_obj->get_id();

		// use the protocol of the file as service (e.g. HTTP, as the file is hosted there and not in the list)
		// and remove any credentials, if this is an external file.
		if ( $external_file_obj->is_valid() ) {
			$protocol_obj = Protocols::get_instance()->get_protocol_object_for_url( $external_file_obj->get_url( true ) );
			$external_file_obj->set_service_name( $protocol_obj ? $protocol_obj->get_name() : 'http' );
			$external_file_obj->set_fields( array() );
		}

		// save the list this file is imported from.
		update_post_meta( $attachment_id, self::META_IMPORTED_FROM, absint( $file_data['efml_url_list']['list'] ?? 0 ) );

		// get the meta data from the list.
		$meta = isset( $file_data['efml_url_list']['meta'] ) && is_array( $file_data['efml_url_list']['meta'] ) ? $file_data['efml_url_list']['meta'] : array();

		// set caption and description.
		$post_data = array();
		if ( ! empty( $meta['caption'] ) ) {
			$post_data['post_excerpt'] = wp_kses_post( $meta['caption'] );
		}
		if ( ! empty( $meta['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $meta['description'] );
		}
		if ( ! empty( $post_data ) ) {
			$post_data['ID'] = $attachment_id;
			wp_update_post( $post_data );
		}

		// set the alternative text.
		if ( ! empty( $meta['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $meta['alt'] ) );
		}
	}

	/**
	 * Add the mapping of the fields in the import dialog for lists of this service.
	 *
	 * @param array<string,mixed> $dialog The dialog.
	 * @param array<string,mixed> $settings The settings for the dialog.
	 *
	 * @return array<string,mixed>
	 */
	public function add_mapping_in_dialog( array $dialog, array $settings ): array {
		// bail if this is not our service.
		if ( ! isset( $settings['service'] ) || $this->get_name() !== $settings['service'] ) {
			return $dialog;
		}

		// get the fields from the settings or from the external source.
		$fields = isset( $settings['fields'] ) && is_array( $settings['fields'] ) ? $settings['fields'] : array();
		if ( ! empty( $settings['term'] ) ) {
			$term_data = \easyDirectoryListingForWordPress\Taxonomy::get_instance()->get_entry( absint( $settings['term'] ) );
			if ( ! empty( $term_data['fields'] ) && is_array( $term_data['fields'] ) ) {
				$fields = $term_data['fields'];
			}
		}

		// get the address.
		$address = $this->parse_address( isset( $settings['urls'] ) && is_string( $settings['urls'] ) ? html_entity_decode( $settings['urls'] ) : '', $fields );

		// bail if this is not an address of a list or a single URL of it (which uses the normal dialog).
		if ( false === $address || 'url' === $address['scope'] ) {
			return $dialog;
		}

		// get the list.
		$url_list = $this->get_list( $fields );

		// show error, if list could not be loaded.
		if ( $url_list instanceof WP_Error ) {
			$dialog['texts'][] = '<p class="efml-url-list-error"><strong>' . esc_html( $url_list->get_error_message() ) . '</strong></p>';
			return $dialog;
		}

		// get the mapping.
		$mapping = $this->get_mapping( $url_list, false );

		// set the title.
		/* translators: %1$s will be replaced by the label of the format (e.g. CSV). */
		$dialog['title'] = sprintf( __( 'Import URLs from %1$s file', 'external-files-in-media-library' ), $this->get_label() );

		// get the labels of the roles.
		$roles = array(
			''            => __( '— not used —', 'external-files-in-media-library' ),
			'url'         => __( 'URL of a file to import', 'external-files-in-media-library' ),
			'alt'         => __( 'Alternative text', 'external-files-in-media-library' ),
			'caption'     => __( 'Caption', 'external-files-in-media-library' ),
			'description' => __( 'Description', 'external-files-in-media-library' ),
			'date'        => __( 'Date', 'external-files-in-media-library' ),
		);

		// get the URL field of a single field address.
		$fixed_url_field = 'field' === $address['scope'] ? $address['value'] : '';

		// create the table with the fields.
		$html  = '<div class="efml-url-list-mapping" data-scope="' . esc_attr( $address['scope'] ) . '">';
		$html .= '<input type="hidden" name="efml_url_list_scope" value="' . esc_attr( $address['scope'] ) . '">';
		$html .= '<p><strong>' . esc_html( $this->get_list_title( $url_list ) ) . '</strong></p>';
		if ( 'all' === $address['scope'] ) {
			$html .= '<p>' . esc_html__( 'Choose which fields contain the URLs of the files to import and which fields should be used for the data of the files.', 'external-files-in-media-library' ) . '</p>';
		} else {
			$html .= '<p>' . esc_html__( 'Choose which fields should be used for the data of the files.', 'external-files-in-media-library' ) . '</p>';
		}
		$html .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Field', 'external-files-in-media-library' ) . '</th><th>' . esc_html__( 'Example', 'external-files-in-media-library' ) . '</th><th>' . esc_html__( 'Use as', 'external-files-in-media-library' ) . '</th></tr></thead><tbody>';
		foreach ( $url_list['fields'] as $key => $field ) {
			// get the actual role of this field.
			$role = in_array( $key, (array) $mapping['url'], true ) ? 'url' : '';
			foreach ( $roles as $role_name => $label ) {
				if ( '' !== $role_name && 'url' !== $role_name && isset( $mapping[ $role_name ] ) && $mapping[ $role_name ] === $key ) {
					$role = $role_name;
				}
			}

			// the field of a single field address is always the URL field.
			if ( '' !== $fixed_url_field ) {
				$role = $fixed_url_field === $key ? 'url' : ( 'url' === $role ? '' : $role );
			}

			// get the example.
			$example = (string) ( $field['samples'][0] ?? '' );
			if ( mb_strlen( $example ) > 60 ) {
				$example = mb_substr( $example, 0, 57 ) . '…';
			}

			// create the select field.
			$select = '<select name="efml_url_list_role[' . esc_attr( rawurlencode( (string) $key ) ) . ']" data-urls="' . absint( $field['urls'] ) . '"' . ( '' !== $fixed_url_field && ( $fixed_url_field === $key || 'url' === $role ) ? ' disabled="disabled"' : '' ) . '>';
			foreach ( $roles as $role_name => $label ) {
				// URLs could only be used from fields with URLs.
				if ( 'url' === $role_name && 0 === $field['urls'] ) {
					continue;
				}
				$select .= '<option value="' . esc_attr( $role_name ) . '"' . selected( $role, $role_name, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$select .= '</select>';

			// keep the URL field of a single field address in the request.
			if ( '' !== $fixed_url_field && $fixed_url_field === $key ) {
				$select .= '<input type="hidden" name="efml_url_list_role[' . esc_attr( rawurlencode( (string) $key ) ) . ']" value="url">';
			}

			// add the row.
			$html .= '<tr><td>' . esc_html( (string) $field['label'] ) . '</td><td><code>' . esc_html( $example ) . '</code></td><td>' . $select . '</td></tr>';
		}
		$html .= '</tbody></table>';

		// show the count of URLs.
		$count = 0;
		foreach ( '' !== $fixed_url_field ? array( $fixed_url_field ) : (array) $mapping['url'] as $key ) {
			$count += absint( $url_list['fields'][ $key ]['urls'] ?? 0 );
		}
		/* translators: %1$s will be replaced by a number. */
		$html .= '<p class="efml-url-list-count" data-text="' . esc_attr__( 'Up to %1$s URLs will be imported.', 'external-files-in-media-library' ) . '">' . esc_html( sprintf( __( 'Up to %1$s URLs will be imported.', 'external-files-in-media-library' ), number_format_i18n( $count ) ) ) . '</p>';

		// show hint for reached limit.
		if ( ! empty( $url_list['limit_reached'] ) ) {
			$html .= '<p><strong>' . esc_html__( 'The list contains more entries than can be read. Only the first entries are used.', 'external-files-in-media-library' ) . '</strong></p>';
		}
		$html .= '</div>';

		// add the table to the dialog.
		$dialog['texts'][] = $html;

		// return the resulting dialog.
		return $dialog;
	}

	/**
	 * Return the settings for the import dialog of the given list in the media library.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return array<string,mixed>
	 */
	private function get_dialog_settings_for_attachment( int $attachment_id ): array {
		return array(
			'service' => $this->get_name(),
			'urls'    => (string) wp_get_attachment_url( $attachment_id ),
			'fields'  => array(
				'list_type' => array(
					'value' => $this->get_name(),
				),
				'list_file' => array(
					'value' => (string) $attachment_id,
				),
			),
		);
	}

	/**
	 * Return whether the import option should be shown for the given attachment in the media library.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool
	 */
	private function is_media_library_option_visible( int $attachment_id ): bool {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_' . $this->get_name() . '_media_library', 1 ) ) ) {
			return false;
		}

		// bail if user has not the capability for this service or for this file.
		if ( ! current_user_can( $this->get_permission_name() ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return false;
		}

		// return whether the file is a list of this service.
		return $this->is_list_attachment( $attachment_id );
	}

	/**
	 * Return the link to import the URLs of the given list.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	private function get_import_link( int $attachment_id ): string {
		return '<a href="#" class="efml-url-list-import" data-settings="' . esc_attr( Helper::get_json( $this->get_dialog_settings_for_attachment( $attachment_id ) ) ) . '">' . esc_html__( 'Import URLs', 'external-files-in-media-library' ) . '</a>';
	}

	/**
	 * Add the option to import the URLs of a list in the media library (list view).
	 *
	 * @param array<string,string> $actions List of actions.
	 * @param WP_Post              $post The attachment.
	 *
	 * @return array<string,string>
	 */
	public function add_media_row_action( array $actions, WP_Post $post ): array {
		// bail if option should not be visible.
		if ( ! $this->is_media_library_option_visible( $post->ID ) ) {
			return $actions;
		}

		// add the action.
		$actions[ 'efml-url-list-import-' . $this->get_name() ] = $this->get_import_link( $post->ID );

		// return the resulting list of actions.
		return $actions;
	}

	/**
	 * Add the option to import the URLs of a list in the details of the file (grid view and edit page).
	 *
	 * @param array<string,array<string,mixed>> $form_fields List of fields.
	 * @param WP_Post                           $post The attachment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function add_attachment_field( array $form_fields, WP_Post $post ): array {
		// bail if option should not be visible.
		if ( ! $this->is_media_library_option_visible( $post->ID ) ) {
			return $form_fields;
		}

		// add the field.
		$form_fields[ 'efml_url_list_' . $this->get_name() ] = array(
			'label'         => __( 'External files', 'external-files-in-media-library' ),
			'input'         => 'html',
			'html'          => '<span class="dashicons dashicons-database-import"></span> ' . $this->get_import_link( $post->ID ),
			'show_in_edit'  => true,
			'show_in_modal' => true,
		);

		// return the resulting list of fields.
		return $form_fields;
	}

	/**
	 * Add the mime types of this format to the list of allowed mime types for uploads.
	 *
	 * @param array<string,string> $mime_types List of mime types.
	 *
	 * @return array<string,string>
	 */
	public function add_mime_types( array $mime_types ): array {
		// add each extension, if it is not allowed already.
		foreach ( $this->get_extensions() as $extension ) {
			// bail if extension is already allowed.
			foreach ( array_keys( $mime_types ) as $extensions ) {
				if ( in_array( $extension, explode( '|', (string) $extensions ), true ) ) {
					continue 2;
				}
			}

			// add the extension with the first mime type of this format.
			$mime_types[ $extension ] = $this->get_list_mime_types()[0];
		}

		// return the resulting list.
		return $mime_types;
	}

	/**
	 * Allow the upload of files of this format for users with the capability for this service.
	 *
	 * @param array<string,string> $mime_types List of mime types.
	 *
	 * @return array<string,string>
	 */
	public function allow_upload( array $mime_types ): array {
		// bail if user has not the capability for this service.
		if ( ! current_user_can( $this->get_permission_name() ) ) {
			return $mime_types;
		}

		// return the list with the mime types of this format.
		return $this->add_mime_types( $mime_types );
	}

	/**
	 * Allow the real mime types of this format during the check of uploaded files.
	 *
	 * The real mime type detected by PHP could differ from the mime type of the file extension
	 * (e.g. "application/xml" instead of "text/xml").
	 *
	 * @param array<string,mixed> $data The data of the file.
	 * @param string              $file The path to the file.
	 * @param string              $filename The name of the file.
	 *
	 * @return array<string,mixed>
	 */
	public function check_upload( array $data, string $file, string $filename ): array {
		// bail if the file is already allowed.
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		// bail if the file extension does not match this format.
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return $data;
		}

		// bail if the extension is not allowed for uploads in this request.
		$allowed = false;
		foreach ( array_keys( get_allowed_mime_types() ) as $extensions ) {
			if ( in_array( $extension, explode( '|', (string) $extensions ), true ) ) {
				$allowed = true;
			}
		}
		if ( ! $allowed ) {
			return $data;
		}

		// bail if the real mime type is not one of this format or plain text.
		$real_mime = function_exists( 'finfo_open' ) && is_readable( $file ) ? (string) mime_content_type( $file ) : '';
		if ( ! in_array( $real_mime, array_merge( $this->get_list_mime_types(), array( 'text/plain' ) ), true ) ) {
			return $data;
		}

		// allow this file.
		$data['ext']  = $extension;
		$data['type'] = $this->get_list_mime_types()[0];

		// return the resulting data.
		return $data;
	}
}
