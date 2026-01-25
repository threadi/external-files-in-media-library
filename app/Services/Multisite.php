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
	 * The public label.
	 *
	 * @var string
	 */
	protected string $label = 'Multisite';

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

		// bail if user has no capability for this service.
		if ( ! current_user_can( 'efml_cap_' . $this->get_name() ) ) {
			return;
		}

		// set title.
		$this->title = __( 'Choose file(s) from another website in your multisite', 'external-files-in-media-library' );

		// misc.
		add_filter( 'efml_filter_url_response', array( $this, 'get_multisite_files' ) );
		add_action( 'efml_after_file_save', array( $this, 'change_service_name' ) );
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

		// switch to the given blog.
		switch_to_blog( $blog_id );

		// get all media files in this blog.
		$results = $this->get_all_attachments();

		// bail on no results.
		if ( 0 === $results->found_posts ) {
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

			// get the metadata of this file.
			$meta_data = wp_get_attachment_metadata( $post->ID );

			// get its URL.
			$url = (string) wp_get_attachment_url( $post->ID );

			// collect the data for this file.
			$entry = array(
				'title'         => basename( $url ),
				'file'          => $url,
				'filesize'      => absint( $meta_data['filesize'] ), // @phpstan-ignore offsetAccess.nonOffsetAccessible
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
					'label'   => __( 'Website', 'external-files-from-aws-s3' ),
					'options' => $this->get_websites(),
					'value'   => '',
				),
			);
		}

		// return the list of fields.
		return parent::get_fields();
	}

	/**
	 * Return list of websites in this multisite.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_websites(): array {
		global $wpdb;

		// get the websites.
		$websites = $wpdb->get_results(
			$wpdb->prepare(
				"
	            SELECT blog_id
	            FROM ' . $wpdb->blogs . '
	            WHERE site_id = ' . $wpdb->siteid . '
	            AND spam = '0'
	            AND deleted = '0'
	            AND archived = '0'
	            AND blog_id != %d",
				get_current_blog_id()
			)
		);

		// prepare the list.
		$list = array(
			array(
				'label' => __( 'Choose the website', 'external-files-in-media-library' ),
				'value' => '',
			),
		);

		// add the sites to the list.
		foreach ( $websites as $website ) {
			// get the URL of this website.
			$url = get_blogaddress_by_id( $website->blog_id );

			// add it to the list.
			$list[] = array(
				'label' => $url,
				'value' => $website->blog_id,
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
		// get service from request.
		$service = filter_input( INPUT_POST, 'method', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return $results;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return $results;
		}

		// get the term ID.
		$term_id = absint( filter_input( INPUT_POST, 'term', FILTER_SANITIZE_NUMBER_INT ) );

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
		$blog_id = absint( $fields['website']['value'] );

		// switch to the given blog.
		switch_to_blog( $blog_id );

		// get the attachments from this blog.
		$attachments = $this->get_all_attachments();

		// bail on no results.
		if ( 0 === $attachments->found_posts ) {
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

			// get the metadata of this file.
			$meta_data = wp_get_attachment_metadata( $post->ID );

			// get the URL.
			$url = (string) wp_get_attachment_url( $post->ID );

			// download the URL as tmp file.
			$tmp_file = download_url( $url );

			// bail if download was not successfully.
			if ( ! is_string( $tmp_file ) ) {
				// log this event.
				Log::get_instance()->create( __( 'Could not download file for import. Error:', 'external-files-in-media-library' ) . ' <code>' . wp_json_encode( $tmp_file ) . '</code>', $url, 'error' );

				// do nothing more.
				continue;
			}

			// collect the data for this file.
			$entry = array(
				'title'         => $post->post_title,
				'local'         => false,
				'url'           => $url,
				'last-modified' => $post->post_modified,
				'filesize'      => absint( $meta_data['filesize'] ), // @phpstan-ignore offsetAccess.nonOffsetAccessible
				'mime-type'     => $post->post_mime_type,
				'tmp-file'      => $tmp_file,
			);

			// add it to the list.
			$listing[] = $entry;
		}

		// switch back to our blog.
		restore_current_blog();

		// return the resulting list.
		return $listing;
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
		// get service from request.
		$service = filter_input( INPUT_POST, 'service', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if it is not set.
		if ( is_null( $service ) ) {
			return;
		}

		// bail if service is not ours.
		if ( $this->get_name() !== $service ) {
			return;
		}

		// set the service name.
		$external_file_obj->set_service_name( $service );
	}
}
