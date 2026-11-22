<?php
/**
 * File to handle the multisite support as directory listing.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\Services;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use easyDirectoryListingForWordPress\Taxonomy;
use ExternalFilesInMediaLibrary\ExternalFiles\Export_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use ExternalFilesInMediaLibrary\ExternalFiles\Import;
use ExternalFilesInMediaLibrary\ExternalFiles\Synchronization;
use ExternalFilesInMediaLibrary\Plugin\Helper;
use ExternalFilesInMediaLibrary\Plugin\Log;
use ExternalFilesInMediaLibrary\Services\Multisite\Export;
use WP_Post;
use WP_Query;

/**
 * Object to handle support for multisite directory listing.
 */
class Multisite extends Service_Base implements Service {
	/**
	 * The object name.
	 *
	 * @var string
	 */
	protected string $name = 'multisite';

	/**
	 * Marker whether values of fields could be defined via constants (true) or not (false).
	 *
	 * Disabled as this service does not use any credentials.
	 *
	 * @var bool
	 */
	protected bool $fields_via_constants = false;

	/**
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Multisite';

	/**
	 * Meta key on imported files: the marker of the state of the file, when it has been imported.
	 */
	public const META_STAMP = 'efml_multisite_stamp';

	/**
	 * External sources, whose files have been listed completely in this request.
	 *
	 * @var array<int,bool>
	 */
	private array $listed = array();

	/**
	 * Instance of actual object.
	 *
	 * @var ?Multisite
	 */
	private static ?Multisite $instance = null;

	/**
	 * Constructor, not used as this a Singleton object.
	 */
	private function __construct() {    }

	/**
	 * Prevent cloning of this object.
	 *
	 * @return void
	 */
	private function __clone() {    }

