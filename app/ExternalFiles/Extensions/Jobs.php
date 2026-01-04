<?php
/**
 * File to handle the jobs for file import handlings.
 *
 * @package external-files-in-media-library
 */

namespace ExternalFilesInMediaLibrary\ExternalFiles\Extensions;

// prevent direct access.
defined( 'ABSPATH' ) || exit;

use ExternalFilesInMediaLibrary\ExternalFiles\Extension_Base;
use ExternalFilesInMediaLibrary\ExternalFiles\File;
use ExternalFilesInMediaLibrary\ExternalFiles\Files;
use WP_Post;
use WP_Query;

/**
 * Object to handle the jobs for file import handlings.
 */
class Jobs extends Extension_Base {
	/**
	 * The internal extension name.
	 *
	 * @var string
	 */
	protected string $name = 'jobs';

	/**
	 * The job ID.
	 *
	 * @var string
	 */
	private string $job_id = '';

	/**
	 * Instance of actual object.
	 *
	 * @var ?Jobs
	 */
	private static ?Jobs $instance = null;

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
	 * @return Jobs
	 */
	public static function get_instance(): Jobs {
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
		// use our own hooks.
		add_action( 'efml_before_file_list', array( $this, 'create' ) );
		add_action( 'efml_after_file_save', array( $this, 'add_file' ) );

		// misc.
		add_filter( 'media_row_actions', array( $this, 'add_media_action' ), 20, 2 );
		add_action( 'pre_get_posts', array( $this, 'use_filter_options' ) );
	}

	/**
	 * Return the object title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Jobs', 'external-files-in-media-library' );
	}

	/**
	 * Hide this extension in settings.
	 *
	 * @return bool
	 */
	public function hide(): bool {
		return true;
	}

	/**
	 * Create the job id for this single import.
	 *
	 * @return void
	 */
	public function create(): void {
		$this->job_id = uniqid( '', true );
	}

	/**
	 * Add single file to this job ID.
	 *
	 * @param File $external_file_obj The external file object.
	 *
	 * @return void
	 */
	public function add_file( File $external_file_obj ): void {
		update_post_meta( $external_file_obj->get_id(), 'eml_job_id', $this->job_id );
	}

	/**
	 * Add filter for all files of this import job on media actions.
	 *
	 * @param array<string,string> $actions List of actions.
	 * @param WP_Post              $post The post object of the attachment.
	 *
	 * @return array<string,string>
	 */
	public function add_media_action( array $actions, WP_Post $post ): array {
		// bail if setting is disabled.
		if ( 1 !== absint( get_option( 'eml_job_show_link' ) ) ) {
			return $actions;
		}

		// get external file object by object ID.
		$external_file_obj = Files::get_instance()->get_file( $post->ID );

		// bail if it is not an external file.
		if ( ! $external_file_obj->is_valid() ) {
			return $actions;
		}

		// get the used job id.
		$job_id = get_post_meta( $external_file_obj->get_id(), 'eml_job_id', true );

		// bail if no job id is set.
		if ( empty( $job_id ) ) {
			return $actions;
		}

		// create filter url.
		$url = add_query_arg(
			array(
				'mode'          => 'list',
				'filter_job_id' => $job_id,
			),
			get_admin_url() . 'upload.php'
		);

		// add action.
		$actions['efml-filter-job'] = '<a href="' . esc_url( $url ) . '">' . __( 'Show job', 'external-files-in-media-library' ) . '</a>';

		// return the resulting list of actions.
		return $actions;
	}

	/**
	 * Use the filter options.
	 *
	 * @param WP_Query $query The WP_Query object.
	 *
	 * @return void
	 */
	public function use_filter_options( WP_Query $query ): void {
		// get filter value.
		$filter = filter_input( INPUT_GET, 'filter_job_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		// bail if filter is not set.
		if ( is_null( $filter ) ) {
			return;
		}

		// get the meta query.
		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		// extend the filter.
		$query->set(
			'meta_query',
			array_merge(
				$meta_query,
				array(
					array(
						'key'     => 'eml_job_id',
						'value'   => $filter,
						'compare' => '=',
					),
				)
			)
		);
	}
}