	/**
	 * Return instance of this object as singleton.
	 *
	 * @return Multisite
	 */
	public static function get_instance(): Multisite {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run during activation of the plugin.
	 *
	 * @return void
	 */
	public function activation(): void {}

	/**
	 * Initialize this object.
	 *
	 * @return void
	 */
	public function init(): void {
		// bail if this is not a multisite.
		if ( ! is_multisite() ) {
			return;
		}

		// use parent initialization.
		parent::init();

		// hide files with a not allowed type in the directory listing, if enabled.
		add_filter( 'efml_service_multisite_hide_file', array( $this, 'prevent_not_allowed_files' ), 10, 2 );

		// bail if user has no capability for this service.
		// scheduled synchronizations via cron and WP CLI run without user, but need the hooks for importing.
		if ( ! $this->is_import_allowed() ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from another website in your multisite', 'external-files-in-media-library' );

		// use our own hooks.
		add_filter( 'efml_protocols', array( $this, 'add_protocol' ) );
		add_filter( 'efml_filter_url_response', array( $this, 'get_multisite_files' ) );
		add_action( 'efml_after_file_save', array( $this, 'change_service_name' ) );
		add_action( 'efml_after_file_save', array( $this, 'save_stamp' ), 10, 2 );
		add_filter( 'efml_sync_listing_complete', array( $this, 'is_listing_complete' ), 10, 3 );
	}

	/**
	 * Prevent the visibility of files with a not allowed mime type, if this is enabled in the settings.
	 *
	 * @param bool   $result The result - should be true to hide the file.
	 * @param string $mime_type The mime type of the file.
	 *
	 * @return bool
	 */
	public function prevent_not_allowed_files( bool $result, string $mime_type ): bool {
		return $result || $this->is_mime_type_hidden( $mime_type );
	}

	/**
	 * Add our own protocol.
	 *
	 * @param array<string> $protocols List of protocols.
	 *
	 * @return array<string>
	 */
	public function add_protocol( array $protocols ): array {
		// add the DropBox protocol before the HTTPS-protocol and return resulting list of protocols.
		array_unshift( $protocols, 'ExternalFilesInMediaLibrary\Services\Multisite\Protocol' );

		// return the resulting list.
		return $protocols;
	}

	/**
	 * Return the directory listing structure.
	 *
	 * @param string $directory The requested directory.
	 *
	 * @return array<int|string,mixed>
	 */
	public function get_directory_listing( string $directory ): array {
		// get fields.
		$fields = $this->get_fields();

		// bail if no "website" has been chosen.
		if ( empty( $fields['website']['value'] ) ) {
			return array();
		}

		// get the blog ID.
		$blog_id = absint( $fields['website']['value'] );

		// bail if ID is unusable.
		if ( 0 === $blog_id ) {
			return array();
		}

		// bail if the actual user is not allowed to access this blog.
		if ( ! $this->is_blog_allowed( $blog_id ) ) {
			return array();
		}

		// get the actual website, which requests the listing.
		$requesting_blog_id = get_current_blog_id();

		// switch to the given blog.
		switch_to_blog( $blog_id );

		// get all media files in this blog.
		$results = $this->get_all_attachments();

		// bail on no results.
		if ( 0 === $results->found_posts ) {
			// restore the current blog.
			restore_current_blog();

			// return empty array.
			return array();
		}

		// prepare the resulting array.
		$listing = array(
			'title' => get_blogaddress_by_id( $blog_id ),
			'files' => array(),
			'dirs'  => array(),
		);

		// add each attachment to the list.
		foreach ( $results->posts as $post ) {
			// bail if result is not "WP_Post".
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$false = false;
			/**
			 * Filter whether the given file of the other website should be hidden.
			 *
			 * @since 5.6.0 Available since 5.6.0.
			 *
			 * @param bool $false True if it should be hidden.
			 * @param string $mime_type The mime type of the file.
			 * @param WP_Post $post The attachment.
			 *
			 * @noinspection PhpConditionAlreadyCheckedInspection
			 */
			if ( apply_filters( 'efml_service_multisite_hide_file', $false, $post->post_mime_type, $post ) ) {
				continue;
			}

			// bail if the requesting website could not use this file of the central media library.
			if ( ! $this->is_file_visible( $blog_id, $post->ID, $requesting_blog_id ) ) {
				continue;
			}

			// get the metadata of this file.
			$meta_data = wp_get_attachment_metadata( $post->ID );

			// get the file URL.
			$url = $this->get_file_url( $blog_id, $post->ID );

			// collect the data for this file.
			$entry = array(
				'title'         => basename( $url ),
				'file'          => $url,
				'filesize'      => is_array( $meta_data ) ? absint( $meta_data['filesize'] ?? 0 ) : 0, // @phpstan-ignore nullCoalesce.offset (older uploads do not have this entry)
				'mime-type'     => $post->post_mime_type,
				'icon'          => '<span class="dashicons dashicons-media-default" data-type="' . esc_attr( $post->post_mime_type ) . '"></span>',
				'last-modified' => $post->post_modified,
				'thumbnail'     => '', // TODO to be completed.
			);

			// add it to the list.
			$listing['files'][] = $entry;
		}

		// switch back to our own blog.
		restore_current_blog();

		// return the resulting list.
		return $listing;
	}

	/**
	 * Return the actions.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_actions(): array {
		// get list of allowed mime types.
		$mimetypes = implode( ',', Helper::get_allowed_mime_types() );

		return array(
			array(
				'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": file.file, "fields": config.fields, "term": term } );',
				'label'  => __( 'Import', 'external-files-in-media-library' ),
				'show'   => 'let mimetypes = "' . $mimetypes . '";mimetypes.includes( file["mime-type"] )',
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
					'action' => 'efml_get_import_dialog( { "service": "' . $this->get_name() . '", "urls": actualDirectoryPath, "fields": config.fields, "term": config.term } );',
					'label'  => __( 'Import active directory', 'external-files-in-media-library' ),
				),
				array(
					'action' => 'efml_save_as_directory( "' . $this->get_name() . '", actualDirectoryPath, config.fields, config.term );',
					'label'  => __( 'Save active directory as your external source', 'external-files-in-media-library' ),
				),
			)
		);
	}

	/**
	 * We do not need to check the login for Multisite.
	 *
	 * @param string $directory The directory to check.
	 *
	 * @return bool
	 */
	public function do_login( string $directory ): bool {
		return true;
	}

	/**
	 * Initialize WP CLI for this service.
	 *
	 * @return void
	 */
	public function cli(): void {}

	/**
	 * Return list of fields we need for this listing.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_fields(): array {
		// set fields, if they are empty atm.
		if ( empty( $this->fields ) ) {
			// set the fields.
			$this->fields = array( // @phpstan-ignore property.notFound
				'website' => array(
					'name'    => 'website',
					'type'    => 'select',
					'label'   => __( 'Website', 'external-files-in-media-library' ),
					'options' => $this->get_websites(),
					'value'   => '',
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return whether the actual user is allowed to access the media files of the given blog.
	 *
	 * Without this check each user with access to this service could read, import, add and delete
	 * media files of every website in the network.
	 *
	 * @param int $blog_id The blog ID to check.
	 *
	 * @return bool
	 */
	public function is_blog_allowed( int $blog_id ): bool {
		// bail if no blog ID is given.
		if ( $blog_id <= 0 ) {
			return false;
		}

		// background tasks (cron, WP CLI) do not have an interactive user we could check.
		$allowed = Helper::is_cli() || wp_doing_cron();

		// super admins are allowed to access every blog.
		if ( ! $allowed && is_super_admin() ) {
			$allowed = true;
		}

		// the main media library of the network (configured by the network administration) is usable for every user.
		if ( ! $allowed && \ExternalFilesInMediaLibrary\Plugin\Network\Settings::get_instance()->get_main_media_library_site_id() === $blog_id ) {
			$allowed = true;
		}

		// other users must be allowed to upload files in the requested blog.
		if ( ! $allowed ) {
			$allowed = is_user_member_of_blog( get_current_user_id(), $blog_id ) && function_exists( 'current_user_can_for_site' ) && current_user_can_for_site( $blog_id, 'upload_files' );
		}

		/**
		 * Filter whether the actual user is allowed to access the media files of the given blog.
		 *
		 * @since 5.5.0 Available since 5.5.0.
		 * @param bool $allowed True if the access is allowed.
		 * @param int $blog_id The requested blog ID.
		 */
		return (bool) apply_filters( 'efml_multisite_blog_allowed', $allowed, $blog_id );
	}

	/**
	 * Return list of websites in this multisite.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_websites(): array {
		// get the websites.
		$websites = Helper::get_blogs( get_current_blog_id() );

		// prepare the list.
		$list = array(
			array(
				'label' => __( 'Choose the website', 'external-files-in-media-library' ),
				'value' => '',
			),
		);

		// add the sites to the list.
		foreach ( $websites as $website ) {
			// bail if the actual user is not allowed to access this website.
			if ( ! $this->is_blog_allowed( absint( $website->blog_id ) ) ) { // @phpstan-ignore property.notFound
				continue;
			}

			// get the URL of this website.
			$url = get_blogaddress_by_id( $website->blog_id ); // @phpstan-ignore property.notFound

			// add it to the list.
			$list[] = array(
				'label' => $url,
				'value' => $website->blog_id, // @phpstan-ignore property.notFound
			);
		}

		// return the resulting list.
		return $list;
	}

	/**
	 * Return the form title.
	 *
	 * @return string
	 */
	public function get_form_title(): string {
		return __( 'Choose the website', 'external-files-in-media-library' );
	}

	/**
	 * Return the form description.
	 *
	 * @return string
	 */
	public function get_form_description(): string {
		return __( 'Select the website whose media library you want to access.', 'external-files-in-media-library' );
	}

	/**
	 * Return the URL for the listing.
	 *
	 * @return string
	 */
	public function get_directory(): string {
		// get the fields.
		$fields = $this->get_fields();

		// if website is selected, use their URL.
		if ( ! empty( $fields['website']['value'] ) ) {
			$blog_id = absint( $fields['website']['value'] );

			// bail if ID is not valid.
			if ( 0 === $blog_id ) {
				return $this->directory;
			}

			// return the blog URL.
			return get_blogaddress_by_id( $blog_id );
		}

		// otherwise return the default value.
		return $this->directory;
	}

	/**
	 * Return the list of files from given REST API-URL for import.
	 *
	 * @param array<int,array<string,mixed>> $results The result as array for file import.
	 *
	 * @return array<int|string,array<string,mixed>|bool>
	 */
	public function get_multisite_files( array $results ): array {
		// bail if the actual import is not for this service.
		if ( ! $this->is_actual_service( 'method' ) ) {
			return $results;
		}

		// get the term ID from the request or, during a synchronization, from the import object.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );
		if ( 0 === $term_id ) {
			$term_id = Import::get_instance()->get_term_id();
		}

		// bail if term ID is not given.
		if ( 0 === $term_id ) {
			return $results;
		}

		// get the term data.
		$term_data = Taxonomy::get_instance()->get_entry( $term_id );

		// bail if not term data could be loaded.
		if ( empty( $term_data ) ) {
			return $results;
		}

		// get the fields.
		$fields = $term_data['fields'];

		// bail if fields are empty.
		if ( empty( $fields ) ) {
			return $results;
		}

		// set the fields on this object.
		$this->set_fields( $fields );

		// get the blog ID from the configuration.
		$blog_id = isset( $fields['website']['value'] ) ? absint( $fields['website']['value'] ) : 0;

		// bail if no blog is set or the actual user is not allowed to access this blog.
		if ( ! $this->is_blog_allowed( $blog_id ) ) {
			return array();
		}

		// get the actual website, which imports the files.
		$requesting_blog_id = get_current_blog_id();

		// switch to the given blog.
		switch_to_blog( $blog_id );

		// get the attachments from this blog.
		$attachments = $this->get_all_attachments();

		// bail on no results.
		if ( 0 === $attachments->found_posts ) {
			// restore the current blog.
			restore_current_blog();

			// the list is complete: it is empty.
			$this->listed[ $term_id ] = true;

			// return empty array.
			return array();
		}

		// collect the files.
		$listing = array();

		// check each attachment.
		foreach ( $attachments->posts as $post ) {
			// bail if post is not "WP_Post".
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			// bail if the importing website could not use this file of the central media library.
			if ( ! $this->is_file_visible( $blog_id, $post->ID, $requesting_blog_id ) ) {
				continue;
			}

			// get the metadata of this file.
			$meta_data = wp_get_attachment_metadata( $post->ID );

			// get the file URL.
			$url = $this->get_file_url( $blog_id, $post->ID );

			// get the size of the file: from its metadata or, for older uploads without it, from the file.
			// The file is not loaded: the import saves only the reference to it (it is not saved locally).
			$file_size = is_array( $meta_data ) ? absint( $meta_data['filesize'] ?? 0 ) : 0; // @phpstan-ignore nullCoalesce.offset (older uploads do not have this entry)
			$path      = (string) get_attached_file( $post->ID );
			if ( 0 === $file_size && is_file( $path ) ) {
				$file_size = absint( filesize( $path ) );
			}

			// collect the data for this file.
			$entry = array(
				// the file name is the title, as the proxy uses it in the URL of the file (like for the other services).
				'title'         => basename( $url ),
				'local'         => false,
				'url'           => $url,
				'last-modified' => $post->post_modified,
				'filesize'      => $file_size,
				'mime-type'     => $post->post_mime_type,
				'tmp-file'      => '',
				// marker of the state of the file: the next synchronization skips the file, if it is unchanged.
				'efml-stamp'    => md5( $url . '|' . $post->post_modified_gmt . '|' . $file_size ),
			);

			// add the ID and the values of a file of the central media library, so the website uses them.
			if ( \ExternalFilesInMediaLibrary\Plugin\Network\Media_Library::get_instance()->is_central( $blog_id ) ) {
				$entry['efml-central-id']     = $post->ID;
				$entry['efml-central-values'] = \ExternalFilesInMediaLibrary\Plugin\Network\Metadata::get_instance()->get_values( $post->ID );
				$entry['efml-central-hash']   = (string) get_post_meta( $post->ID, \ExternalFilesInMediaLibrary\Plugin\Network\Media_Library::META_HASH, true );
			}

			// add it to the list.
			$listing[] = $entry;
		}

		// switch back to our blog.
		restore_current_blog();

		// the list is complete: the synchronization could delete files, which are not in it, also if all are skipped.
		$this->listed[ $term_id ] = true;

		// skip unchanged files, which exist already: they are marked as synchronized without importing them again.
		$changed = array();
		foreach ( $listing as $entry ) {
			$external_file_obj = Files::get_instance()->get_file_by_url( $entry['url'] );
			if ( $external_file_obj && $external_file_obj->is_valid() && get_post_meta( $external_file_obj->get_id(), self::META_STAMP, true ) === $entry['efml-stamp'] && Synchronization::get_instance()->is_running() ) {
				Synchronization::get_instance()->mark_as_synced( $external_file_obj );
				continue;
			}
			$changed[] = $entry;
		}

		// link the files, which the website has already as local files with the same content, instead of importing
		// them again (e.g. if it joined its group with these files). Only for files, which it does not have yet.
		$new     = array();
		$present = array();
		foreach ( $changed as $entry ) {
			$external_file_obj = Files::get_instance()->get_file_by_url( $entry['url'] );
			if ( $external_file_obj && $external_file_obj->is_valid() ) {
				$present[] = $entry;
				continue;
			}
			$new[] = $entry;
		}
		$changed = array_merge( $present, \ExternalFilesInMediaLibrary\Plugin\Network\Linking::get_instance()->link_local_files( $new, $blog_id, $term_id ) );

		// return the resulting list.
		return $changed;
	}

	/**
	 * Save the marker of the state of an imported file, so the next synchronization could skip it, if it is unchanged.
	 *
	 * @param File                $external_file_obj The external file.
	 * @param array<string,mixed> $file_data         The data of the file during the import.
	 *
	 * @return void
	 */
	public function save_stamp( File $external_file_obj, array $file_data ): void {
		// bail if no marker is given.
		if ( empty( $file_data['efml-stamp'] ) ) {
			return;
		}

		// save it.
		update_post_meta( $external_file_obj->get_id(), self::META_STAMP, (string) $file_data['efml-stamp'] );
	}

	/**
	 * Return whether the files of the given external source of this service could be listed completely, also if all
	 * files have been skipped as unchanged (then the import does not save any file).
	 *
	 * @param bool   $is_complete Whether the files could be listed completely.
	 * @param string $url         The URL of the external source.
	 * @param int    $term_id     The ID of the external source.
	 *
	 * @return bool
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function is_listing_complete( bool $is_complete, string $url, int $term_id ): bool {
		// use the marker of the listing of this request.
		if ( ! $is_complete && ! empty( $this->listed[ $term_id ] ) ) {
			unset( $this->listed[ $term_id ] );
			return true;
		}

		// return the given value.
		return $is_complete;
	}

	/**
	 * Return the URL of the given attachment of the given website, as it is used by the other websites.
	 *
	 * Must be called in the given website.
	 *
	 * We have an active Core bug here: https://core.trac.wordpress.org/ticket/25650,
	 * so we cant use wp_get_attachment_url() atm.
	 *
	 * Our solution:
	 * 1. get_attached_file() gives the absolute path for the file.
	 * 2. We strip the ABSPATH and add the domain before it.
	 *
	 * @param int $blog_id       The website.
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string
	 */
	public function get_file_url( int $blog_id, int $attachment_id ): string {
		return str_replace( ABSPATH, (string) get_blogaddress_by_id( $blog_id ), (string) get_attached_file( $attachment_id ) );
	}

	/**
	 * Return whether the given website could use the given file of the given website.
	 *
	 * Only files of the central media library of the network could be limited to some websites.
	 * Must be called in the website of the file.
	 *
	 * @param int $blog_id            The website of the file.
	 * @param int $attachment_id      The attachment ID.
	 * @param int $requesting_blog_id The website, which wants to use the file.
	 *
	 * @return bool
	 */
	private function is_file_visible( int $blog_id, int $attachment_id, int $requesting_blog_id ): bool {
		// all files of other websites are visible.
		if ( ! \ExternalFilesInMediaLibrary\Plugin\Network\Media_Library::get_instance()->is_central( $blog_id ) || $blog_id === $requesting_blog_id ) {
			return true;
		}

		// check the visibility of the file of the central media library.
		return \ExternalFilesInMediaLibrary\Plugin\Network\Media_Library::get_instance()->is_visible_for( $attachment_id, $requesting_blog_id );
	}

	/**
	 * Return all attachment for the current blog.
	 *
	 * @return WP_Query
	 */
	private function get_all_attachments(): WP_Query {
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);
		return new WP_Query( $query );
	}

	/**
	 * Return the export object for this service.
	 *
	 * @return Export_Base|false
	 */
	public function get_export_object(): Export_Base|false {
		return Export::get_instance();
	}

	/**
	 * Change the used service name if multisite import has been used.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function change_service_name( File $external_file_obj ): void {
		// bail if this is not an import or synchronization for this service.
		if ( ! $this->is_actual_service() ) {
			return;
		}

		// set the service name.
		$external_file_obj->set_service_name( $this->get_name() );
	}

	/**
	 * Return further search terms of this service, separated by commas.
	 *
	 * @return string
	 */
	protected function get_keywords(): string {
		return _x( 'multisite, network, website', 'search terms for the multisite service, separated by commas', 'external-files-in-media-library' );
	}
}
